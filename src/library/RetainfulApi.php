<?php

namespace Rnoc\Retainful\library;

use Unirest\Request;

if (!defined('ABSPATH')) exit;

class RetainfulApi
{
    public $domain = "https://api.retainful.com/v1/";

    /**
     * Validate API Key
     * @param $api_key
     * @return bool|array
     */
    function validateApi($api_key)
    {
        $response = $this->request($this->domain . 'app/' . $api_key);
        if (isset($response->success) && $response->success) {
            $plan = isset($response->plan) ? strtolower($response->plan) : 'free';
            $status = isset($response->status) ? strtolower($response->status) : 'active';
            $period_end = isset($response->period_end) ? strtolower($response->period_end) : 'never';
            return array(
                'plan' => $plan,
                'status' => $status,
                'expired_on' => $period_end
            );
        } else {
            return false;
        }
    }

    /**
     * get operation for Remote URL
     * @param $url
     * @param $need_domain_in_suffix
     * @param array $fields
     * @return array|bool|mixed|object|string
     */
    function request($url, $fields = array(), $need_domain_in_suffix = false)
    {
        $response = '';
        try {
            $headers = array('Origin' => $this->siteURL());
            if (is_array($fields) && !empty($fields)) {
                $url = rtrim($url, '/');
                $url .= '?' . http_build_query($fields);
            }
            if ($need_domain_in_suffix)
                $url = $this->domain . $url;
            $response = Request::get($url, $headers);
            $response = $response->body;
        } catch (\Exception $e) {
            $e->getMessage();
        }
        return $response;
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