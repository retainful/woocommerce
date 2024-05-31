<?php

namespace RNOC\App\Helpers;

defined( 'ABSPATH' ) || exit;

class Customer {
	protected static $default = [
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

	/**
	 * Get cart customer.
	 *
	 * @return array
	 */
	public static function getCartCustomer() {
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
		$user_data = [
			'id'         => $user_id,
			'email'      => $billing_email,
			'phone'      => $billing_phone,
			'state'      => $billing_state,
			'last_name'  => $billing_last_name,
			'first_name' => $billing_first_name,
			'created_at' => WP::formatToIso8601( $created_at ),
			'updated_at' => WP::formatToIso8601( $updated_at ),
			'currency'   => WC::getDefaultCurrency(),
			'user_roles' => WP::getUserRoles( $billing_email )
		];

		return wp_parse_args( $user_data, self::$default );
	}

	/**
	 * Get Order customer.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public static function getOrderCustomer( $order ) {
		if ( ! ( $order instanceof \WC_Order ) ) {
			return [];
		}
		$created_at = $updated_at = current_time( 'timestamp', true );
		if ( $user_id = WC::getOrderUserId( $order ) ) {
			$user       = WC::getOrderData( 'user', $order );
			$created_at = $updated_at = is_object( $user->user_registered ) && ! empty( $user->user_registered ) ? strtotime( $user->user_registered ) : current_time( 'timestamp', true );
		}
		$billing_email   = WC::getOrderBillingEmail( $order );
		$customer_orders = WC::getOrdersByEmail( $billing_email );
		$total_spent     = 0;
		$last_order_id   = 0;
		if ( is_array( $customer_orders ) && count( $customer_orders ) ) {
			foreach ( $customer_orders as $key => $customer_order ) {
				if ( $customer_order instanceof \WC_Order ) {
					if ( $key == 0 ) {
						$last_order_id = WC::getOrderId( $customer_order );
					}
					$total_spent += WC::getOrderTotal( $customer_order );
				}
			}
		}

		return wp_parse_args( [
			'id'            => $user_id,
			'email'         => $billing_email,
			'phone'         => WC::getOrderData( 'billing_phone', $order ),
			'state'         => WC::getOrderData( 'billing_state', $order ),
			'last_name'     => WC::getOrderData( 'billing_last_name', $order ),
			'first_name'    => WC::getOrderData( 'billing_first_name', $order ),
			'created_at'    => WP::formatToIso8601( $created_at ),
			'updated_at'    => WP::formatToIso8601( $updated_at ),
			'currency'      => WC::getOrderData( 'currency', $order ),
			'user_roles'    => WP::getUserRoles( $billing_email ),
			'last_order_id' => $last_order_id,
			'total_spent'   => $total_spent,
			'orders_count'  => is_array( $customer_orders ) ? count( $customer_orders ) : 0
		], self::$default );

	}
}