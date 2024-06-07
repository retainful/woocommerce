<?php

namespace RNOC\App\Modules\AbandonedCart;

use RNOC\App\Helpers\Cart as CartHelper;
use RNOC\App\Helpers\Customer;
use RNOC\App\Helpers\Input;
use RNOC\App\Helpers\Order;
use RNOC\App\Helpers\Product;
use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\Util;
use RNOC\App\Helpers\WC;
use RNOC\App\Helpers\WP;
use RNOC\App\Modules\Storage\Cookie;

defined( 'ABSPATH' ) || exit;

class Cart extends AbandonedCart {

	protected static $cart_token_key = "rnoc_user_cart_token", $cart_token_key_for_db = "_rnoc_user_cart_token";
	protected static $cart_tracking_started_key = "rnoc_cart_created_at", $cart_tracking_started_key_for_db = "_rnoc_cart_tracking_started_at";
	protected static $pending_recovery_key = "rnoc_is_pending_recovery";
	protected static $abandoned_cart_api_url = "https://api.retainful.com/v1/woocommerce/";

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

	/**
	 * Recover user cart
	 */
	function recoverUserCart() {
		// recovery URL
		$token  = (string) Input::get( 'token', '' );
		$hash   = (string) Input::get( 'hash', '' );
		$wc_api = (string) Input::get( 'wc_api', '' );
		if ( empty( $token ) && empty( $hash ) ) {
			return;
		}
		$checkout_url = Order::getCheckoutUrl();
		try {
			$this->reCreateCart( $token, $hash );
		} catch ( \Exception $e ) {

		}


//		if ( ! empty( $_REQUEST['token'] ) && ! empty( $_REQUEST['hash'] ) ) {
//			$checkout_url = self::$woocommerce->getCheckoutUrl();
//			try {
//				$this->reCreateCart();
//			} catch ( Exception $exception ) {
//			}
//			if ( ! empty( $_GET ) ) {
//				foreach ( $_GET as $key => $value ) {
//					if ( ! in_array( $key, array( "token", "hash", "wc-api" ) ) ) {
//						$checkout_url = add_query_arg( $key, $value, $checkout_url );
//					}
//				}
//			}
//			$checkout_url = apply_filters( 'retainful_recovery_redirect_url', $checkout_url );
//			wp_safe_redirect( $checkout_url );
//		}
	}

