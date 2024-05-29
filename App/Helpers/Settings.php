<?php

namespace Rnoc\App\Helpers;

use http\Encoding\Stream\Deflate;
use Rnoc\App\Storage\Cookie;
use Rnoc\App\Storage\PhpSession;
use Rnoc\App\Storage\WCSession;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
class Settings {

	/**
	 * Get Default Data.
	 *
	 * @return array
	 */
	public static function getDefaultData() {
		return [
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
	}

	/**
	 * Get Setting Data.
	 *
	 * @return array|mixed
	 */
	public static function getData( $option_key, $default = [] ) {
		return get_option( $option_key, $default );
	}

	/**
	 * get single settings data.
	 *
	 * @param string $key Setting key.
	 *
	 * @return mixed|string
	 */
	public static function get( $option_key, $key, $default = '' ) {
		$options = self::getData( $option_key );
		if ( ! isset( $options[ $key ] ) ) {
			$default_data = self::getDefaultData();

			return isset( $default_data[ $key ] ) && $default_data[ $key ] ? $default_data[ $key ]['value'] : $default;
		}

		return $options[ $key ];
	}


	/**
	 * init the storage classes
	 */
	public static function initStorage() {
		$storage_handler = \Rnoc\App\Controller\Admin\Settings::getStorageHandler();
		switch ( $storage_handler ) {
			case "php";
				$storage = new PhpSession();
				break;
			case "cookie";
				$storage = new Cookie();
				break;
			default:
			case "woocommerce":
				$storage = new WCSession();
				break;
		}

		return $storage;
	}

	/**
	 * Set identity.
	 *
	 * @param $value
	 *
	 * @return void
	 */
	public static function setIdentity( $value = '' ) {
		if ( ! self::isCustomerPage() || empty( $value ) || ! self::needPopupWidget() ) {
			return;
		}
		$cookie      = new \Rnoc\App\Storage\Cookie();
		$cookie_data = [ 'email' => trim( $value ) ];
		$cookie->removeValue( '_wc_rnoc_tk_session' );
		if ( function_exists( 'wc_setcookie' ) ) {
			wc_setcookie( '_wc_rnoc_tk_session', base64_encode( json_encode( $cookie_data ) ), strtotime( '+30 days' ) );
		}
	}

	/**
	 * Is customer page.
	 *
	 * @return bool
	 */
	public static function isCustomerPage() {
		if ( is_ajax() ) {
			return true;
		}

		return ! is_admin();
	}

	public static function needPopupWidget() {
		$need_widget = self::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup', 'no' );

		return apply_filters( "retainful_enable_popup_widget", ( $need_widget === "yes" ) );
	}


}