<?php

/**
 * Cloudflare API v4 Client
 * Uses API Token (Bearer) authentication
 */
class cloudflare_api
{
    const API_BASE = 'https://api.cloudflare.com/client/v4';
    const TIMEOUT  = 10;

    private $api_token;

    public function __construct($api_token)
    {
        $this->api_token = $api_token;
    }

    /**
     * Purge all cached files for a zone.
     *
     * @param string $zone_id  Cloudflare Zone ID
     * @return stdClass
     */
    public function purge_everything($zone_id)
    {
        $url  = self::API_BASE . '/zones/' . rawurlencode($zone_id) . '/purge_cache';
        $body = json_encode(array('purge_everything' => true));
        return $this->http_post($url, $body);
    }

    private function http_post($url, $body)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->api_token,
            'Content-Type: application/json',
        ));
        $http_result = curl_exec($ch);
        $error       = curl_error($ch);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_result === false) {
            $result           = new stdClass();
            $result->success  = false;
            $result->errors   = array($error);
            return $result;
        }

        return json_decode($http_result);
    }
}
