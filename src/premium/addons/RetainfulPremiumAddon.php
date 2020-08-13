<?php
/**
 * Created by PhpStorm.
 * User: cartrabbit
 * Date: 2019-05-15
 * Time: 13:54
 */
if (!class_exists('RetainfulPremiumAddon')) {
    include __DIR__ . '/RetainfulPremiumAddonBase.php';

    class RetainfulPremiumAddon extends RetainfulPremiumAddonBase
    {
        function __construct()
        {
            parent::__construct();
            $this->title = __('Premium addon', RNOC_TEXT_DOMAIN);
            $this->description = __('Collect customer email at the time of adding to cart. This can help recover the cart even if the customer abandon it before checkout', RNOC_TEXT_DOMAIN);
            $this->version = '1.0.0';
            $this->slug = 'add-to-cart-popup-editor';
            $this->icon = 'dashicons-cart';
        }

        function init()
        {
            $arr = array();
            $this->exitIntentPopupSettings($arr);
        }

        function exitIntentPopupSettings(&$premium_settings)
        {
            echo '<pre>';
            print_r($this->premium_addon_settings);
            echo '</pre>';
            die;
            $need_ei_popup = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal', 1);
            if ($need_ei_popup == 1) {
                $show_settings = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings', array());
                $mobile_settings = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings', array(array()));
                $mobile_settings = isset($mobile_settings[0]) ? $mobile_settings[0] : array();
                $premium_settings['ei_popup'] = array(
                    'enable' => 'yes',
                    'show_for' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to', 'all'),
                    'is_user_logged_in' => is_user_logged_in() ? 'yes' : 'no',
                    'coupon_code' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon', ''),
                    'show_once_its_coupon_applied' => ($this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied', '0') == 1) ? 'yes' : 'no',
                    'applied_coupons' => array(),
                    'show_popup' => $this->getKeyFromArray($show_settings, 'show_option', 'once_per_session'),
                    'number_of_times_per_page' => $this->getKeyFromArray($show_settings, 'show_count', '1'),
                    'cookie_expired_at' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life', '1'),
                );
                if ($this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'enable_mobile_support', '0') == 1) {
                    $premium_settings['ei_popup']['mobile'] = array(
                        'enable' => 'yes',
                        'time_delay' => ($this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'enable_delay_trigger', '0') == 1) ? 'yes' : 'no',
                        'delay' => $this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec', '0'),
                        'scroll_distance' => ($this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger', '0') == 1) ? 'yes' : 'no',
                        'distance' => $this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance', '0'),
                    );
                } else {
                    $premium_settings['ei_popup']['mobile'] = array(
                        'enable' => 'no'
                    );
                }
            } else {
                $premium_settings['ei_popup'] = array(
                    'enable' => 'no',
                );
            }
        }
    }
}