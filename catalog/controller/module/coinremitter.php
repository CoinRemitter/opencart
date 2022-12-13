<?php 
namespace Opencart\Catalog\Controller\Extension\Coinremitter\Module;
class Coinremitter extends \Opencart\System\Engine\Controller {

	private $obj_curl;

	public function __construct($registry){
		parent::__construct($registry);
		
		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$this->obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);
		

	}

	public function changeOrderStatus($data=[]){
		$orderIds = [];

		if(!empty($data)){
			$orderIds = $data['order_ids'];
		}else if(isset($this->request->post['order_ids'])){
			$orderIds = $this->request->post['order_ids'];
		}
		
		if(empty($orderIds)){
			return false;
		}

		$this->load->model('extension/payment/coinremitter');	
		$this->load->model('checkout/order');
		foreach ($orderIds as $orderId) {
			/*** check if order id exists in coinremitter_order or not ***/
			$order_info = $this->model_extension_payment_coinremitter->getOrder($orderId);
			$order_cart = $this->model_checkout_order->getOrder($orderId);
			if(!empty($order_info) && !empty($order_cart)){

				if($order_info['payment_status'] == 'pending' || $order_info['payment_status'] == 'under paid'){

					$coin = $order_info['coin'];
					$address = $order_info['address'];

					/*** now get wallet data from oc_coinremitter_wallet with use of coin ***/
					$wallet_info = $this->model_extension_payment_coinremitter->getWallet($coin);
					
					if(!empty($wallet_info)){
						/*** Now get all transactions by address from api ***/
						$get_trx_params = array(
							'url'		=> 'get-transaction-by-address',
							'api_key'	=>	$wallet_info['api_key'],
		                    'password'	=>	$wallet_info['password'],
		                    'coin'		=>	$coin,
		                    'address'	=> $address
						);

						$getTransactionByAddressRes = $this->obj_curl->commonApiCall($get_trx_params);
						if(!empty($getTransactionByAddressRes) && isset($getTransactionByAddressRes['flag']) && $getTransactionByAddressRes['flag'] == 1){

							if(!empty($getTransactionByAddressRes['data'])){

								$getTrxByAddData = $getTransactionByAddressRes['data'];

								/*** Get sum of paid amount of all transations which have 3 or more than 3 confirmtions  ***/

								$total_paid = 0;
								
								for ($i=0; $i < count($getTrxByAddData); $i++) { 

									$trx = $getTrxByAddData[$i];

									if(isset($trx['type']) && $trx['type'] == 'receive'){

										/*** Insertion in coinremitter_webhook start ***/
										/*** now check if transaction exists in oc_coinremitter_webhook or not if does not exist then insert else update confirmations ***/
											
										$webhook_info = $this->model_extension_payment_coinremitter->getWebhook($trx['id']);
										if(empty($webhook_info)){
											//insert record
											$insert_arr = array(

												'order_id' => $order_info['order_id'],
												'address' => $trx['address'],
												'transaction_id' => $trx['id'],
												'txId' => $trx['txid'],
												'explorer_url' => $trx['explorer_url'],
												'paid_amount' => $trx['amount'],
												'coin' => $trx['coin_short_name'],
												'confirmations' => $trx['confirmations'],
												'paid_date' => $trx['date']
											);

											$this->model_extension_payment_coinremitter->addWebhook($insert_arr);

										}else{
											//update confirmations if confirmation is less than 3
											if($webhook_info['confirmations'] < 3){
												$update_confirmation = array(
													'confirmations' => $trx['confirmations'] <= 3 ? $trx['confirmations'] : 3 
												);
												$this->model_extension_payment_coinremitter->updateTrxConfirmation($webhook_info['transaction_id'],$update_confirmation);
											} 
										}

										/*** Insertion in coinremitter_webhook end ***/

										if($trx['confirmations'] >= 3){
											$total_paid = $total_paid + $trx['amount'];
										}
									}
								}

								$total_paid = number_format($total_paid,8,'.','');

								$status = '';
								if($total_paid == $order_info['crp_amount']){
									$status = 'paid';
								}else if($total_paid > $order_info['crp_amount']){
									$status = 'over paid';
								}else if($total_paid != 0 && $total_paid < $order_info['crp_amount']){
									$status = 'under paid';
								}
								
								if($status != '' && $status != $order_info['payment_status']){

									//update payment_status,status
									$update_arr = array('payment_status' => $status);
									$this->model_extension_payment_coinremitter->updateOrder($order_info['order_id'],$update_arr);
									$update_arr = array('status' => ucfirst($status));
									$this->model_extension_payment_coinremitter->updatePaymentStatus($order_info['order_id'],$update_arr);

									if(($status == 'paid' || $status == 'over paid') && $order_cart['order_status'] == 'Pending'){
										$order_info['payment_status'] = $status;
										$order_info['status'] = ucfirst($status);
										$comments = $this->getOrderSuccessComments($getTrxByAddData,$order_info);
										$this->model_checkout_order->addOrderHistory($order_info['order_id'], 5, $comments);  // 5 = Complete
									}
								}

							}else{
								if($order_info['payment_status'] == 'pending'){

									/*** check if expired time of invoice is defined or not. If defined and invoice time is expired then change invoice status as expired  ***/
									if(isset($order_info['expire_on']) && $order_info['expire_on'] != ''){
										if(time() >= strtotime($order_info['expire_on'])){
											
											//update payment_status,status as expired
											$status = 'expired';
											$update_arr = array('payment_status' => $status);
											$this->model_extension_payment_coinremitter->updateOrder($order_info['order_id'],$update_arr);
											$update_arr = array('status' => ucfirst($status));
											$this->model_extension_payment_coinremitter->updatePaymentStatus($order_info['order_id'],$update_arr);
	
											if($order_cart['order_status'] != 'Canceled'){
												/*** Update order history status to canceled, add comment  ***/
												$comments = 'Order #'.$order_info['order_id'];
												$is_customer_notified = true;
												$this->model_checkout_order->addOrderHistory($order_info['order_id'], 7, $comments, $is_customer_notified);  // 7 = Canceled
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}


	private function getOrderSuccessComments($getTrxByAddData=[],$order_info=[]){
		$comments = '';
		if(empty($getTrxByAddData) || empty($order_info)){
			return $comments;
		}

		$total_paid = 0;
		
        $transaction_ids = '';
        foreach ($getTrxByAddData as $trx) {
        	$transaction_ids .= '<a href="'.$trx['explorer_url'].'" target="_blank">'.$trx['txid'].'</a> - '.$trx['amount'].' '.$order_info['coin'].'<br />Date : '.$trx['date'].'<br /><br />';

        	if($trx['confirmations'] >= 3){
				$total_paid = $total_paid + $trx['amount'];
			}
        }
        $total_paid = number_format($total_paid,8,'.','');
		$pending = number_format($order_info['crp_amount'] - $total_paid,8,'.','');
        $total_amount = $order_info['crp_amount'];

        $enc_order_id = urlencode($this->obj_curl->encrypt($order_info['order_id']));  // order id in encryption format
        $order_url = $this->url->link('extension/coinremitter/module/coinremitter_invoice|detail&order_id='.$enc_order_id);

        $comments .= 'Coinremitter Order - <a href="'.$order_url.'" target="_blank">#'.$order_info['order_id'].'</a> '.$order_info['status'].' <br /><br />';

        $comments .= 'Base Currency : '.$order_info['base_currancy'].'<br />';
        $comments .= 'Coin : '.$order_info['coin'].'<br />';
        $comments .= 'Created Date : '.$order_info['created_at'].' (UTC) <br />';
        $comments .= 'Expiry Date : '.$order_info['expire_on'].' (UTC) <br />';
        $comments .= 'Description : '.$order_info['description'].'<br /><br />';


        $comments .= 'Transaction Ids:<br />';
        $comments .= $transaction_ids;

        $comments .= '<br />Order Amount:<br />';
		$comments .= $total_amount.' '.$order_info['coin'];

        $comments .= '<br /><br /> Paid Amount:<br />';
        $comments .= $total_paid.' '.$order_info['coin'];

        $comments .= '<br /><br /> Pending Amount:<br />';
        $comments .= $pending.' '.$order_info['coin'];

		return $comments;
	}

	public function view_account_order_list_before(&$route, &$args, &$output){
		
		$this->load->model('extension/payment/coinremitter');	
		
		$orderIds = [];
		$orders = $args['orders']??[];

		foreach ($orders as $order) {
			$orderId = $order['order_id'];
			/*** check if order is of coinremitter's order or not ***/
			$order_detail = $this->model_extension_payment_coinremitter->getOrder($orderId);
			if($order_detail){
				$orderIds[] = $orderId;
			}
		}

		if(!empty($orderIds)){
			$data = array('order_ids' => $orderIds);
			$this->changeOrderStatus($data);
		}
	}

	public function view_account_order_info_before(&$route, &$args, &$output){
		$this->load->model('extension/payment/coinremitter');	

		$orderId = $args['order_id']??0;
		$order_detail = $this->model_extension_payment_coinremitter->getOrder($orderId);

		if($order_detail){
			$data = array('order_ids' => [$orderId]);
			$this->changeOrderStatus($data);
		}

	}
}