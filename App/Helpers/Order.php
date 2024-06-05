<?php

namespace RNOC\App\Helpers;

use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

class Order {

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
	 * Get an order object.
	 *
	 * @param int|WC_Order $order_or_id Order object or id.
	 *
	 * @return WC_Order|WC_Order_Refund|bool
	 */
	public static function getOrder( $order_or_id ) {
		return function_exists( 'wc_get_order' ) ? wc_get_order( $order_or_id ) : false;
	}

	/**
	 * Get order meta.
	 *
	 * @param string $meta_key Meta key.
	 * @param WC_Order $order Order object
	 *
	 * @return mixed
	 */
	public static function getOrderMeta( $meta_key, $order ) {
		return Util::isMethodExists( $order, 'get_meta' ) ? $order->get_meta( $meta_key ) : '';
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
	 * Get used coupons.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return array
	 */
	public static function getUsedCoupons( $order ) {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.7.0', '<' ) ) {
			if ( Util::isMethodExists( $order, 'get_used_coupons' ) ) {
				return $order->get_used_coupons();
			}
		} else if ( Util::isMethodExists( $order, 'get_coupon_codes' ) ) {
			return $order->get_coupon_codes();
		}

		return [];
	}
}