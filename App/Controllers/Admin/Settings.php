<?php

namespace RNOC\App\Controllers\Admin;

use RNOC\App\Helpers\Util;
use RNOC\App\Helpers\WC;

defined( 'ABSPATH' ) || exit;

class Settings {

	/**
	 * Add menu.
	 *
	 * @return void
	 */
	public static function addMenu() {
		if ( ! WC::hasAdminPrivilege() ) {
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
		if ( ! WC::hasAdminPrivilege() ) {
			return;
		}
		$file_path     = RNOC_PLUGIN_PATH . 'App/Views/Admin/connection.php';
		$override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/connection.php' );
		if ( file_exists( $override_path ) ) {
			$file_path = $override_path;
		}
		$sub_content        = Util::renderTemplate( $file_path, [
			'settings'    => \RNOC\App\Helpers\Settings::getConnectionSettings(),
			'app_url'     => '',
			'is_pro_plan' => \RNOC\App\Helpers\Settings::isProPlan()
		], false );
		$main_file_path     = RNOC_PLUGIN_PATH . 'App/Views/Admin/tabs.php';
		$main_override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/tabs.php' );
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
		if ( ! WC::hasAdminPrivilege() ) {
			return;
		}
		$file_path     = RNOC_PLUGIN_PATH . 'App/Views/Admin/settings.php';
		$override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/settings.php' );
		if ( file_exists( $override_path ) ) {
			$file_path = $override_path;
		}

		$sub_content = Util::renderTemplate( $file_path, [ 'settings' => \RNOC\App\Helpers\Settings::getSettings() ], false );

		$main_file_path = RNOC_PLUGIN_PATH . 'App/Views/Admin/tabs.php';

		$main_override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/tabs.php' );
		if ( file_exists( $main_override_path ) ) {
			$main_file_path = $main_override_path;
		}
		Util::renderTemplate( $main_file_path, [ 'page' => 'retainful_settings', 'sub_content' => $sub_content ] );
	}


	/**
	 * check this page are retainful page
	 *
	 * @return void
	 */
	public static function addAdminPageStyles() {
//		$page = Input::getData( 'page', '' );
		$page = 'retainful_license';
		if ( is_admin() && in_array( $page, array(
				'retainful_settings',
				'retainful_license'
			) ) ) {
			self::addScript();
		}
	}

	/**
	 * add retainful admin style and script
	 *
	 * @return void
	 */
	public static function addScript() {
		//$page = Input::getData( 'page', '' );
		$page = 'retainful_license';
		if ( ! in_array( $page, array( 'retainful_setting', 'retainful_license' ) ) ) {
			return;
		}
		$asset_path = RNOC_PLUGIN_URL . 'assets/admin';
		wp_enqueue_style( 'retainful-admin-css', $asset_path . '/css/main.scss', array(), RNOC_VERSION );
	}
}