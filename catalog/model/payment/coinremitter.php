<?php

namespace Opencart\Catalog\Model\Extension\Coinremitter\Payment;

use function PHPSTORM_META\type;

class Coinremitter extends \Opencart\System\Engine\Model
{
	public function getMethods($address, $total = 0)
	{
		$this->load->language('extension/coinremitter/payment/coinremitter');

		if ($total <= 0.00) {
			$status = true;
		} else {
			$status = false;
		}
		$status = true;

		$method_data = array();

		if ($status) {
			$option_data['coinremitter'] = [
				'code' => 'coinremitter.coinremitter',
				'name' => $this->config->get('payment_coinremitter_title')
			];
			if (version_compare(VERSION, '4.0.1.1', '>')) {
				$method_data = array(
					'code'       => 'coinremitter',
					'title'      => $this->config->get('payment_coinremitter_title'),
					'name'       => $this->config->get('payment_coinremitter_title'),
					'terms'      => '',
					'option'     => $option_data,
					'sort_order' => 15
				);
			} else {
				$method_data = array(
					'code'       => 'coinremitter',
					'title'      => $this->config->get('payment_coinremitter_title'),
					'terms'      => '',
					'sort_order' => 15,
				);
			}
		}

		return $method_data;
	}

	/** getAllWallets method is to get wallets without pagination ***/
	public function getAllWallets()
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_wallets");
		return $query->rows;
	}

	/** getWallet method is to retrieve the wallet data which is called from controller like $wallet_info = $this->model_extension_payment_coinremitter->getWallet($coin);. Only one wallet with that coin is returned  ***/
	public function getWalletByCoin($coin_symbol)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_wallets WHERE `coin_symbol` = '" . $this->db->escape($coin_symbol) . "' LIMIT 1");
		return $query->row;
	}

	/** getWallet method is to retrieve the wallet data which is called from controller like $wallet_info = $this->model_extension_payment_coinremitter->getWallet($coin);. Only one wallet with that coin is returned  ***/
	public function getWalletById($id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_wallets WHERE `id` = '" . (int)$id . "' LIMIT 1");
		return $query->row;
	}

	/** addOrder method is to add order detail which is called from controller like $this->model_extension_payment_coinremitter->addOrder($order_data);. Data is inserted in the oc_coinremitter_orders table and cache is cleared for the coinremitter_orders variable ***/
	public function addOrder($data)
	{
		$this->db->query("INSERT INTO " . DB_PREFIX . "coinremitter_orders SET order_id = '" . $this->db->escape($data['order_id']) . "', user_id = '" . $this->db->escape($data['user_id']) . "', coin_symbol = '" . $this->db->escape($data['coin_symbol']) . "', coin_name = '" . $this->db->escape($data['coin_name']) . "', crypto_amount = '" . $this->db->escape($data['crypto_amount']) . "', fiat_amount = '" . $this->db->escape($data['fiat_amount']) . "', fiat_symbol = '" . $this->db->escape($data['fiat_symbol']) . "', payment_address = '" . $this->db->escape($data['payment_address']) . "', expiry_date = '" . $this->db->escape($data['expiry_date']) . "', qr_code = '" . $this->db->escape($data['qr_code']) . "'");
		$last_inserted_id = $this->db->getLastId();

		$this->cache->delete('coinremitter_orders');
		return $last_inserted_id;
	}

	/** getOrder method is to retrieve the order data which is called from controller like $order_detail = $this->model_extension_payment_coinremitter->getOrder($order_id);.Only one order with that order_id is returned  ***/
	public function getOrder($id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_orders WHERE `order_id` = '" . $this->db->escape($id) . "'");
		return $query->row;
	}

	/** 
	 * getOrderByAddress method is to retrieve the order data which is called from controller like $order_detail = $this->model_extension_payment_coinremitter->getOrderByAddress($address);.Only one order with that order_id is returned
	 **/
	public function getOrderByAddress($address)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_orders WHERE `payment_address` = '" . $this->db->escape($address) . "' LIMIT 1");
		if ($query->num_rows > 0) {
			$order_id = $query->row['order_id'];
			$order = $this->getOrder($order_id);
			return $order;
		} else {
			return [];
		}
	}

	/** updateOrder method is to update the order which is called from controller like $this->model_extension_payment_coinremitter->updateOrder($id,$order_data);. Data is updated in the oc_coinremitter_orders table and cache is cleared for the coniremitter_order variable ***/
	public function updateOrderStatus($order_id, $status)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_orders SET order_status = '" . (int)$status . "' WHERE order_id = '" . $this->db->escape($order_id) . "'");

		$this->cache->delete('coinremitter_orders');
	}

	/** updateInvoiceOrder method is to update the order which is called from controller like $this->model_extension_payment_coinremitter->updateInvoiceOrder($id,$order_data);. Data is updated in the oc_coinremitter_orders table and cache is cleared for the coniremitter_order variable ***/
	public function updateOrder($order_id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_orders SET `order_status` = " . (int)$data['order_status'] . ", `transaction_meta` = '" . $this->db->escape($data['transaction_meta']) . "', `paid_crypto_amount` = '" . $this->db->escape($data['paid_crypto_amount']) . "', `paid_fiat_amount` = '" . $this->db->escape($data['paid_fiat_amount']) . "' WHERE `order_id` = '" . $this->db->escape($order_id) . "'");

		$this->cache->delete('coinremitter_orders');
	}

	/** updateInvoiceOrder method is to update the order which is called from controller like $this->model_extension_payment_coinremitter->updateInvoiceOrder($id,$order_data);. Data is updated in the oc_coinremitter_orders table and cache is cleared for the coniremitter_order variable ***/
	public function checkTransactionExists($transactions, $trxId)
	{
		if (empty($transactions)) {
			return [];
		}
		if (isset($transactions[$trxId])) {
			return $transactions[$trxId];
		}
		return [];
	}
}
