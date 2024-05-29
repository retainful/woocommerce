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
}