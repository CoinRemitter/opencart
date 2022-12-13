<?php
namespace Opencart\Catalog\Model\Extension\Coinremitter\Payment;
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
    
    public function apiCall($endPoint='',$postdata = array(), $is_get = FALSE){
        
        $api_url = 'https://coinremitter.com/api/';
        $api_version = 'v3';

        $url = $api_url.$api_version.'/'.$endPoint;     

        $opencart_version = '';
        if (VERSION) {
            $opencart_version = VERSION;
        }
        $user_agent = 'api@'.$api_version.' opencart@'.$opencart_version.' checkout-module';

        $method = 'GET';
        $postStr = '';
        if($is_get == FALSE){
            $method = 'POST';
            if(isset($postdata['password'])){
                $postdata['password'] = $this->decrypt($postdata['password']);
            }
            $postStr = http_build_query($postdata);
        }
        
        $options = array(
            'http' =>
                array(
                    'method'  => $method, //We are using the POST HTTP method.
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n"."User-agent:".$user_agent ,
                    'content' => $postStr //Our URL-encoded query string.
                ),
               
        );

        $streamContext  = stream_context_create($options);
        //Use PHP's file_get_contents function to carry out the request.
        //We pass the $streamContext variable in as a third parameter.
        $result = file_get_contents($url, false, $streamContext);
        //If $result is FALSE, then the request has failed.
        if($result === false){
            //If the request failed, throw an Exception containing
            //the error.
            /*$error = error_get_last();
            throw new Exception('POST request failed: ' . $error['message']);*/
            $res = array('flag' => 0, 'msg' => 'Something went wrong. Please try again later.');
            $result = json_encode($res);
        }
        //If everything went OK, return the response.
        if(!is_array($result)){
            $result = json_decode($result,true);
        }
        
        return $result;
        
    }    

    public function commonApiCall($params){
        $res = '';

        $endPoint = $params['coin'].'/'.$params['url'];
        unset($params['url']);

        $api_response = $this->apiCall($endPoint,$params);

        if($api_response){
            $res = $api_response;
        }
        return $res;
    }



    public function encrypt($value){
        if(!$value){
           return false;
        }
        $text = $value;
        $crypttext = openssl_encrypt($text, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
        return trim($crypttext);
    }
    public function decrypt($value) {
        if (!$value) {
            return false;
        }
        $encryption = $value;
        $decrypttext=openssl_decrypt($encryption, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
        return trim($decrypttext);
    }
}