<?php

namespace RNOC\App\Helpers;


defined( 'ABSPATH' ) || exit;

class Cart {


	/**
	 * Check is empty cart.
	 *
	 * @return bool
	 */
	public static function isCartEmpty() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'is_empty' ) ) {
			try {
				return WC()->cart->is_empty();
			}
			catch ( \Exception $e ) {
				return true;
			}
		}

		return true;
	}

	/**
	 * Get cart fees.
	 *
	 * @return array
	 */
	public static function getCartFees() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_fees' ) ) {
			return WC()->cart->get_fees();
		}

		return [];
	}

	/**
	 * Get cart total.
	 *
	 * @return float|string
	 */
	public static function getCartTotal( $context = 'view' ) {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_total' ) ) {
			return wc()->cart->get_total( $context );
		}

		return 0;
	}

	/**
	 * Get cart shipping tax.
	 *
	 * @return float
	 */
	public static function getCartShippingTaxTotal() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_shipping_tax' ) ) {
			return wc()->cart->get_shipping_tax();
		}

		return 0.0;
	}

	/**
	 * Get cart tax details.
	 *
	 * @return array
	 */
	public static function getCartTaxDetails() {
		$tax_details = [];
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_tax_totals' ) ) {
			$tax_details = WC()->cart->get_tax_totals();
		}
		if ( empty( $tax_details ) ) {
			return [];
		}
		$taxes = [];
		foreach ( $tax_details as $tax_detail ) {
			$taxes[] = [
				'rate'  => 0,
				'price' => WC::formatDecimalPrice( ( isset( $tax_detail->amount ) ) ? $tax_detail->amount : 0 ),
				'title' => ( isset( $tax_detail->label ) ) ? $tax_detail->label : 'Tax'
			];
		}

		return $taxes;
	}

	/**
	 * Get shipping total.
	 *
	 * @return float
	 */
	public static function getCartShippingTotal() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_shipping_total' ) ) {
			return wc()->cart->get_shipping_total();
		}

		return 0.0;
	}

	/**
	 * Get cart total tax.
	 *
	 * @return float|int
	 */
	public static function getCartTotalTax() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_total_tax' ) ) {
			return wc()->cart->get_total_tax();
		}

		return 0;
	}

	/**
	 * Get discount total.
	 *
	 * @return float
	 */
	public static function getCartTotalDiscount() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_discount_total' ) ) {
			return WC()->cart->get_discount_total();
		}

		return 0.0;
	}

	/**
	 * Get Cart.
	 *
	 * @return array
	 */
	public static function getCart() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_cart' ) ) {
			return WC()->cart->get_cart();
		}

		return [];
	}

	/**
	 * Get cart subtotal.
	 *
	 * @return float
	 */
	public static function getCartSubTotal() {


		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) ) {
			return 0.0;
		}

		$subtotal = 0.0;
		if ( WC::isPriceExcludingTax() ) {
			if ( Util::isMethodExists( WC()->cart, 'get_subtotal' ) ) {
				$subtotal = WC()->cart->get_subtotal();
			}
		} else if ( Util::isMethodExists( WC()->cart, 'get_subtotal' ) && Util::isMethodExists( WC()->cart, 'get_subtotal_tax' ) ) {
			$subtotal = WC()->cart->get_subtotal() + WC()->cart->get_subtotal_tax();
		}

		return $subtotal;
	}

	/**
	 * Get tax total.
	 *
	 * @return float
	 */
	public static function getCartTaxTotal() {
		$tax_total = 0.0;
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_fee_tax' ) && Util::isMethodExists( WC()->cart, 'get_cart_contents_tax' ) ) {
			$tax_total = WC()->cart->get_fee_tax() + WC()->cart->get_cart_contents_tax();
		}

		return $tax_total;
	}

	/**
	 * Get applied coupons.
	 *
	 * @return array
	 */
	public static function getAppliedCartCoupons() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'get_coupons' ) ) {
			return WC()->cart->get_coupons();
		}

		return [];
	}


	/**
	 * Add to cart.
	 *
	 * @param   int    $product_id      product id.
	 * @param   int    $variation_id    variant id.
	 * @param   int    $quantity        product quantity.
	 * @param   array  $variation       variations.
	 * @param   array  $cart_item_data  cart item data.
	 *
	 * @return bool|string
	 */
	public static function addToCart( $product_id, $variation_id = 0, $quantity = 1, $variation = [], $cart_item_data = [] ) {
		if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || ! Util::isMethodExists( WC()->cart, 'add_to_cart' ) ) {
			return false;
		}
		try {
			return WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );
		}
		catch ( \Exception $e ) {

		}

		return false;
	}

	/**
	 *  empty cart.
	 *
	 * @return void
	 */
	public static function clearCart() {
		if ( function_exists( 'WC' ) && Util::isMethodExists( WC()->cart, 'empty_cart' ) ) {
			WC()->cart->empty_cart();
		}
	}

}