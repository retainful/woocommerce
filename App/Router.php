<?php

namespace RNOC\App;

use RNOC\App\Controllers\Admin\Settings;
use RNOC\App\Controllers\Site\Common;
use RNOC\App\Controllers\Site\Popups;
use RNOC\App\Controllers\Site\RestApi;

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
		//Register deactivation hook
		//register_deactivation_hook( RNOC_FILE, [ Common::class, 'onPluginDeactivation' ] );
		// Rest api
		add_action( 'rest_api_init', [ RestApi::class, 'registerEndPoints' ] );
		add_action( 'rnocp_check_user_plan', [ Settings::class, 'checkUserPlan' ] );
	}

	/**
	 * Admin side hooks.
	 *
	 * @return void
	 */
	public static function addAdminHooks() {
		add_action( 'admin_menu', [ Settings::class, 'addMenu' ] );
		add_action( 'wp_after_admin_bar_render', [ Settings::class, 'schedulePlanChecker' ] );
		add_action( 'admin_enqueue_scripts', [ Settings::class, 'addAdminScript' ] );
		//connection
		add_action( 'wp_ajax_rnoc_validate_connection', [ Settings::class, 'validateAppKey' ] );
		add_action( 'wp_ajax_rnoc_disconnect_connection', [ Settings::class, 'disConnectConnection' ] );


	}

	/**
	 * Store side hooks.
	 *
	 * @return void
	 */
	public static function addStoreHooks() {
		//Popups
		if ( \RNOC\App\Helpers\Settings::isProPlan() && \RNOC\App\Helpers\Settings::get( RNOC_PLUGIN_PREFIX . 'enable_referral_widget', 'no' ) == 'yes' ) {
			add_action( 'wp_footer', [ Popups::class, 'printReferralPopup' ] );
		}
		if ( \RNOC\App\Helpers\Settings::isProPlan() && \RNOC\App\Helpers\Settings::get( RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup', 'no' ) == 'yes' ) {
			// Cookie update hooks
			add_filter( 'woocommerce_set_cookie_options', [ Popups::class, 'changeIdentityPath' ], 10, 3 );
			add_action( 'woocommerce_init', [ Popups::class, 'setIdentityData' ] );
			add_action( 'user_register', [ Popups::class, 'setRegisterIdentity' ] );
			add_action( 'wp_login', [ Popups::class, 'setLoginIdentity' ], 10, 2 );
			add_action( 'wp_enqueue_scripts', [ Popups::class, 'addPopupScript' ] );
			add_action( 'wp_footer', [ Popups::class, 'printPopup' ] );
		}
	}
}