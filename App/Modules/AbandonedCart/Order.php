<?php

namespace RNOC\App\Modules\AbandonedCart;

use RNOC\App\Helpers\Settings;
use RNOC\App\Modules\AbandonedCart\Traits\SyncData;

defined( 'ABSPATH' ) || exit;

class Order {
	use SyncData;

	/**
	 * Backend order change time synchronization.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	public function orderUpdatedShopBackend( $order_id ) {
		if ( ! is_admin() || $order_id <= 0 ) {
			return;
		}
		if ( Settings::get( RNOC_PLUGIN_PREFIX . 'enable_background_order_sync', 'no' ) == 'yes' ) {
			return;
		}
		$this->syncOrder( $order_id );
	}

	/**
	 * synchronize order.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	function syncOrder( $order_id ) {
		if ( $order_id <= 0 || Settings::get( RNOC_PLUGIN_PREFIX . 'enable_background_order_sync', 'no' ) == 'yes' ) {
			return;
		}

		$order      = \RNOC\App\Helpers\Order::getOrder( $order_id );
		$cart_token = apply_filters( 'rnoc_sync_order_change_order_token', \RNOC\App\Helpers\Order::getOrderMeta( self::$cart_token_key_for_db, $order ), $order_id, $this );

		if ( empty( $cart_token ) ) {
			return;
		}
		$order_status       = \RNOC\App\Helpers\Order::getStatus( $order );
		$order_cancelled_at = \RNOC\App\Helpers\Order::getOrderMeta( self::$order_cancelled_date_key_for_db, $order );
		if ( ! $order_cancelled_at && $order_status == 'cancelled' ) {

		}
	}
}