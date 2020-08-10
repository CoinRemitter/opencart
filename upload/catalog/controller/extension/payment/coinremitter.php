<?php
class ControllerExtensionPaymentCoinremitter extends Controller {

	/*** index() : This method will call when user selects 'coinremitter' as payment method and a whole view will return on this method. A dropdown of 'wallets' and a 'confim button' will be generated on this view.  ***/
	public function index() {
		$this->load->model('extension/payment/coinremitter');
		$data['wallets'] = $this->model_extension_payment_coinremitter->getAllWallets();
		return $this->load->view('extension/payment/coinremitter',$data);
	}

	/*** confirm() : This method will call when user click on confirm button on last step of check out. This will insert data history as well as payment history in the `oc_coinremitter_order` and the `oc_coinremitter_payment` tables respectively and redirect to coinremitter payment page. ***/
	public function confirm() {

		$json = array();
		
		if ($this->session->data['payment_method']['code'] == 'coinremitter') {

			if($this->request->post['coin'] != ''){

				$this->load->model('extension/payment/coinremitter');

				/*** Get wallet data from 'oc_coinremitter_wallet' with use of `coin` ***/
				$coin = $this->request->post['coin'];
				$wallet_info = $this->model_extension_payment_coinremitter->getWallet($coin);

				if($wallet_info){

					$this->load->model('checkout/order');
					$orderId = $this->session->data['order_id'];
					$order_cart = $this->model_checkout_order->getOrder($orderId);
					$api_key = $wallet_info['api_key'];
					$api_password = $wallet_info['password'];
					$selectcoin = $wallet_info['coin'];

					$http_server_url = HTTP_SERVER;
					$https_server_url = HTTPS_SERVER;

					if(strpos($http_server_url, 'localhost') !== false || strpos($https_server_url, 'localhost') !== false){
						$notify_url = '';
					}else{
						$notify_url = $this->url->link('extension/payment/coinremitter/notify');
					}

					
					$fail_url = $this->url->link('checkout/failure');
					$currency_type = $order_cart['currency_code'];
					$invoice_expiry = $this->config->get('payment_coinremitter_invoice_expiry');
					$success_url = $this->url->link('checkout/success');
					$exchange_rate = $this->config->get('payment_coinremitter_exchange_rate');

					if($invoice_expiry == 0){
						$invoice_expiry == '';
					}

					if ($exchange_rate == 0 || $exchange_rate == '') {
						$exchange_rate = 1;
					}
					
					$amount = $order_cart['total'] * $exchange_rate;

					$invoice_data =[
                        'api_key' =>$api_key,
                        'password' => $api_password,
                        'amount' =>$amount,
                        'coin'=> $selectcoin,
                        'notify_url' => $notify_url,
                        'fail_url' => $fail_url,
                        'currency' => $currency_type,
                        'expire_time'=>$invoice_expiry,
                        'suceess_url' => $success_url,
                        'description' => 'Order Id #'.$orderId   
                    ];
                    
                    $invoice = $this->createInvoice($invoice_data);
                    
                    if(!empty($invoice) && isset($invoice['flag']) && $invoice['flag'] == 1){

                    	$invoice_data = $invoice['data'];
                    	$invoice_id = $invoice_data['invoice_id'];
                    	$amountusd = $invoice_data['usd_amount'];
                    	$coin = $invoice_data['coin'];
                    	$crp_amount = $invoice_data['total_amount'][$coin];
                    	$payment_status = strtolower($invoice_data['status']);

                        $order_data = array(
                        	'order_id' 		=> $orderId,
                        	'invoice_id' 	=> $invoice_id ,
                        	'amountusd' 	=> $amountusd,
                        	'crp_amount' 	=> $crp_amount,
                        	'payment_status'=> $payment_status,
                        );

                    	/*** Now, insert detail in `oc_coinremitter_order` ***/
                    	$this->model_extension_payment_coinremitter->addOrder($order_data);

                    	/*** Now, insert detail in `oc_coinremitter_payment` ***/
                    	$expire_on = '';
                    	if(isset($invoice_data['expire_on']) && $invoice_data['expire_on'] != ''){
                    		$expire_on = $invoice_data['expire_on'];	
                    	}

                        $total_amount = json_encode($invoice_data['total_amount']); 
                        $paid_amount = $invoice_data['paid_amount'] ? json_encode($invoice_data['paid_amount']) : '';
                        $payment_history = isset($invoice_data['payment_history']) ? json_encode($invoice_data['payment_history']) : '';
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

                        $this->model_extension_payment_coinremitter->addPayment($payment_data);

                    	
						$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], 1);  // 1 = Pending
					
						//$json['redirect'] = $this->url->link('checkout/success');	
						$json['redirect'] = $invoice_data['url'];	
                    }
				}
			}
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));		
	}

	/*** createInvoice() : This will create invoice on coinremitter api call ***/
	private function createInvoice($params){

		$res = '';
		$endPoint = $params['coin'].'/create-invoice';

		// load coinremitter library
		$this->load->library('coinremitter');
		$obj_curl = Coinremitter::get_instance($this->registry);
		$api_response = $obj_curl->apiCall($endPoint,$params);

		if($api_response){
			$res = $api_response;
		}
        return $res;
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

	        		$this->load->model('extension/payment/coinremitter');

					/*** Get wallet data from 'oc_coinremitter_wallet' with use of `coin` ***/
					$wallet_info = $this->model_extension_payment_coinremitter->getWallet($coin);
					
					if($wallet_info){

						/*** Now get order detail from `oc_coinremitter_order` ***/
						$order_detail = $this->model_extension_payment_coinremitter->getOrder($invoice_id);
						
						if($order_detail){
							if($order_detail['payment_status'] != 'paid' && $order_detail['payment_status'] != 'over paid'){

								$orderId = $order_detail['order_id'];
								$get_invoice_params = array(
									'api_key'=>$wallet_info['api_key'],
				                    'password'=>$wallet_info['password'],
				                    'invoice_id'=>$invoice_id,
				                    'coin'=>$coin
								);

								$invoice = $this->getInvoice($get_invoice_params);
								
								if(!empty($invoice) && $invoice['flag'] ==1){
									$invoice_data = $invoice['data'];

									if($invoice_data['status_code'] == 1 || $invoice_data['status_code'] == 3){

										/*** Update status in `oc_coinremitter_order` table ***/

										$order_data = array(
											'payment_status' =>strtolower($invoice_data['status'])
										);
				                        $id = $invoice_data['invoice_id'];
				                        
				                        $this->model_extension_payment_coinremitter->updateOrder($id,$order_data);

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
				                        
				                        $this->model_extension_payment_coinremitter->updatePayment($id,$payment_data);

				                        /*** Update order history status to complete, add comment with payment info)  ***/

				                        $transaction_ids = '';

				                        foreach ($invoice_data['payment_history'] as $inv_dt) {
				                        	$transaction_ids .= '<a href="'.$inv_dt['explorer_url'].'" target="_blank">'.$inv_dt['txid'].'</a> - '.$inv_dt['amount'].' '.$invoice_data['coin'].'<br />Date:<br /> '.$inv_dt['date'].'<br /><br />';
				                        }

				                        $comments = '';
				                        $comments .= 'Coinremitter Invoice - <a href="'.$invoice_data['url'].'" target="_blank">#'.$invoice_data['invoice_id'].'</a> '.$invoice_data['status'].' <br /><br />';
				                        $comments .= 'Transaction Ids<br />';
				                        $comments .= $transaction_ids;
				                        $comments .= '<br />Total Amount<br>';
				     					$comments .= $invoice_data['total_amount'][$invoice_data['coin']].' '.$invoice_data['coin'];
				                        $comments .= '<br /><br />Wallet Name <br>'.$invoice_data['wallet_name'];
				                        $this->load->model('checkout/order');
				                        $this->model_checkout_order->addOrderHistory($orderId, 5, $comments);  // 5 = Complete

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


	/*** getInvoice() : This will get the invoice on coinremitter api call ***/
	private function getInvoice($params){

		$res = '';
		$endPoint = $params['coin'].'/get-invoice';

		// load coinremitter library
		$this->load->library('coinremitter');
		$obj_curl = Coinremitter::get_instance($this->registry);
		$api_response = $obj_curl->apiCall($endPoint,$params);

		if($api_response){
			$res = $api_response;
		}
        return $res;
	}

}
