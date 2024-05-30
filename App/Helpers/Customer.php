<?php

namespace RNOC\App\Helpers;

defined( 'ABSPATH' ) || exit;

class Customer {
	protected static $data = [
		'id'                => 0,
		'email'             => '',
		'phone'             => '',
		'state'             => '',
		'currency'          => '',
		'last_name'         => '',
		'created_at'        => '',
		'first_name'        => '',
		'updated_at'        => '',
		'total_spent'       => 0,
		'orders_count'      => 0,
		'last_order_id'     => 0,
		'verified_email'    => true,
		'accepts_marketing' => true,
		'user_roles'        => ''
	];

	public static function setCartCustomer() {
		$billing_email      = WC::getCustomerBillingEmail();
		$billing_phone      = WC::getCustomer( 'billing_phone' );
		$billing_state      = WC::getCustomer( 'billing_state' );
		$billing_first_name = WC::getCustomer( 'billing_first_name' );
		$billing_last_name  = WC::getCustomer( 'billing_last_name' );
		$created_at         = Settings::getStorage()->get( 'rnoc_session_created_at', current_time( 'timestamp', true ) );
		$updated_at         = current_time( 'timestamp', true );
		if ( $user_id = get_current_user_id() ) {
			$user          = wp_get_current_user();
			$billing_email = empty( $billing_email ) ? WP::getLoginUserEmail() : $billing_email;
			$billing_email = empty( $billing_email ) ? ( function_exists( 'WC' ) && Util::isMethodExists( WC()->customer, 'get_email' ) ? WC()->customer->get_email() : '' ) : $billing_email;

			$billing_state = empty( $billing_state ) ? get_user_meta( $user_id, 'billing_state', true ) : $billing_state;

			$billing_phone      = empty( $billing_phone ) ? get_user_meta( $user_id, 'billing_phone', true ) : $billing_phone;
			$billing_first_name = empty( $billing_first_name ) ? get_user_meta( $user_id, 'billing_first_name', true ) : $billing_first_name;
			$billing_first_name = empty( $billing_first_name ) ? ( is_object( $user ) && ! empty( $user->first_name ) ? $user->first_name : '' ) : $billing_first_name;

			$billing_last_name = empty( $billing_last_name ) ? get_user_meta( $user_id, 'billing_last_name', true ) : $billing_last_name;
			$billing_last_name = empty( $billing_last_name ) ? ( is_object( $user ) && ! empty( $user->last_name ) ? $user->last_name : '' ) : $billing_last_name;

			$created_at = $updated_at = is_object( $user->user_registered ) && ! empty( $user->user_registered ) ? strtotime( $user->user_registered ) : current_time( 'timestamp', true );
		}

		self::set( 'id', $user_id );
		self::set( 'email', $billing_email );
		self::set( 'phone', $billing_phone );
		self::set( 'state', $billing_state );
		self::set( 'last_name', $billing_last_name );
		self::set( 'first_name', $billing_first_name );
		self::set( 'created_at', WP::formatToIso8601( $created_at ) );
		self::set( 'updated_at', WP::formatToIso8601( $updated_at ) );
		self::set( 'currency', WC::getDefaultCurrency() );
		self::set( 'user_roles', WP::getUserRoles( $billing_email ) );
	}

	public static function setOrderCustomer( $order ) {
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}
		$created_at = $updated_at = current_time( 'timestamp', true );
		if ( $user_id = WC::getOrderUserId( $order ) ) {
			$user       = WC::getOrderData( 'user', $order );
			$created_at = $updated_at = is_object( $user->user_registered ) && ! empty( $user->user_registered ) ? strtotime( $user->user_registered ) : current_time( 'timestamp', true );
		}
		$billing_email = WC::getOrderBillingEmail( $order );
		self::set( 'id', $user_id );
		self::set( 'email', $billing_email );
		self::set( 'phone', WC::getOrderData( 'billing_phone', $order ) );
		self::set( 'state', WC::getOrderData( 'billing_state', $order ) );
		self::set( 'last_name', WC::getOrderData( 'billing_last_name', $order ) );
		self::set( 'first_name', WC::getOrderData( 'billing_first_name', $order ) );
		self::set( 'created_at', WP::formatToIso8601( $created_at ) );
		self::set( 'updated_at', WP::formatToIso8601( $updated_at ) );
		self::set( 'currency', WC::getOrderData( 'currency', $order ) );
		self::set( 'user_roles', WP::getUserRoles( $billing_email ) );
		//last_order_id
	}

	public static function getCustomer( $order = '' ) {
		if ( empty( $order ) ) {
			self::setCartCustomer();

			return self::$data;
		}
		self::setOrderCustomer( $order );

		return self::$data;
	}

	/**
	 * Set customer data.
	 *
	 * @param string $key Customer data key.
	 * @param mixed $value Customer data value.
	 *
	 * @return void
	 */
	public static function set( $key, $value ) {
		self::$data[ $key ] = $value;
	}
	
}