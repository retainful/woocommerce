<?php

namespace Rnoc\Retainful;

class IpFiltering
{
    protected $black_list_ip = "";

    function __construct($ip_address)
    {
        $this->black_list_ip = $ip_address;
    }

    /**
     * Get the client IP address
     * @return mixed|string
     */
    function getClientIp()
    {
        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $client_ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $client_ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $client_ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $client_ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $client_ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $client_ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $client_ip = '';
        }
        return $this->formatUserIP($client_ip);
    }

    /**
     * Sometimes the IP address returne is not formatted quite well.
     * So it requires a basic formating.
     * @param $ip
     * @return String
     */
    function formatUserIP($ip)
    {
        //check for commas in the IP
        $ip = trim(current(preg_split('/,/', sanitize_text_field(wp_unslash($ip)))));
        return (string)$ip;
    }

    /**
     * Check the IP address is valid
     * @param $client_ip
     * @param $black_list_ip
     * @return bool
     */
    function isBlockedIp($client_ip, $black_list_ip)
    {
        $blocked = false;
        if (!empty($black_list_ip)) {
            foreach ($black_list_ip as $ip) {
                if ($client_ip == $ip) {
                    $blocked = true;
                    break;
                } elseif (strpos($ip, '*') !== false) {
                    $digits = explode(".", $ip);
                    $client_ip_digits = explode(".", $client_ip);
                    if (isset($digits[1]) && isset($client_ip_digits[0]) && $digits[1] == '*' && $digits[0] == $client_ip_digits[0]) {
                        $blocked = true;
                        break;
                    } elseif (isset($digits[2]) && isset($client_ip_digits[1]) && $digits[2] == '*' && $digits[0] == $client_ip_digits[0] && $digits[1] == $client_ip_digits[1]) {
                        $blocked = true;
                        break;
                    } elseif (isset($digits[3]) && isset($client_ip_digits[2]) && $digits[3] == '*' && $digits[0] == $client_ip_digits[0] && $digits[1] == $client_ip_digits[1] && $digits[2] == $client_ip_digits[2]) {
                        $blocked = true;
                        break;
                    }
                } elseif (strpos($ip, "-") !== false) {
                    list($start_ip, $end_ip) = explode("-", $ip);
                    $start_ip = preg_replace('/\s+/', '', $start_ip);
                    $end_ip = preg_replace('/\s+/', '', $end_ip);
                    $start_ip_long = ip2long($start_ip);
                    $end_ip_long = ip2long($end_ip);
                    $client_ip_long = ip2long($client_ip);
                    if ($client_ip_long >= $start_ip_long && $client_ip_long <= $end_ip_long) {
                        $blocked = true;
                        break;
                    }
                }
            }
        }
        return $blocked;
    }

    /**
     * Need to track the abandoned cart or not
     * @param $need_tracking
     * @param $ip_address
     * @return mixed
     */
    function trackAbandonedCart($need_tracking, $ip_address = NULL)
    {
        $ignored_ip_addresses = trim($this->black_list_ip);
        if (empty($ignored_ip_addresses)) {
            return true;
        }
        $black_list_ip = explode(',', $ignored_ip_addresses);
        if (empty($ip_address)) {
            $client_ip = $this->getClientIp();
        } else {
            $client_ip = $ip_address;
        }
        if ($this->isBlockedIp($client_ip, $black_list_ip)) {
            return false;
        }
        return true;
    }
}