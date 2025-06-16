<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Admin\Settings;

class OrderCoupon
{
    public $wc_functions, $admin;
    protected static $applied_coupons = NULL;

    function __construct()
    {
        $this->wc_functions = new WcFunctions();
        $this->admin = new Settings();
    }

    /**
     * Add settings link
     * @param $links
     * @return array
     */
    function pluginActionLinks($links)
    {
        if ($this->admin->runAbandonedCartExternally()) {
            $action_links = array(
                'license' => '<a href="' . admin_url('admin.php?page=retainful_license') . '">' . __('Connection', 'retainful-next-order-coupon-for-woocommerce') . '</a>',
            );
        } else {
            $action_links = array(
                'abandoned_carts' => '<a href="' . admin_url('admin.php?page=retainful_abandoned_cart') . '">' . __('Abandoned carts', 'retainful-next-order-coupon-for-woocommerce') . '</a>',
                'settings' => '<a href="' . admin_url('admin.php?page=retainful_settings') . '">' . __('Settings', 'retainful-next-order-coupon-for-woocommerce') . '</a>',
                'license' => '<a href="' . admin_url('admin.php?page=retainful_license') . '">' . __('License', 'retainful-next-order-coupon-for-woocommerce') . '</a>',
            );
        }
        return array_merge($action_links, $links);
    }


    /**
     * Remove Coupon from session
     */
    function removeCouponFromSession()
    {
        $coupon_code = $this->wc_functions->getSession('retainful_coupon_code');
        if (!empty($coupon_code)) {
            $this->wc_functions->removeSession('retainful_coupon_code');
        }
    }

