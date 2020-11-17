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
            $need_coupon_timer = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'enable_coupon_timer', 0);
            $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
            add_action('wp_enqueue_scripts', array($this, 'enqueueScript'));
            if ($need_coupon_timer && !empty($coupon_code)) {
                add_action('woocommerce_add_to_cart', array($this, 'productAddedToCart'));
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
            $need_coupon_timer = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'enable_coupon_timer', 0);
            $code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
            $ended = $this->wc_functions->getSession('rnoc_is_coupon_timer_time_ended');
            $selected_pages = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages', array());
            if ($need_coupon_timer && !empty($code) && $ended !== 1 && $this->isValidPagesToDisplay($selected_pages)) {
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
            if (!wp_script_is('rnoc-premium')) {
                wp_enqueue_script('rnoc-premium', RNOCPREMIUM_PLUGIN_URL . 'assets/js/premium.min.js', array('jquery'), RNOC_VERSION);
            }
            wp_localize_script('rnoc-premium', 'rnoc_premium_ct', $premium_settings['coupon_timer']);
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