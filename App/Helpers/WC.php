<?php

namespace Rnoc\App\Helpers;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\Utilities\Users;
use Rnoc\App\Helpers\Settings as SettingHelper;

class WC {

	/**
	 * get product data.
	 *
	 * @param $product_id
	 *
	 * @return array|false|\WC_Product|null
	 */
	public static function getProduct( $product_id ) {
		return function_exists( 'wc_get_product' ) ? wc_get_product( intval( $product_id ) ) : array();
	}

	/**
	 * get product image id.
	 *
	 * @param \WC_Product $product
	 *
	 * @return int|mixed
	 */
	public static function getProductImageId( $product ) {
		return self::isMethodExists( $product, 'get_image_id' ) ? $product->get_image_id() : 0;
	}


	/**
	 * get product image src
	 *
	 * @param \WC_Product $product
	 *
	 * @return mixed|null
	 */
	public static function getProductImageSrc( $product ) {
		$image_id = self::getProductImageId( $product );
		$image    = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
		$src      = ! empty( $image ) ? $image : wc_placeholder_img_src();

		return apply_filters( 'rnoc_get_product_image_src', $src, $product );
	}


	/**
	 * check for method exists
	 *
	 * @param $obj
	 * @param $method
	 *
	 * @return bool
	 */
	public static function isMethodExists( $obj, $method ) {
		if ( is_object( $obj ) && method_exists( $obj, $method ) ) {
			return true;
		}

		return false;
	}