    /**
     * Check that coupon is validated in retainful usage restriction
     * @param $coupon_code
     * @return bool
     */
    public function checkCouponBeforeCouponApply($coupon_code)
    {
        if (empty($coupon_code))
            return false;
        $return = array();
        $coupon_details = $this->isValidCoupon($coupon_code, null, array('rnoc_order_coupon'));
        if (!empty($coupon_details)) {
            $usage_restrictions = $this->admin->getUsageRestrictions();
            //Return true if there is any usage restriction
            if (empty($usage_restrictions))
                return true;
            //Check for coupon expired or not
            $coupon_expiry_date = get_post_meta($coupon_details->ID, 'coupon_expired_on', true);
            if (!empty($coupon_expiry_date) && current_time('timestamp', true) > strtotime($coupon_expiry_date)) {
                array_push($return, false);
            }
            $cart_total = $this->wc_functions->getCartTotal();
            //Check for minimum spend
            $minimum_spend = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend']) && $usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend'] > 0) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend'] : '';
            if (!empty($minimum_spend) && $cart_total < $minimum_spend) {
                array_push($return, false);
            }
            //Check for maximum spend
            $maximum_spend = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend']) && $usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend'] > 0) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend'] : '';
            if (!empty($maximum_spend) && $cart_total > $maximum_spend) {
                array_push($return, false);
            }
            $products_in_cart = $this->wc_functions->getProductIdsInCart();
            //Check the cart having only sale items
            $sale_products_in_cart = $this->wc_functions->getSaleProductIdsInCart();
            if ((count($sale_products_in_cart) >= count($products_in_cart)) && isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_sale_items'])) {
                array_push($return, false);
            }
            //Check for must in cart products
            $must_in_cart_products = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'products'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'products'] : array();
            if (!empty($must_in_cart_products) && count(array_intersect($must_in_cart_products, $products_in_cart)) == 0) {
                array_push($return, false);
            }
            $categories_in_cart = $this->wc_functions->getCategoryIdsOfProductInCart();
            //Check for must in categories of cart
            $must_in_cart_categories = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'product_categories'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'product_categories'] : array();
            if (!empty($must_in_cart_categories) && count(array_intersect($must_in_cart_categories, $categories_in_cart)) == 0) {
                array_push($return, false);
            }
            //Check for must in cart products and exclude products in cart are given
            $must_not_in_cart_products = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_products'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_products'] : array();
            $must_not_in_cart_categories = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'] : array();
            if (array_intersect($must_not_in_cart_products, $must_in_cart_products) || array_intersect($must_in_cart_categories, $must_not_in_cart_categories)) {
                array_push($return, false);
            }
        } else {
            $this->removeCouponFromSession();
        }
        if (in_array(false, $return))
            return false;
        return true;
    }


    /**
     * Check the given Coupon code is valid or not
     * @param $coupon
     * @param null $order
     * @param string[] $coupon_type
     * @return String|null
     */
    function isValidCoupon($coupon, $order = NULL, $coupon_type = array('rnoc_order_coupon', 'shop_coupon'))
    {
        $coupon_details = $this->getCouponByCouponCode($coupon, $coupon_type);
        if (!empty($coupon_details) && $coupon_details->post_status == "publish") {
            $coupon_only_for = $this->admin->couponFor();
            $current_user_id = $current_email = '';
            if ($coupon_only_for != 'all') {
                if (!empty($order)) {
                    $current_user_id = $this->wc_functions->getOrderUserId($order);
                    if ($coupon_only_for != 'login_users') {
                        $current_email = $this->wc_functions->getOrderEmail($order);
                    }
                } else {
                    $current_user_id = get_current_user_id();
                    if ($coupon_only_for != 'login_users') {
                        $current_email = $this->getCurrentEmail();
                    }
                }
            }
            if ($coupon_only_for == 'all') {
                return $coupon_details;
            } else if ($coupon_only_for == 'login_users') {
                $user_id = get_post_meta($coupon_details->ID, 'user_id', true);
                if ($current_user_id == $user_id) return $coupon_details;
            } else {
                $user_id = get_post_meta($coupon_details->ID, 'user_id', true);
                $email = get_post_meta($coupon_details->ID, 'email', true);
                if (!empty($current_user_id) || !empty($current_email)) {
                    if ($current_user_id == $user_id || $current_email == $email)
                        return $coupon_details;
                } else if (empty($current_user_id) && empty($current_email)) {
                    return $coupon_details;
                }
            }
        }
        return NULL;
    }




    /**
     * Get Order Email
     * @return mixed|string|null
     */
    function getCurrentEmail()
    {
        $postData = isset($_REQUEST['post_data']) ? sanitize_text_field(wp_unslash($_REQUEST['post_data'])) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $postDataArray = array();
        if (is_string($postData) && $postData != '') {
            parse_str($postData, $postDataArray);
        }
        $postBillingEmail = isset($_REQUEST['billing_email']) ? sanitize_email(wp_unslash($_REQUEST['billing_email'])) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($postBillingEmail != '') {
            $postDataArray['billing_email'] = $postBillingEmail;
        }
        if (!get_current_user_id()) {
            $order_id = isset($_REQUEST['order-received']) ? sanitize_key($_REQUEST['order-received']) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ($order_id) {
                $order = $this->wc_functions->getOrder($order_id);
                $postDataArray['billing_email'] = $this->wc_functions->getOrderEmail($order);
            }
        }
        $user_email = '';
        if (isset($postDataArray['billing_email']) && $postDataArray['billing_email'] != '') {
            $user_email = $postDataArray['billing_email'];
        } else if ($user_id = get_current_user_id()) {
            $user_email = get_user_meta($user_id, 'billing_email', true);
            if ($user_email != '' && !empty($user_email)) {
                return $user_email;
            } else {
                $user_details = get_userdata($user_id);
                if (isset($user_details->data->user_email) && $user_details->data->user_email != '') {
                    $user_email = $user_details->data->user_email;
                    return $user_email;
                }
            }
        }
        return sanitize_email($user_email);
    }


    /**
     * @param $coupon_code
     * @param string[] $coupon_type
     * @return Object|null
     */
    function getCouponByCouponCode($coupon_code, $coupon_type = array('rnoc_order_coupon', 'shop_coupon'))
    {
        $coupon_code = sanitize_text_field($coupon_code);
        if (empty($coupon_code)) return NULL;
        $post_args = array('post_type' => $coupon_type, 'numberposts' => '1', 'title' => strtoupper($coupon_code));
        $posts = get_posts($post_args);
        if (!empty($posts)) {
            foreach ($posts as $post) {
                if (strtoupper($post->post_title) == strtoupper($coupon_code)) {
                    return $post;
                }
            }
        }
        return NULL;
    }
}