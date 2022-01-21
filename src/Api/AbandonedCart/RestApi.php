<?php

namespace Rnoc\Retainful\Api\AbandonedCart;

use DateTime;
use Exception;
use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\Storage\Cookie;
use Rnoc\Retainful\Api\AbandonedCart\Storage\PhpSession;
use Rnoc\Retainful\Api\AbandonedCart\Storage\WooSession;
use Rnoc\Retainful\library\RetainfulApi;
use Rnoc\Retainful\WcFunctions;

class RestApi
{
    public static $cart, $checkout, $settings, $api, $woocommerce, $storage;
    protected $cart_token_key = "rnoc_user_cart_token", $cart_token_key_for_db = "_rnoc_user_cart_token";
    protected $user_ip_key = "rnoc_user_ip_address", $user_ip_key_for_db = "_rnoc_user_ip_address";
    protected $order_placed_date_key_for_db = "_rnoc_order_placed_at", $order_cancelled_date_key_for_db = "_rnoc_order_cancelled_at";
    protected $pending_recovery_key = "rnoc_is_pending_recovery", $pending_recovery_key_for_db = "_rnoc_is_pending_recovery";
    protected $cart_tracking_started_key = "rnoc_cart_created_at", $cart_tracking_started_key_for_db = "_rnoc_cart_tracking_started_at";
    protected $order_note_key = "rnoc_order_note", $order_note_key_for_db = "_rnoc_order_note";
    protected $order_recovered_key = "rnoc_order_recovered", $order_recovered_key_for_db = "_rnoc_order_recovered";
    protected $accepts_marketing_key_for_db = "_rnoc_is_buyer_accepts_marketing";
    protected $previous_cart_hash_key = "rnoc_previous_cart_hash";
    protected $cart_hash_key_for_db = "_rnoc_cart_hash";
    /** The cipher method name to use to encrypt the cart data */
    const CIPHER_METHOD = 'AES256';
    /** The HMAC hash algorithm to use to sign the encrypted cart data */
    const HMAC_ALGORITHM = 'sha256';

    function __construct()
    {
        self::$settings = !empty(self::$settings) ? self::$settings : new Settings();
        self::$api = !empty(self::$api) ? self::$api : new RetainfulApi();
        self::$woocommerce = !empty(self::$woocommerce) ? self::$woocommerce : new WcFunctions();
        $this->initStorage();
    }

