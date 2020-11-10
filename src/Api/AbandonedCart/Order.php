<?php

namespace Rnoc\Retainful\Api\AbandonedCart;

use Rnoc\Retainful\OrderCoupon;

class Order extends RestApi
{
    /**
     * Get the customer details
     * @param $order \WC_Order
     * @return array
     */
    function getCustomerDetails($order)
    {
        global $retainful;
        if ($user_id = $retainful::$woocommerce->getOrderUserId($order)) {
            $user_data = $retainful::$woocommerce->getOrderUser($order);
            $created_at = $updated_at = strtotime($user_data->user_registered);
        } else {
            $user_id = 0;
            $created_at = $updated_at = current_time('timestamp', true);
        }
        $customer_id = $order->get_customer_id();
        $last_order = wc_get_customer_last_order($user_id);
        $last_order_id = null;
        if (!empty($last_order) && $last_order instanceof \WC_Order) {
            $last_order_id = $retainful::$woocommerce->getOrderId($last_order);
        }
        $billing_email = $retainful::$woocommerce->getBillingEmail($order);
        $orders_count = $retainful::$woocommerce->getCustomerTotalOrders($billing_email);
        return array(
            'id' => $user_id,
            'customer_id' => $customer_id,
            'email' => $billing_email,
            'phone' => $retainful::$woocommerce->getBillingPhone($order),
            'state' => $retainful::$woocommerce->getBillingState($order),
            'currency' => NULL,
            'last_name' => $retainful::$woocommerce->getBillingFirstName($order),
            'created_at' => $this->formatToIso8601($created_at),
            'first_name' => $retainful::$woocommerce->getBillingLastName($order),
            'updated_at' => $this->formatToIso8601($updated_at),
            'total_spent' => $retainful::$woocommerce->getCustomerTotalSpent($billing_email),
            'orders_count' => ($orders_count == 1) ? 0 : $orders_count,
            'last_order_id' => $last_order_id,
            'verified_email' => true,
            'last_order_name' => NULL,
            'accepts_marketing' => true,
        );
    }

