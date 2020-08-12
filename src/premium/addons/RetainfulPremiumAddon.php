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
            add_action('wp_footer', array($this, 'enqueueScript'));
            add_action('woocommerce_before_cart', array($this, 'beforeCart'));
            add_filter('woocommerce_cart_totals_coupon_html', array($this, 'belowDiscount'), 100, 2);
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