<?php

namespace RNOC\App\Modules\AbandonedCart\Traits;

defined( 'ABSPATH' ) || exit;

use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\WC;

trait SyncData {
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
	function getLineItemTotal( $item ) {
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
		// add hash for easier verification that the checkout URL hasn't been tampered with
		$hash = hash_hmac( self::HMAC_ALGORITHM, $data, $secret );
		$url  = self::getRetainfulApiUrl();

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
				$iv_len          = openssl_cipher_iv_length( self::CIPHER_METHOD );
				$iv              = openssl_random_pseudo_bytes( $iv_len );
				$cipher_text_raw = openssl_encrypt( $data, self::CIPHER_METHOD, $secret, OPENSSL_RAW_DATA, $iv );
				$hmac            = hash_hmac( self::HMAC_ALGORITHM, $cipher_text_raw, $secret, true );

				return base64_encode( bin2hex( $iv ) . ':retainful:' . bin2hex( $hmac ) . ':retainful:' . bin2hex( $cipher_text_raw ) );
			} catch ( \Exception $e ) {
				return null;
			}
		}

		return null;
	}
}