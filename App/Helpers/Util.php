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
	const HMAC_ALGORITHM = 'sha256';

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

	/**
	 * Check the hash matches or not
	 *
	 * @param $hash
	 * @param $data
	 *
	 * @return bool
	 */
	public static function isHashMatches( $hash, $data ) {

		$is_valid_hash = false;

		if ( hash_equals( self::hashTheData( $data ), $hash ) ) {
			$is_valid_hash = true;
		}

		return $is_valid_hash;
	}

	/**
	 * Hash the data
	 *
	 * @param $data
	 *
	 * @return false|string
	 */
	public static function hashTheData( $data ) {

		$secret = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );

		return hash_hmac( self::HMAC_ALGORITHM, $data, $secret );
	}

	/**
	 * Set identity.
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public static function setIdentity( $value = '' ) {
		$popup_widget = Settings::get( RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup', 'no' );
		if ( ! WP::isCustomerPage() || empty( $value ) || $popup_widget == 'no' ) {
			return;
		}
		$cookie      = new Cookie();
		$cookie_data = [ 'email' => trim( $value ) ];
		$cookie->remove( '_wc_rnoc_tk_session' );
		if ( function_exists( 'wc_setcookie' ) ) {
			wc_setcookie( '_wc_rnoc_tk_session', base64_encode( json_encode( $cookie_data ) ), strtotime( '+30 days' ) );
		}
	}

}