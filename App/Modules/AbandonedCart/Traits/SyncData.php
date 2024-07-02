<?php

namespace RNOC\App\Modules\AbandonedCart\Traits;

defined( 'ABSPATH' ) || exit;

use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\WC;

trait SyncData {
	protected static $hmac_algorithm = 'sha256';
	protected static $cipher_method = 'AES256';
	protected static $cart_token_key_for_db = '_rnoc_user_cart_token';
	protected static $cart_token_key = 'rnoc_user_cart_token';
	protected static $cart_tracking_started_key = 'rnoc_cart_created_at';
	protected static $cart_tracking_started_key_for_db = '_rnoc_cart_tracking_started_at';
	protected static $previous_cart_hash_key = 'rnoc_previous_cart_hash';
	protected static $order_cancelled_date_key_for_db = "_rnoc_order_cancelled_at";
	protected static $pending_recovery_key = "rnoc_is_pending_recovery";
	protected static $pending_recovery_key_for_db = "_rnoc_is_pending_recovery";
	protected static $cart_hash_key_for_db = "_rnoc_cart_hash";

	protected static $user_ip_key_for_db = "_rnoc_user_ip_address";
	protected static $order_placed_date_key_for_db = "_rnoc_order_placed_at";
	protected static $order_recovered_key_for_db = "_rnoc_order_recovered";
	protected static $accepts_marketing_key_for_db = "_rnoc_is_buyer_accepts_marketing";

	/**
	 * Remove temporary storage data.
	 *
	 * @return void
	 */
	public static function removeTmpStorageData() {
		$storage = Settings::getStorage();
		$storage->remove( self::$cart_token_key );
		$storage->remove( self::$pending_recovery_key );
		$storage->remove( self::$cart_tracking_started_key );
		$storage->remove( self::$previous_cart_hash_key );
		//This was set in plugin since 2.0.4
		$storage->remove( 'rnoc_force_refresh_cart' );
		$storage->remove( 'rnoc_recovered_at' );
		$storage->remove( 'rnoc_current_cart_hash' );
		$storage->remove( 'rnoc_recovered_by_retainful' );
		$storage->remove( 'rnoc_recovered_cart_token' );
	}

	/**
	 * Can track abandoned cart.
	 *
	 * @param string $ip_address Ip address.
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	public static function canTrackAbandonedCart( $ip_address = null, $order = null ) {
		if ( apply_filters( 'rnoc_is_cart_has_valid_ip', true, $ip_address ) && apply_filters( 'rnoc_can_track_abandoned_carts', true, $order ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get line item total.
	 *
	 * @param array $item Line item.
	 *
	 * @return float
	 */
	public function getLineItemTotal( $item ) {
		$line_total     = ( isset( $item['line_total'] ) && ! empty( $item['line_total'] ) ) ? $item['line_total'] : 0;
		$line_total_tax = 0;
		if ( ! WC::isPriceExcludingTax() ) {
			$line_total_tax = ( isset( $item['line_tax'] ) && ! empty( $item['line_tax'] ) ) ? $item['line_tax'] : 0;
		}
		$total = $line_total + $line_total_tax;

		return apply_filters( 'retainful_get_line_item_total', $total, $line_total, $line_total_tax, $item, $this );
	}

	/**
	 * Is valid order status.
	 *
	 * @param string $order_status Order status.
	 *
	 * @return bool
	 */
	public static function isValidOrderStatus( $order_status ) {
		if ( empty( $order_status ) || ! is_string( $order_status ) ) {
			return false;
		}
		$invalid_order_status = apply_filters( 'rnoc_abandoned_cart_invalid_order_statuses', [
			'pending',
			'failed',
			'checkout-draft',
			'trash',
			'cancelled',
			'refunded'
		] );

		if ( Settings::get( RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 0 ) == 1 ) {
			$invalid_order_status[] = 'on-hold';
		}

		return ! in_array( $order_status, array_unique( $invalid_order_status ) );
	}

	/**
	 * Get currency details.
	 *
	 * @param float $cart_total Cart total.
	 * @param string $current_currency_code Current currency.
	 * @param string $default_currency_code default currency.
	 *
	 * @return array
	 */
	public static function getCurrencyDetails( $cart_total, $current_currency_code, $default_currency_code ) {
		if ( $current_currency_code != $default_currency_code ) {
			$exchange_rate   = apply_filters( 'rnoc_get_currency_rate', $cart_total, $current_currency_code );
			$shop_cart_total = self::convertToCurrency( $cart_total, $exchange_rate );
		} else {
			$shop_cart_total = $cart_total;
		}
		$details = [
			'shop_money'        => [
				'amount'        => $shop_cart_total,
				'currency_code' => $default_currency_code
			],
			'presentment_money' => [
				'amount'        => $cart_total,
				'currency_code' => $current_currency_code
			]
		];

		return apply_filters( 'rnoc_get_cart_currency_details', $details, $current_currency_code, $default_currency_code );
	}

