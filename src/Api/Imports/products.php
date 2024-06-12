<?php

namespace Rnoc\Retainful\Api\Imports;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Rnoc\Retainful\Api\AbandonedCart\Order;

class products extends Order {
	/**
	 * Hash verification.
	 *
	 * @param array $data
	 * @param $hash_value
	 *
	 * @return bool
	 */
	protected function hashVerification( $data, $hash_value ) {
		$reverse_hmac = $this->hashToken( $data );

		return hash_equals( $reverse_hmac, $hash_value );
	}

	/**
	 * Hash token.
	 *
	 * @param $data
	 *
	 * @return string
	 */
	protected function hashToken( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}
		$data   = json_encode( $data );
		$secret = self::$settings->getSecretKey();

		return hash_hmac( 'sha256', $data, $secret );
	}

	/**
	 * get orders
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	protected function getProducts( $params ) {
		if ( empty( $params ) ) {
			return array();
		}
		$limit = ! empty( $params['limit'] ) ? $params['limit'] : 10;
		global $wpdb;
		$query = $wpdb->prepare( "SELECT {$wpdb->prefix}posts.ID FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_status != %s ORDER BY ID ASC LIMIT %d", array(
			0,
			'trash',
			(int) $limit
		) );

		return $wpdb->get_results( $query );

	}


	/**
	 * get order count
	 * @return string|null
	 */
	protected function getProductCount() {
		global $wpdb;
		$query = $wpdb->prepare( "SELECT COUNT(DISTINCT {$wpdb->prefix}posts.ID) FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_status != %s", array(
			0,
			'trash'
		) );

		return $wpdb->get_var( $query );
	}

	/**
	 * Create coupons
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	function getSyncProducts( \WP_REST_Request $request ) {
		$request_params = $request->get_params();

		$default_request_params = array(
			'limit'  => 10,
			'id'     => 0,
			'status' => 'any',
			//'last_days' => 0,
			'digest' => ''
		);
		$params                 = wp_parse_args( $request_params, $default_request_params );

		self::$settings->logMessage( $params, 'API Product get request' );
		if ( is_array( $params['limit'] ) || empty( $params['digest'] ) || ! is_string( $params['digest'] ) || empty( $params['limit'] ) || $params['since_id'] < 0 || $params['status'] != 'any' ) {
			self::$settings->logMessage( $params, 'API Product data missing' );
			$status   = 400;
			$response = array( 'success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!' );

			return new \WP_REST_Response( $response, $status );
		}
		self::$settings->logMessage( $params, 'API Product data matched' );

		if ( ! $this->hashVerification( array(
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


		$products = $this->getProducts( $params );

		//Do like his response
		$response = array(
			'success'       => true,
			'RESPONSE_CODE' => 'Ok',
			'items'         => array()
		);
		foreach ( $products as $product_data ) {
			$response['items'][] = $this->setProductData( $product_data->ID );
		}
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
	public function getSyncProductCount( \WP_REST_Request $request ) {
		$request_params         = $request->get_params();
		$default_request_params = array(
			'status' => 'any',
			'digest' => '',
			// 'last_days' => 0

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
		if ( ! $this->hashVerification( array( 'status' => $params['status'] ), $params['digest'] ) ) {
			self::$settings->logMessage( $params, 'API Product Count request digest not matched' );
			$status   = 400;
			$response = array(
				'success'       => false,
				'RESPONSE_CODE' => 'SECURITY_BREACH',
				'message'       => 'Security validation failed!'
			);

			return new \WP_REST_Response( $response, $status );
		}
		$response = array(
			'success'       => true,
			'RESPONSE_CODE' => 'Ok',
			'total_count'   => (int) $this->getProductCount( $params )
		);
		$status   = 200;

		return new \WP_REST_Response( $response, $status );
	}

	/**
	 * change the woocommerce header data
	 *
	 * @param $http_args
	 * @param int $product_id product id.
	 * @param int $webhook_id webhook id.
	 *
	 * @return array|\WP_REST_Response
	 * @throws \Exception
	 */
	function changeWebHookHeaderProduct( $http_args, $product_id, $webhook_id ) {
		if ( $webhook_id <= 0 || ! class_exists( 'WC_Webhook' ) || ! self::$settings->isConnectionActive() ) {
			return $http_args;
		}
		try {
			$webhook      = new \WC_Webhook( $webhook_id );
			$topic        = $webhook->get_topic();
			$topic_status = self::$settings->getWebHookStatus();

			if ( ! isset( $topic_status[ $topic ] ) || ! $topic_status[ $topic ] ) {
				return $http_args;
			}
			$delivery_url      = $webhook->get_delivery_url();
			$site_delivery_url = self::$settings->getDeliveryUrl( $topic );

			if ( $delivery_url != $site_delivery_url || $product_id <= 0 ) {
				return $http_args;
			}
			$product_data = $this->setProductData( $product_id );

			if ( is_array( $product_data['id'] ) || empty( $product_data['created_at'] ) ) {
				self::$settings->logMessage( $product_data, 'API Product data missing' );
				$status   = 400;
				$response = array(
					'success'       => false,
					'RESPONSE_CODE' => 'DATA_MISSING',
					'message'       => 'Invalid data!'
				);

				return new \WP_REST_Response( $response, $status );
			}
			$product_data['digest']     = $this->hashToken( array(
				$product_data['id'],
				$product_data['created_at'],
				$product_data['title']
			) );
			$product_data['event_type'] = $topic;
			if ( ! empty( $product_data ) ) {
				$app_id        = self::$settings->getApiKey();
				$extra_headers = array(
					"X-Retainful-Version" => RNOC_VERSION,
					"app_id"              => $app_id,
					"Content-Type"        => 'application/json'
				);
				foreach ( $extra_headers as $key => $value ) {
					$http_args['headers'][ $key ] = $value;
				}
				$body              = array(
					'data' => $product_data
				);
				$http_args['body'] = trim( wp_json_encode( $body ) );
			}
		} catch ( Exception $e ) {

		}

		return $http_args;
	}

	/**
	 * Set the product data.
	 *
	 * @param int $product_id woocommerce product id.
	 *
	 * @return array|void
	 */
	protected function setProductData( $product_id ) {
		if ( empty( $product_id ) ) {
			return;
		}
		$product           = self::$woocommerce->getProduct( $product_id );
		$product_variation = array();
		if ( $product->is_type( 'variable' ) ) {
			$variations = self::$woocommerce->isMethodExists( $product, 'get_available_variations' ) ? $product->get_available_variations() : array();

			foreach ( $variations as $variation ) {
				$variation_id        = is_array( $variation ) && ! empty( $variation['variation_id'] ) ? $variation['variation_id'] : 0;
				$variation_obj       = self::$woocommerce->getProduct( $variation_id );
				$product_variation[] = [
					'id'                     => $variation_id,
					'title'                  => self::$woocommerce->getItemTitle( $variation_obj ),
					'display_name'           => self::$woocommerce->isMethodExists( $variation_obj, 'get_name' ) ? $variation_obj->get_name() : '',
					'description'            => self::$woocommerce->isMethodExists( $variation_obj, 'get_description' ) ? $variation_obj->get_description() : '',
					'price'                  => self::$woocommerce->getItemPrice( $variation_obj ),
					'sku'                    => self::$woocommerce->getItemSku( $variation_obj ),
					'variant_stock_quantity' => self::$woocommerce->isMethodExists( $variation_obj, 'get_stock_quantity' ) ? $variation_obj->get_stock_quantity() : 0,
					'variant_url'            => function_exists( 'get_permalink' ) ? get_permalink( $variation_id ) : '',
					'variant_image_url'      => self::$woocommerce->getProductImageSrc( $variation_obj ),
					'variant_total_sales'    => self::$woocommerce->isMethodExists( $variation_obj, 'get_total_sales' ) ? $variation_obj->get_total_sales() : 0,
					'created_at'             => $this->formatToIso8601( self::$woocommerce->isMethodExists( $variation_obj, 'get_date_created' ) ? strtotime( $variation_obj->get_date_created() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
					'updated_at'             => $this->formatToIso8601( self::$woocommerce->isMethodExists( $variation_obj, 'get_date_modified' ) ? strtotime( $variation_obj->get_date_modified() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
				];
			}
		}
		$product_category = function_exists( 'wp_get_post_terms' ) ? wp_get_post_terms( $product->get_id(), 'product_cat' ) : array();
		$category         = array_map( function ( $product_cat ) {
			return $product_cat->name;
		}, $product_category );
		$product_tag      = function_exists( 'wp_get_post_terms' ) ? wp_get_post_terms( $product->get_id(), 'product_tag' ) : array();
		$tags             = array_map( function ( $tag ) {
			return $tag->name;
		}, $product_tag );

		return [
			'id'                     => self::$woocommerce->getItemId( $product ),
			'title'                  => self::$woocommerce->getItemName( $product ),
			'description'            => self::$woocommerce->isMethodExists( $product, 'get_description' ) ? $product->get_description() : '',
			'price'                  => self::$woocommerce->getItemPrice( $product ),
			'currency'               => self::$woocommerce->getDefaultCurrency(),
			'product_url'            => function_exists( 'get_permalink' ) ? get_permalink( $product_id ) : '',
			'product_type'           => self::$woocommerce->isMethodExists( $product, 'get_type' ) ? $product->get_type() : '',
			'created_at'             => $this->formatToIso8601( self::$woocommerce->isMethodExists( $product, 'get_date_created' ) ? strtotime( $product->get_date_created() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
			'updated_at'             => $this->formatToIso8601( self::$woocommerce->isMethodExists( $product, 'get_date_modified' ) ? strtotime( $product->get_date_modified() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
			'status'                 => self::$woocommerce->isMethodExists( $product, 'get_status' ) ? $product->get_status() : '',
			'product_sku'            => self::$woocommerce->getItemSku( $product ),
			'product_stock_quantity' => self::$woocommerce->isMethodExists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : 0,
			'product_image_url'      => self::$woocommerce->getProductImageSrc( $product ),
			'product_category'       => ! empty( $category ) ? $category : array(),
			'product_tag'            => ! empty( $tags ) ? $tags : array(),
			'total_sales'            => self::$woocommerce->isMethodExists( $product, 'get_total_sales' ) ? $product->get_total_sales() : 0,
			'variants'               => $product_variation,
		];
	}

}