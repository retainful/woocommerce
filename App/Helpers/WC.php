<?php

namespace RNOC\App\Helpers;

defined( 'ABSPATH' ) || exit;

class WC {
	/**
	 * Has admin privilege.
	 *
	 * @return bool
	 */
	public static function hasAdminPrivilege() {
		return current_user_can( 'manage_woocommerce' );
	}


	/**
	 * check for method exists
	 *
	 * @param $obj
	 * @param $method
	 *
	 * @return bool
	 */
	public static function isMethodExists( $obj, $method ) {
		if ( is_object( $obj ) && method_exists( $obj, $method ) ) {
			return true;
		}

		return false;
	}


}