<?php
namespace Opencart\Admin\Model\Extension\Coinremitter\Module;
class Coinremitter extends \Opencart\System\Engine\Model
{
	/** addWallet method is to add wallet which is called from controller like $this->model_extension_module_coinremitter->addWallet($this->request->post);. Data is inserted in the oc_coinremitter_wallets table and cache is cleared for the coinremitter_wallets variable ***/
	public function addWallet($data)
	{
		$this->db->query("INSERT INTO " . DB_PREFIX . "coinremitter_wallets SET coin = '" . $data['coin'] . "', coin_name = '" . $data['coin_name'] . "', name = '" . $data['name'] . "', balance = '". $data['balance'] ."', api_key = '". $data['api_key'] ."', password = '". $data['password'] ."',exchange_rate_multiplier ='".$data['exchange_rate_multiplier'] ."',minimum_value ='".$data['minimum_value'] ."'");
		$wallet_id = $this->db->getLastId();
		
		$this->cache->delete('coinremitter_wallets');
		return $wallet_id;
	}

	/** editWallet method is to update the wallet which is called from controller like $this->model_extension_module_coinremitter->editWallet($id,$this->request->post);. Data is updated in the oc_coinremitter_wallets table and cache is cleared for the coniremitter_wallet variable ***/
	
	public function editWallet($id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_wallets SET coin_name = '". $data['coin_name'] ."', name = '". $data['name'] ."', balance = '". $data['balance'] ."', api_key = '" . $data['api_key'] . "', password = '" . $data['password'] . "',exchange_rate_multiplier ='".$data['exchange_rate_multiplier'] ."',minimum_value ='".$data['minimum_value'] ."', is_valid = '" . $data['is_valid'] . "' WHERE id = '" . (int) $id . "'");
		
		$this->cache->delete('coinremitter_wallets');
	}

	/** editWalletStatus method is to update the wallet balance and is_valid status which is called from controller like $this->model_extension_module_coinremitter->editWalletStatus($id,$this->request->post);. Data is updated in the oc_coinremitter_wallets table and cache is cleared for the coniremitter_wallet variable ***/
	
	public function editWalletStatus($id, $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "coinremitter_wallets SET balance = '". $data['balance'] ."', is_valid = '" . $data['is_valid'] . "' WHERE id = '" . (int) $id . "'");
		
		$this->cache->delete('coinremitter_wallets');
	}

	/** checkIsValidColumn method is to check if oc_coinremitter_wallets table has column named `is_valid`. If does not exist in table then it will add `is_valid` column in oc_coinremitter_wallets table which is called from controller like $this->model_extension_module_coinremitter->checkIsValidColumn();. cache is cleared for the coniremitter_wallet variable ***/

	public function checkIsValidColumn(){
		$check = $this->db->query("SHOW COLUMNS FROM " . DB_PREFIX . "coinremitter_wallets LIKE 'is_valid'");
		if ($check->num_rows == 0) {
		    $this->db->query("ALTER TABLE " . DB_PREFIX . "coinremitter_wallets ADD COLUMN `is_valid` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 on valid wallet else 0' AFTER `password`");
		    $this->cache->delete('coinremitter_wallets');
		}
	}

	/** deleteWallet method is to delete the wallet which is called from controller like $this->model_extension_module_coinremitter->deleteWallet($id);. Data is removed from the oc_coinremitter_wallets table and cache is cleared for the coinremitter_wallets variable ***/
	public function deleteWallet($id)
	{

		$this->db->query("DELETE FROM " . DB_PREFIX . "coinremitter_wallets WHERE id = '" . (int) $id . "'");
		$this->cache->delete('coinremitter_wallets');
	}

	/** getWallet method is to retrieve the wallet which is called from controller like $wallet_info = $this->model_extension_module_coinremitter->getWallet($this->request->get['id']);. Only one wallet with that id is returned  ***/
	public function getWallet($id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_wallets WHERE `id` = " . (int) $id );
		return $query->row;
	}
	/** getWallets method is to retrieve the wallets which is called from controller like $results = $this->model_extension_module_coinremitter->getWallets($filter_data);. $data is the filtering parameter. Multiple wallets are returned  ***/
	public function getWallets($data = array())
	{
		$sql = "SELECT * FROM " . DB_PREFIX . "coinremitter_wallets";
		$sort_data = array(
			'coin'
		);
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY id";
		}
		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}
		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}
			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}
			$sql .= " LIMIT " . (int) $data['start'] . "," . (int) $data['limit'];
		}
		$query = $this->db->query($sql);
		return $query->rows;
	}

	/** getAllWallets method is to get wallets without pagination ***/
	public function getAllWallets()
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "coinremitter_wallets");
		return $query->rows;
	}
	
	/** getTotalWallets method is to count the total number of wallets ***/
	public function getTotalWallets()
	{
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "coinremitter_wallets");
		return $query->row['total'];
	}

	/** getOrder method is to retrieve the order data which is called from controller like $order_detail = $this->model_extension_module_coinremitter->getOrder($order_id);. 
	Only one order with that order_id is returned  ***/
	public function getOrder($id)
	{
		$sql = "SELECT co.*,cp.*,`co`.`created_at` as 'order_created_at',`co`.`updated_at` as 'order_updated_at' FROM " . DB_PREFIX . "coinremitter_order as `co`, " . DB_PREFIX . "coinremitter_payment as `cp` WHERE `co`.`address` = `cp`.`address` AND  `co`.`order_id` = '".$id."'";
		$query = $this->db->query($sql);
		return $query->row;
	}
	
}