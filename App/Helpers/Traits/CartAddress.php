<?php

namespace RNOC\App\Helpers\Traits;

use RNOC\App\Helpers\WC;
use RNOC\App\Helpers\WP;

defined( 'ABSPATH' ) || exit;

trait CartAddress {
	/**
	 * Get billing Email.
	 *
	 * @return string
	 */
	public static function getCartBillingEmail() {
		$billing_email = WC::getCustomerBillingEmail();
		if ( empty( $billing_email ) && get_current_user_id() ) {
			$billing_email = WP::getLoginUserEmail();
			//$billing_email = empty( $billing_email ) ? ( function_exists( 'WC' ) && Util::isMethodExists( WC()->customer, 'get_email' ) ? WC()->customer->get_email() : '' ) : $billing_email;
		}

		return $billing_email;
	}

	/**
	 * Get customer phone.
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public static function getCartPhone( $type = 'billing' ) {
		$phone = WC::getCustomerData( $type . '_phone' );
		if ( empty( $phone ) && $user_id = get_current_user_id() ) {
			$phone = get_user_meta( $user_id, $type . '_phone', true );
		}

		return $phone;
	}

	/**
	 * Get customer state.
	 *
	 * @param string $type Address type.
	 *
	 * @return string
	 */
	public static function getCartState( $type = 'billing' ) {
		$state = WC::getCustomerData( $type . '_state' );
		if ( empty( $state ) && $user_id = get_current_user_id() ) {
			$state = get_user_meta( $user_id, $type . '_state', true );
		}

		return $state;
	}

	/**
	 * Get customer first name.
	 *
	 * @param string $type Address type.
	 *
	 * @return string
	 */
	public static function getCartFirstName( $type = 'billing' ) {
		$first_name = WC::getCustomerData( $type . '_first_name' );
		if ( empty( $first_name ) && $user_id = get_current_user_id() ) {
			$first_name = get_user_meta( $user_id, $type . '_first_name', true );
			if ( empty( $first_name ) ) {
				$user       = wp_get_current_user();
				$first_name = is_object( $user ) && ! empty( $user->first_name ) ? $user->first_name : '';
			}
		}

		return $first_name;
	}

	/**
	 * Get customer last name.
	 *
	 * @param string $type Address type.
	 *
	 * @return string
	 */
	public static function getCartLastName( $type = 'billing' ) {
		$last_name = WC::getCustomerData( $type . '_last_name' );
		if ( empty( $last_name ) && $user_id = get_current_user_id() ) {
			$last_name = get_user_meta( $user_id, $type . '_last_name', true );
			if ( empty( $last_name ) ) {
				$user      = wp_get_current_user();
				$last_name = is_object( $user ) && ! empty( $user->last_name ) ? $user->last_name : '';
			}
		}

		return $last_name;
	}

	/**
	 * Get customer zip code.
	 *
	 * @param string $type Address type.
	 *
	 * @return string
	 */
	public static function getCartZipCode( $type = 'billing' ) {
		$zip_code = WC::getCustomerData( $type . '_postcode' );
		if ( empty( $zip_code ) && $user_id = get_current_user_id() ) {
			$zip_code = get_user_meta( $user_id, $type . '_postcode', true );
		}

		return $zip_code;
	}

	/**
	 * Get customer city.
	 *
	 * @param string $type Address type.
	 *
	 * @return mixed
	 */
	public static function getCartCity( $type = 'billing' ) {
		$city = WC::getCustomerData( $type . '_city' );
		if ( empty( $city ) && $user_id = get_current_user_id() ) {
			$city = get_user_meta( $user_id, $type . '_city', true );
		}

		return $city;
	}

	/**
	 * Get customer company.
	 *
	 * @param string $type Address type.
	 *
	 * @return string
	 */
	public static function getCartCompany( $type = 'billing' ) {
		$company = WC::getCustomerData( $type . '_company' );
		if ( empty( $company ) && $user_id = get_current_user_id() ) {
			$company = get_user_meta( $user_id, $type . '_company', true );
		}

		return $company;
	}

	/**
	 * Get customer country.
	 *
	 * @param string $type Address type.
	 *
	 * @return string
	 */
	public static function getCartCountry( $type = 'billing' ) {
		$country = WC::getCustomerData( $type . '_country' );
		if ( empty( $country ) && $user_id = get_current_user_id() ) {
			$country = get_user_meta( $user_id, $type . '_country', true );
		}

		return $country;
	}

	/**
	 * Get customer address one.
	 *
	 * @param string $type Address type.
	 *
	 * @return string
	 */
	public static function getCartAddressOne( $type = 'billing' ) {
		$field = WC::getCustomerData( $type . '_address_1' );
		if ( empty( $field ) && $user_id = get_current_user_id() ) {
			$field = get_user_meta( $user_id, $type . '_address_1', true );
		}

		return $field;
	}

	/**
	 * Get customer address two.
	 *
	 * @param string $type Address type.
	 *
	 * @return string
	 */
	public static function getCartAddressTwo( $type = 'billing' ) {
		$field = WC::getCustomerData( $type . '_address_2' );
		if ( empty( $field ) && $user_id = get_current_user_id() ) {
			$field = get_user_meta( $user_id, $type . '_address_2', true );
		}

		return $field;
	}

}