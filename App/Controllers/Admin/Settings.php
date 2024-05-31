<?php

namespace RNOC\App\Controllers\Admin;

use RNOC\App\Helpers\Util;
use Rnoc\App\Helpers\WC;
use RNOC\App\Helpers\WP;
use RNOC\App\Helpers\Input;
use RNOC\App\library\RetainfulApi;
use Valitron\Validator;


defined( 'ABSPATH' ) || exit;

class Settings extends BaseController {

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
		$page = Input::get( 'page', '' );
		if ( ! in_array( $page, [ 'retainful_license', 'retainful_settings' ] ) ) {
			return;
		}
		$asset_path = RNOC_PLUGIN_URL . 'assets/admin';
		wp_enqueue_style( 'retainful-admin-css', $asset_path . '/css/main.css', [], RNOC_VERSION );
		wp_enqueue_script( 'retainful-abandoncart', $asset_path . '/js/rnoc_admin.js', [], RNOC_VERSION );
		$localize = [
			'save_settings'      => WP::createNonce( 'rnoc_save_settings' ),
			'disconnect_license' => WP::createNonce( 'rnoc_disconnect_license' ),
			'validate_app_key'   => WP::createNonce( 'rnoc_validate_app_key' ),
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
	 * Validate app Id
	 */
	public static function validateAppKey() {
		$security_check = wp::isSecurityValid( 'rnoc_validate_app_key' );
		if ( empty( $security_check ) ) {
			wp_send_json_error( 'security validation failed' );
		}
		$app_id     = Input::get( 'app_id' );
		$secret_key = Input::get( 'app_secret' );
		$data       = [
			'app_id'     => $app_id,
			'secret_key' => $secret_key
		];

		$validator = new Validator( $data );
		$validator->rule( 'required', [ 'app_id', 'secret_key' ] );
		$validator->rule( 'slug', [ 'app_id', 'secret_key' ] );
		if ( ! $validator->validate() ) {
			$response['error'] = $validator->errors();
			wp_send_json( $response );
		}

		$is_production = apply_filters( 'rnoc_is_production_plugin', true );
		if ( ! $is_production ) {
			wp_send_json_error( 'You can only change you App-Id and Secret key in production store!', 500 );
		}

		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0, 'license' );
		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'retainful_app_id', $app_id, 'license' );
		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', $secret_key, 'license' );

		$response = array();
		self::updateUserAsFreeUser();
		if ( empty( $response ) ) {
			$api_response = self::isApiEnabled( $app_id, $secret_key );

			if ( isset( $api_response['success'] ) ) {
				//Change app id status
				\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 1, 'license' );
				$response['success'] = $api_response['success'];
			} elseif ( isset( $api_response['error'] ) ) {
				$response['error'] = $api_response['error'];
			} else {
				$response['error'] = __( 'Please check the entered details', 'retainful-next-order-coupon-for-woocommerce' );
			}
		}
		wp_send_json( $response );
	}


	/**
	 * disconnect the app.
	 */
	public static function disConnectConnection() {
		$security_check = wp::isSecurityValid( 'rnoc_disconnect_license' );
		if ( empty( $security_check ) ) {
			wp_send_json_error( 'security validation failed' );
		}
		\RNOC\App\Helpers\Settings::set( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0, 'license' );
		wp_send_json_success( __( 'App disconnected successfully!', 'retainful-next-order-coupon-for-woocommerce' ) );
	}

	/**
	 * update user as Free user
	 */
	public static function updateUserAsFreeUser() {
		$details = RetainfulApi::getPlanDetails();
		self::updatePlanDetails( $details );
	}

	/**
	 * update the plan details
	 *
	 * @param array $details
	 */
	public static function updatePlanDetails( $details = array() ) {
		update_option( 'rnoc_plan_details', $details );
		update_option( 'rnoc_last_plan_checked', current_time( 'timestamp' ) );
	}

	/**
	 * Check fo entered API key is valid or not
	 *
	 * @param string $api_key
	 * @param string $secret_key
	 * @param string $store_data
	 *
	 * @return bool|array
	 */
	public static function isApiEnabled( $api_key = "", $secret_key = null, $store_data = null ) {

		$api_key    = empty( $api_key ) ? self::getApiKey() : $api_key;
		$secret_key = empty( $secret_key ) ? self::getSecretKey() : $secret_key;
		$store_data = empty( $store_data ) ? self::storeDetails( $api_key, $secret_key ) : $store_data;
		if ( ! empty( $api_key ) ) {

			if ( $details = RetainfulApi::validateApi( $api_key, $store_data ) ) {
				if ( empty( $details ) || is_string( $details ) ) {
					self::updateUserAsFreeUser();

					return array( 'error' => $details );
				} else {

					self::updatePlanDetails( $details );

					return array( 'success' => isset( $details['message'] ) ? $details['message'] : null );
				}
			} else {
				self::updateUserAsFreeUser();

				return false;
			}
		} else {
			self::updateUserAsFreeUser();

			return false;
		}
	}

	/**
	 * Get Admin API key
	 * @return String|null
	 */
	public static function getApiKey() {
		return \RNOC\App\Helpers\Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '', 'retainful_license' );
	}

	/**
	 * Get Admin API key
	 * @return String|null
	 */
	public static function getSecretKey() {
		return \RNOC\App\Helpers\Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'retainful_license' );
	}

}