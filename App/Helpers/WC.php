<?php

namespace RNOC\App\Helpers;

use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

class WC {

	public static function getCustomer( $key, $default = '' ) {
		if ( empty( $key ) ) {
			return $default;
		}
		$method = 'get_' . $key;
		if ( function_exists( 'WC' ) && Util::isMethodExists( WC()->customer, $method ) ) {
			return WC()->customer->$method();
		}

		return $default;
	}

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

	public static function removeSession( $key ) {
		if ( empty( $key ) || ! function_exists( 'WC' ) || ! Util::isMethodExists( WC()->session, '__unset' ) ) {
			return false;
		}
		WC()->session->__unset( $key );

		return true;
	}

	/**
	 * Get order object.
	 *
	 * @param int|WC_Order $order_or_id Order object or id.
	 *
	 * @return WC_Order|WC_Order_Refund|bool
	 */
	public static function getOrder( $order_or_id ) {
		return function_exists( 'wc_get_order' ) ? wc_get_order( $order_or_id ) : false;
	}

	/**
	 * Get order user id.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return int
	 */
	public static function getOrderUserId( $order ) {
		return Util::isMethodExists( $order, 'get_user_id' ) ? $order->get_user_id() : 0;
	}

	/**
	 * Get order billing email.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return string
	 */
	public static function getOrderBillingEmail( $order ) {
		if ( ! Util::isMethodExists( $order, 'get_billing_email' ) ) {
			return '';
		}

		return $order->get_billing_email();
	}

	/**
	 * Get order key data.
	 *
	 * @param string $key Order key
	 * @param WC_Order $order Order object.
	 * @param mixed $default default value.
	 *
	 * @return mixed
	 */
	public static function getOrderData( $key, $order, $default = '' ) {
		if ( empty( $key ) ) {
			return $default;
		}
		$method = 'get_' . $key;

		return Util::isMethodExists( $order, $method ) ? $order->$method() : $default;
	}

	/**
	 * Get orders by email.
	 *
	 * @param string $email Order email.
	 * @param int $limit
	 *
	 * @return array
	 */
	public static function getOrdersByEmail( $email, $limit = - 1 ) {
		if ( empty( $email ) || ! is_email( $email ) ) {
			return [];
		}
		$args = [
			'billing_email' => $email,
			'orderby'       => 'ID',
			'order'         => 'DESC',
			'limit'         => $limit
		];

		return apply_filters( 'rnoc_get_customer_orders_by_email', wc_get_orders( $args ) );
	}

	/**
	 * Get order total.
	 *
	 * @param WC_Order $order Order object
	 *
	 * @return float
	 */
	public static function getOrderTotal( $order ) {
		return Util::isMethodExists( $order, 'get_total' ) ? $order->get_total() : 0;
	}

	/**
	 * Get order id.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return int
	 */
	public static function getOrderId( $order ) {
		return Util::isMethodExists( $order, 'get_id' ) ? $order->get_id() : 0;
	}
}