<?php

namespace Rnoc\Retainful\Api\Imports;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Rnoc\Retainful\Api\AbandonedCart\Order;

class Category extends Order {

	/**
	 * Hash verification.
	 *
	 * @param array $data
	 * @param $hash_value
	 *
	 * @return bool
	 */
	protected static function hashVerification( $data, $hash_value ) {
		$reverse_hmac = self::hashToken( $data );
		return hash_equals( $reverse_hmac, $hash_value );
	}

	/**
	 * Hash token.
	 *
	 * @param $data
	 *
	 * @return string
	 */
	protected static function hashToken( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}
		$data   = json_encode( $data );
		$secret = self::$settings->getSecretKey();
		return hash_hmac( 'sha256', $data, $secret );
	}

	public static function getCategoryData($params){
		if ( empty( $params ) ) {
			return array();
		}
		$limit = ! empty( $params['limit'] ) ? $params['limit'] : 10;
		$since_id = ! empty( $params['since_id'] ) ? $params['since_id'] : 10;

		global $wpdb;
		$query = $wpdb->prepare("SELECT t.term_id, t.name, tt.taxonomy FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = %s AND t.term_id > %d
        ORDER BY t.term_id ASC
        LIMIT %d
    ", 'product_cat', $since_id, $limit);

		return $wpdb->get_results($query);

	}


	public static function getProductIdsByCategoryId($category_id) {
		global $wpdb;
		$product_ids = $wpdb->get_col($wpdb->prepare("
        SELECT object_id FROM {$wpdb->term_relationships}
        WHERE term_taxonomy_id = %d
    ", $category_id));

		return $product_ids;
	}

	/**
	 * get orders
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	protected static function getCatergotyList( $params ) {
		if ( empty( $params ) ) {
			return array();
		}
		$categories = self::getCategoryData($params);
		$category_list = [];
		foreach ($categories as $category) {
			// Ensure slug exists before using it
			$slug = isset($category->slug) ? $category->slug : '';

			// Fetch description safely using get_term()
			$term = get_term($category->term_id, 'product_cat');
			$description = !is_wp_error($term) && isset($term->description) ? $term->description : '';

			$category_list[] = [
				'Id'                 => wp_generate_uuid4(), // Generate a unique ID
				'ExternalCategoryId' => $category->term_id, // Use term ID as ExternalCategoryId
				'AppId'              => self::$settings->getApiKey(), // Replace with actual AppId
				'ShopId'             => '', // Replace with actual ShopId
				'ExternalProductIds' => self::getProductIdsByCategoryId($category->term_id),
				'Name'               => $category->name,
				'Description'        => $description, // Use fetched description
				'handle'             => $slug, // Ensure slug is valid
				'CreatedAt'          => function_exists('current_time') ? current_time('Y-m-d\TH:i:s.u\Z') : '',
				'UpdatedAt'          => function_exists('current_time') ? current_time('Y-m-d\TH:i:s.u\Z') : '',
				'CategoryUpdatedAt'  => function_exists('current_time') ? current_time('Y-m-d\TH:i:s.u\Z') : '',
				'DeletedAt'          => null, // Set to null as categories are not deleted in this list
			];
		}
		return $category_list;
	}

	/**
	 * Create coupons
	 *
	 * @param \WP_REST_Request $request
	 *cc
	 * @return \WP_REST_Response
	 */
	public static function getCategory( \WP_REST_Request $request ) {
		$request_params = $request->get_params();
		$default_request_params = array(
			'limit'  => 10,
			'id'     => 0,
			'status' => 'any',
			//'last_days' => 0,
			'digest' => ''
		);
		$params  = wp_parse_args( $request_params, $default_request_params );
		self::$settings->logMessage( $params, 'API Product get request' );
		if ( is_array( $params['limit'] ) || empty( $params['digest'] ) || ! is_string( $params['digest'] ) || empty( $params['limit'] ) || !isset($params['since_id']) || $params['since_id'] < 0 || $params['status'] != 'any' ) {
			self::$settings->logMessage( $params, 'API Product data missing' );
			$status   = 400;
			$response = array( 'success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!' );
			return new \WP_REST_Response( $response, $status );
 		}
		self::$settings->logMessage( $params, 'API Product data matched' );

		if ( ! self::hashVerification( array(
			'limit'    => (int) $params['limit'],
			'since_id' => (int) $params['since_id'],
			'status'   => (string) $params['status']
		), $params['digest'] ) ) {
			self::$settings->logMessage( $params, 'API Product request digest not matched' );
			$status   = 400;
			$response = array(
				'success'       => false,
				'RESPONSE_CODE' => 'SECURITY_BREACH',
				'message'       => 'Security validation failed'
			);

			return new \WP_REST_Response( $response, $status );
		}


		$category = self::getCatergotyList( $params );
		//Do like his response
		$response = array(
			'success'       => true,
			'RESPONSE_CODE' => 'Ok',
			'items'         => $category
		);
		$status = 200;

		return new \WP_REST_Response( $response, $status );
	}

	/**
	 * Get Order Count via Rest api.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public static function getCategoryCount( \WP_REST_Request $request ) {
		$request_params         = $request->get_params();
		$default_request_params = array(
			'status' => 'any',
			'digest' => '',
		);
		$params                 = wp_parse_args( $request_params, $default_request_params );
		self::$settings->logMessage( $params, 'API Product get request' );
		if ( empty( $params['digest'] ) || ! is_string( $params['digest'] ) || $params['status'] != 'any' ) {
			self::$settings->logMessage( $params, 'API Product Count data missing' );
			$status   = 400;
			$response = array( 'success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!' );

			return new \WP_REST_Response( $response, $status );
		}
		self::$settings->logMessage( $params, 'API Product Count data matched' );
		if ( ! self::hashVerification( array( 'status' => $params['status'] ), $params['digest'] ) ) {
			self::$settings->logMessage( $params, 'API Product Count request digest not matched' );
			$status   = 400;
			$response = array(
				'success'       => false,
				'RESPONSE_CODE' => 'SECURITY_BREACH',
				'message'       => 'Security validation failed!'
			);

			return new \WP_REST_Response( $response, $status );
		}
		$total_categories = function_exists('wp_count_terms') ? wp_count_terms( 'product_cat', array( 'hide_empty' => false ) ) : '';

		$response = array(
			'success'       => true,
			'RESPONSE_CODE' => 'Ok',
			'total_count'   => (int)$total_categories,
		);
		$status   = 200;

		return new \WP_REST_Response( $response, $status );
	}

	public static function createCategory( $term_id, $taxonomy) {
		self::categoryCallback( $term_id, $taxonomy,'created' );
	}

	public static function updateCategory($term_id, $taxonomy ) {
		self::categoryCallback( $term_id, $taxonomy,'updated' );
	}

	public static function deleteCategory( $term_id, $taxonomy ) {
		self::categoryCallback( $term_id, $taxonomy,'deleted' );
	}

	public static function categoryCallback($term_id, $taxonomy, $action) {
		$category = get_term($term_id, 'product_cat');
		if (!$category || is_wp_error($category)) {
			return;
		}

		// Get category details
		$description = isset($category->description) ? $category->description : '';
		$slug = isset($category->slug) ? $category->slug : '';

		// Fetch active webhooks
		$data_store = \WC_Data_Store::load('webhook');
		$args = array(
			'limit' => -1,
			'offset' => 0,
			'status' => 'active', // Filter only active webhooks
		);
		$webhooks = $data_store->search_webhooks($args);

		if (empty($webhooks)) {
			return;
		}

		foreach ($webhooks as $webhook_id) {
			$webhook = wc_get_webhook($webhook_id);
			$topic = $webhook->get_topic();
			if(!in_array($topic,['category.updated', 'category.created', 'category.updated'])){
				continue;
			}
			// Ensure topic is valid and matches our custom webhook
			if (!empty($topic) && strpos($topic, 'category') !== false) {
				if ($topic === "category.{$action}") {
					$payload = [
						'Id'                 => wp_generate_uuid4(),
						'ExternalCategoryId' => $category->term_id,
						'AppId'              => self::$settings->getApiKey(),
						'ShopId'             => '',
						'ExternalProductIds' => self::getProductIdsByCategoryId($category->term_id),
						'Name'               => $category->name,
						'Description'        => $description,
						'handle'             => $slug,
						'CreatedAt'          => current_time('Y-m-d H:i:s'),
						'UpdatedAt'          => current_time('Y-m-d H:i:s'),
						'CategoryUpdatedAt'  => current_time('Y-m-d H:i:s'),
						'DeletedAt'          => null,
						'EventType'          => $action,
					];
					// Deliver webhook
					$webhook->deliver($payload);
				}
			}
		}
	}


}