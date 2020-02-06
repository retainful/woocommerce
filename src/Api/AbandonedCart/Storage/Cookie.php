<?php

namespace Rnoc\Retainful\Api\AbandonedCart\Storage;
class Cookie extends Base
{
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
            setcookie($key, $value, 0, '/');
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
        if (empty($key))
            return false;
        if (isset($_COOKIE[$key])) {
            unset($_COOKIE[$key]);
        }
        return true;
    }
}