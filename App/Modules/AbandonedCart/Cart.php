<?php

namespace RNOC\App\Modules\AbandonedCart;

use RNOC\App\Helpers\Cart as CartHelper;
use RNOC\App\Helpers\Customer;
use RNOC\App\Helpers\Product;
use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\WC;
use RNOC\App\Helpers\WP;

defined( 'ABSPATH' ) || exit;

class Cart extends AbandonedCart {

	/**
	 * Set customer data.
	 *
	 * @return void
	 */
	public function setCustomerData() {
		//TODO: Get deta from input helper
		$billing_email = $_POST['billing_email'];
		if ( ! empty( $billing_email ) && is_email( $billing_email ) ) {
			// update customer email
			Customer::setCustomerEmail( $billing_email );
			Settings::setIdentity( '_wc_rnoc_tk_session', $billing_email );
			// update customer billing details
			Customer::setCustomerDetails();
			//TODO: use input heler
			$ship_to_billing = ( isset( $_POST['ship_to_billing'] ) ) ? $_POST['ship_to_billing'] : 0;
			if ( $ship_to_billing < 1 ) {
				Customer::setCustomerDetails( 'billing', 'shipping' );
			} else {
				// update customer shipping details
				Customer::setCustomerDetails( 'shipping', 'shipping' );
			}
			$is_buyer_accept_marketing = true;
			if ( Settings::get( RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance', 0 ) ) {
				$is_buyer_accept_marketing = ( isset( $_POST['allow_gdpr'] ) && $_POST['allow_gdpr'] == 'true' ); //TODO: use input helper
			}
			WC::setSession( 'is_buyer_accepting_marketing', $is_buyer_accept_marketing );
			$storage            = Settings::getStorage();
			$session_created_at = $storage->get( 'rnoc_session_created_at' );
			$current_time       = current_time( 'timestamp', true );
			if ( empty( $session_created_at ) ) {
				$storage->set( 'rnoc_session_created_at', $current_time );
			}
		}

		if ( self::isValidCartToTrack() ) {
			$cart_token      = $this->retrieveCartToken();
			$post_cart_token = $_POST['cart_token'];// TODO: use input helper
			if ( empty( $cart_token ) && ! empty( $post_cart_token ) ) {
				$this->setCartToken( $post_cart_token );
			}
			$user_data      = $this->getCartData();
			$encrypted_cart = self::getEncryptData( $user_data );
			wp_send_json_success( $encrypted_cart );
		}
		wp_send_json_error();
	}

	/**
	 * Handle persistent cart.
	 *
	 * @return void
	 */
	public function handlePersistentCart() {
		// bail for guest users, when the cart is empty, or when doing a WP cron request
		if ( ! is_user_logged_in() || CartHelper::isCartEmpty() || defined( 'DOING_CRON' ) ) {
			return;
		}
		$user_id             = get_current_user_id();
		$cart_token          = get_user_meta( $user_id, self::$cart_token_key_for_db, true );
		$retrieve_cart_token = $this->retrieveCartToken();
		if ( $cart_token && ! $retrieve_cart_token ) {
			// for a logged-in user with a persistent cart, set the cart token to the session
			$this->setCartToken( $cart_token );
		} elseif ( ! $cart_token && $retrieve_cart_token ) {
			// when a guest user with an existing cart logs in, save the cart token to user meta
			update_user_meta( $user_id, self::$cart_token_key_for_db, $retrieve_cart_token );
		}
	}

	/**
	 * Get cart request data.
	 *
	 * @return mixed|null
	 */
	function getCartData() {
		$cart_token            = $this->getCartToken();
		$customer_details      = Customer::getCartCustomer();
		$created_at            = self::getTrackingStartAt();
		$cart_total            = WC::formatDecimalPrice( CartHelper::getCartTotal() );
		$current_currency_code = WC::getCurrentCurrencyCode();
		$default_currency_code = WC::getDefaultCurrency();
		$storage               = Settings::getStorage();
		$recovered_at          = $storage->get( 'rnoc_recovered_at' );
		$cart                  = [
			'cart_type'                 => 'cart',
			'treat_on_hold_as_complete' => Settings::get( RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 0 ) == 0,
			'cart_hash'                 => self::generateCartHash(),
			'ip'                        => Customer::getUserIPDetails(),
			'id'                        => $cart_token,
			'token'                     => $cart_token,
			'cart_token'                => $cart_token,
			'email'                     => ( isset( $customer_details['email'] ) ) ? $customer_details['email'] : null,
			'currency'                  => $default_currency_code,
			'presentment_currency'      => $current_currency_code,
			'customer'                  => $customer_details,
			'tax_lines'                 => CartHelper::getCartTaxDetails(),
			'total_tax'                 => CartHelper::getCartTotalTax(),
			'created_at'                => WC::formatToIso8601( $created_at ),
			'line_items'                => $this->getCartLineItemsDetails(),
			'updated_at'                => WC::formatToIso8601( '' ),
			'total_price'               => $cart_total,
			'completed_at'              => null,
			'discount_codes'            => WC::getAppliedDiscounts(),
			'shipping_lines'            => [],
			'subtotal_price'            => WC::formatDecimalPrice( CartHelper::getCartSubTotal() ),
			'total_price_set'           => self::getCurrencyDetails( $cart_total, $current_currency_code, $default_currency_code ),
			'taxes_included'            => ( ! WC::isPriceExcludingTax() ),
			'customer_locale'           => WP::getCurrentLanguage(),
			'order_status'              => null,
			'total_discounts'           => WC::formatDecimalPrice( CartHelper::getCartTotalDiscount() ),
			'shipping_address'          => Customer::getCartShippingAddress(),
			'billing_address'           => Customer::getCartBillingAddress(),
			'abandoned_checkout_url'    => self::getRecoveryLink( $cart_token ),
			'total_line_items_price'    => WC::formatDecimalPrice( CartHelper::getCartTotal() ),
			'buyer_accepts_marketing'   => self::isBuyerAcceptsMarketing(),
			'client_session'            => WC::getClientSession(),
			'woocommerce_totals'        => self::getCartTotals(),
			'recovered_at'              => ( ! empty( $recovered_at ) ) ? WC::formatToIso8601( $recovered_at ) : null,
			'recovered_by_retainful'    => (bool) $storage->get( 'rnoc_recovered_by_retainful' ),
			'recovered_cart_token'      => $storage->get( 'rnoc_recovered_cart_token' ),
			'client_details'            => Customer::getClientDetails()
		];

		return apply_filters( 'rnoc_get_user_cart', $cart );
	}

	/**
	 * Get cart line items.
	 *
	 * @return array
	 */
	public function getCartLineItemsDetails() {
		$cart = CartHelper::getCart();
		if ( empty( $cart ) ) {
			return [];
		}
		$items = [];
		foreach ( $cart as $item_key => $item ) {
			$variant_id = isset( $item['variation_id'] ) && $item['variation_id'] > 0 ? $item['variation_id'] : 0;
			$product_id = isset( $item['product_id'] ) && $item['product_id'] > 0 ? $item['product_id'] : 0;
			$product    = apply_filters( 'woocommerce_cart_item_product', $item['data'], $item, $item_key );
			if ( empty( $product ) ) {
				if ( $variant_id > 0 ) {
					$product = Product::getProduct( $variant_id );
				} else {
					$product = Product::getProduct( $product_id );
				}
			}
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$line_tax  = ( isset( $item['line_tax'] ) && ! empty( $item['line_tax'] ) ) ? $item['line_tax'] : 0;
			$tax_lines = [];
			if ( $line_tax > 0 ) {
				$tax_lines[] = array(
					'rate'       => 0,
					'zone'       => 'province',
					'price'      => WC::formatDecimalPriceRemoveTrailingZeros( $line_tax ),
					'title'      => 'tax',
					'source'     => 'WooCommerce',
					'position'   => 1,
					'compare_at' => 0,
				);
			}
			$cat_ids = ! empty( $product_id ) && $product_id > 0 ? Product::getProductCategoryIds( $product_id ) : array();
			$items[] = apply_filters( 'rnoc_get_cart_line_item_details', [
				'key'           => $item_key,
				'sku'           => Product::getItemSku( $product ),
				'price'         => WC::formatDecimalPriceRemoveTrailingZeros( CartHelper::getCartItemPrice( $product ) ),
				'title'         => Product::getItemName( $product ),
				'taxable'       => ( $line_tax != 0 ),
				'quantity'      => ! empty( $item['quantity'] ) ? $item['quantity'] : 1,
				'tax_lines'     => $tax_lines,
				'line_price'    => WC::formatDecimalPriceRemoveTrailingZeros( $this->getLineItemTotal( $item ) ),
				'product_id'    => $product_id,
				'cat_ids'       => implode( ',', $cat_ids ),
				'cat_names'     => Product::getProductCategoryName( $product_id ),
				'variant_id'    => $variant_id,
				'variant_price' => $variant_id > 0 ? WC::formatDecimalPriceRemoveTrailingZeros( CartHelper::getCartItemPrice( $product ) ) : 0,
				'variant_title' => $variant_id > 0 ? Product::getItemName( $product ) : '',
				'image_url'     => Product::getProductImageSrc( $product ),
				'product_url'   => Product::getProductUrl( $item ),
				'properties'    => []
			], $cart, $item_key, $product, $item );
		}

		return apply_filters( "rnoc_get_abandoned_cart_line_items", $items, $cart );
	}

	/**
	 * Get line item total.
	 *
	 * @param array $item Cart item.
	 *
	 * @return float
	 */
	function getLineItemTotal( $item ) {
		$line_total     = ( isset( $item['line_total'] ) && ! empty( $item['line_total'] ) ) ? $item['line_total'] : 0;
		$line_total_tax = 0;
		if ( ! WC::isPriceExcludingTax() ) {
			$line_total_tax = ( isset( $item['line_tax'] ) && ! empty( $item['line_tax'] ) ) ? $item['line_tax'] : 0;
		}
		$total = $line_total + $line_total_tax;

		return apply_filters( 'retainful_get_line_item_total', $total, $line_total, $line_total_tax, $item, $this );
	}

	/**
	 * Get currency details.
	 *
	 * @param float $cart_total Cart total.
	 * @param string $current_currency_code Current currency.
	 * @param string $default_currency_code default currency.
	 *
	 * @return array
	 */
	public static function getCurrencyDetails( $cart_total, $current_currency_code, $default_currency_code ) {
		if ( $current_currency_code != $default_currency_code ) {
			$exchange_rate   = apply_filters( 'rnoc_get_currency_rate', $cart_total, $current_currency_code );
			$shop_cart_total = self::convertToCurrency( $cart_total, $exchange_rate );
		} else {
			$shop_cart_total = $cart_total;
		}
		$details = [
			'shop_money'        => [
				'amount'        => $shop_cart_total,
				'currency_code' => $default_currency_code
			],
			'presentment_money' => [
				'amount'        => $cart_total,
				'currency_code' => $current_currency_code
			]
		];

		return apply_filters( 'rnoc_get_cart_currency_details', $details, $current_currency_code, $default_currency_code );
	}

	/**
	 * Convert price.
	 *
	 * @param float $price Price.
	 * @param float $rate Convert rate.
	 *
	 * @return float
	 */
	public static function convertToCurrency( $price, $rate ) {
		if ( ! empty( $price ) && ! empty( $rate ) ) {
			return $price / $rate;
		}

		return $price;
	}

	/**
	 * Get cart totals.
	 *
	 * @return array
	 */
	public static function getCartTotals() {
		return [
			'total_price'     => WC::formatDecimalPrice( CartHelper::getCartTotal() ),
			'subtotal_price'  => WC::formatDecimalPrice( CartHelper::getCartSubTotal() ),
			'total_tax'       => WC::formatDecimalPrice( CartHelper::getCartTaxTotal() + CartHelper::getCartShippingTaxTotal() ),
			'total_discounts' => WC::formatDecimalPrice( CartHelper::getCartTotalDiscount() ),
			'total_shipping'  => WC::formatDecimalPrice( CartHelper::getCartShippingTotal() ),
			'fee_items'       => self::getCartFeeDetails(),
		];
	}

	/**
	 * Get cart fees.
	 *
	 * @return array
	 */
	public static function getCartFeeDetails() {
		$fees = CartHelper::getCartFees();
		if ( empty( $fees ) ) {
			return [];
		}
		$fee_items = [];

		foreach ( $fees as $fee ) {
			$fee_items[] = array(
				'title'  => html_entity_decode( $fee->name ),
				'key'    => $fee->id,
				'amount' => WC::formatDecimalPrice( $fee->amount )
			);
		}

		return $fee_items;
	}
}
