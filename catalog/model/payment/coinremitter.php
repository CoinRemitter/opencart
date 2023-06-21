<?php
namespace Opencart\Catalog\Model\Extension\Coinremitter\Payment;
class Coinremitter extends \Opencart\System\Engine\Model {
	public function getMethods($address, $total=0) {
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
                            'name' => "Pay with crypto options"
                    ];
                    $method_data = array(
                            'code'       => 'coinremitter',
                            'name'      => $this->config->get('payment_coinremitter_title'),
                            'terms'      => '',
                            'option'     => $option_data,
                            'sort_order' => 15
                    );
		}

		return $method_data;
	}

	/** checkIsValidColumn method is to check if oc_coinremitter_wallets table has column named `is_valid`. If does not exist in table then it will add `is_valid` column in oc_coinremitter_wallets table which is called from controller like $this->model_extension_payment_coinremitter->checkIsValidColumn();. cache is cleared for the coniremitter_wallet variable ***/

	public function checkIsValidColumn(){
		$check = $this->db->query("SHOW COLUMNS FROM " . DB_PREFIX . "coinremitter_wallets LIKE 'is_valid'");
		if ($check->num_rows == 0) {
		    $this->db->query("ALTER TABLE " . DB_PREFIX . "coinremitter_wallets ADD COLUMN `is_valid` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 on valid wallet else 0' AFTER `password`");
		    $this->cache->delete('coinremitter_wallets');
		}
	}


	/** getAllWallets method is to get wallets without pagination ***/
	public function getAllWallets()
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_wallets where `is_valid` = 1");
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
		$this->db->query("INSERT INTO " . DB_PREFIX . "coinremitter_order SET order_id = '" . $data['order_id'] . "', invoice_id = '" . $data['invoice_id'] . "', amountusd = '" . $data['amountusd'] . "', crp_amount = '". $data['crp_amount'] ."', payment_status = '". $data['payment_status'] ."', address = '". $data['address'] ."', qr_code = '". $data['qr_code'] ."'");
		$last_inserted_id = $this->db->getLastId();
		
		$this->cache->delete('coinremitter_order');
		return $last_inserted_id;
	}

	/** getOrder method is to retrieve the order data which is called from controller like $order_detail = $this->model_extension_payment_coinremitter->getOrder($order_id);. 
	Only one order with that order_id is returned  ***/
	public function getOrder($id)
	{
		$sql = "SELECT co.*,cp.*,`co`.`created_at` as 'order_created_at',`co`.`updated_at` as 'order_updated_at' FROM " . DB_PREFIX . "coinremitter_order as `co`, " . DB_PREFIX . "coinremitter_payment as `cp` WHERE `co`.`address` = `cp`.`address` AND  `co`.`order_id` = '".$id."'";
		$query = $this->db->query($sql);
		return $query->row;
	}

	/** getInvoiceOrder method is to retrieve the order data which is called from controller like $order_detail = $this->model_extension_payment_coinremitter->getInvoiceOrder($invoice_id);. 
	Only one order with that invoice_id is returned  ***/
	public function getInvoiceOrder($invoice_id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_order WHERE `invoice_id` = '". $invoice_id ."'");
		return $query->row;
	}


	/** getOrderByAddress method is to retrieve the order data which is called from controller like $order_detail = $this->model_extension_payment_coinremitter->getOrderByAddress($address);. 
	Only one order with that order_id is returned  ***/
	public function getOrderByAddress($address)
	{	
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_order WHERE `address` = '". $address ."'");

		if($query->num_rows > 0){
			$order_id = $query->row['order_id'];
			$order = $this->getOrder($order_id);
			return $order;
		}else{
			return [];
		}
	}

	/** updateOrder method is to update the order which is called from controller like $this->model_extension_payment_coinremitter->updateOrder($id,$order_data);. Data is updated in the oc_coinremitter_order table and cache is cleared for the coniremitter_order variable ***/
	public function updateOrder($order_id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_order SET payment_status = '". $data['payment_status'] ."' WHERE order_id = '" . $order_id . "'");
		
		$this->cache->delete('coinremitter_order');
	}

	/** updateInvoiceOrder method is to update the order which is called from controller like $this->model_extension_payment_coinremitter->updateInvoiceOrder($id,$order_data);. Data is updated in the oc_coinremitter_order table and cache is cleared for the coniremitter_order variable ***/
	public function updateInvoiceOrder($invoice_id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_order SET payment_status = '". $data['payment_status'] ."' WHERE invoice_id = '" . $invoice_id . "'");
		
		$this->cache->delete('coinremitter_order');
	}

	/** addPayment method is to add payment detail which is called from controller like $this->model_extension_payment_coinremitter->addPayment($payment_data);. Data is inserted in the oc_coinremitter_payment table and cache is cleared for the coinremitter_payment variable ***/
	public function addPayment($data)
	{
		$this->db->query("INSERT INTO " . DB_PREFIX . "coinremitter_payment SET order_id = '" . $data['order_id'] . "', invoice_id = '" . $data['invoice_id'] . "', address = '" . $data['address'] . "', invoice_name = '" . $data['invoice_name'] . "', marchant_name = '". $data['marchant_name'] ."', total_amount = '". $data['total_amount'] ."', paid_amount = '". $data['paid_amount'] ."', base_currancy = '". $data['base_currancy'] ."', description = '". $data['description'] ."', coin = '". $data['coin'] ."', payment_history = '". $data['payment_history'] ."', conversion_rate = '". $data['conversion_rate'] ."', invoice_url = '". $data['invoice_url'] ."', status = '". $data['status'] ."', expire_on = '". $data['expire_on'] ."', created_at = '". $data['created_at'] ."'");

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

	/** updatePaymentStatus method is to update the payment status which is called from controller like $this->model_extension_payment_coinremitter->updatePaymentStatus($id,$payment_data);. Data is updated in the oc_coinremitter_payment table and cache is cleared for the coniremitter_payment variable ***/
	public function updatePaymentStatus($id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_payment SET status = '". $data['status'] ."' WHERE order_id = '" . $id . "'");
		
		$this->cache->delete('coinremitter_payment');
	}

	/** updateInvoicePaymentStatus method is to update the payment status which is called from controller like $this->model_extension_payment_coinremitter->updateInvoicePaymentStatus($id,$payment_data);. Data is updated in the oc_coinremitter_payment table and cache is cleared for the coniremitter_payment variable ***/
	public function updateInvoicePaymentStatus($invoice_id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_payment SET status = '". $data['status'] ."' WHERE invoice_id = '" . $invoice_id . "'");
		
		$this->cache->delete('coinremitter_payment');
	}

	/** getWebhook method is to retrieve the webhook data which is called from controller like $webhook_info = $this->model_extension_payment_coinremitter->getWebhook($id);***/
	public function getWebhook($id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_webhook WHERE `transaction_id` = '". $id ."'");
		return $query->row;
	}

	/** getWebhookByAddress method is to retrieve the webhook data by address which is called from controller like $getWebhookByAddressRes = $this->model_extension_payment_coinremitter->getWebhookByAddress($address);***/
	public function getWebhookByAddress($address)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_webhook WHERE `address` = '". $address ."'");
		return $query->rows;
	}

	/** addWebhook method is to add webhook detail which is called from controller like $this->model_extension_payment_coinremitter->addWebhook($insert_arr);. Data is inserted in the oc_coinremitter_webhook table and cache is cleared for the coinremitter_webhook variable ***/
	public function addWebhook($data)
	{
		$this->db->query("INSERT INTO " . DB_PREFIX . "coinremitter_webhook SET order_id = '" . $data['order_id'] . "',address = '" . $data['address'] . "', transaction_id = '" . $data['transaction_id'] . "', txId = '" . $data['txId'] . "', explorer_url = '" . $data['explorer_url'] . "', paid_amount = '". $data['paid_amount'] ."', coin = '". $data['coin'] ."', confirmations = '". $data['confirmations'] ."', paid_date = '". $data['paid_date'] ."'");

		$last_inserted_id = $this->db->getLastId();
		
		$this->cache->delete('coinremitter_webhook');
		return $last_inserted_id;
	}

	/** updateTrxConfirmation method is to update the webhook confirmation which is called from controller like $this->model_extension_payment_coinremitter->updateTrxConfirmation($id,$update_confirmation);. Data is updated in the oc_coinremitter_webhook table and cache is cleared for the coniremitter_webhook variable ***/
	public function updateTrxConfirmation($id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_webhook SET confirmations = '". $data['confirmations'] ."' WHERE transaction_id = '" . $id . "'");
		
		$this->cache->delete('coinremitter_payment');
	}


	/** getSpecificWebhookTrxByAddress method is to retrieve the webhook data which is has less than 3 confirmations, called from controller like $webhook_res = $this->model_extension_payment_coinremitter->getSpecificWebhookTrxByAddress($order_info['address']);***/
	public function getSpecificWebhookTrxByAddress($address)
	{
		$sql = "SELECT * FROM " . DB_PREFIX . "coinremitter_webhook WHERE `address` = '". $address ."' AND `confirmations` < 3";
		$query = $this->db->query($sql);
		return $query->rows;
	}


	/*** getTotalPaidAmountByAddress method is to retrieve total amount of all transactions which have 3 or more than 3 confirmations, called from controller like $total_paid = $this->model_extension_payment_coinremitter->getTotalPaidAmountByAddress($address);***/
	public function getTotalPaidAmountByAddress($address)
	{
		$sql = "SELECT SUM(`paid_amount`) AS `total_paid` FROM " . DB_PREFIX . "coinremitter_webhook WHERE `address` = '". $address ."' AND `confirmations` >= 3";
		$query = $this->db->query($sql);
		return $query->row;
	}

}
