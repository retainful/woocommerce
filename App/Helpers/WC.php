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
	 * Check the site has multi currency
	 * @return bool
	 */
	public static function getAllAvailableCurrencies() {
		$base_currency = WC::getDefaultCurrency();
		$currencies    = array( $base_currency );

		return apply_filters( 'rnoc_get_available_currencies', $currencies );
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
	 * Get cart subtotal.
	 *
	 * @return float
	 */
	public static function getCartSubTotal() {
		if ( ! function_exists( 'WC' ) || isset( WC()->cart ) ) {
			return 0.0;
		}

		$subtotal = 0.0;
		if ( self::isPriceExcludingTax() ) {
			if ( Util::isMethodExists( WC()->cart, 'get_subtotal' ) ) {
				$subtotal = WC()->cart->get_subtotal();
			}
		} else if ( Util::isMethodExists( WC()->cart, 'get_subtotal' ) && Util::isMethodExists( WC()->cart, 'get_subtotal_tax' ) ) {
			$subtotal = WC()->cart->get_subtotal() + WC()->cart->get_subtotal_tax();
		}

		return $subtotal;
	}


	/**
	 * Get current currency.
	 *
	 * @return string
	 */
	public static function getCurrentCurrencyCode() {
		$default_currency = self::getDefaultCurrency();

		return apply_filters( 'rnoc_get_current_currency_code', $default_currency );
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
				'price' => self::formatDecimalPrice( ( isset( $tax_detail->amount ) ) ? $tax_detail->amount : 0 ),
				'title' => ( isset( $tax_detail->label ) ) ? $tax_detail->label : 'Tax'
			];
		}

		return $taxes;
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
	 * Get product
	 *
	 * @param int|\WC_Product $product Product id or object.
	 *
	 * @return false|\WC_Product
	 */
	public static function getProduct( $product ) {
		return function_exists( 'wc_get_product' ) ? wc_get_product( $product ) : false;
	}

	/**
	 * Get product name.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string|null
	 */
	public static function getItemName( $product ) {
		if ( Util::isMethodExists( $product, 'get_name' ) ) {
			return apply_filters( 'rnoc_get_item_name', $product->get_name(), $product );
		}

		return null;
	}

	/**
	 * Get product sku.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string|null
	 */
	public static function getItemSku( $product ) {
		if ( Util::isMethodExists( $product, 'get_sku' ) ) {
			return $product->get_sku();
		}

		return null;
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
	 * Get price excluding tax.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return float
	 */
	public static function getPriceExcludingTax( $product ) {
		$price = 0.0;
		if ( is_object( $product ) && function_exists( 'wc_get_price_excluding_tax' ) ) {
			$price = wc_get_price_excluding_tax( $product );
		}

		return $price;
	}

	/**
	 * Get price including tax.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return float
	 */
	public static function getPriceIncludingTax( $product ) {
		$price = 0.0;
		if ( is_object( $product ) && function_exists( 'wc_get_price_including_tax' ) ) {
			$price = wc_get_price_including_tax( $product );
		}

		return $price;
	}

	/**
	 * Get cart item price.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return float
	 */
	public static function getCartItemPrice( $product ) {
		if ( self::isPriceExcludingTax() ) {
			$price = self::getPriceExcludingTax( $product );
		} else {
			$price = self::getPriceIncludingTax( $product );
		}

		return $price;
	}

	/**
	 * Get product category ids.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array
	 */
	public static function getProductCategoryIds( $product_id ) {
		if ( empty( $product_id ) ) {
			return [];
		}

		if ( function_exists( 'wc_get_product_term_ids' ) ) {
			return wc_get_product_term_ids( $product_id, 'product_cat' );
		}

		return [];
	}

	/**
	 * Get product category name.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array
	 */
	public static function getProductCategoryName( $product_id ) {
		if ( empty( $product_id ) ) {
			return [];
		}
		$terms = get_the_terms( $product_id, 'product_cat' );

		return ( empty( $terms ) || is_wp_error( $terms ) ) ? [] : wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Get product image id.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return int
	 */
	public static function getProductImageId( $product ) {
		if ( Util::isMethodExists( $product, 'get_image_id' ) ) {
			return $product->get_image_id();
		}

		return 0;
	}

	/**
	 * Get product image source.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	public static function getProductImageSrc( $product ) {
		$image_id = self::getProductImageId( $product );
		if ( $image_id < 0 ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );

		$src = ! empty( $src ) ? $src : wc_placeholder_img_src();

		return apply_filters( 'rnoc_get_product_image_src', $src, $product );
	}

	/**
	 * Get product url.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	public static function getProductUrl( $product ) {
		if ( Util::isMethodExists( $product, 'get_permalink' ) ) {
			return $product->get_permalink();
		}

		return '';
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
			$applied_discounts = self::getUsedCoupons( $order );
		} else {
			$applied_discounts = self::getAppliedCartCoupons();
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
	 * Check is empty cart.
	 *
	 * @return bool
	 */
	public static function isCartEmpty() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && Util::isMethodExists( WC()->cart, 'is_empty' ) ) {
			try {
				return WC()->cart->is_empty();
			} catch ( \Exception $e ) {
				return true;
			}
		}

		return true;
	}
}