<?php

namespace Rnoc\Retainful\Admin;
class Settings
{
    /**
     * options key
     * @var string
     */
    public $slug = 'retainful';
    /**
     * settings
     * @var array[]
     */
    public static $settings;

    function __construct()
    {
        $connection = get_option($this->slug . '_license', array());
        $premium = get_option($this->slug . '_premium', array());
        $next_order_coupon = get_option($this->slug, array());
        $general_settings = get_option($this->slug . '_settings', array());
        $default_connection = $this->getDefaultConnectionSettings();
        $default_premium = $this->getDefaultPremiumSettings();
        $default_next_order_coupon = $this->getDefaultNocSettings();
        $default_general_settings = $this->getDefaultGsSettings();
        self::$settings = array(
            'connection' => wp_parse_args($connection, $default_connection),
            'premium' => wp_parse_args($premium, $default_premium),
            'next_order_coupon' => wp_parse_args($next_order_coupon, $default_next_order_coupon),
            'general_settings' => wp_parse_args($general_settings, $default_general_settings),
        );
    }

    /**
     * save the settings
     * @param $category
     * @param $data
     */
    function set($category, $data)
    {
        switch ($category) {
            case "connection":
                update_option($this->slug . '_license', $data);
                self::$settings['connection'] = $data;
                break;
            case "premium":
                update_option($this->slug . '_premium', $data);
                self::$settings['premium'] = $data;
                break;
            case "next_order_coupon":
                update_option($this->slug, $data);
                self::$settings['next_order_coupon'] = $data;
                break;
            case "general_settings":
                update_option($this->slug . '_settings', $data);
                self::$settings['general_settings'] = $data;
                break;
            default:
                break;
        }
    }

    /**
     * get page url
     * @param $category
     * @return string|void
     */
    function getPageUrl($category)
    {
        switch ($category) {
            case "connection":
                $page = $this->slug . '_license';
                break;
            case "premium":
                $page = $this->slug . '_premium';
                break;
            case "next_order_coupon":
                $page = $this->slug;
                break;
            case "general_settings":
                $page = $this->slug . '_settings';
                break;
            default:
                $page = '';
                break;
        }
        return admin_url('admin.php?page=' . $page);;
    }

    /**
     * get the option
     * @param $category
     * @param string $key
     * @param string $default
     * @param bool $all
     * @param bool $depth
     * @param string $depth_key
     * @return array|mixed|string
     */
    function get($category, $key = "", $default = "", $all = false, $depth = false, $depth_key = null)
    {
        switch ($category) {
            case "connection":
                if ($all) {
                    return self::$settings['connection'];
                } else {
                    return $this->getByKey(self::$settings['connection'], $key, $depth, $depth_key, $default);
                }
                break;
            case "premium":
                if ($all) {
                    return self::$settings['premium'];
                } else {
                    return $this->getByKey(self::$settings['premium'], $key, $depth, $depth_key, $default);
                }
                break;
            case "next_order_coupon":
                if ($all) {
                    return self::$settings['next_order_coupon'];
                } else {
                    return $this->getByKey(self::$settings['next_order_coupon'], $key, $depth, $depth_key, $default);
                }
                break;
            case "general_settings":
                if ($all) {
                    return self::$settings['general_settings'];
                } else {
                    return $this->getByKey(self::$settings['general_settings'], $key, $depth, $depth_key, $default);
                }
                break;
            default:
                return $default;
                break;
        }
    }

    /**
     * get the settings by key
     * @param $settings
     * @param $key
     * @param $depth
     * @param $depth_key
     * @param $default
     * @return mixed
     */
    function getByKey($settings, $key, $depth, $depth_key, $default)
    {
        if (is_int($depth) && $depth >= 0) {
            if (empty($depth_key)) {
                return isset($settings[$key][$depth]) ? $settings[$key][$depth] : $default;
            }
            return isset($settings[$key][$depth][$depth_key]) ? $settings[$key][$depth][$depth_key] : $default;
        } else {
            return isset($settings[$key]) ? $settings[$key] : $default;
        }
    }

    /**
     * default connection settings
     * @return array
     */
    function getDefaultConnectionSettings()
    {
        return array(
            RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => 0,
            RNOC_PLUGIN_PREFIX . 'retainful_app_id' => '',
            RNOC_PLUGIN_PREFIX . 'retainful_app_secret' => '',
        );
    }

