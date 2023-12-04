<?php
class ModelExtensionPaymentCoinremitter extends Model {

	public function install() {
        $charset_collate = "ENGINE=MyISAM DEFAULT CHARSET=utf8";
        $table_name = DB_PREFIX.'coinremitter_wallets';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `coin` varchar(255) NOT NULL COMMENT 'Coin Short Name',
            `coin_name` varchar(100) NOT NULL COMMENT 'Coin Full Name',
            `name` varchar(255) NOT NULL COMMENT 'Wallet Name',
            `balance` varchar(255) NOT NULL COMMENT 'Wallet Balance',
            `api_key` varchar(255) NOT NULL COMMENT 'API Key',
            `password` varchar(255) NOT NULL COMMENT 'Wallet Password',
            `exchange_rate_multiplier` varchar(255) NOT NULL DEFAULT 1 COMMENT 'between 0 to 101',
            `minimum_value` varchar(255)     NOT NULL DEFAULT 0.5 COMMENT 'between 0.01 to 1000000',
            `is_valid` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 on valid wallet else 0',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At Date',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At Date',
            PRIMARY KEY (`id`)
            ) $charset_collate;";
    
		$this->db->query($sql);


        $table_name = DB_PREFIX.'coinremitter_order';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `order_id` varchar(255) NOT NULL COMMENT 'opencart orderid',
            `invoice_id` varchar(255) NOT NULL COMMENT 'Invocie orderid',
            `amountusd` varchar(255) NOT NULL COMMENT 'amount in usd',
            `crp_amount` varchar(255) NOT NULL COMMENT 'crp amount',
            `payment_status` varchar(255) NOT NULL COMMENT 'payment status',
            `address` varchar(255) NOT NULL COMMENT 'order address(wallet)',
            `qr_code` varchar(255) NOT NULL COMMENT 'address qr cdde',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At Date',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At Date',
            PRIMARY KEY (`id`)
            ) $charset_collate;";
    
        $this->db->query($sql);

        $table_name = DB_PREFIX.'coinremitter_payment';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `order_id` varchar(255) NOT NULL COMMENT 'opencart orderid',
            `invoice_id` varchar(255) NOT NULL COMMENT 'Invoice orderid',
            `address` varchar(255) NOT NULL COMMENT 'order address(wallet)',
            `invoice_name` varchar(255) NOT NULL COMMENT 'Invoice Name',
            `marchant_name` varchar(255) NOT NULL COMMENT 'Marchant Name',
            `total_amount` varchar(255) NOT NULL COMMENT 'Total Amount',
            `paid_amount` varchar(255) NOT NULL COMMENT 'Paid Amount',
            `base_currancy` varchar(255) NOT NULL COMMENT 'Base Currancy',
            `description` varchar(255) NOT NULL COMMENT 'Description',
            `coin` varchar(255) NOT NULL COMMENT 'Coin',
            `payment_history` text NOT NULL COMMENT 'Payment History',
            `conversion_rate` text NOT NULL COMMENT 'Conversion Rate',
            `invoice_url` varchar(255) NOT NULL COMMENT 'Invoice Url',
            `status` varchar(100) NOT NULL COMMENT 'Invoice Status',
            `expire_on` varchar(255) NOT NULL COMMENT 'Expiration Time',
            `created_at` varchar(255) NOT NULL COMMENT 'Invoice Created Date',
            PRIMARY KEY (`id`)
            ) $charset_collate;";
    
        $this->db->query($sql);

        $table_name = DB_PREFIX.'coinremitter_webhook';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `order_id` varchar(255) NOT NULL COMMENT 'opencart orderid',
            `address` varchar(255) NOT NULL COMMENT 'Wallet Address',
            `transaction_id` varchar(255) NOT NULL COMMENT 'id of webhook',
            `txId` varchar(255) NOT NULL COMMENT 'txId of webhook',
            `explorer_url` varchar(255) NOT NULL COMMENT 'explorer url of transaction',
            `paid_amount` varchar(255) NOT NULL COMMENT 'Paid Amount',
            `coin` varchar(255) NOT NULL COMMENT 'coin',
            `confirmations` int(11) NOT NULL COMMENT 'transaction confirmations',
            `paid_date` datetime NOT NULL COMMENT 'transaction date',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'transaction created date',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'transaction updated date',
            PRIMARY KEY (`id`)
            ) $charset_collate;";
    
        $this->db->query($sql);

		
	}

	public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "coinremitter_wallets`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "coinremitter_order`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "coinremitter_payment`;");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "coinremitter_webhook`;");
	}

	
}
