<?php

namespace Rnoc\Retainful\Api\AbandonedCart;

use Rnoc\Retainful\OrderCoupon;

class Order extends RestApi
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Get the customer details
     * @param $order
     * @return array
     */
    function getCustomerDetails($order)
    {
        if ($user_id = self::$woocommerce->getOrderUserId($order)) {
            $user_data = self::$woocommerce->getOrderUser($order);
            $created_at = $updated_at = strtotime($user_data->user_registered);
        } else {
            $user_id = 0;
            $created_at = $updated_at = current_time('timestamp', true);
        }
        $email = self::$woocommerce->getBillingEmail($order);
        $user_info = array(
            'id' => $user_id,
            'email' => $email,
            'phone' => self::$woocommerce->getBillingPhone($order),
            'state' => self::$woocommerce->getBillingState($order),
            'currency' => NULL,
            'last_name' => self::$woocommerce->getBillingFirstName($order),
            'created_at' => $this->formatToIso8601($created_at),
            'first_name' => self::$woocommerce->getBillingLastName($order),
            'updated_at' => $this->formatToIso8601($updated_at),
            'total_spent' => self::$woocommerce->getCustomerTotalSpent($email),
            'orders_count' => self::$woocommerce->getCustomerTotalOrders($email),
            'last_order_id' => NULL,
            'verified_email' => true,
            'last_order_name' => NULL,
            'accepts_marketing' => true,
        );
        return $user_info;
    }

    /**
     * get the cart tax details
     * @return array
     */
    function getOrderTaxDetails()
    {
        //$tax_details = self::$woocommerce->getCartTaxes();
        $taxes = array();
        /*if (!empty($tax_details)) {
            foreach ($tax_details as $key => $tax_detail) {
                $taxes[] = array(
                    'rate' => 0,
                    'price' => (isset($tax_detail->amount)) ? $tax_detail->amount : 0,
                    'title' => (isset($tax_detail->label)) ? $tax_detail->label : 'Tax'
                );
            }
        }*/
        return $taxes;
    }

    /**
     * Get the line items details
     * @param $order
     * @return array
     */
    function getOrderLineItemsDetails($order)
    {
        $items = array();
        $cart = self::$woocommerce->getOrderItems($order);
        if (!empty($cart)) {
            foreach ($cart as $item_key => $item_details) {
                //Deceleration
                $tax_details = array();
                $item_quantity = (isset($item_details['quantity']) && !empty($item_details['quantity'])) ? $item_details['quantity'] : NULL;
                $variant_id = (isset($item_details['variation_id']) && !empty($item_details['variation_id'])) ? $item_details['variation_id'] : 0;
                $product_id = (isset($item_details['product_id']) && !empty($item_details['product_id'])) ? $item_details['product_id'] : 0;
                $is_variable_item = false;
                if (!empty($variant_id)) {
                    $item = self::$woocommerce->getProduct($variant_id);
                    $is_variable_item = true;
                } elseif (!empty($product_id)) {
                    $item = self::$woocommerce->getProduct($product_id);
                } else {
                    $item = (isset($item_details['data']) && !empty($item_details['data'])) ? $item_details['data'] : NULL;
                }
                $line_tax = $this->formatDecimalPriceRemoveTrailingZeros((isset($item_details['line_tax']) && !empty($item_details['line_tax'])) ? $item_details['line_tax'] : 0);
                if ($line_tax > 0) {
                    $tax_details[] = array(
                        'rate' => 0,
                        'zone' => 'province',
                        'price' => $line_tax,
                        'title' => 'tax',
                        'source' => 'WooCommerce',
                        'position' => 1,
                        'compare_at' => 0,
                    );
                }
                $image_url = self::$woocommerce->getProductImageSrc($item);
                if (!empty($item) && !empty($item_quantity)) {
                    $item_array = array(
                        'key' => $item_key,
                        'image_url' => $image_url,
                        'product_url' => self::$woocommerce->getProductUrl($item),
                        'sku' => self::$woocommerce->getItemSku($item),
                        'price' => $this->formatDecimalPriceRemoveTrailingZeros(self::$woocommerce->getItemPrice($item)),
                        'title' => self::$woocommerce->getItemName($item),
                        'vendor' => 'woocommerce',
                        'taxable' => ($line_tax != 0),
                        'user_id' => NULL,
                        'quantity' => $item_quantity,
                        'tax_lines' => $tax_details,
                        'line_price' => $this->formatDecimalPriceRemoveTrailingZeros($this->getLineItemTotal($item_details)),
                        'product_id' => $product_id,
                        'properties' => array(),
                        'variant_id' => $variant_id,
                        'variant_price' => $this->formatDecimalPriceRemoveTrailingZeros(($is_variable_item) ? self::$woocommerce->getItemPrice($item) : 0),
                        'variant_title' => ($is_variable_item) ? self::$woocommerce->getItemName($item) : 0,
                        'requires_shipping' => true
                    );
                    $items[] = apply_filters('rnoc_get_order_line_item_details', $item_array, $cart, $item_key, $item);
                }
            }
        }
        return $items;
    }

    /**
     * get the completed at time of the order
     * @param $order
     * @return mixed|void
     */
    function getCompletedAt($order)
    {
        $order_id = self::$woocommerce->getOrderId($order);
        $order_placed_at = self::$woocommerce->getOrderMeta($order, $this->order_placed_date_key_for_db);
        $order_status = self::$woocommerce->getStatus($order);
        if (!$order_placed_at && $this->isOrderHasValidOrderStatus($order_status)) {
            $order_placed_at = current_time('timestamp', true);
            self::$woocommerce->setOrderMeta($order_id, $this->order_placed_date_key_for_db, $order_placed_at);
            if ($this->isOrderInPendingRecovery($order_id)) {
                $this->markOrderAsRecovered($order_id);
            }
        }
        $completed_at = (!empty($order_placed_at)) ? $this->formatToIso8601($order_placed_at) : NULL;
        return apply_filters('rnoc_order_completed_at', $completed_at, $order);
    }

    /**
     * get the cart token from the order object
     * @param $order
     * @return string|null
     */
    function getOrderCartToken($order)
    {
        return self::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db);
    }

    /**
     * get order details for sync cart
     * @param $order
     * @return array
     */
    function getOrderData($order)
    {
        $user_ip = self::$woocommerce->getOrderMeta($order, $this->user_ip_key_for_db);
        $can_track_cart = $this->canTrackAbandonedCarts($user_ip, $order);
        $order_id = self::$woocommerce->getOrderId($order);
        self::$settings->logMessage(array('can_track_cart' => $can_track_cart, 'user_ip' => $user_ip), 'can track cart in getOrderData method for ' . $order_id);
        if (!$can_track_cart) {
            return array();
        }
        $cart_token = $this->getOrderCartToken($order);
        self::$settings->logMessage($cart_token, 'Cart token in getOrderData method for ' . $order_id);
        if (empty($cart_token)) {
            return array();
        }
        $cart_hash = self::$woocommerce->getOrderMeta($order, $this->cart_hash_key_for_db);
        $is_buyer_accepts_marketing = self::$woocommerce->getOrderMeta($order, $this->accepts_marketing_key_for_db);
        $customer_details = $this->getCustomerDetails($order);
        $current_currency_code = self::$woocommerce->getOrderCurrency($order);
        $default_currency_code = self::$settings->getBaseCurrency();
        $cart_created_at = self::$woocommerce->getOrderMeta($order, $this->cart_tracking_started_key_for_db);
        self::$settings->logMessage($cart_created_at, 'cart created time in getOrderData ' . $order_id);
        $cart_total = $this->formatDecimalPrice(self::$woocommerce->getOrderTotal($order));
        $excluding_tax = (self::$woocommerce->isPriceExcludingTax());
        $consider_on_hold_order_as_ac = $this->considerOnHoldAsAbandoned();
        $recovered_at = self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_at');
        $order_status = self::$woocommerce->getStatus($order);
        $order_status = $this->changeOrderStatus($order_status);
        $order_data = array(
            'cart_type' => 'order',
            'treat_on_hold_as_complete' => ($consider_on_hold_order_as_ac == 0),
            'r_order_id' => $order_id,
            'order_number' => $order_id,
            'woo_r_order_number' => self::$woocommerce->getOrderNumber($order),
            'plugin_version' => RNOC_VERSION,
            'cart_hash' => $cart_hash,
            'ip' => $user_ip,
            'id' => $cart_token,
            'name' => '#' . $cart_token,
            'email' => (isset($customer_details['email'])) ? $customer_details['email'] : NULL,
            'token' => $cart_token,
            'user_id' => NULL,
            'currency' => $default_currency_code,
            'customer' => $customer_details,
            'tax_lines' => $this->getOrderTaxDetails(),
            'total_tax' => $this->formatDecimalPrice(self::$woocommerce->getOrderTotalTax($order)),
            'cart_token' => $cart_token,
            'created_at' => $this->formatToIso8601($cart_created_at),
            'line_items' => $this->getOrderLineItemsDetails($order),
            'updated_at' => $this->formatToIso8601(''),
            'source_name' => 'web',
            'total_price' => $cart_total,
            'completed_at' => $this->getCompletedAt($order),
            'total_weight' => 0,
            'discount_codes' => self::$woocommerce->getAppliedDiscounts($order),
            'order_status' => apply_filters('rnoc_abandoned_cart_order_status', $order_status, $order),
            'shipping_lines' => array(),
            'subtotal_price' => $this->formatDecimalPrice(self::$woocommerce->getOrderSubTotal($order)),
            'total_price_set' => $this->getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code),
            'taxes_included' => (!self::$woocommerce->isPriceExcludingTax()),
            'customer_locale' => $this->getOrderLanguage($order),
            'total_discounts' => $this->formatDecimalPrice(self::$woocommerce->getOrderDiscount($order, $excluding_tax)),
            'shipping_address' => $this->getCustomerShippingAddressDetails($order),
            'billing_address' => $this->getCustomerBillingAddressDetails($order),
            'presentment_currency' => $current_currency_code,
            'abandoned_checkout_url' => $this->getRecoveryLink($cart_token),
            'total_line_items_price' => $this->formatDecimalPrice($this->getOrderItemsTotal($order)),
            'buyer_accepts_marketing' => ($is_buyer_accepts_marketing == 1),
            'cancelled_at' => self::$woocommerce->getOrderMeta($order, $this->order_cancelled_date_key_for_db),
            'woocommerce_totals' => $this->getOrderTotals($order, $excluding_tax),
            'recovered_by_retainful' => (self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_by')) ? true : false,
            'recovered_cart_token' => self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_cart_token'),
            'recovered_at' => (!empty($recovered_at)) ? $this->formatToIso8601($recovered_at) : NULL,
            'noc_discount_codes' => $this->getNextOrderCouponDetails($order),
            'client_details' => $this->getClientDetails($order)
        );
        if(!empty($cart_token)){
            $referrer_automation_id = self::$woocommerce->getSession($cart_token.'_referrer_automation_id');
            if(!empty($referrer_automation_id)){
                $order_data['referrer_automation_id'] = $referrer_automation_id;
            }
        }
        return apply_filters('rnoc_api_get_order_data', $order_data, $order);
    }

    /**
     * get the language from order
     * @param $order
     * @return string
     */
    function getOrderLanguage($order)
    {
        $selected_language = $language = '';
        //to get language from WPML language
        if (!empty($order)) {
            $order_id = self::$woocommerce->getOrderId($order);
            if (!empty($order_id)) {
                $language = get_post_meta($order_id, 'wpml_language', true);
            }
        }
        if ($language !== false && $language != '') {
            if (function_exists('icl_get_languages')) {
                $languages = icl_get_languages();
                if (isset($languages[$language])) {
                    if (isset($languages[$language]['default_locale'])) {
                        $selected_language = $languages[$language]['default_locale'];
                    }
                }
            }
        }
        //If empty of selected language, then use site's default language as selected language
        if (empty($selected_language)) {
            $selected_language = self::$woocommerce->getSiteDefaultLang();
        }
        return apply_filters('rnoc_get_order_language', $selected_language);
    }

    /**
     * next order coupon details
     * @param $order
     * @return array
     */
    function getNextOrderCouponDetails($order)
    {
        $order_id = self::$woocommerce->getOrderId($order);
        $data = array();
        $next_order_coupon = self::$woocommerce->getPostMeta($order_id, '_rnoc_next_order_coupon');
        $order_coupon_obj = new OrderCoupon();
        if (empty($next_order_coupon) && self::$settings->isNextOrderCouponEnabled()) {
            $next_order_coupon = $order_coupon_obj->createNewCoupon($order_id, array());
        }
        if (!empty($next_order_coupon)) {
            $coupon_details = $order_coupon_obj->getCouponByCouponCode($next_order_coupon);
            if (!empty($coupon_details)) {
                $coupon_id = $coupon_details->ID;
                $coupon_expiry_date = get_post_meta($coupon_id, 'coupon_expired_on', true);
                $ends_at = null;
                if (!empty($coupon_expiry_date)) {
                    $expiry_date = get_gmt_from_date($coupon_expiry_date);
                    $ends_at = strtotime($expiry_date);
                }
                $data[] = array(
                    'id' => $coupon_id,
                    'code' => $next_order_coupon,
                    'ends_at' => $ends_at,
                    'created_at' => strtotime($coupon_details->post_date_gmt),
                    'updated_at' => strtotime($coupon_details->post_modified_gmt),
                    'usage_count' => 1
                );
            }
        }
        return $data;
    }

    /**
     * get the subtotal from order
     * @param $order
     * @return int|String|null
     */
    function getOrderItemsTotal($order)
    {
        $subtotal = 0;
        $cart = self::$woocommerce->getOrderItems($order);
        if (!empty($cart)) {
            foreach ($cart as $item) {
                $subtotal += self::$woocommerce->getItemSubTotal($item);
                if (!self::$woocommerce->isPriceExcludingTax()) {
                    $subtotal += self::$woocommerce->getItemTaxSubTotal($item);
                }
            }
        }
        return $subtotal;
    }

    /**
     * get cart totals
     * @param $order
     * @param $excluding_tax
     * @return array
     */
    function getOrderTotals($order, $excluding_tax)
    {
        return array(
            'total_price' => $this->formatDecimalPrice(self::$woocommerce->getOrderTotal($order)),
            'subtotal_price' => $this->formatDecimalPrice($this->getOrderItemsTotal($order)),
            'total_tax' => $this->formatDecimalPrice(self::$woocommerce->getOrderTotalTax($order)),
            'total_discounts' => $this->formatDecimalPrice(self::$woocommerce->getOrderDiscount($order, $excluding_tax)),
            'total_shipping' => $this->formatDecimalPrice(self::$woocommerce->getOrderShippingTotal($order)),
            'fee_items' => $this->getOrderFeeDetails($order, $excluding_tax),
        );
    }

    /**
     * get cart fee details
     * @param $order
     * @param $excluding_tax
     * @return array
     */
    function getOrderFeeDetails($order, $excluding_tax)
    {
        $fee_items = array();
        if ($fees = self::$woocommerce->getOrderFees($order)) {
            foreach ($fees as $id => $fee) {
                $fee_items[] = array(
                    'title' => html_entity_decode($fee['name'] ? $fee['name'] : __('Fee', RNOC_TEXT_DOMAIN)),
                    'key' => $id,
                    'amount' => $this->formatDecimalPrice(($excluding_tax) ? $fee['line_total'] : $fee['line_total'] + $fee['line_tax'])
                );
            }
        }
        return $fee_items;
    }

    /**
     * Currency details for cart
     * @param $cart_total
     * @param $current_currency_code
     * @param $default_currency_code
     * @return array
     */
    function getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code)
    {
        if ($current_currency_code != $default_currency_code) {
            $exchange_rate = apply_filters('rnoc_get_currency_rate', $cart_total, $current_currency_code);
            $shop_cart_total = $this->convertToCurrency($cart_total, $exchange_rate);
        } else {
            $shop_cart_total = $cart_total;
        }
        $details = array(
            'shop_money' => array(
                'amount' => $shop_cart_total,
                'currency_code' => $default_currency_code
            ),
            'presentment_money' => array(
                'amount' => $cart_total,
                'currency_code' => $current_currency_code
            )
        );
        return $details;
    }

    /**
     * Get the shipping address of the customer
     * @param $order
     * @return array
     */
    function getCustomerShippingAddressDetails($order)
    {
        return array(
            'zip' => self::$woocommerce->getShippingPostCode($order),
            'city' => self::$woocommerce->getShippingCity($order),
            'name' => self::$woocommerce->getShippingFirstName($order) . ' ' . self::$woocommerce->getShippingLastName($order),
            'phone' => NULL,
            'company' => NULL,
            'country' => self::$woocommerce->getShippingCountry($order),
            'address1' => self::$woocommerce->getShippingAddressOne($order),
            'address2' => self::$woocommerce->getShippingAddressTwo($order),
            'latitude' => '',
            'province' => self::$woocommerce->getShippingState($order),
            'last_name' => self::$woocommerce->getShippingLastName($order),
            'longitude' => '',
            'first_name' => self::$woocommerce->getShippingFirstName($order),
            'country_code' => self::$woocommerce->getShippingCountry($order),
            'province_code' => self::$woocommerce->getShippingState($order),
        );
    }

    /**
     * Get the billing address of the customer
     * @param $order
     * @return array
     */
    function getCustomerBillingAddressDetails($order)
    {
        return array(
            'zip' => self::$woocommerce->getBillingPostCode($order),
            'city' => self::$woocommerce->getBillingCity($order),
            'name' => self::$woocommerce->getBillingFirstName($order) . ' ' . self::$woocommerce->getBillingLastName($order),
            'phone' => NULL,
            'company' => NULL,
            'country' => self::$woocommerce->getBillingCountry($order),
            'address1' => self::$woocommerce->getBillingAddressOne($order),
            'address2' => self::$woocommerce->getBillingAddressTwo($order),
            'latitude' => '',
            'province' => self::$woocommerce->getBillingState($order),
            'last_name' => self::$woocommerce->getBillingLastName($order),
            'longitude' => '',
            'first_name' => self::$woocommerce->getBillingFirstName($order),
            'country_code' => self::$woocommerce->getBillingCountry($order),
            'province_code' => self::$woocommerce->getBillingState($order),
        );
    }
}