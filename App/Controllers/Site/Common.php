<?php

namespace RNOC\App\Controllers\Site;

use RNOC\App\Helpers\Settings;

defined( 'ABSPATH' ) || exit;

class Common {
	/**
	 * Change cookie path.
	 *
	 * @param array $option Cookie options.
	 * @param string $name Cookie name.
	 * @param string $value Cookie value.
	 *
	 * @return array
	 */
	public static function changeIdentityPath( $option, $name, $value ) {
		if ( $name != '_wc_rnoc_tk_session' ) {
			return $option;
		}
		$option['path'] = Settings::getIdentityPath();

		return $option;
	}

	public static function setIdentityData() {

	}
}