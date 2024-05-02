<?php

namespace Rnoc\Retainful\Api\Imports;

use Rnoc\Retainful\Api\AbandonedCart\Order;

class Imports extends Order
{
    /**
     * Hash verification
     * @param $data
     * @param $hash_value
     * @return bool
     */
    protected function hashVerification($data, $hash_value)
    {
        if (!is_array($data) || !is_string($hash_value)) {
            return false;
        }
        $data = json_encode($data);
        $secret = self::$settings->getSecretKey();
        $reverse_hmac = hash_hmac('sha256', $data, $secret);
        return hash_equals($reverse_hmac, $hash_value);
    }

    /**
     * get orders
     * @param $params
     * @return array
     */
    protected function getOrders($params)
    {
        if (!is_array($params) || !isset($params['since_id']) || !isset($params['limit'])) {
            return array();
        }
        global $wpdb;
        if (self::isHPOSEnabled()) {
            $query = $wpdb->prepare("SELECT id FROM {$wpdb->prefix}wc_orders LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}woocommerce_order_items.order_id
          WHERE type = %s AND id > %s AND status != %s AND {$wpdb->prefix}woocommerce_order_items.order_id > 0 AND {$wpdb->prefix}woocommerce_order_items.order_item_type = %s GROUP BY id ORDER BY id ASC LIMIT %d", array('shop_order', (int)$params['since_id'], 'trash', 'line_item', (int)$params['limit']));
        } else {
            $query = $wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}woocommerce_order_items.order_id 
          WHERE post_type IN ('shop_order') AND ID > %d AND post_status != %s AND {$wpdb->prefix}woocommerce_order_items.order_id > 0 AND {$wpdb->prefix}woocommerce_order_items.order_item_type = %s GROUP BY ID ORDER BY ID ASC LIMIT %d", array((int)$params['since_id'], 'trash', 'line_item', (int)$params['limit']));
        }
        return $wpdb->get_col($query);
    }

