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
			self::purchaseComplete( intval( $draft_order ) );
		}
	}

	/**
	 * purchase complete.
	 *
	 * @param $order_id
	 *
	 * @return null
	 */
	public static function purchaseComplete( $order_id ) {
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
		}

		return null;
	}

	/**
	 * Payment completed.
	 *
	 * @param $order_id
	 */
	public static function paymentCompleted( $order_id, $order ) {
		Settings::logMessage( array( "order" => $order ), 'paymentCompleted' );
		$cart_token = self::retrieveCartToken();
		if ( ! empty( $cart_token ) ) {
			self::unsetOrderTempData();
		}
	}

	/**
	 * Unset the temporary cart token and order data.
	 *
	 * @param null $user_id
	 */
	public static function unsetOrderTempData( $user_id = null ) {
		SettingsHelper::initStorage()->removeValue( self::$cart_token_key );
		SettingsHelper::initStorage()->removeValue( self::$pending_recovery_key );
		SettingsHelper::initStorage()->removeValue( self::$cart_tracking_started_key );
		SettingsHelper::initStorage()->removeValue( self::$previous_cart_hash_key );
		//This was set in plugin since 2.0.4
		SettingsHelper::initStorage()->removeValue( 'rnoc_force_refresh_cart' );
		SettingsHelper::initStorage()->removeValue( 'rnoc_recovered_at' );
		SettingsHelper::initStorage()->removeValue( 'rnoc_current_cart_hash' );
		SettingsHelper::initStorage()->removeValue( 'rnoc_recovered_by_retainful' );
		SettingsHelper::initStorage()->removeValue( 'rnoc_recovered_cart_token' );

		if ( $user_id || ( $user_id = get_current_user_id() ) ) {
			self::removeTempDataForUser( $user_id );
		}
	}


	/**
	 * processing checkout order
	 *
	 * @param $order_id
	 */
	function checkoutOrderProcessed( $order_id ) {
		Settings::logMessage( array( "order_id" => $order_id ), 'checkoutOrderProcessed' );
		try {
			$cart_token = $this->retrieveCartToken();
			if ( ! empty( $cart_token ) ) {
				$order = WC::getOrder( $order_id );
				$this->purchaseComplete( $order_id );
				$this->syncOrderToAPI( $order, $order_id );
			}
		} catch ( Exception $e ) {
		}
	}

	/**
	 * Delete temp data of the user
	 *
	 * @param $user_id
	 */
	public static function removeTempDataForUser( $user_id ) {
		$user_meta_data = [
			self::$cart_token_key_for_db,
			self::$pending_recovery_key_for_db,
			self::$cart_tracking_started_key_for_db,
			self::$user_ip_key_for_db
		];

		foreach ( $user_meta_data as $meta_key ) {
			WC::deleteUserMeta( $user_id, $meta_key );
		}
	}


	/**
	 * sync order to api
	 *
	 * @param $order
	 * @param $order_id
	 */
	public static function syncOrderToAPI( $order, $order_id ) {
		$order_sync_enabled = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'enable_background_order_sync', true );
		if ( $order_sync_enabled ) {
			return;
		}
		if ( self::needInstantOrderSync() ) {
			$cart = self::getOrderData( $order );
			if ( ! empty( $cart ) ) {
				$cart_hash = self::encryptData( $cart );
				//Reduce the loading speed
				$client_ip = WC::getOrderMeta( $order, self::$user_ip_key_for_db );
				$token     = WC::getOrderMeta( $order, self::$cart_token_key_for_db );
				Settings::logMessage( array(
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
					self::syncCart( $cart_hash, $extra_headers );
				}
			} else {
				Settings::logMessage( array( 'order_id' => $order_id ), 'Failed syncOrderToAPI' );
			}
		} else {
			self::scheduleCartSync( $order_id );
		}
	}

	/**
	 * need the instant sync or not
	 * @return mixed|void
	 */
	public static function needInstantOrderSync() {
		return apply_filters( 'rnoc_sync_order_data_instantly_to_api', true );
	}


	/**
	 * get order details for sync cart
	 *
	 * @param $order
	 *
	 * @return array
	 */
	public static function getOrderData( $order ) {
		$user_ip        = WC::getOrderMeta( $order, self::$user_ip_key_for_db );
		$can_track_cart = self::canTrackAbandonedCarts( $user_ip, $order );
		$order_id       = WC::getOrderId( $order );
		Settings::logMessage( array(
			'can_track_cart' => $can_track_cart,
			'user_ip'        => $user_ip
		), 'can track cart in getOrderData method for ' . $order_id );
		if ( ! $can_track_cart ) {
			return array();
		}
		$cart_token = self::getOrderCartToken( $order );
		Settings::logMessage( $cart_token, 'Cart token in getOrderData method for ' . $order_id );
		if ( empty( $cart_token ) ) {
			return array();
		}
		$cart_hash = WC::getOrderMeta( $order, self::$cart_hash_key_for_db );
		if ( empty( $cart_hash ) ) {
			$cart_hash = $order->get_cart_hash();
		}
		$is_buyer_accepts_marketing = WC::getOrderMeta( $order, self::$accepts_marketing_key_for_db );
		$customer_details           = self::getCustomerDetails( $order );
		$current_currency_code      = WC::getOrderCurrency( $order );
		$default_currency_code      = Settings::getBaseCurrency();
		$cart_created_at            = WC::getOrderMeta( $order, self::$cart_tracking_started_key_for_db );
		Settings::logMessage( $cart_created_at, 'cart created time in getOrderData ' . $order_id );
		$cart_total                   = self::formatDecimalPrice( wc::getOrderTotal( $order ) );
		$excluding_tax                = ( wc::isPriceExcludingTax() );
		$consider_on_hold_order_as_ac = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 0 );
		$recovered_at                 = wc::getOrderMeta( $order, '_rnoc_recovered_at' );
		$order_status                 = wc::getStatus( $order );
		$order_status                 = self::changeOrderStatus( $order_status );
		$order_data                   = array(
			'cart_type'                 => 'order',
			'treat_on_hold_as_complete' => ( $consider_on_hold_order_as_ac == 0 ),
			'r_order_id'                => $order_id,
			'order_number'              => $order_id,
			'order_date'                => self::formatToIso8601( WC::getOrderDate( $order ) ),
			'woo_r_order_number'        => WC::getOrderId( $order ),
			'cart_hash'                 => $cart_hash,
			'ip'                        => $user_ip,
			'id'                        => $cart_token,
			'email'                     => ( isset( $customer_details['email'] ) ) ? $customer_details['email'] : null,
			'token'                     => $cart_token,
			'currency'                  => $default_currency_code,
			'customer'                  => $customer_details,
			'tax_lines'                 => self::getOrderTaxDetails(),
			'total_tax'                 => self::formatDecimalPrice( wc::getOrderTotalTax( $order ) ),
			'cart_token'                => $cart_token,
			'created_at'                => self::formatToIso8601( $cart_created_at ),
			'line_items'                => self::getLineItemsDetails( $order, 'order' ),
			'updated_at'                => self::formatToIso8601( '' ),
			'source_name'               => 'web',
			'total_price'               => $cart_total,
			'completed_at'              => self::getCompletedAt( $order ),
			'total_weight'              => 0,
			'discount_codes'            => wc::getAppliedDiscounts( $order ),
			'order_status'              => apply_filters( 'rnoc_abandoned_cart_order_status', $order_status, $order ),
			'shipping_lines'            => array(),
			'subtotal_price'            => self::formatDecimalPrice( wc::getOrderSubTotal( $order ) ),
			'total_price_set'           => Currency::getCurrencyDetails( $cart_total, $current_currency_code, $default_currency_code ),
			'taxes_included'            => ( ! wc::isPriceExcludingTax() ),
			'customer_locale'           => self::getOrderLanguage( $order ),
			'total_discounts'           => self::formatDecimalPrice( wc::getOrderDiscount( $order, $excluding_tax ) ),
			'shipping_address'          => self::getCustomerAddressDetails( $order ),
			'billing_address'           => self::getCustomerAddressDetails( $order ),
			'presentment_currency'      => $current_currency_code,
			'abandoned_checkout_url'    => self::getRecoveryLink( $cart_token ),
			'total_line_items_price'    => self::formatDecimalPrice( self::getOrderItemsTotal( $order ) ),
			'buyer_accepts_marketing'   => ( $is_buyer_accepts_marketing == 1 ),
			'cancelled_at'              => wc::getOrderMeta( $order, self::$order_cancelled_date_key_for_db ),
			'woocommerce_totals'        => self::getOrderTotals( $order, $excluding_tax ),
			'recovered_by_retainful'    => ( wc::getOrderMeta( $order, '_rnoc_recovered_by' ) ) ? true : false,
			'recovered_cart_token'      => wc::getOrderMeta( $order, '_rnoc_recovered_cart_token' ),
			'recovered_at'              => ( ! empty( $recovered_at ) ) ? self::formatToIso8601( $recovered_at ) : null,
			'client_details'            => self::getClientDetails( $order ),
			'payment_method'            => array(
				'value' => $order->get_payment_method(),
				'name'  => $order->get_payment_method_title(),
			)

		);
		if ( ! empty( $cart_token ) ) {
			$referrer_automation_id = self::$woocommerce->getSession( $cart_token . '_referrer_automation_id' );
			if ( ! empty( $referrer_automation_id ) ) {
				$order_data['referrer_automation_id'] = $referrer_automation_id;
			}
		}

		return apply_filters( 'rnoc_api_get_order_data', $order_data, $order );
	}

	/**
	 * get the cart token from the order object
	 *
	 * @param $order
	 *
	 * @return string|null
	 */
	public static function getOrderCartToken( $order ) {
		return apply_filters( 'rnoc_get_order_cart_token', wc::getOrderMeta( $order, self::$cart_token_key_for_db ), $order );
	}

	/**
	 * change order status
	 *
	 * @param $order_status
	 *
	 * @return mixed|string
	 *
	 */
	public static function changeOrderStatus( $order_status ) {
		$consider_cancelled_as_abandoned_status = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status', 1 );
		$consider_on_hold_as_abandoned_status   = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 1 );
		$consider_failed_as_abandoned_status    = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status', 1 );
		$changable_order_status                 = array( 'checkout-draft' );
		if ( $consider_cancelled_as_abandoned_status == 1 ) {
			$changable_order_status[] = "cancelled";
		}
		if ( $consider_on_hold_as_abandoned_status == 1 ) {
			$changable_order_status[] = "on-hold";
		}
		if ( $consider_failed_as_abandoned_status == 1 ) {
			$changable_order_status[] = "failed";
		}
		if ( in_array( $order_status, $changable_order_status ) ) {
			$order_status = "pending";
		}

		return $order_status;
	}


	/**
	 * get the cart tax details
	 * @return array
	 */
	public static function getOrderTaxDetails() {
		//$tax_details = self::$woocommerce->getCartTaxes();
		$taxes = array();

		/*if (!empty($tax_details)) {
			foreach ($tax_details as $key => $tax_detail) {
				$taxes[] = array(
					'rate' => 0,
					'price' => (isset($tax_detail->amount)) ? $tax_detail->amount : 0,
					'title' => (isset($tax_detail->label)) ? $tax_detail->label : 'Tax'
				);
			}
		}*/

		return $taxes;
	}

	/**
	 * get the completed at time of the order.
	 *
	 * @param $order
	 *
	 * @return mixed|void
	 */
	public static function getCompletedAt( $order ) {
		$order_placed_at = WC::getOrderMeta( $order, self::$order_placed_date_key_for_db );
		$order_status    = WC::getStatus( $order );
		if ( ! $order_placed_at && self::isOrderHasValidOrderStatus( $order_status ) ) {
			$order_placed_at = WC::getOrderPlacedDate( $order );
			$order_placed_at = self::processOrderPlaceDate( $order_placed_at );
			$order->update_meta_data( self::$order_placed_date_key_for_db, $order_placed_at );
			$is_order_pending_recovery = (bool) WC::getOrderMeta( $order, self::$pending_recovery_key_for_db );

			//mark the order as recovered
			if ( $is_order_pending_recovery ) {
				$is_order_recovered = (bool) WC::getOrderMeta( $order, self::$order_recovered_key_for_db );
				if ( ! $is_order_recovered && WC::getOrderMeta( $order, '_rnoc_recovered_by' ) == 1 ) {
					$order->delete_meta_data( self::$pending_recovery_key_for_db );
					$order->update_meta_data( self::$order_recovered_key_for_db, true );
					$order->add_order_note( __( 'Order recovered by Retainful.', RNOC_TEXT_DOMAIN ) );
					do_action( 'rnoc_abandoned_order_recovered', $order );
				}
			}

			$order->save();
		}
		$completed_at = ( ! empty( $order_placed_at ) ) ? self::formatToIso8601( $order_placed_at ) : null;

		return apply_filters( 'rnoc_order_completed_at', $completed_at, $order );
	}

	/**
	 * get the order placed date.
	 *
	 * @param $order_placed_at
	 *
	 * @return int|mixed|string|null
	 */
	public static function processOrderPlaceDate( $order_placed_at = '' ) {
		return is_object( $order_placed_at ) && ! empty( $order_placed_at ) ? self::formatToIso8601( $order_placed_at ) : current_time( 'timestamp', true );
	}


	/**
	 * get the language from order
	 *
	 * @param $order
	 *
	 * @return string
	 */
	public static function getOrderLanguage( $order ) {
		//to get language from WPML language
		if ( is_object( $order ) && ! empty( $order ) ) {
			$selected_language = WC::getOrderMeta( $order, 'wpml_language', true );
		}
		if ( empty( $language ) ) {
			$selected_language = wc::getSiteDefaultLang();
		}

		return apply_filters( 'rnoc_get_order_language', $selected_language );
	}

	/**
	 * Get the shipping address of the customer
	 *
	 * @param $order
	 *
	 * @return array
	 */
	function getCustomerAddressDetails( $order, $type = 'billing' ) {
		$first_name    = $type == 'shipping' ? WC::getShippingFirstName( $order ) : WC::getBillingFirstName( $order );
		$last_name     = $type == 'shipping' ? WC::getShippingLastName( $order ) : WC::getBillingLastName( $order );
		$country_code  = $type == 'shipping' ? WC::getShippingCountry( $order ) : WC::getBillingCountry( $order );
		$longitude     = '';
		$latitude      = '';
		$address2      = $type == 'shipping' ? WC::getShippingAddressTwo( $order ) : WC::getBillingAddressTwo( $order );
		$address1      = $type == 'shipping' ? WC::getShippingAddressOne( $order ) : WC::getBillingAddressOne( $order );
		$country       = $type == 'shipping' ? WC::getShippingCountry( $order ) : WC::getBillingCountry( $order );
		$company       = $type == 'shipping' ? '' : WC::getBillingCompany( $order );
		$phone         = $type == 'shipping' ? '' : WC::getBillingPhone( $order );
		$name          = $type == 'shipping' ? WC::getShippingFirstName( $order ) . ' ' . wc::getShippingLastName( $order ) : WC::getBillingFirstName( $order ) . ' ' . wc::getBillingLastName( $order );
		$city          = $type == 'shipping' ? WC::getShippingCity( $order ) : WC::getBillingcity( $order );
		$zip           = $type == 'shipping' ? WC::getShippingPostCode( $order ) : WC::getBillingPostCode( $order );
		$province_code = $type == 'shipping' ? WC::getShippingState( $order ) : WC::getBillingState( $order );
		$province      = $type == 'shipping' ? WC::getShippingState( $order ) : WC::getBillingState( $order );


		return array(
			'zip'           => self::$woocommerce->getShippingPostCode( $order ),
			'city'          => self::$woocommerce->getShippingCity( $order ),
			'name'          => self::$woocommerce->getShippingFirstName( $order ) . ' ' . self::$woocommerce->getShippingLastName( $order ),
			'phone'         => null,
			'company'       => null,
			'country'       => self::$woocommerce->getShippingCountry( $order ),
			'address1'      => self::$woocommerce->getShippingAddressOne( $order ),
			'address2'      => self::$woocommerce->getShippingAddressTwo( $order ),
			'latitude'      => '',
			'province'      => self::$woocommerce->getShippingState( $order ),
			'last_name'     => self::$woocommerce->getShippingLastName( $order ),
			'longitude'     => '',
			'first_name'    => self::$woocommerce->getShippingFirstName( $order ),
			'country_code'  => self::$woocommerce->getShippingCountry( $order ),
			'province_code' => self::$woocommerce->getShippingState( $order ),
		);
	}
}