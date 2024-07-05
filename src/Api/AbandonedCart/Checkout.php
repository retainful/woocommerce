<?php

namespace Rnoc\Retainful\Api\AbandonedCart;

use Exception;

class Checkout extends RestApi {
	function __construct() {
		parent::__construct();
	}

	function recoverHeldOrders() {
		$recover_held_orders = apply_filters( 'rnoc_recover_held_orders', 'no' );

		return ( $recover_held_orders == "no" );
	}

	/**
	 * set retainful related data to order
	 */
	function setRetainfulOrderData() {
		$draft_order = self::$woocommerce->getSession( 'store_api_draft_order' );
		if ( ! empty( $draft_order ) && intval( $draft_order ) > 0 ) {
			$order      = self::$woocommerce->getOrder( $draft_order );
			$cart_token = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db );
			if ( empty( $cart_token ) ) {
				$cart_token             = $this->retrieveCartToken();
				$draft_order_cart_token = self::$woocommerce->getPostMeta( intval( $draft_order ), $this->cart_token_key_for_db );
				if ( empty( $draft_order_cart_token ) && empty( $cart_token ) ) {
					$cart_token = $this->getCartToken();
				}
			}
			$this->purchaseComplete( intval( $draft_order ) );
		}
	}

	/**
	 * purchase complete
	 *
	 * @param $order_id
	 *
	 * @return null
	 */
	function purchaseComplete( $order_id ) {
		if ( empty( $order_id ) ) {
			return null;
		}
		//TODO remove carthash from session after success place order
		$order_object = self::$woocommerce->getOrder( $order_id );
		$cart_token   = self::$woocommerce->getOrderMeta( $order_object, $this->cart_token_key_for_db );
		if ( empty( $cart_token ) ) {
			$cart_token = $this->retrieveCartToken();
		}

		self::$settings->logMessage( array(
			"cart_token" => $cart_token,
			"order_id"   => $order_id
		), 'purchaseComplete' );
		if ( ! empty( $cart_token ) ) {
			$cart_created_at            = $this->userCartCreatedAt();
			$user_ip                    = $this->retrieveUserIp();
			$is_buyer_accepts_marketing = ( $this->isBuyerAcceptsMarketing() ) ? 1 : 0;
			//$cart_hash = self::$storage->getValue('rnoc_current_cart_hash');
			$cart_hash            = $this->generateCartHash();
			$recovered_at         = self::$storage->getValue( 'rnoc_recovered_at' );
			$recovered_by         = self::$storage->getValue( 'rnoc_recovered_by_retainful' );
			$recovered_cart_token = self::$storage->getValue( 'rnoc_recovered_cart_token' );
			$user_agent           = $this->getUserAgent();
			$user_accept_language = $this->getUserAcceptLanguage();

			//$order_object = self::$woocommerce->getOrder( $order_id );
			if ( is_object( $order_object ) && ! empty( $order_object ) ) {
				$order_object->update_meta_data( $this->cart_token_key_for_db, $cart_token );
				$order_object->update_meta_data( $this->cart_hash_key_for_db, $cart_hash );
				$order_object->update_meta_data( $this->cart_tracking_started_key_for_db, $cart_created_at );
				$order_object->update_meta_data( $this->user_ip_key_for_db, $user_ip );
				$order_object->update_meta_data( $this->accepts_marketing_key_for_db, $is_buyer_accepts_marketing );
				$order_object->update_meta_data( '_rnoc_recovered_at', $recovered_at );
				$order_object->update_meta_data( '_rnoc_recovered_by', $recovered_by );
				$order_object->update_meta_data( '_rnoc_recovered_cart_token', $recovered_cart_token );
				$order_object->update_meta_data( '_rnoc_get_http_user_agent', $user_agent );
				$order_object->update_meta_data( '_rnoc_get_http_accept_language', $user_accept_language );
				$order_object->update_meta_data( $this->pending_recovery_key_for_db, true );
				$order_object->save();
				self::$woocommerce->setSession( 'store_api_draft_order', 0 );
				/*if ( $user_id = get_current_user_id() ) {
					delete_user_meta( $user_id, $this->cart_token_key_for_db );
				}*/
			}
			//$this->markOrderAsPendingRecovery($order_id);
			//$this->unsetOrderTempData();
		}

		return null;
	}

	/**
	 * Order had some changes
	 *
	 * @param $order_id
	 *
	 * @return void|null
	 */
	function orderUpdated( $order_id ) {
		self::$settings->logMessage( array( "order_id" => $order_id ), 'OrderUpdated ' );

		if ( $this->needInstantOrderSync() ) {
			$this->syncOrder( $order_id );
		} else {
			$this->scheduleCartSync( $order_id );
		}
	}


	/**
	 * Order has been updated from the shop backend.
	 *
	 * @param $order_id
	 *
	 * @return void|null
	 */
	function orderUpdatedShopBackend( $order_id ) {
		self::$settings->logMessage( array( "order_id" => $order_id ), 'OrderUpdatedShopBackend ' );
		if ( is_admin() ) {
			if ( $this->needInstantOrderSync() ) {
				$this->syncOrder( $order_id );
			} else {
				$this->scheduleCartSync( $order_id );
			}
		}
	}

	/**
	 * @return mixed|void
	 */
	function generateNocCouponForManualOrders() {
		$is_enabled           = self::$settings->isNextOrderCouponEnabled();
		$has_backorder_coupon = self::$settings->autoGenerateCouponsForOldOrders();
		$need_noc_coupon      = ( $is_enabled && $has_backorder_coupon && is_admin() );

		return apply_filters( 'rnoc_generate_noc_coupon_for_manual_orders', $need_noc_coupon, $this );
	}

	function changeWebHookHeader( $http_args, $order_id, $webhook_id ) {

		if ( $webhook_id <= 0 || ! class_exists( 'WC_Webhook' ) || ! self::$settings->isConnectionActive() ) {
			return $http_args;
		}

		try {
			$webhook      = new \WC_Webhook( $webhook_id );
			$topic        = $webhook->get_topic();
			$topic_status = self::$settings->getWebHookStatus();
			if ( ! isset( $topic_status[ $topic ] ) || ! $topic_status[ $topic ] ) {
				return $http_args;
			}
			$delivery_url      = $webhook->get_delivery_url();
			$site_delivery_url = self::$settings->getDeliveryUrl();
			if ( $delivery_url != $site_delivery_url || $order_id <= 0 ) {
				return $http_args;
			}
			$order = self::$woocommerce->getOrder( $order_id );
			if ( self::$woocommerce->getStatus( $order ) == 'checkout-draft' ) {
				return $http_args;
			}
			$order_obj  = new Order();
			$cart_token = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db );
			self::$settings->logMessage( array( "cart_token" => $cart_token ), 'Cart Token' );
			if ( empty( $cart_token ) ) {
				//Usually we should not force generate the cart token as this would sync all the old orders otherwise, if their status changes.
				$force_generate_cart_token = apply_filters( 'rnoc_force_generate_cart_token', false, $http_args, $order_id, $webhook_id );
				if ( $force_generate_cart_token === true ) {
					//Let's generate a token and set to the order meta
					$cart_token = $this->generateCartToken();
					self::$woocommerce->setOrderMeta( $order_id, $this->cart_token_key_for_db, $cart_token );
					self::$settings->logMessage( array( "cart_token" => $cart_token ), 'Force Generated Cart Token' );
				}
			}

			if ( empty( $cart_token ) ) {
				//bail on empty cart token
				return $http_args;
			}
			$order_data = $order_obj->getOrderData( $order );
			if ( ! empty( $cart_token ) && $order_data ) {
				$client_ip     = self::$woocommerce->getOrderMeta( $order, $this->user_ip_key_for_db );
				$token         = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db );
				$app_id        = self::$settings->getApiKey();
				$extra_headers = array(
					"X-Client-Referrer-IP" => ( ! empty( $client_ip ) ) ? $client_ip : null,
					"X-Retainful-Version"  => RNOC_VERSION,
					"X-Cart-Token"         => $token,
					"Cart-Token"           => $token,
					"app-id"               => $app_id,
					"app_id"               => $app_id,
					"Content-Type"         => 'application/json'
				);
				foreach ( $extra_headers as $key => $value ) {
					$http_args['headers'][ $key ] = $value;
				}
				$cart_hash         = $this->encryptData( $order_data );
				$body              = array(
					'data' => $cart_hash
				);
				$http_args['body'] = trim( wp_json_encode( $body ) );
			}
		}
		catch ( Exception $e ) {

		}

		return $http_args;
	}

	/**
	 * Sync the order with API
	 *
	 * @param $order_id
	 *
	 * @return void|null
	 */
	function syncOrder( $order_id ) {
		if ( empty( $order_id ) || self::$settings->isBackgroundOrderSyncEnabled() ) {
			return;
		}
		$order = self::$woocommerce->getOrder( $order_id );
		if ( self::$woocommerce->getStatus( $order ) == 'checkout-draft' ) {
			return;
		}
		$order_obj  = new Order();
		$cart_token = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db );
		$cart_token = apply_filters( 'rnoc_sync_order_change_order_token', $cart_token, $order_id, $this );
		if ( empty( $cart_token ) ) {
			return;
		}
		$order_status       = self::$woocommerce->getStatus( $order );
		$order_cancelled_at = self::$woocommerce->getOrderMeta( $order, $this->order_cancelled_date_key_for_db );
		// handle order cancellation
		if ( ! $order_cancelled_at && 'cancelled' === $order_status ) {
			$order_cancelled_at = current_time( 'timestamp', true );
			self::$woocommerce->setOrderMeta( $order_id, $this->order_cancelled_date_key_for_db, $order_cancelled_at );
			$this->unsetOrderTempData();
		}
		$order_data = $order_obj->getOrderData( $order );
		if ( empty( $order_data ) ) {
			return;
		}
		$order_data['cancelled_at'] = ( ! empty( $order_cancelled_at ) ) ? $this->formatToIso8601( $order_cancelled_at ) : null;
		self::$settings->logMessage( array( "order_id" => $order_id ), 'syncOrder' );
		$cart_hash = $this->encryptData( $order_data );
		$client_ip = self::$woocommerce->getOrderMeta( $order, $this->user_ip_key_for_db );
		if ( ! empty( $cart_hash ) ) {
			$token         = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db );
			$extra_headers = array(
				"X-Client-Referrer-IP" => ( ! empty( $client_ip ) ) ? $client_ip : null,
				"X-Retainful-Version"  => RNOC_VERSION,
				"X-Cart-Token"         => $token,
				"Cart-Token"           => $token
			);
			$this->syncCart( $cart_hash, $extra_headers );
		}
	}

	/**
	 * Sync the order
	 *
	 * @param $order_id
	 */
	function syncOrderByScheduler( $order_id ) {
		$this->syncOrder( $order_id );
	}

	/**
	 * schedule the sync of the cart
	 *
	 * @param $order_id
	 */
	function scheduleCartSync( $order_id ) {
		if ( ! apply_filters( 'rnoc_schedule_cart_sync', true ) ) {
			return;
		}
		$hook     = 'retainful_sync_abandoned_cart_order';
		$meta_key = '_rnoc_order_id';
		if ( self::$settings->hasAnyActiveScheduleExists( $hook, $order_id, $meta_key ) == false ) {
			self::$settings->scheduleEvents( $hook, current_time( 'timestamp' ) + 60, array( $meta_key => $order_id ) );
		}
	}

	/**
	 * Clear any persistent cart session data for logged in customers
	 *
	 * @param   int     $order_id  order ID
	 * @param   string  $old_status
	 * @param   string  $new_status
	 */
	public function orderStatusChanged( $order_id, $old_status, $new_status ) {
		global $wp;
		self::$settings->logMessage( array( "order_id" => $order_id ), 'orderStatusChanged' );
		try {
			// PayPal IPN request
			if ( isset( $wp->query_vars['wc-api'] ) && ! empty( $wp->query_vars['wc-api'] ) && ( 'WC_Gateway_Paypal' === $wp->query_vars['wc-api'] ) ) {
				$order = self::$woocommerce->getOrder( $order_id );
				// PayPal order is completed or authorized: clear any user session
				// data so that we don't have to rely on the thank-you page rendering
				if ( ( self::$woocommerce->isOrderPaid( $order ) || $new_status == 'on-hold' ) && ( $user_id = self::$woocommerce->getOrderUserId( $order ) ) ) {
					delete_user_meta( $user_id, '_woocommerce_persistent_cart_' . get_current_blog_id() );
					if ( $this->isPendingRecovery( $user_id ) ) {
						self::$woocommerce->setOrderMeta( $order_id, $this->pending_recovery_key_for_db, true );
					}
					if ( $this->retrieveCartToken( $user_id ) ) {
						$this->removeTempDataForUser( $user_id );
					}
				}
			}
		}
		catch ( Exception $e ) {
		}
	}

	/**
	 * Check the order is placed or not
	 *
	 * @param $order
	 * @param $old_status
	 * @param $new_status
	 *
	 * @return bool|mixed|void
	 */
	function isPlaced( $order, $old_status, $new_status ) {
		$placed = self::$woocommerce->isOrderPaid( $order ) || ( $new_status === 'on-hold' && ! $this->recoverHeldOrders() );
		$placed = apply_filters( 'rnoc_abandoned_cart_is_order_get_placed', $placed, $order, $old_status, $new_status, $this );

		return $placed;
	}

	/**
	 * HAndle order completion in order page
	 *
	 * @param $order_id
	 */
	function payPageOrderCompletion( $order_id ) {
		$this->unsetOrderTempData();
	}

	/**
	 * @param $order_id
	 */
	function checkoutOrderProcessed( $order_id ) {
		self::$settings->logMessage( array( "order_id" => $order_id ), 'checkoutOrderProcessed' );
		/* if ($this->isPendingRecovery()) {
			 $this->markOrderAsPendingRecovery($order_id);
		 }*/
		try {
			$order      = self::$woocommerce->getOrder( $order_id );
			$cart_token = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db );
			if ( empty( $cart_token ) ) {
				$cart_token = $this->retrieveCartToken();
			}
			if ( ! empty( $cart_token ) ) {
				$this->purchaseComplete( $order_id );
				$this->syncOrderToAPI( $order, $order_id );
				//$this->unsetOrderTempData();

			}
		}
		catch ( Exception $e ) {
		}
	}

	function apiCheckoutOrderProcessed( $order ) {
		if ( ! is_object( $order ) ) {
			return;
		}
		$order_id = self::$woocommerce->getOrderId( $order );
		self::$settings->logMessage( array( "order_id" => $order_id ), 'checkoutOrderProcessed' );
		/* if ($this->isPendingRecovery()) {
			 $this->markOrderAsPendingRecovery($order_id);
		 }*/
		try {
			$cart_token = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db );
			if ( empty( $cart_token ) ) {
				$cart_token = $this->retrieveCartToken();
			}
			if ( ! empty( $cart_token ) ) {
				//$order = self::$woocommerce->getOrder($order_id);
				$this->purchaseComplete( $order_id );
				$this->syncOrderToAPI( $order, $order_id );
				//$this->unsetOrderTempData();
			}
		}
		catch ( Exception $e ) {
		}
	}

	/**
	 * sync order to api
	 *
	 * @param $order
	 * @param $order_id
	 */
	function syncOrderToAPI( $order, $order_id ) {
		if ( self::$settings->isBackgroundOrderSyncEnabled() || self::$woocommerce->getStatus( $order ) == 'checkout-draft' ) {
			return;
		}
		if ( $this->needInstantOrderSync() ) {
			$order_obj = new Order();
			$cart      = $order_obj->getOrderData( $order );
			if ( ! empty( $cart ) ) {
				$cart_hash = $this->encryptData( $cart );
				//Reduce the loading speed
				$client_ip = self::$woocommerce->getOrderMeta( $order, $this->user_ip_key_for_db );
				$token     = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db );
				self::$settings->logMessage( array(
					'order_id'  => $order_id,
					'token'     => $token,
					'client_ip' => $client_ip
				), 'syncOrderToAPI' );
				if ( ! empty( $cart_hash ) ) {

					$extra_headers = array(
						"X-Client-Referrer-IP" => ( ! empty( $client_ip ) ) ? $client_ip : null,
						"X-Retainful-Version"  => RNOC_VERSION,
						"X-Cart-Token"         => $token,
						"Cart-Token"           => $token
					);

					$this->syncCart( $cart_hash, $extra_headers );
				}
			} else {
				self::$settings->logMessage( array( 'order_id' => $order_id ), 'Failed syncOrderToAPI' );
			}
		} else {
			$this->scheduleCartSync( $order_id );
		}
	}

	/**
	 * need the instant sync or not
	 * @return mixed|void
	 */
	function needInstantOrderSync() {
		return apply_filters( 'rnoc_sync_order_data_instantly_to_api', true );
	}

	/**
	 * Assign cart token for Order
	 *
	 * @param $cart_token
	 * @param $order_id
	 */
	function setOrderCartToken( $cart_token, $order_id ) {
		if ( empty( $cart_token ) ) {
			$cart_token = $this->generateCartToken();
		}
		self::$woocommerce->setOrderMeta( $order_id, $this->cart_token_key_for_db, $cart_token );
	}

	/**
	 * Update order on successful payment
	 *
	 * @param $result
	 * @param $order_id
	 *
	 * @return mixed
	 */
	function maybeUpdateOrderOnSuccessfulPayment( $result, $order_id ) {
		self::$settings->logMessage( array( "order_id" => $order_id ), 'maybeUpdateOrderOnSuccessfulPayment' );
		$order = self::$woocommerce->getOrder( $order_id );
		if ( ! $cart_token = self::$woocommerce->getOrderMeta( $order, $this->cart_token_key_for_db ) ) {
			return $result;
		}
		$this->syncOrderToAPI( $order, $order_id );

		//$this->unsetOrderTempData();
		return $result;
	}

	/**
	 * Payment completed
	 *
	 * @param $order_id
	 */
	function paymentCompleted( $order_id ) {
		self::$settings->logMessage( array( "order_id" => $order_id ), 'paymentCompleted' );
		if ( self::$woocommerce->getOrder( $order_id ) ) {
			self::$woocommerce->setCustomerPayingForOrder( $order_id );
		}
		$cart_token = $this->retrieveCartToken();
		if ( ! empty( $cart_token ) ) {
			$this->unsetOrderTempData();
		}
	}

	/**
	 * Unset the temporary cart token and order data
	 *
	 * @param   null  $user_id
	 */
	function unsetOrderTempData( $user_id = null ) {
		self::$storage->removeValue( $this->cart_token_key );
		self::$storage->removeValue( $this->pending_recovery_key );
		self::$storage->removeValue( $this->cart_tracking_started_key );
		self::$storage->removeValue( $this->previous_cart_hash_key );
		//This was set in plugin since 2.0.4
		self::$storage->removeValue( 'rnoc_force_refresh_cart' );
		self::$storage->removeValue( 'rnoc_recovered_at' );
		self::$storage->removeValue( 'rnoc_current_cart_hash' );
		self::$storage->removeValue( 'rnoc_recovered_by_retainful' );
		self::$storage->removeValue( 'rnoc_recovered_cart_token' );
		if ( $user_id || ( $user_id = get_current_user_id() ) ) {
			$this->removeTempDataForUser( $user_id );
		}
	}

	/**
	 * Delete temp data of the user
	 *
	 * @param $user_id
	 */
	function removeTempDataForUser( $user_id ) {
		delete_user_meta( $user_id, $this->cart_token_key_for_db );
		delete_user_meta( $user_id, $this->pending_recovery_key_for_db );
		delete_user_meta( $user_id, $this->cart_tracking_started_key_for_db );
		delete_user_meta( $user_id, $this->user_ip_key_for_db );
	}

	/**
	 * Mark order as pending recovery
	 *
	 * @param $order_id
	 */
	function markOrderAsPendingRecovery( $order_id ) {
		self::$settings->logMessage( array( "order_id" => $order_id ), 'markOrderAsPendingRecovery' );
		/*$order = self::$woocommerce->getOrder($order_id);
		if (!$order instanceof \WC_Order) {
			return;
		}*/
		self::$woocommerce->setOrderMeta( $order_id, $this->pending_recovery_key_for_db, true );
	}
}