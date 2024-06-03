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

		return self::post( $url, '', [ 'app_id' => $api_key ] );
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
	 * Encrypt the cart
	 *
	 * @param $data
	 * @param $secret
	 *
	 * @return string
	 */
	public static function encryptData( $data, $secret = null ) {

		if ( extension_loaded( 'openssl' ) ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				$data = wp_json_encode( $data );
			}
			try {
				if ( empty( $secret ) ) {
					$secret = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );
				}
				$iv_len          = openssl_cipher_iv_length( self::CIPHER_METHOD );
				$iv              = openssl_random_pseudo_bytes( $iv_len );
				$cipher_text_raw = openssl_encrypt( $data, self::CIPHER_METHOD, $secret, OPENSSL_RAW_DATA, $iv );
				$hmac            = hash_hmac( self::HMAC_ALGORITHM, $cipher_text_raw, $secret, true );

				return base64_encode( bin2hex( $iv ) . ':retainful:' . bin2hex( $hmac ) . ':retainful:' . bin2hex( $cipher_text_raw ) );
			} catch ( Exception $e ) {
				return null;
			}
		}

		return null;
	}
}