	/**
	 * recreate the cart.
	 *
	 * @param $token
	 * @param $hash
	 *
	 * @return false|void
	 */
	public function reCreateCart( $token, $hash ) {
		if ( empty( $token ) && empty( $hash ) ) {
			return;
		}
		$data = wc_clean( rawurldecode( $token ) );
		$hash = wc_clean( $hash );
		if ( Util::isHashMatches( $hash, $data ) ) {
			$app_id     = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '' );
			$data       = json_decode( base64_decode( $data ) );
			$cart_token = is_object( $data ) && isset( $data->cart_token ) ? $data->cart_token : '';
			if ( empty( $cart_token ) ) {
				throw new Exception( __( 'Cart token missed', 'retainful-next-order-coupon-for-woocommerce' ) );
			}
			$cart_data = self::retrieveCartDetails( $app_id, $cart_token );
			if ( empty( ( $cart_data ) ) ) {
				return false;
			}
			do_action( 'rnoc_retainful_cart_recreate', $cart_data );
			$order_id = self::getOrderIdFromCartToken( $cart_token );
			$note     = __( 'Customer visited Retainful order recovery URL.', 'retainful-next-order-coupon-for-woocommerce' );
			if ( ! empty ( $order_id ) ) {
				$order = Order::getOrder( $order_id );
				if ( Order::hasOrderStatus( $order, 'checkout-draft' ) ) {
					WC::setSession( 'store_api_draft_order', $order_id );
				} else {
					if ( Order::hasOrderStatus( $order, 'cancelled' ) ) {
						Order::setOrderStatus( $order, 'pending', $note );
					} else {
						Order::setOrderNote( $order, $note );
					}

					$session_coupon = Settings::getStorage()->getValue( 'rnoc_ac_coupon' );
					if ( ! empty( $session_coupon ) && Order::isOrderNeedPayment( $order ) ) {
						Order::applyCouponToOrder( $session_coupon, $order );
						Settings::getStorage()->removeValue( 'rnoc_ac_coupon' );
					}
					$redirect = Order::isOrderNeedPayment( $order ) ? Order::getOrderPaymentURL( $order ) : Order::getOrderReceivedURL( $order );
					Settings::getStorage()->setValue( 'rnoc_is_pending_recovery', true );
					// set (or refresh, if already set) session
					WC::setSessionCookie( true );
					wp_safe_redirect( $redirect );
					exit;
				}
			}
			$is_buyer_accept_marketing = ( isset( $data->buyer_accepts_marketing ) && $data->buyer_accepts_marketing ) ? 1 : 0;
			WC::setSession( 'is_buyer_accepting_marketing', $is_buyer_accept_marketing );
			$user_currency = isset( $data->presentment_currency ) ? $data->presentment_currency : WC::getDefaultCurrency();
			apply_filters( 'rnoc_set_current_currency_code', $user_currency );
			Settings::getStorage()->setValue( 'rnoc_recovered_at', current_time( 'timestamp', true ) );
			Settings::getStorage()->setValue( 'rnoc_recovered_by_retainful', 1 );
			Settings::getStorage()->setValue( 'rnoc_recovered_cart_token', $cart_token );

			$user_id        = self::getUserIdFromCartToken( $cart_token );
			$cart_recreated = false;
			if ( $user_id && Customer::loginUser( $user_id ) ) {
				WP::updateUserMeta( $user_id, '_rnoc_order_note', $note );
				$current_cart   = CartHelper::getCart();
				$cart_recreated = ! empty( $current_cart );
			}

			$cart_recreated = apply_filters( 'rnoc_cart_re_created', $cart_recreated, $data );
			if ( ! $cart_recreated ) {
				Settings::getStorage()->setValue( '_rnoc_order_note', $note );
				$this->reCreateCartForGuestUsers( $data );
			}

			$this->populateSessionDetails( $data );
			$cart_session = WC::getSession( 'cart' );
			if ( empty( $cart_session ) ) {
				$client_session = isset( $data->client_session ) ? $data->client_session : array();
				if ( ! empty( $client_session ) ) {
					$cart = json_decode( wp_json_encode( $client_session->cart ), true );
					if ( ! empty( $cart ) ) {
						WC::setSession( 'cart', $cart );
					}
				} else {
					$cart_contents = isset( $data->cart_contents ) ? $data->cart_contents : array();
					$this->recreateCartFromCartContents( $cart_contents );
				}
			}

		}

