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
            return wc_get_order(intval($order_id));
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
            return wc_get_product(intval($product_id));
        }
        return array();
    }

    function getProductImageId($product)
    {
        if ($this->isMethodExists($product, 'get_image_id')) {
            return $product->get_image_id();
        }
        return NULL;
    }

    function getProductImageSrc($product)
    {
        $image_id = $this->getProductImageId($product);
        if (empty($image_id)) {
            $image = array("");
        } else {
            $image = wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail');
        }
        list($src) = $image;
        $src = !empty($src) ? $src : wc_placeholder_img_src();
        return apply_filters('rnoc_get_product_image_src', $src, $product);
    }

    function getProductImage($product)
    {
        if ($this->isMethodExists($product, 'get_image')) {
            return $product->get_image();
        }
        return NULL;
    }

    function getProductName($product)
    {
        if ($this->isMethodExists($product, 'get_formatted_name')) {
            return $product->get_formatted_name();
        }
        return NULL;
    }

    function getCartUrl()
    {
        if (function_exists('wc_get_cart_url')) {
            $cart_page_link = wc_get_cart_url();
        } elseif ($this->isMethodExists(WC()->cart, 'get_cart_url')) {
            $cart_page_link = WC()->cart->get_cart_url();
        } else {
            $cart_page_id = wc_get_page_id('cart');
            $cart_page_link = $cart_page_id ? get_permalink($cart_page_id) : '';
        }
        return apply_filters("rnoc_get_cart_page_url", $cart_page_link);
    }

    /**
     * check for method exists
     * @param $obj
     * @param $method
     * @return bool
     */
    function isMethodExists($obj, $method)
    {
        if (is_object($obj) && method_exists($obj, $method)) {
            return true;
        }
        return false;
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
        if ($this->isMethodExists($order, 'get_billing_email')) {
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
        if ($this->isMethodExists($order, 'update_status')) {
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
        if ($this->isMethodExists($order, 'has_status')) {
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
            return wc_get_order_item_meta(intval($item_id), $meta_key, true);
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
            if ($this->isMethodExists($order, 'get_used_coupons')) {
                return $order->get_used_coupons();
            }
        } else {
            if ($this->isMethodExists($order, 'get_coupon_codes')) {
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
        if ($this->isMethodExists($order, 'get_status')) {
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
        if ($this->isMethodExists($order, 'get_order_item_totals')) {
            return $order->get_order_item_totals();
        }
        return NULL;
    }

    /**
     * Get order meta from order object
     * @param $order
     * @param $meta_key
     * @return null
     * @since 2.2.5
     */
    function getOrderMeta($order, $meta_key)
    {
        $order_id = $this->getOrderId($order);
        return get_post_meta(intval($order_id), $meta_key, true);
    }

    /**
     * delete order meta from order object
     * @param $order_id
     * @param $meta_key
     * @return null
     */
    function deleteOrderMeta($order_id, $meta_key)
    {
        return delete_post_meta(intval($order_id), $meta_key);
    }

    /**
     * get the post meta
     * @param $post_id
     * @param $meta_key
     * @return bool
     */
    function getPostMeta($post_id, $meta_key)
    {
        return get_post_meta(intval($post_id), $meta_key, true);
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
        if (!empty($meta_key)) {
            return update_post_meta(intval($order_id), $meta_key, $meta_value);
        }
        return null;
    }

    /**
     * Get Order Id
     * @param $order
     * @return String|null
     */
    function getOrderId($order)
    {
        if ($this->isMethodExists($order, 'get_id')) {
            return $order->get_id();
        } elseif (is_object($order) && isset($order->id)) {
            return $order->id;
        }
        return NULL;
    }

    /**
     * Get site's default language
     * @return string
     */
    function getSiteDefaultLang()
    {
        $current_lang = 'en_US';
        if (function_exists('get_locale')) {
            $current_lang = get_locale();
            if (empty($current_lang) || $current_lang == 'en') {
                $current_lang = 'en_US';
            }
        }
        return $current_lang;
    }

    /**
     * Get Order Id
     * @param $order
     * @return String|null
     */
    function getOrderNumber($order)
    {
        if ($this->isMethodExists($order, 'get_order_number')) {
            return $order->get_order_number();
        }
        return $this->getOrderId($order);
    }

    /**
     * Get Order Id
     * @param $order
     * @return String|null
     */
    function getOrderSubTotal($order)
    {
        if ($this->isMethodExists($order, 'get_subtotal')) {
            return $order->get_subtotal();
        }
        return 0;
    }

    /**
     * Get Product url
     * @param $product
     * @return String|null
     */
    function getProductUrl($product)
    {
        if ($this->isMethodExists($product, 'get_permalink')) {
            return $product->get_permalink();
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
        if ($this->isMethodExists($item, 'get_subtotal')) {
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
        if ($this->isMethodExists($item, 'get_subtotal_tax')) {
            return $item->get_subtotal_tax();
        }
        return 0;
    }

    /**
     * Get total order discount
     * @param $order
     * @param $excluding
     * @return String|null
     */
    function getOrderDiscount($order, $excluding = true)
    {
        if ($this->isMethodExists($order, 'get_total_discount')) {
            return $order->get_total_discount($excluding);
        }
        return 0;
    }

    /**
     * Get order shipping total
     * @param $order
     * @param $context
     * @return String|null
     */
    function getOrderShippingTotal($order, $context = "edit")
    {
        if ($this->isMethodExists($order, 'get_shipping_total')) {
            return $order->get_shipping_total($context);
        }
        return 0;
    }

    /**
     * get order fees
     * @param $order
     * @return int|\WC_Order_Item[]|\WC_Order_item_Fee[]
     */
    function getOrderFees($order)
    {
        if ($this->isMethodExists($order, 'get_fees')) {
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
        if ($this->isMethodExists($order, 'add_order_note')) {
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
        if ($this->isMethodExists($order, 'is_paid')) {
            return $order->is_paid();
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
        if ($this->isMethodExists($order, 'needs_payment')) {
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
        if ($this->isMethodExists($order, 'get_checkout_payment_url')) {
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
        if ($this->isMethodExists($order, 'get_checkout_order_received_url')) {
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
        if ($this->isMethodExists($order, 'get_billing_first_name')) {
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
        if ($this->isMethodExists($order, 'get_billing_last_name')) {
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
        if ($this->isMethodExists($order, 'get_total')) {
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
        if ($this->isMethodExists($order, 'get_date_created')) {
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
        if ($this->isMethodExists($order, 'get_user_id')) {
            return $order->get_user_id();
        } elseif (is_object($order) && isset($order->user_id)) {
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
        if ($this->isMethodExists($order, 'get_user')) {
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
        return 2;
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
        if ($this->isMethodExists(WC()->session, 'set')) {
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
        if (isset(WC()->session) && !is_null(WC()->session) && is_object(WC()->session) && $this->isMethodExists(WC()->session, 'set_customer_session_cookie')) {
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
        if ($this->isMethodExists(WC()->customer, 'set_billing_email')) {
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
        if ($this->isMethodExists(WC()->customer, 'get_billing_email')) {
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
            if ($this->isMethodExists(WC()->customer, 'get_email')) {
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
        if ( is_null( WC()->session )) {
            return false;
        }
        if ($this->isMethodExists(WC()->session, 'has_session')) {
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
        if ($this->isMethodExists($woocommerce->cart, 'empty_cart')) {
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
            wc_paying_customer(intval($order_id));
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
        if ($this->isMethodExists(WC()->session, 'get')) {
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
        if ($this->isMethodExists(WC()->session, 'get_customer_id')) {
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
        if ($this->isMethodExists(WC()->cart, 'add_to_cart')) {
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
        if ($this->isMethodExists(WC()->cart, 'set_quantity')) {
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
        if ($this->isMethodExists(WC()->cart, 'is_empty')) {
            try {
                return WC()->cart->is_empty();
            } catch (\Exception $e) {
                return true;
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
        if ($this->isMethodExists(WC()->session, '__unset')) {
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
        if ($this->isMethodExists(WC()->cart, 'has_discount')) {
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
        if ($this->isMethodExists(WC()->cart, 'add_discount')) {
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
        if ($this->isMethodExists(WC()->cart, 'remove_coupon')) {
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
        if ($this->isMethodExists(WC()->cart, 'get_applied_coupons'))
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
        if ($this->isMethodExists(WC()->cart, 'get_cart')) {
            return WC()->cart->get_cart();
        }
        return array();
    }

    /**
     * get cart hash
     * @return string
     */
    function getCartHash()
    {
        if ($this->isMethodExists(WC()->cart, 'get_cart_hash')) {
            return WC()->cart->get_cart_hash();
        }
        return "";
    }

    /**
     * get cart total
     * @return float|int|mixed
     */
    function getCartTotalForEdit()
    {
        if ($this->isMethodExists(WC()->cart, 'get_total')) {
            return wc()->cart->get_total('edit');
        }
        return $this->getCartTotal();
    }

    /**
     * Get cart items total tax
     * @return float
     */
    function getCartTotalTax()
    {
        if ($this->isMethodExists(WC()->cart, 'get_total_tax')) {
            return WC()->cart->get_total_tax();
        }
        return 0;
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
        if ($this->isMethodExists(WC()->cart, 'get_coupons')) {
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
        if ($this->isMethodExists($coupon, 'get_usage_count')) {
            return $coupon->get_usage_count();
        }
        return 0;
    }

    function getCouponDateExpires($coupon)
    {
        if ($this->isMethodExists($coupon, 'get_date_expires')) {
            return $coupon->get_date_expires();
        }
        return '';
    }

    function getCouponDiscountType($coupon)
    {
        if ($this->isMethodExists($coupon, 'get_discount_type')) {
            return $coupon->get_discount_type();
        }
        return '';
    }

    /**
     * Get cart items subtotal
     * @return float
     */
    function getCartTotalDiscount()
    {
        if ($this->isMethodExists(WC()->cart, 'get_discount_total')) {
            return WC()->cart->get_discount_total();
        }
        return 0;
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
        if ($this->isMethodExists(WC()->cart, 'calculate_totals')) {
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
        if ($this->isMethodExists(WC()->cart, 'get_tax_totals')) {
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
            if ($this->isMethodExists(WC()->session, 'get_session_cookie')) {
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
        if ($this->isMethodExists($item, 'get_id')) {
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
        if ($this->isMethodExists($item, 'get_sku')) {
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
        if ($this->isMethodExists($item, 'get_title')) {
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
        if ($this->isMethodExists($item, 'get_name')) {
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
        if ($this->isMethodExists($item, 'get_price')) {
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
        if ($this->isMethodExists($item, 'get_category_ids')) {
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
        if ($this->isMethodExists($item, 'is_on_sale')) {
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
        if ($this->isMethodExists($coupon, 'get_code')) {
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
        $checkout_url = "";
        if (function_exists('wc_get_checkout_url')) {
            $checkout_url = wc_get_checkout_url();
        }
        return apply_filters('rnoc_get_checkout_url', $checkout_url);
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
        if ($this->isMethodExists($order, 'get_billing_first_name')) {
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
        if ($this->isMethodExists($order, 'get_currency')) {
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
        if ($this->isMethodExists($order, 'get_billing_last_name')) {
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
        if ($this->isMethodExists($order, 'get_billing_email')) {
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
        if ($this->isMethodExists($order, 'get_billing_address_1')) {
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
        if ($this->isMethodExists($order, 'get_billing_address_2')) {
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
        if ($this->isMethodExists($order, 'get_billing_city')) {
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
        if ($this->isMethodExists($order, 'get_billing_state')) {
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
        if ($this->isMethodExists($order, 'get_billing_country')) {
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
        if ($this->isMethodExists($order, 'get_billing_postcode')) {
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
        if ($this->isMethodExists($order, 'get_billing_company')) {
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
        if ($this->isMethodExists($order, 'get_billing_phone')) {
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
        if ($this->isMethodExists($order, 'get_shipping_first_name')) {
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
        if ($this->isMethodExists($order, 'get_shipping_last_name')) {
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
        if ($this->isMethodExists($order, 'get_shipping_address_1')) {
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
        if ($this->isMethodExists($order, 'get_formatted_shipping_address')) {
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
        if ($this->isMethodExists($order, 'get_shipping_address_2')) {
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
        if ($this->isMethodExists($order, 'get_shipping_city')) {
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
        if ($this->isMethodExists($order, 'get_shipping_state')) {
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
        if ($this->isMethodExists($order, 'get_shipping_country')) {
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
        if ($this->isMethodExists($order, 'get_shipping_postcode')) {
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
        if ($this->isMethodExists($order, 'get_shipping_company')) {
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
        if ($this->isMethodExists($order, 'get_total_tax')) {
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
        if ($this->isMethodExists($order, 'get_items')) {
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
        if ($this->isMethodExists(WC()->cart, 'get_fees')) {
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
        if (is_object($product) && function_exists('wc_get_price_excluding_tax')) {
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
        if (is_object($product) && function_exists('wc_get_price_including_tax')) {
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
            $coupon = new \WC_Coupon($coupon_code);
            if ($this->isMethodExists($coupon, "is_valid")) {
                return $coupon->is_valid();
            }
        }
        return false;
    }

    /**
     * @param $coupon string Coupon code to apply for the order
     * @param $order \WC_Order Order object
     * @return bool True or false
     */

    function applyCouponToOrder($coupon, $order)
    {
        if ($this->isValidCoupon($coupon) && $this->isMethodExists($order, "apply_coupon")) {
            $result = $order->apply_coupon($coupon);
            if($result === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * get total orders for billing emails
     * @param $email
     * @return int
     */
    function getCustomerTotalOrders($email)
    {
        if (!empty($email) && is_email($email)) {
            $customer_orders = $this->getCustomerOrdersByEmail($email);
            if (is_array($customer_orders)) {
                return count($customer_orders);
            }
        }
        return 0;
    }

    /**
     * get the total orders from session
     * @param $email
     * @return float
     */
    function getCustomerTotalOrdersFromSession($email)
    {
        $customer_total_orders = $this->getSession('rnoc_customer_total_orders');
        if (!is_null($customer_total_orders)) {
            return intval($customer_total_orders);
        } else {
            $total_orders = $this->getCustomerTotalOrders($email);
            $this->setSession('rnoc_customer_total_orders', $total_orders);
            return intval($total_orders);
        }
    }

    /**
     * remove customers total spent and total orders from session
     */
    function removeTotalOrdersAndSpentFromSession()
    {
        $this->removeSession('rnoc_customer_total_orders');
        $this->removeSession('rnoc_customer_total_spent');
    }

    /**
     * get the customer total spent
     * @param $email
     * @return float
     */
    function getCustomerTotalSpent($email)
    {
        $sum = 0;
        if (!empty($email) && is_email($email)) {
            $customer_orders = $this->getCustomerOrdersByEmail($email);
            if (is_array($customer_orders)) {
                foreach ($customer_orders as $order) {
                    if ($order instanceof \WC_Order) {
                        $sum = $sum + $this->getOrderTotal($order);
                    }
                }
            }
        }
        return floatval($sum);
    }

    /**
     * get the total spent from session
     * @param $email
     * @return float
     */
    function getCustomerTotalSpentFromSession($email)
    {
        $customer_total_spent = $this->getSession('rnoc_customer_total_spent');
        if (!is_null($customer_total_spent)) {
            return floatval($customer_total_spent);
        } else {
            $total_spent = $this->getCustomerTotalSpent($email);
            $this->setSession('rnoc_customer_total_spent', $total_spent);
            return floatval($total_spent);
        }
    }

    /**
     * @param $email
     * @return array[]
     */
    function getCustomerOrdersByEmail($email)
    {
        if (!empty($email) && is_email($email)) {
            $args = array(
                'customer' => $email,
            );
            $orders = $this->getOrdersList($args);
            return apply_filters('rnoc_get_customer_orders_by_email', $orders);
        } else {
            return array();
        }
    }
}