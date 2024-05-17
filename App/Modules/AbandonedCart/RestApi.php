<?php

namespace Rnoc\App\Modules\AbandonedCart;

use DateTime;
use Exception;
use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Controller\Admin\BaseController;
use Rnoc\App\Storage\Cookie;
use Rnoc\App\Storage\PhpSession;
use Rnoc\App\Storage\WooSession;
use Rnoc\App\library\RetainfulApi;
use Rnoc\App\Helpers\WC;
use Rnoc\App\Helpers\Settings as SettingHelper;

class RestApi
{
    public static $storage;
    protected static $cart_token_key = "rnoc_user_cart_token", $cart_token_key_for_db = "_rnoc_user_cart_token";
    protected static $user_ip_key = "rnoc_user_ip_address", $user_ip_key_for_db = "_rnoc_user_ip_address";
    protected static $cart_tracking_started_key = "rnoc_cart_created_at", $cart_tracking_started_key_for_db = "_rnoc_cart_tracking_started_at";
    protected static $previous_cart_hash_key = "rnoc_previous_cart_hash";

    /** The cipher method name to use to encrypt the cart data */
    const CIPHER_METHOD = 'AES256';
    /** The HMAC hash algorithm to use to sign the encrypted cart data */
    const HMAC_ALGORITHM = 'sha256';

    function __construct()
    {
        $this->initStorage();
    }

    /**
     * init the storage classes
     */
    public static function initStorage()
    {
        $storage_handler = Settings::getStorageHandler();
        switch ($storage_handler) {
            case "php";
                self::$storage = new PhpSession();
                break;
            case "cookie";
                self::$storage = new Cookie();
                break;
            default:
            case "woocommerce":
                self::$storage = new WooSession();
                break;
        }
    }

    /**
     * Get the current user's cart token
     * @return array|string|null
     */
    public static function getCartToken()
    {
        $cart_token = self::retrieveCartToken();
        if (empty($cart_token)) {
            $cart_token = self::generateCartToken();
            self::setCartToken($cart_token);
        }
        return apply_filters('rnoc_get_cart_token', $cart_token, self::class);
    }

    /**
     * Set the cart token for the session
     * @param $cart_token
     * @param $user_id
     */
    public static function setCartToken($cart_token, $user_id = null)
    {
        $cart_token = apply_filters('rnoc_before_set_cart_token', $cart_token, $user_id, self::class);
        $old_cart_token = self::$storage->getValue(self::$cart_token_key);
        if (empty($old_cart_token)) {
            Settings::logMessage($cart_token, 'setting cart token');
            $current_time = current_time('timestamp', true);
            self::$storage->setValue(self::$cart_token_key, $cart_token);
            self::$storage->setValue(self::$cart_tracking_started_key, $current_time);
            if (!empty($user_id) || $user_id = get_current_user_id()) {
                update_user_meta($user_id, self::$cart_token_key_for_db, $cart_token);
                self::setCartCreatedDate($user_id, $current_time);
            }
        }
    }

    /**
     * @param $price
     * @return string
     */
    public static function formatDecimalPrice($price)
    {
        $decimals = WC::priceDecimals();
        $price = floatval($price);
        return round($price, $decimals);
    }

    /**
     * @param $price
     * @return string
     */
    public static function formatDecimalPriceRemoveTrailingZeros($price)
    {
        $price = (float)$price;
        $decimals = WC::priceDecimals();
        $rounded_price = round($price, $decimals);
        return number_format($rounded_price, $decimals, '.', '');
    }


    /**
     * Line item total
     * @param $item_details
     * @return int
     */
    public static function getLineItemTotal($item_details)
    {
        $line_total = !empty($item_details['line_total']) ? $item_details['line_total'] : 0;
        $line_total_tax = 0;
        if (!WC::isPriceExcludingTax()) {
            $line_total_tax = !empty($item_details['line_tax']) ? $item_details['line_tax'] : 0;
        }
        $total = $line_total + $line_total_tax;
        return apply_filters('retainful_get_line_item_total', $total, $line_total, $line_total_tax, $item_details, self::class);
    }

    /**
     * Set the session shipping details
     * @param $shipping_address
     */
    function setSessionShippingDetails($shipping_address)
    {
        if (!empty($shipping_address)) {
            foreach ($shipping_address as $key => $value) {
                $method = 'set_' . $key;
                if (is_callable(array(WC()->customer, $method))) {
                    WC()->customer->$method($value);
                }
            }
        }
    }

