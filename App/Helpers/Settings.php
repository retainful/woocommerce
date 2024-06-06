<?php

namespace RNOC\App\Helpers;

use RNOC\App\Modules\Storage\PHPSession;
use RNOC\App\Modules\Storage\WooSession;
use Rnoc\Retainful\Api\AbandonedCart\Storage\Cookie;
use Valitron\Validator;

defined( 'ABSPATH' ) || exit;

class Settings {

	/**
	 * Get license settings.
	 *
	 * @return array
	 */
	public static function getConnectionSettings() {
		$settings = get_option( 'retainful_license', [] );
		$default  = [
			RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => 0,
			RNOC_PLUGIN_PREFIX . 'retainful_app_id'       => '',
			RNOC_PLUGIN_PREFIX . 'retainful_app_secret'   => '',
		];

		return wp_parse_args( $settings, $default );
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public static function getSettings() {
		$settings = get_option( 'retainful_settings', [] );
		$default  = [
			RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'                   => 'js',
			RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'           => 'no',
			RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'                 => 'no',
			RNOC_PLUGIN_PREFIX . 'enable_referral_widget'                 => 'no',
			RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup'                   => 'no',
			RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget'         => 'yes',
			RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'   => '0',
			RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status' => '1',
			RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'    => '0',
			RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'         => '0',
			RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'                 => '0',
			RNOC_PLUGIN_PREFIX . 'cart_capture_msg'                       => 'Keep me up to date on news and exclusive offers',
			RNOC_PLUGIN_PREFIX . 'gdpr_display_position'                  => 'after_billing_email',
			RNOC_PLUGIN_PREFIX . 'enable_ip_filter'                       => '0',
			RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'                   => '',
			RNOC_PLUGIN_PREFIX . 'enable_debug_log'                       => '0',
			RNOC_PLUGIN_PREFIX . 'handle_storage_using'                   => 'woocommerce',
			RNOC_PLUGIN_PREFIX . 'enable_afterpay_action'                 => 'no',
			RNOC_PLUGIN_PREFIX . 'varnish_check'                          => 'no',
		];

		return wp_parse_args( $settings, $default );
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed $default Setting default value.
	 * @param string $type Setting type.
	 *
	 * @return mixed|string
	 */
	public static function get( $key, $default = '', $type = 'settings' ) {
		if ( ! in_array( $type, [ 'settings', 'license' ] ) ) {
			return $default;
		}
		$settings = $type === 'license' ? self::getConnectionSettings() : self::getSettings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * set setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed $value Setting default value.
	 * @param string $type Setting type.
	 *
	 * @return void
	 */
	public static function set( $key, $value, $type = 'settings' ) {
		if ( ! in_array( $type, [ 'settings', 'license' ] ) ) {
			return;
		}
		$settings    = $type === 'license' ? self::getConnectionSettings() : self::getSettings();
		$setting_key = $type === 'license' ? 'retainful_license' : 'retainful_settings';
		if ( isset( $settings[ $key ] ) ) {
			$settings[ $key ] = $value;
		}

		update_option( $setting_key, $settings );
	}


	/**
	 * Check is pro plan.
	 *
	 * @return bool
	 */
	public static function isProPlan() {
		return true;
	}

	/**
	 * Identity path.
	 *
	 * @return string
	 */
	public static function getIdentityPath() {
		$path = preg_replace( '|https?://[^/]+|i', '', get_option( 'home' ) );

		return ! empty( $path ) ? $path : '/';
	}

	/**
	 * Get identity value.
	 *
	 * @param string $key Identity name.
	 * @param mixed $default_value Identity value.
	 *
	 * @return mixed|string
	 */
	public static function getIdentity( $key, $default_value = '' ) {
		return isset( $_COOKIE[ $key ] ) ? $_COOKIE[ $key ] : $default_value;
	}

	/**
	 * Set identity value.
	 *
	 * @param string $key Identity key.
	 * @param array $value Identity value.
	 *
	 * @return void
	 */
	public static function setIdentity( $key, $value ) {
		if ( ! WP::isCustomerPage() || empty( $key ) || empty( $value ) || ! is_array( $value ) ) {
			return;
		}
		if ( function_exists( 'wc_setcookie' ) ) {
			wc_setcookie( $key, base64_encode( json_encode( $value ) ), strtotime( '+30 days' ) );
		}
	}

	/**
	 * Get storage object.
	 *
	 * @return PHPSession|WooSession|Cookie
	 */
	public static function getStorage() {
		$storage = Settings::get( RNOC_PLUGIN_PREFIX . 'handle_storage_using', 'woocommerce' );

		switch ( $storage ) {
			case "php";
				$storage_handler = new PHPSession();
				break;
			case "cookie";
				$storage_handler = new Cookie();
				break;
			default:
			case "woocommerce":
				$storage_handler = new WooSession();
				break;
		}

		return $storage_handler;
	}

	/**
	 * Get Setting Data.
	 *
	 * @return array|mixed
	 */
	public static function getData( $option_key, $default = [] ) {
		if ( ! in_array( $option_key, [ 'retainful_license', 'retainful_settings' ] ) ) {
			return false;
		}

		return get_option( $option_key, $default );
	}

	/**
	 * update Data.
	 *
	 * @return array|mixed
	 */
	public static function updateData( $option_key, $default = [] ) {
		if ( ! in_array( $option_key, [ 'retainful_license', 'retainful_settings' ] ) ) {
			return false;
		}

		return update_option( $option_key, $default );
	}


	/**
	 * Update plan details.
	 *
	 * @param object|string $response Response.
	 *
	 * @return void
	 */

	/*
	 * success response
	 *Array
		(
		    [plan] => business
		    [status] => active
		    [cancel_at_period_end] =>
		    [type] => monthly
		    [success] => 1
		    [message] => Store verified and connected successfully!
		)
	 */

	/*
	 * error response
	 * Array (
         [message] => App ID is not valid.
         [success] =>
		)
	 */
	public static function updatePlanDetails( $response = '' ) {
		$details = [
			'plan'       => 'free',
			'status'     => 'active',
			'expired_on' => 'never',
			'message'    => __( 'App connected successfully', 'retainful-next-order-coupon-for-woocommerce' )
		];
		if ( is_object( $response ) ) {
			$details = [
				'plan'       => ! empty( $response->plan ) ? strtolower( $response->plan ) : 'free',
				'status'     => ! empty( $response->status ) ? strtolower( $response->status ) : 'active',
				'expired_on' => ! empty( $response->period_end ) ? strtolower( $response->period_end ) : 'never',
				'message'    => ! empty( $response->message ) ? strtolower( $response->message ) : 'App connected successfully'
			];
		}

		update_option( 'rnoc_plan_details', $details );
		update_option( 'rnoc_last_plan_checked', current_time( 'timestamp' ) );
	}

	/**
	 * validate settings date.
	 *
	 * @param $post_data
	 *
	 * @return string|array
	 */
	public static function settingsValidation( $post_data ) {

		if ( empty( $post_data ) && ! is_array( $post_data ) ) {
			return __( 'validation failed!', 'retainful-next-order-coupon-for-woocommerce' );
		}
		$validator = new Validator( $post_data );
		$validator->rule( 'in', RNOC_PLUGIN_PREFIX . 'cart_tracking_engine', [
			'js',
			'php'
		] )->message( 'This field contains invalid value' );
		$validator->rule( 'in', [
			RNOC_PLUGIN_PREFIX . 'track_zero_value_carts',
			RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'
		], [ 'yes', 'no' ] )->message( 'This field contains invalid value' );
		$validator->rule( 'in', RNOC_PLUGIN_PREFIX . 'handle_storage_using', [
			'woocommerce',
			'cookie',
			'php'
		] )->message( 'This field contains invalid value' );
		$validator->rule( 'in', [
			RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status',
			RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status',
			RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status',
			RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load',
			RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance',
			RNOC_PLUGIN_PREFIX . 'enable_ip_filter',
			RNOC_PLUGIN_PREFIX . 'enable_debug_log',
		], [ '0', '1' ] )->message( 'This field contains invalid value' );

		if ( $validator->validate() ) {
			return true;
		}

		return $validator->errors();
	}
}