	/**
	 * check for method exists
	 *
	 * @param $obj
	 * @param $method
	 *
	 * @return bool
	 */
	public static function isObjectExists( $obj, $value ) {
		if ( is_object( $obj ) && ! empty( $obj->$value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get used coupons of order
	 *
	 * @param $order
	 *
	 * @return null
	 */
	public static function getUsedCoupons( $order ) {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.7.0', '<' ) ) {
			return self::isMethodExists( $order, 'get_used_coupons' ) ? $order->get_used_coupons() : null;
		}

		return self::isMethodExists( $order, 'get_coupon_codes' ) ? $order->get_coupon_codes() : null;
	}


	/**
	 * Get order meta from order object
	 *
	 * @param $order
	 * @param $meta_key
	 *
	 * @return string
	 * @since 2.2.5
	 */
	public static function getOrderMeta( $order, $meta_key ) {
		return self::isMethodExists( $order, 'get_meta' ) ? $order->get_meta( $meta_key ) : '';
	}


	/**
	 * Get Product url
	 *
	 * @param $product
	 *
	 * @return String|null
	 */
	public static function getProductUrl( $product ) {
		return self::isMethodExists( $product, 'get_permalink' ) ? $product->get_permalink() : '';
	}


	/**
	 * get customer Email
	 * @return bool
	 */
	public static function getCustomerBillingEmail() {
		return self::isMethodExists( WC()->customer, 'get_billing_email' ) ? WC()->customer->get_billing_email() : false;
	}

	/**
	 * get customer billing Email
	 * @return string
	 */
	public static function getCustomerEmail() {
		return ! empty( self::getCustomerBillingEmail() ) ? self::getCustomerBillingEmail() : ( self::isMethodExists( WC()->customer, 'get_email' ) ? WC()->customer->get_email() : '' );
	}

	/**
	 * Get data from session
	 *
	 * @param $key
	 *
	 * @return array|string|null
	 */
	public static function getSession( $key ) {
		if ( empty( $key ) ) {
			return null;
		}
		if ( self::isMethodExists( WC()->session, 'get' ) ) {
			return WC()->session->get( $key );
		}

		return null;
	}


	/**
	 * get the client session details
	 * @return mixed|void
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
	 * Get cart items
	 * @return array
	 */
	public static function getCart() {
		return self::isMethodExists( WC()->cart, 'get_cart' ) ? WC()->cart->get_cart() : array();
	}


	/**
	 * Get cart items total tax
	 * @return float
	 */
	public static function getCartTotalTax() {
		return self::isMethodExists( WC()->cart, 'get_total_tax' ) ? WC()->cart->get_total_tax() : 0;
	}

	/**
	 * Get cart items subtotal.
	 *
	 * @return array
	 */
	public static function getCartSubTotal() {

		$subtotal = self::isObjectExists( WC()->cart, 'subtotal' ) ? WC()->cart->subtotal : 0;
		if ( self::isPriceExcludingTax() ) {
			if ( self::isObjectExists( WC()->cart, 'subtotal_ex_tax' ) ) {
				$subtotal = WC()->cart->subtotal_ex_tax;
			}
		}

		return $subtotal;
	}

	public static function getAppliedDiscounts( $order = null ) {
		$discounts = array();
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
	 * Get Applied coupons
	 * @return array
	 */
	public static function getAppliedCartCoupons() {
		return self::isMethodExists( WC()->cart, 'get_coupons' ) ? WC()->cart->get_coupons() : array();
	}

	/**
	 * Get Coupon usage count
	 *
	 * @param $coupon
	 *
	 * @return integer
	 */
	public static function getCouponUsageCount( $coupon ) {
		return self::isMethodExists( $coupon, 'get_usage_count' ) ? $coupon->get_usage_count() : 0;
	}

	public static function getCouponDateExpires( $coupon ) {
		return self::isMethodExists( $coupon, 'get_date_expires' ) ? $coupon->get_date_expires() : '';
	}

	public static function getCouponDiscountType( $coupon ) {
		return self::isMethodExists( $coupon, 'get_discount_type' ) ? $coupon->get_discount_type() : '';
	}

	/**
	 * Get cart items subtotal
	 * @return float
	 */
	public static function getCartTotalDiscount() {
		return self::isMethodExists( WC()->cart, 'get_discount_total' ) ? WC()->cart->get_discount_total() : 0;
	}

	/**
	 * Get cart items total
	 * @return float
	 */
	public static function getCartTotalPrice() {
		return isset( WC()->cart->total ) && ! empty( WC()->cart->total ) ? WC()->cart->total : 0;
	}


	/**
	 * Get cart items
	 * @return array
	 */
	public static function getCartTaxes() {
		return self::isMethodExists( WC()->cart, 'get_tax_totals' ) ? WC()->cart->get_tax_totals() : array();
	}


	/**
	 * get Cart total from woocommerce
	 * @return int|mixed
	 */
	public static function getCartTotal() {
		return ! empty( WC()->cart->subtotal ) ? WC()->cart->subtotal : 0;
	}

	/**
	 * Get Item name from Item object
	 *
	 * @param $item
	 *
	 * @return null
	 */
	public static function getItemName( $item ) {
		return self::isMethodExists( $item, 'get_name' ) ? apply_filters( 'rnoc_get_item_name', $item->get_name(), $item ) : '';
	}


	/**
	 * get coupon code from coupon object
	 *
	 * @param $coupon
	 *
	 * @return null
	 */
	public static function getCouponCode( $coupon ) {
		return self::isMethodExists( $coupon, 'get_code' ) ? $coupon->get_Code() : null;
	}

	/**
	 * get the default currency
	 * @return string|null
	 */
	public static function getDefaultCurrency() {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
	}


	/**
	 * Get cart items total tax
	 * @return int|float
	 */
	public static function getCartTaxTotal() {
		return self::isObjectExists( WC()->cart, 'tax_total' ) ? WC()->cart->tax_total : 0;
	}

	/**
	 * Get cart items shipping total
	 * @return int|float
	 */
	public static function getCartShippingTaxTotal() {
		return self::isObjectExists( WC()->cart, 'shipping_tax_total' ) ? WC()->cart->shipping_tax_total : 0;
	}

	/**
	 * Get cart items
	 * @return int|float
	 */
	public static function getCartDiscountTotal() {
		return self::isObjectExists( WC()->cart, 'discount_cart' ) ? WC()->cart->discount_cart : 0;
	}

	/**
	 * Get cart items
	 * @return int|float
	 */
	public static function getCartShippingTotal() {
		return self::isObjectExists( WC()->cart, 'shipping_total' ) ? WC()->cart->shipping_total : 0;
	}

	/**
	 * Get cart fees
	 * @return array
	 */
	public static function getCartFees() {
		return self::isMethodExists( WC()->cart, 'get_fees' ) ? WC()->cart->get_fees() : array();
	}

	/**
	 * get cart item price
	 *
	 * @param $product
	 *
	 * @return float|int
	 */
	public static function getCartItemPrice( $product ) {
		$price = self::getPriceIncludingTax( $product );
		if ( self::isPriceExcludingTax() ) {
			$price = self::getPriceExcludingTax( $product );
		}

		return $price;
	}

	/**
	 * Get price excluding tax.
	 *
	 * @param $product
	 *
	 * @return float|int
	 */
	public static function getPriceExcludingTax( $product ) {
		return is_object( $product ) && function_exists( 'wc_get_price_excluding_tax' ) ? wc_get_price_excluding_tax( $product ) : 0;
	}

	/**
	 * get price Including tax
	 *
	 * @param $product
	 *
	 * @return float|int
	 */
	public static function getPriceIncludingTax( $product ) {
		return is_object( $product ) && function_exists( 'wc_get_price_including_tax' ) ? wc_get_price_including_tax( $product ) : 0;
	}

	/**
	 * Check the price is including tax or excluding tax in cart and checkout page
	 * @return bool
	 */
	public static function isPriceExcludingTax() {
		return ( 'excl' == SettingHelper::getData( 'woocommerce_tax_display_cart' ) );
	}


	public static function checkSecuritykey( $security_name ) {
		$message = __( 'Security check failed', 'retainful-next-order-coupon-for-woocommerce' );
		if ( empty( $security_name ) ) {
			wp_send_json_error( $message );
		}
		check_ajax_referer( $security_name, 'security' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( $message );
		}
	}

	/**
	 * @return int|null
	 */
	public static function getCurrentUserId() {
		return function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
	}

	/**
	 * @return \WP_User|null
	 */
	public static function getCurrentUser() {
		return function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
	}

	/**
	 * @return mixed|null
	 */
	public static function getUserMeta( $user_id, $key, $single ) {
		return function_exists( 'get_user_meta' ) ? get_user_meta( $user_id, $key, $single ) : null;
	}

	public static function deleteUserMeta( $user_id, $meta_key ) {
		return function_exists( 'delete_user_meta' ) && delete_user_meta( $user_id, $meta_key );
	}

	public static function getWooPluginUrl() {
		return self::isMethodExists( WC(), 'plugin_url' ) ? WC()->plugin_url() : null;

	}

	public static function getStoreCountry() {
		return self::isMethodExists( WC()->countries, 'get_base_country' ) ? WC()->countries->get_base_country() : null;
	}

	public static function getStoreState() {
		return self::isMethodExists( WC()->countries, 'get_base_state' ) ? WC()->countries->get_base_state() : null;
	}

	public static function getUserRoles( $email ) {
		if ( empty( $email ) ) {
			return array();
		}
		try {
			$user = get_user_by( 'email', sanitize_email( $email ) );
			if ( $user && is_object( $user ) && isset( $user->roles ) ) {
				return ( array ) $user->roles;
			}
		} catch ( \Exception $e ) {

		}

		return array();
	}

	/**
	 * get price decimal separator
	 * @return null
	 */
	public static function priceDecimalSeparator() {
		return function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : null;
	}

	/**
	 * get price decimal separator
	 * @return null
	 */
	public static function priceDecimals() {
		return function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
	}

	/**
	 * get cart total
	 * @return float|int|mixed
	 */
	public static function getCartTotalForEdit() {
		return self::isMethodExists( WC()->cart, 'get_total' ) ? wc()->cart->get_total( 'edit' ) : self::getCartTotal();
	}


	public static function getProductCategoryName( $product_id ) {
		if ( empty( $product_id ) ) {
			return array();
		}
		$terms = get_the_terms( $product_id, 'product_cat' );

		return ( empty( $terms ) || is_wp_error( $terms ) ) ? array() : wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Get Item sku from Item object
	 *
	 * @param $item
	 *
	 * @return string
	 */
	public static function getItemSku( $item ) {
		return self::isMethodExists( $item, 'get_sku' ) ? $item->get_sku() : null;
	}

	/**
	 * get the default currency
	 *
	 * @param $product_id
	 *
	 * @return array
	 */
	public static function getProductCategoryIds( $product_id ) {
		return function_exists( 'wc_get_product_term_ids' ) ? wc_get_product_term_ids( $product_id, 'product_cat' ) : array();
	}

	/**
	 * Add admin notice.
	 *
	 * @param string $message Message.
	 * @param string $status Status.
	 *
	 * @return void
	 */
	public static function adminNotice( $message, $status = "success" ) {
		add_action( 'admin_notices', function () use ( $message, $status ) {
			?>
            <div class="notice notice-<?php echo esc_attr( $status ); ?>">
                <p><?php echo wp_kses_post( $message ); ?></p>
            </div>
			<?php
		}, 1 );
	}

	/**
	 * get user by filed.
	 *
	 * @param $field
	 * @param $user_name
	 *
	 * @return false|\stdClass|\WP_User
	 */
	public static function getUserBy( $field, $user_name ) {
		return function_exists( 'get_user_by' ) ? get_user_by( $field, $user_name ) : new \stdClass();
	}


	/**
	 * update the user meta data.
	 *
	 * @param $user_id
	 * @param $meta_key
	 * @param $meta_value
	 *
	 * @return bool|int
	 */
	public static function updateUserMeta( $user_id, $meta_key, $meta_value ) {
		return function_exists( 'update_user_meta' ) ? update_user_meta( $user_id, $meta_key, $meta_value ) : false;
	}


	/**
	 * @param $key
	 * @param $value
	 *
	 * @return bool
	 */
	public static function setSession( $key, $value ) {
		if ( empty( $key ) ) {
			return false;
		}
		self::initWoocommerceSession();
		if ( self::isMethodExists( WC()->session, 'set' ) ) {
			WC()->session->set( $key, $value );
		}

		return true;
	}

	/**
	 * Init the woocommerce session when it was not initlized
	 */
	public static function initWoocommerceSession() {
		if ( ! self::hasSession() && ! defined( 'DOING_CRON' ) ) {
			self::setSessionCookie( true );
		}
	}

	/**
	 * check woocommerce session has started
	 * @return bool
	 */
	public static function hasSession() {
		if ( is_null( WC()->session ) ) {
			return false;
		}
		if ( self::isMethodExists( WC()->session, 'has_session' ) ) {
			return WC()->session->has_session();
		}

		return false;
	}

	/**
	 * set customer session cookie
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public static function setSessionCookie( $value ) {
		if ( self::isMethodExists( WC()->session, 'set_customer_session_cookie' && ! is_null( WC()->session ) ) ) {
			WC()->session->set_customer_session_cookie( $value );
		}

		return true;
	}


	/**
	 * set customer email
	 *
	 * @param $value
	 *
	 * @return false|null
	 */
	public static function setCustomerEmail( $value ) {
		if ( self::isMethodExists( WC()->customer, 'set_billing_email' ) ) {
			return WC()->customer->set_billing_email( $value );
		}

		return false;
	}


	/**
	 * Check cart is empty or not
	 * @return bool|string
	 */
	public static function isCartEmpty() {
		if ( self::isMethodExists( WC()->cart, 'is_empty' ) ) {
			try {
				return WC()->cart->is_empty();
			} catch ( \Exception $e ) {
				return true;
			}
		}

		return true;
	}


	/**
	 * check coupon is valid.
	 *
	 * @param $coupon_code
	 *
	 * @return bool
	 */
	public static function isValidCoupon( $coupon_code ) {
		if ( class_exists( 'WC_Coupon' ) ) {
			$coupon = new \WC_Coupon( $coupon_code );
			if ( self::isMethodExists( $coupon, "is_valid" ) ) {
				return $coupon->is_valid();
			} else if ( self::isMethodExists( \WC_Discounts, 'is_coupon_valid' ) ) {
				$discount = new \WC_Discounts();

				return $discount->is_coupon_valid( $coupon );
			}
		}

		return false;
	}


	/**
	 * Check the coupon code is available on cart.
	 *
	 * @param $discount_code
	 *
	 * @return bool
	 */
	public static function hasDiscount( $discount_code ) {
		if ( empty( $discount_code ) ) {
			return false;
		}
		if ( self::isMethodExists( WC()->cart, 'has_discount' ) ) {
			return WC()->cart->has_discount( $discount_code );
		}

		return false;
	}


	/**
	 * Add discount to cart.
	 *
	 * @param $discount_code
	 *
	 * @return bool
	 */
	public static function addDiscount( $discount_code ) {
		if ( empty( $discount_code ) ) {
			return false;
		}
		if ( class_exists( 'WC' ) && self::isMethodExists( WC()->cart, 'add_discount' ) ) {
			return WC()->cart->add_discount( $discount_code );
		}

		return false;
	}

	/**
	 * Get order order note
	 *
	 * @param $order
	 *
	 * @return array
	 */
	public static function getOrderItems( $order ) {
		if ( self::isMethodExists( $order, 'get_items' ) ) {
			return $order->get_items();
		}

		return [];
	}


	/**
	 * Get Order Id
	 *
	 * @param $order
	 *
	 * @return String|null
	 */
	public static function getOrderSubTotal( $order ) {
		if ( self::isMethodExists( $order, 'get_subtotal' ) ) {
			return $order->get_subtotal();
		}

		return 0;
	}

	/**
	 * Get Order Total
	 *
	 * @param $order
	 *
	 * @return null
	 */
	public static function getOrderTotal( $order ) {
		if ( self::isMethodExists( $order, 'get_total' ) ) {
			return $order->get_total();
		}

		return null;
	}

	/**
	 * get the checkout url
	 * @return string|null
	 */
	public static function getCheckoutUrl() {
		$checkout_url = "";
		if ( function_exists( 'wc_get_checkout_url' ) ) {
			$checkout_url = wc_get_checkout_url();
		}

		return apply_filters( 'rnoc_get_checkout_url', $checkout_url );
	}


	/**
	 * get the post meta
	 *
	 * @param $post_id
	 * @param $meta_key
	 *
	 * @return bool
	 */
	public static function getPostMeta( $post_id, $meta_key ) {
		return ( function_exists( 'get_post_meta' ) ) ? get_post_meta( intval( $post_id ), $meta_key, true ) : '';
	}


	/**
	 * Get order object
	 *
	 * @param $order_id
	 *
	 * @return bool|\WC_Order|null
	 */
	public static function getOrder( $order_id ) {
		return function_exists( 'wc_get_order' ) ? wc_get_order( intval( $order_id ) ) : null;
	}

	/**
	 * Order payment completed - This is a paying customer.
	 *
	 * @param $order_id
	 */
	public static function setCustomerPayingForOrder( $order_id ) {
		if ( function_exists( 'wc_paying_customer' ) ) {
			wc_paying_customer( intval( $order_id ) );
		}
	}


	/**
	 * Get Order Id
	 *
	 * @param $order
	 *
	 * @return String|null
	 */
	public static function getOrderId( $order ) {
		if ( self::isMethodExists( $order, 'get_id' ) ) {
			return $order->get_id();
		} elseif ( is_object( $order ) && isset( $order->id ) ) {
			return $order->id;
		}

		return null;
	}

	/**
	 * Get User Last name
	 *
	 * @param \WC_Order $order
	 *
	 * @return null
	 */
	public static function getOrderCurrency( $order ) {
		return self::isMethodExists( $order, 'get_currency' ) ? $order->get_currency() : null;
	}

	/**
	 * Get status of order
	 *
	 * @param \WC_Order $order
	 *
	 * @return null
	 */
	public static function getStatus( $order ) {
		if ( self::isMethodExists( $order, 'get_status' ) ) {
			$order_status = $order->get_status();

			return strtolower( $order_status );
		}

		return null;
	}


	/**
	 * get Ordered Date
	 *
	 * @param \WC_Order $order
	 * @param $format
	 *
	 * @return null
	 */
	public static function getOrderDate( $order, $format = null ) {
		if ( ! self::isMethodExists( $order, 'get_date_created' ) ) {
			return null;
		}
		$date = $order->get_date_created();
		if ( ! is_null( $format ) ) {
			$date = $date->format( $format );
		}

		return $date;

	}


	/**
	 * Get Order total tax
	 *
	 * @param \WC_Order $order
	 *
	 * @return null
	 */
	public static function getOrderTotalTax( $order ) {
		return self::isMethodExists( $order, 'get_total_tax' ) ? $order->get_total_tax() : null;
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
		if ( self::isMethodExists( $order, 'get_date_paid' ) ) {
			$dateObject = $order->get_date_paid();
			if ( $dateObject instanceof \WC_DateTime ) {
				$date = $dateObject->getTimestamp();
			}
			if ( ! is_null( $format ) ) {
				$date = $dateObject->format( $format );
			}

			return $date;
		}

		return null;
	}


	/**
	 * Get site's default language
	 * @return string
	 */
	public static function getSiteDefaultLang() {
		$current_lang = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		if ( $current_lang == 'en' ) {
			$current_lang = 'en_US';
		}

		return $current_lang;
	}

	/**
	 * Get total order discount
	 *
	 * @param $order
	 * @param $excluding
	 *
	 * @return String|null
	 */
	public static function getOrderDiscount( $order, $excluding = true ) {
		return self::isMethodExists( $order, 'get_total_discount' ) ? $order->get_total_discount( $excluding ) : 0;
	}


	public static function getOrderAddressInfo( $order, $method ) {
		if ( empty( $method ) ) {
			return null;
		}

		return self::isMethodExists( $order, $method ) ? $order->$method() : null;
	}

	/**
	 * Get Item subtotal
	 *
	 * @param $item
	 *
	 * @return String|null
	 */
	public static function getItemSubTotal( $item ) {
		return self::isMethodExists( $item, 'get_subtotal' ) ? $item->get_subtotal : 0;
	}


	/**
	 * Get Item subtotal tax
	 *
	 * @param $item
	 *
	 * @return String|null
	 */
	public static function getItemTaxSubTotal( $item ) {
		return self::isMethodExists( $item, 'get_subtotal_tax' ) ? $item->get_subtotal_tax() : 0;
	}

	/**
	 * get order fees
	 *
	 * @param $order
	 *
	 * @return int|\WC_Order_Item[]|\WC_Order_item_Fee[]
	 */
	public static function getOrderFees( $order ) {
		return self::isMethodExists( $order, 'get_fees' ) ? $order->get_fees() : 0;
	}


	/**
	 * Get order meta from order object
	 *
	 * @param $order_id
	 * @param $meta_key
	 * @param $meta_value
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
	 * is order paid
	 *
	 * @param $order
	 *
	 * @return bool
	 */
	public static function isOrderPaid( $order ) {
		return ( self::isMethodExists( $order, 'is_paid' ) ) ? $order->is_paid() : false;
	}


	/**
	 * get Order User Id
	 *
	 * @param $order
	 *
	 * @return null
	 */
	public static function getOrderUserId( $order ) {
		if ( self::isMethodExists( $order, 'get_user_id' ) ) {
			return $order->get_user_id();
		} elseif ( is_object( $order ) && isset( $order->user_id ) ) {
			return $order->user_id;
		}

		return null;
	}

	/**
	 * Get order shipping total
	 *
	 * @param $order
	 * @param $context
	 *
	 * @return String|null
	 */
	public static function getOrderShippingTotal( $order, $context = "edit" ) {
		return self::isMethodExists( $order, 'get_shipping_total' ) ? $order->get_shipping_total( $context ) : 0;
	}

}
