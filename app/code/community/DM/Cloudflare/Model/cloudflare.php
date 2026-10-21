<?php

/**
 * CloudFlare API
 *
 *
 * @author AzzA <azza@broadcasthe.net>
 * @copyright omgwtfhax inc. 2013
 * @version 1.1
 */
class cloudflare_api
{
    //The URL of the API
    private static $URL = 'https://www.cloudflare.com/api_json.html';
      

    //Timeout for the API requests in seconds
    const TIMEOUT = 5;

    //Interval values for Stats
    const INTERVAL_365_DAYS = 10;
    const INTERVAL_30_DAYS = 20;
    const INTERVAL_7_DAYS = 30;
    const INTERVAL_DAY = 40;
    const INTERVAL_24_HOURS = 100;
    const INTERVAL_12_HOURS = 110;
    const INTERVAL_6_HOURS = 120;

    //Stores the api key
    private $token_key;

    //Stores the email login
    private $email;

    /**
     * Make a new instance of the API client
     */
    public function __construct()
    {
        $parameters = func_get_args();
        
                $this->email     = $parameters[0];
                $this->token_key = $parameters[1];
     
    }

    public function fpurge_ts($domain)
    {
        $data = array(
            'a' => 'fpurge_ts',
            'z' => $domain,
            'v' => 1
        );
        return $this->http_post($data);
    }

    private function http_post($data)
    {
        
                $data['u']   = $this->email;
                $data['tkn'] = $this->token_key;
               
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_URL, self::$URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $http_result = curl_exec($ch);
        $error       = curl_error($ch);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code != 200) {
            return array(
                'error' => $error
            );
        } else {
            return json_decode($http_result);
        }
    }
}
