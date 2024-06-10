<?php

namespace RNOC\App\Controllers\Admin;

use RNOC\App\Helpers\Util;
use RNOC\App\Helpers\WP;
use RNOC\App\Helpers\Input;
use RNOC\App\Modules\AbandonedCart\AbandonedCart;
use RNOC\App\Modules\AbandonedCart\Request;
use Valitron\Validator;


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

		$sub_content = Util::renderTemplate( $file_path, [
			'settings' => \RNOC\App\Helpers\Settings::getSettings(),
		], false );

		$main_file_path = RNOC_PLUGIN_PATH . 'App/Views/Admin/tabs.php';

		$main_override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/admin/tabs.php' );
		if ( file_exists( $main_override_path ) ) {
			$main_file_path = $main_override_path;
		}
		Util::renderTemplate( $main_file_path, [ 'page' => 'retainful_settings', 'sub_content' => $sub_content ] );
	}


	/**
	 * Add admin script function.
	 *
	 * @return void
	 */
	public static function addAdminScript() {
		$page = (string) Input::get( 'page', '' );
		if ( ! in_array( $page, [ 'retainful_license', 'retainful_settings' ] ) ) {
			return;
		}
		$suffix = '.min';
		if ( defined( 'SCRIPT_DEBUG' ) ) {
			$suffix = SCRIPT_DEBUG ? '' : '.min';
		}
		$asset_path = RNOC_PLUGIN_URL . 'assets/admin';
		wp_enqueue_style( 'retainful-admin-css', $asset_path . '/css/main.css', [], RNOC_VERSION );
		wp_enqueue_script( 'retainful-abandoncart', $asset_path . '/js/rnoc_admin.js', [], RNOC_VERSION );
		wp_enqueue_style( RNOC_PLUGIN_SLUG . '-alertify', RNOC_PLUGIN_URL . 'assets/admin/css/alertify' . $suffix . '.css', array(), RNOC_VERSION );
		wp_enqueue_script( RNOC_PLUGIN_SLUG . '-alertify', RNOC_PLUGIN_URL . 'assets/admin/js/alertify' . $suffix . '.js', array(), RNOC_VERSION . '&t=' . time() );
		$localize = [
			'save_settings'      => WP::createNonce( 'rnoc-save-setting' ),
			'disconnect_license' => WP::createNonce( 'rnoc-disconnect-license' ),
			'app_connect'        => WP::createNonce( 'rnoc-app-connect' ),
			'ajax_url'           => admin_url( 'admin-ajax.php' ),
			'admin_url'          => admin_url(),
			'home_url'           => get_home_url(),
		];
		wp_localize_script( 'retainful-abandoncart', 'rnoc_localize_data', $localize );
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

	/**
	 * Connect to RetainFul.
	 *
	 * @return void
	 */
	public static function connect() {

		if ( ! WP::isSecurityValid( 'rnoc-app-connect' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic validation failed', 'retainful-next-order-coupon-for-woocommerce' ) ] );
		}


		$app_id     = (string) Input::get( 'app_id' );
		$secret_key = (string) Input::get( 'app_secret' );
		$data       = [
			'app_id'     => $app_id,
			'secret_key' => $secret_key
		];

		$validator = new Validator( $data );
		$validator->rule( 'required', [ 'app_id', 'secret_key' ] );
		$validator->rule( 'slug', [ 'app_id', 'secret_key' ] );
		if ( ! $validator->validate() ) {
			$errors = $validator->errors();
			foreach ( $errors as $field => $messages ) {
				$errors[ $field ] = current( $messages );
			}
			wp_send_json_error( [
				'error_fields' => $errors,
				'message'      => __( 'Basic validation failed', 'retainful-next-order-coupon-for-woocommerce' )
			] );
		}


		/*$is_production = apply_filters( 'rnoc_is_production_plugin', true );
		if ( ! $is_production ) {
			wp_send_json_error( [ 'message' => __( 'You can only change your App-Id and Secret key in production store!', 'retainful-next-order-coupon-for-woocommerce' ) ] );
		}*/

		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0, 'license' );
		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'retainful_app_id', $app_id, 'license' );
		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', $secret_key, 'license' );
		$data         = [
			'shop' => self::getStoreDetails( $app_id, $secret_key ),
		];
		$api_response = Request::connect( $app_id, $data );

		if ( empty( $api_response['success'] ) ) {
			\RNOC\App\Helpers\Settings::updatePlanDetails();
			wp_send_json_error( [ 'message' => __( 'Please check the entered details', 'retainful-next-order-coupon-for-woocommerce' ) ] );
		}
		//Change app id status
		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 1, 'license' );
		\RNOC\App\Helpers\Settings::updatePlanDetails( $api_response );
		wp_send_json_success( [ 'message' => $api_response['message'] ] );
	}


	/**
	 * Disconnect the app.
	 *
	 * @return void
	 */
	public static function disConnect() {
		if ( ! WP::isSecurityValid( 'rnoc-disconnect-license' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic validation failed', 'retainful-next-order-coupon-for-woocommerce' ) ] );
		}
		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0, 'license' );
		wp_send_json_success( [ 'message' => __( 'App disconnected successfully!', 'retainful-next-order-coupon-for-woocommerce' ) ] );
	}


	/**
	 * Get the store details.
	 *
	 * @param string $api_key Api key.
	 * @param string $secret_key Secret key.
	 *
	 * @return array
	 */
	public static function getStoreDetails( $api_key, $secret_key ) {
		if ( empty( $api_key ) && empty( $secret_key ) ) {
			return array();
		}
		$scheme           = wc_site_is_https() ? 'https' : 'http';
		$default_language = ''; //TODO:need to add the store language using the multilingual addon
		$time_zone        = \RNOC\App\Helpers\Settings::getData( 'timezone_string' );
		if ( empty( $time_zone ) ) {
			$time_zone = \RNOC\App\Helpers\Settings::getData( 'gmt_offset' );
		}

		return [
			'woocommerce_app_id'             => $api_key,
			'secret_key'                     => AbandonedCart::getEncryptData( $api_key, $secret_key ),
			'id'                             => null,
			'name'                           => \RNOC\App\Helpers\Settings::getData( 'blogname' ),
			'email'                          => \RNOC\App\Helpers\Settings::getData( 'admin_email' ),
			'domain'                         => get_home_url( null, null, $scheme ),
			'address1'                       => \RNOC\App\Helpers\Settings::getData( 'woocommerce_store_address', null ),
			'address2'                       => \RNOC\App\Helpers\Settings::getData( 'woocommerce_store_address_2', null ),
			'currency'                       => \RNOC\App\Helpers\WC::getDefaultCurrency(),
			'city'                           => \RNOC\App\Helpers\Settings::getData( 'woocommerce_store_city', null ),
			'zip'                            => \RNOC\App\Helpers\Settings::getData( 'woocommerce_store_postcode', null ),
			'country'                        => null,
			'timezone'                       => $time_zone,
			'weight_unit'                    => \RNOC\App\Helpers\Settings::getData( 'woocommerce_weight_unit' ),
			'country_code'                   => \RNOC\App\Helpers\WC::getStoreCountry(),
			'province_code'                  => \RNOC\App\Helpers\WC::getStoreState(),
			'force_ssl'                      => ( \RNOC\App\Helpers\Settings::getData( 'woocommerce_force_ssl_checkout', 'no' ) == 'yes' ),
			'enabled_presentment_currencies' => \RNOC\App\Helpers\WC::getAllAvailableCurrencies(),
			'primary_locale'                 => $default_language
		];
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function saveSettings() {

		if ( ! WP::isSecurityValid( 'rnoc-save-setting' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic validation failed', 'retainful-next-order-coupon-for-woocommerce' ) ] );
		}
		$settings = \RNOC\App\Helpers\Settings::getSettings();
		foreach ( $settings as $key => $value ) {
			$settings[ $key ] = Input::get( $key, $value );
		}
		$errors = \RNOC\App\Helpers\Settings::settingsValidation( $settings );
		if ( is_array( $errors ) ) {
			foreach ( $errors as $field => $messages ) {
				$errors[ $field ] = current( $messages );
			}
			wp_send_json_error( [
				'error_fields' => $errors,
				'message'      => __( 'Settings not saved!', 'retainful-next-order-coupon-for-woocommerce' )
			] );
		}
		$cart_capture_msg                                    = (string) Input::get( RNOC_PLUGIN_PREFIX . 'cart_capture_msg', '' );
		$settings                                            = Input::clean( $settings );
		$settings[ RNOC_PLUGIN_PREFIX . 'cart_capture_msg' ] = trim( Input::sanitizeContent( $cart_capture_msg ) );
		\RNOC\App\Helpers\Settings::updateData( 'retainful_settings', $settings );
		wp_send_json_success( [ 'message' => __( 'Settings successfully saved!', 'retainful-next-order-coupon-for-woocommerce' ) ] );
	}


}