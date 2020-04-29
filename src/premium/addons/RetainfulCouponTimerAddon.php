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
                add_action('wp', array($this, 'showTimer'));
                add_action('woocommerce_init', array($this, 'showTimer'));
                add_action('woocommerce_coupon_is_valid', array($this, 'ValidateCoupon'), 10, 2);
                add_filter('woocommerce_coupon_error', array($this, 'modifyInvalidCouponMessage'), 10, 3);
                add_filter('woocommerce_order_status_changed', array($this, 'orderStatusChanged'), 10, 3);
                add_action('woocommerce_before_cart', array($this, 'initTimerInCart'));
                add_action('wp_footer', array($this, 'showCouponTimerOnAjaxCall'));
            }
        }

        /**
         * Refresh the page immediately after user added to cart, because timer need to show after countdown start
         */
        function showCouponTimerOnAjaxCall()
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_coupon_timer_expired_on_gmt');
            if (empty($coupon_expire_time)) {
                echo '<script type="text/javascript">jQuery(document).on("added_to_cart",function(){window.location.reload();});</script>';
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
            $this->wc_functions->removeSession('rnoc_coupon_timer_expired_on_gmt');
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
         * validate coupon code
         * @param $true
         * @param $coupon
         * @return bool
         */
        function ValidateCoupon($true, $coupon)
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_coupon_timer_expired_on_gmt');
            if (!empty($coupon_expire_time) && (current_time('timestamp', true) > $coupon_expire_time)) {
                $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
                $hook_coupon_code = $this->wc_functions->getCouponCode($coupon);
                if (strtolower($hook_coupon_code) == strtolower($coupon_code)) {
                    return false;
                }
            }
            return $true;
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
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_coupon_timer_expired_on_gmt');
            if (!empty($coupon_expire_time) && (current_time('timestamp', true) > $coupon_expire_time)) {
                $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
                $hook_coupon_code = $this->wc_functions->getCouponCode($coupon);
                if ((strtolower($hook_coupon_code) == strtolower($coupon_code)) && ($error_code == 101 || $error_code == 100)) {
                    $message = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message', 'Sorry! Instant Offer has expired.');
                }
            }
            return $message;
        }

        /**
         * init the addon
         */
        function showTimer()
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_coupon_timer_expired_on_gmt');
            if (!empty($coupon_expire_time) && $coupon_expire_time >= current_time("timestamp", true)) {
                $modal_display_pages = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages', array());
                if ($this->isValidPagesToDisplay($modal_display_pages)) {
                    $top_position = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings');
                    $enable_top_position = $this->getKeyFromArray($top_position, RNOC_PLUGIN_PREFIX . 'enable_position', 1);
                    if ($enable_top_position) {
                        add_action('wp_footer', array($this, 'displayTimerOnTop'));
                    }
                    $above_cart_position = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings');
                    $enable_above_cart_position = $this->getKeyFromArray($above_cart_position, RNOC_PLUGIN_PREFIX . 'enable_position', 0);
                    if ($enable_above_cart_position) {
                        add_action('woocommerce_before_cart_table', array($this, 'displayTimerAboveCart'));
                    }
                    $below_discount_position = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings');
                    $enable_above_cart_position = $this->getKeyFromArray($below_discount_position, RNOC_PLUGIN_PREFIX . 'enable_position', 0);
                    if ($enable_above_cart_position) {
                        add_filter('woocommerce_cart_totals_coupon_html', array($this, 'displayTimerBelowDiscount'), 100, 2);
                    }
                }
            }
            return true;
        }

        /**
         * Default design settings for timer
         * @return array
         */
        function defaultTimerDesign()
        {
            $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
            $checkout_url = $this->getCheckoutUrl();
            return array(
                RNOC_PLUGIN_PREFIX . 'enable_position' => 0,
                RNOC_PLUGIN_PREFIX . 'coupon_timer_message' => __('Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_background' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format' => ' {{minutes}}M {{seconds}}S',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'enable_checkout_button' => 1,
                RNOC_PLUGIN_PREFIX . 'checkout_url' => add_query_arg('retainful_ac_coupon', $coupon_code, $checkout_url),
                RNOC_PLUGIN_PREFIX . 'checkout_button_text' => __('Checkout Now', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'checkout_button_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color' => '#f27052',
            );
        }

        /**
         * display timer in float
         */
        function displayTimerOnTop()
        {
            $top_position = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings', $this->defaultTimerDesign());
            $this->displayTimerInPlace('top', $this->premium_addon_settings, $top_position);
        }

        /**
         * display timer before cart
         */
        function displayTimerAboveCart()
        {
            $above_cart_position = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings', $this->defaultTimerDesign());
            $this->displayTimerInPlace('above_cart', $this->premium_addon_settings, $above_cart_position);
        }

        /**
         *  display timer below discount data
         * @param $discount_amount_html
         * @param $coupon
         */
        function displayTimerBelowDiscount($discount_amount_html, $coupon)
        {
            echo $discount_amount_html;
            $code = $this->wc_functions->getCouponCode($coupon);
            if (!empty($code)) {
                $above_cart_position = $this->getPositionSettings($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings', $this->defaultTimerDesign());
                $this->displayTimerInPlace('below_discount', $this->premium_addon_settings, $above_cart_position, $code);
            }
        }

        /**
         * Display timer
         * @param $position
         * @param $settings_details
         * @param $position_settings
         * @param null $used_code
         * @return bool
         */
        function displayTimerInPlace($position, $settings_details, $position_settings, $used_code = NULL)
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_coupon_timer_expired_on_gmt');
            if (!empty($coupon_expire_time) && (current_time('timestamp', true) <= $coupon_expire_time)) {
                $coupon_code = $this->getKeyFromArray($settings_details, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon');
                if (!is_null($used_code) && strtolower($used_code) != strtolower($coupon_code)) {
                    return false;
                }
                $timer_message = $this->getKeyFromArray($position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_message', __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN));
                $timer_display_format = '"' . $this->getKeyFromArray($position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format', __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN)) . '"';
                $text_to_replace = array(
                    'coupon_code' => '<span class="timer-coupon-code-' . $position . '">' . $coupon_code . '</span>',
                    'coupon_timer' => '<span id="rnoc-coupon-timer-' . $position . '"></span>'
                );
                foreach ($text_to_replace as $find => $replace) {
                    $timer_message = str_replace('{{' . $find . '}}', $replace, $timer_message);
                }
                $text_to_replace_timer = array(
                    'days',
                    'hours',
                    'minutes',
                    'seconds'
                );
                foreach ($text_to_replace_timer as $find) {
                    $timer_display_format = str_replace('{{' . $find . '}}', '"+' . $find . '+"', $timer_display_format);
                }
                $position_settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_message'] = $timer_message;
                $position_settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format'] = $timer_display_format;
                $position_settings['coupon_expire_time'] = $coupon_expire_time;
                $position_settings['coupon_timer_position'] = $position;
                $override_path = get_theme_file_path('retainful/premium/templates/timer/' . $position . '.php');
                $template_path = RNOCPREMIUM_PLUGIN_PATH . 'templates/timer/' . $position . '.php';
                if (file_exists($override_path)) {
                    $template_path = $override_path;
                }
                echo $this->getTemplateContent($template_path, $position_settings, 'coupon_timer_on_' . $position);
            }
            return true;
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
         * Automatically init the timer
         */
        function initTimerInCart()
        {
            $cart = $this->wc_functions->getCart();
            if (is_cart() && !empty($cart)) {
                $this->initTimer();
            }
        }

        /**
         * Apply coupon while adding product to cart
         */
        function productAddedToCart()
        {
            $this->initTimer();
        }

        /**
         * init the timer
         */
        function initTimer()
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_coupon_timer_expired_on_gmt');
            if (empty($coupon_expire_time)) {
                $expired_on_min = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time', 15);
                if (!empty($expired_on_min)) {
                    $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
                    $expiry_timestamp_gmt = current_time('timestamp', true) + ($expired_on_min * 60);
                    $this->wc_functions->setSession('rnoc_coupon_timer_expired_on_gmt', $expiry_timestamp_gmt);
                    $coupon_timer_apply_coupon = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon', 'automatically');
                    if ($coupon_timer_apply_coupon == "automatically" && $this->wc_functions->isValidCoupon($coupon_code)) {
                        $this->wc_functions->addDiscount($coupon_code);
                    }
                }
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
            //Top position settings
            $top_position_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('Top position display settings', RNOC_TEXT_DOMAIN),
                    'sortable' => true
                ),
            ));
            $general_settings->add_group_field($top_position_settings, array(
                'name' => __('Enable "Top" position', RNOC_TEXT_DOMAIN),
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