<?php

namespace Opencart\Catalog\Model\Extension\Coinremitter\Payment;

define('CR_BASE_URL', 'https://api.coinremitter.com/');
class CoinremitterApi extends \Opencart\System\Engine\Model
{
    private static $instance;

    public $skey = "coinremitter"; // Store the encryption key
    public $ciphering = "AES-256-CBC"; // Store the cipher method
    public $options = 0; //a bitwise disjunction of the flags OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING.
    public $encryption_iv = 'Coinremitter__iv'; // Non-NULL (precisely 16 bytes) Initialization Vector for encryption

    /**
     * @param  object  $registry  Registry Object
     */
    public static function get_instance($registry)
    {
        if (is_null(static::$instance)) {
            static::$instance = new static($registry);
        }

        return static::$instance;
    }

    /**
     * @param  string  $endPoint   api end point
     * @param  array  $postdata  Key-value pair
     * @param  bool $is_get  TRUE/FALSE 
     */

    public function apiCall($endPoint = '', $postdata = array())
    {
        $api_url = CR_BASE_URL;
        $api_version = 'v1';
        $plugin_version = '0.0.4';
        $url = $api_url . $api_version . $endPoint;

        $opencart_version = '';
        if (VERSION) {
            $opencart_version = VERSION;
        }
        $user_agent = 'api@' . $api_version . ' opencart@' . $opencart_version . ' checkout-module' . ' version@' . $plugin_version;

        $header = array(
            "Content-Type: application/json",
            "User-Agent: " . $user_agent
        );

        if (isset($postdata['password'])) {
            $header[] = "X-Api-Password: " . $this->decrypt($postdata['password']);
        }
        if (isset($postdata['api_key'])) {
            $header[] = "X-Api-Key: " . $postdata['api_key'];
        }

        unset($postdata['password']);
        unset($postdata['api_key']);

        $options = [
            'http' => [
                'header' => $header,
                'ignore_errors' =>  true,
                'method'  => 'POST', //We are using the POST HTTP method.
                'content' => json_encode($postdata)
            ],
        ];

        $streamContext  = stream_context_create($options);
        $result = @file_get_contents($url, FALSE, $streamContext);
        if ($result === false) {
            return array('success' => false, 'error' => "server_error", "error_code" => 1002, 'msg' => 'Something went wrong. Please try again later.');
        }
        return json_decode($result, true);
    }

    public function encrypt($value)
    {
        if (!$value) {
            return false;
        }
        $text = $value;
        $crypttext = openssl_encrypt($text, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
        return trim($crypttext);
    }
    public function decrypt($value)
    {
        if (!$value) {
            return false;
        }
        $encryption = $value;
        $decrypttext = openssl_decrypt($encryption, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
        return trim($decrypttext);
    }
}