		return false;
	}


	/**
	 * Sync the cart details to server
	 *
	 * @param $app_id
	 * @param string $cart_token
	 *
	 * @return array|bool|mixed|object|string
	 */
	public static function retrieveCartDetails( $app_id, $cart_token ) {
		$response = Request::getRetrieveCart( $app_id, $cart_token );//Request::get( $url, $headers );
		if ( isset( $response->success ) && $response->success ) {
			$referrer_automation_id = Input::get( 'referrer_automation_id', 0 );
			if ( ! empty( $referrer_automation_id ) ) {
				WC::setSession( $cart_token . '_referrer_automation_id', $referrer_automation_id );
				$response->data->referrer_automation_id = $referrer_automation_id;
			}

			return isset( $response->data ) ? $response->data : null;
		}

		return null;
	}

	/**
	 * Recreate user guest cart
	 *
	 * @param $data
	 */
	function reCreateCartForGuestUsers( $data ) {
		$this->setCartToken( $data->cart_token );
		WC::setSession( self::$pending_recovery_key, true );
		$created_at = isset( $data->created_at ) ? strtotime( $data->created_at ) : current_time( 'mysql', true );
		CartHelper::setCartCreatedDate( null, $created_at );
		$data           = apply_filters( 'rnoc_abandoned_cart_recover_guest_cart', $data );
		$client_session = isset( $data->client_session ) ? $data->client_session : array();
		if ( ! empty( $client_session ) ) {
			$cart = json_decode( wp_json_encode( $client_session->cart ), true );
			if ( ! empty( $cart ) ) {
				$applied_coupons         = isset( $data->discount_codes ) ? $data->discount_codes : array();
				$chosen_shipping_methods = (array) $client_session->chosen_shipping_methods;
				$shipping_method_counts  = (array) $client_session->shipping_method_counts;
				$chosen_payment_method   = $client_session->chosen_payment_method;
				// base session data
				WC::setSession( 'cart', $cart );
				WC::setSession( 'applied_coupons', self::getValidCoupons( $applied_coupons ) );
				WC::setSession( 'chosen_shipping_methods', $chosen_shipping_methods );
				WC::setSession( 'shipping_method_counts', $shipping_method_counts );
				WC::setSession( 'chosen_payment_method', $chosen_payment_method );
			}
		} else {
			$cart_contents = isset( $data->cart_contents ) ? $data->cart_contents : array();
			self::recreateCartFromCartContents( $cart_contents );
		}
		// set (or refresh, if already set) session
		WC::setSessionCookie( true );
	}

	/**
	 * recreate the cart from cart content
	 *
	 * @param $cart_contents
	 */
	public static function recreateCartFromCartContents( $cart_contents ) {
		if ( ! empty( $cart_contents ) ) {
			CartHelper::emptyUserCart();
			WC::clearWooNotices();
			$remove_list = self::mustCartItemsKeys();
			foreach ( $cart_contents as $key => $cart_item ) {
				$array_cart_item = json_decode( wp_json_encode( $cart_item ), true );
				self::unsetFromArray( $array_cart_item, $remove_list );
				if ( ! is_array( $array_cart_item ) ) {
					$array_cart_item = array();
				}
				$variant_id = isset( $cart_item->variation_id ) ? $cart_item->variation_id : 0;
				$variation  = isset( $cart_item->variation ) ? $cart_item->variation : array();
				if ( is_object( $variation ) ) {
					$variation = json_decode( wp_json_encode( $variation ), true );
				}
				CartHelper::addToCart( $cart_item->product_id, $variant_id, $cart_item->quantity, $variation, $array_cart_item );
			}
		}
	}

	/**
	 * Contains the list of keys that every cart ites have
	 * @return array
	 */
	public static function mustCartItemsKeys() {
		return array(
			'key',
			'line_tax',
			'quantity',
			'variation',
			'line_total',
			'product_id',
			'line_tax_data',
			'line_subtotal_tax',
			'variation_id',
			'data_hash',
			'line_subtotal',
			'data'
		);
	}


	/**
	 * remove key value pairs from list
	 *
	 * @param $full_list
	 * @param array $remove_list
	 */
	public static function unsetFromArray( &$full_list, $remove_list = array() ) {
		if ( ! empty( $remove_list ) ) {
			foreach ( $remove_list as $key ) {
				if ( isset( $full_list[ $key ] ) ) {
					unset( $full_list[ $key ] );
				}
			}
		}
	}

	/**
	 * Returns $coupons, with any invalid coupons removed.
	 *
	 * @param \WC_Coupon $coupons
	 *
	 * @return mixed|null
	 * @throws \Exception
	 */
	protected static function getValidCoupons( $coupons ) {
		$valid_coupons = array();
		if ( $coupons ) {
			foreach ( $coupons as $coupon ) {
				$coupon_code = isset( $coupon->code ) ? $coupon->code : null;
				$coupon_code = apply_filters( 'rnoc_recover_cart_before_validate_coupon', $coupon_code, $coupon );
				if ( ! empty( $coupon_code ) && WC::isValidCoupon( $coupon_code ) ) {
					$valid_coupons[] = $coupon_code;
				}
			}
		}
		$valid_coupons = apply_filters( "rnoc_recover_cart_coupons", $valid_coupons );

		return $valid_coupons;
	}

	/**
	 * Get Order ID from cart token
	 *
	 * @param $cart_token
	 *
	 * @return string|null
	 */
	public static function getOrderIdFromCartToken( $cart_token ) {
		if ( empty( $cart_token ) ) {
			return null;
		}
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_rnoc_user_cart_token' AND meta_value = %s", $cart_token ) );
	}


	/**
	 * Get User ID from cart token
	 *
	 * @param $cart_token
	 *
	 * @return string|null
	 */
	public static function getUserIdFromCartToken( $cart_token ) {
		if ( empty( $cart_token ) ) {
			return null;
		}
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_rnoc_user_cart_token' AND meta_value = %s", $cart_token ) );
	}


	/**
	 * populate cart from session data
	 *
	 * @param $data
	 */
	function populateSessionDetails( $data ) {
		$customer_email = isset( $data->email ) ? $data->email : '';
		//Setting the email
		Customer::setCustomerEmail( $customer_email );
		Util::setIdentity( $customer_email );
		$billing_details = isset( $data->billing_address ) ? $data->billing_address : new \stdClass();
		Customer::setCustomerDetails( 'billing', 'billing', $billing_details );
		$shipping_details = isset( $data->shipping_address ) ? $data->shipping_address : new \stdClass();
		Customer::setCustomerDetails( 'shipping', 'shipping', $shipping_details );
	}


}
