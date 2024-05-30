<?php

namespace RNOC\App\Helpers;

defined( 'ABSPATH' ) || exit;

class WC {

	/**
	 * Get Customer email.
	 *
	 * @return string
	 */
	public static function getCustomerBillingEmail() {

		if ( function_exists( 'WC' ) && Util::isMethodExists( WC()->customer, 'get_billing_email' ) ) {
			return WC()->customer->get_billing_email();
		}

		return '';
	}

	/**
	 * Set customer billing email.
	 *
	 * @param string $value Customer email.
	 *
	 * @return void
	 */
	public static function setCustomerBillingEmail( $value ) {
		if ( ! empty( $value ) && function_exists( 'WC' ) && Util::isMethodExists( WC()->customer, 'set_billing_email' ) ) {
			WC()->customer->set_billing_email( $value );
		}
	}

	/**
	 * Get default currency.
	 *
	 * @param string $currency Currency code.
	 *
	 * @return string
	 */
	public static function getDefaultCurrency( $currency = '' ) {
		if ( empty( $currency ) ) {
			$currency = get_woocommerce_currency();
		}

		return apply_filters( RNOC_PLUGIN_PREFIX . 'custom_default_currency', $currency );
	}

	/**
	 * Get default site language.
	 *
	 * @param string $current_lang Site language.
	 *
	 * @return string
	 */
	public static function getSiteDefaultLanguage( $current_lang = 'en_US' ) {
		if ( function_exists( 'get_locale' ) ) {
			$current_lang = get_locale();
			if ( empty( $current_lang ) || $current_lang == 'en' ) {
				$current_lang = 'en_US';
			}
		}

		return $current_lang;
	}
}