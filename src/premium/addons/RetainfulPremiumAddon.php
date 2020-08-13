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
            $encoded = "{
            checkout_url: '',
            cart_url: '',
            ei_popup: {
                enable: 'yes',
                show_for: 'everyone',
                is_user_logged_in: 'no',
                coupon_code: null,
                show_once_its_coupon_applied: 'no',
                applied_coupons: ['no'],
                show_popup: 'always',
                number_of_times_per_page: '1',
                cookie_expired_at: '1',
                redirect_url: '1',
                mobile: {
                    enable: 'yes',
                    time_delay: 'yes',
                    delay: '10',
                    scroll_distance: 'yes',
                    distance: '10'
                }
            },
            coupon_timer: {
                enable: 'yes',
                time_in_minutes: 15,
                code: 'YmJ4a3ljejI=',
                expiry_url: '',
                expired_text: 'Expired',
                top: {
                    enable: 'yes',
                    message: 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}',
                    timer: '{{minutes}}M {{seconds}}S',
                    display_on: 'bottom',
                    background: '#ffffff',
                    color: '#000000',
                    coupon_code_color: '#000000',
                    coupon_timer_color: '#000000',
                    enable_cta: 'yes',
                    cta_text: 'Checkout Now',
                    cta_color: '#ffffff',
                    cta_background: '#f27052',

                }, above_cart: {
                    enable: 'yes',
                    message: 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}',
                    timer: '{{minutes}}M {{seconds}}S',
                    background: '#ffffff',
                    color: '#000000',
                    coupon_code_color: '#000000',
                    coupon_timer_color: '#000000',
                    enable_cta: 'yes',
                    cta_text: 'Checkout Now',
                    cta_color: '#ffffff',
                    cta_background: '#f27052'
                }, below_discount: {
                    enable: 'yes',
                    message: 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}',
                    timer: '{{minutes}}M {{seconds}}S',
                    background: '#ffffff',
                    color: '#000000',
                    coupon_code_color: '#000000',
                    coupon_timer_color: '#000000'
                }
            }
        }";
            $decoded = json_decode($encoded);
            /*echo '<pre>';
            print_r($decoded);
            echo '</pre>';
            die;*/
            add_action('wp_footer', array($this, 'enqueueScript'));
            $need_coupon_timer = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'enable_coupon_timer', 1);
            $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon', NULL);
            if ($need_coupon_timer && !empty($coupon_code)) {
                add_action('woocommerce_before_cart', array($this, 'beforeCart'));
                add_filter('woocommerce_cart_totals_coupon_html', array($this, 'belowDiscount'), 100, 2);
            }
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

        function beforeCart()
        {
            echo '<div class="rnoc_before_cart_container"></div>';
        }

        function enqueueScript()
        {
            wp_enqueue_script('rnoc-premium', RNOCPREMIUM_PLUGIN_URL . 'assets/js/premium.js', array('jquery'), RNOC_VERSION);
            wp_enqueue_style('rnoc-premium', RNOCPREMIUM_PLUGIN_URL . 'assets/css/premium.css', array(), RNOC_VERSION);
        }
    }
}