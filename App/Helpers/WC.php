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
	 * @param int $timestamp Time stamp.
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
	 * Get coupon usage count.
	 *
	 * @param \WC_Coupon $coupon Coupon object.
	 *
	 * @return int
	 */
	public static function getCouponUsageCount( $coupon ) {
		if ( Util::isMethodExists( $coupon, 'get_usage_count' ) ) {
			return $coupon->get_usage_count();
		}

		return 0;
	}

	/**
	 * Get coupon expire date.
	 *
	 * @param \WC_Coupon $coupon Coupon object.
	 *
	 * @return string
	 */
	public static function getCouponDateExpires( $coupon ) {
		if ( Util::isMethodExists( $coupon, 'get_date_expires' ) ) {
			return $coupon->get_date_expires();
		}

		return '';
	}

	/**
	 * Coupon discount type.
	 *
	 * @param \WC_Coupon $coupon Coupon object.
	 *
	 * @return string
	 */
	public static function getCouponDiscountType( $coupon ) {
		if ( Util::isMethodExists( $coupon, 'get_discount_type' ) ) {
			return $coupon->get_discount_type();
		}

		return '';
	}

	/**
	 * Get coupon code.
	 *
	 * @param \WC_Coupon $coupon Coupon code.
	 *
	 * @return string
	 */
	public static function getCouponCode( $coupon ) {
		if ( Util::isMethodExists( $coupon, 'get_code' ) ) {
			return $coupon->get_code();
		}

		return '';
	}

	/**
	 * Get applied discounts.
	 *
	 * @param WC_Order|null $order Order object.
	 *
	 * @return array
	 */
	public static function getAppliedDiscounts( $order = null ) {
		$discounts = [];
		if ( ! is_null( $order ) ) {
			$applied_discounts = Order::getUsedCoupons( $order );
		} else {
			$applied_discounts = Cart::getAppliedCartCoupons();
		}
		$i = 1;
		if ( ! empty( $applied_discounts ) ) {
			foreach ( $applied_discounts as $applied_discount ) {
				if ( ! $applied_discount instanceof \WC_Coupon ) {
					$applied_discount = new \WC_Coupon( $applied_discount );
				}
				$discounts[] = array(
					"id"            => $i,
					"usage_count"   => self::getCouponUsageCount( $applied_discount ),
					"code"          => self::getCouponCode( $applied_discount ),
					"date_expires"  => self::getCouponDateExpires( $applied_discount ),
					"discount_type" => self::getCouponDiscountType( $applied_discount ),
					"created_at"    => null,
					"updated_at"    => null
				);
			}
		}

		return $discounts;
	}

	/**
	 * Get client sessions.
	 *
	 * @return array
	 */
	public static function getClientSession() {
		$session = array(
			'cart'                      => self::getSession( 'cart' ),
			'applied_coupons'           => self::getSession( 'applied_coupons' ),
			'chosen_shipping_methods'   => self::getSession( 'chosen_shipping_methods' ),
			'shipping_method_counts'    => self::getSession( 'shipping_method_counts' ),
			'chosen_payment_method'     => self::getSession( 'chosen_payment_method' ),
			'previous_shipping_methods' => self::getSession( 'previous_shipping_methods' ),
		);

		return apply_filters( 'rnoc_get_client_session', $session );
	}

}