    /**
     * default noc settings
     * @return array
     */
    function getDefaultNocSettings()
    {
        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_type' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount' => '10',
            RNOC_PLUGIN_PREFIX . 'retainful_expire_days' => '60',
            RNOC_PLUGIN_PREFIX . 'expire_date_format' => 'F j, Y, g:i a',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to' => 'all',
            RNOC_PLUGIN_PREFIX . 'automatically_generate_coupon' => '1',
            RNOC_PLUGIN_PREFIX . 'show_next_order_coupon_in_thankyou_page' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_add_coupon_message_to' => 'woocommerce_email_customer_details',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_message' => '<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Go! </a></p></div></div>',
            RNOC_PLUGIN_PREFIX . 'preferred_order_status' => array('wc-processing', 'wc-completed'),
            RNOC_PLUGIN_PREFIX . 'preferred_user_roles' => array('all'),
            RNOC_PLUGIN_PREFIX . 'limit_per_user' => 99,
            RNOC_PLUGIN_PREFIX . 'minimum_sub_total' => '',
            RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_products' => array(),
            RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_categories' => array(),
            RNOC_PLUGIN_PREFIX . 'minimum_spend' => '',
            RNOC_PLUGIN_PREFIX . 'maximum_spend' => '',
            RNOC_PLUGIN_PREFIX . 'products' => array(),
            RNOC_PLUGIN_PREFIX . 'exclude_products' => array(),
            RNOC_PLUGIN_PREFIX . 'product_categories' => array(),
            RNOC_PLUGIN_PREFIX . 'exclude_product_categories' => array(),
            RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup' => '1',
            RNOC_PLUGIN_PREFIX . 'coupon_applied_popup_design' => $this->appliedCouponDefaultTemplate(),
        );
        return apply_filters('rnoc_get_default_noc_settings', $default_settings);
    }

    /**
     * default noc settings
     * @return array
     */
    function getDefaultGsSettings()
    {
        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'cart_tracking_engine' => 'js',
            RNOC_PLUGIN_PREFIX . 'track_zero_value_carts' => 'no',
            RNOC_PLUGIN_PREFIX . 'move_email_field_to_top' => '0',
            RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status' => '1',
            RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load' => '0',
            RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance' => '0',
            RNOC_PLUGIN_PREFIX . 'cart_capture_msg' => '',
            RNOC_PLUGIN_PREFIX . 'enable_ip_filter' => '0',
            RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses' => '',
            RNOC_PLUGIN_PREFIX . 'enable_debug_log' => '0',
            RNOC_PLUGIN_PREFIX . 'handle_storage_using' => 'woocommerce',
        );
        return apply_filters('rnoc_get_default_general_settings', $default_settings);
    }

    /**
     * default connection settings
     * @return array
     */
    function getDefaultPremiumSettings()
    {
        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'enable_coupon_timer' => '0',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon' => '',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages' => array(),
            RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon' => 'automatically',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time' => '15',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message' => __('Sorry! Instant Offer has expired.', RNOC_TEXT_DOMAIN),
            RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload' => '0',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'enable_position' => '1',
                RNOC_PLUGIN_PREFIX . 'top_bottom_position' => 'top',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_message' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_background' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'checkout_button_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'checkout_button_text' => __('Checkout Now', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'enable_checkout_button' => 1,
            )),
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'enable_position' => '1',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_message' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_background' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'checkout_button_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'checkout_button_text' => __('Checkout Now', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'enable_checkout_button' => 1,
            )),
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'enable_position' => '1',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_message' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_background' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color' => '#000000'
            )),
            RNOC_PLUGIN_PREFIX . 'need_modal' => '0',
            RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory' => '1',
            RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action' => '1',
            RNOC_PLUGIN_PREFIX . 'close_btn_behavior' => 'just_close',
            RNOC_PLUGIN_PREFIX . 'modal_show_popup_until' => '1',
            RNOC_PLUGIN_PREFIX . 'modal_display_pages' => array(),
            RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class' => '',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'modal_heading' => __('Enter your email to add this item to cart', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_heading_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'modal_email_placeholder' => __('Email address', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_text' => __('Add to Cart', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_bg_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_border_top_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'modal_bg_color' => '#F8F0F0',
                RNOC_PLUGIN_PREFIX . 'modal_not_mandatory_text' => __('No thanks! Add item to cart', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_no_thanks_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'modal_terms_text' => __('*By completing this, you are signing up to receive our emails. You can unsubscribe at any time.', RNOC_TEXT_DOMAIN),
            )),
            RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings' => 'no_need_gdpr',
                RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message' => __('I accept the <a href="#">Terms and conditions</a>', RNOC_TEXT_DOMAIN),
            )),
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'need_coupon' => '0',
                RNOC_PLUGIN_PREFIX . 'woo_coupon' => '',
                RNOC_PLUGIN_PREFIX . 'modal_sub_heading' => __('Get a discount in your email!', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color' => '#333333',
                RNOC_PLUGIN_PREFIX . 'show_woo_coupon' => 'send_via_email',
                RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template' => '',
                RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject' => __('You got a new coupon code, Grab it now!', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_mail_template' => '',
            )),
            RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal' => 0,
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages' => array(),
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to' => 'all',
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon' => '',
            RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied' => 0,
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings' => array('show_option' => 'once_per_page', 'show_count' => 1),
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life' => 1,
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template' => '',
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style' => '',
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success' => 'checkout',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings' => 'no_need_gdpr',
                RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message' => __('I accept the <a href="#">Terms and conditions</a>', RNOC_TEXT_DOMAIN),
            )),
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder' => __('Enter E-mail address', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_height' => '46px',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_width' => '100%',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text' => __('Complete checkout', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_bg_color' => '#f20561',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_height' => '100%',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_width' => '100%',
            )),
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'enable_mobile_support' => '0',
                RNOC_PLUGIN_PREFIX . 'enable_delay_trigger' => '0',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec' => '0',
                RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger' => '0',
                RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance' => '0',
            )),
        );
        return apply_filters('rnoc_premium_addon_default_values', $default_settings);
    }

    /**
     * applied Coupon Default Template
     * @return string
     */
    function appliedCouponDefaultTemplate()
    {
        return '<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_code}} was successfully applied to your cart!</h3><p style="margin:10px auto; ">Enjoy your shopping :)</p><p style="text-align: center; margin: 0;"><a href="{{shop_url}}" style="text-decoration: none;line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff;">Continue shopping!</a></p></div></div>';
    }
}