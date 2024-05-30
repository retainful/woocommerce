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
}