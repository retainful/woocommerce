<?php

namespace RNOC\App;

use RNOC\App\Controllers\Admin\Settings;
use RNOC\App\Controllers\Site\Common;

defined( 'ABSPATH' ) || exit;

class Router {
	/**
	 * init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		do_action( RNOC_PLUGIN_PREFIX . 'before_init' );
		self::addCommonHooks();
		if ( is_admin() ) {
			self::addAdminHooks();
		} else {
			self::addStoreHooks();
		}

		do_action( RNOC_PLUGIN_PREFIX . 'after_init' );
	}

	/**
	 * Add common hooks.
	 *
	 * @return void
	 */
	public static function addCommonHooks() {
		//add_filter( 'woocommerce_set_cookie_options', [ Common::class, 'changeIdentityPath' ], 10, 3 );
		//add_action( 'woocommerce_init', [ Common::class, 'setIdentityData' ] );
	}

	/**
	 * Admin side hooks.
	 *
	 * @return void
	 */
	public static function addAdminHooks() {
		add_action( 'admin_menu', [ Settings::class, 'addMenu' ] );
	}

	/**
	 * Store side hooks.
	 *
	 * @return void
	 */
	public static function addStoreHooks() {

	}
}