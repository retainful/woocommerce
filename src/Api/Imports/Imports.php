<?php

namespace Rnoc\Retainful\Api\Imports;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\Order;
use Rnoc\Retainful\WcFunctions;

class Imports extends Order
{
    /**
     * create coupons
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    function getOrders(\WP_REST_Request $request)
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
        $secret = self::$settings->getSecretKey();
        $reverse_hmac = hash_hmac('sha256', json_encode(array('limit' => (int)$params['limit'], 'since_id' => (int)$params['since_id'], 'status' => (string)$params['status'])), $secret);
        if (!hash_equals($reverse_hmac, $params['digest'])) {
            self::$settings->logMessage($reverse_hmac, 'API Orders request digest not matched');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security breached!');
            return new \WP_REST_Response($response, $status);
        }
        self::$settings->logMessage($reverse_hmac, 'API Orders request digest matched');
        global $wpdb;
        if(self::isHPOSEnabled()){
            $query = $wpdb->prepare("SELECT id FROM {$wpdb->prefix}wc_orders LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}woocommerce_order_items.order_id
          WHERE type = %s AND id > %s AND {$wpdb->prefix}woocommerce_order_items.order_id > 0 GROUP BY id ORDER BY id ASC LIMIT %d",array('shop_order',(int)$params['since_id'], (int)$params['limit']));
        }else{
            $query = $wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}woocommerce_order_items.order_id 
          WHERE post_type IN ('shop_order') AND ID > %d AND {$wpdb->prefix}woocommerce_order_items.order_id > 0 GROUP BY ID ORDER BY ID ASC LIMIT %d", array((int)$params['since_id'], (int)$params['limit']));
        }
        /*$orders = wc_get_orders(array('orderby' => 'id', 'order' => 'ASC','offset' => $params['offset'], 'limit' => $params['limit']));*/
        $orders = $wpdb->get_col($query);
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
    public static function isHPOSEnabled(){
        if(!class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')){
            return false;
        }
        if(\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()){
            return true;
        }
        return false;
    }
    public static function getOrderCount(\WP_REST_Request $request)
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
        $secret = self::$settings->getSecretKey();
        $reverse_hmac = hash_hmac('sha256', json_encode(array('status' => $params['status'])), $secret);
        if (!hash_equals($reverse_hmac, $params['digest'])) {
            self::$settings->logMessage($reverse_hmac, 'API Order Count request digest not matched');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security breached!');
            return new \WP_REST_Response($response, $status);
        }
        self::$settings->logMessage($reverse_hmac, 'API Order Count request digest matched');
        global $wpdb;
        if(self::isHPOSEnabled()){
            //$query = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE type = %s",array('shop_order'));
            //
            $query = $wpdb->prepare("SELECT COUNT(DISTINCT {$wpdb->prefix}wc_orders.id) FROM {$wpdb->prefix}wc_orders LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}wc_orders.id = {$wpdb->prefix}woocommerce_order_items.order_id
          WHERE type = %s AND id > 0 AND {$wpdb->prefix}woocommerce_order_items.order_id > 0",array('shop_order'));
        }else{
            //$query = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = %s",array('shop_order'));
            //SELECT COUNT(DISTINCT wp_posts.ID) as total
            //FROM wp_posts
            //LEFT JOIN wp_woocommerce_order_items ON wp_posts.ID = wp_woocommerce_order_items.order_id
            //WHERE wp_posts.post_type = 'shop_order' AND wp_posts.ID > 0 AND wp_woocommerce_order_items.order_id > 0;
            $query = $wpdb->prepare("SELECT COUNT(DISTINCT {$wpdb->prefix}posts.ID) FROM {$wpdb->prefix}posts LEFT JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}woocommerce_order_items.order_id 
          WHERE post_type = %s AND ID > 0 AND {$wpdb->prefix}woocommerce_order_items.order_id > 0", array('shop_order'));
        }
        //$orders = wc_get_orders(array('orderby' => 'id', 'order' => 'ASC', 'limit' => -1));
        $response = array(
            'success' => true,
            'RESPONSE_CODE' => 'Ok',
            'total_count' => (int)$wpdb->get_var($query)
            //'total_count' => is_array($orders) ? count($orders) : 0
        );
        $status = 200;
        return new \WP_REST_Response($response, $status);
    }

    public function getOrderData($order)
    {
        if(!is_object($order)){ // bool|WC_Order|WC_Order_Refund
            return array();
        }
        $cart_token = self::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db);
        if(empty($cart_token)) $cart_token = $this->generateCartToken();
        $order_id = self::$woocommerce->getOrderId($order);
        //still Cart token empty
        if(empty($cart_token) || empty($order_id)){
            return array();
        }
        $user_ip = self::$woocommerce->getOrderMeta($order, $this->user_ip_key_for_db);
        if(empty($user_ip)) $user_ip = $order->get_customer_ip_address();
        $cart_hash = self::$woocommerce->getOrderMeta($order, $this->cart_hash_key_for_db);
        if(empty($cart_hash)) $cart_hash = $order->get_cart_hash();
        $is_buyer_accepts_marketing = self::$woocommerce->getOrderMeta($order, $this->accepts_marketing_key_for_db);
        if(!in_array($is_buyer_accepts_marketing,array(0,1))) $is_buyer_accepts_marketing = $order->get_customer_id() > 0 ? 1: 0;
        $cart_created_at = self::$woocommerce->getOrderMeta($order, $this->cart_tracking_started_key_for_db);
        if(empty($cart_created_at)) $cart_created_at = $order->get_date_created();
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
            'completed_at' => !empty($order_placed_at) ? $this->formatToIso8601($order_placed_at) : NULL,
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
            'cancelled_at' => self::$woocommerce->getOrderMeta($order, $this->order_cancelled_date_key_for_db),//check with developer
            'woocommerce_totals' => $this->getOrderTotals($order, $excluding_tax),
            'recovered_by_retainful' => (self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_by')) ? true : false,
            'recovered_cart_token' => self::$woocommerce->getOrderMeta($order, '_rnoc_recovered_cart_token'),
            'recovered_at' => (!empty($recovered_at)) ? $this->formatToIso8601($recovered_at) : NULL,
            'noc_discount_codes' => array(),
            'client_details' => array()
        );

        /*$abandoned_order = new Order();
        $woocommerce_helper = new WcFunctions();
        $retainful_api = new \Rnoc\Retainful\Api\AbandonedCart\RestApi();
        $settings = new Settings();
        $user_ip_key_for_db = '_rnoc_user_ip_address';
        $cart_hash_key_for_db = '_rnoc_cart_hash';
        $accepts_marketing_key_for_db = '_rnoc_is_buyer_accepts_marketing';
        $cart_tracking_started_key_for_db = '_rnoc_cart_tracking_started_at';
        $order_cancelled_date_key_for_db = '_rnoc_order_cancelled_at';
        $cart_token_key_for_db = "_rnoc_user_cart_token";
        $user_ip = $woocommerce_helper->getOrderMeta($order, $user_ip_key_for_db);
        $order_id = $woocommerce_helper->getOrderId($order);
        if(empty($user_ip)){
            $user_ip = $retainful_api->retrieveUserIp();
            $woocommerce_helper->setOrderMeta($order_id, $user_ip_key_for_db, $user_ip);
        }
        $cart_token = $abandoned_order->getOrderCartToken($order);
        if(empty($cart_token)){
            $cart_token = $retainful_api->generateCartToken();
            $woocommerce_helper->setOrderMeta($order_id, $cart_token_key_for_db, $cart_token);
        }
        $cart_hash = $woocommerce_helper->getOrderMeta($order, $cart_hash_key_for_db);
        if(empty($cart_hash)){
            $cart_hash = $woocommerce_helper->getOrderMeta($order,'_cart_hash');
            $woocommerce_helper->setOrderMeta($order_id, $cart_hash_key_for_db, $cart_hash);
        }
        $is_buyer_accepts_marketing = $woocommerce_helper->getOrderMeta($order, $accepts_marketing_key_for_db);
        if(empty($is_buyer_accepts_marketing)){
            $is_buyer_accepts_marketing = ($retainful_api->isBuyerAcceptsMarketing()) ? 1 : 0;
            $woocommerce_helper->setOrderMeta($order_id, $accepts_marketing_key_for_db, $is_buyer_accepts_marketing);
        }
        $cart_created_at = $woocommerce_helper->getOrderMeta($order, $cart_tracking_started_key_for_db);
        if(empty($cart_created_at)){
            $cart_created_at = $retainful_api->userCartCreatedAt();
            $woocommerce_helper->setOrderMeta($order_id, $cart_tracking_started_key_for_db, $cart_created_at);
        }
        $customer_details = $abandoned_order->getCustomerDetails($order);
        $current_currency_code = $woocommerce_helper->getOrderCurrency($order);
        $default_currency_code = $settings->getBaseCurrency();

        //$settings->logMessage($cart_created_at, 'cart created time in getOrderData ' . $order_id);
        $cart_total = $abandoned_order->formatDecimalPrice($woocommerce_helper->getOrderTotal($order));
        $excluding_tax = ($woocommerce_helper->isPriceExcludingTax());
        $consider_on_hold_order_as_ac = $abandoned_order->considerOnHoldAsAbandoned();
        $recovered_at = $woocommerce_helper->getOrderMeta($order, '_rnoc_recovered_at');
        $order_status = $woocommerce_helper->getStatus($order);
        $order_data = array(
            'cart_type' => 'order',
            'treat_on_hold_as_complete' => ($consider_on_hold_order_as_ac == 0),
            'r_order_id' => $order_id,
            'order_number' => $order_id,
            'woo_r_order_number' => $woocommerce_helper->getOrderNumber($order),
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
            'tax_lines' => $abandoned_order->getOrderTaxDetails(),
            'total_tax' => $abandoned_order->formatDecimalPrice($woocommerce_helper->getOrderTotalTax($order)),
            'cart_token' => $cart_token,
            'created_at' => $abandoned_order->formatToIso8601($cart_created_at),
            'line_items' => $abandoned_order->getOrderLineItemsDetails($order),
            'updated_at' => $abandoned_order->formatToIso8601(''),
            'source_name' => 'web',
            'total_price' => $cart_total,
            'completed_at' => $abandoned_order->getCompletedAt($order),
            'total_weight' => 0,
            'discount_codes' => $woocommerce_helper->getAppliedDiscounts($order),
            'order_status' => $order_status,
            'shipping_lines' => array(),
            'subtotal_price' => $abandoned_order->formatDecimalPrice($woocommerce_helper->getOrderSubTotal($order)),
            'total_price_set' => $abandoned_order->getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code),
            'taxes_included' => (!$woocommerce_helper->isPriceExcludingTax()),
            'customer_locale' => $abandoned_order->getOrderLanguage($order),
            'total_discounts' => $abandoned_order->formatDecimalPrice($woocommerce_helper->getOrderDiscount($order, $excluding_tax)),
            'shipping_address' => $abandoned_order->getCustomerShippingAddressDetails($order),
            'billing_address' => $abandoned_order->getCustomerBillingAddressDetails($order),
            'presentment_currency' => $current_currency_code,
            'abandoned_checkout_url' => $abandoned_order->getRecoveryLink($cart_token),
            'total_line_items_price' => $abandoned_order->formatDecimalPrice($abandoned_order->getOrderItemsTotal($order)),
            'buyer_accepts_marketing' => ($is_buyer_accepts_marketing == 1),
            'cancelled_at' => $woocommerce_helper->getOrderMeta($order, $order_cancelled_date_key_for_db),
            'woocommerce_totals' => $abandoned_order->getOrderTotals($order, $excluding_tax),
            'recovered_by_retainful' => ($woocommerce_helper->getOrderMeta($order, '_rnoc_recovered_by')) ? true : false,
            'recovered_cart_token' => $woocommerce_helper->getOrderMeta($order, '_rnoc_recovered_cart_token'),
            'recovered_at' => (!empty($recovered_at)) ? $abandoned_order->formatToIso8601($recovered_at) : NULL,
            'noc_discount_codes' => $abandoned_order->getNextOrderCouponDetails($order),
            'client_details' => $abandoned_order->getClientDetails($order)
        );
        if (!empty($cart_token)) {
            $referrer_automation_id = $woocommerce_helper->getSession($cart_token . '_referrer_automation_id');
            if (!empty($referrer_automation_id)) {
                $order_data['referrer_automation_id'] = $referrer_automation_id;
            }
        }*/
        return $order_data;
    }
}