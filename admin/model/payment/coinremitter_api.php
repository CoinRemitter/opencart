<?php

namespace Opencart\Admin\Model\Extension\Coinremitter\Payment;

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
        $plugin_version = '0.0.5';
        $url = $api_url . $api_version . $endPoint;

        $opencart_version = '';
        if (VERSION) {
            $opencart_version = VERSION;
        }
        $user_agent = 'Opencart@' . $opencart_version . ' crplugin@'. $plugin_version;

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

    public function get_encryption_key()
    {
        $key = '';
        try {
            $key = (string)$this->config->get('config_encryption');
        } catch (\Throwable $e) {}

        if ($key === '') {
            $key = $this->skey;
        }

        return hash('sha256', $key, true);
    }

    public function encrypt($value)
    {
        if (!$value) {
            return false;
        }

        $key = $this->get_encryption_key();
        $iv = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt((string)$value, $this->ciphering, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return false;
        }

        return 'crv2:' . base64_encode($iv . $encrypted);
    }

    public function decrypt($value)
    {
        if (!$value) {
            return false;
        }

        // Handle URL-encoded crv2 prefix or percent-encoding
        if (strpos($value, '%') !== false) {
            $value = rawurldecode($value);
        }

        // v2: dynamic IV
        if (strpos($value, 'crv2:') === 0) {
            $raw = base64_decode(substr($value, 5), true);
            if ($raw === false || strlen($raw) <= 16) {
                return false;
            }

            $iv = substr($raw, 0, 16);
            $ciphertext = substr($raw, 16);
            $key = $this->get_encryption_key();

            $decrypted = openssl_decrypt($ciphertext, $this->ciphering, $key, OPENSSL_RAW_DATA, $iv);
            return ($decrypted === false) ? false : $decrypted;
        }

        // Legacy fallback (only for non-crv2 values)
        $legacy = openssl_decrypt($value, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
        return ($legacy === false) ? false : $legacy;
    }
}
