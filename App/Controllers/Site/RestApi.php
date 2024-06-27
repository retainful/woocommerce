<?php

namespace RNOC\App\Controllers\Site;

use RNOC\App\Helpers\Settings;
use RNOC\App\Modules\AbandonedCart\Coupon;

defined( 'ABSPATH' ) || exit;

class RestApi {
	/**
	 * Register api routes.
	 *
	 * @return void
	 */
	public static function registerEndPoints() {
		// do register endpoint

		register_rest_route( 'retainful-api/v1', '/verify', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => [ self::class, 'verifyAppId' ]
		] );
		register_rest_route( 'retainful-api/v1', '/coupon', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => [ Coupon::class, 'createRestCoupon' ]
		] );
	}

	/**
	 * Verify the app connection.
	 *
	 * @param \WP_REST_Request $data Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function verifyAppId( $data ) {
		$app_id             = sanitize_text_field( $data->get_param( 'app_id' ) );
		$app_secret         = sanitize_text_field( $data->get_param( 'app_secret' ) );
		$site_url           = site_url();
		$entered_app_id     = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '', 'license' );
		$entered_secret_key = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );
		$is_app_connected   = Settings::get( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', '', 'license' );;
		$response_code = 'INSTALLED_CONNECTED';
		if ( empty( $entered_app_id ) ) {
			$response_code = 'INSTALLED_NO_APP_ID_FOUND';
		} elseif ( empty( $entered_secret_key ) ) {
			$response_code = 'INSTALLED_NO_SECRET_KEY_FOUND';
		} elseif ( $app_id != $entered_app_id ) {
			$response_code = 'INSTALLED_DIFFERENT_APP_ID';
		} elseif ( $app_secret != $entered_secret_key ) {
			$response_code = 'INSTALLED_DIFFERENT_SECRET_KEY';
		} elseif ( ! $is_app_connected ) {
			$response_code = 'INSTALLED_NOT_CONNECTED';
		}
		$response        = array(
			'success' => $response_code == 'INSTALLED_CONNECTED',
			'message' => '',
			'code'    => $response_code,
			'data'    => array(
				'domain' => $site_url
			)
		);
		$response_object = new \WP_REST_Response( $response );
		$response_object->set_status( 200 );

		return $response_object;
	}
}