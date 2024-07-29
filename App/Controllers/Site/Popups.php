<?php

namespace RNOC\App\Controllers\Site;

use RNOC\App\Helpers\Cart;
use RNOC\App\Helpers\Customer;
use RNOC\App\Helpers\Order as OrderAlias;
use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\Util;
use RNOC\App\Helpers\WC;
use RNOC\App\Helpers\WP;
use RNOC\App\Helpers\Input;
use RNOC\App\Models\WC\Order;

defined( 'ABSPATH' ) || exit;

class Popups {
	/**
	 * Change cookie path.
	 *
	 * @param array $option Cookie options.
	 * @param string $name Cookie name.
	 * @param string $value Cookie value.
	 *
	 * @return array
	 */
	public static function changeIdentityPath( $option, $name, $value ) {
		if ( $name != '_wc_rnoc_tk_session' ) {
			return $option;
		}
		$option['path'] = Settings::getIdentityPath();

		return $option;
	}

	/**
	 * Set identity data.
	 *
	 * @return void
	 */
	public static function setIdentityData() {
		$customer_billing_email = Customer::getCustomerBillingEmail();

		if ( ! WP::isCustomerPage() || ! empty( $customer_billing_email ) ) {
			return;
		}
		$login_user_email = WP::getLoginUserEmail();
		$identity_data    = Settings::getIdentity( '_wc_rnoc_tk_session' );
		if ( empty( $identity_data ) && ! empty( $login_user_email ) ) {
			Settings::setIdentity( '_wc_rnoc_tk_session', [ 'email' => trim( $login_user_email ) ] );
		}
		$identity_data = Settings::getIdentity( '_wc_rnoc_tk_session' );
		if ( ! empty( $identity_data ) ) {
			$identity_data = json_decode( base64_decode( $identity_data ), true );
			if ( is_array( $identity_data ) && ! empty( $identity_data['email'] ) ) {
				Customer::setCustomerEmail( $identity_data['email'] );
			}
		}
	}

	/**
	 * Set register identity.
	 *
	 * @param int $user_id User id.
	 *
	 * @return void
	 */
	public static function setRegisterIdentity( $user_id ) {
		if ( $user_id <= 0 || ! WP::isCustomerPage() ) {
			return;
		}

		$login_user_email = WP::getLoginUserEmail();
		if ( ! empty( $login_user_email ) ) {
			Settings::setIdentity( '_wc_rnoc_tk_session', [ 'email' => trim( $login_user_email ) ] );
		}
	}

	/**
	 * Set login identity.
	 *
	 * @param string $user_name User name.
	 * @param \WP_User $user User object.
	 *
	 * @return void
	 */
	public static function setLoginIdentity( $user_name, $user ) {
		if ( ! WP::isCustomerPage() || ! is_object( $user ) || empty( $user->user_email ) ) {
			return;
		}
		Settings::setIdentity( '_wc_rnoc_tk_session', [ 'email' => trim( $user->user_email ) ] );
	}

	/**
	 * Add popup script.
	 *
	 * @return void
	 */
	public static function addPopupScript() {
		if ( ! WP::isCustomerPage() || ! ( Settings::get( RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup', 'no' ) == 'yes' ) ) {
			return;
		}
		$popup_js = apply_filters( 'rnoc_popup_js', 'https://js.retainful.com/woocommerce/v2/popup/production/poup-widget.js' );
		wp_enqueue_script( RNOC_PLUGIN_PREFIX . 'popups', $popup_js, [ 'jquery' ], RNOC_VERSION, true );
		$pop_coupon_url = apply_filters( 'rnoc_popup_coupon_url', RNOC_PLUGIN_URL . 'assets/site/js/popup_coupon.js' );
		wp_enqueue_script( RNOC_PLUGIN_PREFIX . 'popup-coupon', $pop_coupon_url, array(
			'jquery',
			RNOC_PLUGIN_PREFIX . 'popups'
		), RNOC_VERSION, false );
		$popup_data = [
			'ajax_url'               => admin_url( 'admin-ajax.php' ),
			'version'                => RNOC_VERSION,
			'popup_redirect_timeout' => apply_filters( 'rnoc_popup_redirect_time_after_add_to_cart', 1500 )
		];
		wp_localize_script( RNOC_PLUGIN_PREFIX . 'popup-coupon', 'retainful_popup_data', $popup_data );
	}

	public static function printPopup() {
		if ( ! WP::isCustomerPage() ) {
			return;
		}

		$api_key    = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '', 'license' );
		$secret_key = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );
		$user       = [
			'api_key' => $api_key,
			'email'   => WP::getLoginUserEmail(),
		];
		$data       = implode( '', $user );
		$default    = [
			'digest'        => hash_hmac( 'sha256', $data, $secret_key ),
			'email'         => '',
			'api_key'       => '',
			'path'          => Settings::getIdentityPath(),
			'domain'        => COOKIE_DOMAIN,
			'currency_code' => WC::getDefaultCurrency(),
			'lang'          => WC::getSiteDefaultLanguage()
		];
		$params     = wp_parse_args( $user, $default );

		$file_path     = RNOC_PLUGIN_PATH . 'App/Views/Site/popup.php';
		$override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/site/popup.php' );
		if ( file_exists( $override_path ) ) {
			$file_path = $override_path;
		}
		Util::renderTemplate( $file_path, [ 'params' => $params ] );
	}

	/**
	 * Add coupon code into session.
	 *
	 * @return void
	 */
	public static function addPopupCouponToSession() {

		$coupon_code = Input::get( 'coupon_code', '' );
		if ( empty( $coupon_code ) ) {
			wp_send_json_error( [
				'is_redirect' => false,
				'message'     => __( 'Coupon code invalid', 'retainful-next-order-coupon-for-woocommerce' )
			] );
		}
		WC::setSession( 'rnoc_popup_coupon_code', $coupon_code );
		wp_send_json_success( [
			'is_redirect' => true,
			'message'     => __( 'Coupon added into the Session', 'retainful-next-order-coupon-for-woocommerce' )
		] );
	}

	/**
	 * Add coupon code into cart.
	 *
	 * @return void
	 */
	public static function applyPopupCoupon() {
		$popup_coupon_code = WC::getSession( 'rnoc_popup_coupon_code' );

		if ( ! empty( $popup_coupon_code ) && function_exists( 'WC' ) && is_object( WC()->cart ) && ! Cart::isCartEmpty() ) {
			if ( \RNOC\App\Helpers\Order::hasDiscount( $popup_coupon_code ) ) {
				WC::setSession( 'rnoc_popup_coupon_code', '' );

				return;
			}
			\RNOC\App\Helpers\Order::addDiscount( $popup_coupon_code );
			WC::setSession( 'rnoc_popup_coupon_code', '' );
		}
	}
}