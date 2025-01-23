<?php

namespace Opencart\Catalog\Controller\Extension\Coinremitter\Module;

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
class CoinremitterInvoice extends \Opencart\System\Engine\Controller
{

	private $obj_curl;
	public function __construct($registry)
	{
		parent::__construct($registry);

		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$this->obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);
	}

	public function detail()
	{

		$enc_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';
		$order_id = $this->obj_curl->decrypt($enc_order_id);
		//check if order id exists in coinremitter_order or not
		$this->load->model('extension/coinremitter/payment/coinremitter');
		$coinremitter_order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($order_id);
		if (empty($coinremitter_order_info)) {
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
		
		if (!$order_info) {
			return new \Opencart\System\Engine\Action('error/not_found');
		}
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

		if ($data['payment_address'] == '') {
			$data['payment_address'] = $data['shipping_address'];
		}
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
					'value' => (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value)
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
		$this->document->setTitle($this->language->get('invoice_title'));

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$this->document->addStyle('extension/coinremitter/catalog/view/javascript/coinremitter/css/coinremitter_invoice.css');
		$this->document->addScript('extension/coinremitter/catalog/view/javascript/coinremitter/js/coinremitter_invoice.js');
		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');
		if ($order_info['order_status_id'] == 1) {
			$data['order_status'] = '';
		} else {
			$data['order_status'] = $order_info['order_status'];
		}

		$this->response->setOutput($this->load->view('extension/coinremitter/module/coinremitter_invoice', $data));
	}


	public function check_payment()
	{


		if (!isset($this->request->post['address']) || $this->request->post['address'] == '') {
			$json['flag'] = 0;
			$json['msg'] = 'Invalid Address';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return false;
		}
		$address = $this->request->post['address'];
		$this->load->model('extension/coinremitter/payment/coinremitter');
		$this->load->model('checkout/order');
		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrderByAddress($address);
		if (empty($order_info)) {
			$json['flag'] = 0;
			$json['msg'] = 'Invalid Address';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return false;
		}
		$address = $order_info['payment_address'];
		$pendingCryptoAmount = $order_info['crypto_amount'] - $order_info['paid_crypto_amount'];
		$expire_on = null;
		if ($order_info['expiry_date'] != 0) {
			$expire_on = date('M d, Y H:i:s', strtotime($order_info['expiry_date']));
		}
		$order_info['transaction_meta'] = $order_info['transaction_meta'] ? json_decode($order_info['transaction_meta'], true) : [];
		$returnData = array(
			'flag' => 1,
			'msg' => 'Success',
			'data' => [
				"enc_order_id" => urlencode($this->obj_curl->encrypt($order_info['order_id'])),
				"now_time" => date('Y-m-d, H:i:s'),
				"coin_symbol" => $order_info['coin_symbol'],
				"status" => ORDER_STATUS_CODE[$order_info['order_status']],
				"status_code" => $order_info['order_status'],
				"transactions" => $order_info['transaction_meta'],
				"paid_amount" => number_format($order_info['paid_crypto_amount'], 8, '.', ''),
				"pending_amount" => number_format($pendingCryptoAmount, 8, '.', ''),
				"expire_on" => $expire_on,
			]
		);
		if ($order_info['order_status'] != ORDER_STATUS['pending'] && $order_info['order_status'] != ORDER_STATUS['under_paid']) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($returnData));
			return false;
		}
		$coinSymbol = $order_info['coin_symbol'];
		$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWalletByCoin($coinSymbol);

		if (empty($wallet_info)) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($returnData));
			return false;
		}

		/*** Now get all transactions by address from api ***/
		$trxParam = array(
			'api_key'	=>	$wallet_info['api_key'],
			'password'	=>	$wallet_info['password'],
			'address'	=> 	$address
		);

		$getTransactionByAddressRes = $this->obj_curl->apiCall('/wallet/address/transactions', $trxParam);
		if (!$getTransactionByAddressRes || !$getTransactionByAddressRes['success']) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($returnData));
			return false;
		}
		$trxByAddressData = $getTransactionByAddressRes['data'];
		$allTrx = $trxByAddressData['transactions'];

		if (empty($allTrx)) {
			if (
				$order_info['order_status'] == ORDER_STATUS['pending']
				&& isset($order_info['expiry_date']) && $order_info['expiry_date'] != ''
				&& time() >= strtotime($order_info['expiry_date'])
			) {
				//update payment_status,status as expired
				$status = 'expired';
				$this->model_extension_coinremitter_payment_coinremitter->updateOrderStatus($order_info['order_id'], ORDER_STATUS['expired']);
				$comments = 'Order #' . $order_info['order_id'];
				$this->model_checkout_order->addHistory($order_info['order_id'], 7, $comments, true);  // 7 = Canceled
				$returnData['data']['status'] = ORDER_STATUS_CODE[ORDER_STATUS['expired']];
				$returnData['data']['status_code'] = ORDER_STATUS['expired'];

				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($returnData));
				return false;
			}
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($returnData));
			return false;
		}

		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrderByAddress($address);
		$order_info['transaction_meta'] = $order_info['transaction_meta'] ? json_decode($order_info['transaction_meta'], true) : [];
		$total_paid = 0;
		$trxMeta = $order_info['transaction_meta'];
		$updateOrderRequired = false;
		foreach ($allTrx as $trx) {
			if (isset($trx['type']) && $trx['type'] == 'receive') {
				$fiat_amount = ($trx['amount'] * $order_info['fiat_amount']) / $order_info['crypto_amount'];
				$minFiatAmount = $this->currency->convert($wallet_info['minimum_invoice_amount'], $wallet_info['base_fiat_symbol'], $order_info['fiat_symbol']);
				$minFiatAmount = number_format($minFiatAmount, 2, '.', '');
				if($fiat_amount < $minFiatAmount){
					continue;
				}

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
				if ($trx['status_code'] == 1) {
					$total_paid += $trx['amount'];
				}
			}
		}


		if ($updateOrderRequired) {
			$truncationValue = $this->currency->convert(TRUNCATION_VALUE, 'USD', $order_info['fiat_symbol']);
			$truncationValue = number_format($truncationValue, 4, '.', '');
			$status = ORDER_STATUS['pending'];
			$total_fiat_paid = ($total_paid * $order_info['fiat_amount']) / $order_info['crypto_amount'];
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
			$update_arr = array(
				'transaction_meta' => json_encode($trxMeta),
				'paid_crypto_amount' => $total_paid,
				'paid_fiat_amount' => $total_fiat_paid,
				'order_status' => $status
			);
			$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'], $update_arr);
			if ($status == ORDER_STATUS['paid'] || $status == ORDER_STATUS['over_paid']) {
				$this->load->model('checkout/order');
				$this->add_paid_order_history($order_info['order_id']);
			}

			$returnData['data']['status'] = ORDER_STATUS_CODE[$status];
			$returnData['data']['status_code'] = $status;
			$returnData['data']['transactions'] = $trxMeta;
			$returnData['data']['paid_amount'] = number_format($total_paid, 8, '.', '');
			$returnData['data']['pending_amount'] = number_format($order_info['crypto_amount'] - $total_paid, 8, '.', '');
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($returnData));
		return false;
	}

	public function expired()
	{

		$enc_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';
		$orderId = $this->obj_curl->decrypt($enc_order_id);

		if ($orderId == '') {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		//check if order id exists in coinremitter_order or not
		$this->load->model('extension/coinremitter/payment/coinremitter');
		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($orderId);

		if (empty($order_info)) {
			return new \Opencart\System\Engine\Action('error/not_found');
		}
		$this->load->model('checkout/order');
		$order_cart = $this->model_checkout_order->getOrder($orderId);

		if ($order_cart['order_status'] == 'Canceled') {
			return new \Opencart\System\Engine\Action('checkout/failure');
		}
		if (!isset($order_info['expiry_date']) || $order_info['expiry_date'] == null) {
			return new \Opencart\System\Engine\Action('error/not_found');
		}
		if (time() < strtotime($order_info['expiry_date'])) {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		$coin = $order_info['coin_symbol'];
		$address = $order_info['payment_address'];
		$wallet_info = $this->model_extension_coinremitter_payment_coinremitter->getWalletByCoin($coin);
		if (empty($wallet_info)) {
			return new \Opencart\System\Engine\Action('error/not_found');
		}

		$get_trx_params = array(
			'api_key'	=>	$wallet_info['api_key'],
			'password'	=>	$wallet_info['password'],
			'address'	=> $address
		);

		$getTransactionByAddressRes = $this->obj_curl->apiCall('/wallet/address/transactions', $get_trx_params);
		if (!$getTransactionByAddressRes || !$getTransactionByAddressRes['success']) {
			return new \Opencart\System\Engine\Action('error/not_found');
		}
		if (empty($getTransactionByAddressRes['data']['transactions'])) {
			//update payment_status,status as expired
			$status = ORDER_STATUS['expired'];
			$update_arr = array('order_status' => $status);
			$this->model_extension_coinremitter_payment_coinremitter->updateOrderStatus($orderId, $update_arr);

			/*** Update order history status to canceled, add comment  ***/
			$comments = 'Order #' . $orderId;
			$this->model_checkout_order->addHistory($orderId, 7, $comments, true);  // 7 = Canceled

			return new \Opencart\System\Engine\Action('checkout/failure');
		}
		$getTrxByAddData = $getTransactionByAddressRes['data'];
		$allTrx = $getTrxByAddData['transactions'];
		$trxMeta = [];
		$order_info['transaction_meta'] = $order_info['transaction_meta'] ? json_decode($order_info['transaction_meta'], true) : [];
		foreach ($allTrx as $trx) {
			if (isset($trx['type']) && $trx['type'] == 'receive') {
				$transactionInfo = $this->model_extension_coinremitter_payment_coinremitter->checkTransactionExists($order_info['transaction_meta'], $trx['txid']);
				if (empty($transactionInfo)) {
					$trxMeta[$trx['txid']] = $trx;
					$this->add_order_history($order_info['order_id'], $trx);
				}
			}
		}
		$update_arr = array('transaction_meta' => json_encode($trxMeta), 'paid_crypto_amount' => 0, 'paid_fiat_amount' => 0, 'order_status' => $order_info['order_status']);
		$this->model_extension_coinremitter_payment_coinremitter->updateOrder($order_info['order_id'], $update_arr);
		return new \Opencart\System\Engine\Action('checkout/failure');
	}


	public function success()
	{

		$enc_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';
		$orderId = $this->obj_curl->decrypt($enc_order_id);
		if ($orderId == '') {
			return new \Opencart\System\Engine\Action('error/not_found');
		}
		//check if order id exists in coinremitter_order or not
		$this->load->model('extension/coinremitter/payment/coinremitter');
		$order_info = $this->model_extension_coinremitter_payment_coinremitter->getOrder($orderId);

		if (empty($order_info)) {
			return new \Opencart\System\Engine\Action('error/not_found');
		}
		$order_info['transaction_meta'] = $order_info['transaction_meta'] ? json_decode($order_info['transaction_meta'], true) : [];
		if (($order_info['order_status'] == ORDER_STATUS['paid'] || $order_info['order_status'] == ORDER_STATUS['over_paid']) && !empty($order_info['transaction_meta'])) {
			return new \Opencart\System\Engine\Action('checkout/success');
		} else {
			return new \Opencart\System\Engine\Action('error/not_found');
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
