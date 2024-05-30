<?php

namespace RNOC\App\Controllers\Admin;

use RNOC\App\Helpers\Util;
use RNOC\App\Helpers\WP;

defined( 'ABSPATH' ) || exit;

class Settings {

	/**
	 * Add menu.
	 *
	 * @return void
	 */
	public static function addMenu() {
		if ( ! WP::hasAdminPrivilege() ) {
			return;
		}
		add_menu_page( __( 'Retainful', 'retainful-next-order-coupon-for-woocommerce' ), __( 'Retainful', 'retainful-next-order-coupon-for-woocommerce' ), 'manage_woocommerce', 'retainful_license', [
			self::class,
			'getLicensePage'
		], 'dashicons-controls-repeat', 56 );
		add_submenu_page( 'retainful_license', __( 'Connection', 'retainful-next-order-coupon-for-woocommerce' ), __( 'Connection', 'retainful-next-order-coupon-for-woocommerce' ), 'manage_woocommerce', 'retainful_license', [
			self::class,
			'getLicensePage'
		] );
		add_submenu_page( 'retainful_license', __( 'Settings', 'retainful-next-order-coupon-for-woocommerce' ), __( 'Settings', 'retainful-next-order-coupon-for-woocommerce' ), 'manage_woocommerce', 'retainful_settings', array(
			self::class,
			'getSettingsPage'
		) );
	}

	/**
	 * Display Connection page.
	 *
	 * @return void
	 */
	public static function getLicensePage() {
		if ( ! WP::hasAdminPrivilege() ) {
			return;
		}
		$file_path     = RNOC_PLUGIN_PATH . 'App/Views/Admin/connection.php';
		$override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/admin/connection.php' );
		if ( file_exists( $override_path ) ) {
			$file_path = $override_path;
		}
		$sub_content        = Util::renderTemplate( $file_path, [
			'settings'    => \RNOC\App\Helpers\Settings::getConnectionSettings(),
			'app_url'     => "https://app.retainful.com",
			'is_pro_plan' => \RNOC\App\Helpers\Settings::isProPlan()
		], false );
		$main_file_path     = RNOC_PLUGIN_PATH . 'App/Views/Admin/tabs.php';
		$main_override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/admin/tabs.php' );
		if ( file_exists( $main_override_path ) ) {
			$main_file_path = $main_override_path;
		}
		Util::renderTemplate( $main_file_path, [ 'page' => 'retainful_license', 'sub_content' => $sub_content ] );
	}

	/**
	 * Display setting page.
	 *
	 * @return void
	 */
	public static function getSettingsPage() {
		if ( ! WP::hasAdminPrivilege() ) {
			return;
		}
		$file_path     = RNOC_PLUGIN_PATH . 'App/Views/Admin/settings.php';
		$override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/admin/settings.php' );
		if ( file_exists( $override_path ) ) {
			$file_path = $override_path;
		}

		$sub_content = Util::renderTemplate( $file_path, [ 'settings' => \RNOC\App\Helpers\Settings::getSettings() ], false );

		$main_file_path = RNOC_PLUGIN_PATH . 'App/Views/Admin/tabs.php';

		$main_override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/admin/tabs.php' );
		if ( file_exists( $main_override_path ) ) {
			$main_file_path = $main_override_path;
		}
		Util::renderTemplate( $main_file_path, [ 'page' => 'retainful_settings', 'sub_content' => $sub_content ] );
	}


	/**
	 * add admin script function
	 *
	 * @return void
	 */
	public static function addAdminScript() {
		//$page = Input::getData( 'page', '' );
		$page = 'retainful_license';
		if ( ! in_array( $page, array( 'retainful_setting', 'retainful_license' ) ) ) {
			return;
		}
		$asset_path = RNOC_PLUGIN_URL . 'assets/admin';
		$localize   = array(
			'rnoc_save_settings'      => wp_create_nonce( 'rnoc_save_settings' ),
			'rnoc_disconnect_license' => wp_create_nonce( 'rnoc_disconnect_license' ),
			'validate_app_key'        => wp_create_nonce( 'validate_app_key' ),
			'ajax_url'                => admin_url( 'admin-ajax.php' ),
			'admin_url'               => admin_url(),
			'home_url'                => get_home_url(),

		);
		wp_enqueue_style( 'retainful-admin-css', $asset_path . '/css/main.css', array(), RNOC_VERSION );
		wp_enqueue_script( 'retainful-abandoncart', $asset_path . '/js/rnoc_admin.js', array(), RNOC_VERSION );
		wp_localize_script( 'retainful-abandoncart', 'rnoc_localize_data', $localize );
		/*End Admin React */

	}

	/**
	 * Schedule plan checker.
	 *
	 * @return void
	 */
	public static function schedulePlanChecker() {
		$hook      = 'rnocp_check_user_plan';
		$timestamp = wp_next_scheduled( $hook );
		if ( false === $timestamp ) {
			$scheduled_time = strtotime( '+12 hours', current_time( 'timestamp' ) );
			wp_schedule_event( $scheduled_time, 'hourly', $hook );
		}
	}

	/**
	 * Check use plan.
	 *
	 * @return void
	 */
	public static function checkUserPlan() {
		// do check here
	}

}