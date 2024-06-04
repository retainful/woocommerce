<?php

namespace RNOC\App\Helpers;

defined( 'ABSPATH' ) || exit;

class WP {

	/**
	 * Has admin privilege.
	 *
	 * @return bool
	 */
	public static function hasAdminPrivilege() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Check is customer page.
	 *
	 * @return bool
	 */
	public static function isCustomerPage() {
		if ( is_ajax() ) {
			return true;
		}

		return ! is_admin();
	}

	/**
	 * Get login user.
	 *
	 * @return false|\WP_User|null
	 */
	public static function getLoginUser() {
		return is_user_logged_in() ? wp_get_current_user() : false;
	}

	/**
	 * Get login user email.
	 *
	 * @return string
	 */
	public static function getLoginUserEmail() {
		$user = self::getLoginUser();

		return is_object( $user ) && ! empty( $user->user_email ) ? $user->user_email : '';
	}

	/**
	 * Format date field.
	 *
	 * @param int $timestamp Time stamp.
	 *
	 * @return string|null
	 */
	public static function formatToIso8601( $timestamp ) {
		if ( empty( $timestamp ) ) {
			$timestamp = current_time( 'timestamp', true );
		}
		if ( $timestamp instanceof \WC_DateTime ) {
			$timestamp = $timestamp->getTimestamp();
		}

		try {
			$date      = date( 'Y-m-d H:i:s', $timestamp );
			$date_time = new \DateTime( $date );

			return $date_time->format( \DateTime::ATOM );
		} catch ( \Exception $e ) {

		}

		return null;
	}

	/**
	 * Get user role by email.
	 *
	 * @param string $email User email.
	 *
	 * @return array
	 */
	public static function getUserRoles( $email ) {
		if ( empty( $email ) ) {
			return [];
		}

		try {
			$user = get_user_by( 'email', sanitize_email( $email ) );
			if ( is_object( $user ) && isset( $user->roles ) ) {
				return (array) $user->roles;
			}
		} catch ( \Exception $e ) {

		}

		return [];
	}

	/**
	 * Create nonce for woocommerce.
	 *
	 * @param string $action
	 *
	 *
	 * @return false|string
	 */
	public static function createNonce( $action = '' ) {
		if ( empty( $action ) ) {
			return false;
		}

		return wp_create_nonce( $action );
	}


	/**
	 * Check the validity of a security nonce and the admin privilege.
	 *
	 * @param string $nonce_name The name of the nonce.
	 *
	 * @return bool
	 */
	public static function isSecurityValid( $nonce_name = '' ) {
		$nonce = Input::get( 'rnoc_nonce', '' );
		if ( ! self::hasAdminPrivilege() || ! self::verifyNonce( $nonce, $nonce_name ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Verify nonce.
	 *
	 * @param string $nonce Nonce.
	 *
	 * @param string $action Action.
	 *
	 * @return bool
	 */
	public static function verifyNonce( $nonce, $action ) {
		if ( empty( $nonce ) || empty( $action ) ) {
			return false;
		}

		return wp_verify_nonce( $nonce, $action );
	}
}