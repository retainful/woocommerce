<?php

namespace Rnoc\Retainful\Api\AbandonedCart\Storage;
class PhpSession extends Base
{
    function __construct()
    {
        if (!session_id() && !headers_sent() && !is_admin()) {
            session_start();
        }
    }

    /**
     * check the cookie has the value
     * @param $key
     * @return bool
     */
    function hasKey($key)
    {
        return (isset($_SESSION[$key]));
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
        $_SESSION[$key] = $value;
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
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
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
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
        return true;
    }
}