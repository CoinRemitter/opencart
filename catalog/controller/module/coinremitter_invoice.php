<?php
namespace Opencart\Catalog\Controller\Extension\Coinremitter\Module;
use Opencart\System\Library\Log;
class CoinremitterInvoice extends \Opencart\System\Engine\Controller {

	private $obj_curl;

	public function __construct($registry){
		parent::__construct($registry);
		
		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$this->obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);
		
	}

	public function detail() {

		$enc_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';
		$order_id = $this->obj_curl->decrypt($enc_order_id);

		//check if order id exists in coinremitter_order or not
		$this->load->model('extension/coinremitter/payment/coinremitter');	
		$coinremitter_order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($order_id);

		if(empty($coinremitter_order_info)){
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		$this->load->language('extension/coinremitter/payment/coinremitter');

		/*** clear cart and unset session only if order is of coinremitter's order  ***/

		if (isset($this->session->data['order_id']) && $this->session->data['order_id'] == $order_id) {
			$this->cart->clear();

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['guest']);
			unset($this->session->data['comment']);
			unset($this->session->data['order_id']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['voucher']);
			unset($this->session->data['vouchers']);
			unset($this->session->data['totals']);
		}

		$data['coinremitter_order_info'] = $coinremitter_order_info;

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if ($order_info) {

			$data['order_id'] = $order_id;
			$data['enc_order_id'] = $enc_order_id;
			$data['date_added'] = date($this->language->get('date_format_short'), strtotime($order_info['date_added']));

			if ($order_info['payment_address_format']) {
				$format = $order_info['payment_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['payment_firstname'],
				'lastname'  => $order_info['payment_lastname'],
				'company'   => $order_info['payment_company'],
				'address_1' => $order_info['payment_address_1'],
				'address_2' => $order_info['payment_address_2'],
				'city'      => $order_info['payment_city'],
				'postcode'  => $order_info['payment_postcode'],
				'zone'      => $order_info['payment_zone'],
				'zone_code' => $order_info['payment_zone_code'],
				'country'   => $order_info['payment_country']
			);

			$data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['payment_method'] = $order_info['payment_method'];

			if ($order_info['shipping_address_format']) {
				$format = $order_info['shipping_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['shipping_firstname'],
				'lastname'  => $order_info['shipping_lastname'],
				'company'   => $order_info['shipping_company'],
				'address_1' => $order_info['shipping_address_1'],
				'address_2' => $order_info['shipping_address_2'],
				'city'      => $order_info['shipping_city'],
				'postcode'  => $order_info['shipping_postcode'],
				'zone'      => $order_info['shipping_zone'],
				'zone_code' => $order_info['shipping_zone_code'],
				'country'   => $order_info['shipping_country']
			);

			$data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['shipping_method'] = $order_info['shipping_method'];

			$this->load->model('catalog/product');
			$this->load->model('tool/upload');
			$this->load->model('tool/image');

			// Products
			$data['products'] = array();

			$products = $this->model_checkout_order->getProducts($order_id);

			foreach ($products as $product) {
				$option_data = array();

				$options = $this->model_checkout_order->getOptions($order_id, $product['order_product_id']);

				foreach ($options as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name'  => $option['name'],
						'value' => (strlen(utf8_decode($value)) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				$product_info = $this->model_catalog_product->getProduct($product['product_id']);

				if (!empty($product_info) && is_file(DIR_IMAGE . $product_info['image'])) {
					$thumb = $this->model_tool_image->resize($product_info['image'], 100, 100);
				} else {
					$thumb = $this->model_tool_image->resize('no_image.png', 100, 100);
				}

				$data['products'][] = array(
					'name'     => $product['name'],
					'model'    => $product['model'],
					'option'   => $option_data,
					'quantity' => $product['quantity'],
					'thumb'    => $thumb,
					'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
					'total'    => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			// Totals
			$data['totals'] = array();

			$totals = $this->model_checkout_order->getTotals($order_id);

			foreach ($totals as $total) {
				$data['totals'][] = array(
					'title' => $total['title'],
					'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
				);
			}
			$orderData =	$this->model_checkout_order->getOrder($order_id);
			$this->document->setTitle($this->language->get('invoice_title'));

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$this->document->addStyle('extension/coinremitter/catalog/view/javascript/coinremitter/css/coinremitter_invoice.css');
			$this->document->addScript('extension/coinremitter/catalog/view/javascript/coinremitter/js/coinremitter_invoice.js');
			$data['header'] = $this->load->controller('common/header');
			$data['footer'] = $this->load->controller('common/footer');
			$data['order_status'] = ($orderData['order_status_id'] == 1)?'':$orderData['order_status'];
			
			$this->response->setOutput($this->load->view('extension/coinremitter/module/coinremitter_invoice', $data));
		}else{
			return new \Opencart\System\Engine\Action('error/not_found');
		}

	}


	public function payment_history(){

		$json = array();
		
		if(isset($this->request->post['address']) && $this->request->post['address'] != ''){

			$address = $this->request->post['address'];

			/*** check if address exists in oc_coinremitter_order ***/
			$this->load->model('extension/coinremitter/payment/coinremitter');
			$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrderByAddress($address);
			if(!empty($order_info)){
				
				$json['enc_order_id'] = urlencode($this->obj_curl->encrypt($order_info['order_id']));

				$isShowDataFromCoinremitterWebhookTable = 0;

				if($order_info['payment_status'] == 'pending' || $order_info['payment_status'] == 'under paid'){

					$coin = $order_info['coin'];

					/*** now get wallet data from oc_coinremitter_wallet with use of coin ***/
					$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWallet($coin);
					
					if(empty($wallet_info)){
						$json['flag'] = 0;
						$json['msg'] = "Wallet not found";

						$this->response->addHeader('Content-Type: application/json');
						$this->response->setOutput(json_encode($json));	
						return false;
					}

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
							$resTrxData = array();
							
							for ($i=0; $i < count($getTrxByAddData); $i++) { 

								$trx = $getTrxByAddData[$i];

								if(isset($trx['type']) && $trx['type'] == 'receive'){

									/*** Insertion in coinremitter_webhook start ***/
									/*** now check if transaction exists in oc_coinremitter_webhook or not if does not exist then insert else update confirmations ***/
										
									if ($trx['confirmations'] >= 3) {
										$total_paid = $total_paid + $trx['amount'];
									}
									$resTrxData[$i]['txId'] = substr($trx['txid'], 0, 20) . '...';
									$resTrxData[$i]['explorer_url'] = $trx['explorer_url'];
									$resTrxData[$i]['coin'] = $trx['coin_short_name'];
									$resTrxData[$i]['paid_amount'] = $trx['amount'];
									$resTrxData[$i]['confirmations'] = $trx['confirmations'];
									$resTrxData[$i]['paid_date'] = date('M d, Y H:i:s', strtotime($trx['date']));
									$resTrxData[$i]['now_time'] = date('M d, Y H:i:s');

									$webhook_info = $this->model_extension_coinremitter_payment_coinremitter->getWebhook($trx['id']);
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

										$this->model_extension_coinremitter_payment_coinremitter->addWebhook($insert_arr);
									
										$total_paid = number_format($total_paid, 8, '.', '');

										$status = '';
										if ($total_paid == $order_info['crp_amount']) {
											$status = 'paid';
										} else if ($total_paid > $order_info['crp_amount']) {
											$status = 'over paid';
										} else if ($total_paid != 0 && $total_paid < $order_info['crp_amount']) {
											$status = 'under paid';
										}
										if ($status != '') {
											//update payment_status,status
											$update_arr = array('payment_status' => $status);

											
											$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'], $update_arr);
											$update_arr = array('status' => ucfirst($status));
											$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($order_info['order_id'], $update_arr);
											if ($status == 'paid' || $status == 'over paid' || $status == 'under paid') {
												$this->add_order_success_history($order_info['order_id']);
											}
										}	

									}else{
										//update confirmations if confirmation is less than 3
										if($webhook_info['confirmations'] < 3){
											$update_confirmation = array(
												'confirmations' => $trx['confirmations'] <= 3 ? $trx['confirmations'] : 3 
											);
											$this->model_extension_coinremitter_payment_coinremitter->updateTrxConfirmation($webhook_info['transaction_id'],$update_confirmation);
										} 
									}

									/*** Insertion in coinremitter_webhook end ***/
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
							
							if($status != ''){
								//update payment_status,status
								$update_arr = array('payment_status' => $status);
								$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'],$update_arr);
								$update_arr = array('status' => ucfirst($status));
								$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($order_info['order_id'],$update_arr);

								if($status == 'paid' || $status == 'over paid'){

									$json['flag'] = 1;
									$json['msg'] = "Success";
									$json['is_success'] = 1;
									
									$this->response->addHeader('Content-Type: application/json');
									$this->response->setOutput(json_encode($json));	
									return false;
								}
							}

							$pending = ($order_info['crp_amount'] - $total_paid) > 0 ? $order_info['crp_amount'] - $total_paid : 0;

							$json['flag'] = 1;
							$json['msg'] = 'success';
							$json['data'] = array(
								'payment_data' => $resTrxData,
								'total_paid' => $total_paid,
								'pending' => number_format($pending,8,'.',''),
								'coin' => $order_info['coin'],
								'nopayment' => 1,
								'expire_on' => '',
								'order_status' => $status
							);

							$this->response->addHeader('Content-Type: application/json');
							$this->response->setOutput(json_encode($json));
							return false;

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
	
										$this->load->model('checkout/order');
										$order_cart = $this->model_checkout_order->getOrder($order_info['order_id']);
	
										$json['flag'] = 1;
										$json['msg'] = 'No payment history found';
										$json['data'] = array(
											'payment_data' => [],
											'total_paid' => number_format(0,8,'.',''),
											'pending' => number_format($order_info['crp_amount'],8,'.',''),
											'coin' => $order_info['coin'],
											'nopayment' => 2,
											'expire_on' => '',
											'is_simply_display_detail' => 1,
											'order_status' => 'Expired'
										);
	
										$this->response->addHeader('Content-Type: application/json');
										$this->response->setOutput(json_encode($json));	
										return false;
									}
								}
	
								$nopayment = 0;
								$expire_on = '';
								if(isset($order_info['expire_on']) && $order_info['expire_on'] != ''){
									$expire_on = date('M d, Y H:i:s',strtotime($order_info['expire_on']));
								}else{
									$nopayment = 1;
								}
	
								$json['flag'] = 1;
								$json['msg'] = 'No payment history found';
								$json['data'] = array(
									'payment_data' => [],
									'total_paid' => number_format(0,8,'.',''),
									'pending' => number_format($order_info['crp_amount'],8,'.',''),
									'coin' => $order_info['coin'],
									'nopayment' => $nopayment,
									'expire_on' => $expire_on
								);
	
								$this->response->addHeader('Content-Type: application/json');
								$this->response->setOutput(json_encode($json));	
								return false;
	
							}else{
								$isShowDataFromCoinremitterWebhookTable = 1;
							}
						}
					}else{
						$isShowDataFromCoinremitterWebhookTable = 1;
					}
				}else{
					$isShowDataFromCoinremitterWebhookTable = 1;
				}

				/*** If admin changed API Key or if API call fails or order status is other then 'pending','under paid' then data will be got from coinremitter webhook table ***/
				if($isShowDataFromCoinremitterWebhookTable == 1){

					/*** get data from oc_coinremitte_webhook ***/
					$getWebhookByAddressRes = $this->model_extension_coinremitter_payment_coinremitter->getWebhookByAddress($address);
					
					$total_paid = 0;
					$resTrxData = [];
					if($getWebhookByAddressRes){
						for ($i=0; $i < count($getWebhookByAddressRes); $i++) { 
							$trx = $getWebhookByAddressRes[$i];
							if($trx['confirmations'] >= 3){
								$total_paid += $trx['paid_amount'];
							}

							$resTrxData[$i]['txId'] = substr($trx['txId'],0,20).'...';
							$resTrxData[$i]['explorer_url'] = $trx['explorer_url'];
							$resTrxData[$i]['coin'] = $trx['coin'];
							$resTrxData[$i]['paid_amount'] = $trx['paid_amount'];
							$resTrxData[$i]['confirmations'] = $trx['confirmations'];
							$resTrxData[$i]['paid_date'] = date('M d, Y H:i:s',strtotime($trx['paid_date']));
							$resTrxData[$i]['now_time'] = date('M d, Y H:i:s');
						}
					}
					
					$total_paid = number_format($total_paid,8,'.','');
					$pending = ($order_info['crp_amount'] - $total_paid) > 0 ? $order_info['crp_amount'] - $total_paid : 0;

					$json['flag'] = 1;
					$json['msg'] = !empty($resTrxData) ? 'Success' : 'No payment history found';
					$json['data'] = array(
						'payment_data' => $resTrxData,
						'total_paid' => $total_paid,
						'pending' => number_format($pending,8,'.',''),
						'coin' => $order_info['coin'],
						'nopayment' => 2,
						'expire_on' => '',
						'is_simply_display_detail' => 1,
						'order_status' => ucfirst($order_info['payment_status'])
					);
					$total_paid = number_format($total_paid,8,'.','');

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
						$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'],$update_arr);
						$update_arr = array('status' => ucfirst($status));
						$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($order_info['order_id'],$update_arr);

						if($status == 'paid' || $status == 'over paid'){

							$json['flag'] = 1;
							$json['msg'] = "Success";
							$json['is_success'] = 1;
							
							$this->response->addHeader('Content-Type: application/json');
							$this->response->setOutput(json_encode($json));	
							return false;
						}
					}

					$this->response->addHeader('Content-Type: application/json');
					$this->response->setOutput(json_encode($json));
					return false;
				}
			}else{
				$json['flag'] = 0;
				$json['msg'] = 'Invalid Address';	
			}
		}else{
			$json['flag'] = 0;
			$json['msg'] = 'Invalid address';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
		
	}

	public function expired(){

		$enc_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';
		$orderId = $this->obj_curl->decrypt($enc_order_id);
		
		if($orderId == ''){
			return new \Opencart\System\Engine\Action('error/not_found');	
		}
		//check if order id exists in coinremitter_order or not
		$this->load->model('extension/coinremitter/payment/coinremitter');	
		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($orderId);
		
		if(empty($order_info)){
			return new \Opencart\System\Engine\Action('error/not_found');		
		}
		$this->load->model('checkout/order');
		$order_cart = $this->model_checkout_order->getOrder($orderId);
		
		if($order_cart['order_status'] != 'Canceled'){

			if(isset($order_info['expire_on']) && $order_info['expire_on'] != ''){
				if(time() >= strtotime($order_info['expire_on'])){
					/*** Get webhook by address***/
					$getWebhookByAddressRes = $this->model_extension_coinremitter_payment_coinremitter->getWebhookByAddress($order_info['address']);	
					if(empty($getWebhookByAddressRes)){

						/*** Check if any transaction done or not for this address ***/

						$coin = $order_info['coin'];
						$address = $order_info['address'];

						/*** get wallet data from oc_coinremitter_wallet with use of coin ***/
						$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWallet($coin);

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
								
								if(empty($getTransactionByAddressRes['data'])){
									//update payment_status,status as expired
									$status = 'expired';
									$update_arr = array('payment_status' => $status);
									$this->model_extension_coinremitter_payment_coinremitter->updateOrder($orderId,$update_arr);
									$update_arr = array('status' => ucfirst($status));
									$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($orderId,$update_arr);

									/*** Update order history status to canceled, add comment  ***/
									$comments = 'Order #'.$orderId;
									$is_customer_notified = true;
									$this->model_checkout_order->addHistory($orderId, 7, $comments, $is_customer_notified);  // 7 = Canceled
									return new \Opencart\System\Engine\Action('checkout/failure');	
								}else{									
									$getTrxByAddData = $getTransactionByAddressRes['data'];
		
									/*** Get sum of paid amount of all transations which have 3 or more than 3 confirmtions  ***/
		
									for ($i=0; $i < count($getTrxByAddData); $i++) { 
		
										$trx = $getTrxByAddData[$i];
		
										if(isset($trx['type']) && $trx['type'] == 'receive'){
		
											/*** Insertion in coinremitter_webhook start ***/
											/*** now check if transaction exists in oc_coinremitter_webhook or not if does not exist then insert else update confirmations ***/
											$webhook_info = $this->model_extension_coinremitter_payment_coinremitter->getWebhook($trx['id']);
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
		
												$this->model_extension_coinremitter_payment_coinremitter->addWebhook($insert_arr);
												$status = 'expired';
												$update_arr = array('payment_status' => $status);
												$this->model_extension_coinremitter_payment_coinremitter->updateOrder($orderId,$update_arr);
												$update_arr = array('status' => ucfirst($status));
												$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($orderId,$update_arr);
												$this->add_order_success_history($order_info['order_id']);
											}else{
												//update confirmations if confirmation is less than 3
												if($webhook_info['confirmations'] < 3){
													$update_confirmation = array(
														'confirmations' => $trx['confirmations'] <= 3 ? $trx['confirmations'] : 3 
													);
													$this->model_extension_coinremitter_payment_coinremitter->updateTrxConfirmation($webhook_info['transaction_id'],$update_confirmation);
												} 
											}
										}		
									}
									return new \Opencart\System\Engine\Action('checkout/failure');	
								}	
							}
							// if(!empty($getTransactionByAddressRes) && isset($getTransactionByAddressRes['flag']) && $getTransactionByAddressRes['flag'] == 1 && empty($getTransactionByAddressRes['data']) ){

							// 	//update payment_status,status as expired
							// 	$status = 'expired';
							// 	$update_arr = array('payment_status' => $status);
							// 	$this->model_extension_coinremitter_payment_coinremitter->updateOrder($orderId,$update_arr);
							// 	$update_arr = array('status' => ucfirst($status));
							// 	$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($orderId,$update_arr);

							// 	/*** Update order history status to canceled, add comment  ***/
			                //     $comments = 'Order #'.$orderId;
			                //     $is_customer_notified = true;
			                //     $this->model_checkout_order->addHistory($orderId, 7, $comments, $is_customer_notified);  // 7 = Canceled

			                //     return new \Opencart\System\Engine\Action('checkout/failure');	
							// }
							else{
								return new \Opencart\System\Engine\Action('error/not_found');	
							}
						}else{
							return new \Opencart\System\Engine\Action('error/not_found');
						}
					}else{
						return new \Opencart\System\Engine\Action('error/not_found');
					}
				}else{
					return new \Opencart\System\Engine\Action('error/not_found');
				}
			}else{
				return new \Opencart\System\Engine\Action('error/not_found');
			}
		}else{
			return new \Opencart\System\Engine\Action('checkout/failure');
		}
		return new \Opencart\System\Engine\Action('checkout/failure');
	}


	public function success(){

		$enc_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';
		$orderId = $this->obj_curl->decrypt($enc_order_id);

		if($orderId == ''){
			return new \Opencart\System\Engine\Action('error/not_found');	
		}
		//check if order id exists in coinremitter_order or not
		$this->load->model('extension/coinremitter/payment/coinremitter');	
		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($orderId);
		
		if(empty($order_info)){
			return new \Opencart\System\Engine\Action('error/not_found');	
		}
		
		$this->load->model('checkout/order');
		$order_cart = $this->model_checkout_order->getOrder($orderId);
		
		if(strtolower($order_info['payment_status']) == 'paid' || strtolower($order_info['payment_status']) == 'over paid'){
			//$this->add_order_success_history($orderId);
			return new \Opencart\System\Engine\Action('checkout/success');	
		}else{
			return new \Opencart\System\Engine\Action('error/not_found');	
		}
		// if($order_cart['order_status'] == 'Pending'){

		// }else{
		// 	return new \Opencart\System\Engine\Action('error/not_found');	
		// }
		
	}


	private function add_order_success_history($orderId){

		$this->load->model('checkout/order');
		$order_cart = $this->model_checkout_order->getOrder($orderId);
		// if(!empty($order_cart) && $order_cart['order_status'] == 'Pending'){
			if(!empty($order_cart)){

			//check if order id exists in coinremitter_order or not
			$this->load->model('extension/coinremitter/payment/coinremitter');	
			$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($orderId);
			
			$log = new Log('system.log');
			$log->write(json_encode($order_info));
			
			if(!empty($order_info) && (strtolower($order_info['payment_status']) == 'paid' || strtolower($order_info['payment_status']) == 'over paid' || strtolower($order_info['payment_status']) == 'under paid' || strtolower($order_info['payment_status']) == 'expired')){

				$getWebhookByAddressRes = $this->model_extension_coinremitter_payment_coinremitter->getWebhookByAddress($order_info['address']);	

				if(!empty($getWebhookByAddressRes)){

					/*** Get sum of paid amount of all transations which have 3 or more than 3 confirmtions  ***/
					$total_paid_res = $this->model_extension_coinremitter_payment_coinremitter->getTotalPaidAmountByAddress($order_info['address']);

					$total_paid = 0;
					if(isset($total_paid_res['total_paid']) && $total_paid_res['total_paid'] > 0 ){
						$total_paid = $total_paid_res['total_paid'];
					}

					$pending = number_format($order_info['crp_amount'] - $total_paid,8,'.','');
					$paid_amount = number_format($total_paid,8,'.','');
			        $total_amount = $order_info['crp_amount'];

					/*** Update order history status to complete, add comment with payment info  ***/
			        $transaction_ids = '';

			        foreach ($getWebhookByAddressRes as $trx) {
			        	$transaction_ids .= '<a href="'.$trx['explorer_url'].'" target="_blank">'.$trx['txId'].'</a> - '.$trx['paid_amount'].' '.$order_info['coin'].'<br />Date : '.$trx['paid_date'].'<br /><br />';
			        }

			        $enc_order_id = urlencode($this->obj_curl->encrypt($order_info['order_id']));  // order id in encryption format
		            $order_url = $this->url->link('extension/coinremitter/module/coinremitter_invoice|detail&order_id='.$enc_order_id);


			        $comments = '';
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
			        $comments .= $paid_amount.' '.$order_info['coin'];

			        $comments .= '<br /><br /> Pending Amount:<br />';
			        $comments .= $pending.' '.$order_info['coin'];

			        

			        $this->load->model('checkout/order');
			        // $this->model_checkout_order->addHistory($orderId, 5, $comments);  // 5 = Complete
					if($order_cart['order_status'] == 'Pending'){
						if($order_info['payment_status'] == 'over paid' || $order_info['payment_status'] == 'paid'){
							$status =  ($this->config->get('payment_coinremitter_order_status') == 0)?1:5;
							$this->model_checkout_order->addHistory($orderId, $status, $comments);  // 5 = Complete
						}else{
							$this->model_checkout_order->addHistory($orderId, 1, $comments);  //1 = pending
						}
					}else{
						$status_id = $this->db->query("SELECT *  FROM " . DB_PREFIX . "order_status where name LIKE '%" . $order_cart['order_status'] . "%'");
						$this->model_checkout_order->addHistory($orderId, $status_id->row['order_status_id'], $comments);  // 5 = pending
					}
				}
			}
		}
	}

}