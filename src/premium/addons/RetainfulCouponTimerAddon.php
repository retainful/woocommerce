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
                add_action('rnoc_premium_addon_settings_page_' . $this->slug(), array($this, 'premiumAddonTabContent'), 10, 3);
            }
            add_action('wp_ajax_rnoc_coupon_timer_expired', array($this, 'timerExpired'));
            add_action('wp_ajax_nopriv_rnoc_coupon_timer_expired', array($this, 'timerExpired'));
            $need_coupon_timer = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'enable_coupon_timer', 0);
            $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
            if ($need_coupon_timer && !empty($coupon_code)) {
                add_action('woocommerce_add_to_cart', array($this, 'productAddedToCart'));
                add_action('woocommerce_after_calculate_totals', array($this, 'autoApplyCouponCode'));
                add_action('wp', array($this, 'showTimer'));
                add_action('woocommerce_init', array($this, 'showTimer'));
                add_action('woocommerce_coupon_is_valid', array($this, 'ValidateCoupon'), 10, 2);
                add_filter('woocommerce_coupon_error', array($this, 'modifyInvalidCouponMessage'), 10, 3);
                add_action('woocommerce_order_status_changed', array($this, 'orderStatusChanged'), 10, 3);
                add_action('woocommerce_before_cart', array($this, 'initTimerInCart'));
                add_action('wp_footer', array($this, 'showCouponTimerOnAjaxCall'));
            }
        }

        function timerExpired()
        {
            $this->wc_functions->setSession('rnoc_is_coupon_timer_time_ended', '1');
            wp_send_json_success('coupon expired');
        }

        /**
         * Refresh the page immediately after user added to cart, because timer need to show after countdown start
         */
        function showCouponTimerOnAjaxCall()
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_started');
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
         * init the addon
         */
        function showTimer()
        {
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_ended');
            if (empty($coupon_expire_time)) {
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
                RNOC_PLUGIN_PREFIX . 'top_bottom_position' => "top",
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
            $is_ended = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_ended');
            $is_started = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_started');
            if (empty($is_ended) && !empty($is_started)) {
                $coupon_code = $this->getKeyFromArray($settings_details, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon');
                if (!is_null($used_code) && strtolower($used_code) != strtolower($coupon_code)) {
                    return false;
                }
                $timer_message = $this->getKeyFromArray($position_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_message', "Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}");
                $timer_message = apply_filters('rnoc_coupon_timer_message',$timer_message);
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
                $expired_on_min = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time', 15);
                $is_timer_started = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_started');
                $is_timer_reset = $this->wc_functions->getSession('rnoc_is_coupon_timer_reset');
                $position_settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_message'] = $timer_message;
                $position_settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format'] = $timer_display_format;
                $position_settings['is_timer_started'] = (empty($is_timer_started)) ? 0 : 1;
                $position_settings['is_timer_reset'] = (empty($is_timer_reset)) ? 0 : 1;
                $position_settings['coupon_timer_position'] = $position;
                $position_settings['expired_in_min'] = $expired_on_min;
                $position_settings['auto_fix_page_reload'] = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload', 0);
                $position_settings['coupon_timer_expire_message'] = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message', 'Sorry! Instant Offer has expired.');
                $position_settings['coupon_timer_expired_text'] = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expired_text', 'EXPIRED');
                $position_settings['coupon_code'] = $coupon_code;
                $position_settings['woocommerce'] = $this->wc_functions;
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
            $coupon_expire_time = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_ended');
            if (empty($coupon_expire_time)) {
                $expired_on_min = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time', 15);
                if (!empty($expired_on_min)) {
                    $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
                    $coupon_timer_apply_coupon = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon', 'automatically');
                    $this->wc_functions->setSession('rnoc_is_coupon_timer_time_started', '1');
                    $need_apply_coupon_code = apply_filters('rnoc_ei_popup_need_apply_coupon', true);
                    if ($coupon_timer_apply_coupon == "automatically" && $need_apply_coupon_code) {
                        $this->wc_functions->setSession('rnoc_coupon_timer_coupon_code', $coupon_code);
                    }
                }
            }
        }

        function autoApplyCouponCode()
        {
            $coupon_code = $this->wc_functions->getSession('rnoc_coupon_timer_coupon_code');
            if (!empty($coupon_code) && $this->wc_functions->isValidCoupon($coupon_code) && !$this->wc_functions->hasDiscount($coupon_code)) {
                $this->wc_functions->addDiscount($coupon_code);
                $this->wc_functions->removeSession('rnoc_coupon_timer_coupon_code');
            }
        }

        /**
         * @param $settings
         * @param $base_url
         * @param $add_on_slug
         */
        function premiumAddonTabContent($settings, $base_url, $add_on_slug)
        {
            if ($this->slug() == $add_on_slug) {
                $pages = $this->getPageLists();
                $coupon_codes = $this->getWooCouponCodes();
                ?>
                <input type="hidden" name="addon" value="ct">
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_coupon_timer'; ?>"><?php
                                esc_html_e('Enable Coupon timer?', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_coupon_timer'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_coupon_timer_1'; ?>"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_coupon_timer'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_coupon_timer'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_coupon_timer_0'; ?>"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_coupon_timer'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon'; ?>"><?php
                                esc_html_e('Choose the coupon code', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon'; ?>"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon'; ?>"
                                   placeholder="<?php esc_html_e('Search for a coupon code', RNOC_TEXT_DOMAIN); ?>"
                                   class="search-and-select-coupon" autocomplete="off"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon']) ?>">
                            <p class="description">
                                <b>Note</b>:This is a list of coupon codes from WooCommerce -> Coupons. If none found,
                                please create the coupon code in WooCommerce -> Coupons
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages'; ?>"><?php
                                esc_html_e('Custom pages to display the coupon timer', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <select multiple="multiple"
                                    name="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages[]'; ?>"
                                    class="rnoc-multi-select"
                                    id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages'; ?>">
                                <?php
                                if (!empty($pages)) {
                                    foreach ($pages as $key => $label) {
                                        ?>
                                        <option value="<?php echo $key ?>" <?php if (in_array($key, $settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages'])) {
                                            echo "selected";
                                        } ?>><?php echo $label ?></option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon'; ?>"><?php
                                esc_html_e('Apply coupon', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon_automatically'; ?>"
                                       value="automatically" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon'] == 'automatically') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Automatically', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon_manually'; ?>"
                                       value="manually" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon'] == 'manually') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Manually', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time'; ?>"><?php
                                esc_html_e('Time', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time'; ?>"
                                   type="number" class="regular-text number-only-field"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time'; ?>"
                                   value="<?php echo $settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time']; ?>">
                            <p class="description">
                                <?php
                                echo __('In minutes', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message'; ?>"><?php
                                esc_html_e('Coupon expiry message', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message']); ?>">
                            <p class="description">
                                <?php
                                echo __('Display this text when coupon expires.', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expired_text'; ?>"><?php
                                esc_html_e('Coupon timer expired text', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expired_text'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_expired_text'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_expired_text']); ?>">
                            <p class="description">
                                <?php
                                echo __('Display this text when coupon timer gets expired.', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload'; ?>"><?php
                                esc_html_e('Fix repeat reloading of page when coupon expires?', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload_1'; ?>"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload_0'; ?>"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('Top / bottom position display settings', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $top_position_name = RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings[0]'
                    ?>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_position'; ?>"><?php
                                esc_html_e('Enable Top / bottom position', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_position]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_position'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_position]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_position'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'top_bottom_position'; ?>"><?php
                                esc_html_e('Position', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'top_bottom_position]'; ?>"
                                       type="radio"
                                       value="top" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'top_bottom_position'] == 'top') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Top', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'top_bottom_position]'; ?>"
                                       type="radio"
                                       value="bottom" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'top_bottom_position'] == 'bottom') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Bottom', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_message'; ?>"><?php
                                esc_html_e('Message', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_message]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_message'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_message']); ?>">
                            <p class="description">
                                <?php
                                echo __('Use below short codes<br>{{coupon_code}} => to show coupon code<br>{{coupon_timer}} => remaining time timer', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format'; ?>"><?php
                                esc_html_e('Timer display format', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format']); ?>">
                            <p class="description">
                                <?php
                                echo __('Use below short codes<br>{{days}} => to show remaining days<br>{{hours}} => to show remaining hours<br>{{minutes}} - to show remaining minutes<br>{{seconds}} - to display remaining seconds', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_background'; ?>"><?php
                                esc_html_e('Background color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_background]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_background']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_color'; ?>"><?php
                                esc_html_e('Color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color'; ?>"><?php
                                esc_html_e('Coupon code color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color'; ?>"><?php
                                esc_html_e('Coupon timer color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_checkout_button'; ?>"><?php
                                esc_html_e('Enable checkout button', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_checkout_button]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_checkout_button'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_checkout_button]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_checkout_button'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'checkout_button_text'; ?>"><?php
                                esc_html_e('Call to action button text', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'checkout_button_text]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'checkout_button_text'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'checkout_button_text']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'checkout_button_color'; ?>"><?php
                                esc_html_e('Call to action button color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'checkout_button_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'checkout_button_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color'; ?>"><?php
                                esc_html_e('Call to action button background color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $top_position_name . '[' . RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings'][0][RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('Above cart display settings', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $above_cart_position_name = RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings[0]'
                    ?>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_position'; ?>"><?php
                                esc_html_e('Enable above cart position', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_position]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_position'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_position]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_position'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_message1'; ?>"><?php
                                esc_html_e('Message', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_message]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_message1'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_message']); ?>">
                            <p class="description">
                                <?php
                                echo __('Use below short codes<br>{{coupon_code}} => to show coupon code<br>{{coupon_timer}} => remaining time timer', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format1'; ?>"><?php
                                esc_html_e('Timer display format', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format1'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format']); ?>">
                            <p class="description">
                                <?php
                                echo __('Use below short codes<br>{{days}} => to show remaining days<br>{{hours}} => to show remaining hours<br>{{minutes}} - to show remaining minutes<br>{{seconds}} - to display remaining seconds', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_background'; ?>"><?php
                                esc_html_e('Background color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_background]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_background']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_color'; ?>"><?php
                                esc_html_e('Color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color'; ?>"><?php
                                esc_html_e('Coupon code color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color'; ?>"><?php
                                esc_html_e('Coupon timer color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_checkout_button'; ?>"><?php
                                esc_html_e('Enable checkout button', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_checkout_button]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_checkout_button'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_checkout_button]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_checkout_button'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'checkout_button_text1'; ?>"><?php
                                esc_html_e('Call to action button text', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'checkout_button_text]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'checkout_button_text1'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'checkout_button_text']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'checkout_button_color'; ?>"><?php
                                esc_html_e('Call to action button color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'checkout_button_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'checkout_button_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color'; ?>"><?php
                                esc_html_e('Call to action button background color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $above_cart_position_name . '[' . RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings'][0][RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('Below applied coupon display settings', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $below_discount_position_name = RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings[0]'
                    ?>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_position'; ?>"><?php
                                esc_html_e('Enable below applied coupon position', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $below_discount_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_position]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_position'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $below_discount_position_name . '[' . RNOC_PLUGIN_PREFIX . 'enable_position]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_position'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_message2'; ?>"><?php
                                esc_html_e('Message', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $below_discount_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_message]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_message2'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_message']); ?>">
                            <p class="description">
                                <?php
                                echo __('Use below short codes<br>{{coupon_code}} => to show coupon code<br>{{coupon_timer}} => remaining time timer', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format2'; ?>"><?php
                                esc_html_e('Timer display format', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $below_discount_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format2'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format']); ?>">
                            <p class="description">
                                <?php
                                echo __('Use below short codes<br>{{days}} => to show remaining days<br>{{hours}} => to show remaining hours<br>{{minutes}} - to show remaining minutes<br>{{seconds}} - to display remaining seconds', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_background'; ?>"><?php
                                esc_html_e('Background color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $below_discount_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_background]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_background']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_color'; ?>"><?php
                                esc_html_e('Color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $below_discount_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color'; ?>"><?php
                                esc_html_e('Coupon code color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $below_discount_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color'; ?>"><?php
                                esc_html_e('Coupon timer color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $below_discount_position_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <?php
            }
        }
    }
}