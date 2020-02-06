<?php

namespace Rnoc\Retainful\Api\AbandonedCart\Storage;
class WooSession extends Base
{
    function __construct()
    {
        /**
         * Check is woocommerce session was initlized
         */
        if (!isset(WC()->session) || empty(WC()->session)) {
            if (class_exists('WC_Session_Handler')) {
                WC()->session = new \WC_Session_Handler();
                WC()->session->init();
            }
        }
        /**
         * make sure the session was created
         */
        if (method_exists(WC()->session, 'has_session')) {
            if (!WC()->session->has_session()) {
                if (method_exists(WC()->session, 'set_customer_session_cookie')) {
                    WC()->session->set_customer_session_cookie(true);
                }
            }
        }
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
        if (method_exists(WC()->session, 'set')) {
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
        if (method_exists(WC()->session, 'get')) {
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
        if (method_exists(WC()->session, '__unset')) {
            WC()->session->__unset($key);
        }
        return true;
    }
}