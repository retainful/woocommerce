<?php
/**
 * Created by PhpStorm.
 * User: cartrabbit
 * Date: 2019-05-15
 * Time: 13:54
 */
if (!class_exists('RetainfulCouponTimerAddon')) {
    include __DIR__ . '/RetainfulPremiumAddonBase.php';

    class RetainfulCouponTimerAddon extends RetainfulPremiumAddonBase
    {
        function __construct()
        {
            parent::__construct();
            $this->title = __('Countdown Timer', RNOC_TEXT_DOMAIN);
            $this->description = __('Give a clear deadline to grab the offer and create a sense of urgency using Countdown Timer', RNOC_TEXT_DOMAIN);
            $this->version = '1.0.0';
            $this->slug = 'coupon-timer-editor';
            $this->icon = 'dashicons-clock';
        }

        function init()
        {
            if (is_admin()) {
                add_filter('rnoc_premium_addon_tab', array($this, 'premiumAddonTab'));
                add_filter('rnoc_premium_addon_tab_content', array($this, 'premiumAddonTabContent'));
            }
            $need_coupon_timer = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'enable_coupon_timer', 1);
            $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
            if ($need_coupon_timer && !empty($coupon_code)) {
                add_action('woocommerce_add_to_cart', array($this, 'productAddedToCart'));
                add_action('wp_footer', array($this, 'enqueueScript'));
                add_action('woocommerce_before_cart', array($this, 'beforeCart'));
                add_filter('woocommerce_cart_totals_coupon_html', array($this, 'belowDiscount'), 100, 2);
                add_action('wp_ajax_rnoc_coupon_timer_expired', array($this, 'timerExpired'));
                add_action('wp_ajax_nopriv_rnoc_coupon_timer_expired', array($this, 'timerExpired'));
                add_action('wp_ajax_rnoc_coupon_timer_reset', array($this, 'timerReset'));
                add_action('wp_ajax_nopriv_rnoc_coupon_timer_reset', array($this, 'timerReset'));
                add_action('woocommerce_coupon_is_valid', array($this, 'ValidateCoupon'), 10, 2);
                add_filter('woocommerce_coupon_error', array($this, 'modifyInvalidCouponMessage'), 10, 3);
                add_filter('woocommerce_order_status_changed', array($this, 'orderStatusChanged'), 10, 3);
            }
        }

        function productAddedToCart()
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_ended');
            if (empty($coupon_expire_time)) {
                $this->wc_functions->setSession('rnoc_is_coupon_timer_time_started', '1');
                $coupon_timer_apply_coupon = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon', 'automatically');
                if ($coupon_timer_apply_coupon == "automatically") {
                    $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
                    $this->wc_functions->setSession('rnoc_coupon_timer_coupon_code', $coupon_code);
                }
            }
        }

        /**
         * order status changed
         * @param $order_id
         * @param $old_status
         * @param $new_status
         */
        function orderStatusChanged($order_id, $old_status, $new_status)
        {
            $this->wc_functions->removeSession('rnoc_is_coupon_timer_time_started');
            $this->wc_functions->removeSession('rnoc_is_coupon_timer_time_ended');
            $this->wc_functions->setSession('rnoc_is_coupon_timer_reset', 1);
            $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
            $order = $this->wc_functions->getOrder($order_id);
            if (!empty($order)) {
                $used_coupon_codes = $this->wc_functions->getUsedCoupons($order);
                if (is_array($used_coupon_codes) && !empty($used_coupon_codes) && in_array($coupon_code, $used_coupon_codes)) {
                    update_post_meta($order_id, '_rnocp_coupon_timer_recovered', 1);
                    update_post_meta($order_id, '_rnocp_coupon_timer_used_coupon', $coupon_code);
                }
            }
        }

        /**
         * Message when coupon expired
         * @param $message
         * @param $error_code
         * @param $coupon
         * @return mixed
         */
        function modifyInvalidCouponMessage($message, $error_code, $coupon)
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_ended');
            if (!empty($coupon_expire_time)) {
                $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
                $hook_coupon_code = $this->wc_functions->getCouponCode($coupon);
                if ((strtolower($hook_coupon_code) == strtolower($coupon_code)) && ($error_code == 101 || $error_code == 100)) {
                    $message = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message', 'Sorry! Instant Offer has expired.');
                }
            }
            return $message;
        }

        /**
         * validate coupon code
         * @param $true
         * @param $coupon
         * @return bool
         */
        function ValidateCoupon($true, $coupon)
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_ended');
            if (!empty($coupon_expire_time)) {
                $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
                $hook_coupon_code = $this->wc_functions->getCouponCode($coupon);
                if (strtolower($hook_coupon_code) == strtolower($coupon_code)) {
                    return false;
                }
            }
            return $true;
        }

        /**
         * set coupon expired
         */
        function timerExpired()
        {
            $this->wc_functions->setSession('rnoc_is_coupon_timer_time_ended', '1');
            wp_send_json_success('coupon expired');
        }

        /**
         * set coupon rested
         */
        function timerReset()
        {
            $this->wc_functions->removeSession('rnoc_is_coupon_timer_reset');
            wp_send_json_success('');
        }

        /**
         * @param $discount_amount_html
         * @param $coupon
         * @return string
         */
        function belowDiscount($discount_amount_html, $coupon)
        {
            $code = $this->wc_functions->getCouponCode($coupon);
            if (!empty($code)) {
                $discount_amount_html .= '<div class="rnoc-below-discount_container-' . $code . '"></div>';
            }
            return $discount_amount_html;
        }

        /**
         * before cart
         */
        function beforeCart()
        {
            echo '<div class="rnoc_before_cart_container"></div>';
        }

        /**
         * get the position settings
         * @param $settings_details
         * @param $key
         * @param array $default
         * @return array
         */
        function getPositionSettings($settings_details, $key, $default = array())
        {
            if (isset($settings_details[$key][0]) && is_array($settings_details[$key][0])) {
                return array_merge($default, $settings_details[$key][0]);
            }
            return $default;
        }

        /**
         * @param $premium_settings
         */
        function couponTimerSettings(&$premium_settings)
        {
            $need_coupon_timer = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'enable_coupon_timer', 1);
            $code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
            $ended = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_ended');
            if ($need_coupon_timer && !empty($code) && $ended !== 1) {
                $reset = $this->wc_functions->getSession('rnoc_is_coupon_timer_reset');
                $started = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_started');
                $premium_settings['coupon_timer'] = array(
                    'enable' => 'yes',
                    'timer_started' => ($started == 1) ? 1 : 0,
                    'expiry_message' => __($this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message', 'Sorry! Instant Offer has expired.'), RNOC_TEXT_DOMAIN),
                    'code' => base64_encode($code),
                    'time_in_minutes' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time', 15),
                    'expiry_url' => admin_url('admin-ajax.php?action=rnoc_coupon_timer_expired'),
                    'reset_url' => admin_url('admin-ajax.php?action=rnoc_coupon_timer_reset'),
                    'expired_text' => __('Expired', RNOC_TEXT_DOMAIN),
                    'timer_reset' => ($reset == 1) ? 1 : 0
                );
                $top_position_settings = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings', array());
                $top_position_enabled = $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'enable_position', 1);
                if ($top_position_enabled) {
                    $premium_settings['coupon_timer']['top'] = array(
                        'enable' => 'yes',
                        'checkout_url' => wc_get_checkout_url(),
                        'message' => __($this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_message', 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}'), RNOC_TEXT_DOMAIN),
                        'timer' => __($this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format', '{{minutes}}M {{seconds}}S'), RNOC_TEXT_DOMAIN),
                        'display_on' => $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'top_bottom_position', 'top'),
                        'background' => $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_background', '#ffffff'),
                        'color' => $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_color', '#000000'),
                        'coupon_code_color' => $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color', '#000000'),
                        'coupon_timer_color' => $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color', '#000000'),
                        'enable_cta' => ($this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'enable_checkout_button', '1') == 1) ? 'yes' : 'no',
                        'cta_text' => $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'checkout_button_text', __('Checkout Now', RNOC_TEXT_DOMAIN)),
                        'cta_color' => $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'checkout_button_color', '#ffffff'),
                        'cta_background' => $this->getKeyFromArray($top_position_settings, RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color', '#f27052'),
                    );
                } else {
                    $premium_settings['coupon_timer']['top'] = array(
                        'enable' => 'no'
                    );
                }
                $above_cart_position_settings = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings', array());
                $above_cart_position_enabled = $this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'enable_position', 0);
                if ($above_cart_position_enabled) {
                    $premium_settings['coupon_timer']['above_cart'] = array(
                        'enable' => 'yes',
                        'checkout_url' => wc_get_checkout_url(),
                        'message' => __($this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_message', 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}'), RNOC_TEXT_DOMAIN),
                        'timer' => __($this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format', '{{minutes}}M {{seconds}}S'), RNOC_TEXT_DOMAIN),
                        'background' => $this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_background', '#ffffff'),
                        'color' => $this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_color', '#000000'),
                        'coupon_code_color' => $this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color', '#000000'),
                        'coupon_timer_color' => $this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color', '#000000'),
                        'enable_cta' => ($this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'enable_checkout_button', '1') == 1) ? 'yes' : 'no',
                        'cta_text' => $this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'checkout_button_text', __('Checkout Now', RNOC_TEXT_DOMAIN)),
                        'cta_color' => $this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'checkout_button_color', '#ffffff'),
                        'cta_background' => $this->getKeyFromArray($above_cart_position_settings, RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color', '#f27052'),
                    );
                } else {
                    $premium_settings['coupon_timer']['above_cart'] = array(
                        'enable' => 'no'
                    );
                }
                $below_discount_position_settings = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings', array());
                $below_discount_position_enabled = $this->getKeyFromArray($below_discount_position_settings, RNOC_PLUGIN_PREFIX . 'enable_position', 0);
                if ($below_discount_position_enabled) {
                    $premium_settings['coupon_timer']['below_discount'] = array(
                        'enable' => 'yes',
                        'message' => __($this->getKeyFromArray($below_discount_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_message', 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}'), RNOC_TEXT_DOMAIN),
                        'timer' => __($this->getKeyFromArray($below_discount_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format', '{{minutes}}M {{seconds}}S'), RNOC_TEXT_DOMAIN),
                        'background' => $this->getKeyFromArray($below_discount_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_background', '#ffffff'),
                        'color' => $this->getKeyFromArray($below_discount_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_color', '#000000'),
                        'coupon_code_color' => $this->getKeyFromArray($below_discount_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color', '#000000'),
                        'coupon_timer_color' => $this->getKeyFromArray($below_discount_position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color', '#000000'),
                    );
                } else {
                    $premium_settings['coupon_timer']['below_discount'] = array(
                        'enable' => 'no'
                    );
                }
            } else {
                $premium_settings['coupon_timer'] = array(
                    'enable' => 'no'
                );
            }
        }

        /**
         * enqueue script
         */
        function enqueueScript()
        {
            $premium_settings = array();
            $this->couponTimerSettings($premium_settings);
            $arr = array(
                'checkout_url' => '',
                'cart_url' => '',
                'ei_popup' =>
                    array(
                        'enable' => 'yes',
                        'show_for' => 'everyone',
                        'is_user_logged_in' => 'no',
                        'coupon_code' => NULL,
                        'show_once_its_coupon_applied' => 'no',
                        'applied_coupons' =>
                            array(
                                0 => 'no',
                            ),
                        'show_popup' => 'always',
                        'number_of_times_per_page' => '1',
                        'cookie_expired_at' => '1',
                        'redirect_url' => '1',
                        'mobile' =>
                            array(
                                'enable' => 'yes',
                                'time_delay' => 'yes',
                                'delay' => '10',
                                'scroll_distance' => 'yes',
                                'distance' => '10',
                            ),
                    ),
            );
            if (!wp_script_is('rnoc-premium')) {
                wp_enqueue_script('rnoc-premium', RNOCPREMIUM_PLUGIN_URL . 'assets/js/premium.js', array('jquery'), RNOC_VERSION);
            }
            wp_localize_script('rnoc-premium', 'rnoc_premium', $premium_settings);
            if (!wp_style_is('rnoc-premium')) {
                wp_enqueue_style('rnoc-premium', RNOCPREMIUM_PLUGIN_URL . 'assets/css/premium.css', array(), RNOC_VERSION);
            }
        }

        /**
         * auto apply coupon code
         */
        function autoApplyCouponCode()
        {
            $coupon_code = $this->wc_functions->getSession('rnoc_coupon_timer_coupon_code');
            if (!empty($coupon_code) && $this->wc_functions->isValidCoupon($coupon_code) && !$this->wc_functions->hasDiscount($coupon_code)) {
                $this->wc_functions->addDiscount($coupon_code);
                $this->wc_functions->removeSession('rnoc_coupon_timer_coupon_code');
            }
        }

        /**
         * add the settings tabs
         * @param $settings
         * @return array
         */
        function premiumAddonTab($settings)
        {
            $settings[] = array(
                'id' => $this->slug,
                'icon' => $this->icon,
                'title' => __('Coupon Timer', RNOC_TEXT_DOMAIN),
                'fields' => array(
                    RNOC_PLUGIN_PREFIX . 'enable_coupon_timer',
                    RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages',
                    RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon',
                    RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message',
                    RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload',
                    RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon',
                    RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time',
                    RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings',
                    RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings',
                    RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings',
                ),
            );
            return $settings;
        }

        /**
         * add settings field to render
         * @param $general_settings
         * @return mixed
         */
        function premiumAddonTabContent($general_settings)
        {
            $general_settings->add_field(array(
                'name' => __('Enable Coupon timer?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_coupon_timer',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 1
            ));
            $general_settings->add_field(array(
                'type' => 'post_search_ajax',
                'limit' => 1,
                'valuefield' => 'title',
                'attributes' => array(
                    'placeholder' => __('Search and select Coupons..', RNOC_TEXT_DOMAIN)
                ),
                'query_args' => array('post_type' => 'shop_coupon', 'post_status' => 'publish'),
                'name' => __('Choose the coupon code', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon',
                'desc' => __('<b>Note</b>:This is a list of coupon codes from WooCommerce -> Coupons. If none found, please create the coupon code in WooCommerce -> Coupons', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Custom pages to display the coupon timer', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages',
                'type' => 'pw_multiselect',
                'options' => $this->getPageLists(),
                'attributes' => array(
                    'placeholder' => __('Select Pages', RNOC_TEXT_DOMAIN)
                )
            ));
            $general_settings->add_field(array(
                'name' => __('Apply coupon', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    'automatically' => __('Automatically', RNOC_TEXT_DOMAIN),
                    'manually' => __('Manually', RNOC_TEXT_DOMAIN)
                ),
                'default' => 'automatically'
            ));
            $general_settings->add_field(array(
                'name' => __('Time', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time',
                'type' => 'text',
                'desc' => __('In minutes', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'type' => 'number',
                    'class' => 'number_only_field',
                    'min' => 1
                ),
                'default' => 15
            ));
            $general_settings->add_field(array(
                'name' => __('Coupon expiry message', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message',
                'type' => 'text',
                'desc' => __('Display this text when coupon expires.', RNOC_TEXT_DOMAIN),
                'default' => __('Sorry! Instant Offer has expired.', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Fix repeat reloading of page when coupon expires?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 0
            ));
            //Top position settings
            $top_position_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('Top / bottom position display settings', RNOC_TEXT_DOMAIN),
                    'sortable' => true
                ),
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Enable Top / bottom position', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_position',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 1
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Position', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'top_bottom_position',
                'type' => 'radio_inline',
                'options' => array(
                    'top' => __('Top', RNOC_TEXT_DOMAIN),
                    'bottom' => __('Bottom', RNOC_TEXT_DOMAIN)
                ),
                'default' => "top"
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Message', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_message',
                'type' => 'text',
                'desc' => __('Use below short codes<br>{{coupon_code}} => to show coupon code<br>{{coupon_timer}} => remaining time timer', RNOC_TEXT_DOMAIN),
                'default' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Timer format', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format',
                'type' => 'text',
                'desc' => __('Use below short codes<br>{{days}} => to show remaining days<br>{{hours}} => to show remaining hours<br>{{minutes}} - to show remaining minutes<br>{{seconds}} - to display remaining seconds', RNOC_TEXT_DOMAIN),
                'default' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Background color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_background',
                'type' => 'colorpicker',
                'default' => '#ffffff'
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Coupon code color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Coupon Timer color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Enable checkout button', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_checkout_button',
                'type' => 'radio_inline',
                'options' => array(
                    0 => __('No', RNOC_TEXT_DOMAIN),
                    1 => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 1
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Call to action button text', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'checkout_button_text',
                'type' => 'text',
                'default' => __('Checkout Now', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Call to action button color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'checkout_button_color',
                'type' => 'colorpicker',
                'default' => '#ffffff'
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Call to action button background color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color',
                'type' => 'colorpicker',
                'default' => '#f27052'
            ));
            //Above cart position settings
            $above_cart_position_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('Above cart display settings', RNOC_TEXT_DOMAIN),
                    'sortable' => true
                ),
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Enable "Above cart" position', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_position',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 0
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Message', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_message',
                'type' => 'text',
                'desc' => __('Use below short codes<br>{{coupon_code}} => to show coupon code<br>{{coupon_timer}} => remaining time timer', RNOC_TEXT_DOMAIN),
                'default' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Timer format', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format',
                'type' => 'text',
                'desc' => __('Use below short codes<br>{{days}} => to show remaining days<br>{{hours}} => to show remaining hours<br>{{minutes}} - to show remaining minutes<br>{{seconds}} - to display remaining seconds', RNOC_TEXT_DOMAIN),
                'default' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Background color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_background',
                'type' => 'colorpicker',
                'default' => '#ffffff'
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Coupon code color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Coupon Timer color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Enable checkout button', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_checkout_button',
                'type' => 'radio_inline',
                'options' => array(
                    0 => __('No', RNOC_TEXT_DOMAIN),
                    1 => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 1
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Call to action button text', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'checkout_button_text',
                'type' => 'text',
                'default' => __('Checkout Now', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Call to action button color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'checkout_button_color',
                'type' => 'colorpicker',
                'default' => '#ffffff'
            ));
            $general_settings->add_group_field($above_cart_position_settings, array(
                'name' => __('Call to action button background color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color',
                'type' => 'colorpicker',
                'default' => '#f27052'
            ));
            //Below applied coupon settings
            $below_discount_position_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('Below applied coupon display settings', RNOC_TEXT_DOMAIN),
                    'sortable' => true
                ),
            ));
            $general_settings->add_group_field($below_discount_position_settings, array(
                'name' => __('Enable "Below applied coupon" position', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_position',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 0
            ));
            $general_settings->add_group_field($below_discount_position_settings, array(
                'name' => __('Message', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_message',
                'type' => 'text',
                'desc' => __('Use below short codes<br>{{coupon_code}} => to show coupon code<br>{{coupon_timer}} => remaining time timer', RNOC_TEXT_DOMAIN),
                'default' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($below_discount_position_settings, array(
                'name' => __('Timer format', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format',
                'type' => 'text',
                'desc' => __('Use below short codes<br>{{days}} => to show remaining days<br>{{hours}} => to show remaining hours<br>{{minutes}} - to show remaining minutes<br>{{seconds}} - to display remaining seconds', RNOC_TEXT_DOMAIN),
                'default' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($below_discount_position_settings, array(
                'name' => __('Background color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_background',
                'type' => 'colorpicker',
                'default' => '#ffffff'
            ));
            $general_settings->add_group_field($below_discount_position_settings, array(
                'name' => __('Color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($below_discount_position_settings, array(
                'name' => __('Coupon code color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($below_discount_position_settings, array(
                'name' => __('Coupon Timer color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            return $general_settings;
        }
    }
}