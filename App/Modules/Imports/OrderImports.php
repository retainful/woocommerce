<?php

namespace RNOC\App\Modules\Imports;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RNOC\App\Helpers\Customer;
use RNOC\App\Helpers\WC;
use RNOC\App\Helpers\WP;
use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\Order;
use RNOC\App\Modules\AbandonedCart\AbandonedCart;
use RNOC\App\Modules\AbandonedCart\Traits\SyncData;

class OrderImports {
	use syncData;

	/**
	 * Get Order Count via Rest api
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function getSyncOrderCount( \WP_REST_Request $request ) {

		$request_params         = $request->get_params();
		$default_request_params = [
			'status' => 'any',
			'digest' => ''
		];
		$params                 = wp_parse_args( $request_params, $default_request_params );
		if ( empty( $params['digest'] ) || ! is_string( $params['digest'] ) || $params['status'] != 'any' ) {
			$status   = 400;
			$response = [ 'success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!' ];

			return new \WP_REST_Response( $response, $status );
		}
		if ( ! Settings::isHashMatches( $params['digest'], [ 'status' => $params['status'] ] ) ) {

			$status   = 400;
			$response = [
				'success'       => false,
				'RESPONSE_CODE' => 'SECURITY_BREACH',
				'message'       => 'Security validation failed!'
			];

			return new \WP_REST_Response( $response, $status );
		}

		$response = [
			'success'       => true,
			'RESPONSE_CODE' => 'Ok',
			'total_count'   => (int) self::getOrderCount()
			//'total_count' => is_array($orders) ? count($orders) : 0
		];
		$status   = 200;

		return new \WP_REST_Response( $response, $status );
	}


	/**
	 * Get order count
	 *
	 * @return string|null
	 */
	protected static function getOrderCount() {
		global $wpdb;
		$tablePrefix     = $wpdb->prefix;
		$orderItemsTable = "{$tablePrefix}woocommerce_order_items";
		if ( WP::isHPOSEnabled() ) {
			$ordersTable          = "{$tablePrefix}wc_orders";
			$typeOrPostTypeColumn = 'type';
		} else {
			$ordersTable          = "{$tablePrefix}posts";
			$typeOrPostTypeColumn = 'post_type';
		}
		$query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT {$ordersTable}.id) FROM {$ordersTable} LEFT JOIN {$orderItemsTable} ON {$ordersTable}.id = {$orderItemsTable}.order_id
         WHERE {$typeOrPostTypeColumn} = %s AND {$ordersTable}.id > 0 AND {$orderItemsTable}.order_id > 0 AND {$orderItemsTable}.order_item_type = %s",
			[ 'shop_order', 'line_item' ]
		);

		return $wpdb->get_var( $query );
	}

	/**
	 * Get sync order.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */

	function getSyncOrders( \WP_REST_Request $request ) {
		$request_params         = $request->get_params();
		$default_request_params = [
			'limit'    => 10,
			'since_id' => 0,
			'status'   => 'any',
			'digest'   => ''
		];
		$params                 = wp_parse_args( $request_params, $default_request_params );
		if ( is_array( $params['limit'] ) || empty( $params['digest'] ) || ! is_string( $params['digest'] ) || empty( $params['limit'] ) || $params['since_id'] < 0 || $params['status'] != 'any' ) {
			$status   = 400;
			$response = [ 'success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!' ];

			return new \WP_REST_Response( $response, $status );
		}
		if ( ! Settings::isHashMatches( $params['digest'], [
			'limit'    => (int) $params['limit'],
			'since_id' => (int) $params['since_id'],
			'status'   => (string) $params['status']
		] ) ) {
			$status   = 400;
			$response = [
				'success'       => false,
				'RESPONSE_CODE' => 'SECURITY_BREACH',
				'message'       => 'Security validation failed'
			];

			return new \WP_REST_Response( $response, $status );
		}
		$orders_data = $this->getOrders( $params );

		//Do like his response
		$response = [
			'success'       => true,
			'RESPONSE_CODE' => 'Ok',
			'items'         => []
		];

		foreach ( $orders_data as $order_id ) {
			$order               = wc_get_order( $order_id );
			$response['items'][] = self::getOrderData( $order );
		}
		$status = 200;

		return new \WP_REST_Response( $response, $status );
	}

	/**
	 * Get orders based on parameters.
	 *
	 * @param array $params Array containing 'since_id' and 'limit'.
	 *
	 * @return array Array of order IDs.
	 */
	protected function getOrders( $params ) {
		if ( ! is_array( $params ) || ! isset( $params['since_id'] ) || ! isset( $params['limit'] ) ) {
			return [];
		}

		global $wpdb;

		$sinceId         = (int) $params['since_id'];
		$limit           = (int) $params['limit'];
		$orderItemType   = 'line_item';
		$typeOrPostType  = 'shop_order';
		$orderItemsTable = "{$wpdb->prefix}woocommerce_order_items";

		if ( WP::isHPOSEnabled() ) {
			$ordersTable          = "{$wpdb->prefix}wc_orders";
			$typeOrPostTypeColumn = 'type';
			$id                   = 'id';
		} else {
			$ordersTable          = "{$wpdb->prefix}posts";
			$typeOrPostTypeColumn = 'post_type';
			$id                   = 'ID';
		}

		$query = $wpdb->prepare( "SELECT {$id} FROM {$ordersTable} LEFT JOIN {$orderItemsTable} ON {$ordersTable}.{$id} = {$orderItemsTable}.order_id WHERE {$typeOrPostTypeColumn} = %s 
	AND {$ordersTable}.{$id} > %d AND {$orderItemsTable}.order_id > 0 AND {$orderItemsTable}.order_item_type = %s GROUP BY {$id} ORDER BY {$id} ASC LIMIT %d",
			[ $typeOrPostType, $sinceId, $orderItemType, $limit ]
		);

		return $wpdb->get_col( $query );
	}

	/**
	 * Get order related data
	 *
	 * @param $order
	 *
	 * @return array
	 */
	public function getOrderData( $order ) {
		if ( ! is_object( $order ) ) { // bool|WC_Order|WC_Order_Refund
			return [];
		}
		$order_id = Order::getOrderId( $order );
		if ( empty( $order_id ) ) {
			return [];
		}

		$cart_token = Order::getOrderMeta( self::$cart_token_key_for_db, $order );
		if ( empty( $cart_token ) ) {
			$cart_token = AbandonedCart::generateCartToken();
		}
		//still Cart token empty
		if ( empty( $cart_token ) ) {
			return [];
		}
		$user_ip = Order::getOrderMeta( self::$user_ip_key_for_db, $order );
		if ( empty( $user_ip ) ) {
			$user_ip = $order->get_customer_ip_address();
		}
		$cart_hash = Order::getOrderMeta( self::$cart_hash_key_for_db, $order );
		if ( empty( $cart_hash ) ) {
			$cart_hash = $order->get_cart_hash();
		}
		$is_buyer_accepts_marketing = Order::getOrderMeta( self::$accepts_marketing_key_for_db, $order );
		if ( ! in_array( $is_buyer_accepts_marketing, array( 0, 1 ) ) ) {
			$is_buyer_accepts_marketing = $order->get_customer_id() > 0 ? 1 : 0;
		}
		$cart_created_at = Order::getOrderMeta( self::$cart_tracking_started_key_for_db, $order );
		if ( empty( $cart_created_at ) ) {
			$cart_created_at = $order->get_date_created();
		}
		if ( is_null( $cart_created_at ) ) {
			$cart_created_at = current_time( 'timestamp', true );
		}
		$updated_at = $order->get_date_modified();
		if ( is_null( $updated_at ) ) {
			$updated_at = current_time( 'timestamp', true );
		}
		//completed_at if available need to do
		$customer_details      = Customer::getOrderCustomer( $order );
		$default_currency_code = WC::getDefaultCurrency();
		$cart_total            = WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderTotal( $order ) );
		$order_placed_at       = WC::getOrderPlacedDate( $order );
		$order_status          = Order::getStatus( $order );
		$order_status          = Order::getRetainFulOrderStatus( $order_status );
		$current_currency_code = Order::getOrderData( 'currency', $order );
		$excluding_tax         = WC::isPriceExcludingTax();
		$recovered_at          = Order::getOrderMeta( '_rnoc_recovered_at', $order );
		$user_agent            = Customer::getUserAgent( $order );
		if ( empty( $user_agent ) ) {
			$user_agent = $order->get_customer_user_agent();
		}
		$order_module = new \RNOC\App\Modules\AbandonedCart\Order();

		$order_data = [
			'cart_type'                 => 'order',
			'treat_on_hold_as_complete' => Settings::get( RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 0 ) == 0,
			'r_order_id'                => $order_id,
			'order_number'              => $order_id,
			'order_date'                => WP::formatToIso8601( Order::getOrderDate( $order ) ),
			'woo_r_order_number'        => Order::getOrderNumber( $order ),
			'cart_hash'                 => $cart_hash,
			'ip'                        => $user_ip,
			'id'                        => $cart_token,
			'email'                     => ( isset( $customer_details['email'] ) ) ? $customer_details['email'] : null,
			'token'                     => $cart_token,
			'currency'                  => $default_currency_code,
			'customer'                  => $customer_details,
			'tax_lines'                 => [],
			'total_tax'                 => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderData( 'total_tax', $order, 0 ) ),
			'cart_token'                => $cart_token,
			'created_at'                => WP::formatToIso8601( $cart_created_at ),
			'line_items'                => $order_module->getOrderLineItemsDetails( $order ),
			'updated_at'                => WP::formatToIso8601( $updated_at ),
			'source_name'               => 'web',
			'total_price'               => $cart_total,
			'completed_at'              => ! empty( $order_placed_at ) ? WP::formatToIso8601( $order_placed_at ) : null,
			'total_weight'              => 0,
			'discount_codes'            => Order::getAppliedDiscounts( $order ),
			'order_status'              => apply_filters( 'rnoc_abandoned_cart_order_status', $order_status, $order ),
			'shipping_lines'            => [],
			'subtotal_price'            => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderSubTotal( $order ) ),
			'total_price_set'           => $this->getCurrencyDetails( $cart_total, $current_currency_code, $default_currency_code ),
			'taxes_included'            => ( ! WC::isPriceExcludingTax() ),
			'customer_locale'           => \RNOC\App\Helpers\Order::getOrderLanguage( $order ),
			'total_discounts'           => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderDiscount( $order, $excluding_tax ) ),
			'shipping_address'          => Customer::getOrderShippingAddress( $order ),
			'billing_address'           => Customer::getOrderBillingAddress( $order ),
			'presentment_currency'      => $current_currency_code,
			'abandoned_checkout_url'    => $this->getRecoveryLink( $cart_token ),
			'total_line_items_price'    => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderItemsTotal( $order ) ),
			'buyer_accepts_marketing'   => ( $is_buyer_accepts_marketing == 1 ),
			'cancelled_at'              => \RNOC\App\Helpers\Order::getOrderMeta( self::$order_cancelled_date_key_for_db, $order ),
			'woocommerce_totals'        => $order_module->getOrderTotals( $order, $excluding_tax ),
			'recovered_by_retainful'    => (bool) Order::getOrderMeta( '_rnoc_recovered_by', $order ),
			'recovered_cart_token'      => order::getOrderMeta( '_rnoc_recovered_cart_token', $order ),
			'recovered_at'              => ( ! empty( $recovered_at ) ) ? WC::formatToIso8601( $recovered_at ) : null,
			'client_details'            => Customer::getClientDetails( $order ),
			'payment_method'            => [
				'value' => $order->get_payment_method(),
				'name'  => $order->get_payment_method_title(),
			]
		];

		return apply_filters( 'rnoc_import_order_data', $order_data, $order );
	}

}