    /**
     * get the cart tax details
     * @return array
     */
    function getOrderTaxDetails()
    {
        global $retainful;
        //$tax_details = $retainful::$woocommerce->getCartTaxes();
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
        global $retainful;
        $items = array();
        $cart = $retainful::$woocommerce->getOrderItems($order);
        if (!empty($cart)) {
            foreach ($cart as $item_key => $item_details) {
                //Deceleration
                $tax_details = array();
                $item_quantity = (isset($item_details['quantity']) && !empty($item_details['quantity'])) ? $item_details['quantity'] : NULL;
                $variant_id = (isset($item_details['variation_id']) && !empty($item_details['variation_id'])) ? $item_details['variation_id'] : 0;
                $product_id = (isset($item_details['product_id']) && !empty($item_details['product_id'])) ? $item_details['product_id'] : 0;
                $is_variable_item = false;
                if (!empty($variant_id)) {
                    $item = $retainful::$woocommerce->getProduct($variant_id);
                    $is_variable_item = true;
                } elseif (!empty($product_id)) {
                    $item = $retainful::$woocommerce->getProduct($product_id);
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
                $image_url = $retainful::$woocommerce->getProductImageSrc($item);
                if (!empty($item) && !empty($item_quantity)) {
                    $item_array = array(
                        'key' => $item_key,
                        'image_url' => $image_url,
                        'product_url' => $retainful::$woocommerce->getProductUrl($item),
                        'sku' => $retainful::$woocommerce->getItemSku($item),
                        'price' => $this->formatDecimalPriceRemoveTrailingZeros($retainful::$woocommerce->getItemPrice($item)),
                        'title' => $retainful::$woocommerce->getItemName($item),
                        'vendor' => 'woocommerce',
                        'taxable' => ($line_tax != 0),
                        'user_id' => NULL,
                        'quantity' => $item_quantity,
                        'tax_lines' => $tax_details,
                        'line_price' => $this->formatDecimalPriceRemoveTrailingZeros((isset($item_details['line_total']) && !empty($item_details['line_total'])) ? $item_details['line_total'] : 0),
                        'product_id' => $product_id,
                        'properties' => array(),
                        'variant_id' => $variant_id,
                        'variant_price' => $this->formatDecimalPriceRemoveTrailingZeros(($is_variable_item) ? $retainful::$woocommerce->getItemPrice($item) : 0),
                        'variant_title' => ($is_variable_item) ? $retainful::$woocommerce->getItemName($item) : 0,
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
        global $retainful;
        $order_id = $retainful::$woocommerce->getOrderId($order);
        $order_placed_at = $retainful::$woocommerce->getOrderMeta($order, $this->order_placed_date_key_for_db);
        $order_status = $retainful::$woocommerce->getStatus($order);
        if (!$order_placed_at && $this->isOrderHasValidOrderStatus($order_status)) {
            $order_placed_at = current_time('timestamp', true);
            $retainful::$woocommerce->setOrderMeta($order_id, $this->order_placed_date_key_for_db, $order_placed_at);
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
        global $retainful;
        return $retainful::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db);
    }

    /**
     * get order details for sync cart
     * @param $order
     * @return array
     */
    function getOrderData($order)
    {
        global $retainful;
        $user_ip = $retainful::$woocommerce->getOrderMeta($order, $this->user_ip_key_for_db);
        if (!$this->canTrackAbandonedCarts($user_ip)) {
            return array();
        }
        $order_id = $retainful::$woocommerce->getOrderId($order);
        $cart_token = $this->getOrderCartToken($order);
        if (empty($cart_token)) {
            return array();
        }
        $cart_hash = $retainful::$woocommerce->getOrderMeta($order, $this->cart_hash_key_for_db);
        $is_buyer_accepts_marketing = $retainful::$woocommerce->getOrderMeta($order, $this->accepts_marketing_key_for_db);
        $customer_details = $this->getCustomerDetails($order);
        $current_currency_code = $retainful::$woocommerce->getOrderCurrency($order);
        $default_currency_code = $retainful::$plugin_admin->getBaseCurrency();
        $cart_created_at = $retainful::$woocommerce->getOrderMeta($order, $this->cart_tracking_started_key_for_db);
        $retainful::$plugin_admin->logMessage($cart_created_at, 'cart created time');
        $cart_total = $this->formatDecimalPrice($retainful::$woocommerce->getOrderTotal($order));
        $excluding_tax = ($retainful::$woocommerce->isPriceExcludingTax());
        $consider_on_hold_order_as_ac = $this->considerOnHoldAsAbandoned();
        $recovered_at = $retainful::$woocommerce->getOrderMeta($order, '_rnoc_recovered_at');
        $order_status = $retainful::$woocommerce->getStatus($order);
        $order_status = $this->cancelledOrderStatus($order_status);
        $order_data = array(
            'cart_type' => 'order',
            'treat_on_hold_as_complete' => ($consider_on_hold_order_as_ac == 0),
            'r_order_id' => $order_id,
            'order_number' => $order_id,
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
            'total_tax' => $this->formatDecimalPrice($retainful::$woocommerce->getOrderTotalTax($order)),
            'cart_token' => $cart_token,
            'created_at' => $this->formatToIso8601($cart_created_at),
            'line_items' => $this->getOrderLineItemsDetails($order),
            'updated_at' => $this->formatToIso8601(''),
            'source_name' => 'web',
            'total_price' => $cart_total,
            'completed_at' => $this->getCompletedAt($order),
            'total_weight' => 0,
            'discount_codes' => $retainful::$woocommerce->getAppliedDiscounts($order),
            'order_status' => apply_filters('rnoc_abandoned_cart_order_status', $order_status, $order),
            'shipping_lines' => array(),
            'subtotal_price' => $this->formatDecimalPrice($retainful::$woocommerce->getOrderSubTotal($order)),
            'total_price_set' => $this->getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code),
            'taxes_included' => (!$retainful::$woocommerce->isPriceExcludingTax()),
            'customer_locale' => NULL,
            'total_discounts' => $this->formatDecimalPrice($retainful::$woocommerce->getOrderDiscount($order, $excluding_tax)),
            'shipping_address' => $this->getCustomerShippingAddressDetails($order),
            'billing_address' => $this->getCustomerBillingAddressDetails($order),
            'presentment_currency' => $current_currency_code,
            'abandoned_checkout_url' => $this->getRecoveryLink($cart_token),
            'total_line_items_price' => $this->formatDecimalPrice($this->getOrderItemsTotal($order)),
            'buyer_accepts_marketing' => ($is_buyer_accepts_marketing == 1),
            'cancelled_at' => $retainful::$woocommerce->getOrderMeta($order, $this->order_cancelled_date_key_for_db),
            'woocommerce_totals' => $this->getOrderTotals($order, $excluding_tax),
            'recovered_by_retainful' => ($retainful::$woocommerce->getOrderMeta($order, '_rnoc_recovered_by')) ? true : false,
            'recovered_cart_token' => $retainful::$woocommerce->getOrderMeta($order, '_rnoc_recovered_cart_token'),
            'recovered_at' => (!empty($recovered_at)) ? $this->formatToIso8601($recovered_at) : NULL,
            'noc_discount_codes' => $this->getNextOrderCouponDetails($order),
            'client_details' => $this->getClientDetails($order)
        );
        return apply_filters('rnoc_api_get_order_data', $order_data, $order);
    }

    /**
     * next order coupon details
     * @param $order
     * @return array
     */
    function getNextOrderCouponDetails($order)
    {
        global $retainful;
        $order_id = $retainful::$woocommerce->getOrderId($order);
        $data = array();
        $next_order_coupon = $retainful::$woocommerce->getPostMeta($order_id, '_rnoc_next_order_coupon');
        $order_coupon_obj = new OrderCoupon();
        if (empty($next_order_coupon)) {
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
        global $retainful;
        $subtotal = 0;
        $cart = $retainful::$woocommerce->getOrderItems($order);
        if (!empty($cart)) {
            foreach ($cart as $item) {
                $subtotal += $retainful::$woocommerce->getItemSubTotal($item);
                if (!$retainful::$woocommerce->isPriceExcludingTax()) {
                    $subtotal += $retainful::$woocommerce->getItemTaxSubTotal($item);
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
        global $retainful;
        return array(
            'total_price' => $this->formatDecimalPrice($retainful::$woocommerce->getOrderTotal($order)),
            'subtotal_price' => $this->formatDecimalPrice($this->getOrderItemsTotal($order)),
            'total_tax' => $this->formatDecimalPrice($retainful::$woocommerce->getOrderTotalTax($order)),
            'total_discounts' => $this->formatDecimalPrice($retainful::$woocommerce->getOrderDiscount($order, $excluding_tax)),
            'total_shipping' => $this->formatDecimalPrice($retainful::$woocommerce->getOrderShippingTotal($order)),
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
        global $retainful;
        $fee_items = array();
        if ($fees = $retainful::$woocommerce->getOrderFees($order)) {
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
        global $retainful;
        return array(
            'zip' => $retainful::$woocommerce->getShippingPostCode($order),
            'city' => $retainful::$woocommerce->getShippingCity($order),
            'name' => $retainful::$woocommerce->getShippingFirstName($order) . ' ' . $retainful::$woocommerce->getShippingLastName($order),
            'phone' => NULL,
            'company' => NULL,
            'country' => $retainful::$woocommerce->getShippingCountry($order),
            'address1' => $retainful::$woocommerce->getShippingAddressOne($order),
            'address2' => $retainful::$woocommerce->getShippingAddressTwo($order),
            'latitude' => '',
            'province' => $retainful::$woocommerce->getShippingState($order),
            'last_name' => $retainful::$woocommerce->getShippingLastName($order),
            'longitude' => '',
            'first_name' => $retainful::$woocommerce->getShippingFirstName($order),
            'country_code' => $retainful::$woocommerce->getShippingCountry($order),
            'province_code' => $retainful::$woocommerce->getShippingState($order),
        );
    }

    /**
     * Get the billing address of the customer
     * @param $order
     * @return array
     */
    function getCustomerBillingAddressDetails($order)
    {
        global $retainful;
        return array(
            'zip' => $retainful::$woocommerce->getBillingPostCode($order),
            'city' => $retainful::$woocommerce->getBillingCity($order),
            'name' => $retainful::$woocommerce->getBillingFirstName($order) . ' ' . $retainful::$woocommerce->getBillingLastName($order),
            'phone' => NULL,
            'company' => NULL,
            'country' => $retainful::$woocommerce->getBillingCountry($order),
            'address1' => $retainful::$woocommerce->getBillingAddressOne($order),
            'address2' => $retainful::$woocommerce->getBillingAddressTwo($order),
            'latitude' => '',
            'province' => $retainful::$woocommerce->getBillingState($order),
            'last_name' => $retainful::$woocommerce->getBillingLastName($order),
            'longitude' => '',
            'first_name' => $retainful::$woocommerce->getBillingFirstName($order),
            'country_code' => $retainful::$woocommerce->getBillingCountry($order),
            'province_code' => $retainful::$woocommerce->getBillingState($order),
        );
    }
}