    /**
     * init the storage classes
     */
    function initStorage()
    {
        $storage_handler = self::$settings->getStorageHandler();
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
    function getCartToken()
    {
        $cart_token = $this->retrieveCartToken();
        if (empty($cart_token)) {
            $cart_token = $this->generateCartToken();
            $this->setCartToken($cart_token);
        }
        return apply_filters('rnoc_get_cart_token', $cart_token, $this);
    }

    /**
     * Set the cart token for the session
     * @param $cart_token
     * @param $user_id
     */
    function setCartToken($cart_token, $user_id = null)
    {
        $cart_token = apply_filters('rnoc_before_set_cart_token', $cart_token, $user_id, $this);
        $old_cart_token = self::$storage->getValue($this->cart_token_key);
        if (empty($old_cart_token)) {
            self::$settings->logMessage($cart_token, 'setting cart token');
            $current_time = current_time('timestamp', true);
            self::$storage->setValue($this->cart_token_key, $cart_token);
            self::$storage->setValue($this->cart_tracking_started_key, $current_time);
            if (!empty($user_id) || $user_id = get_current_user_id()) {
                update_user_meta($user_id, $this->cart_token_key_for_db, $cart_token);
                $this->setCartCreatedDate($user_id, $current_time);
            }
        }
    }

    /**
     * @param $price
     * @return string
     */
    function formatDecimalPrice($price)
    {
        $decimals = self::$woocommerce->priceDecimals();
        $price = floatval($price);
        return round($price, $decimals);
    }

    /**
     * @param $price
     * @return string
     */
    function formatDecimalPriceRemoveTrailingZeros($price)
    {
        $decimals = self::$woocommerce->priceDecimals();
        $rounded_price = round($price, $decimals);
        return number_format($rounded_price, $decimals, '.', '');
    }


    /**
     * Line item total
     * @param $item_details
     * @return int
     */
    function getLineItemTotal($item_details)
    {
        $line_total = (isset($item_details['line_total']) && !empty($item_details['line_total'])) ? $item_details['line_total'] : 0;
        if (!self::$woocommerce->isPriceExcludingTax()) {
            $line_total_tax = (isset($item_details['line_tax']) && !empty($item_details['line_tax'])) ? $item_details['line_tax'] : 0;
        } else {
            $line_total_tax = 0;
        }
        $total = $line_total + $line_total_tax;
        return apply_filters('retainful_get_line_item_total', $total, $line_total, $line_total_tax, $item_details, $this);
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
    function generateCartHash()
    {
        $cart = self::$woocommerce->getCart();
        $cart_session = array();
        if (!empty($cart)) {
            foreach ($cart as $key => $values) {
                $cart_session[$key] = $values;
                unset($cart_session[$key]['data']); // Unset product object.
            }
        }
        return $cart_session ? md5(wp_json_encode($cart_session) . self::$woocommerce->getCartTotalForEdit()) : '';
    }

    /**
     * Set the customer billing details
     * @param $billing_address
     */
    function setCustomerBillingDetails($billing_address)
    {
        if (!empty($billing_address)) {
            foreach ($billing_address as $key => $value) {
                $method = 'set_' . $key;
                if (is_callable(array(WC()->customer, $method))) {
                    WC()->customer->$method($value);
                }
            }
        }
    }

    /**
     * Customer address mapping fields
     * @return array
     */
    function getAddressMapFields()
    {
        $fields = array(
            'first_name',
            'last_name',
            'state',
            'phone',
            'postcode',
            'city',
            'country',
            'address_1',
            'address_2',
            'company'
        );
        return apply_filters('rnoc_get_checkout_mapping_fields', $fields);
    }

    /**
     * Get the customer billing details
     * @param $type
     * @return array
     */
    function getCustomerCheckoutDetails($type = "billing")
    {
        $fields = $this->getAddressMapFields();
        $checkout_field_values = array();
        if (!empty($fields)) {
            foreach ($fields as $key) {
                $method = 'get_' . $type . '_' . $key;
                if (is_callable(array(WC()->customer, $method))) {
                    $checkout_field_values[$type . '_' . $key] = WC()->customer->$method();
                }
            }
        }
        return $checkout_field_values;
    }

    /**
     * Remove the session billing details
     */
    function removeSessionBillingDetails()
    {
        self::$storage->removeValue('rnoc_billing_address');
    }

    /**
     * Check the cart is in pending recovery
     * @param null $user_id
     * @return array|mixed|string|null
     */
    function isPendingRecovery($user_id = NULL)
    {
        if ($user_id || ($user_id = get_current_user_id())) {
            return (bool) get_user_meta($user_id, $this->pending_recovery_key_for_db, true);
        } else {
            return (bool) self::$storage->getValue($this->pending_recovery_key);
        }
    }

    /**
     * retrieve cart token from session
     * @param $user_id
     * @return array|mixed|string|null
     */
    function retrieveCartToken($user_id = null)
    {
        if ($user_id == null) {
            $user_id = get_current_user_id();
        }
        if (!empty($user_id)) {
            $token = get_user_meta($user_id, $this->cart_token_key_for_db, true);
        } else {
            $token = self::$storage->getValue($this->cart_token_key);
        }
        return apply_filters('rnoc_retrieve_cart_token', $token, $user_id, $this);
    }

    /**
     * Recovery link to recover the user cart
     *
     * @param $cart_token
     * @return string
     */
    function getRecoveryLink($cart_token)
    {
        $data = array('cart_token' => $cart_token);
        // encode
        $data = base64_encode(wp_json_encode($data));
        // add hash for easier verification that the checkout URL hasn't been tampered with
        $hash = $this->hashTheData($data);
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
    function hashTheData($data)
    {
        $secret = self::$settings->getSecretKey();
        return hash_hmac(self::HMAC_ALGORITHM, $data, $secret);
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
        return $client_ip;
    }

    /**
     * retrieve User IP address
     * @param null $user_id
     * @return array|mixed|string|null
     */
    function retrieveUserIp($user_id = NULL)
    {
        if ($user_id) {
            $ip = get_user_meta($user_id, $this->user_ip_key_for_db);
        } else {
            $ip = $this->getClientIp();
        }
        return $this->formatUserIP($ip);
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
     * generate the random cart token
     * @return string
     */
    function generateCartToken()
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
        return md5($token);
    }

    /**
     * Check that the order status is valid to clear temp data
     * @param $order_status
     * @return bool
     */
    function isValidOrderStatusToResetCartToken($order_status)
    {
        $to_clear_order_status = apply_filters('rnoc_to_clear_temp_data_order_status', array('failed', 'pending'));
        return in_array($order_status, $to_clear_order_status);
    }

    /**
     * Check the order has valid order statuses
     * @param $order_status
     * @return bool
     */
    function isOrderHasValidOrderStatus($order_status)
    {
        $invalid_order_status = apply_filters('rnoc_abandoned_cart_invalid_order_statuses', array('pending', 'failed', 'checkout-draft'));
        $consider_on_hold_order_as_ac = $this->considerOnHoldAsAbandoned();
        if ($consider_on_hold_order_as_ac == 1) {
            $invalid_order_status[] = 'on-hold';
        }
        $consider_cancelled_order_as_ac = $this->considerCancelledAsAbandoned();
        if ($consider_cancelled_order_as_ac == 1) {
            $invalid_order_status[] = 'cancelled';
        }
        $invalid_order_status = array_unique($invalid_order_status);
        return (!in_array($order_status, $invalid_order_status));
    }

    /**
     * Checks whether an order is pending recovery.
     *
     * @param int|string $order_id order ID
     * @return bool
     * @since 2.1.0
     *
     */
    public function isOrderInPendingRecovery($order_id)
    {
        $order = self::$woocommerce->getOrder($order_id);
        if (!$order instanceof \WC_Order) {
            return false;
        }
        return (bool) get_post_meta($order_id, $this->pending_recovery_key_for_db, true);
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
        return (bool) get_post_meta($order_id, $this->order_recovered_key_for_db, true);
    }

    /**
     * Mark order as recovered
     * @param $order_id
     */
    function markOrderAsRecovered($order_id)
    {
        $order = self::$woocommerce->getOrder($order_id);
        if (!$order instanceof \WC_Order || $this->isOrderRecovered($order_id)) {
            return;
        }
        if (self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_by', 0) == 1) {
            self::$woocommerce->deleteOrderMeta($order_id, $this->pending_recovery_key_for_db);
            self::$woocommerce->setOrderMeta($order_id, $this->order_recovered_key_for_db, true);
            self::$woocommerce->setOrderNote($order, __('Order recovered by Retainful.', RNOC_TEXT_DOMAIN));
            do_action('rnoc_abandoned_order_recovered', $order);
        }
    }

    function changeOrderStatus($order_status)
    {
        $changable_order_status = array('checkout-draft');
        if ($this->considerCancelledAsAbandoned() == 1) {
            $changable_order_status[] = "cancelled";
        }
        if ($this->considerOnHoldAsAbandoned() == 1) {
            $changable_order_status[] = "on-hold";
        }
        if ($this->considerFailedAsAbandoned() == 1) {
            $changable_order_status[] = "failed";
        }
        if (in_array($order_status, $changable_order_status)) {
            $order_status = "pending";
        }
        return $order_status;
    }

    /**
     * Consider on hold payment as abandoned
     * @return int
     */
    function considerOnHoldAsAbandoned()
    {
        $settings = self::$settings->getAdminSettings();
        return isset($settings[RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status']) ? $settings[RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'] : 0;
    }

    /**
     * Consider cancelled order as abandoned
     * @return int
     */
    function considerCancelledAsAbandoned()
    {
        $settings = self::$settings->getAdminSettings();
        return isset($settings[RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status']) ? $settings[RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'] : 1;
    }

    /**
     * Consider failed order as abandoned
     * @return int
     */
    function considerFailedAsAbandoned()
    {
        $settings = self::$settings->getAdminSettings();
        return isset($settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status']) ? $settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'] : 0;
    }

    /**
     * refresh fragments on page load
     * @return int
     */
    function refreshFragmentsOnPageLoad()
    {
        $settings = self::$settings->getAdminSettings();
        return isset($settings[RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load']) ? $settings[RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'] : 0;
    }

    /**
     * Format the date to ISO8601
     * @param $timestamp
     * @return string|null
     */
    function formatToIso8601($timestamp)
    {
        if (empty($timestamp)) {
            $timestamp = current_time('timestamp', true);
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
     * Convert price to another price as per currency rate
     * @param $price
     * @param $rate
     * @return float|int
     */
    function convertToCurrency($price, $rate)
    {
        if (!empty($price) && !empty($rate)) {
            return $price / $rate;
        }
        return $price;
    }

    /**
     * Encrypt the cart
     * @param $data
     * @param $secret
     * @return string
     */
    function encryptData($data, $secret = NULL)
    {
        if (extension_loaded('openssl')) {
            if (is_array($data) || is_object($data)) {
                $data = wp_json_encode($data);
            }
            try {
                if (empty($secret)) {
                    $secret = self::$settings->getSecretKey();
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
     * Decrypt the user cart
     * @param $data_hash
     * @return string
     */
    function decryptData($data_hash)
    {
        $secret = self::$settings->getSecretKey();
        $string = base64_decode($data_hash);
        list($iv, $hmac, $cipher_text_raw) = explode(':retainful:', $string);
        $reverse_hmac = hash_hmac(self::HMAC_ALGORITHM, $cipher_text_raw, $secret, true);
        if (hash_equals($reverse_hmac, $hmac)) {
            return openssl_decrypt($cipher_text_raw, self::CIPHER_METHOD, $secret, OPENSSL_RAW_DATA, $iv);
        }
        return NULL;
    }

    /**
     * get the active currency code
     * @return String|null
     */
    function getCurrentCurrencyCode()
    {
        $default_currency = self::$settings->getBaseCurrency();
        return apply_filters('rnoc_get_current_currency_code', $default_currency);
    }

    /**
     * Get the date of cart tracing started
     * @param $user_id
     * @return array|mixed|string|null
     */
    function userCartCreatedAt($user_id = NULL)
    {
        if ($user_id || $user_id = get_current_user_id()) {
            $cart_created_at = get_user_meta($user_id, $this->cart_tracking_started_key_for_db, true);
        } else {
            $cart_created_at = self::$storage->getValue($this->cart_tracking_started_key);
        }
        return $cart_created_at;
    }

    /**
     * When user start adding to cart
     * @param null $user_id
     * @param null $time
     * @return array|mixed|string|null
     */
    function setCartCreatedDate($user_id = NULL, $time = NULL)
    {
        if (empty($time)) {
            $time = current_time('timestamp', true);
        }
        if (!empty($user_id) || $user_id = get_current_user_id()) {
            update_user_meta($user_id, $this->cart_tracking_started_key_for_db, $time);
        }
        return $time;
    }

    /**
     * Synchronize cart with SaaS
     * @param $cart_details
     * @param $extra_headers
     * @return array|bool|mixed|object|string
     */
    function syncCart($cart_details, $extra_headers)
    {
        $app_id = self::$settings->getApiKey();
        $response = false;
        if (!empty($cart_details)) {
            self::$settings->logMessage('cart synced with PHP', 'synced by');
            $response = self::$api->syncCartDetails($app_id, $cart_details, $extra_headers);
        }
        return $response;
    }

    /**
     * Check is buyer accepts marketing
     * @return bool
     */
    function isBuyerAcceptsMarketing()
    {
        if (is_user_logged_in()) {
            return true;
        } else {
            $is_buyer_accepts_marketing = self::$woocommerce->getSession('is_buyer_accepting_marketing');
            if ($is_buyer_accepts_marketing == 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * need to track carts or not
     * @param string $ip_address
     * @param $order null | \WC_Order | \WC_Cart
     * @return bool
     */
    function canTrackAbandonedCarts($ip_address = NULL, $order = null)
    {
        if (apply_filters('rnoc_is_cart_has_valid_ip', true, $ip_address) && apply_filters('rnoc_can_track_abandoned_carts', true, $order)) {
            return true;
        }
        return false;
    }

    /**
     * get the client details
     * @param null $order
     * @return mixed|void
     */
    function getClientDetails($order = null)
    {
        $client_details = array(
            'user_agent' => $this->getUserAgent($order),
            'accept_language' => $this->getUserAcceptLanguage($order),
        );
        return apply_filters('rnoc_get_client_details', $client_details, $order);
    }

    /**
     * get the user agent of client
     * @param null $order
     * @return mixed|string|null
     */
    function getUserAgent($order = null)
    {
        if (!empty($order)) {
            return self::$woocommerce->getOrderMeta($order, '_rnoc_get_http_user_agent');
        } else {
            if (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
                return $_SERVER['HTTP_USER_AGENT'];
            }
        }
        return '';
    }

    /**
     * get the user accept language
     * @param null $order
     * @return mixed|string|null
     */
    function getUserAcceptLanguage($order = null)
    {
        if (!empty($order)) {
            return self::$woocommerce->getOrderMeta($order, '_rnoc_get_http_accept_language');
        } else {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $lang = trim($_SERVER['HTTP_ACCEPT_LANGUAGE']);
                return substr($lang, 0, 2);
            }
        }
        return '';
    }
}