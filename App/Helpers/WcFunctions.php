<?php

namespace Rnoc\App\Helpers;
if (!defined('ABSPATH')) exit;

use Rnoc\App\Helpers\Settings as SettingHelper;

class WcFunctions
{


    public static function getProduct($product_id)
    {
        return function_exists('wc_get_product') ? wc_get_product(intval($product_id)) : array();
    }

    public static function getProductImageId($product)
    {
        if (self::isMethodExists($product, 'get_image_id')) {
            return $product->get_image_id();
        }
        return NULL;
    }

    public static function getProductImageSrc($product)
    {
        $image_id = self::getProductImageId($product);
        $image = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');
        $src = !empty($image) ? $image : wc_placeholder_img_src();
        return apply_filters('rnoc_get_product_image_src', $src, $product);
    }


    /**
     * check for method exists
     * @param $obj
     * @param $method
     * @return bool
     */
    public static function isMethodExists($obj, $method)
    {
        if (is_object($obj) && method_exists($obj, $method)) {
            return true;
        }
        return false;
    }


    /**
     * Get used coupons of order
     * @param $order
     * @return null
     */
    public static function getUsedCoupons($order)
    {
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '3.7.0', '<')) {
            return self::isMethodExists($order, 'get_used_coupons') ? $order->get_used_coupons() : NULL;
        }
        return self::isMethodExists($order, 'get_coupon_codes') ? $order->get_coupon_codes() : NULL;
    }


    /**
     * Get order meta from order object
     * @param $order
     * @param $meta_key
     * @return null
     * @since 2.2.5
     */
    public static function getOrderMeta($order, $meta_key)
    {
        $meta_value = null;
        if (is_object($order) && method_exists($order, 'get_meta')) {
            $meta_value = $order->get_meta($meta_key);
        }
        return $meta_value;
    }


    /**
     * Get Product url
     * @param $product
     * @return String|null
     */
    public static function getProductUrl($product)
    {
        return self::isMethodExists($product, 'get_permalink') ? $product->get_permalink() : '';
    }


    /**
     * get customer Email
     * @return bool
     */
    public static function getCustomerBillingEmail()
    {
        return self::isMethodExists(WC()->customer, 'get_billing_email') ? WC()->customer->get_billing_email() : false;
    }

    /**
     * get customer billing Email
     * @return string
     */
    public static function getCustomerEmail()
    {
        return !empty(self::getCustomerBillingEmail()) ? self::getCustomerBillingEmail() : (self::isMethodExists(WC()->customer, 'get_email') ? WC()->customer->get_email() : '');
    }

    /**
     * Get data from session
     * @param $key
     * @return array|string|null
     */
    public static function getSession($key)
    {
        if (empty($key))
            return NULL;
        if (self::isMethodExists(WC()->session, 'get')) {
            return WC()->session->get($key);
        }
        return NULL;
    }


    /**
     * get the client session details
     * @return mixed|void
     */
    public static function getClientSession()
    {
        $session = array(
            'cart' => self::getSession('cart'),
            'applied_coupons' => self::getSession('applied_coupons'),
            'chosen_shipping_methods' => self::getSession('chosen_shipping_methods'),
            'shipping_method_counts' => self::getSession('shipping_method_counts'),
            'chosen_payment_method' => self::getSession('chosen_payment_method'),
            'previous_shipping_methods' => self::getSession('previous_shipping_methods'),
        );
        return apply_filters('rnoc_get_client_session', $session);
    }

    /**
     * Get cart items
     * @return array
     */
    public static function getCart()
    {
        return self::isMethodExists(WC()->cart, 'get_cart') ? WC()->cart->get_cart() : array();
    }


    /**
     * Get cart items total tax
     * @return float
     */
    public static function getCartTotalTax()
    {
        return self::isMethodExists(WC()->cart, 'get_total_tax') ? WC()->cart->get_total_tax() : 0;
    }

    /**
     * Get cart items subtotal
     * @return array
     */
    public static function getCartSubTotal()
    {
        $subtotal = !empty(WC()->cart->subtotal) ? WC()->cart->subtotal : 0;
        if (self::isPriceExcludingTax()) {
            if (WC()->cart->subtotal_ex_tax) {
                $subtotal = WC()->cart->subtotal_ex_tax;
            }
        }
        return $subtotal;
    }

    public static function getAppliedDiscounts($order = null)
    {
        $discounts = array();
        if (!is_null($order)) {
            $applied_discounts = self::getUsedCoupons($order);
        } else {
            $applied_discounts = self::getAppliedCartCoupons();
        }
        $i = 1;
        if (!empty($applied_discounts)) {
            foreach ($applied_discounts as $applied_discount) {
                if (!$applied_discount instanceof \WC_Coupon) {
                    $applied_discount = new \WC_Coupon($applied_discount);
                }
                $discounts[] = array(
                    "id" => $i,
                    "usage_count" => self::getCouponUsageCount($applied_discount),
                    "code" => self::getCouponCode($applied_discount),
                    "date_expires" => self::getCouponDateExpires($applied_discount),
                    "discount_type" => self::getCouponDiscountType($applied_discount),
                    "created_at" => NULL,
                    "updated_at" => NULL
                );
            }
        }
        return $discounts;
    }

    /**
     * Get Applied coupons
     * @return array
     */
    public static function getAppliedCartCoupons()
    {
        return self::isMethodExists(WC()->cart, 'get_coupons') ? WC()->cart->get_coupons() : array();
    }

    /**
     * Get Coupon usage count
     * @param $coupon
     * @return integer
     */
    public static function getCouponUsageCount($coupon)
    {
        return self::isMethodExists($coupon, 'get_usage_count') ? $coupon->get_usage_count() : 0;
    }

    public static function getCouponDateExpires($coupon)
    {
        return self::isMethodExists($coupon, 'get_date_expires') ? $coupon->get_date_expires() : '';
    }

    public static function getCouponDiscountType($coupon)
    {
        return self::isMethodExists($coupon, 'get_discount_type') ? $coupon->get_discount_type() : '';
    }

    /**
     * Get cart items subtotal
     * @return float
     */
    public static function getCartTotalDiscount()
    {
        return self::isMethodExists(WC()->cart, 'get_discount_total') ? WC()->cart->get_discount_total() : 0;
    }

    /**
     * Get cart items total
     * @return float
     */
    public static function getCartTotalPrice()
    {
        return isset(WC()->cart->total) && !empty(WC()->cart->total) ? WC()->cart->total : 0;
    }

    /**
     * Force to calculate cart totals
     */
    function calculateCartTotals()
    {
        if ($this->isMethodExists(WC()->cart, 'calculate_totals')) {
            return WC()->cart->calculate_totals();
        }
        return NULL;
    }

    /**
     * Get cart items
     * @return array
     */
    public static function getCartTaxes()
    {
        return self::isMethodExists(WC()->cart, 'get_tax_totals') ? WC()->cart->get_tax_totals() : array();
    }


    /**
     * get Cart total from woocommerce
     * @return int|mixed
     */
    public static function getCartTotal()
    {
        return !empty(WC()->cart->subtotal) ? WC()->cart->subtotal : 0;
    }

    /**
     * Get Item name from Item object
     * @param $item
     * @return null
     */
    public static function getItemName($item)
    {
        if (self::isMethodExists($item, 'get_name')) {
            return apply_filters('rnoc_get_item_name', $item->get_name(), $item);
        }
        return NULL;
    }


    /**
     * get coupon code from coupon object
     * @param $coupon
     * @return null
     */
    public static function getCouponCode($coupon)
    {
        return self::isMethodExists($coupon, 'get_code') ? $coupon->get_Code() : NULL;
    }

    /**
     * get the default currency
     * @return string|null
     */
    public static function getDefaultCurrency()
    {
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }
        return NULL;
    }


    /**
     * Get cart items total tax
     * @return int|float
     */
    public static function getCartTaxTotal()
    {
        return !empty(WC()->cart->tax_total) ? WC()->cart->tax_total : 0;
    }

    /**
     * Get cart items shipping total
     * @return int|float
     */
    public static function getCartShippingTaxTotal()
    {
        return !empty(WC()->cart->shipping_tax_total) ? WC()->cart->shipping_tax_total : 0;
    }

    /**
     * Get cart items
     * @return int|float
     */
    public static function getCartDiscountTotal()
    {
        return !empty(WC()->cart->discount_cart) ? WC()->cart->discount_cart : 0;
    }

    /**
     * Get cart items
     * @return int|float
     */
    public static function getCartShippingTotal()
    {
        return !empty(WC()->cart->shipping_total) ? WC()->cart->shipping_total : 0;
    }

    /**
     * Get cart fees
     * @return array
     */
    public static function getCartFees()
    {
        return self::isMethodExists(WC()->cart, 'get_fees') ? WC()->cart->get_fees() : array();
    }

    /**
     * get cart item price
     * @param $product
     * @return float|int
     */
    public static function getCartItemPrice($product)
    {
        if (self::isPriceExcludingTax()) {
            $price = self::getPriceExcludingTax($product);
        } else {
            $price = self::getPriceIncludingTax($product);
        }
        return $price;
    }

    /**
     * get price excluding tax
     * @param $product
     * @return float|int
     */
    public static function getPriceExcludingTax($product)
    {
        return is_object($product) && function_exists('wc_get_price_excluding_tax') ? wc_get_price_excluding_tax($product) : 0;
    }

    /**
     * get price Including tax
     * @param $product
     * @return float|int
     */
    public static function getPriceIncludingTax($product)
    {
        return is_object($product) && function_exists('wc_get_price_including_tax') ? wc_get_price_including_tax($product) : 0;
    }

    /**
     * Check the price is including tax or excluding tax in cart and checkout page
     * @return bool
     */
    public static function isPriceExcludingTax()
    {
        return ('excl' == SettingHelper::getData('woocommerce_tax_display_cart'));
    }


    public static function checkSecuritykey($security_name)
    {
        $message = __('Security check failed', RNOC_TEXT_DOMAIN);
        if (empty($security_name)) wp_send_json_error($message);
        check_ajax_referer($security_name, 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error($message);
        }
    }

    /**
     * @return int|null
     */
    public static function getCurrentUserId()
    {
        return function_exists('get_current_user_id') ? get_current_user_id() : 0;
    }

    /**
     * @return \WP_User|null
     */
    public static function getCurrentUser()
    {
        return function_exists('wp_get_current_user') ? wp_get_current_user() : NULL;
    }

    /**
     * @return mixed|null
     */
    public static function getUserMeta($user_id, $key, $single)
    {
        return function_exists('get_user_meta') ? get_user_meta($user_id, $key, $single) : NULL;
    }

    public static function getWooPluginUrl()
    {
        return function_exists('WC') ? WC()->plugin_url() : NULL;
    }

    public static function getStoreCountry()
    {
        return function_exists('WC') ? WC()->countries->get_base_country() : NULL;
    }

    public static function getStoreState()
    {
        return function_exists('WC') ? WC()->countries->get_base_state() : NULL;
    }

    public static function getUserRoles($email)
    {
        if (empty($email)) return array();
        try {
            $user = get_user_by('email', sanitize_email($email));
            if ($user && is_object($user) && isset($user->roles)) {
                return ( array )$user->roles;
            }
        } catch (\Exception $e) {

        }
        return array();
    }

    /**
     * get price decimal separator
     * @return null
     */
    public static function priceDecimalSeparator()
    {
        return function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : NULL;
    }

    /**
     * get price decimal separator
     * @return null
     */
    public static function priceDecimals()
    {
        return function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
    }

    /**
     * get cart total
     * @return float|int|mixed
     */
    public static function getCartTotalForEdit()
    {
        if (self::isMethodExists(WC()->cart, 'get_total')) {
            return wc()->cart->get_total('edit');
        }
        return self::getCartTotal();
    }


    public static function getProductCategoryName($product_id)
    {
        if (empty($product_id)) return array();
        $terms = get_the_terms($product_id, 'product_cat');
        return (empty($terms) || is_wp_error($terms)) ? array() : wp_list_pluck($terms, 'name');
    }

    /**
     * Get Item sku from Item object
     * @param $item
     * @return null
     */
    public static function getItemSku($item)
    {
        return self::isMethodExists($item, 'get_sku') ? $item->get_sku() : NULL;
    }

    /**
     * get the default currency
     * @param $product_id
     * @return array
     */
    public static function getProductCategoryIds($product_id)
    {
        return function_exists('wc_get_product_term_ids') ? wc_get_product_term_ids($product_id, 'product_cat') : array();
    }

}
