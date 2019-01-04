<?php

namespace Rnoc\Retainful\library;
if (!defined('ABSPATH')) exit;

class RetainfulApi
{
    public $domain = "https://api.retainful.com/v1/";
    //public $domain = "https://c13061yiw2.execute-api.us-east-2.amazonaws.com/production/v1/";
    //public $domain = "http://retainful.ngrok.io/v1/";

    /**
     * Validate API Key
     * @param $api_key
     * @return bool
     */
    function validateApi($api_key)
    {
        $response = $this->remoteGet($this->domain . 'app/' . $api_key);
        if (isset($response->success) && $response->success)
            return true;
        else
            return false;
    }

    /**
     * get operation for Remote URL
     * @param $url
     * @param $need_domain_in_suffix
     * @param array $fields
     * @return array|bool|mixed|object|string
     */
    function remoteGet($url, $fields = array(), $need_domain_in_suffix = false)
    {
        $response_data = '';
        if (is_callable('curl_init')) {
            try {
                if (is_array($fields) && !empty($fields)) {
                    $url = rtrim($url, '/');
                    $url .= '?' . http_build_query($fields);
                }
                if ($need_domain_in_suffix)
                    $url = $this->domain . $url;
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HEADER, false);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Origin: ' . $this->siteURL()));
                curl_setopt($curl, CURLOPT_REFERER, $this->siteURL());
                curl_setopt($curl, CURLOPT_AUTOREFERER, true);
                $response_data = curl_exec($curl);
                curl_close($curl);
                if (is_string($response_data)) {
                    try {
                        $response_data = json_decode($response_data);
                    } catch (\Exception $e) {

                    }
                }
            } catch (\Exception $e) {
                //
            }
        }
        return $response_data;
    }

    /**
     * get site url
     * @return string
     */
    function siteURL()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['SERVER_NAME'] . '/';
        return $protocol . $domainName;
    }

    /**
     * Link to track email
     * @param $url
     * @param $fields
     * @return string
     */
    function emailTrack($url, $fields)
    {
        if (is_array($fields) && !empty($fields)) {
            $url = rtrim($url, '/');
            $url .= '?' . http_build_query($fields);
        }
        return $this->domain . $url;
    }
}