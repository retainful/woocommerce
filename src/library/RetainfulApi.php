<?php

namespace Rnoc\Retainful\library;
use Rnoc\Retainful\WcFunctions;

if (!defined('ABSPATH')) exit;

class RetainfulApi
{
    public $app_url = "https://app.retainful.com/";
    public $domain = "https://api.retainful.com/v1/";
    public $abandoned_cart_api_url = "https://api.retainful.com/v1/woocommerce/";

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
     * @param $body
     * @return bool|array
     */
    function validateApi($api_key, $body)
    {
        $url = $this->domain . 'app/' . $api_key;
        $body = array(
            'shop' => $body
        );
        if (is_array($body) || is_object($body)) {
            $body = json_encode($body);
        }
        $headers = array(
            'app_id' => $api_key,
            'Content-Type' => 'application/json'
        );
        $response = $this->request($url, array(), 'post', $body, $headers);
        //$response = $this->request($this->domain . 'app/' . $api_key);
        if (isset($response->success) && $response->success) {
            return $this->getPlanDetails($response);
        } else {
            return isset($response->message) ? $response->message : NULL;
        }
    }

    /**
     * @param string $response
     * @return array
     */
    function getPlanDetails($response = \stdClass::class)
    {
        $plan = isset($response->plan) ? strtolower($response->plan) : 'free';
        $status = isset($response->status) ? strtolower($response->status) : 'active';
        $period_end = isset($response->period_end) ? strtolower($response->period_end) : 'never';
        $message = isset($response->message) ? strtolower($response->message) : 'App connected successfully';
        return array(
            'plan' => (empty($plan)) ? 'free' : $plan,
            'status' => (empty($status)) ? 'active' : $status,
            'expired_on' => (empty($period_end)) ? 'never' : $period_end,
            'message' => $message,
        );
    }

    /**
     * get operation for Remote URL
     * @param $url
     * @param $body
     * @param $method
     * @param array $fields
     * @param array $headers
     * @param bool $blocking
     * @return array|bool|mixed|object|string
     */
    function request($url, $fields = array(), $method = 'get', $body = '', $headers = array(), $blocking = true)
    {
        $response = '';
        try {
            if (is_array($fields) && !empty($fields)) {
                $url = rtrim($url, '/');
                $url .= '?' . http_build_query($fields);
            }
            if (empty($headers) || !is_array($headers)) {
                $headers = array('Origin' => $this->siteURL());
            }
            $use_wp_requests = true;
            if (class_exists('Requests')) {
                $use_wp_requests = false;
                \Requests::register_autoloader();
            }
            switch ($method) {
                case 'post':
                    if ($use_wp_requests) {
                        $args = array(
                            'body' => $body,
                            'timeout' => '30',
                            'httpversion' => '1.0',
                            'blocking' => $blocking,
                            'headers' => $headers
                        );
                        $result = wp_remote_post($url, $args);
                    } else {
                        $result = \Requests::post($url, $headers, $body);
                    }
                    break;
                default:
                case 'get':
                    if ($use_wp_requests) {
                        $args = array(
                            'timeout' => '30',
                            'httpversion' => '1.0',
                            'blocking' => $blocking,
                            'headers' => $headers
                        );
                        $result = wp_remote_get($url, $args);
                    } else {
                        $result = \Requests::get($url, $headers);
                    }
                    break;
            }
            if ($use_wp_requests) {
                $body = wp_remote_retrieve_body($result);
            } else {
                $body = $result->body;
            }
            if (is_string($body)) {
                $response = json_decode($body);
            } elseif (is_object($body)) {
                $response = $body;
            } elseif (is_array($body)) {
                $response = (object)$body;
            } else {
                $response = new \stdClass();
            }
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

    /**
     * abandoned_cart api url
     * @return string
     */
    function getAbandonedCartEndPoint()
    {
        $url = rtrim($this->abandoned_cart_api_url, '/');
        $url .= '/webhooks/checkout';
        return $url;
    }

    /**
     * Sync the cart details to server
     * @param $app_id
     * @param string $body
     * @param array $extra_headers
     * @return array|bool|mixed|object|string
     */
    function syncCartDetails($app_id, $body = '', $extra_headers = array())
    {
        $url = $this->getAbandonedCartEndPoint();
        $body = array(
            'data' => $body
        );
        if (is_array($body) || is_object($body)) {
            $body = json_encode($body);
        }
        $headers = array(
            'app_id' => $app_id,
            'Content-Type' => 'application/json'
        );
        //Process any extra headers need to post
        if (is_array($extra_headers) && !empty($extra_headers)) {
            $headers = array_merge($headers, $extra_headers);
        }
        $this->request($url, array(), 'post', $body, $headers, false);
        return true;
    }

    /**
     * Synchronize call, without wait for response
     * You can not get any response. This will only help for
     * @param $url
     * @param $body
     * @param $headers
     * @return bool
     */
    function broadCastEvent($url, $body, $headers)
    {
        $parts = parse_url($url);
        $port = isset($parts['port']) ? $parts['port'] : 80;
        $fp = fsockopen($parts['host'], $port, $errno, $errstr, 30);
        $out = "POST " . $parts['path'] . " HTTP/1.1\r\n";
        $out .= "Host: " . $parts['host'] . "\r\n";
        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $out .= "$key: $value\r\n";
            }
        }
        $out .= "Content-Length: " . strlen($body) . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        if (isset($body)) $out .= $body;
        fwrite($fp, $out);
        fclose($fp);
        return true;
    }

    /**
     * Sync the cart details to server
     * @param $app_id
     * @param string $cart_token
     * @return array|bool|mixed|object|string
     */
    function retrieveCartDetails($app_id, $cart_token)
    {
        $url = rtrim($this->abandoned_cart_api_url, '/');
        $url .= '/abandoned_checkouts/' . $cart_token;
        $headers = array(
            'app_id' => $app_id
        );
        $response = $this->request($url, array(), 'get', '', $headers);
        if (isset($response->success) && $response->success) {
            $referrer_automation_id = wc_clean($_REQUEST['referrer_automation_id']);
            if(!empty($referrer_automation_id)){
                $woocommerce = new WcFunctions();
                $woocommerce->setSession($cart_token.'_referrer_automation_id', $referrer_automation_id);
                $response->data->referrer_automation_id = $referrer_automation_id;
            }
            return isset($response->data) ? $response->data : NULL;
        }
        return NULL;
    }
}