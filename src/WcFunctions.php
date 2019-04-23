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

    function getProduct($product_id)
    {
        if (function_exists('wc_get_product')) {
            return wc_get_product($product_id);
        }
        return array();
    }

    function getProductImage($product)
    {
        if (method_exists($product, 'get_image')) {
            return $product->get_image();
        }
        return NULL;
    }

    function getProductName($product)
    {
        if (method_exists($product, 'get_formatted_name')) {
            return $product->get_formatted_name();
        }
        return NULL;
    }

    function getCartUrl()
    {
        if (function_exists('wc_get_cart_url')) {
            $cart_page_link = wc_get_cart_url();
        } else if (method_exists(WC()->cart, 'get_cart_url')) {
            $cart_page_link = WC()->cart->get_cart_url();
        } else {
            $cart_page_id = wc_get_page_id('cart');
            $cart_page_link = $cart_page_id ? get_permalink($cart_page_id) : '';
        }
        return $cart_page_link;
    }

    function getPage($page_id)
    {
        $page = NULL;
        if (function_exists('wc_get_page_id')) {
            $page = wc_get_page_id($page_id);
        } else if (function_exists('woocommerce_get_page_id')) {
            $page = woocommerce_get_page_id($page_id);
        }
        return $page;
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
        } elseif (isset($order->billing_email)) {
            return $order->billing_email;
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
        } elseif (isset($order->id)) {
            return $order->id;
        }
        return NULL;
    }

    /**
     * Set order note
     * @param $order
     * @param $note
     */
    function setOrderNote($order, $note)
    {
        if (method_exists($order, 'add_order_note')) {
            $order->add_order_note($note);
        }
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
        } elseif (isset($order->user_id)) {
            return $order->user_id;
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
     * @param $key
     * @param $value
     * @return bool
     */
    function setPHPSession($key, $value)
    {
        if (empty($key) || empty($value))
            return false;
        if (!session_id()) {
            session_start();
        }
        $_SESSION[$key] = $value;
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
     * Get data from session
     * @param $key
     * @return array|string|null
     */
    function getPHPSession($key)
    {
        if (empty($key))
            return NULL;
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        }
        return NULL;
    }

    function getSessionCustomerId()
    {
        if (method_exists(WC()->session, 'get_customer_id')) {
            return WC()->session->get_customer_id();
        }
        return NULL;
    }

    /**
     * Add to cart
     * @param $product_id
     * @param int $variation_id
     * @param int $quantity
     * @param array $variation
     * @param array $cart_item_data
     * @return bool|string
     */
    function addToCart($product_id, $variation_id = 0, $quantity = 1, $variation = array(), $cart_item_data = array())
    {
        if (method_exists(WC()->cart, 'add_to_cart')) {
            try {
                WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }
        return true;
    }

    /**
     * Set cart item quantity
     * @param $cart_key
     * @param int $quantity
     * @return bool|string
     */
    function setQuantity($cart_key, $quantity = 1)
    {
        if (method_exists(WC()->cart, 'set_quantity')) {
            try {
                WC()->cart->set_quantity($cart_key, $quantity);
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }
        return true;
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
     * Remove data from session
     * @param $key
     * @return bool
     */
    function removePHPSession($key)
    {
        if (empty($key))
            return false;
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
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
     * Add discount to cart
     * @param $discount_code
     * @return bool
     */
    function removeDiscount($discount_code)
    {
        if (empty($discount_code))
            return false;
        if (method_exists(WC()->cart, 'remove_coupon')) {
            return WC()->cart->remove_coupon($discount_code);
        }
        return false;
    }

    /**
     * get all coupons in cart
     * @return array|bool
     */
    function getAppliedCouponsOfCart()
    {
        if (method_exists(WC()->cart, 'get_applied_coupons'))
            return WC()->cart->get_applied_coupons();
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
     * Get session cart items
     * @return array
     */
    function getSessionCart()
    {
        if (function_exists('WC')) {
            return WC()->session->cart;
        } else {
            global $woocommerce;
            return $woocommerce->session->cart;
        }
    }

    /**
     * Get session cookie
     * @return array
     */
    function getSessionCookie()
    {
        if (function_exists('WC')) {
            if (method_exists(WC()->session, 'get_session_cookie')) {
                return WC()->session->get_session_cookie();
            } else {
                return NULL;
            }
        } else {
            global $woocommerce;
            return $woocommerce->session->get_session_cookie();
        }
    }

    /**
     * get Cart total from woocommerce
     * @return int|mixed
     */
    function getCartTotal()
    {
        if (method_exists(WC()->cart, 'get_cart')) {
            return WC()->cart->subtotal;
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
     * Check the item is in sale
     * @param $item
     * @return bool
     */
    function isProductInSale($item)
    {
        if (method_exists($item, 'is_on_sale')) {
            if ($item->is_on_sale())
                return true;
            else
                return false;
        }
        return false;
    }

    /**
     * @return array
     * Send all the products is sale
     */
    function getSaleProductIdsInCart()
    {
        $cart_items = $this->getCart();
        $product_ids = array();
        if (!empty($cart_items)) {
            foreach ($cart_items as $key => $item) {
                if ($this->isProductInSale($item['data'])) {
                    $product_ids[$key] = $this->getItemId($item['data']);
                }
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