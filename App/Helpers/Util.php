<?php

namespace RNOC\App\Helpers;

use RNOC\App\Modules\Storage\Cookie;

defined( 'ABSPATH' ) || exit;


class Util {
	/**
	 * Is method exists in object.
	 *
	 * @param object $object Object.
	 * @param string $method Method name.
	 *
	 * @return bool
	 */
	/** The HMAC hash algorithm to use to sign the encrypted cart data */


	public static function isMethodExists( $object, $method ) {
		if ( is_object( $object ) && method_exists( $object, $method ) ) {
			return true;
		}

		return false;
	}

	/**
	 * render template.
	 *
	 * @param string $file File path.
	 * @param array $data Template data.
	 * @param bool $display Display or not.
	 *
	 * @return string|void
	 */
	public static function renderTemplate( $file, array $data = [], $display = true ) {
		$content = '';
		if ( file_exists( $file ) ) {
			ob_start();
			extract( $data );
			include $file;
			$content = ob_get_clean();
		}
		if ( $display ) {
			echo $content;
		} else {
			return $content;
		}
	}


}