	/**
	 * Convert price.
	 *
	 * @param float $price Price.
	 * @param float $rate Convert rate.
	 *
	 * @return float
	 */
	public static function convertToCurrency( $price, $rate ) {
		if ( ! empty( $price ) && ! empty( $rate ) ) {
			return $price / $rate;
		}

		return $price;
	}

	/**
	 * Get recovery url.
	 *
	 * @param string $cart_token Cart token.
	 *
	 * @return string
	 */
	public static function getRecoveryLink( $cart_token ) {
		if ( ! is_string( $cart_token ) ) {
			return '';
		}
		$data = [ 'cart_token' => $cart_token ];
		// encode
		$data   = base64_encode( wp_json_encode( $data ) );
		$secret = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );


		$hash = hash_hmac( self::$hmac_algorithm, $data, $secret );

		$url = self::getRetainfulApiUrl();

		return esc_url_raw( add_query_arg( array( 'token' => rawurlencode( $data ), 'hash' => $hash ), $url ) );
	}

	/**
	 * Get encrypt data.
	 *
	 * @param mixed $data Data.
	 * @param string $secret Secret key.
	 *
	 * @return string|null
	 */
	public static function getEncryptData( $data, $secret = '' ) {
		if ( extension_loaded( 'openssl' ) ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				$data = wp_json_encode( $data );
			}
			try {
				if ( empty( $secret ) ) {
					$secret = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );
				}
				$iv_len          = openssl_cipher_iv_length( self::$cipher_method );
				$iv              = openssl_random_pseudo_bytes( $iv_len );
				$cipher_text_raw = openssl_encrypt( $data, self::$cipher_method, $secret, OPENSSL_RAW_DATA, $iv );
				$hmac            = hash_hmac( self::$hmac_algorithm, $cipher_text_raw, $secret, true );

				return base64_encode( bin2hex( $iv ) . ':retainful:' . bin2hex( $hmac ) . ':retainful:' . bin2hex( $cipher_text_raw ) );
			} catch ( \Exception $e ) {
				return null;
			}
		}

		return null;
	}

	/**
	 * Retrieve cart token.
	 *
	 * @param int $user_id User id.
	 *
	 * @return string
	 */
	public function retrieveCartToken( $user_id = null ) {

		if ( $user_id == null ) {
			$user_id = get_current_user_id();
		}
		if ( ! empty( $user_id ) ) {
			$token = get_user_meta( $user_id, self::$cart_token_key_for_db, true );
		} else {
			$storage = Settings::getStorage();
			$token   = $storage->get( self::$cart_token_key );
		}

		return apply_filters( 'rnoc_retrieve_cart_token', $token, $user_id, $this );
	}


	/**
	 * Get cart token.
	 *
	 * @return string
	 */
	public function getCartToken() {

		$cart_token = $this->retrieveCartToken();
		if ( empty( $cart_token ) ) {
			$cart_token = $this->generateCartToken();
			$this->setCartToken( $cart_token );
		}

		return apply_filters( 'rnoc_get_cart_token', $cart_token, $this );
	}

	/**
	 * Is allow buyer accept marketing.
	 *
	 * @return bool
	 */
	public static function isBuyerAcceptsMarketing() {
		$enable_gdpr_compliance = Settings::get( RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance', 0 );
		if ( $enable_gdpr_compliance ) {
			return in_array( WC::getSession( 'is_buyer_accepting_marketing' ), array( 1, 'true' ) );
		}

		return true;
	}

	/**
	 * Generate cart hash.
	 *
	 * @return string
	 */
	public static function generateCartHash() {
		$cart = \RNOC\App\Helpers\Cart::getCart();
		if ( empty( $cart ) ) {
			return '';
		}
		$cart_session = [];
		foreach ( $cart as $key => $values ) {
			$cart_session[ $key ] = $values;
			unset( $cart_session[ $key ]['data'] ); // Unset product object.
		}

		return $cart_session ? md5( wp_json_encode( $cart_session ) . \RNOC\App\Helpers\Cart::getCartTotal() ) : '';
	}

	/**
	 * Get retainful api url.
	 *
	 * @return string
	 */
	private static function getRetainfulApiUrl() {
		$scheme = function_exists( 'wc_site_is_https' ) && wc_site_is_https() ? 'https' : 'http';

		return get_option( 'permalink_structure' )
			? get_home_url( null, 'wc-api/retainful', $scheme )
			: add_query_arg( 'wc-api', 'retainful', get_home_url( null, null, $scheme ) );
	}


	/**
	 * Get tracking start date.
	 *
	 * @param int|null $user_id User id.
	 *
	 * @return mixed
	 */
	public static function getTrackingStartAt( $user_id = null ) {
		if ( $user_id || $user_id = get_current_user_id() ) {
			$cart_created_at = get_user_meta( $user_id, self::$cart_tracking_started_key_for_db, true );
		} else {
			$storage         = Settings::getStorage();
			$cart_created_at = $storage->get( self::$cart_tracking_started_key );
		}

		return $cart_created_at;
	}
}