    /**
     * Remove the session shipping details
     */
    function removeSessionShippingDetails()
    {
        self::$storage->removeValue('rnoc_shipping_address');
    }

    /**
     * generate cart hash
     * @return string
     */
    public static function generateCartHash()
    {
        $cart = WC::getCart();
        $cart_session = array();
        if (!empty($cart)) {
            foreach ($cart as $key => $values) {
                $cart_session[$key] = $values;
                unset($cart_session[$key]['data']); // Unset product object.
            }
        }
        return $cart_session ? md5(wp_json_encode($cart_session) . WC::getCartTotalForEdit()) : '';
    }


    /**
     * retrieve cart token from session
     * @param $user_id
     * @return array|mixed|string|null
     */
    public static function retrieveCartToken($user_id = null)
    {
        $user_id = ($user_id == NULL) ? WC::getCurrentUserId() : 0;
        $token = !empty($user_id) ? get_user_meta($user_id, self::$cart_token_key_for_db, true) : self::$storage->getValue(self::$cart_token_key);
        return apply_filters('rnoc_retrieve_cart_token', $token, $user_id, self::class);
    }

    /**
     * Recovery link to recover the user cart
     *
     * @param $cart_token
     * @return string
     */
    public static function getRecoveryLink($cart_token)
    {
        $data = array('cart_token' => $cart_token);
        // encode
        $data = base64_encode(wp_json_encode($data));
        // add hash for easier verification that the checkout URL hasn't been tampered with
        $hash = self::hashTheData($data);
        $url = self::getRetainfulApiUrl();
        // returns URL like:
        // pretty permalinks enabled - https://example.com/wc-api/retainful?token=abc123&hash=xyz
        // pretty permalinks disabled - https://example.com?wc-api=retainful&token=abc123&hash=xyz
        return esc_url_raw(add_query_arg(array('token' => rawurlencode($data), 'hash' => $hash), $url));
    }

    /**
     * Return the WC API URL for handling Retainful recovery links by accounting
     * for whether pretty permalinks are enabled or not.
     *
     * @return string
     * @since 1.1.0
     */
    private static function getRetainfulApiUrl()
    {
        $scheme = wc_site_is_https() ? 'https' : 'http';
        return get_option('permalink_structure')
            ? get_home_url(null, 'wc-api/retainful', $scheme)
            : add_query_arg('wc-api', 'retainful', get_home_url(null, null, $scheme));
    }

    /**
     * Hash the data
     * @param $data
     * @return false|string
     */
    public static function hashTheData($data)
    {
        $secret = Settings::getSecretKey();
        return hash_hmac(self::HMAC_ALGORITHM, $data, $secret);
    }

