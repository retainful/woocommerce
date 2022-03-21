<?php

namespace Rnoc\Retainful\Api\AbandonedCart\Storage;
use Rnoc\Retainful\Admin\Settings;

class WooSession extends Base
{
    function __construct()
    {
        /**
         * Check is woocommerce session was initlized
         */
        /*if ((!isset(WC()->session) || is_null(WC()->session) || empty(WC()->session)) && !is_admin()) {
            if (class_exists('WC_Session_Handler')) {
                WC()->session = new \WC_Session_Handler();
                WC()->session->init();
            }
        }*/
        /**
         * make sure the session was created
         */
        if (isset(WC()->session) && !is_null(WC()->session) && is_object(WC()->session) && method_exists(WC()->session, 'has_session')) {
            if (!WC()->session->has_session() && !defined('DOING_CRON')) {
                if (method_exists(WC()->session, 'set_customer_session_cookie')) {
                    $settings = new Settings();
                    $rnoc_varnish_check = $settings->getRetainfulSettingValue('rnoc_varnish_check', 'no');
                    if ($rnoc_varnish_check === 'no') {
                        WC()->session->set_customer_session_cookie(true);
                    }

                }
            }
        }
    }

    /**
     * check the wc session has the value
     * @param $key
     * @return bool
     */
    function hasKey($key)
    {
        return true;
    }

    /**
     * Set the value for the PHP session
     * @param $key
     * @param $value
     * @return null
     */
    function setValue($key, $value)
    {
        if (empty($key)) {
            return NULL;
        }
        if (is_object(WC()->session) && method_exists(WC()->session, 'set')) {
            WC()->session->set($key, $value);
        }
        return true;
    }

    /**
     * get the value from the session
     * @param $key
     * @return mixed|null
     */
    function getValue($key)
    {
        if (empty($key)) {
            return NULL;
        }
        if (is_object(WC()->session) && method_exists(WC()->session, 'get')) {
            return WC()->session->get($key);
        }
        return NULL;
    }

    /**
     * remove the value from the session
     * @param $key
     * @return bool
     */
    function removeValue($key)
    {
        if (empty($key))
            return false;
        if (is_object(WC()->session) && method_exists(WC()->session, '__unset')) {
            WC()->session->__unset($key);
        }
        return true;
    }
}