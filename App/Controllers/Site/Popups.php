<?php

namespace RNOC\App\Controllers\Site;

use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\Util;
use RNOC\App\Helpers\WC;
use RNOC\App\Helpers\WP;
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
		$customer_billing_email = WC::getCustomerBillingEmail();

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
				WC::setCustomerBillingEmail( $identity_data['email'] );
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
	 * Add referral popups.
	 *
	 * @return void
	 */
	public static function printReferralPopup() {
		if ( ! WP::isCustomerPage() ) {
			return;
		}

		$api_key           = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '', 'license' );
		$secret_key        = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );
		$user              = WP::getLoginUser();
		$user_data         = [
			'api_key'           => $api_key,
			'accepts_marketing' => '0',
			'email'             => is_object( $user ) && $user->user_email ? $user->user_email : '',
			'first_name'        => is_object( $user ) && $user->first_name ? $user->first_name : '',
			'id'                => is_object( $user ) && $user->ID ? $user->ID : '',
			'last_name'         => is_object( $user ) && $user->last_name ? $user->last_name : '',
			'tags'              => '',
		];
		$data              = implode( '', $user_data );
		$account_url       = esc_url( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) );
		$customer_email    = $user_data['email'];
		$is_thank_you_page = ( ! empty( is_wc_endpoint_url( 'order-received' ) ) );
		if ( $is_thank_you_page ) {
			WC::removeSession( 'rnoc_customer_total_orders' );
			WC::removeSession( 'rnoc_customer_total_spent' );
		}
		$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;
		if ( $is_thank_you_page && empty( $customer_email ) && ! empty( $order_id ) ) {
			$order          = Order::get( $order_id );
			$customer_email = WC::getOrderBillingEmail( $order );
		}
		$default = [
			'digest'       => hash_hmac( 'sha256', $data, $secret_key ),
			'referral_url' => apply_filters( 'referral_engine_url', 'https://js.retainful.com/woocommerce/v1/widget.js' ),
			'window'       => [
				'is_thank_you_page' => $is_thank_you_page,
				'customer_id'       => $user_data['id'],
				'customer_email'    => $customer_email,
				'login_url'         => apply_filters( 'rnoc_referral_login_url', $account_url ),
				'register_url'      => apply_filters( 'rnoc_referral_register_url', $account_url ),
			]
		];
		$params  = wp_parse_args( $user_data, $default );

		$file_path     = RNOC_PLUGIN_PATH . 'App/Views/Site/referral.php';
		$override_path = get_theme_file_path( 'retainful-next-order-coupon-for-woocommerce/site/referral.php' );
		if ( file_exists( $override_path ) ) {
			$file_path = $override_path;
		}
		Util::renderTemplate( $file_path, [ 'params' => $params ] );
	}
}