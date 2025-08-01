<?php

namespace Rnoc\Retainful\Api\Imports;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Rnoc\Retainful\Api\AbandonedCart\Order;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;
use Valitron\Validator;

class Products extends Order {
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
		$since_id = ! empty( $params['since_id'] ) ? $params['since_id'] : 10;
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT {$wpdb->prefix}posts.ID FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_status NOT IN (%s, %s, %s) ORDER BY ID ASC LIMIT %d", array($since_id, 'trash', 'auto-draft', 'draft', (int) $limit ) ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

	}


	/**
	 * get order count
	 * @return string|null
	 */
	protected function getProductCount() {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT {$wpdb->prefix}posts.ID) FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_status NOT IN (%s, %s, %s)", array( 0, 'trash', 'auto-draft', 'draft' ) ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
			'digest' => ''
		);
		$params  = wp_parse_args( $request_params, $default_request_params );
		$validator = new Validator($params);
		$validator->rule('required', ['limit', 'status', 'digest'])->message('{field} is required');
		$validator->rule('integer', ['limit', 'since_id'])->message('This {field} contains invalid value');
		$validator->rule('min', 'limit', 1)->message('Limit must be at least 1');
		$validator->rule('max', 'limit', 1000)->message('Limit must not exceed 1000');
		// Run validation
		if (!$validator->validate()) {
			$error_message = [];
			foreach ($validator->errors() as $field => $messages) {
				foreach ($messages as $msg) {
					$error_message[] = $msg;
				}
			}
			$status   = 400;
			$response = array(
				'success'       => false,
				'RESPONSE_CODE' => 'SECURITY_BREACH',
				'message'       => implode(' ,', $error_message),
			);
			return new \WP_REST_Response( $response, $status );
		}
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
		$rest_api = new RestApi();
		try {
			$webhook      = new \WC_Webhook( $webhook_id );
			$topic        = $webhook->get_topic();
			if(!in_array($topic,['product.updated', 'product.created', 'product.deleted'])){
				return $http_args;
			}
			$topic_status = self::$settings->getWebHookStatus();

			if ( ! isset( $topic_status[ $topic ] ) || ! $topic_status[ $topic ] ) {
				return $http_args;
			}
			$delivery_url      = $webhook->get_delivery_url();
			$site_delivery_url = self::$settings->getDeliveryUrl( $topic );

			if ( $delivery_url != $site_delivery_url || $product_id <= 0 ) {
				return $http_args;
			}
			$product           = self::$woocommerce->getProduct( $product_id );
			$product_type = self::$woocommerce->isMethodExists( $product, 'get_type' ) ? $product->get_type() : '';
			if(in_array($topic,['product.deleted','product.updated','product.created']) && $product_type == 'variation') {
				return $http_args;
			}
			$product_data = $this->setProductData( $product_id );
			if ( empty( $product_data['Id'] ) || empty( $product_data['CreatedAt'] ) ) {
				self::$settings->logMessage( $product_data, 'API Product data missing' );
				$status   = 400;
				$response = [
					'success'       => false,
					'RESPONSE_CODE' => 'DATA_MISSING',
					'message'       => 'Invalid data!'
				];
				return new \WP_REST_Response( $response, $status );
			}

			$product_data['digest']     = $this->hashToken( array(
				$product_data['Id'],
				$product_data['CreatedAt'],
				$product_data['Title']
			) );
			$product_data['eventType'] = $topic;
			if($topic == 'product.deleted'){
				$product_data['DeletedAt'] = $this->formatToIso8601(strtotime(current_time('Y-m-d H:i:s')));
			}

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
					'data' => $rest_api->encryptData($product_data)
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
		if ( self::$woocommerce->isMethodExists( $product,'is_type') && $product->is_type( 'variable' ) ) {
			$variations = self::$woocommerce->isMethodExists( $product, 'get_children' ) ? $product->get_children() : array();
			foreach ( $variations as $variation_id ) {
				//$variation_id        = is_array( $variation_id ) && ! empty( $variation['variation_id'] ) ? $variation['variation_id'] : 0;
				$variation_obj       = self::$woocommerce->getProduct( $variation_id );
				if( self::$woocommerce->isMethodExists( $variation_obj, 'get_status' )  && $variation_obj->get_status() != 'publish') continue;
				$image_id  = $variation_obj->get_image_id();
				$product_variation[] = [
					'Id'                     => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : '',
					'ExternalVariantId'      => $variation_id,
					'AppId'                  =>  self::$settings->getApiKey(),
					'ExternalProductId'      =>  $variation_obj->get_parent_id(),
					'Title'                  => self::$woocommerce->getItemTitle( $variation_obj ),
					'DisplayName'            => self::$woocommerce->isMethodExists( $variation_obj, 'get_name' ) ? $variation_obj->get_name() : '',
					'Url'                    => self::$woocommerce->isMethodExists( $product, 'get_id' ) && function_exists( 'get_permalink' ) ? \get_permalink( $variation_id ) : '',
					'Price'                  => self::$woocommerce->getItemPrice( $variation_obj ),
					'Sku'                    => self::$woocommerce->getItemSku( $variation_obj ),
					'CompareAtPrice'         => self::$woocommerce->getItemRegularPrice( $variation_obj ),
					'ImageUrl'               => function_exists('wp_get_attachment_url') && !empty($image_id) ?  \wp_get_attachment_url($image_id) : '',
					'InventoryQuantity'      => self::$woocommerce->isMethodExists( $variation_obj, 'get_stock_quantity' ) ? $variation_obj->get_stock_quantity() : 0,
					'InventoryPolicy'        => $this->getStockStatus($variation_obj),
					'InventoryStatus'        => self::$woocommerce->isMethodExists( $variation_obj, 'get_manage_stock' ) && ! empty( $variation_obj->get_manage_stock() ) && $variation_obj->get_manage_stock(),
					'CreatedAt'              => $this->formatToIso8601( self::$woocommerce->isMethodExists( $variation_obj, 'get_date_created' ) ? strtotime( $variation_obj->get_date_created() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
					'UpdatedAt'              => $this->formatToIso8601( self::$woocommerce->isMethodExists( $variation_obj, 'get_date_modified' ) ? strtotime( $variation_obj->get_date_modified() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
					"DeletedAt"              => '',
				];
			}
		}else{
			$product_variation[] = [
				'Id'                     => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : '',
				'ExternalVariantId'      => (int)$product_id,
				'AppId'                  =>  self::$settings->getApiKey(),
				'ExternalProductId'      => (int)$product_id,
				'Title'                  => self::$woocommerce->getItemTitle( $product ),
				'DisplayName'            => self::$woocommerce->isMethodExists( $product, 'get_name' ) ? $product->get_name() : '',
				'Url'                    => self::$woocommerce->isMethodExists( $product, 'get_id' ) && function_exists( 'get_permalink' ) ? \get_permalink( $product->get_id() ) : '',
				'Price'                  => (int)self::$woocommerce->getItemPrice( $product ),
				'Sku'                    => self::$woocommerce->getItemSku( $product ),
				'CompareAtPrice'         => self::$woocommerce->getItemRegularPrice($product),
				'ImageUrl'               => self::$woocommerce->getProductImageSrc( $product ),
				'InventoryQuantity'      => self::$woocommerce->isMethodExists( $product, 'get_stock_quantity' ) ?( !empty($product->get_stock_quantity()) ?$product->get_stock_quantity(): 0)  : 0,
				'InventoryPolicy'        => $this->getStockStatus($product),
				'InventoryStatus'        => self::$woocommerce->isMethodExists( $product, 'get_manage_stock' ) && ! empty( $product->get_manage_stock() ) && $product->get_manage_stock(),
				'CreatedAt'              => $this->formatToIso8601( self::$woocommerce->isMethodExists( $product, 'get_date_created' ) ? strtotime( $product->get_date_created() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
				'UpdatedAt'              => $this->formatToIso8601( self::$woocommerce->isMethodExists( $product, 'get_date_modified' ) ? strtotime( $product->get_date_modified() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
				"DeletedAt"              => '',
			];
		}

		$product_tag      = self::$woocommerce->isMethodExists( $product, 'get_id' ) && function_exists( 'wp_get_post_terms' ) ? wp_get_post_terms( $product->get_id(), 'product_tag' ) : array();
		$tags             = array_map( function ( $tag ) {
			return $tag->name;
		}, $product_tag );

		return [
			'Id'                     => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : '',
			'ExternalProductId'      => self::$woocommerce->getItemId( $product ),
			'AppId'                  =>  self::$settings->getApiKey(),
			'ShopId'                 =>  '',// need to check,
			'Title'                  => self::$woocommerce->getItemName( $product ),
			'Description'            => self::$woocommerce->isMethodExists( $product, 'get_description' ) ? $product->get_description() : '',
			'ProductSku'             => self::$woocommerce->getItemSku( $product ),
			'ProductUrl'             => self::$woocommerce->isMethodExists( $product, 'get_id' ) && function_exists( 'get_permalink' ) ? \get_permalink( $product->get_id() ) : '',
			'ProductStatus'          => self::$woocommerce->isMethodExists( $product, 'get_status' ) ? $product->get_status() : '',
			'ProductTags'            => ! empty( $tags ) ? $tags : array(),
			'ProductType'            =>  self::$woocommerce->isMethodExists( $product, 'get_type' ) ? $product->get_type() : '',//"snowboard",,
			'ProductCreatedAt'       => $this->formatToIso8601( self::$woocommerce->isMethodExists( $product, 'get_date_created' ) ? strtotime( $product->get_date_created() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
			'ProductUpdatedAt'       => $this->formatToIso8601( self::$woocommerce->isMethodExists( $product, 'get_date_modified' ) ? strtotime( $product->get_date_modified() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
			'ProductPublishedAt'     => $this->formatToIso8601( self::$woocommerce->isMethodExists( $product, 'get_date_created' ) ? strtotime( $product->get_date_created() ) : strtotime( '0000-00-00T00:00:00+00:00' ) ),
			'CreatedAt'              => $this->formatToIso8601(strtotime(gmdate('Y-m-d H:i:s'))),
			'UpdatedAt'              => $this->formatToIso8601(strtotime(gmdate('Y-m-d H:i:s'))),
			'DeletedAt'              => null,
			'ProductStockQuantity'   => self::$woocommerce->isMethodExists( $product, 'get_stock_quantity' ) ? (!empty($product->get_stock_quantity()) ? $product->get_stock_quantity() : 0 ) : 0,
			'Vendor'                 => '', // need to check
			'Price'                  => (int) self::$woocommerce->getItemPrice( $product ),
			'Currency'               => self::$woocommerce->getDefaultCurrency(),
			'ImageUrl'               => self::$woocommerce->getProductImageSrc( $product ),
			'PriceRange'             => $this->getPriceRange( $product ),
			'TotalOrderedCount'      =>self::$woocommerce->isMethodExists( $product, 'get_total_sales' ) ? $product->get_total_sales() : 0,
			'MetaData'               => '',
			'Variants'               => $product_variation,
		];
	}

	public  function getStockStatus( $product ) {
		$stock_status =  self::$woocommerce->isMethodExists( $product, 'get_manage_stock' ) && ! empty( $product->get_manage_stock() ) && $product->get_manage_stock();
		if($stock_status) {
			return 'CONTINUE';
		}
		return 'DENY';
	}
	public function getPriceRange($product){
		if(empty($product) || !function_exists('wc_price')){
			return '';
		}
		if ($product->is_type('variable')) {
			return [
				'min' => (int) $product->get_variation_price('min'),
				'max' => (int) $product->get_variation_price('max'),
			];
		} else {
			return [
				'min' => (int) $product->get_price(),
				'max' => (int) $product->get_price(),
			];
		}
	}

}