<?php

namespace Rnoc\Retainful\Api\AbandonedCart\Storage;
class Cookie extends Base
{
    /**
     * check the cookie has the value
     * @param $key
     * @return bool
     */
    function hasKey($key)
    {
        return (isset($_COOKIE[$key]));
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
        if (function_exists('wc_setcookie')) {
            wc_setcookie($key, $value);
        } else {
            setcookie($key, $value, array(
                'expires' => 0,
                'path' => COOKIEPATH ? COOKIEPATH : '/',
                'domain' => COOKIE_DOMAIN,
                'samesite' => 'None',
                'secure' => false,
                'httponly' => false,
            ));
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
        if (isset($_COOKIE[$key])) {
            return $_COOKIE[$key];
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
        if (empty($key)) {
            return false;
        }
        if (isset($_COOKIE[$key])) {
            unset($_COOKIE[$key]);
            if (function_exists('wc_setcookie')) {
                wc_setcookie($key, null, -1);
            } else {
                setcookie($key, null, array(
                    'expires' => -1,
                    'path' => COOKIEPATH ? COOKIEPATH : '/',
                    'domain' => COOKIE_DOMAIN,
                    'samesite' => 'None',
                    'secure' => false,
                    'httponly' => false,
                ));
            }
            return true;
        } else {
            return false;
        }
    }
}