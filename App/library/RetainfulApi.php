<?php

namespace Rnoc\App\library;

if (!defined('ABSPATH')) exit;

use Rnoc\App\Helpers\WC;

class RetainfulApi
{
    public static $app_url = "https://app.retainful.com/";
    public static $domain = "https://api.retainful.com/v1/";
    public static $abandoned_cart_api_url = "https://api.retainful.com/v1/woocommerce/";

    /**
     * Upgrade premium URL
     * @return string
     */
    public static function upgradePremiumUrl()
    {
        return self::$app_url . '?utm_source=retainful-free&utm_medium=plugin&utm_campaign=inline-addon&utm_content=premium-addon';
    }

    public static function getDomain()
    {
        return apply_filters('retainful_domain_url', self::$domain);
    }

    public static function getAbandonedCartApiUrl()
    {
        return apply_filters('retainful_abandoned_cart_api_url', self::$abandoned_cart_api_url);
    }

    /**
     * Validate API Key
     * @param $api_key
     * @param $body
     * @return bool|array
     */
    public static function validateApi($api_key, $body)
    {
        $url = self::getDomain() . 'app/' . $api_key;
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
        $response = self::request($url, array(), 'post', $body, $headers);
        //$response = $this->request($this->domain . 'app/' . $api_key);
        if (isset($response->success) && $response->success) {
            return self::getPlanDetails($response);
        } else {
            return isset($response->message) ? $response->message : NULL;
        }
    }

    /**
     * @param string $response
     * @return array
     */
    public static function getPlanDetails($response = \stdClass::class)
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
    public static function request($url, $fields = array(), $method = 'get', $body = '', $headers = array(), $blocking = true)
    {
        $response = '';
        try {
            if (is_array($fields) && !empty($fields)) {
                $url = rtrim($url, '/');
                $url .= '?' . http_build_query($fields);
            }
            if (empty($headers) || !is_array($headers)) {
                $headers = array('Origin' => self::siteURL());
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
            $body = $result->body;
            if ($use_wp_requests) {
                $body = wp_remote_retrieve_body($result);
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
    public static function siteURL()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['SERVER_NAME'] . '/';
        return $protocol . $domainName;
    }


    /**
     * abandoned_cart api url
     * @return string
     */
    public static function getAbandonedCartEndPoint()
    {
        $url = rtrim(self::getAbandonedCartApiUrl(), '/');
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
    public static function syncCartDetails($app_id, $body = '', $extra_headers = array())
    {
        $url = self::getAbandonedCartEndPoint();
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

        self::request($url, array(), 'post', $body, $headers, false);
        return true;
    }


}