<?php

namespace Rnoc\App\Modules\Entity;

use Rnoc\Retainful\OrderCoupon;
use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Helpers\Input;
use Rnoc\App\Helpers\WC;
use Rnoc\App\Helpers\Currency;
use Rnoc\App\Helpers\Settings as SettingsHelper;

class Order extends RestApi {

	/**
	 * set retainful data order.
	 *
	 * @return void
	 */
	public static function setRetainfulOrderData() {
		$draft_order = WC::getSession( 'store_api_draft_order' );
		if ( ! empty( $draft_order ) && intval( $draft_order ) > 0 ) {
			$cart_token             = self::retrieveCartToken();
			$draft_order_cart_token = WC::getPostMeta( intval( $draft_order ), self::$cart_token_key_for_db );
			if ( empty( $draft_order_cart_token ) && empty( $cart_token ) ) {
				$cart_token = self::getCartToken();
			}
			self::purchaseComplete( intval( $draft_order ) );
		}
	}

	/**
	 * purchase complete
	 *
	 * @param $order_id
	 *
	 * @return null
	 */
	public static function purchaseComplete() {
		if ( empty( $order_id ) ) {
			return null;
		}
		//TODO remove carthash from session after success place order
		$cart_token = self::retrieveCartToken();
		Settings::logMessage( array( "cart_token" => $cart_token, "order_id" => $order_id ), 'purchaseComplete' );
		if ( ! empty( $cart_token ) ) {
			$cart_created_at            = self::userCartCreatedAt();
			$user_ip                    = self::retrieveUserIp();
			$is_buyer_accepts_marketing = ( self::isBuyerAcceptsMarketing() ) ? 1 : 0;
			//$cart_hash = self::$storage->getValue('rnoc_current_cart_hash');
			$cart_hash            = self::generateCartHash();
			$recovered_at         = SettingsHelper::initStorage()->getValue( 'rnoc_recovered_at' );
			$recovered_by         = SettingsHelper::initStorage()->getValue( 'rnoc_recovered_by_retainful' );
			$recovered_cart_token = SettingsHelper::initStorage()->getValue( 'rnoc_recovered_cart_token' );
			$user_agent           = self::getUserAgent();
			$user_accept_language = self::getUserAcceptLanguage();

			$order_object = WC::getOrder( $order_id );
			if ( is_object( $order_object ) && ! empty( $order_object ) ) {
				$order_object->update_meta_data( self::$cart_token_key_for_db, $cart_token );
				$order_object->update_meta_data( self::$cart_hash_key_for_db, $cart_hash );
				$order_object->update_meta_data( self::$cart_tracking_started_key_for_db, $cart_created_at );
				$order_object->update_meta_data( self::$user_ip_key_for_db, $user_ip );
				$order_object->update_meta_data( self::$accepts_marketing_key_for_db, $is_buyer_accepts_marketing );
				$order_object->update_meta_data( '_rnoc_recovered_at', $recovered_at );
				$order_object->update_meta_data( '_rnoc_recovered_by', $recovered_by );
				$order_object->update_meta_data( '_rnoc_recovered_cart_token', $recovered_cart_token );
				$order_object->update_meta_data( '_rnoc_get_http_user_agent', $user_agent );
				$order_object->update_meta_data( '_rnoc_get_http_accept_language', $user_accept_language );
				$order_object->update_meta_data( self::$pending_recovery_key_for_db, true );
				$order_object->save();
			}

			//$this->markOrderAsPendingRecovery($order_id);
			//$this->unsetOrderTempData();
		}

		return null;
	}

}