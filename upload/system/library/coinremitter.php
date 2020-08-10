<?php
class Coinremitter
{
    private static $instance;
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
     * @param  string  $url     Url
     * @param  array  $params  Key-value pair
     * @param  bool $is_get  TRUE/FALSE 
     */
    
    public function apiCall($endPoint='',$postdata = array(), $is_get = FALSE){
        
        $api_url = 'https://coinremitter.com/api/';
        $api_version = 'v1';

        $url = $api_url.$api_version.'/'.$endPoint;     

        $opencart_version = '';
        if (VERSION) {
            $opencart_version = VERSION;
        }
        $user_agent = 'api@'.$api_version.' opencart@'.$opencart_version.' checkout-module';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if($is_get == FALSE){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
        }
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($result,TRUE);

        return $res;
        
    }
}