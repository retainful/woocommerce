<?php

namespace RNOC\App\Helpers;

use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

class WC {

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

		return apply_filters( 'rnoc_get_default_currency_code', $currency );
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

	/**
	 * Init woocommerce session.
	 *
	 * @return void
	 */
	public static function initWoocommerceSession() {
		if ( ! self::hasSession() && ! defined( 'DOING_CRON' ) ) {
			self::setSessionCookie( true );
		}
	}

	/**
	 * Check session available.
	 *
	 * @return bool
	 */
	public static function hasSession() {

		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) || is_null( WC()->session ) ) {
			return false;
		}

		if ( Util::isMethodExists( WC()->session, 'has_session' ) ) {
			return WC()->session->has_session();
		}

		return false;
	}

	/**
	 * Set session cookie.
	 *
	 * @param bool $value cookie value.
	 *
	 * @return void
	 */
	public static function setSessionCookie( $value ) {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) || is_null( WC()->session ) ) {
			return;
		}

		if ( Util::isMethodExists( WC()->session, 'set_customer_session_cookie' ) ) {
			WC()->session->set_customer_session_cookie( $value );
		}
	}

	/**
	 * Set session.
	 *
	 * @param string $key Session key.
	 * @param mixed $value Session value.
	 *
	 * @return void
	 */
	public static function setSession( $key, $value ) {
		if ( empty( $key ) || ! function_exists( 'WC' ) || ! isset( WC()->session ) || is_null( WC()->session ) ) {
			return;
		}
		self::initWoocommerceSession();
		if ( Util::isMethodExists( WC()->session, 'set' ) ) {
			WC()->session->set( $key, $value );
		}
	}

	/**
	 * Get session.
	 *
	 * @param string $key Session key.
	 * @param mixed $default Default value.
	 *
	 * @return mixed
	 */
	public static function getSession( $key, $default = null ) {
		if ( empty( $key ) ) {
			return $default;
		}
		if ( function_exists( 'WC' ) && isset( WC()->session ) && Util::isMethodExists( WC()->session, 'get' ) ) {
			return WC()->session->get( $key, $default );
		}

		return $default;
	}

	/**
	 * Remove session.
	 *
	 * @param string $key Session key.
	 *
	 * @return bool
	 */
	public static function removeSession( $key ) {
		if ( empty( $key ) || ! function_exists( 'WC' ) || ! Util::isMethodExists( WC()->session, '__unset' ) ) {
			return false;
		}
		WC()->session->__unset( $key );

		return true;
	}

	/**
	 * woocommerce get store Country.
	 *
	 * @return string|null
	 */
	public static function getStoreCountry() {
		return function_exists( 'WC' ) && isset( WC()->countries ) && Util::isMethodExists( WC()->countries, 'get_base_country' ) ? WC()->countries->get_base_country() : null;
	}


	/**
	 * woocommerce get store state.
	 *
	 * @return string|null
	 */
	public static function getStoreState() {
		return function_exists( 'WC' ) && isset( WC()->countries ) && Util::isMethodExists( WC()->countries, 'get_base_state' ) ? WC()->countries->get_base_state() : null;
	}

	/**
	 * Check the site has multi-currency.
	 *
	 * @return bool
	 */
	public static function getAllAvailableCurrencies() {
		$base_currency = WC::getDefaultCurrency();
		$currencies    = array( $base_currency );

		return apply_filters( 'rnoc_get_available_currencies', $currencies );
	}


	/**
	 * Get the current currency.
	 *
	 * @return string
	 */
	public static function getCurrentCurrencyCode() {
		$default_currency = self::getDefaultCurrency();

		return apply_filters( 'rnoc_get_current_currency_code', $default_currency );
	}

	/**
	 * Format price
	 *
	 * @param float $price Price.
	 *
	 * @return float
	 */
	public static function formatDecimalPrice( $price ) {
		$decimals = WC::getPriceDecimals();
		$price    = floatval( $price );

		return round( $price, $decimals );
	}

	/**
	 * Get price decimal.
	 *
	 * @return int
	 */
	public static function getPriceDecimals() {
		if ( function_exists( 'wc_get_price_decimals' ) ) {
			return wc_get_price_decimals();
		}

		return 2;
	}

	/**
	 * Format to Iso 8601
	 *
	 * @param int|string $timestamp Time stamp.
	 *
	 * @return string|null
	 */
	public static function formatToIso8601( $timestamp = '' ) {
		if ( empty( $timestamp ) ) {
			$timestamp = current_time( 'timestamp', true );
		}
		if ( $timestamp instanceof \WC_DateTime ) {
			$timestamp = $timestamp->getTimestamp();
		}

		try {
			$date      = date( 'Y-m-d H:i:s', $timestamp );
			$date_time = new \DateTime( $date );

			return $date_time->format( \DateTime::ATOM );
		} catch ( \Exception $e ) {

		}

		return null;
	}

	/**
	 * Format price.
	 *
	 * @param float $price Price.
	 *
	 * @return string
	 */
	public static function formatDecimalPriceRemoveTrailingZeros( $price ) {
		$price         = (float) $price;
		$decimals      = WC::getPriceDecimals();
		$rounded_price = round( $price, $decimals );

		return number_format( $rounded_price, $decimals, '.', '' );
	}

	/**
	 * Is price excluding tax.
	 *
	 * @return bool
	 */
	public static function isPriceExcludingTax() {
		return ( 'excl' == get_option( 'woocommerce_tax_display_cart' ) );
	}


	/**
	 * Get client sessions.
	 *
	 * @return array
	 */
	public static function getClientSession() {
		$session = [
			'cart'                      => self::getSession( 'cart' ),
			'applied_coupons'           => self::getSession( 'applied_coupons' ),
			'chosen_shipping_methods'   => self::getSession( 'chosen_shipping_methods' ),
			'shipping_method_counts'    => self::getSession( 'shipping_method_counts' ),
			'chosen_payment_method'     => self::getSession( 'chosen_payment_method' ),
			'previous_shipping_methods' => self::getSession( 'previous_shipping_methods' ),
		];

		return apply_filters( 'rnoc_get_client_session', $session );
	}


	/**
	 * Add woocommerce notice.
	 *
	 * @param string $message Notice message.
	 *
	 */
	public static function addNotice( $message ) {
		function_exists( 'wc_add_notice' ) && wc_add_notice( $message );
	}


	/**
	 * Clear all notices.
	 */
	public static function clearWooNotices() {
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}
	}

	/**
	 * get Ordered Date
	 *
	 * @param $order
	 * @param $format
	 *
	 * @return null
	 */
	public static function getOrderPlacedDate( $order, $format = null ) {
		$date = null;
		if ( Util::isMethodExists( $order, 'get_date_paid' ) ) {
			$dateObject = $order->get_date_paid();
			if ( is_object( $dateObject ) && $dateObject instanceof \WC_DateTime ) {
				$date = $dateObject->getTimestamp();
			}
			if ( ! is_null( $format ) ) {
				$date = $dateObject->format( $format );
			}

			return $date;
		}

		return null;
	}

}