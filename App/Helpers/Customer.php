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
		'last_order_name'   => null,
		'accepts_marketing' => true,
		'user_roles'        => ''
	];

	/**
	 * Get address fields.
	 *
	 * @return array
	 */
	public static function getAddressFields() {
		return apply_filters( 'rnoc_get_checkout_mapping_fields', [
			'first_name',
			'last_name',
			'state',
			'phone',
			'postcode',
			'city',
			'country',
			'address_1',
			'address_2',
			'company'
		] );
	}

	/**
	 * Set customer details.
	 *
	 * @param string $from From address.
	 * @param string $set To address.
	 * @param string|object $address_value To address.
	 *
	 * @return void
	 */
	public static function setCustomerDetails( $from = 'billing', $set = 'billing', $address_value = [] ) {
		if ( ! function_exists( 'WC' ) || ! is_object( WC()->customer ) ) {
			return;
		}
		$address_fields = self::getAddressFields();
		foreach ( $address_fields as $field ) {
			if ( $field == 'email' ) {
				continue;
			}
			$field_name = $from . '_' . $field;
			if ( isset( $_POST[ $field_name ] ) ) {
				$field_value = $_POST[ $field_name ];
			}

			if ( is_array( $address_value ) && ! empty( $address_value ) ) {
				$field_value = isset( $address_value[ $field ] ) ? $address_value[ $field ] : null;
			}

			if ( empty( $field_value ) ) {
				continue;
			}
			$method_name = 'set_' . $set . '_' . $field;
			if ( is_callable( [ WC()->customer, $method_name ] ) ) {
				WC()->customer->$method_name( $field_value );
			}
		}
	}

	/**
	 * Set customer email.
	 *
	 * @param string $billing_email Customer email.
	 *
	 * @return void
	 */
	public static function setCustomerEmail( $billing_email ) {
		if ( is_email( $billing_email ) && function_exists( 'WC' ) && isset( WC()->customer ) && Util::isMethodExists( WC()->customer, 'set_billing_email' ) ) {
			WC()->customer->set_billing_email( $billing_email );
		}
	}

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
		if ( $user_id = Order::getOrderUserId( $order ) ) {
			$user       = Order::getOrderData( 'user', $order );
			$created_at = $updated_at = is_object( $user->user_registered ) && ! empty( $user->user_registered ) ? strtotime( $user->user_registered ) : current_time( 'timestamp', true );
		}
		$billing_email   = Order::getOrderBillingEmail( $order );
		$customer_orders = Order::getOrdersByEmail( $billing_email );
		$total_spent     = 0;
		$last_order_id   = 0;
		if ( is_array( $customer_orders ) && count( $customer_orders ) ) {
			foreach ( $customer_orders as $key => $customer_order ) {
				if ( $customer_order instanceof \WC_Order ) {
					if ( $key == 0 ) {
						$last_order_id = Order::getOrderId( $customer_order );
					}
					$total_spent += Order::getOrderTotal( $customer_order );
				}
			}
		}

		return wp_parse_args( [
			'id'            => $user_id,
			'email'         => $billing_email,
			'phone'         => Order::getOrderData( 'billing_phone', $order ),
			'state'         => Order::getOrderData( 'billing_state', $order ),
			'last_name'     => Order::getOrderData( 'billing_last_name', $order ),
			'first_name'    => Order::getOrderData( 'billing_first_name', $order ),
			'created_at'    => WP::formatToIso8601( $created_at ),
			'updated_at'    => WP::formatToIso8601( $updated_at ),
			'currency'      => Order::getOrderData( 'currency', $order ),
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
			'zip'           => Order::getOrderData( 'billing_postcode', $order ),
			'city'          => Order::getOrderData( 'billing_city', $order ),
			'name'          => Order::getOrderData( 'billing_first_name', $order ) . ' ' . Order::getOrderData( 'billing_last_name', $order ),
			'phone'         => null, // TODO: Need to ask, why we need to send null value
			'company'       => null, // TODO: Need to ask, why we need to send null value
			'country'       => Order::getOrderData( 'billing_country', $order ),
			'address1'      => Order::getOrderData( 'billing_address_1', $order ),
			'address2'      => Order::getOrderData( 'billing_address_2', $order ),
			'latitude'      => '',
			'longitude'     => '',
			'province'      => Order::getOrderData( 'billing_state', $order ),
			'last_name'     => Order::getOrderData( 'billing_last_name', $order ),
			'first_name'    => Order::getOrderData( 'billing_first_name', $order ),
			'country_code'  => Order::getOrderData( 'billing_country', $order ),
			'province_code' => Order::getOrderData( 'billing_state', $order ),
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
			'zip'           => Order::getOrderData( 'shipping_postcode', $order ),
			'city'          => Order::getOrderData( 'shipping_city', $order ),
			'name'          => Order::getOrderData( 'shipping_first_name', $order ) . ' ' . Order::getOrderData( 'shipping_last_name', $order ),
			'phone'         => null, // TODO: Need to ask, why we need to send null value
			'company'       => null, // TODO: Need to ask, why we need to send null value
			'country'       => Order::getOrderData( 'shipping_country', $order ),
			'address1'      => Order::getOrderData( 'shipping_address_1', $order ),
			'address2'      => Order::getOrderData( 'shipping_address_2', $order ),
			'latitude'      => '',
			'longitude'     => '',
			'province'      => Order::getOrderData( 'shipping_state', $order ),
			'last_name'     => Order::getOrderData( 'shipping_last_name', $order ),
			'first_name'    => Order::getOrderData( 'shipping_first_name', $order ),
			'country_code'  => Order::getOrderData( 'shipping_country', $order ),
			'province_code' => Order::getOrderData( 'shipping_state', $order ),
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
			return Order::getOrderMeta( '_rnoc_get_http_accept_language', $order );
		} else if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$lang = trim( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

			return substr( $lang, 0, 2 );
		}

		return '';
	}

	/**
	 * Customer user ip.
	 *
	 * @return string
	 */
	public static function getUserIPDetails() {
		$ip = self::getClientIp();

		return (string) trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $ip ) ) ) ) );
	}

	/**
	 * Get client ip.
	 *
	 * @return string
	 */
	public static function getClientIP() {
		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$client_ip = $_SERVER['HTTP_X_REAL_IP'];
		} elseif ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$client_ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
			$client_ip = $_SERVER['HTTP_X_FORWARDED'];
		} elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
			$client_ip = $_SERVER['HTTP_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
			$client_ip = $_SERVER['HTTP_FORWARDED'];
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$client_ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$client_ip = '';
		}

		return $client_ip;
	}

	/**
	 * Get Customer email.
	 *
	 * @return string
	 */
	public static function getCustomerBillingEmail() {

		if ( function_exists( 'WC' ) && isset( WC()->customer ) && Util::isMethodExists( WC()->customer, 'get_billing_email' ) ) {
			return WC()->customer->get_billing_email();
		}

		return '';
	}

	/**
	 * Get customer data.
	 *
	 * @param string $key Customer key.
	 * @param mixed $default Customer data default value.
	 *
	 * @return mixed
	 */
	public static function getCustomerData( $key, $default = '' ) {
		if ( empty( $key ) ) {
			return $default;
		}
		$method = 'get_' . $key;
		if ( function_exists( 'WC' ) && isset( WC()->customer ) && Util::isMethodExists( WC()->customer, $method ) ) {
			return WC()->customer->$method();
		}

		return $default;
	}


	/**
	 * Login the recover cart user.
	 *
	 * @param int $user_id user id.
	 *
	 * @return bool
	 */
	public static function recoverCartUserLogin( $user_id ) {
		if ( $login_user = WP::getCurrentUserId() ) {
			if ( (int) $user_id !== $login_user ) {
				wp_logout();

				return self::updateRecoverCartUser( $user_id );
			}
		} else {
			return self::updateRecoverCartUser( $user_id );
		}

		return false;
	}

	/**
	 * update recover cart user data.
	 *
	 * @param int $user_id user id.
	 *
	 * @return bool
	 */
	public static function updateRecoverCartUser( $user_id ) {
		if ( self::allowCartRecoveryUserLogin( $user_id ) ) {
			WP::setCurrentUser( $user_id );
			WP::setAuthCookie( $user_id );
			WP::updateUserMeta( $user_id, '_rnoc_is_pending_recovery', true );

			return true;
		}
		//"Not logging in user {$user_id} with admin rights"
		WC::addNotice( __( 'Note: Auto-login disabled when recreating cart for WordPress Admin account. Checking out as guest.', RNOC_TEXT_DOMAIN ) );

		return false;
	}

	/**
	 * Check if a user is allowed to be logged in for cart recovery.
	 *
	 * @param int|\WP_User $user user id
	 *
	 * @return bool
	 */
	public static function allowCartRecoveryUserLogin( $user ) {
		return (bool) apply_filters( 'wc_retainful_allow_cart_recovery_user_login', ! user_can( $user, 'edit_others_posts' ), $user );
	}


}