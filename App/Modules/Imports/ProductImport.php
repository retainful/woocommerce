<?php

namespace RNOC\App\Modules\Imports;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use RNOC\App\Helpers\Product;
use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\Util;
use RNOC\App\Helpers\WC;
use RNOC\App\Helpers\Webhook;
use RNOC\App\Helpers\WP;

class ProductImport {
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
		);
		$params                 = function_exists( 'wp_parse_args' ) ? wp_parse_args( $request_params, $default_request_params ) : '';
		if ( empty( $params['digest'] ) || ! is_string( $params['digest'] ) || $params['status'] != 'any' ) {
			$status   = 400;
			$response = [
				'success'       => false,
				'RESPONSE_CODE' => 'DATA_MISSING',
				'message'       => 'Invalid data!'
			];

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
			'total_count'   => (int) $this->getProductCount( $params )
		];
		$status   = 200;

		return new \WP_REST_Response( $response, $status );
	}

	/**
	 * get order count
	 * @return string|null
	 */
	protected function getProductCount() {
		global $wpdb;
		$query = $wpdb->prepare( "SELECT COUNT(DISTINCT {$wpdb->prefix}posts.ID) FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_status != %s", [
			0,
			'trash'
		] );

		return $wpdb->get_var( $query );
	}


	/**
	 * Create coupons
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function getSyncProducts( \WP_REST_Request $request ) {
		$request_params = $request->get_params();

		$default_request_params = array(
			'limit'  => 10,
			'id'     => 0,
			'status' => 'any',
			'digest' => ''
		);
		$params                 = function_exists( 'wp_parse_args' ) ? wp_parse_args( $request_params, $default_request_params ) : [];

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
		$products = $this->getProducts( $params );
		//Do like his response
		$response = [
			'success'       => true,
			'RESPONSE_CODE' => 'Ok',
			'items'         => []
		];
		foreach ( $products as $product_data ) {
			$response['items'][] = self::setProductData( $product_data->ID );
		}
		$status = 200;

		return new \WP_REST_Response( $response, $status );
	}

	/**
	 * get orders
	 *
	 * @param array $params params.
	 *
	 * @return array
	 */
	protected function getProducts( $params ) {
		if ( empty( $params ) ) {
			return array();
		}
		$limit = ! empty( $params['limit'] ) ? $params['limit'] : 10;
		global $wpdb;
		$query = $wpdb->prepare( "SELECT {$wpdb->prefix}posts.ID FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_status != %s ORDER BY ID ASC LIMIT %d", [
			0,
			'trash',
			(int) $limit
		] );

		return $wpdb->get_results( $query );

	}

	/**
	 * Set the product data.
	 *
	 * @param int $product_id woocommerce product id.
	 *
	 * @return array|void
	 */
	protected static function setProductData( $product_id ) {
		if ( empty( $product_id ) ) {
			return;
		}
		$product           = Product::getProduct( $product_id );
		$product_variation = array();
		if ( $product->is_type( 'variable' ) ) {
			$variations = Util::isMethodExists( $product, 'get_available_variations' ) ? $product->get_available_variations() : array();

			foreach ( $variations as $variation ) {
				$variation_id        = is_array( $variation ) && ! empty( $variation['variation_id'] ) ? $variation['variation_id'] : 0;
				$variation_obj       = Product::getProduct( $variation_id );
				$product_variation[] = [
					'id'                     => $variation_id,
					'title'                  => product::getItemTitle( $variation_obj ),
					'display_name'           => Util::isMethodExists( $variation_obj, 'get_name' ) ? $variation_obj->get_name() : '',
					'description'            => Util::isMethodExists( $variation_obj, 'get_description' ) ? $variation_obj->get_description() : '',
					'price'                  => Product::getItemPrice( $variation_obj ),
					'sku'                    => Product::getItemSku( $variation_obj ),
					'variant_stock_quantity' => Util::isMethodExists( $variation_obj, 'get_stock_quantity' ) ? $variation_obj->get_stock_quantity() : 0,
					'variant_url'            => function_exists( 'get_permalink' ) ? get_permalink( $variation_id ) : '',
					'variant_image_url'      => Product::getProductImageSrc( $variation_obj ),
					'variant_total_sales'    => Util::isMethodExists( $variation_obj, 'get_total_sales' ) ? $variation_obj->get_total_sales() : 0,
					'created_at'             => WP::formatToIso8601( Util::isMethodExists( $variation_obj, 'get_date_created' ) ? strtotime( $variation_obj->get_date_created() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
					'updated_at'             => WP::formatToIso8601( Util::isMethodExists( $variation_obj, 'get_date_modified' ) ? strtotime( $variation_obj->get_date_modified() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
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
			'id'                     => Product::getItemId( $product ),
			'title'                  => Product::getItemName( $product ),
			'description'            => Util::isMethodExists( $product, 'get_description' ) ? $product->get_description() : '',
			'price'                  => Product::getItemPrice( $product ),
			'currency'               => WC::getDefaultCurrency(),
			'product_url'            => function_exists( 'get_permalink' ) ? get_permalink( $product_id ) : '',
			'product_type'           => Util::isMethodExists( $product, 'get_type' ) ? $product->get_type() : '',
			'created_at'             => WP::formatToIso8601( Util::isMethodExists( $product, 'get_date_created' ) ? strtotime( $product->get_date_created() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
			'updated_at'             => WP::formatToIso8601( Util::isMethodExists( $product, 'get_date_modified' ) ? strtotime( $product->get_date_modified() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
			'status'                 => Util::isMethodExists( $product, 'get_status' ) ? $product->get_status() : '',
			'product_sku'            => Product::getItemSku( $product ),
			'product_stock_quantity' => Util::isMethodExists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : 0,
			'product_image_url'      => Product::getProductImageSrc( $product ),
			'product_category'       => ! empty( $category ) ? $category : array(),
			'product_tag'            => ! empty( $tags ) ? $tags : array(),
			'total_sales'            => Util::isMethodExists( $product, 'get_total_sales' ) ? $product->get_total_sales() : 0,
			'variants'               => $product_variation,
		];
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
	public static function changeWebHookHeaderProduct( $http_args, $product_id, $webhook_id ) {
		$is_app_connected = Settings::get( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0, 'license' );
		$secret           = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );

		if ( $webhook_id <= 0 || ! class_exists( 'WC_Webhook' ) || ! $is_app_connected ) {
			return $http_args;
		}

		try {
			$webhook      = new \WC_Webhook( $webhook_id );
			$topic        = $webhook->get_topic();
			$topic_status = Webhook::getWebHookStatus();
			if ( ! isset( $topic_status[ $topic ] ) || ! $topic_status[ $topic ] || ! in_array( $topic, [
					'product.created',
					'product.updated',
					'product.deleted'
				] ) ) {
				return $http_args;
			}

			$delivery_url      = $webhook->get_delivery_url();
			$site_delivery_url = Webhook::getDeliveryUrl( $topic );
			if ( $delivery_url != $site_delivery_url || $product_id <= 0 ) {
				return $http_args;
			}
			$product_data = self::setProductData( $product_id );

			if ( is_array( $product_data['id'] ) || empty( $product_data['created_at'] ) ) {
				$status   = 400;
				$response = array(
					'success'       => false,
					'RESPONSE_CODE' => 'DATA_MISSING',
					'message'       => 'Invalid data!'
				);

				return new \WP_REST_Response( $response, $status );
			}
			$product_data['digest']     = Settings::isHashMatches( $secret, array(
				$product_data['id'],
				$product_data['created_at'],
				$product_data['title']
			) );
			$product_data['event_type'] = $topic;
			if ( ! empty( $product_data ) ) {
				$app_id        = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '', 'license' );
				$extra_headers = array(
					"X-Retainful-Version" => RNOC_VERSION,
					"app_id"              => $app_id,
					"Content-Type"        => 'application/json'
				);
				foreach ( $extra_headers as $key => $value ) {
					$http_args['headers'][ $key ] = $value;
				}
				$body = array(
					'data' => $product_data
				);

				$http_args['body'] = trim( wp_json_encode( $body ) );
			}
		} catch ( Exception $e ) {

		}

		return $http_args;
	}


}