    /**
     * get order count
     * @return string|null
     */
    protected function getOrderCount()
    {
        global $wpdb;
        if (self::isHPOSEnabled()) {
            $query = $wpdb->prepare("SELECT COUNT(DISTINCT {$wpdb->prefix}wc_orders.id) FROM {$wpdb->prefix}wc_orders LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}woocommerce_order_items.order_id
          WHERE type = %s AND id > 0 AND status != %s AND {$wpdb->prefix}woocommerce_order_items.order_id > 0 AND {$wpdb->prefix}woocommerce_order_items.order_item_type = %s", array('shop_order', 'trash', 'line_item'));
        } else {
            $query = $wpdb->prepare("SELECT COUNT(DISTINCT {$wpdb->prefix}posts.ID) FROM {$wpdb->prefix}posts LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}woocommerce_order_items.order_id 
          WHERE post_type = %s AND ID > 0 AND post_status != %s AND {$wpdb->prefix}woocommerce_order_items.order_id > 0 AND {$wpdb->prefix}woocommerce_order_items.order_item_type = %s", array('shop_order', 'trash', 'line_item'));
        }
        return $wpdb->get_var($query);
    }

    /**
     * create coupons
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    function getSyncOrders(\WP_REST_Request $request)
    {
        $request_params = $request->get_params();
        $default_request_params = array(
            'limit' => 10,
            'since_id' => 0,
            'status' => 'any',
            'digest' => ''
        );
        $params = wp_parse_args($request_params, $default_request_params);
        self::$settings->logMessage($params, 'API Orders get request');
        if (is_array($params['limit']) || empty($params['digest']) || !is_string($params['digest']) || empty($params['limit']) || $params['since_id'] < 0 || $params['status'] != 'any') {
            self::$settings->logMessage($params, 'API Orders data missing');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!');
            return new \WP_REST_Response($response, $status);
        }
        self::$settings->logMessage($params, 'API Orders data matched');
        if (!$this->hashVerification(array('limit' => (int)$params['limit'], 'since_id' => (int)$params['since_id'], 'status' => (string)$params['status']), $params['digest'])) {
            self::$settings->logMessage($params, 'API Orders request digest not matched');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security validation failed');
            return new \WP_REST_Response($response, $status);
        }
        $orders = $this->getOrders($params);
        //Do like his response
        $response = array(
            'success' => true,
            'RESPONSE_CODE' => 'Ok',
            'items' => array()
        );
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            $response['items'][] = $this->getOrderData($order);
        }
        $status = 200;
        return new \WP_REST_Response($response, $status);
    }

    /**
     * check is HPOS enabled
     * @return bool
     */
    public static function isHPOSEnabled()
    {
        if (!class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }
        if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            return true;
        }
        return false;
    }

    /**
     * Get Order Count via Rest api
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getSyncOrderCount(\WP_REST_Request $request)
    {
        $request_params = $request->get_params();
        $default_request_params = array(
            'status' => 'any',
            'digest' => ''
        );
        $params = wp_parse_args($request_params, $default_request_params);
        self::$settings->logMessage($params, 'API Orders get request');
        if (empty($params['digest']) || !is_string($params['digest']) || $params['status'] != 'any') {
            self::$settings->logMessage($params, 'API Order Count data missing');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!');
            return new \WP_REST_Response($response, $status);
        }
        self::$settings->logMessage($params, 'API Order Count data matched');
        if (!$this->hashVerification(array('status' => $params['status']), $params['digest'])) {
            self::$settings->logMessage($params, 'API Order Count request digest not matched');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security validation failed!');
            return new \WP_REST_Response($response, $status);
        }
        $response = array(
            'success' => true,
            'RESPONSE_CODE' => 'Ok',
            'total_count' => (int)$this->getOrderCount()
            //'total_count' => is_array($orders) ? count($orders) : 0
        );
        $status = 200;
        return new \WP_REST_Response($response, $status);
    }

    /**
     * Get order related data
     * @param $order
     * @return array
     */
    public function getOrderData($order)
    {
        if (!is_object($order)) { // bool|WC_Order|WC_Order_Refund
            return array();
        }
        $order_id = self::$woocommerce->getOrderId($order);
        if (empty($order_id)) return array();
        $cart_token = self::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db);
        if (empty($cart_token)) $cart_token = $this->generateCartToken();
        //still Cart token empty
        if (empty($cart_token)) {
            return array();
        }
        $user_ip = self::$woocommerce->getOrderMeta($order, $this->user_ip_key_for_db);
        if (empty($user_ip)) $user_ip = $order->get_customer_ip_address();
        $cart_hash = self::$woocommerce->getOrderMeta($order, $this->cart_hash_key_for_db);
        if (empty($cart_hash)) $cart_hash = $order->get_cart_hash();
        $is_buyer_accepts_marketing = self::$woocommerce->getOrderMeta($order, $this->accepts_marketing_key_for_db);
        if (!in_array($is_buyer_accepts_marketing, array(0, 1))) $is_buyer_accepts_marketing = $order->get_customer_id() > 0 ? 1 : 0;
        $cart_created_at = self::$woocommerce->getOrderMeta($order, $this->cart_tracking_started_key_for_db);
        if (empty($cart_created_at)) $cart_created_at = $order->get_date_created();
        if (is_null($cart_created_at)) {
            $cart_created_at = current_time('timestamp', true);
        }
        $updated_at = $order->get_date_modified();
        if (is_null($updated_at)) {
            $updated_at = current_time('timestamp', true);
        }
        //completed_at if available need to do
        $consider_on_hold_order_as_ac = $this->considerOnHoldAsAbandoned();
        $customer_details = $this->getCustomerDetails($order);
        $default_currency_code = self::$settings->getBaseCurrency();
        $cart_total = $this->formatDecimalPrice(self::$woocommerce->getOrderTotal($order));
        $order_placed_at = self::$woocommerce->getOrderPlacedDate($order);
        $order_status = self::$woocommerce->getStatus($order);
        $order_status = $this->changeOrderStatus($order_status);
        $current_currency_code = self::$woocommerce->getOrderCurrency($order);
        $excluding_tax = self::$woocommerce->isPriceExcludingTax();
        $recovered_at = self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_at');
        $user_agent = $this->getUserAgent($order);
        if (empty($user_agent)) $user_agent = $order->get_customer_user_agent();
        $customer_language = $this->getOrderLanguage($order);
        $order_data = array(
            'cart_type' => 'order',
            'treat_on_hold_as_complete' => ($consider_on_hold_order_as_ac == 0),
            'r_order_id' => $order_id,
            'order_number' => $order_id,
            'order_date' => $this->formatToIso8601(self::$woocommerce->getOrderDate($order)),
            'woo_r_order_number' => self::$woocommerce->getOrderNumber($order),
            'cart_hash' => $cart_hash,
            'ip' => $user_ip,
            'id' => $cart_token,
            'email' => (isset($customer_details['email'])) ? $customer_details['email'] : NULL,
            'token' => $cart_token,
            'currency' => $default_currency_code,
            'customer' => $customer_details,
            'tax_lines' => $this->getOrderTaxDetails(),
            'total_tax' => $this->formatDecimalPrice(self::$woocommerce->getOrderTotalTax($order)),
            'cart_token' => $cart_token,
            'created_at' => $this->formatToIso8601($cart_created_at),
            'line_items' => $this->getOrderLineItemsDetails($order),
            'updated_at' => $this->formatToIso8601($updated_at),
            'source_name' => 'web',
            'total_price' => $cart_total,
            'completed_at' => !empty($order_placed_at) ? $this->formatToIso8601($order_placed_at) : NULL,
            'total_weight' => 0,
            'discount_codes' => self::$woocommerce->getAppliedDiscounts($order),
            'order_status' => apply_filters('rnoc_abandoned_cart_order_status', $order_status, $order),
            'shipping_lines' => array(),
            'subtotal_price' => $this->formatDecimalPrice(self::$woocommerce->getOrderSubTotal($order)),
            'total_price_set' => $this->getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code),
            'taxes_included' => (!self::$woocommerce->isPriceExcludingTax()),
            'customer_locale' => $customer_language,
            'total_discounts' => $this->formatDecimalPrice(self::$woocommerce->getOrderDiscount($order, $excluding_tax)),
            'shipping_address' => $this->getCustomerShippingAddressDetails($order),
            'billing_address' => $this->getCustomerBillingAddressDetails($order),
            'presentment_currency' => $current_currency_code,
            'abandoned_checkout_url' => $this->getRecoveryLink($cart_token),
            'total_line_items_price' => $this->formatDecimalPrice($this->getOrderItemsTotal($order)),
            'buyer_accepts_marketing' => ($is_buyer_accepts_marketing == 1),
            'cancelled_at' => self::$woocommerce->getOrderMeta($order, $this->order_cancelled_date_key_for_db),
            'woocommerce_totals' => $this->getOrderTotals($order, $excluding_tax),
            'recovered_by_retainful' => (bool)self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_by'),
            'recovered_cart_token' => self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_cart_token'),
            'recovered_at' => (!empty($recovered_at)) ? $this->formatToIso8601($recovered_at) : NULL,
            'client_details' => $this->getClientDetails($order),
            'payment_method' => array(
                'value' => $order->get_payment_method(),
                'name' => $order->get_payment_method_title(),
            )
        );
        return apply_filters('rnoc_import_order_data', $order_data, $order);
    }
}