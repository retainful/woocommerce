<?php

namespace RNOC\App\Modules\AbandonedCart;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\WC;
use RNOC\App\Modules\AbandonedCart\Traits\SyncData;

defined( 'ABSPATH' ) || exit;

class AbandonedCart {
	use SyncData;


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
		}
		catch ( \Exception $e ) {
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
	 * @param   string  $cart_token  Cart token.
	 * @param   int     $user_id     User id.
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
	 * @param   int       $time     Time stamp.
	 * @param   int|null  $user_id  User id.
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
	 * @param   string  $current_cart_hash  Current cart hash.
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