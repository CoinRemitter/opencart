<?php
#Bitpay
class ModelExtensionPaymentCoinremitter extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/coinremitter');

		if ($total <= 0.00) {
			$status = true;
		} else {
			$status = false;
        }
        $status = true;

		$method_data = array();

		if ($status) {
			$method_data = array(
				'code'       => 'coinremitter',
				'title'      => $this->config->get('payment_coinremitter_title'),
				'terms'      => '',
				'sort_order' => 15
			);
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
	public function getWallet($coin)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_wallets WHERE `coin` = '". $coin ."'");
		return $query->row;
	}

	/** addOrder method is to add order detail which is called from controller like $this->model_extension_payment_coinremitter->addOrder($order_data);. Data is inserted in the oc_coinremitter_order table and cache is cleared for the coinremitter_order variable ***/
	public function addOrder($data)
	{
		$this->db->query("INSERT INTO " . DB_PREFIX . "coinremitter_order SET order_id = '" . $data['order_id'] . "', invoice_id = '" . $data['invoice_id'] . "', amountusd = '" . $data['amountusd'] . "', crp_amount = '". $data['crp_amount'] ."', payment_status = '". $data['payment_status'] ."'");
		$last_inserted_id = $this->db->getLastId();
		
		$this->cache->delete('coinremitter_order');
		return $last_inserted_id;
	}

	/** getOrder method is to retrieve the order data which is called from controller like $order_detail = $this->model_extension_payment_coinremitter->getOrder($invoice_id);. 
	Only one order with that invoice_id is returned  ***/
	public function getOrder($id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_order WHERE `invoice_id` = '". $id ."'");
		return $query->row;
	}

	/** updateOrder method is to update the order which is called from controller like $this->model_extension_payment_coinremitter->updateOrder($id,$order_data);. Data is updated in the oc_coinremitter_order table and cache is cleared for the coniremitter_order variable ***/
	public function updateOrder($id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_order SET payment_status = '". $data['payment_status'] ."' WHERE invoice_id = '" . $id . "'");
		
		$this->cache->delete('coinremitter_order');
	}

	/** addPayment method is to add payment detail which is called from controller like $this->model_extension_payment_coinremitter->addPayment($payment_data);. Data is inserted in the oc_coinremitter_payment table and cache is cleared for the coinremitter_payment variable ***/
	public function addPayment($data)
	{
		$this->db->query("INSERT INTO " . DB_PREFIX . "coinremitter_payment SET order_id = '" . $data['order_id'] . "', invoice_id = '" . $data['invoice_id'] . "', invoice_name = '" . $data['invoice_name'] . "', marchant_name = '". $data['marchant_name'] ."', total_amount = '". $data['total_amount'] ."', paid_amount = '". $data['paid_amount'] ."', base_currancy = '". $data['base_currancy'] ."', description = '". $data['description'] ."', coin = '". $data['coin'] ."', payment_history = '". $data['payment_history'] ."', conversion_rate = '". $data['conversion_rate'] ."', invoice_url = '". $data['invoice_url'] ."', status = '". $data['status'] ."', expire_on = '". $data['expire_on'] ."', created_at = '". $data['created_at'] ."'");

		$last_inserted_id = $this->db->getLastId();
		
		$this->cache->delete('coinremitter_payment');
		return $last_inserted_id;
	}

	/** updatePayment method is to update the payment detail which is called from controller like $this->model_extension_payment_coinremitter->updatePayment($id,$payment_data);. Data is updated in the oc_coinremitter_payment table and cache is cleared for the coniremitter_payment variable ***/
	public function updatePayment($id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_payment SET order_id = '". $data['order_id'] ."',invoice_id = '". $data['invoice_id'] ."',invoice_name = '". $data['invoice_name'] ."',marchant_name = '". $data['marchant_name'] ."',total_amount = '". $data['total_amount'] ."',paid_amount = '". $data['paid_amount'] ."',base_currancy = '". $data['base_currancy'] ."',description = '". $data['description'] ."',coin = '". $data['coin'] ."',payment_history = '". $data['payment_history'] ."',conversion_rate = '". $data['conversion_rate'] ."',invoice_url = '". $data['invoice_url'] ."',status = '". $data['status'] ."',expire_on = '". $data['expire_on'] ."',created_at = '". $data['created_at'] ."' WHERE invoice_id = '" . $id . "'");
		$this->cache->delete('coinremitter_payment');
	}
}
