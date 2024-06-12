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
		return Util::isMethodExists( $order, 'get_billing_email' ) ? $order->get_billing_email() : '';
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

	/**
	 * Get order status.
	 *
	 * @param WC_Order $order Order object.
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
	 * Set order meta.
	 *
	 * @param int $order_id Order id.
	 * @param string $meta_key Meta key.
	 * @param mixed $meta_value Meta value.
	 *
	 * @return void
	 */
	public static function setOrderMeta( $order_id, $meta_key, $meta_value ) {
		if ( ! empty( $order_id ) && ! empty( $meta_key ) ) {
			$order = self::getOrder( intval( $order_id ) );
			$order->update_meta_data( $meta_key, $meta_value );
			$order->save_meta_data();
		}
	}

	/**
	 * Get order created date.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $format Display format.
	 *
	 * @return string|null
	 */
	public static function getOrderDate( $order, $format = null ) {
		if ( Util::isMethodExists( $order, 'get_date_created' ) ) {
			$date = $order->get_date_created();
			if ( ! empty( $format ) ) {
				return $date->format( $format );
			}

			return $date;
		}

		return null;
	}

	/**
	 * Get order number.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return int|string
	 */
	public static function getOrderNumber( $order ) {
		if ( Util::isMethodExists( $order, 'get_order_number' ) ) {
			return $order->get_order_number();
		}

		return self::getOrderId( $order );
	}

	/**
	 * Get order items.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return array
	 */
	public static function getOrderItems( $order ) {
		if ( Util::isMethodExists( $order, 'get_items' ) ) {
			return $order->get_items();
		}

		return [];
	}

	/**
	 * Get order paid date.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $format Date format.
	 *
	 * @return null
	 */
	public static function getOrderPaidDate( $order, $format = null ) {
		if ( ! empty( $format ) && ! is_string( $format ) ) {
			$format = null;
		}
		if ( Util::isMethodExists( $order, 'get_date_paid' ) ) {
			$date_object = $order->get_date_paid();
			if ( is_object( $date_object ) && Util::isMethodExists( $date_object, 'getTimestamp' ) ) {
				$date = $date_object->getTimestamp();

				return ! empty( $format ) && Util::isMethodExists( $date_object, 'format' ) ? $date_object->format( $format ) : $date;
			}
		}

		return null;
	}

	/**
	 * Get retainful order status.
	 *
	 * @param string $order_status Order status.
	 *
	 * @return string
	 */
	public static function getRetainFulOrderStatus( $order_status ) {
		if ( empty( $order_status ) ) {
			return $order_status;
		}

		$changeable_order_statuses = [ 'checkout-draft' ];
		if ( Settings::get( RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status', 1 ) == 1 ) {
			$changeable_order_statuses[] = 'cancelled';
		}
		if ( Settings::get( RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 0 ) == 1 ) {
			$changeable_order_statuses[] = 'cancelled';
		}
		if ( Settings::get( RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status', 0 ) == 1 ) {
			$changeable_order_statuses[] = 'cancelled';
		}
		if ( in_array( $order_status, $changeable_order_statuses ) ) {
			$order_status = 'pending';
		}

		return $order_status;
	}

	/**
	 * Get order subtotal.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return float
	 */
	public static function getOrderSubtotal( $order ) {
		if ( Util::isMethodExists( $order, 'get_subtotal' ) ) {
			return $order->get_subtotal();
		}

		return 0.0;
	}

	/**
	 * Get order language.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return string
	 */
	public static function getOrderLanguage( $order ) {
		$language          = is_object( $order ) ? self::getOrderMeta( 'wpml_language', $order ) : '';
		$selected_language = '';
		if ( ! empty( $language ) ) {
			$languages = function_exists( 'icl_get_languages' ) ? icl_get_languages() : [];
			if ( ! empty( $languages ) && ! empty( $languages[ $language ] ) ) {
				$selected_language = ! empty( $languages[ $language ]['default_locale'] ) ? $languages[ $language ]['default_locale'] : '';
			}
		}
		if ( empty( $selected_language ) ) {
			$selected_language = WC::getSiteDefaultLanguage();
		}

		return apply_filters( 'rnoc_get_order_language', $selected_language );
	}

	/**
	 * Get order discount.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool $excluding is excluding tax.
	 *
	 * @return float
	 */
	public static function getOrderDiscount( $order, $excluding = true ) {
		if ( Util::isMethodExists( $order, 'get_total_discount' ) ) {
			return $order->get_total_discount( $excluding );
		}

		return 0.0;
	}

	/**
	 * Get item subtotal.
	 *
	 * @param \WC_Order_Item_Product $item Order item object.
	 *
	 * @return float
	 */
	public static function getItemSubtotal( $item ) {
		if ( Util::isMethodExists( $item, 'get_subtotal' ) ) {
			return $item->get_subtotal();
		}

		return 0.0;
	}

	/**
	 * Get item subtotal tax.
	 *
	 * @param \WC_Order_Item_Product $item Order item object.
	 *
	 * @return float
	 */
	public static function getItemTaxSubTotal( $item ) {
		if ( Util::isMethodExists( $item, 'get_subtotal_tax' ) ) {
			return $item->get_subtotal_tax();
		}

		return 0.0;
	}

	/**
	 * Get order items total.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return float|int
	 */
	public static function getOrderItemsTotal( $order ) {
		$subtotal = 0;
		$cart     = self::getOrderItems( $order );
		if ( ! empty( $cart ) ) {
			foreach ( $cart as $item ) {
				$subtotal += self::getItemSubTotal( $item );
				if ( ! WC::isPriceExcludingTax() ) {
					$subtotal += self::getItemTaxSubTotal( $item );
				}
			}
		}

		return $subtotal;
	}

	/**
	 * Get order shipping total.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $context Context.
	 *
	 * @return float
	 */
	public static function getOrderShippingTotal( $order, $context = 'edit' ) {
		if ( Util::isMethodExists( $order, 'get_shipping_total' ) ) {
			return $order->get_shipping_total( $context );
		}

		return 0.0;
	}

	/**
	 * @param WC_Order $order Order object.
	 *
	 * @return array
	 */
	public static function getOrderFees( $order ) {
		if ( Util::isMethodExists( $order, 'get_fees' ) ) {
			return $order->get_fees();
		}

		return [];
	}

	/**
	 * Get payment method.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return string
	 */
	public static function getPaymentMethod( $order ) {
		if ( Util::isMethodExists( $order, 'get_payment_method' ) ) {
			return $order->get_payment_method();
		}

		return '';
	}

	/**
	 * Get payment method title.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return string
	 */
	public static function getPaymentMethodTitle( $order ) {
		if ( Util::isMethodExists( $order, 'get_payment_method_title' ) ) {
			return $order->get_payment_method_title();
		}

		return '';
	}
}