<?php

namespace RNOC\App\Modules\AbandonedCart;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\WC;
use RNOC\App\Modules\AbandonedCart\Traits\SyncData;

defined( 'ABSPATH' ) || exit;

class AbandonedCart {
	use SyncData;

	const HMAC_ALGORITHM = 'sha256';
	const CIPHER_METHOD = 'AES256';

	/**
	 * Check is valid cart to track.
	 *
	 * @return bool
	 */
	public static function isValidCartToTrack() {
		$crawler_detect = new CrawlerDetect();
		if ( $crawler_detect->isCrawler() ) {
			return false;
		}

		if ( ! self::canTrackAbandonedCart() ) {
			return false;
		}

		return true;
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
	 * Generate cart token.
	 *
	 * @return string
	 */
	public static function generateCartToken() {
		try {
			$data    = random_bytes( 16 );
			$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
			$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10
			$token   = vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
		} catch ( \Exception $e ) {
			// fall back to mt_rand if random_bytes is unavailable
			$token = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				// 32 bits for "time_low"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
				// 16 bits for "time_mid"
				mt_rand( 0, 0xffff ),
				// 16 bits for "time_hi_and_version",
				// four most significant bits holds version number 4
				mt_rand( 0, 0x0fff ) | 0x4000,
				// 16 bits, 8 bits for "clk_seq_hi_res",
				// 8 bits for "clk_seq_low",
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand( 0, 0x3fff ) | 0x8000,
				// 48 bits for "node"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
			);
		}

		return md5( $token . time() );
	}

	/**
	 * Set cart token.
	 *
	 * @param string $cart_token Cart token.
	 * @param int $user_id User id.
	 *
	 * @return void
	 */
	public function setCartToken( $cart_token, $user_id = null ) {
		$cart_token     = apply_filters( 'rnoc_before_set_cart_token', $cart_token, $user_id, $this );
		$storage        = Settings::getStorage();
		$old_cart_token = $storage->get( self::$cart_token_key );
		if ( empty( $old_cart_token ) ) {
			$current_time = current_time( 'timestamp', true );
			$storage->set( self::$cart_token_key, $cart_token );
			$storage->set( self::$cart_tracking_started_key, $current_time );
			if ( ! empty( $user_id ) || $user_id = get_current_user_id() ) {
				update_user_meta( $user_id, self::$cart_token_key_for_db, $cart_token );
				self::setCartCreatedDate( $current_time, $user_id );
			}
		}
	}

	/**
	 * Set cart created date.
	 *
	 * @param int $time Time stamp.
	 * @param int|null $user_id User id.
	 *
	 * @return void
	 */
	public static function setCartCreatedDate( $time, $user_id = null ) {
		if ( empty( $time ) ) {
			$time = current_time( 'timestamp', true );
		}
		if ( ! empty( $user_id ) || $user_id = get_current_user_id() ) {
			update_user_meta( $user_id, self::$cart_tracking_started_key_for_db, $time );
		}
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

		return $cart_session ? md5( wp_json_encode( $cart_session ) . \RNOC\App\Helpers\Cart::getCartTotal( 'edit' ) ) : '';
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

	/**
	 * Get retainful api url.
	 *
	 * @return string
	 */
	private static function getRetainfulApiUrl() {
		$scheme = wc_site_is_https() ? 'https' : 'http';

		return get_option( 'permalink_structure' )
			? get_home_url( null, 'wc-api/retainful', $scheme )
			: add_query_arg( 'wc-api', 'retainful', get_home_url( null, null, $scheme ) );
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

	/**
	 * Get tracking id.
	 *
	 * @return string
	 */
	public static function getTrackingElementId() {
		return apply_filters( 'retainful_abandoned_cart_tracking_element_id', 'retainful-abandoned-cart-data' );
	}

	/**
	 * Need to track cart.
	 *
	 * @return bool
	 */
	function needToTrackCart() {
		$cart_hash       = $this->generateCartHash();
		$cart_created_at = self::getTrackingStartAt();
		if ( empty( $cart_hash ) && empty( $cart_created_at ) ) {
			return false;
		} elseif ( empty( $cart_hash ) && ! empty( $cart_created_at ) ) {
			return $this->comparePreviousCartHash( $cart_hash );
		} elseif ( ! empty( $cart_hash ) && empty( $cart_created_at ) ) {
			//TODO What if it fails to create cart created time
			$time    = current_time( 'timestamp', true );
			$storage = Settings::getStorage();
			$storage->set( self::$cart_tracking_started_key, $time );
			if ( $user_id = get_current_user_id() ) {
				self::setCartCreatedDate( $time, $user_id );
			}

			return $this->comparePreviousCartHash( $cart_hash );
		} else {
			return $this->comparePreviousCartHash( $cart_hash );
		}
	}

	/**
	 * Compare with previous cart.
	 *
	 * @param string $current_cart_hash Current cart hash.
	 *
	 * @return bool
	 */
	public static function comparePreviousCartHash( $current_cart_hash ) {
		$storage        = Settings::getStorage();
		$old_cart_hash  = $storage->get( self::$previous_cart_hash_key );
		$is_not_similar = ( $old_cart_hash != $current_cart_hash );
		if ( $is_not_similar ) {
			$storage->set( self::$previous_cart_hash_key, $current_cart_hash );
		}
		$storage->set( 'rnoc_current_cart_hash', $current_cart_hash );

		return $is_not_similar;
	}

}