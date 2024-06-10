<?php

namespace RNOC\App\Modules\AbandonedCart;


use RNOC\App\Helpers\Settings;

defined( 'ABSPATH' ) || exit;

class Request {
	protected static $app_url = 'https://app.retainful.com/';
	protected static $api_url = 'https://api.retainful.com/v1/';


	/** The cipher method name to use to encrypt the cart data */
	const CIPHER_METHOD = 'AES256';
	/** The HMAC hash algorithm to use to sign the encrypted cart data */
	const HMAC_ALGORITHM = 'sha256';

	/**
	 * Get api url.
	 *
	 * @return string
	 */
	public static function getApiUrl() {
		return apply_filters( 'retainful_domain_url', self::$api_url );
	}

	/**
	 * Get abandoned cart api url.
	 *
	 * @return string
	 */
	public static function getAbandonedCartApiUrl() {
		$url = self::getApiUrl() . 'woocommerce/';

		return apply_filters( 'retainful_abandoned_cart_api_url', $url );
	}

	/**
	 * Connect to RetainFul api.
	 *
	 * @param string $api_key Api key.
	 * @param array $data Request data.
	 *
	 * @return array
	 */
	public static function connect( $api_key, $data ) {

		if ( empty( $api_key ) || empty( $data ) || ! is_array( $data ) ) {
			return [];
		}
		$url = self::getApiUrl() . 'app/' . $api_key;

		$headers = [
			'api_key'      => $api_key,
			'Content-Type' => 'application/json'
		];


		return self::post( $url, $data, $headers );
	}

	/**
	 * Synchronise cart data.
	 *
	 * @param string $api_key Api key.
	 * @param array $data Request data.
	 * @param array $extra_headers Extra headers.
	 *
	 * @return array
	 */
	public static function syncCart( $api_key, $data, $extra_headers = [] ) {
		if ( empty( $api_key ) || empty( $data ) || ! is_array( $data ) ) {
			return [];
		}

		$url     = self::getAbandonedCartApiUrl() . 'webhooks/checkout';
		$headers = [
			'app_id'       => $api_key,
			'Content-Type' => 'application/json'
		];
		if ( is_array( $extra_headers ) && ! empty( $extra_headers ) ) {
			$headers = array_merge( $headers, $extra_headers );
		}

		return self::post( $url, $data, $headers );
	}

	/**
	 * Get retrieve cart details.
	 *
	 * @param string $api_key Api key.
	 * @param string $cart_token Cart token.
	 *
	 * @return array
	 */
	public static function getRetrieveCart( $api_key, $cart_token ) {
		if ( empty( $api_key ) || empty( $cart_token ) ) {
			return [];
		}
		$url = self::getAbandonedCartApiUrl() . 'abandoned_checkouts/' . $cart_token;


		return self::get( $url, [ 'app_id' => $api_key ] );
	}

	/**
	 * Send post request.
	 *
	 * @param string $url Request url.
	 * @param array|string $body Request Body.
	 * @param array $headers Headers.
	 *
	 * @return array|mixed|string|\WP_Error
	 */
	protected static function post( $url, $body, $headers ) {

		if ( is_array( $body ) || is_object( $body ) ) {
			$body = json_encode( $body );
		}
		$response = [];
		try {
			$options = [
				'body'    => $body,
				'timeout' => '30',
				'headers' => $headers
			];

			$response = wp_remote_post( $url, $options );
			$response = wp_remote_retrieve_body( $response );
			if ( is_string( $response ) ) {
				$response = json_decode( $response, true );
			}

		} catch ( \Exception $e ) {

		}

		return $response;
	}

	/**
	 * Get request.
	 *
	 * @param string $url Request url.
	 * @param array $headers Headers.
	 *
	 * @return array|mixed|string|\WP_Error
	 */
	protected static function get( $url, $headers, $blocking = true ) {

		$response = [];
		try {
			$args     = array(
				'timeout'     => '30',
				'httpversion' => '1.0',
				'blocking'    => $blocking,
				'headers'     => $headers
			);
			$response = wp_remote_get( $url, $args );
			$response = wp_remote_retrieve_body( $response );
			if ( is_string( $response ) ) {
				$response = json_decode( $response, true );
			}

		} catch ( \Exception $e ) {

		}


		return $response;
	}
}