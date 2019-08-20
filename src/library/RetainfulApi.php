<?php

namespace Rnoc\Retainful\library;

use Unirest\Request;

if (!defined('ABSPATH')) exit;

class RetainfulApi
{
    public $domain = "https://api.retainful.com/v1/";
    public $app_url = "https://app.retainful.com/";

    /**
     * Upgrade premium URL
     * @return string
     */
    function upgradePremiumUrl()
    {
        return $this->app_url . '?utm_source=retainful-free&utm_medium=plugin&utm_campaign=inline-addon&utm_content=premium-addon';
    }

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
            return $this->getPlanDetails($plan, $status, $period_end);
        } else {
            return false;
        }
    }

    /**
     *  plan details
     * @param null $plan
     * @param null $status
     * @param null $period_end
     * @return array
     */
    function getPlanDetails($plan = NULL, $status = NULL, $period_end = NULL)
    {
        return array(
            'plan' => (empty($plan)) ? 'free' : $plan,
            'status' => (empty($status)) ? 'active' : $status,
            'expired_on' => (empty($period_end)) ? 'never' : $period_end
        );
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