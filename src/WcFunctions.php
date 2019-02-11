<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

class WcFunctions
{
    /**
     * Get order object
     * @param $order_id
     * @return bool|\WC_Order|null
     */
    function getOrder($order_id)
    {
        if (function_exists('wc_get_order')) {
            return wc_get_order($order_id);
        }
        return NULL;
    }

    /**
     * Get order Email form order object
     * @param $order
     * @return null
     */
    function getOrderEmail($order)
    {
        if (method_exists($order, 'get_billing_email')) {
            return $order->get_billing_email();
        }
        return NULL;
    }

    /**
     * Get used coupons of order
     * @param $order
     * @return null
     */
    function getUsedCoupons($order)
    {
        if (method_exists($order, 'get_used_coupons')) {
            return $order->get_used_coupons();
        }
        return NULL;
    }

    /**
     * Get order meta from order object
     * @param $order
     * @param $meta_key
     * @return null
     */
    function getOrderMeta($order, $meta_key)
    {
        if (method_exists($order, 'get_meta')) {
            return $order->get_meta($meta_key);
        }
        return NULL;
    }

    /**
     * Get Order Id
     * @param $order
     * @return String|null
     */
    function getOrderId($order)
    {
        if (method_exists($order, 'get_id')) {
            return $order->get_id();
        }
        return NULL;
    }

    /**
     * Get User Last name
     * @param $order
     * @return null
     */
    function getOrderFirstName($order)
    {
        if (method_exists($order, 'get_billing_first_name')) {
            return $order->get_billing_first_name();
        }
        return NULL;
    }

    /**
     * Get User Last name
     * @param $order
     * @return null
     */
    function getOrderLastName($order)
    {
        if (method_exists($order, 'get_billing_last_name')) {
            return $order->get_billing_last_name();
        }
        return NULL;
    }

    /**
     * Get Order Total
     * @param $order
     * @return null
     */
    function getOrderTotal($order)
    {
        if (method_exists($order, 'get_total')) {
            return $order->get_total();
        }
        return NULL;
    }

    /**
     * get Ordered Date
     * @param $order
     * @return null
     */
    function getOrderDate($order)
    {
        if (method_exists($order, 'get_date_created')) {
            return $order->get_date_created();
        }
        return NULL;
    }

    /**
     * get Order User Id
     * @param $order
     * @return null
     */
    function getOrderUserId($order)
    {
        if (method_exists($order, 'get_user_id')) {
            return $order->get_user_id();
        }
        return NULL;
    }

    /**
     * Format the price
     * @param $price
     * @return string
     */
    function formatPrice($price)
    {
        if (function_exists('wc_price'))
            return wc_price($price);
        else
            return $price;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    function setSession($key, $value)
    {
        if (empty($key) || empty($value))
            return false;
        if (method_exists(WC()->session, 'set')) {
            WC()->session->set($key, $value);
        }
        return true;
    }

    /**
     * Get data from session
     * @param $key
     * @return array|string|null
     */
    function getSession($key)
    {
        if (empty($key))
            return NULL;
        if (method_exists(WC()->session, 'get')) {
            return WC()->session->get($key);
        }
        return NULL;
    }

    /**
     * Remove data from session
     * @param $key
     * @return bool
     */
    function removeSession($key)
    {
        if (empty($key))
            return false;
        if (method_exists(WC()->session, '__unset')) {
            WC()->session->__unset($key);
        }
        return true;
    }

    /**
     * Check the coupon code is available on cart
     * @param $discount_code
     * @return bool
     */
    function hasDiscount($discount_code)
    {
        if (empty($discount_code))
            return false;
        if (method_exists(WC()->cart, 'has_discount')) {
            return WC()->cart->has_discount($discount_code);
        }
        return false;
    }

    /**
     * Add discount to cart
     * @param $discount_code
     * @return bool
     */
    function addDiscount($discount_code)
    {
        if (empty($discount_code))
            return false;
        if (method_exists(WC()->cart, 'add_discount')) {
            return WC()->cart->add_discount($discount_code);
        }
        return false;
    }

    /**
     * Get cart items
     * @return array
     */
    function getCart()
    {
        if (method_exists(WC()->cart, 'get_cart')) {
            return WC()->cart->get_cart();
        }
        return array();
    }

    /**
     * get Cart total from woocommerce
     * @return int|mixed
     */
    function getCartTotal()
    {
        if (method_exists(WC()->cart, 'get_cart')) {
            return WC()->cart->total;
        }
        return 0;
    }

    /**
     * Get Item Id from Item object
     * @param $item
     * @return null
     */
    function getItemId($item)
    {
        if (method_exists($item, 'get_id')) {
            return $item->get_id();
        }
        return NULL;
    }

    /**
     * Get category Id of product
     * @param $item
     * @return null
     */
    function getCategoryId($item)
    {
        if (method_exists($item, 'get_category_ids')) {
            return $item->get_category_ids();
        }
        return NULL;
    }

    /**
     * Get the product ids list from cart
     * @return array
     */
    function getProductIdsInCart()
    {
        $cart_items = $this->getCart();
        $product_ids = array();
        if (!empty($cart_items)) {
            foreach ($cart_items as $key => $item) {
                $product_ids[$key] = $this->getItemId($item['data']);
            }
        }
        return array_unique($product_ids);
    }

    /**
     * Get list of category ids from cart
     * @return array
     */
    function getCategoryIdsOfProductInCart()
    {
        $cart_items = $this->getCart();
        $category_ids = array();
        if (!empty($cart_items)) {
            foreach ($cart_items as $item) {
                $categories = $this->getCategoryId($item['data']);
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        $category_ids[] = $category;
                    }
                }
            }
        }
        return array_unique($category_ids);
    }
}