<?php

namespace RNOC\App\Helpers;

use RNOC\App\Helpers\Traits\CartAddress;

defined( 'ABSPATH' ) || exit;

class Customer {
	use CartAddress;

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
		$billing_email = self::getCartBillingEmail();
		$created_at    = Settings::getStorage()->get( 'rnoc_session_created_at', current_time( 'timestamp', true ) );
		$updated_at    = current_time( 'timestamp', true );
		if ( $user_id = get_current_user_id() ) {
			$user       = wp_get_current_user();
			$created_at = $updated_at = is_object( $user->user_registered ) && ! empty( $user->user_registered ) ? strtotime( $user->user_registered ) : current_time( 'timestamp', true );
		}

		return wp_parse_args( [
			'id'         => $user_id,
			'email'      => $billing_email,
			'phone'      => self::getCartPhone(),
			'state'      => self::getCartState(),
			'last_name'  => self::getCartLastName(),
			'first_name' => self::getCartFirstName(),
			'created_at' => WP::formatToIso8601( $created_at ),
			'updated_at' => WP::formatToIso8601( $updated_at ),
			'currency'   => WC::getDefaultCurrency(),
			'user_roles' => WP::getUserRoles( $billing_email )
		], self::$default );
	}

	/**
	 * Get billing address.
	 *
	 * @return array
	 */
	public static function getCartBillingAddress() {
		return [
			'zip'           => self::getCartZipCode(),
			'city'          => self::getCartCity(),
			'name'          => self::getCartFirstName() . ' ' . self::getCartLastName(),
			'phone'         => self::getCartPhone(),
			'company'       => self::getCartCompany(),
			'country'       => self::getCartCountry(),
			'address1'      => self::getCartAddressOne(),
			'address2'      => self::getCartAddressTwo(),
			'province'      => self::getCartState(),
			'last_name'     => self::getCartLastName(),
			'first_name'    => self::getCartFirstName(),
			'country_code'  => self::getCartCountry(),
			'province_code' => self::getCartState(),
		];
	}

	/**
	 * Get shipping address.
	 *
	 * @return array
	 */
	public static function getCartShippingAddress() {
		return [
			'zip'           => self::getCartZipCode( 'shipping' ),
			'city'          => self::getCartCity( 'shipping' ),
			'name'          => self::getCartFirstName( 'shipping' ) . ' ' . self::getCartLastName( 'shipping' ),
			'phone'         => null, // TODO: Need to ask, why we need to send null value
			'company'       => null,// TODO: Need to ask, why we need to send null value
			'country'       => self::getCartCountry( 'shipping' ),
			'address1'      => self::getCartAddressOne( 'shipping' ),
			'address2'      => self::getCartAddressTwo( 'shipping' ),
			'latitude'      => '',
			'longitude'     => '',
			'province'      => self::getCartState( 'shipping' ),
			'last_name'     => self::getCartLastName( 'shipping' ),
			'first_name'    => self::getCartFirstName( 'shipping' ),
			'country_code'  => self::getCartCountry( 'shipping' ),
			'province_code' => self::getCartState( 'shipping' ),
		];
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

	/**
	 * Get order billing address.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public static function getOrderBillingAddress( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return [];
		}

		return [
			'zip'           => WC::getOrderData( 'billing_postcode', $order ),
			'city'          => WC::getOrderData( 'billing_city', $order ),
			'name'          => WC::getOrderData( 'billing_first_name', $order ) . ' ' . WC::getOrderData( 'billing_last_name', $order ),
			'phone'         => null, // TODO: Need to ask, why we need to send null value
			'company'       => null, // TODO: Need to ask, why we need to send null value
			'country'       => WC::getOrderData( 'billing_country', $order ),
			'address1'      => WC::getOrderData( 'billing_address_1', $order ),
			'address2'      => WC::getOrderData( 'billing_address_2', $order ),
			'latitude'      => '',
			'longitude'     => '',
			'province'      => WC::getOrderData( 'billing_state', $order ),
			'last_name'     => WC::getOrderData( 'billing_last_name', $order ),
			'first_name'    => WC::getOrderData( 'billing_first_name', $order ),
			'country_code'  => WC::getOrderData( 'billing_country', $order ),
			'province_code' => WC::getOrderData( 'billing_state', $order ),
		];
	}

	/**
	 * Get order shipping address.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public static function getOrderShippingAddress( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return [];
		}

		return [
			'zip'           => WC::getOrderData( 'shipping_postcode', $order ),
			'city'          => WC::getOrderData( 'shipping_city', $order ),
			'name'          => WC::getOrderData( 'shipping_first_name', $order ) . ' ' . WC::getOrderData( 'shipping_last_name', $order ),
			'phone'         => null, // TODO: Need to ask, why we need to send null value
			'company'       => null, // TODO: Need to ask, why we need to send null value
			'country'       => WC::getOrderData( 'shipping_country', $order ),
			'address1'      => WC::getOrderData( 'shipping_address_1', $order ),
			'address2'      => WC::getOrderData( 'shipping_address_2', $order ),
			'latitude'      => '',
			'longitude'     => '',
			'province'      => WC::getOrderData( 'shipping_state', $order ),
			'last_name'     => WC::getOrderData( 'shipping_last_name', $order ),
			'first_name'    => WC::getOrderData( 'shipping_first_name', $order ),
			'country_code'  => WC::getOrderData( 'shipping_country', $order ),
			'province_code' => WC::getOrderData( 'shipping_state', $order ),
		];

	}

	/**
	 * Get client details.
	 *
	 * @param \WC_Order $order order object
	 *
	 * @return array
	 */
	public static function getClientDetails( $order = '' ) {
		$client_details = [
			'accept_language' => self::getUserAcceptLanguage( $order )
		];

		return apply_filters( 'rnoc_get_client_details', $client_details, $order );
	}

	/**
	 * Get user agent language.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string
	 */
	public static function getUserAcceptLanguage( $order = '' ) {
		if ( ! empty( $order ) ) {
			return WC::getOrderMeta( '_rnoc_get_http_accept_language', $order );
		} else if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$lang = trim( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

			return substr( $lang, 0, 2 );
		}

		return '';
	}
}