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
							'api_key'	=>	$wallet_info['api_key'],
		                    'password'	=>	$wallet_info['password'],
		                    'address'	=> $address
						);

						$getTransactionByAddressRes = $this->obj_curl->apiCall('/wallet/address/transactions',$get_trx_params);
						if($getTransactionByAddressRes['success']){

							if(!empty($getTransactionByAddressRes['data']['transactions'])){

								$getTrxByAddData = $getTransactionByAddressRes['data'];
								$allTrx = $getTrxByAddData['transactions'];

								/*** Get sum of paid amount of all transations which have 3 or more than 3 confirmtions  ***/

								$total_paid = 0;
								
								for ($i=0; $i < count($allTrx); $i++) { 

									$trx = $allTrx[$i];

									if(isset($trx['type']) && $trx['type'] == 'receive'){

										/*** Insertion in coinremitter_webhook start ***/
										/*** now check if transaction exists in oc_coinremitter_webhook or not if does not exist then insert else update confirmations ***/
											
										$webhook_info = $this->model_extension_payment_coinremitter->getWebhook($trx['id']);
										if(empty($webhook_info)){
											//insert record
											$insert_arr = array(

												'order_id' => $order_info['order_id'],
												'address' => $getTrxByAddData['address'],
												'transaction_id' => $trx['id'],
												'txId' => $trx['txid'],
												'explorer_url' => $trx['explorer_url'],
												'paid_amount' => $trx['amount'],
												'coin' => $getTrxByAddData['coin_symbol'],
												'confirmations' => $trx['confirmations'],
												'paid_date' => $trx['date']
											);

											$this->model_extension_payment_coinremitter->addWebhook($insert_arr);
												$total_paid_res = $this->model_extension_payment_coinremitter->getTotalPaidAmountByAddress($address);

												if(isset($total_paid_res['total_paid']) && $total_paid_res['total_paid'] > 0 ){
													$total_paid = $total_paid_res['total_paid'];
												}
												
												$status = '';
												if($total_paid == $order_info['crp_amount']){
													$status = 'paid';
												}else if($total_paid > $order_info['crp_amount']){
													$status = 'over paid';
												}else if($total_paid != 0 && $total_paid < $order_info['crp_amount']){
													$status = 'under paid';
												}
										
												if($status != ''){
													//update payment_status,status
													$update_arr = array('payment_status' => $status);
													$this->model_extension_coinremitter_payment_coinremitter->updateOrder($orderId,$update_arr);
													$update_arr = array('status' => ucfirst($status));
													$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($orderId,$update_arr);

													if($status == 'paid' || $status == 'over paid' || $status == 'under paid'){
														/*** Update order status as complete ***/
														$this->add_paid_order_history($orderId);
													}
												}

										}else{
											//update confirmations if confirmation is less than 3
											if($webhook_info['confirmations'] < 3){
												$update_confirmation = array(
													'confirmations' => $trx['confirmations']
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

								// $total_paid = number_format($total_paid,8,'.','');

								// $status = '';
								// if($total_paid == $order_info['crp_amount']){
								// 	$status = 'paid';
								// }else if($total_paid > $order_info['crp_amount']){
								// 	$status = 'over paid';
								// }else if($total_paid != 0 && $total_paid < $order_info['crp_amount']){
								// 	$status = 'under paid';
								// }
								
								// if($status != '' && $status != $order_info['payment_status']){

								// 	//update payment_status,status
								// 	$update_arr = array('payment_status' => $status);
								// 	$this->model_extension_payment_coinremitter->updateOrder($order_info['order_id'],$update_arr);
								// 	$update_arr = array('status' => ucfirst($status));
								// 	$this->model_extension_payment_coinremitter->updatePaymentStatus($order_info['order_id'],$update_arr);

								// 	if(($status == 'paid' || $status == 'over paid') && $order_cart['order_status'] == 'Pending'){
								// 		$order_info['payment_status'] = $status;
								// 		$order_info['status'] = ucfirst($status);
								// 		$comments = $this->getOrderSuccessComments($getTrxByAddData,$order_info);
								// 		$this->model_checkout_order->addOrderHistory($order_info['order_id'], 5, $comments);  // 5 = Complete
								// 	}
								// }

							}else{
								if($order_info['payment_status'] == 'pending'){

									/*** check if expired time of invoice is defined or not. If defined and invoice time is expired then change invoice status as expired  ***/
									if(isset($order_info['expire_on']) && $order_info['expire_on'] != ''){
										if(time() >= strtotime($order_info['expire_on'])){
											
											//update payment_status,status as expired
											$status = 'expired';
											$update_arr = array('payment_status' => $status);
											$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'],$update_arr);
											$update_arr = array('status' => ucfirst($status));
											$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($order_info['order_id'],$update_arr);
	
											if($order_cart['order_status'] != 'Canceled'){
												/*** Update order history status to canceled, add comment  ***/
												$comments = 'Order #'.$order_info['order_id'];
												$is_customer_notified = true;
												$this->model_checkout_order->addHistory($order_info['order_id'], 7, $comments, $is_customer_notified);  // 7 = Canceled
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

        $comments .= 'Fiat Currency : '.$order_info['fiat_currancy'].'<br />';
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

	private function add_paid_order_history($orderId)
	{

		$this->load->model('extension/coinremitter/payment/coinremitter');
		$this->load->model('checkout/order');
		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($orderId);

		if (empty($order_info)) {
			return false;
		}
		$enc_order_id = urlencode($this->obj_curl->encrypt($order_info['order_id']));  // order id in encryption format
		$order_url = $this->url->link('extension/coinremitter/module/coinremitter_invoice|detail&order_id=' . $enc_order_id);
		$transaction_ids = '';
		$order_info['transaction_meta'] = json_decode($order_info['transaction_meta'], true);
		foreach ($order_info['transaction_meta'] as $trx) {
			$transaction_ids .= '<a href="' . $trx['explorer_url'] . '" target="_blank">' . $trx['txid'] . '</a> - ' . $trx['amount'] . ' ' . $order_info['coin_symbol'] . '<br />Date : ' . $trx['date'] . '<br /><br />';
		}
		$total_amount = $order_info['crypto_amount'];
		$paid_amount = number_format($order_info['paid_crypto_amount'], 8, '.', '');
		$pending = number_format($total_amount - $paid_amount, 8, '.', '');

		$comments = '';
		$comments .= 'Coinremitter Order - <a href="' . $order_url . '" target="_blank">#' . $order_info['order_id'] . '</a><br /><br />';
		$comments .= 'Fiat Currency : ' . $order_info['fiat_symbol'] . '<br />';
		$comments .= 'Coin : ' . $order_info['coin_symbol'] . '<br />';
		$comments .= 'Created Date : ' . $order_info['created_at'] . ' (UTC) <br />';
		$comments .= 'Expiry Date : ' . $order_info['expiry_date'] . ' (UTC) <br />';

		$comments .= 'Transaction :<br />';
		$comments .= $transaction_ids;

		$comments .= '<br />Order Amount:<br />';
		$comments .= $total_amount . ' ' . $order_info['coin_symbol'];

		$comments .= '<br /><br /> Paid Amount:<br />';
		$comments .= $paid_amount . ' ' . $order_info['coin_symbol'];

		$comments .= '<br /><br /> Pending Amount:<br />';
		$comments .= $pending . ' ' . $order_info['coin_symbol'];


		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory($orderId, 5, $comments);
	}
}