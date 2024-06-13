<?php

namespace RNOC\App\Helpers;

use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

class Order {

	/**
	 * Get order id.
	 *
	 * @param   WC_Order  $order  Order object.
	 *
	 * @return int
	 */
	public static function getOrderId( $order ) {
		return Util::isMethodExists( $order, 'get_id' ) ? $order->get_id() : 0;
	}

	/**
	 * Get order user id.
	 *
	 * @param   WC_Order  $order  Order object.
	 *
	 * @return int
	 */
	public static function getOrderUserId( $order ) {
		return Util::isMethodExists( $order, 'get_user_id' ) ? $order->get_user_id() : 0;
	}

	/**
	 * Get an order object.
	 *
	 * @param   int|WC_Order  $order_or_id  Order object or id.
	 *
	 * @return WC_Order|WC_Order_Refund|bool
	 */
	public static function getOrder( $order_or_id ) {
		return function_exists( 'wc_get_order' ) ? wc_get_order( $order_or_id ) : false;
	}

	/**
	 * Get order meta.
	 *
	 * @param   string    $meta_key  Meta key.
	 * @param   WC_Order  $order     Order object
	 *
	 * @return mixed
	 */
	public static function getOrderMeta( $meta_key, $order ) {
		return Util::isMethodExists( $order, 'get_meta' ) ? $order->get_meta( $meta_key ) : '';
	}

	/**
	 * Get orders by email.
	 *
	 * @param   string  $email  Order email.
	 * @param   int     $limit
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
	 * @param   WC_Order  $order  Order object.
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
	 * @param   WC_Order  $order  Order object
	 *
	 * @return float
	 */
	public static function getOrderTotal( $order ) {
		return Util::isMethodExists( $order, 'get_total' ) ? $order->get_total() : 0;
	}

	/**
	 * Get order key data.
	 *
	 * @param   string    $key      Order key
	 * @param   WC_Order  $order    Order object.
	 * @param   mixed     $default  default value.
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
	 * @param   WC_Order  $order  Order object.
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

	/**
	 * Get order status.
	 *
	 * @param   WC_Order  $order  Order object.
	 *
	 * @return string
	 */
	public static function getStatus( $order ) {
		if ( Util::isMethodExists( $order, 'get_status' ) ) {
			$order_status = $order->get_status();

			return strtolower( $order_status );
		}

		return '';
	}

	/**
	 * Get order received url.
	 *
	 * @param   WC_Order  $order  Order object.
	 *
	 * @return string
	 */
	public static function getOrderReceivedURL( $order ) {
		return Util::isMethodExists( $order, 'get_checkout_order_received_url' ) ? $order->get_checkout_order_received_url() : '';
	}

	/**
	 * Get order payment url.
	 *
	 * @param   WC_Order  $order  Order object.
	 *
	 * @return string
	 */
	public static function getOrderPaymentURL( $order ) {
		return Util::isMethodExists( $order, 'get_checkout_payment_url' ) ? $order->get_checkout_payment_url() : '';
	}

	/**
	 * Apply coupon to the order.
	 *
	 * @param   string     $coupon  Coupon code to apply for the order.
	 * @param   \WC_Order  $order   Order object.
	 *
	 * @return bool True or false
	 */

	public static function applyCouponToOrder( $coupon, $order ) {
		if ( ! self::isValidCoupon( $coupon ) || ! Util::isMethodExists( $order, "apply_coupon" ) || ! self::canApplyCoupon( $coupon, $order ) ) {
			return false;
		}
		if ( $order->apply_coupon( $coupon ) === true ) {
			return true;
		}

		return false;
	}

	/**
	 * Check the coupon code.
	 *
	 * @param   string  $coupon_code  Woocommerce coupon code.
	 *
	 * @return bool|\WP_Error
	 */
	public static function isValidCoupon( $coupon_code ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return false;
		}
		$coupon = new \WC_Coupon( $coupon_code );
		if ( Util::isMethodExists( $coupon, "is_valid" ) ) {
			return $coupon->is_valid();
		}
		if ( ! class_exists( 'WC_Discounts' ) ) {
			return false;
		}
		$discounts = new \WC_Discounts();
		if ( Util::isMethodExists( $discounts, "is_coupon_valid" ) ) {
			try {
				return $discounts->is_coupon_valid( $coupon );
			}
			catch ( \Exception $e ) {

			}
		}

		return false;
	}

	/**
	 * Check the coupon can applicable.
	 *
	 * @param   string     $coupon_code  Coupon code.
	 * @param   \WC_Order  $order        Order object.
	 *
	 * @return bool
	 */
	public static function canApplyCoupon( $coupon_code, $order ) {
		if ( empty( $coupon_code ) || ! Util::isMethodExists( $order, 'get_items' ) ) {
			return false;
		}
		$new_coupon = new \WC_Coupon( $coupon_code );
		if ( ! Util::isMethodExists( $new_coupon, 'get_individual_use' ) ) {
			return false;
		}
		if ( $new_coupon->get_individual_use() && count( $order->get_items( 'coupon' ) ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Get order Email form order object.
	 *
	 * @param   WC_Order  $order   Order object.
	 * @param   string    $status  Order status.
	 *
	 * @return bool
	 */
	public static function hasOrderStatus( $order, $status ) {
		return Util::isMethodExists( $order, 'has_status' ) && $order->has_status( $status );
	}


	/**
	 * Set order status.
	 *
	 * @param   WC_Order  $order   Order object.
	 * @param   string    $status  Order status.
	 * @param   string    $note    Order note.
	 *
	 * @return bool
	 */
	public static function setOrderStatus( $order, $status, $note ) {
		return Util::isMethodExists( $order, 'update_status' ) && $order->update_status( $status, $note );
	}

	/**
	 * Set order note.
	 *
	 * @param   \WC_Order  $order  Order object.
	 * @param   string     $note   Order note.
	 */
	public static function setOrderNote( $order, $note ) {
		Util::isMethodExists( $order, 'add_order_note' ) && $order->add_order_note( $note );
	}

	/**
	 * Check if order needs payment or not.
	 *
	 * @param   \WC_Order  $order  Order object.
	 *
	 * @return bool
	 */
	public static function isOrderNeedPayment( $order ) {
		return Util::isMethodExists( $order, 'needs_payment' ) && $order->needs_payment();
	}


	/**
	 * Get the woocommerce checkout url.
	 *
	 * @return mixed|null
	 *
	 */
	public static function getCheckoutUrl() {
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';

		return apply_filters( 'rnoc_get_checkout_url', $checkout_url );
	}

	/**
	 * Get coupon usage count.
	 *
	 * @param   \WC_Coupon  $coupon  Coupon object.
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
	 * @param   \WC_Coupon  $coupon  Coupon object.
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
	 * @param   \WC_Coupon  $coupon  Coupon object.
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
	 * @param   \WC_Coupon  $coupon  Coupon code.
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
	 * @param   WC_Order|null  $order  Order object.
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
					'id'            => $i,
					'usage_count'   => self::getCouponUsageCount( $applied_discount ),
					'code'          => self::getCouponCode( $applied_discount ),
					'date_expires'  => self::getCouponDateExpires( $applied_discount ),
					'discount_type' => self::getCouponDiscountType( $applied_discount ),
					'created_at'    => null,
					'updated_at'    => null
				);
			}
		}

		return $discounts;
	}
}