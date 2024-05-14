<?php

namespace Rnoc\App\Helpers;

use http\Encoding\Stream\Deflate;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
class Settings
{

    /**
     * Get Default Data.
     *
     * @return array
     */
    public static function getDefaultData()
    {
        return [
            RNOC_PLUGIN_PREFIX . 'cart_tracking_engine' => 'js',
            RNOC_PLUGIN_PREFIX . 'enable_background_order_sync' => 'no',
            RNOC_PLUGIN_PREFIX . 'track_zero_value_carts' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_referral_widget' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget' => 'yes',
            RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status' => '1',
            RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load' => '0',
            RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance' => '0',
            RNOC_PLUGIN_PREFIX . 'cart_capture_msg' => 'Keep me up to date on news and exclusive offers',
            RNOC_PLUGIN_PREFIX . 'gdpr_display_position' => 'after_billing_email',
            RNOC_PLUGIN_PREFIX . 'enable_ip_filter' => '0',
            RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses' => '',
            RNOC_PLUGIN_PREFIX . 'enable_debug_log' => '0',
            RNOC_PLUGIN_PREFIX . 'handle_storage_using' => 'woocommerce',
            RNOC_PLUGIN_PREFIX . 'enable_afterpay_action' => 'no',
            RNOC_PLUGIN_PREFIX . 'varnish_check' => 'no',
        ];
    }

    /**
     * Get Setting Data.
     *
     * @return array|mixed
     */
    public static function getData($option_key, $default = [])
    {
        return get_option($option_key, $default);
    }

    /**
     * get single settings data.
     *
     * @param string $key Setting key.
     * @return mixed|string
     */
    public static function get($key, $option_key)
    {
        $options = self::getData($option_key);
        if (!isset($options[$key])) {
            $default_data = self::getDefaultData();
            return isset($default_data[$key]) && $default_data[$key] ? $default_data[$key]['value'] : '';
        }
        return $options[$key];
    }

}