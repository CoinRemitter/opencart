<?php
namespace Opencart\Catalog\Controller\Extension\Coinremitter\Payment;
class Coinremitter extends \Opencart\System\Engine\Controller {

	private $obj_curl;

	public function __construct($registry){
		parent::__construct($registry);
		
		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$this->obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);
		
	}

	/*** index() : This method will call when user selects 'coinremitter' as payment method and a whole view will return on this method. A dropdown of 'wallets' and a 'confim button' will be generated on this view.  ***/
	public function index() {
		$this->load->model('extension/coinremitter/payment/coinremitter');
		$add_column_if_not_exists = $this->model_extension_coinremitter_payment_coinremitter->checkIsValidColumn();
		$data['wallets'] = $this->model_extension_coinremitter_payment_coinremitter->getAllWallets();
		// echo '<pre>';
		// print_r($data['wallets']);
		$this->load->model('checkout/order');
		$orderId = $this->session->data['order_id'];
		// $this->session->data['agree'] = true;
		$order_cart = $this->model_checkout_order->getOrder($orderId);
		$currency_code = $this->config->get('config_currency');
		$cart_total = $order_cart['total'];
		$validate_coin['wallets'] = array();

		foreach ($data['wallets'] as $key => $value) {
		
			$get_fiat_rate_data = [
				'url' => 'get-fiat-to-crypto-rate',
				'coin' => $value['coin'],
				'api_key' =>$value['api_key'],
				'password' => $value['password'],
				'fiat_symbol' => $currency_code,
				'fiat_amount' => $cart_total * $value['exchange_rate_multiplier']
			];

			$fiat_to_crypto_response = $this->obj_curl->commonApiCall($get_fiat_rate_data);
			// print_r($fiat_to_crypto_response);
			if(!empty($fiat_to_crypto_response) && isset($fiat_to_crypto_response['flag']) && $fiat_to_crypto_response['flag'] == 1){
				if($fiat_to_crypto_response['data']['crypto_amount'] >= $value['minimum_value']){
					array_push($validate_coin['wallets'],$value);
				}
			}
		}
		return $this->load->view('extension/coinremitter/payment/coinremitter',$validate_coin);
	}

	/*** confirm() : This method will call when user click on confirm button on last step of check out. This will insert data history as well as payment history in the `oc_coinremitter_order` and the `oc_coinremitter_payment` tables respectively and redirect to coinremitter payment page. ***/
	public function confirm() {

		$json = array();
		if ($this->session->data['payment_method'] == 'coinremitter') {
			
			if($this->request->post['coin'] != ''){

				$this->load->model('extension/coinremitter/payment/coinremitter');

				/*** Get wallet data from 'oc_coinremitter_wallet' with use of `coin` ***/
				$coin = $this->request->post['coin'];
				$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWallet($coin);

				if($wallet_info){

					$api_key = $wallet_info['api_key'];
					$api_password = $wallet_info['password'];
					$exchange_rate = $wallet_info['exchange_rate_multiplier'];

					$address_data =[
						'url' => 'get-new-address',
						'coin' => $coin,
                        'api_key' =>$api_key,
                        'password' => $api_password
                    ];
					// print_r($this->obj_curl);
					// die;
                    // die($this->obj_curl);
                    $address_res = $this->obj_curl->commonApiCall($address_data);
                    
                    if(!empty($address_res) && isset($address_res['flag']) && $address_res['flag'] == 1){

                    	$this->load->model('checkout/order');
						$orderId = $this->session->data['order_id'];
						$order_cart = $this->model_checkout_order->getOrder($orderId);
						
						//Convert amount format in actual currency.
						/*** Opencart saves amount in USD only. So you need to covert amount for other currency. Below function converts amount in selected(base) currency. It also work for USD, so no need any other condition for USD ***/
						$order_total = $this->currency->format($order_cart['total'], $order_cart['currency_code'], $order_cart['currency_value'],false);

						if ($exchange_rate == 0 || $exchange_rate == '') {
							$exchange_rate = 1;
						}

						$amount = $order_total * $exchange_rate;
						$currency_type = $order_cart['currency_code'];

                    	//now convert order's fiat amount to crypto amount with use of get-fiat-to-crypto-rate api in coinremitter 

                    	$fiat_amount_arr = [
                    		'url' => 'get-fiat-to-crypto-rate',
                    		'coin' => $coin,
                    		'api_key' =>$api_key,
                        	'password' => $api_password,
                        	'fiat_symbol' => $currency_type,
                        	'fiat_amount' => $amount
                    	];

                    	$fiat_to_crypto_res = $this->obj_curl->commonApiCall($fiat_amount_arr);

                    	if(!empty($fiat_to_crypto_res) && isset($fiat_to_crypto_res['flag']) && $fiat_to_crypto_res['flag'] == 1){

                    			$address_data = $address_res['data'];
                    			$fiat_to_crypto_res = $fiat_to_crypto_res['data'];
		                    	$amountusd = $order_cart['total'];
		                    	$crp_amount = $fiat_to_crypto_res['crypto_amount'];
		                    	$address = $address_data['address'];
		                    	$qr_code = $address_data['qr_code'];

		                        $order_data = array(
		                        	'order_id' 		=> $orderId,
		                        	'invoice_id' 	=> '' ,
		                        	'amountusd' 	=> $amountusd,
		                        	'crp_amount' 	=> $crp_amount,
		                        	'payment_status'=> 'pending',
		                        	'address' 		=> $address,
		                        	'qr_code'		=> $qr_code
		                        );

		                    	/*** Now, insert detail in `oc_coinremitter_order` ***/
		                    	$this->model_extension_coinremitter_payment_coinremitter->addOrder($order_data);

		                    	/*** Now, insert detail in `oc_coinremitter_payment` ***/
		                    	$invoice_expiry = (int)$this->config->get('payment_coinremitter_invoice_expiry');

								if($invoice_expiry == 0){
									$expire_on = '';
								}else{
									$newtimestamp = strtotime(date('Y-m-d H:i:s').' + '.$invoice_expiry.' minute');
									$expire_on = date('Y-m-d H:i:s', $newtimestamp);
								}
								$total_amount = array(
									$coin => $crp_amount,
									'USD' => $amountusd,
									$order_cart['currency_code'] => $order_total
								);
		                        $payment_data = array(
		                        	'order_id' 		=> 	$orderId,
		                            'invoice_id'	=>	'',
		                            'address'		=> 	$address,
		                            'invoice_name'	=>	'',
		                            'marchant_name'	=>	'',
		                            'total_amount'	=>	json_encode($total_amount),
		                            'paid_amount'	=>	'',
		                            'base_currancy'	=>	$currency_type,
		                            'description'	=>	'Order Id #'.$orderId,
		                            'coin'			=>	$coin,
		                            'payment_history'=> '',
		                            'conversion_rate'=> '',
		                            'invoice_url'	=>	'',
		                            'status'		=>	'Pending',
		                            'expire_on'		=>	$expire_on,
		                            'created_at'	=>	date('Y-m-d H:i:s')
		                        );

		                        $this->model_extension_coinremitter_payment_coinremitter->addPayment($payment_data);

		                        $enc_order_id = urlencode($this->obj_curl->encrypt($orderId));  // order id in encryption format
		                        $invoice_url = $this->url->link('extension/coinremitter/module/coinremitter_invoice|detail&order_id='.$enc_order_id,'',true);

		                    	/*** Update order history status to pending, add comment  ***/
		                        $comments = 'View order <a href="'.$invoice_url.'">#' . $orderId . '</a> ';
		                        $is_customer_notified = true;
		                        $this->model_checkout_order->addHistory($orderId, 1, $comments, $is_customer_notified); 
		                        													// 1 = Pending

								$json = array();
								$json['flag'] = 1; 
								$json['redirect'] = $invoice_url;

                    	}else{
                    		$msg = 'Something went wrong while converting fiat to crypto. Please try again later';
	                    	if(isset($fiat_to_crypto_res['msg']) && $fiat_to_crypto_res['msg'] != ''){
	                    		$msg = $fiat_to_crypto_res['msg'];
	                    	}
	                    	$json['flag'] = 0;
							$json['msg'] = $msg;
                    	}

                    }else{
                    	$msg = 'Something went wrong while creating address. Please try again later';
                    	if(isset($address_res['msg']) && $address_res['msg'] != ''){
                    		$msg = $address_res['msg'];
                    	}
                    	$json['flag'] = 0;
						$json['msg'] = $msg;
                    }
				}else{
					$json['flag'] = 0;
					$json['msg'] = 'Selected wallet not found. Please try again later';
				}
			}else{
				$json['flag'] = 0;
				$json['msg'] = 'Selected coin not found. Please try again later';	
			}
		}else{
			$json['flag'] = 0;
			$json['msg'] = 'Please select Coinremitter as payment method';
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));		
	}


	/*** notify() : This method will call by coinremitter itself when user has paid amount via coinremitter. This will update data history as well as payment history in the `oc_coinremitter_order` and the `oc_coinremitter_payment` tables respectively. ***/
	public function notify(){
		
		$json = array();

		if($this->request->server['REQUEST_METHOD'] == 'POST'){

			$post = array();
			if(isset($this->request->post['coin']) && $this->request->post['coin'] != ''){
				$post = $this->request->post;
			}else{
				$json = json_decode(file_get_contents('php://input'),TRUE);

				if(isset($json['coin']) && $json['coin'] != ''){
					$post = $json;
				}else{
					$json['flag'] = 0;
					$json['msg'] = "Required paramters are not found";
				}
			}

			if(!empty($post)){

				$coin = $post['coin'];
				if(isset($post['invoice_id']) && $post['invoice_id'] != ''){

					$invoice_id = $post['invoice_id'];

	        		$this->load->model('extension/coinremitter/payment/coinremitter');

					/*** Get wallet data from 'oc_coinremitter_wallet' with use of `coin` ***/
					$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWallet($coin);
					
					if($wallet_info){

						/*** Now get order detail from `oc_coinremitter_order` ***/
						$order_detail = $this->model_extension_coinremitter_payment_coinremitter->getInvoiceOrder($invoice_id);
						
						if($order_detail){
							if($order_detail['payment_status'] != 'paid' && $order_detail['payment_status'] != 'over paid'){

								$orderId = $order_detail['order_id'];
								$get_invoice_params = array(
									'url' => 'get-invoice',
									'api_key'=>$wallet_info['api_key'],
				                    'password'=>$wallet_info['password'],
				                    'invoice_id'=>$invoice_id,
				                    'coin'=>$coin
								);

								$invoice = $this->obj_curl->commonApiCall($get_invoice_params);
								
								if(!empty($invoice) && $invoice['flag'] ==1){
									$invoice_data = $invoice['data'];

									if($invoice_data['status_code'] == 1 || $invoice_data['status_code'] == 3){

										/*** Update status in `oc_coinremitter_order` table ***/

										$order_data = array(
											'payment_status' =>strtolower($invoice_data['status'])
										);
				                        $id = $invoice_data['invoice_id'];
				                        
				                        $this->model_extension_coinremitter_payment_coinremitter->updateInvoiceOrder($id,$order_data);

				                        /*** Update data in `oc_coinremitter_payment` table ***/

				                        $expire_on = '';
				                    	if(isset($invoice_data['expire_on']) && $invoice_data['expire_on'] != ''){
				                    		$expire_on = $invoice_data['expire_on'];	
				                    	}

				                        $total_amount = json_encode($invoice_data['total_amount']); 
				                        $paid_amount = json_encode($invoice_data['paid_amount']);
				                        $payment_history =json_encode($invoice_data['payment_history']);
				                        $conversion_rate =json_encode($invoice_data['conversion_rate']);
				                        
				                        $payment_data = array(
				                        	'order_id' => $orderId,
			                                'invoice_id'=>$invoice_data['invoice_id'],
			                                'invoice_name'=>$invoice_data['name'],
			                                'marchant_name'=>'',
			                                'total_amount'=>$total_amount,
			                                'paid_amount'=>$paid_amount,
			                                'base_currancy'=>$invoice_data['base_currency'],
			                                'description'=>$invoice_data['description'],
			                                'coin'=>$invoice_data['coin'],
			                                'payment_history'=> $payment_history,
			                                'conversion_rate'=> $conversion_rate, 
			                                'invoice_url'=>$invoice_data['url'],
			                                'status'=>$invoice_data['status'],
			                                'expire_on'=>$expire_on,
			                                'created_at'=>$invoice_data['invoice_date']
				                        );
				                        
				                        $this->model_extension_coinremitter_payment_coinremitter->updatePayment($id,$payment_data);

				                        /*** Update order history status to complete, add comment with payment info  ***/

				                        $transaction_ids = '';

				                        foreach ($invoice_data['payment_history'] as $inv_dt) {
				                        	$transaction_ids .= '<a href="'.$inv_dt['explorer_url'].'" target="_blank">'.$inv_dt['txid'].'</a> - '.$inv_dt['amount'].' '.$invoice_data['coin'].'<br />Date:<br /> '.$inv_dt['date'].'<br /><br />';
				                        }
				                        $paid_amount = $invoice_data['paid_amount'][$invoice_data['coin']];
				                        $total_amount = $invoice_data['total_amount'][$invoice_data['coin']];
				                        /*$pending_amount = $total_amount - $paid_amount;
				                        if($pending_amount < 0 ){
				                        	$pending_amount = 0;
				                        }*/
				                        
				                        $conversion_rate_str = '';
				                        foreach ($invoice_data['conversion_rate'] as $key => $value) {
				                        	$convert_head = str_replace("_"," To ",$key);
				                        	$key_explode_coin_name = explode('_', $key)[1];
				                        	$conversion_rate_str .= $convert_head.' : '.$value.' '.$key_explode_coin_name.' <br />';
				                        }


				                        $comments = '';
				                        $comments .= 'Coinremitter Invoice - <a href="'.$invoice_data['url'].'" target="_blank">#'.$invoice_data['invoice_id'].'</a> '.$invoice_data['status'].' <br /><br />';
				                        $comments .= 'Invoice Name : '.$invoice_data['name'].'<br />';
				                        $comments .= 'Created Date : '.$invoice_data['invoice_date'].'<br />';
				                        $comments .= 'Expiry Date : '.$invoice_data['expire_on'].'<br />';
				                        $comments .= 'Base Currency : '.$invoice_data['base_currency'].'<br />';
				                        $comments .= 'Coin : '.$invoice_data['coin'].'<br />';
				                        $comments .= 'Description : '.$invoice_data['description'].'<br /><br />';

											                        

				                        $comments .= 'Transaction Ids<br />';
				                        $comments .= $transaction_ids;

				                        $comments .= '<br /> Paid Amount<br />';
				                        $comments .= $paid_amount.' '.$invoice_data['coin'];

				                        /*$comments .= '<br /> Pending Amount<br />';
				                        $comments .= $pending_amount.' '.$invoice_data['coin'];*/

				                        $comments .= '<br />Total Amount<br />';
				     					$comments .= $total_amount.' '.$invoice_data['coin'];

				     					$comments .= '<br /><br /> Conversation Rate <br />';
				     					$comments .= $conversion_rate_str;

				                        $comments .= '<br />Wallet Name <br />'.$invoice_data['wallet_name'];
				                        $this->load->model('checkout/order');
				                        $this->model_checkout_order->addHistory($orderId, 5, $comments);  // 5 = Complete

				                        $json['flag'] = 1;
				                        $json['msg'] = 'Notified Successfully';

									}elseif($invoice_data['status_code'] == 4){
				                        if(empty($invoice_data['payment_history'])){

					                        $id = $invoice_data['invoice_id'];
					                        $invoice_status = strtolower($invoice_data['status']);

				                        	$order_data = array(
												'payment_status' => $invoice_status
											);
					                        //update coinremitter order as 'expired'
					                        $this->model_extension_coinremitter_payment_coinremitter->updateInvoiceOrder($id,$order_data);

					                        $payment_data = array(
					                        	'status'=>$invoice_status
					                        );
					                        //update coinremitter_payment as 'expired'
					                        $this->model_extension_coinremitter_payment_coinremitter->updateInvoicePaymentStatus($id,$payment_data);

					                        /*** Update order history status to canceled, add comment  ***/
					                        $comments = 'Coinremitter Invoice - <a href="'.$invoice_data['url'].'" target="_blank">#'.$invoice_data['invoice_id'].'</a> '.$invoice_data['status'];
					                        $this->load->model('checkout/order');
					                        $is_customer_notified = true;
					                        $this->model_checkout_order->addHistory($orderId, 7, $comments, $is_customer_notified);  // 7 = Canceled
				                        }
				                        $json['flag'] = 1;
				                        $json['msg'] = 'Notified Successfully';
				                    }else{
										$json['flag'] = 0;
										$json['msg'] = "Invoice is not paid yet";
									}
								}else{
									$json['flag'] = 0;
									$json['msg'] = "Invoice not found";
								}
							}else{
								$json['flag'] = 0;
								$json['msg'] = "Invoice is already paid";
							}
						}else{
							$json['flag'] = 0;
							$json['msg'] = "Order data not found";
						}
					}else{
						$json['flag'] = 0;
						$json['msg'] = "Wallet not found";
					}	
				}else{
					$json['flag'] = 0;
					$json['msg'] = "Required paramters are not found";
				}
			
			}else{
				$json['flag'] = 0;
				$json['msg'] = "Required paramters are not found";
			}
		}else{
			$json['flag'] = 0;
			$json['msg'] = "Only post request allowed";
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}


	/*** webhook() : This method will call by coinremitter itself when user has paid amount via coinremitter. This will update data history as well as payment history and webhook data in the `oc_coinremitter_order`, the `oc_coinremitter_payment` and the `oc_coinremitter_webhook` tables respectively. ***/
	public function webhook(){
		
		$json = array();

		if($this->request->server['REQUEST_METHOD'] == 'POST'){
			
			$post = array();
			if(isset($this->request->post['coin_short_name']) && $this->request->post['coin_short_name'] != '' && isset($this->request->post['address']) && $this->request->post['address'] != '' && isset($this->request->post['type']) && $this->request->post['type'] != ''){
				$post = $this->request->post;
			}else{
				$post_json = json_decode(file_get_contents('php://input'),TRUE);

				if(isset($post_json['coin_short_name']) && $post_json['coin_short_name'] != '' && isset($post_json['address']) && $post_json['address'] != '' && isset($post_json['type']) && $post_json['type'] != '' ){
					$post = $post_json;
				}else{
					$json['flag'] = 0;
					$json['msg'] = "Required paramters are not found";
				}
			}
			if(!empty($post)){

				if($post['type'] == 'receive'){
					$this->load->model('checkout/order');

					$address = $post['address'];

					/*** check if address exists in oc_coinremitter_order ***/
					$this->load->model('extension/coinremitter/payment/coinremitter');
					$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrderByAddress($address);
					
					if(!empty($order_info)){

						if($order_info['payment_status'] == 'pending' || $order_info['payment_status'] == 'under paid'){

							$orderId = $order_info['order_id'];

							$order_cart = $this->model_checkout_order->getOrder($orderId);
							if(!empty($order_cart)){

								/*** check if expired time of invoice is defined or not. If defined then check if invoice has any transaction or not. If no transaction is found and invoice time is expired then change invoice status as expired  ***/

								/*** Get webhook by address***/
								$status = '';
								if(isset($order_info['expire_on']) && $order_info['expire_on'] != ''){
									if(time() >= strtotime($order_info['expire_on'])){
										$getWebhookByAddressRes = $this->model_extension_coinremitter_payment_coinremitter->getWebhookByAddress($address);	
										if(empty($getWebhookByAddressRes)){
											//update payment_status,status as expired
											$status = 'expired';
											$update_arr = array('payment_status' => $status);
											$this->model_extension_coinremitter_payment_coinremitter->updateOrder($orderId,$update_arr);
											$update_arr = array('status' => ucfirst($status));
											$this->model_extension_coinremitter_payment_coinremitter->updatePaymentStatus($orderId,$update_arr);
											
											if($order_cart['order_status'] != 'Canceled'){
												/*** Update order history status to canceled, add comment  ***/
						                        $comments = 'Order #'.$orderId;
						                        $is_customer_notified = true;
						                        $this->model_checkout_order->addHistory($orderId, 7, $comments, $is_customer_notified);  // 7 = Canceled	
											}
										}	
									}
								}

								if($status == ''){

									$coin = $post['coin_short_name'];
									$trxId = $post['id'];

									/*** now get wallet data from oc_coinremitter_wallet with use of coin ***/
									$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWallet($coin);
									if(!empty($wallet_info)){

										/*** now get transaction from coinremitter api call ***/
										$get_trx_params = array(
											'url'		=> 'get-transaction',
											'api_key'	=>	$wallet_info['api_key'],
						                    'password'	=>	$wallet_info['password'],
						                    'id'		=>	$trxId,
						                    'coin'		=>	$coin
										);

										$getTransaction = $this->obj_curl->commonApiCall($get_trx_params);
										if(!empty($getTransaction) && isset($getTransaction['flag']) && $getTransaction['flag'] == 1){

											$transaction = $getTransaction['data'];

											if(isset($transaction['type']) && $transaction['type'] == 'receive'){

												/*** now check if transaction exists in oc_coinremitter_webhook or not if does not exist then insert else update confirmations ***/
											
												$webhook_info = $this->model_extension_coinremitter_payment_coinremitter->getWebhook($transaction['id']);
												if(empty($webhook_info)){
													//insert record
													$insert_arr = array(

														'order_id' => $orderId,
														'address' => $transaction['address'],
														'transaction_id' => $transaction['id'],
														'txId' => $transaction['txid'],
														'explorer_url' => $transaction['explorer_url'],
														'paid_amount' => $transaction['amount'],
														'coin' => $transaction['coin_short_name'],
														'confirmations' => $transaction['confirmations'],
														'paid_date' => $transaction['date']
													);

													$inserted_id = $this->model_extension_coinremitter_payment_coinremitter->addWebhook($insert_arr);
													if($inserted_id > 0){
														$json['flag'] = 1;
														$json['msg'] = "Inserted successfully";	
													}else{
														$json['flag'] = 0;
														$json['msg'] = "system error. Please try again later.";	
													}

												}else{
													//update confirmations if confirmation is less than 3
													if($webhook_info['confirmations'] < 3){
														$update_confirmation = array(
															'confirmations' => $transaction['confirmations'] <= 3 ? $transaction['confirmations'] : 3 
														);
														$this->model_extension_coinremitter_payment_coinremitter->updateTrxConfirmation($webhook_info['transaction_id'],$update_confirmation);
													} 

													$json['flag'] = 1;
													$json['msg'] = "confirmations updated successfully";	
												}

												/*** order paid process start ***/

												/*** Now, get all webhook transactions which have lesser than 3 confirmations ***/
												$webhook_res = $this->model_extension_coinremitter_payment_coinremitter->getSpecificWebhookTrxByAddress($address);

												/*** Get wallet info if and only if webhook_res has atleast one data ***/
												if(!empty($webhook_res)){

													foreach ($webhook_res as $webhook) {
														/*** Get confirmation from coinremitter api (get-transaction) ***/
														$get_trx_params['id'] = $webhook['transaction_id']; 
														$getTransactionRes = $this->obj_curl->commonApiCall($get_trx_params);

														if(!empty($getTransactionRes) && isset($getTransactionRes['flag']) && $getTransactionRes['flag'] == 1){

															$transactionData = $getTransactionRes['data'];

															$update_confirmation = array(
																'confirmations' => $transactionData['confirmations'] <= 3 ? $transactionData['confirmations'] : 3 
															);
															$this->model_extension_coinremitter_payment_coinremitter->updateTrxConfirmation($webhook['transaction_id'],$update_confirmation);
														}
													}
												}

												/*** Get sum of paid amount of all transations which have 3 or more than 3 confirmtions  ***/
												$total_paid_res = $this->model_extension_coinremitter_payment_coinremitter->getTotalPaidAmountByAddress($address);

												$total_paid = 0;
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

													if($status == 'paid' || $status == 'over paid'){
														/*** Update order status as complete ***/
														$this->add_order_success_history($orderId);
													}
												}

												/*** order paid process end ***/

											}else{
												$json['flag'] = 0; 
												$json['msg'] = 'Transaction type is not receive';
											}
										}else{
											$msg = 'Something went wrong while getting transactions. Please try again later'; 
											if(isset($getTransaction['msg']) && $getTransaction['msg'] != ''){
												$msg = $getTransaction['msg']; 
											} 
											$json['flag'] = 0; 
											$json['msg'] = $msg; 
										}
									}else{
										$json['flag'] = 0;
										$json['msg'] = "Wallet not found";	
									}

								}else{
									$json['flag'] = 0;
									$json['msg'] = "Order is expired";
								}
							}else{
								$json['flag'] = 0;
								$json['msg'] = "Order not found";
							}
						}else{
							$json['flag'] = 0;
							$json['msg'] = "Order status is neither a 'pending' nor a'under paid'";
						}
					}else{
						$json['flag'] = 0;
						$json['msg'] = "Address not found";
					}
				}else{
					$json['flag'] = 0;
					$json['msg'] = "Invalid type";
				}
			}else{
				$json['flag'] = 0;
				$json['msg'] = "Required paramters are not found";
			}
		}else{
			$json['flag'] = 0;
			$json['msg'] = "Only post request allowed";
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}


	private function add_order_success_history($orderId){

		$this->load->model('checkout/order');
		$order_cart = $this->model_checkout_order->getOrder($orderId);
		if(!empty($order_cart) && $order_cart['order_status'] == 'Pending'){

			//check if order id exists in coinremitter_order or not
			$this->load->model('extension/coinremitter/payment/coinremitter');	
			$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($orderId);
			
			if(!empty($order_info) && (strtolower($order_info['payment_status']) == 'paid' || strtolower($order_info['payment_status']) == 'over paid')){

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
			        $this->model_checkout_order->addHistory($orderId, 5, $comments);  // 5 = Complete

				}
			}
		}
	}
	

}
