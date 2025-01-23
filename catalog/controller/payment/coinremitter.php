<?php

namespace Opencart\Catalog\Controller\Extension\Coinremitter\Payment;

define('ORDER_STATUS', array(
	'pending' => 0,
	'paid' => 1,
	'under_paid' => 2,
	'over_paid' => 3,
	'expired' => 4,
	'cancelled' => 5,
));
define('ORDER_STATUS_CODE', array('Pending', 'Paid', 'Under Paid', 'Over Paid', 'Expired', 'Cancelled'));
define('TRUNCATION_VALUE', 0.05); // in USD
class Coinremitter extends \Opencart\System\Engine\Controller
{

	private $obj_curl;

	public function __construct($registry)
	{
		parent::__construct($registry);

		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$this->obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);
	}

	/*** index() : This method will call when user selects 'coinremitter' as payment method and a whole view will return on this method. A dropdown of 'wallets' and a 'confim button' will be generated on this view.  ***/
	public function index()
	{

		$this->load->model('extension/coinremitter/payment/coinremitter');
		$allWallets = $this->model_extension_coinremitter_payment_coinremitter->getAllWallets();
		$this->load->model('checkout/order');
		$orderId = $this->session->data['order_id'];
		$order_cart = $this->model_checkout_order->getOrder($orderId);
		$order_total = 0;
		if(!isset($order_cart['totals'])){
			$order_cart['totals'] = $this->model_checkout_order->getTotals($orderId);
		}
		foreach ($order_cart['totals'] as $total) {
			if ($total['code'] == 'sub_total') {
				$order_total += $total['value'];
			}
		}
		$otherTotal = 0;
		foreach ($order_cart['totals'] as $total) {
			if ($total['code'] != 'sub_total' && $total['code'] != 'total') {
				$otherTotal += $total['value'];
			}
		}
		$validate_coin['wallets'] = array();
		$validate_coin['description'] = $this->config->get('payment_coinremitter_description');
		foreach ($allWallets as $wallet) {

			$minimumInvFiatAmount = $wallet['minimum_invoice_amount'];
			if ($wallet['base_fiat_symbol'] != $order_cart['currency_code']) {
				$minimumInvFiatAmount = $this->currency->convert($wallet['minimum_invoice_amount'], $wallet['base_fiat_symbol'], $order_cart['currency_code']);
			}
			$minimumInvFiatAmount = number_format($minimumInvFiatAmount, 2, '.', '');

			$finalFiatAmount = ($order_total * $wallet['exchange_rate_multiplier']) + $otherTotal;
			$covert_order_total = $this->currency->format($finalFiatAmount, $order_cart['currency_code'], $order_cart['currency_value'], false);
			if ($covert_order_total >= $minimumInvFiatAmount) {
				array_push($validate_coin['wallets'], $wallet);
			}
		}

		return $this->load->view('extension/coinremitter/payment/coinremitter', $validate_coin);
	}

	/*** confirm() : This method will call when user click on confirm button on last step of check out. This will insert data history as well as payment history in the `oc_coinremitter_order` and the `oc_coinremitter_payment` tables respectively and redirect to coinremitter payment page. ***/
	public function confirm()
	{

		$json = array();

		if (isset($this->session->data['payment_method']) && $this->session->data['payment_method']['code'] == 'coinremitter.coinremitter') {
			$selectedCurrency = isset($this->request->post['coin']) ? $this->request->post['coin'] : '';
			if ($selectedCurrency == '') {
				$json['flag'] = 0;
				$json['msg'] = 'Please select a coin';
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}
			$this->load->model('extension/coinremitter/payment/coinremitter');

			$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWalletByCoin($selectedCurrency);
			if (empty($wallet_info)) {
				$json['flag'] = 0;
				$json['msg'] = 'Selected coin is invalid, Please try again later';
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}
			$api_key = $wallet_info['api_key'];
			$api_password = $wallet_info['password'];
			$exchange_rate = $wallet_info['exchange_rate_multiplier'];

			$this->load->model('checkout/order');
			$orderId = $this->session->data['order_id'];
			$order_cart = $this->model_checkout_order->getOrder($orderId);
			$order_total = 0;

			if(!isset($order_cart['totals'])){
				$order_cart['totals'] = $this->model_checkout_order->getTotals($orderId);
			}
			foreach ($order_cart['totals'] as $total) {
				if ($total['code'] == 'sub_total') {
					$order_total += $total['value'];
				}
			}
			$otherTotal = 0;
			foreach ($order_cart['totals'] as $total) {
				if ($total['code'] != 'sub_total' && $total['code'] != 'total') {
					$otherTotal += $total['value'];
				}
			}

			if ($exchange_rate == 0 || $exchange_rate == '') {
				$exchange_rate = 1;
			}
			$finalFiatAmount = ($order_total * $exchange_rate) + $otherTotal;
			$order_total = $this->currency->format($finalFiatAmount, $order_cart['currency_code'], $order_cart['currency_value'], false);

			$minimumInvFiatAmount = $wallet_info['minimum_invoice_amount'];
			if ($wallet_info['base_fiat_symbol'] != $order_cart['currency_code']) {
				$minimumInvFiatAmount = $this->currency->convert($wallet_info['minimum_invoice_amount'], $wallet_info['base_fiat_symbol'], $order_cart['currency_code']);
			}
			$minimumInvFiatAmount = number_format($minimumInvFiatAmount, 2, '.', '');

			if ($order_total < $minimumInvFiatAmount) {
				$json['flag'] = 0;
				$json['msg'] = 'Order amount is less than minimum order amount.';
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}

			// $order_total = $order_total * $order_cart['currency_value'];
			$amount = $order_total;
			$amount = number_format($amount, 2, '.', '');
			$fiatSymbol = $order_cart['currency_code'];

			//now convert order's fiat amount to crypto amount with use of get-fiat-to-crypto-rate api in coinremitter 

			$fiat_amount_arr = [
				'fiat' => $fiatSymbol,
				'fiat_amount' => $amount,
				'crypto' => $selectedCurrency,
				'api_key' => $api_key,
				'password' => $api_password,
			];

			$fiat_to_crypto_res = $this->obj_curl->apiCall('/rate/fiat-to-crypto', $fiat_amount_arr);

			if (empty($fiat_to_crypto_res) || !isset($fiat_to_crypto_res['success']) || !$fiat_to_crypto_res['success']) {
				$json['flag'] = 0;
				$json['msg'] = 'Something went wrong while creating order. Please try again later';
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}

			$address_data = [
				'api_key' => $wallet_info['api_key'],
				'password' => $wallet_info['password']
			];
			$address_res = $this->obj_curl->apiCall('/wallet/address/create', $address_data);

			if (empty($address_res) || !isset($address_res['success']) || !$address_res['success']) {
				$json['flag'] = 0;
				$json['msg'] = 'Something went wrong while creating order. Please try again later';
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}
			$address_data = $address_res['data'];
			$fiat_to_crypto_res = $fiat_to_crypto_res['data'][0];
			$cryptoAmount = $fiat_to_crypto_res['price'];
			$address = $address_data['address'];
			$qr_code = $address_data['qr_code'];

			$invoice_expiry = (int)$this->config->get('payment_coinremitter_invoice_expiry');

			$expire_on = null;
			if ($invoice_expiry != 0) {
				$newtimestamp = strtotime(date('Y-m-d H:i:s') . ' + ' . $invoice_expiry . ' minute');
				$expire_on = date('Y-m-d H:i:s', $newtimestamp);
			}
			$order_data = array(
				'order_id' 		=> $orderId,
				'user_id' 	=> $order_cart['customer_id'],
				'coin_symbol' 	=> $wallet_info['coin_symbol'],
				'coin_name' 	=> $wallet_info['coin_name'],
				'crypto_amount' => $cryptoAmount,
				'fiat_symbol' 	=> $fiatSymbol,
				'fiat_amount' 	=> $amount,
				'payment_address' => $address,
				'qr_code' 		=> $qr_code,
				'expiry_date'	=> $expire_on
			);

			/*** Now, insert detail in `oc_coinremitter_order` ***/
			$this->model_extension_coinremitter_payment_coinremitter->addOrder($order_data);

			$enc_order_id = urlencode($this->obj_curl->encrypt($orderId));  // order id in encryption format
			$invoice_url = $this->url->link('extension/coinremitter/module/coinremitter_invoice|detail&order_id=' . $enc_order_id, '', true);
			/*** Update order history status to pending, add comment  ***/
			$comments = '';
			$comments .= 'Coinremitter Order - <a href="' . $invoice_url . '" target="_blank">#' . $orderId . '</a><br /><br />';
			$comments .= 'Fiat Currency : ' . $order_data['fiat_symbol'] . '<br />';
			$comments .= 'Amount : ' . $order_data['crypto_amount'] . ' ' . $order_data['coin_symbol'] . '<br />';
			$comments .= 'Address : ' . $order_data['payment_address'] . '<br />';
			$comments .= 'Expiry Date : ' . $order_data['expiry_date'] . ' (UTC) <br />';

			$is_customer_notified = true;
			$this->model_checkout_order->addHistory($orderId, 1, $comments, $is_customer_notified);

			$json = array();
			$json['flag'] = 1;
			$json['redirect'] = $invoice_url;
		} else {
			$json['flag'] = 0;
			$json['msg'] = 'Please select Coinremitter as payment method';
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}


	/*** webhook() : This method will call by coinremitter itself when user has paid amount via coinremitter. This will update data history as well as payment history and webhook data in the `oc_coinremitter_order`, the `oc_coinremitter_payment` and the `oc_coinremitter_webhook` tables respectively. ***/
	public function webhook()
	{

		$json = array('flag' => 0);

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$json['msg'] = "Only post request allowed";
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		$param = array();
		if (isset($this->request->post['coin_symbol'])) {
			$param = $this->request->post;
		} else {
			$param = json_decode(file_get_contents('php://input'), TRUE);
			if (!isset($param['coin_symbol'])) {
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}
		}

		if (empty($param) || !isset($param['coin_symbol']) || !isset($param['address'])) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		if (!isset($param['type']) || $param['type'] != 'receive') {
			$json['msg'] = "Invalid type";
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		$this->load->model('checkout/order');


		$address = $param['address'];
		$id = $param['id'];

		/*** check if address exists in oc_coinremitter_order ***/
		$this->load->model('extension/coinremitter/payment/coinremitter');
		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrderByAddress($address);

		if (empty($order_info)) {
			$json['msg'] = "Address not found";
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		$orderId = $order_info['order_id'];
		$order_cart = $this->model_checkout_order->getOrder($orderId);
		if (empty($order_cart)) {
			$json['msg'] = "Order not found";
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
		}

		$order_info['transaction_meta'] = $order_info['transaction_meta'] ? json_decode($order_info['transaction_meta'], true) : [];
		$trxMeta = $order_info['transaction_meta'];
		$orderStatus = $order_info['order_status'];
		$coinSymbol = $order_info['coin_symbol'];

		$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWalletByCoin($coinSymbol);
		$get_trx_params = array(
			'api_key'	=>	$wallet_info['api_key'],
			'password'	=>	$wallet_info['password'],
			'id'	=> $id
		);

		$getTransaction = $this->obj_curl->apiCall('/wallet/transaction', $get_trx_params);

		if (!$getTransaction['success']) {
			$msg = 'Something went wrong while getting transactions. Please try again later';
			if (isset($getTransaction['msg']) && $getTransaction['msg'] != '') {
				$msg = $getTransaction['msg'];
			}
			$json['msg'] = $msg;
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		$trx = $getTransaction['data'];

		if ($trx['type'] != 'receive') {
			$json['msg'] = 'Transaction type is not receive';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		if (strtolower($trx['address']) != strtolower($order_info['payment_address'])) {
			$json['msg'] = 'Invalid transaction.';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$fiat_amount = ($trx['amount'] * $order_info['fiat_amount']) / $order_info['crypto_amount'];
		$minFiatAmount = $this->currency->convert($wallet_info['minimum_invoice_amount'], $wallet_info['base_fiat_symbol'], $order_info['fiat_symbol']);
		$minFiatAmount = number_format($minFiatAmount, 2, '.', '');
		if ($fiat_amount < $minFiatAmount) {
			$json['flag'] = 1;
			$json['msg'] = 'Transaction amount less than minimum order amount.';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$address = $trx['address'];
		if ($orderStatus == ORDER_STATUS['expired']) {
			if (
				isset($order_info['expiry_date'])
				&& time() >= strtotime($order_info['expiry_date'])
				&& $order_cart['order_status'] == 'Canceled'
			) {
				$updateOrderRequired = false;
				$transactionInfo = $this->model_extension_coinremitter_payment_coinremitter->checkTransactionExists($order_info['transaction_meta'], $trx['txid']);
				if (empty($transactionInfo)) {
					$updateOrderRequired = true;
					$trxMeta[$trx['txid']] = $trx;
					$this->add_order_history($order_info['order_id'], $trx);
				} else {
					if ($transactionInfo['status_code'] != $trx['status_code']) {
						$trxMeta[$trx['txid']] = $trx;
						$updateOrderRequired = true;
					}
				}
				if ($updateOrderRequired) {
					$update_arr = array('transaction_meta' => json_encode($trxMeta), 'paid_crypto_amount' => 0, 'paid_fiat_amount' => 0, 'order_status' => ORDER_STATUS['expired']);
					$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'], $update_arr);
				}
				$json['msg'] = 'Order has been expired.';
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
			}
		} elseif ($orderStatus != ORDER_STATUS['cancelled'] || $orderStatus != ORDER_STATUS['expired']) {
			$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrderByAddress($address);
			$order_info['transaction_meta'] = $order_info['transaction_meta'] ? json_decode($order_info['transaction_meta'], true) : [];
			if ($orderStatus == ORDER_STATUS['pending'] && empty($order_info['transaction_meta'])) {
				if (
					isset($order_info['expiry_date'])
					&& time() >= strtotime($order_info['expiry_date'])
				) {
					$updateOrderRequired = false;
					$trx = $getTransaction['data'];
					$transactionInfo = $this->model_extension_coinremitter_payment_coinremitter->checkTransactionExists($order_info['transaction_meta'], $trx['txid']);
					if (empty($transactionInfo)) {
						$updateOrderRequired = true;
						$trxMeta[$trx['txid']] = $trx;
						$this->add_order_history($order_info['order_id'], $trx);
					} else {
						if ($transactionInfo['status_code'] != $trx['status_code']) {
							$trxMeta[$trx['txid']] = $trx;
							$updateOrderRequired = true;
						}
					}
					if ($updateOrderRequired) {
						$comments = 'Order #' . $orderId;
						$this->model_checkout_order->addHistory($orderId, 7, $comments, true);  // 7 = Canceled
						$update_arr = array('transaction_meta' => json_encode($trxMeta), 'paid_crypto_amount' => 0, 'paid_fiat_amount' => 0, 'order_status' => ORDER_STATUS['expired']);
						$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'], $update_arr);
						$json['msg'] = 'Order has been expired.';
						$this->response->addHeader('Content-Type: application/json');
						$this->response->setOutput(json_encode($json));
						return;
					}
				}
			}
			$transactionInfo = $this->model_extension_coinremitter_payment_coinremitter->checkTransactionExists($order_info['transaction_meta'], $trx['txid']);
			$total_paid = $order_info['paid_crypto_amount'];
			$updateOrderRequired = false;

			if (empty($transactionInfo)) {
				$updateOrderRequired = true;
				$trxMeta[$trx['txid']] = $trx;
				$this->add_order_history($order_info['order_id'], $trx);
			} else {
				if ($transactionInfo['status_code'] == 0 && $trx['confirmations'] >= $trx['required_confirmations']) {
					$trxMeta[$trx['txid']] = $trx;
					$trxMeta[$trx['txid']]['status_code'] = 1;
					$updateOrderRequired = true;
				}
			}
			if ($trx['confirmations'] >= $trx['required_confirmations']) {
				$trxMeta[$trx['txid']]['status_code'] = 1;
				$total_paid += $trx['amount'];
			}
			if ($updateOrderRequired) {
				$truncationValue = $this->currency->convert(TRUNCATION_VALUE, 'USD', $order_info['fiat_symbol']);
				$truncationValue = number_format($truncationValue, 4, '.', '');
				$status = ORDER_STATUS['pending'];
				$total_fiat_paid = $total_paid * $order_info['fiat_amount'] / $order_info['crypto_amount'];
				$totalFiatPaidWithTruncation = $total_fiat_paid + $truncationValue;
				if ($total_paid == $order_info['crypto_amount']) {
					$status = ORDER_STATUS['paid'];
				} else if ($total_paid > $order_info['crypto_amount']) {
					$status = ORDER_STATUS['over_paid'];
				} else if ($total_paid != 0 && $total_paid < $order_info['crypto_amount']) {
					$status = ORDER_STATUS['under_paid'];
					if ($totalFiatPaidWithTruncation > $order_info['fiat_amount']) {
						$status = ORDER_STATUS['paid'];
					}
				}
				$update_arr = array('transaction_meta' => json_encode($trxMeta), 'paid_crypto_amount' => $total_paid, 'paid_fiat_amount' => $total_fiat_paid, 'order_status' => $status);
				$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'], $update_arr);
				if ($status == ORDER_STATUS['paid'] || $status == ORDER_STATUS['over_paid']) {
					$this->load->model('checkout/order');
					$this->add_paid_order_history($order_info['order_id']);
				}

				$json['flag'] = 1;
				$json['msg'] = 'success.';
				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}
			$json['flag'] = 1;
			$json['msg'] = 'No transaction update.';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}
		$json['flag'] = 1;
		$json['msg'] = 'No transaction update!';
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
		return;
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
	private function add_order_history($orderId, $trxData)
	{

		$this->load->model('extension/coinremitter/payment/coinremitter');
		$this->load->model('checkout/order');
		$order_cart = $this->model_checkout_order->getOrder($orderId);
		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($orderId);
		if (empty($order_info)) {
			return false;
		}
		if (!empty($trxData)) {

			$transaction = '<a href="' . $trxData['explorer_url'] . '" target="_blank">' . $trxData['txid'] . '</a> - ' . $trxData['amount'] . ' ' . $order_info['coin_symbol'] . '<br />Date : ' . $trxData['date'] . '<br /><br />';

			$enc_order_id = urlencode($this->obj_curl->encrypt($order_info['order_id']));  // order id in encryption format
			$order_url = $this->url->link('extension/coinremitter/module/coinremitter_invoice|detail&order_id=' . $enc_order_id);

			$comments = '';
			$comments .= 'Coinremitter Order - <a href="' . $order_url . '" target="_blank">#' . $order_info['order_id'] . '</a><br /><br />';
			$comments .= 'Fiat Currency : ' . $order_info['fiat_symbol'] . '<br />';
			$comments .= 'Coin : ' . $order_info['coin_symbol'] . '<br />';
			$comments .= 'Created Date : ' . $order_info['created_at'] . ' (UTC) <br />';
			$comments .= 'Expiry Date : ' . $order_info['expiry_date'] . ' (UTC) <br />';

			$comments .= 'Transaction :<br />';
			$comments .= $transaction;


			$this->load->model('checkout/order');

			if ($order_cart['order_status'] == 'Pending') {
				if ($order_info['order_status'] == ORDER_STATUS['over_paid'] || $order_info['order_status'] == ORDER_STATUS['paid']) {
					$status =  ($this->config->get('payment_coinremitter_order_status') == 0) ? 1 : 5;
					$this->model_checkout_order->addHistory($orderId, $status, $comments);  // 5 = Complete
				} else {
					$this->model_checkout_order->addHistory($orderId, 1, $comments);  // 1 = pending
				}
			} else {
				$status_id = $this->db->query("SELECT *  FROM " . DB_PREFIX . "order_status where name LIKE '%" . $this->db->escape($order_cart['order_status']) . "%'");

				$this->model_checkout_order->addHistory($orderId, $status_id->row['order_status_id'], $comments);
			}
		}
	}
}
