<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

class WcFunctions
{
    static $wc_version = NULL;

    function __construct()
    {
        $path = 'woocommerce/woocommerce.php';
        if (!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $plugins = get_plugins();
        $wc_installed_version = NULL;
        if (isset($plugins[$path]['Version'])) {
            $wc_installed_version = $plugins[$path]['Version'];
        }
        self::$wc_version = $wc_installed_version;
    }

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

    function getAvailableOrderStatuses()
    {
        if (function_exists('wc_get_order_statuses')) {
            return wc_get_order_statuses();
        }
        return array();
    }

    function getProduct($product_id)
    {
        if (function_exists('wc_get_product')) {
            return wc_get_product($product_id);
        }
        return array();
    }

    function getProductImageId($product)
    {
        if (method_exists($product, 'get_image_id')) {
            return $product->get_image_id();
        }
        return NULL;
    }

    function getProductImageSrc($product)
    {
        $image_id = $this->getProductImageId($product);
        $image = wp_get_attachment_image_src($image_id);
        list($src) = $image;
        $src = !empty($src) ? $src : wc_placeholder_img_src();
        return apply_filters('rnoc_get_product_image_src', $src, $product);
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
        } elseif (method_exists(WC()->cart, 'get_cart_url')) {
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
     * Get order has particular status
     * @param $order
     * @param $status
     * @param $note
     * @return null
     */
    function setOrderStatus($order, $status, $note)
    {
        if (method_exists($order, 'update_status')) {
            return $order->update_status($status, $note);
        }
        return false;
    }

    /**
     * Get order Email form order object
     * @param $order
     * @param $status
     * @return null
     */
    function hasOrderStatus($order, $status)
    {
        if (method_exists($order, 'has_status')) {
            return $order->has_status($status);
        }
        return false;
    }

    /**
     * get the user by email
     * @param $email
     * @return bool|\WP_User|null
     */
    function getUserByEmail($email)
    {
        if (empty($email)) {
            return NULL;
        }
        if (function_exists('get_user_by')) {
            return get_user_by('email', $email);
        }
        return NULL;
    }

    /**
     * get order item meta
     * @param $item_id
     * @param $meta_key
     * @return mixed|null
     * @throws \Exception
     */
    function getOrderItemMeta($item_id, $meta_key)
    {
        if (empty($item_id) || empty($meta_key)) {
            return NULL;
        }
        if (function_exists('wc_get_order_item_meta')) {
            return wc_get_order_item_meta($item_id, $meta_key, true);
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
        if (version_compare(self::$wc_version, '3.7.0', '<')) {
            if (method_exists($order, 'get_used_coupons')) {
                return $order->get_used_coupons();
            }
        } else {
            if (method_exists($order, 'get_coupon_codes')) {
                return $order->get_coupon_codes();
            }
        }
        return NULL;
    }

    /**
     * Get status of order
     * @param $order
     * @return null
     */
    function getStatus($order)
    {
        if (method_exists($order, 'get_status')) {
            $order_status = $order->get_status();
            return strtolower($order_status);
        }
        return NULL;
    }

    /**
     * Get order items total
     * @param $order
     * @return null
     */
    function getOrderItemsTotal($order)
    {
        if (method_exists($order, 'get_order_item_totals')) {
            return $order->get_order_item_totals();
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
     * delete order meta from order object
     * @param $order_id
     * @param $meta_key
     * @return null
     */
    function deleteOrderMeta($order_id, $meta_key)
    {
        return delete_post_meta($order_id, $meta_key);
    }

    /**
     * get the post meta
     * @param $post_id
     * @param $meta_key
     * @return bool
     */
    function getPostMeta($post_id, $meta_key)
    {
        return get_post_meta($post_id, $meta_key, true);
    }

    /**
     * Get order meta from order object
     * @param $order_id
     * @param $meta_key
     * @param $meta_value
     * @return null
     */
    function setOrderMeta($order_id, $meta_key, $meta_value)
    {
        return update_post_meta($order_id, $meta_key, $meta_value);
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
     * Get Order Id
     * @param $order
     * @return String|null
     */
    function getOrderSubTotal($order)
    {
        if (method_exists($order, 'get_subtotal_to_display')) {
            return $order->get_subtotal_to_display();
        }
        return 0;
    }

    /**
     * Get Product url
     * @param $product
     * @param $item_details
     * @return String|null
     */
    function getProductUrl($product, $item_details = NULL)
    {
        if (method_exists($product, 'get_permalink')) {
            return $product->get_permalink($item_details);
        }
        return "";
    }

    /**
     * Get Item subtotal
     * @param $item
     * @return String|null
     */
    function getItemSubTotal($item)
    {
        if (method_exists($item, 'get_subtotal')) {
            return $item->get_subtotal();
        }
        return 0;
    }

    /**
     * Get Item subtotal tax
     * @param $item
     * @return String|null
     */
    function getItemTaxSubTotal($item)
    {
        if (method_exists($item, 'get_subtotal_tax')) {
            return $item->get_subtotal_tax();
        }
        return 0;
    }

    /**
     * Get Order Id
     * @param $order
     * @param $excluding
     * @return String|null
     */
    function getOrderDiscount($order, $excluding = true)
    {
        if (method_exists($order, 'get_total_discount')) {
            return $order->get_total_discount($excluding);
        }
        return 0;
    }

    /**
     * Get Order Id
     * @param $order
     * @param $context
     * @return String|null
     */
    function getOrderShippingTotal($order, $context = "edit")
    {
        if (method_exists($order, 'get_shipping_total')) {
            return $order->get_shipping_total($context);
        }
        return 0;
    }

    /**
     * Get Order Id
     * @param $order
     * @return String|null
     */
    function getOrderFees($order)
    {
        if (method_exists($order, 'get_fees')) {
            return $order->get_fees();
        }
        return 0;
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
     * is order paid
     * @param $order
     * @return bool
     */
    function isOrderPaid($order)
    {
        if (method_exists($order, 'is_paid')) {
            $order->is_paid();
        }
        return false;
    }

    /**
     * check if order needs payment or not
     * @param $order
     * @return null
     */
    function isOrderNeedPayment($order)
    {
        if (method_exists($order, 'needs_payment')) {
            return $order->needs_payment();
        }
        return NULL;
    }

    /**
     * check if order payment url
     * @param $order
     * @return null
     */
    function getOrderPaymentURL($order)
    {
        if (method_exists($order, 'get_checkout_payment_url')) {
            return $order->get_checkout_payment_url();
        }
        return NULL;
    }

    /**
     * check if order payment url
     * @param $order
     * @return null
     */
    function getOrderReceivedURL($order)
    {
        if (method_exists($order, 'get_checkout_order_received_url')) {
            return $order->get_checkout_order_received_url();
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
     * @param $format
     * @return null
     */
    function getOrderDate($order, $format = NULL)
    {
        if (method_exists($order, 'get_date_created')) {
            $date = $order->get_date_created();
            if (!is_null($format)) {
                $date = $date->format($format);
            }
            return $date;
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
     * get Order User
     * @param $order
     * @return null
     */
    function getOrderUser($order)
    {
        if (method_exists($order, 'get_user')) {
            return $order->get_user();
        }
        return NULL;
    }

    /**
     * get price decimal separator
     * @return null
     */
    function priceDecimalSeparator()
    {
        if (function_exists('wc_get_price_decimal_separator')) {
            return wc_get_price_decimal_separator();
        }
        return NULL;
    }

    /**
     * get price decimal separator
     * @return null
     */
    function priceThousandSeparator()
    {
        if (function_exists('wc_get_price_thousand_separator')) {
            return wc_get_price_thousand_separator();
        }
        return NULL;
    }

    /**
     * get price decimal separator
     * @return null
     */
    function priceDecimals()
    {
        if (function_exists('wc_get_price_decimals')) {
            return wc_get_price_decimals();
        }
        return NULL;
    }

    /**
     * Format the price
     * @param $price
     * @param $arg
     * @return string
     */
    function formatPrice($price, $arg = array())
    {
        if (function_exists('wc_price'))
            return wc_price($price, $arg);
        else
            return $price;
    }

    /**
     * Init the woocommerce session when it was not initlized
     */
    function initWoocommerceSession()
    {
        if (!$this->hasSession() && !defined('DOING_CRON')) {
            $this->setSessionCookie(true);
        }
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
        $this->initWoocommerceSession();
        if (method_exists(WC()->session, 'set')) {
            WC()->session->set($key, $value);
        }
        return true;
    }

    /**
     * set customer session cookie
     * @param $value
     * @return bool
     */
    function setSessionCookie($value)
    {
        if (method_exists(WC()->session, 'set_customer_session_cookie')) {
            WC()->session->set_customer_session_cookie($value);
        }
        return true;
    }

    /**
     * set customer Email
     * @param $value
     * @return bool|mixed
     */
    function setCustomerEmail($value)
    {
        if (method_exists(WC()->customer, 'set_billing_email')) {
            return WC()->customer->set_billing_email($value);
        }
        return false;
    }

    /**
     * get customer Email
     * @return bool
     */
    function getCustomerBillingEmail()
    {
        if (method_exists(WC()->customer, 'get_billing_email')) {
            return WC()->customer->get_billing_email();
        }
        return false;
    }

    /**
     * get customer billing Email
     * @return bool
     */
    function getCustomerEmail()
    {
        $email = $this->getCustomerBillingEmail();
        if (empty($email)) {
            if (method_exists(WC()->customer, 'get_email')) {
                return WC()->customer->get_email();
            } else {
                return false;
            }
        } else {
            return $email;
        }
    }

    /**
     * check woocommerce session has started
     * @return bool
     */
    function hasSession()
    {
        if (!isset(\WC()->session) && class_exists('WC_Session_Handler')) {
            \WC()->session = new \WC_Session_Handler();
            \WC()->session->init();
        }
        if (method_exists(WC()->session, 'has_session')) {
            return WC()->session->has_session();
        }
        return false;
    }

    /**
     * Empty the user cart
     * @return bool
     */
    function emptyUserCart()
    {
        global $woocommerce;
        if (method_exists($woocommerce->cart, 'empty_cart')) {
            $woocommerce->cart->empty_cart();
        }
        return true;
    }

    /**
     * Clear all notices
     */
    function clearWooNotices()
    {
        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }
    }

    /**
     * Order payment completed - This is a paying customer.
     * @param $order_id
     */
    function setCustomerPayingForOrder($order_id)
    {
        if (function_exists('wc_paying_customer')) {
            wc_paying_customer($order_id);
        }
    }

    /**
     * Orders list
     * @param $args
     * @return array|\stdClass|\WC_Order[]
     */
    function getOrdersList($args)
    {
        if (function_exists('wc_get_orders')) {
            return wc_get_orders($args);
        }
        return array();
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
        $this->startPHPSession();
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
        $this->startPHPSession();
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        }
        return NULL;
    }

    /**
     * Get session customer ID
     * @return int|null
     */
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
     * Check cart is empty or not
     * @return bool|string
     */
    function isCartEmpty()
    {
        if (method_exists(WC()->cart, 'is_empty')) {
            try {
                WC()->cart->is_empty();
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
        $this->startPHPSession();
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
     * get the client session details
     * @return mixed|void
     */
    public function getClientSession()
    {
        $session = array(
            'cart' => $this->getSession('cart'),
            'applied_coupons' => $this->getSession('applied_coupons'),
            'chosen_shipping_methods' => $this->getSession('chosen_shipping_methods'),
            'shipping_method_counts' => $this->getSession('shipping_method_counts'),
            'chosen_payment_method' => $this->getSession('chosen_payment_method'),
            'previous_shipping_methods' => $this->getSession('previous_shipping_methods'),
        );
        return apply_filters('rnoc_get_client_session', $session);
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
     * Get cart items
     * @return array
     */
    function getCartHash()
    {
        if (method_exists(WC()->cart, 'get_cart_hash')) {
            return WC()->cart->get_cart_hash();
        }
        return array();
    }

    /**
     * get cart total
     * @return float|int|mixed
     */
    function getCartTotalForEdit()
    {
        if (method_exists(WC()->cart, 'get_total')) {
            return wc()->cart->get_total('edit');
        }
        return $this->getCartTotal();
    }

    /**
     * Get cart items total tax
     * @return array
     */
    function getCartTotalTax()
    {
        if (method_exists(WC()->cart, 'get_total_tax')) {
            return WC()->cart->get_total_tax();
        }
        return array();
    }

    /**
     * Get cart items subtotal
     * @return array
     */
    function getCartSubTotal()
    {
        $subtotal = 0;
        if ($this->isPriceExcludingTax()) {
            if (WC()->cart->subtotal_ex_tax) {
                $subtotal = WC()->cart->subtotal_ex_tax;
            }
        } else {
            if (WC()->cart->subtotal) {
                $subtotal = WC()->cart->subtotal;
            }
        }
        return $subtotal;
    }

    function getAppliedDiscounts($order = null)
    {
        $discounts = array();
        if (!is_null($order)) {
            $applied_discounts = $this->getUsedCoupons($order);
        } else {
            $applied_discounts = $this->getAppliedCartCoupons();
        }
        $i = 1;
        if (!empty($applied_discounts)) {
            foreach ($applied_discounts as $applied_discount) {
                if (!$applied_discount instanceof \WC_Coupon) {
                    $applied_discount = new \WC_Coupon($applied_discount);
                }
                $discounts[] = array(
                    "id" => $i,
                    "usage_count" => $this->getCouponUsageCount($applied_discount),
                    "code" => $this->getCouponCode($applied_discount),
                    "date_expires" => $this->getCouponDateExpires($applied_discount),
                    "discount_type" => $this->getCouponDiscountType($applied_discount),
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
    function getAppliedCartCoupons()
    {
        if (method_exists(WC()->cart, 'get_coupons')) {
            return WC()->cart->get_coupons();
        }
        return array();
    }

    /**
     * Get Coupon usage count
     * @param $coupon
     * @return integer
     */
    function getCouponUsageCount($coupon)
    {
        if (method_exists($coupon, 'get_usage_count')) {
            return $coupon->get_usage_count();
        }
        return 0;
    }

    function getCouponDateExpires($coupon)
    {
        if (method_exists($coupon, 'get_date_expires')) {
            return $coupon->get_date_expires();
        }
        return '';
    }

    function getCouponDiscountType($coupon)
    {
        if (method_exists($coupon, 'get_discount_type')) {
            return $coupon->get_discount_type();
        }
        return '';
    }

    /**
     * Get cart items subtotal
     * @return array
     */
    function getCartTotalDiscount()
    {
        if (method_exists(WC()->cart, 'get_discount_total')) {
            return WC()->cart->get_discount_total();
        }
        return array();
    }

    /**
     * Get cart items total
     * @return float
     */
    function getCartTotalPrice()
    {
        if (isset(WC()->cart->total)) {
            return WC()->cart->total;
        }
        return 0;
    }

    /**
     * Force to calculate cart totals
     */
    function calculateCartTotals()
    {
        if (method_exists(WC()->cart, 'calculate_totals')) {
            return WC()->cart->calculate_totals();
        }
        return NULL;
    }

    /**
     * Get cart items
     * @return array
     */
    function getCartTaxes()
    {
        if (method_exists(WC()->cart, 'get_tax_totals')) {
            return WC()->cart->get_tax_totals();
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
        if (isset(WC()->cart->subtotal)) {
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
     * Get Item sku from Item object
     * @param $item
     * @return null
     */
    function getItemSku($item)
    {
        if (method_exists($item, 'get_sku')) {
            return $item->get_sku();
        }
        return NULL;
    }

    /**
     * Get Item title from Item object
     * @param $item
     * @return null
     */
    function getItemTitle($item)
    {
        if (method_exists($item, 'get_title')) {
            return $item->get_title();
        }
        return NULL;
    }

    /**
     * Get Item name from Item object
     * @param $item
     * @return null
     */
    function getItemName($item)
    {
        if (method_exists($item, 'get_name')) {
            return apply_filters('rnoc_get_item_name', $item->get_name(), $item);
        }
        return NULL;
    }

    /**
     * Get Item price from Item object
     * @param $item
     * @return null
     */
    function getItemPrice($item)
    {
        if (method_exists($item, 'get_price')) {
            return $item->get_price();
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

    /**
     * get coupon code from coupon object
     * @param $coupon
     * @return null
     */
    function getCouponCode($coupon)
    {
        if (method_exists($coupon, 'get_code')) {
            return $coupon->get_code();
        }
        return NULL;
    }

    /**
     * get the default currency
     * @return string|null
     */
    function getDefaultCurrency()
    {
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }
        return NULL;
    }

    /**
     * get the default currency
     * @param $product_id
     * @return array
     */
    function getProductCategoryIds($product_id)
    {
        if (function_exists('wc_get_product_term_ids')) {
            return wc_get_product_term_ids($product_id, 'product_cat');
        }
        return array();
    }

    /**
     * get the checkout url
     * @return string|null
     */
    function getCheckoutUrl()
    {
        if (function_exists('wc_get_checkout_url')) {
            return wc_get_checkout_url();
        }
        return NULL;
    }

    /**
     * get the shop url
     * @return string|null
     */
    function getShopUrl()
    {
        if (function_exists('wc_get_page_id')) {
            return get_permalink(wc_get_page_id('shop'));
        }
        return NULL;
    }

    /**
     * start the PHP session securely
     */
    function startPHPSession()
    {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }

    //Check with woo email customizer

    /**
     * Get User Last name
     * @param $order
     * @return null
     */
    function getBillingFirstName($order)
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
    function getOrderCurrency($order)
    {
        if (method_exists($order, 'get_currency')) {
            return $order->get_currency();
        }
        return NULL;
    }

    /**
     * Get User Last name
     * @param $order
     * @return null
     */
    function getBillingLastName($order)
    {
        if (method_exists($order, 'get_billing_last_name')) {
            return $order->get_billing_last_name();
        }
        return NULL;
    }

    /**
     * Get billing Country
     * @param $order
     * @return null
     */
    function getBillingEmail($order)
    {
        if (method_exists($order, 'get_billing_email')) {
            return $order->get_billing_email();
        }
        return NULL;
    }

    /**
     * Get billing address 1
     * @param $order
     * @return null
     */
    function getBillingAddressOne($order)
    {
        if (method_exists($order, 'get_billing_address_1')) {
            return $order->get_billing_address_1();
        }
        return NULL;
    }

    /**
     * Get billing address 2
     * @param $order
     * @return null
     */
    function getBillingAddressTwo($order)
    {
        if (method_exists($order, 'get_billing_address_2')) {
            return $order->get_billing_address_2();
        }
        return NULL;
    }

    /**
     * Get billing City
     * @param $order
     * @return null
     */
    function getBillingCity($order)
    {
        if (method_exists($order, 'get_billing_city')) {
            return $order->get_billing_city();
        }
        return NULL;
    }

    /**
     * Get billing City
     * @param $order
     * @return null
     */
    function getBillingState($order)
    {
        if (method_exists($order, 'get_billing_state')) {
            return $order->get_billing_state();
        }
        return NULL;
    }

    /**
     * Get billing Country
     * @param $order
     * @return null
     */
    function getBillingCountry($order)
    {
        if (method_exists($order, 'get_billing_country')) {
            return $order->get_billing_country();
        }
        return NULL;
    }

    /**
     * Get billing Post code
     * @param $order
     * @return null
     */
    function getBillingPostCode($order)
    {
        if (method_exists($order, 'get_billing_postcode')) {
            return $order->get_billing_postcode();
        }
        return NULL;
    }

    /**
     * Get billing company
     * @param $order
     * @return null
     */
    function getBillingCompany($order)
    {
        if (method_exists($order, 'get_billing_company')) {
            return $order->get_billing_company();
        }
        return NULL;
    }

    /**
     * Get billing phone
     * @param $order
     * @return null
     */
    function getBillingPhone($order)
    {
        if (method_exists($order, 'get_billing_phone')) {
            return $order->get_billing_phone();
        }
        return NULL;
    }

    /**
     * Get Shipping first name
     * @param $order
     * @return null
     */
    function getShippingFirstName($order)
    {
        if (method_exists($order, 'get_shipping_first_name')) {
            return $order->get_shipping_first_name();
        }
        return NULL;
    }

    /**
     * Get shipping Last name
     * @param $order
     * @return null
     */
    function getShippingLastName($order)
    {
        if (method_exists($order, 'get_shipping_last_name')) {
            return $order->get_shipping_last_name();
        }
        return NULL;
    }

    /**
     * Get shipping address 1
     * @param $order
     * @return null
     */
    function getShippingAddressOne($order)
    {
        if (method_exists($order, 'get_shipping_address_1')) {
            return $order->get_shipping_address_1();
        }
        return NULL;
    }

    /**
     * Get shipping address
     * @param $order
     * @return null
     */
    function getShippingAddressFormatted($order)
    {
        if (method_exists($order, 'get_formatted_shipping_address')) {
            return $order->get_formatted_shipping_address();
        }
        return NULL;
    }

    /**
     * Get shipping address 2
     * @param $order
     * @return null
     */
    function getShippingAddressTwo($order)
    {
        if (method_exists($order, 'get_shipping_address_2')) {
            return $order->get_shipping_address_2();
        }
        return NULL;
    }

    /**
     * Get shipping City
     * @param $order
     * @return null
     */
    function getShippingCity($order)
    {
        if (method_exists($order, 'get_shipping_city')) {
            return $order->get_shipping_city();
        }
        return NULL;
    }

    /**
     * Get shipping City
     * @param $order
     * @return null
     */
    function getShippingState($order)
    {
        if (method_exists($order, 'get_shipping_state')) {
            return $order->get_shipping_state();
        }
        return NULL;
    }

    /**
     * Get shipping Country
     * @param $order
     * @return null
     */
    function getShippingCountry($order)
    {
        if (method_exists($order, 'get_shipping_country')) {
            return $order->get_shipping_country();
        }
        return NULL;
    }

    /**
     * Get shipping Post code
     * @param $order
     * @return null
     */
    function getShippingPostCode($order)
    {
        if (method_exists($order, 'get_shipping_postcode')) {
            return $order->get_shipping_postcode();
        }
        return NULL;
    }

    /**
     * Get shipping company
     * @param $order
     * @return null
     */
    function getShippingCompany($order)
    {
        if (method_exists($order, 'get_shipping_company')) {
            return $order->get_shipping_company();
        }
        return NULL;
    }

    /**
     * Get Order total tax
     * @param $order
     * @return null
     */
    function getOrderTotalTax($order)
    {
        if (method_exists($order, 'get_total_tax')) {
            return $order->get_total_tax();
        }
        return NULL;
    }

    /**
     * Get order order note
     * @param $order
     * @return array
     */
    function getOrderItems($order)
    {
        if (method_exists($order, 'get_items')) {
            return $order->get_items();
        }
        return array();
    }

    /**
     * Get cart items total tax
     * @return int|float
     */
    function getCartTaxTotal()
    {
        if (isset(WC()->cart->tax_total)) {
            return WC()->cart->tax_total;
        }
        return 0;
    }

    /**
     * Get cart items shipping total
     * @return int|float
     */
    function getCartShippingTaxTotal()
    {
        if (isset(WC()->cart->shipping_tax_total)) {
            return WC()->cart->shipping_tax_total;
        }
        return 0;
    }

    /**
     * Get cart items
     * @return int|float
     */
    function getCartDiscountTotal()
    {
        if (isset(WC()->cart->discount_cart)) {
            return WC()->cart->discount_cart;
        }
        return 0;
    }

    /**
     * Get cart items
     * @return int|float
     */
    function getCartShippingTotal()
    {
        if (isset(WC()->cart->shipping_total)) {
            return WC()->cart->shipping_total;
        }
        return 0;
    }

    /**
     * Get cart fees
     * @return array
     */
    function getCartFees()
    {
        if (method_exists(WC()->cart, 'get_fees')) {
            return WC()->cart->get_fees();
        }
        return array();
    }

    /**
     * get cart item price
     * @param $product
     * @return float|int
     */
    function getCartItemPrice($product)
    {
        if ($this->isPriceExcludingTax()) {
            $price = $this->getPriceExcludingTax($product);
        } else {
            $price = $this->getPriceIncludingTax($product);
        }
        return $price;
    }

    /**
     * get price excluding tax
     * @param $product
     * @return float|int
     */
    function getPriceExcludingTax($product)
    {
        $price = 0;
        if (function_exists('wc_get_price_excluding_tax')) {
            $price = wc_get_price_excluding_tax($product);
        }
        return $price;
    }

    /**
     * get price Including tax
     * @param $product
     * @return float|int
     */
    function getPriceIncludingTax($product)
    {
        $price = 0;
        if (function_exists('wc_get_price_including_tax')) {
            $price = wc_get_price_including_tax($product);
        }
        return $price;
    }

    /**
     * Check the price is including tax or excluding tax in cart and checkout page
     * @return bool
     */
    function isPriceExcludingTax()
    {
        return ('excl' == get_option('woocommerce_tax_display_cart'));
    }

    function isValidCoupon($coupon_code)
    {
        if (class_exists('WC_Coupon')) {
            $the_coupon = new \WC_Coupon($coupon_code);
            if ($the_coupon->is_valid()) {
                return true;
            }
        }
        return false;
    }
}