    /**
     * Get the client IP address
     * @return mixed|string
     */
    public static function getClientIp()
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
        return $client_ip;
    }

    /**
     * retrieve User IP address
     * @param null $user_id
     * @return array|mixed|string|null
     */
    public static function retrieveUserIp($user_id = NULL)
    {
        $ip = !empty($user_id) ? get_user_meta($user_id, self::$user_ip_key_for_db) : self::getClientIp();
        return self::formatUserIP($ip);
    }

    /**
     * Sometimes the IP address returne is not formatted quite well.
     * So it requires a basic formating.
     * @param $ip
     * @return String
     */
    public static function formatUserIP($ip)
    {
        //check for commas in the IP
        $ip = trim(current(preg_split('/,/', sanitize_text_field(wp_unslash($ip)))));
        return (string)$ip;
    }

    /**
     * generate the random cart token
     * @return string
     */
    public static function generateCartToken()
    {
        try {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
            $token = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        } catch (Exception $e) {
            // fall back to mt_rand if random_bytes is unavailable
            $token = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                // 16 bits for "time_mid"
                mt_rand(0, 0xffff),
                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand(0, 0x0fff) | 0x4000,
                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand(0, 0x3fff) | 0x8000,
                // 48 bits for "node"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
        return md5($token . time());
    }

    /**
     * Checks whether an order is recovered.
     * @param int|string $order_id order ID
     * @return bool
     */
    public function isOrderRecovered($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return false;
        }
        return (bool)WC::getOrderMeta($order, $this->order_recovered_key_for_db);
    }


    /**
     * Format the date to ISO8601
     * @param $timestamp
     * @return string|null
     */
    public static function formatToIso8601($timestamp)
    {
        if (empty($timestamp)) {
            $timestamp = current_time('timestamp', true);
        }
        if (is_object($timestamp) && $timestamp instanceof \WC_DateTime) {
            $timestamp = $timestamp->getTimestamp();
        }

        try {
            $date = date('Y-m-d H:i:s', $timestamp);
            $date_time = new DateTime($date);
            return $date_time->format(DateTime::ATOM);
        } catch (Exception $e) {
            return NULL;
        }
    }


    /**
     * Encrypt the cart
     * @param $data
     * @param $secret
     * @return string
     */
    public static function encryptData($data, $secret = NULL)
    {
        if (extension_loaded('openssl')) {
            if (is_array($data) || is_object($data)) {
                $data = wp_json_encode($data);
            }
            try {
                if (empty($secret)) {
                    $secret = Settings::getSecretKey();
                }
                $iv_len = openssl_cipher_iv_length(self::CIPHER_METHOD);
                $iv = openssl_random_pseudo_bytes($iv_len);
                $cipher_text_raw = openssl_encrypt($data, self::CIPHER_METHOD, $secret, OPENSSL_RAW_DATA, $iv);
                $hmac = hash_hmac(self::HMAC_ALGORITHM, $cipher_text_raw, $secret, true);
                return base64_encode(bin2hex($iv) . ':retainful:' . bin2hex($hmac) . ':retainful:' . bin2hex($cipher_text_raw));
            } catch (Exception $e) {
                return NULL;
            }
        }
        return NULL;
    }


    /**
     * Get the date of cart tracing started
     * @param $user_id
     * @return array|mixed|string|null
     */
    public static function userCartCreatedAt($user_id = NULL)
    {
        $user_id = WC::getCurrentUserId();
        return empty($user_id) ? self::$storage->getValue(self::$cart_tracking_started_key) : WC::getUserMeta($user_id, self::$cart_tracking_started_key_for_db, true);
    }

    /**
     * When user start adding to cart
     * @param null $user_id
     * @param null $time
     * @return array|mixed|string|null
     */
    public static function setCartCreatedDate($user_id = NULL, $time = NULL)
    {
        if (empty($time)) {
            $time = current_time('timestamp', true);
        }
        if (!empty($user_id) || $user_id = get_current_user_id()) {
            update_user_meta($user_id, self::$cart_tracking_started_key_for_db, $time);
        }
        return $time;
    }

    /**
     * Synchronize cart with SaaS
     * @param $cart_details
     * @param $extra_headers
     * @return array|bool|mixed|object|string
     */
    public static function syncCart($cart_details, $extra_headers)
    {
        $app_id = Settings::getApiKey();
        $response = false;
        if (!empty($cart_details)) {
            Settings::logMessage('PHP', 'synced by');
            $response = RetainfulApi::syncCartDetails($app_id, $cart_details, $extra_headers);
        }
        return $response;
    }

    /**
     * Check is buyer accepts marketing
     * @return bool
     */
    public static function isBuyerAcceptsMarketing()
    {
        $enable_gdpr_compliance = SettingHelper::get(RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance', 'retainful_settings', 0);
        if ($enable_gdpr_compliance) {
            return in_array(WC::getSession('is_buyer_accepting_marketing'), array(1, 'true'));
        }
        return true;
    }


    /**
     * get the client details
     * @param null $order
     * @return mixed|void
     */
    public static function getClientDetails($order = null)
    {
        $client_details = array(
            'accept_language' => self::getUserAcceptLanguage($order)
        );
        return apply_filters('rnoc_get_client_details', $client_details, $order);
    }


    /**
     * get the user accept language
     * @param null $order
     * @return mixed|string|null
     */
    public static function getUserAcceptLanguage($order = null)
    {
        if (!empty($order)) {
            return WC::getOrderMeta($order, '_rnoc_get_http_accept_language');
        }
        $lang = !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? trim($_SERVER['HTTP_ACCEPT_LANGUAGE']) : '';
        return !empty($lang) ? substr($lang, 0, 2) : '';

    }

}