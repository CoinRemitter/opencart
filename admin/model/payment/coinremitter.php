<?php

namespace Opencart\Admin\Model\Extension\Coinremitter\Payment;

class Coinremitter extends \Opencart\System\Engine\Model
{

    public function install(): void
    {

        $charset_collate = "ENGINE=MyISAM DEFAULT CHARSET=utf8";
        $table_name = DB_PREFIX . 'coinremitter_wallets';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `wallet_name` varchar(255) NOT NULL COMMENT 'Wallet Name',
            `coin_symbol` varchar(255) NOT NULL COMMENT 'Coin Short Name',
            `coin_name` varchar(100) NOT NULL COMMENT 'Coin Full Name',
            `api_key` varchar(255) NOT NULL COMMENT 'API Key',
            `password` varchar(255) NOT NULL COMMENT 'Wallet Password',
            `minimum_invoice_amount` double(10,2) NOT NULL DEFAULT 0 COMMENT 'in fiat currency',
            `exchange_rate_multiplier` double(10,2) NOT NULL DEFAULT 1 COMMENT 'multiply order amount with this value',
            `unit_fiat_amount` double(20,4) NOT NULL DEFAULT 1 COMMENT 'crypto amount per fiat currency',
            `base_fiat_symbol` varchar(10) NOT NULL COMMENT 'Website base currency',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At Date',
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At Date',
            PRIMARY KEY (`id`)
            ) $charset_collate;";

        $this->db->query($sql);


        $table_name = DB_PREFIX . 'coinremitter_orders';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `order_id` varchar(255) NOT NULL COMMENT 'opencart orderid',
            `user_id` varchar(255) DEFAULT NULL COMMENT 'opencart user_id',
            `coin_symbol` varchar(10) NOT NULL COMMENT 'Coin symbol',
            `coin_name` varchar(100) NOT NULL COMMENT 'Coin full name',
            `crypto_amount` double(20,8) NOT NULL COMMENT 'Order total crypto amount',
            `fiat_symbol` varchar(10) NOT NULL COMMENT 'Fiat symbol',
            `fiat_amount` double(20,4) NOT NULL COMMENT 'Order amount in fiat currency',
            `paid_crypto_amount` double(20,8) NOT NULL DEFAULT 0 COMMENT 'Order crypto fiat amount',
            `paid_fiat_amount` double(20,4) NOT NULL DEFAULT 0 COMMENT 'Order paid fiat amount',
            `payment_address` varchar(255) NOT NULL COMMENT 'Payment address',
            `qr_code` text DEFAULT NULL COMMENT 'QR code',
            `order_status` TINYINT NOT NULL DEFAULT 0 COMMENT 'Order status; 0: pending, 1: paid, 2: under paid, 3: over paid, 4: expired, 5: cancelled',
            `transaction_meta` text DEFAULT NULL COMMENT 'Order transactions',
            `expiry_date` datetime DEFAULT NULL COMMENT 'Order expiry date',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At Date',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At Date',
            PRIMARY KEY (`id`)
            ) $charset_collate;";

        $this->db->query($sql);

        $data = array();
        $data['information_description'][1]['title'] = 'Terms & Conditions';
        $data['information_description'][1]['description']  = '<ul><li>
        <p><span style="font-size:14px"><span style="font-family:Arial,Helvetica,sans-serif"><strong>Please check the amount before you make the payment. The amount must be the same as the item you purchased.&nbsp;</strong></span></span></p>
        </li>
        <li>
        <p><span style="font-size:14px"><span style="font-family:Arial,Helvetica,sans-serif"><strong>The order will only be placed once the blockchain confirmed your transaction.</strong></span></span></p>
        </li>
        <li><p><span style="font-size:14px"><span style="font-family:Arial,Helvetica,sans-serif"><strong>Payment is depending on the blockchain overall speed. If blockchain is taking more time to process payments then it takes a little while to process your payment as well. Be patience.</strong></span></span></p>
        </li>
        <li>
        <p><span style="font-size:14px"><span style="font-family:Arial,Helvetica,sans-serif"><strong>If you are paying from ETH wallet to BTC wallet and if any error occurs during the cross blockchain transaction and payment gets debited from your wallet then we strictly can not recover your funds.</strong></span></span></p>
        </li>
        <li>
        <p><span style="font-size:14px"><span style="font-family:Arial,Helvetica,sans-serif"><strong>Before you make any transaction make sure you check the high fees.</strong></span></span></p>
        </li>
        <li>
        <p><span style="font-size:14px"><span style="font-family:Arial,Helvetica,sans-serif"><strong>If you are having any difficulties please contact our support team. </strong></span></span></p>
        </li>
        </ul>';
        $data['information_description'][1]['meta_title'] =  'The most important terms and conditions before you make any payment for your product purchase meant.';
        $data['information_description'][1]['meta_keyword'] = 'Opencart payment, Payment extension for opencart, Opencart extension,Crypto payment, Payment in cryptocurrency, opencart crypto payment extension';
        $data['information_description'][1]['meta_description'] = '';
        $data['information_seo_url'][0][1] = 'Payment extension for opencart';
        $data['sort_order'] = 5;
        $this->load->model('catalog/information');
        $json['information_id'] = $this->model_catalog_information->addInformation($data);
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "coinremitter_wallets`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "coinremitter_order`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "coinremitter_payment`;");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "coinremitter_webhook`;");
    }
}
