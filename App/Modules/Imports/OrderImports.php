<?php

namespace RNOC\App\Modules\Imports;

use RNOC\App\Helpers\WP;
use RNOC\App\Helpers\Settings;
use RNOC\App\Modules\AbandonedCart\Order;

class OrderImports {

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

		$orders = new Order();
		foreach ( $orders_data as $order_id ) {
			$order               = wc_get_order( $order_id );
			$response['items'][] = $orders->getOrderData( $order, 'import' );
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

}