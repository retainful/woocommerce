<?php

namespace RNOC\App\Modules\AbandonedCart;

use MailPoetVendor\Doctrine\DBAL\Driver\Exception;
use RNOC\App\Helpers\Customer;
use RNOC\App\Helpers\Product;
use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\WC;
use RNOC\App\Helpers\Webhook;
use RNOC\App\Modules\AbandonedCart\Traits\SyncData;

defined( 'ABSPATH' ) || exit;

class Order {
	use SyncData;

	/**
	 * Backend order change time synchronization.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	public function orderUpdatedShopBackend( $order_id ) {
		if ( ! is_admin() || $order_id <= 0 ) {
			return;
		}
		if ( Settings::get( RNOC_PLUGIN_PREFIX . 'enable_background_order_sync', 'no' ) == 'yes' ) {
			return;
		}
		$this->syncOrder( $order_id );
	}

	/**
	 * synchronize order.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	function syncOrder( $order_id ) {
		if ( $order_id <= 0 || Settings::get( RNOC_PLUGIN_PREFIX . 'enable_background_order_sync', 'no' ) == 'yes' ) {
			return;
		}

		$order      = \RNOC\App\Helpers\Order::getOrder( $order_id );
		$cart_token = apply_filters( 'rnoc_sync_order_change_order_token', \RNOC\App\Helpers\Order::getOrderMeta( self::$cart_token_key_for_db, $order ), $order_id, $this );

		if ( empty( $cart_token ) ) {
			return;
		}
		$order_status       = \RNOC\App\Helpers\Order::getStatus( $order );
		$order_cancelled_at = \RNOC\App\Helpers\Order::getOrderMeta( self::$order_cancelled_date_key_for_db, $order );
		if ( ! $order_cancelled_at && $order_status == 'cancelled' ) {
			$order_cancelled_at = current_time( 'timestamp', true );
			\RNOC\App\Helpers\Order::setOrderMeta( $order_id, self::$order_cancelled_date_key_for_db, $order_cancelled_at );
			self::removeTmpStorageData();
		}

		$order_data = $this->getOrderData( $order );
		if ( empty( $order_data ) ) {
			return;
		}
		$cart_hash = self::getEncryptData( $order_data );

		$client_ip = \RNOC\App\Helpers\Order::getOrderMeta( self::$user_ip_key_for_db, $order );

		if ( ! empty( $cart_hash ) ) {
			$token         = \RNOC\App\Helpers\Order::getOrderMeta( self::$cart_token_key_for_db, $order );
			$extra_headers = [
				"X-Client-Referrer-IP" => ( ! empty( $client_ip ) ) ? $client_ip : null,
				"X-Retainful-Version"  => RNOC_VERSION,
				"X-Cart-Token"         => $token,
				"Cart-Token"           => $token
			];
			Request::syncCart( [ 'data' => $cart_hash ], $extra_headers );
		}
	}

	/**
	 * Get order data.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public function getOrderData( $order ) {
		// Can track order.
		$user_ip = \RNOC\App\Helpers\Order::getOrderMeta( self::$user_ip_key_for_db, $order );
		if ( ! $order instanceof \WC_Order || ! self::canTrackAbandonedCart( $user_ip, $order ) ) {
			return [];
		}

		// is valid cart hash
		$cart_hash = \RNOC\App\Helpers\Order::getOrderMeta( self::$cart_hash_key_for_db, $order );
		if ( empty( $cart_hash ) ) {
			return [];
		}

		// is valid cart token
		$cart_token = self::getOrderCartToken( $order );
		if ( empty( $cart_token ) ) {
			return [];
		}

		$order_id                   = \RNOC\App\Helpers\Order::getOrderId( $order );
		$customer_details           = Customer::getOrderCustomer( $order );
		$default_currency_code      = WC::getDefaultCurrency();
		$current_currency_code      = \RNOC\App\Helpers\Order::getOrderData( 'currency', $order );
		$cart_created_at            = \RNOC\App\Helpers\Order::getOrderMeta( self::$cart_tracking_started_key_for_db, $order );
		$cart_total                 = WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderTotal( $order ) );
		$order_status               = \RNOC\App\Helpers\Order::getStatus( $order );
		$order_status               = \RNOC\App\Helpers\Order::getRetainFulOrderStatus( $order_status );
		$excluding_tax              = WC::isPriceExcludingTax();
		$is_buyer_accepts_marketing = \RNOC\App\Helpers\Order::getOrderMeta( self::$accepts_marketing_key_for_db, $order );
		$order_data                 = [
			'cart_type'                 => 'order',
			'treat_on_hold_as_complete' => Settings::get( RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 0 ) == 0,
			'r_order_id'                => $order_id,
			'order_number'              => $order_id,
			'order_date'                => WC::formatToIso8601( \RNOC\App\Helpers\Order::getOrderDate( $order ) ),
			'woo_r_order_number'        => \RNOC\App\Helpers\Order::getOrderNumber( $order ),
			'cart_hash'                 => $cart_hash,
			'ip'                        => $user_ip,
			'id'                        => $cart_token,
			'email'                     => ( isset( $customer_details['email'] ) ) ? $customer_details['email'] : null,
			'token'                     => $cart_token,
			'currency'                  => $default_currency_code,
			'customer'                  => $customer_details,
			'tax_lines'                 => [],
			'total_tax'                 => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderData( 'total_tax', $order, 0 ) ),
			'cart_token'                => $cart_token,
			'created_at'                => WC::formatToIso8601( $cart_created_at ),
			'line_items'                => $this->getOrderLineItemsDetails( $order ),
			'updated_at'                => WC::formatToIso8601(),
			'source_name'               => 'web',
			'total_price'               => $cart_total,
			'completed_at'              => $this->getCompletedAt( $order ),
			'total_weight'              => 0,
			'discount_codes'            => WC::getAppliedDiscounts( $order ),
			'order_status'              => apply_filters( 'rnoc_abandoned_cart_order_status', $order_status, $order ),
			'shipping_lines'            => [],
			'subtotal_price'            => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderSubTotal( $order ) ),
			'total_price_set'           => self::getCurrencyDetails( $cart_total, $current_currency_code, $default_currency_code ),
			'taxes_included'            => ( ! WC::isPriceExcludingTax() ),
			'customer_locale'           => \RNOC\App\Helpers\Order::getOrderLanguage( $order ),
			'total_discounts'           => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderDiscount( $order, $excluding_tax ) ),
			'shipping_address'          => Customer::getOrderShippingAddress( $order ),
			'billing_address'           => Customer::getOrderBillingAddress( $order ),
			'presentment_currency'      => $current_currency_code,
			'abandoned_checkout_url'    => self::getRecoveryLink( $cart_token ),
			'total_line_items_price'    => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderItemsTotal( $order ) ),
			'buyer_accepts_marketing'   => ( $is_buyer_accepts_marketing == 1 ),
			'cancelled_at'              => \RNOC\App\Helpers\Order::getOrderMeta( self::$order_cancelled_date_key_for_db, $order ),
			'woocommerce_totals'        => $this->getOrderTotals( $order, $excluding_tax ),

			'recovered_by_retainful' => (bool) \RNOC\App\Helpers\Order::getOrderMeta( '_rnoc_recovered_by', $order ),
			'recovered_cart_token'   => \RNOC\App\Helpers\Order::getOrderMeta( '_rnoc_recovered_cart_token', $order ),
			'recovered_at'           => ( ! empty( $recovered_at ) ) ? WC::formatToIso8601( $recovered_at ) : null,
			'client_details'         => Customer::getClientDetails( $order ),
			'payment_method'         => [
				'value' => \RNOC\App\Helpers\Order::getPaymentMethod( $order ),
				'name'  => \RNOC\App\Helpers\Order::getPaymentMethodTitle( $order ),
			]
		];

		$referrer_automation_id = WC::getSession( $cart_token . '_referrer_automation_id' );
		if ( ! empty( $referrer_automation_id ) ) {
			$order_data['referrer_automation_id'] = $referrer_automation_id;
		}

		return apply_filters( 'rnoc_api_get_order_data', $order_data, $order );
	}

	/**
	 * Get order cart token.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string
	 */
	public static function getOrderCartToken( $order ) {
		return apply_filters( 'rnoc_get_order_cart_token', \RNOC\App\Helpers\Order::getOrderMeta( self::$cart_token_key_for_db, $order ), $order );
	}

	/**
	 * Get order line items.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public function getOrderLineItemsDetails( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return [];
		}
		$order_items = \RNOC\App\Helpers\Order::getOrderItems( $order );

		if ( empty( $order_items ) ) {
			return [];
		}
		$items = [];
		foreach ( $order_items as $item_key => $item ) {
			$variant_id = ! empty( $item['variation_id'] ) ? $item['variation_id'] : 0;
			$product_id = ! empty( $item['product_id'] ) ? $item['product_id'] : 0;

			if ( $variant_id > 0 ) {
				$product = Product::getProduct( $variant_id );
			} else {
				$product = Product::getProduct( $product_id );
			}

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$line_tax  = ! empty( $item['line_tax'] ) ? $item['line_tax'] : 0;
			$tax_lines = [];
			if ( $line_tax > 0 ) {
				$tax_lines[] = [
					'rate'       => 0,
					'zone'       => 'province',
					'price'      => WC::formatDecimalPriceRemoveTrailingZeros( $line_tax ),
					'title'      => 'tax',
					'source'     => 'WooCommerce',
					'position'   => 1,
					'compare_at' => 0,
				];
			}
			$cat_ids = ! empty( $product_id ) && $product_id > 0 ? Product::getProductCategoryIds( $product_id ) : [];
			$items[] = apply_filters( 'rnoc_get_order_line_item_details', [
				'key'           => $item_key,
				'sku'           => Product::getItemSku( $product ),
				'price'         => WC::formatDecimalPriceRemoveTrailingZeros( Product::getItemPrice( $product ) ),
				'title'         => Product::getItemName( $product ),
				'taxable'       => ( $line_tax != 0 ),
				'quantity'      => ! empty( $item['quantity'] ) ? $item['quantity'] : 1,
				'tax_lines'     => $tax_lines,
				'line_price'    => WC::formatDecimalPriceRemoveTrailingZeros( $this->getLineItemTotal( $item ) ),
				'product_id'    => $product_id,
				'cat_ids'       => implode( ',', $cat_ids ),
				'cat_names'     => Product::getProductCategoryName( $product_id ),
				'variant_id'    => $variant_id,
				'variant_price' => $variant_id > 0 ? WC::formatDecimalPriceRemoveTrailingZeros( Product::getItemPrice( $product ) ) : 0,
				'variant_title' => $variant_id > 0 ? Product::getItemName( $product ) : '',
				'image_url'     => Product::getProductImageSrc( $product ),
				'product_url'   => Product::getProductUrl( $item ),
				'properties'    => [],
			], $order_items, $item_key, $product );
		}

		return apply_filters( 'rnoc_get_abandoned_order_line_items', $items, $order_items );
	}

	/**
	 * @param $order
	 *
	 * @return void|null
	 */
	function getCompletedAt( $order = '' ) {
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		$order_placed_at = \RNOC\App\Helpers\Order::getOrderMeta( self::$order_placed_date_key_for_db, $order );
		$order_status    = \RNOC\App\Helpers\Order::getStatus( $order );
		if ( empty( $order_placed_at ) && self::isValidOrderStatus( $order_status ) ) {
			$order_placed_at = \RNOC\App\Helpers\Order::getOrderPaidDate( $order );
			$order_placed_at = WC::formatToIso8601( $order_placed_at );
			$order->update_meta_data( self::$order_placed_date_key_for_db, $order_placed_at );
			$is_order_pending_recovery = (bool) \RNOC\App\Helpers\Order::getOrderMeta( self::$pending_recovery_key_for_db, $order );
			if ( $is_order_pending_recovery ) {
				$is_order_recovered = (bool) \RNOC\App\Helpers\Order::getOrderMeta( self::$order_recovered_key_for_db, $order );
				if ( ! $is_order_recovered && \RNOC\App\Helpers\Order::getOrderMeta( '_rnoc_recovered_by', $order ) == 1 ) {
					$order->delete_meta_data( self::$pending_recovery_key_for_db );
					$order->update_meta_data( self::$order_recovered_key_for_db, true );
					$order->add_order_note( __( 'Order recovered by Retainful.', RNOC_TEXT_DOMAIN ) );
					do_action( 'rnoc_abandoned_order_recovered', $order );
				}
			}
			$order->save();
		}
		$completed_at = ( ! empty( $order_placed_at ) ) ? WC::formatToIso8601( $order_placed_at ) : null;

		return apply_filters( 'rnoc_order_completed_at', $completed_at, $order );
	}

	/**
	 * Get order totals.
	 *
	 * @param \WC_Order $order Order object.
	 * @param bool $excluding_tax Is excluding tax.
	 *
	 * @return array
	 */
	public static function getOrderTotals( $order, $excluding_tax ) {
		return [
			'total_price'     => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderTotal( $order ) ),
			'subtotal_price'  => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderItemsTotal( $order ) ),
			'total_tax'       => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderData( 'total_tax', $order, 0 ) ),
			'total_discounts' => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderDiscount( $order, $excluding_tax ) ),
			'total_shipping'  => WC::formatDecimalPrice( \RNOC\App\Helpers\Order::getOrderShippingTotal( $order ) ),
			'fee_items'       => self::getOrderFeeDetails( $order, $excluding_tax ),
		];
	}

	/**
	 * Get order fee details.
	 *
	 * @param \WC_Order $order Order object.
	 * @param bool $excluding_tax Is excluding tax.
	 *
	 * @return array
	 */
	public static function getOrderFeeDetails( $order, $excluding_tax ) {
		$fees = \RNOC\App\Helpers\Order::getOrderFees( $order );
		if ( empty( $fees ) ) {
			return [];
		}

		$fee_items = [];
		foreach ( $fees as $id => $fee ) {
			$fee_items[] = array(
				'title'  => html_entity_decode( $fee['name'] ? $fee['name'] : __( 'Fee', 'retainful-next-order-coupon-for-woocommerce' ) ),
				'key'    => $id,
				'amount' => WC::formatDecimalPrice( ( $excluding_tax ) ? $fee['line_total'] : $fee['line_total'] + $fee['line_tax'] )
			);
		}

		return $fee_items;
	}

	/**
	 *  Change webhook header data.
	 *
	 * @param array $http_args Http argument data.
	 * @param int $order_id Order id.
	 * @param int $webhook_id Webhook id.
	 *
	 * return mixed
	 *
	 * @throws \Exception
	 */
	public function changeWebHookHeader( $http_args, $order_id, $webhook_id ) {
		$is_app_connected = Settings::get( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0 );
		if ( $webhook_id <= 0 || ! class_exists( 'WC_Webhook' ) || ! $is_app_connected ) {
			return $http_args;
		}
		try {
			$webhook      = new \WC_Webhook( $webhook_id );
			$topic        = $webhook->get_topic();
			$topic_status = Webhook::getWebHookStatus();
			if ( ! isset( $topic_status[ $topic ] ) || ! $topic_status[ $topic ] ) {
				return $http_args;
			}
			$delivery_url      = $webhook->get_delivery_url();
			$site_delivery_url = Webhook::getDeliveryUrl();
			if ( $delivery_url != $site_delivery_url || $order_id <= 0 ) {
				return $http_args;
			}
			$order      = \RNOC\App\Helpers\Order::getOrder( $order_id );
			$cart_token = \RNOC\App\Helpers\Order::getOrderData( self::$cart_token_key_for_db, $order );
			if ( empty( $cart_token ) ) {
				//Usually we should not force generate the cart token as this would sync all the old orders otherwise, if their status changes.
				$force_generate_cart_token = apply_filters( 'rnoc_force_generate_cart_token', false, $http_args, $order_id, $webhook_id );

				if ( $force_generate_cart_token === true ) {
					//Let's generate a token and set to the order meta
					$cart_token = AbandonedCart::generateCartToken();
					\RNOC\App\Helpers\Order::setOrderMeta( $order_id, self::$cart_token_key_for_db, $cart_token );
				}
			}
			if ( empty( $cart_token ) ) {
				//bail on empty cart token
				return $http_args;
			}
			$order_data = $this->getOrderData( $order );
			if ( is_array( $order_data ) && ! empty( $order_data ) ) {
				$client_ip     = \RNOC\App\Helpers\Order::getOrderMeta( self::$user_ip_key_for_db, $order );
				$token         = \RNOC\App\Helpers\Order::getOrderMeta( self::$cart_token_key_for_db, $order );
				$app_id        = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '', 'licence' );
				$extra_headers = [
					"X-Client-Referrer-IP" => ( ! empty( $client_ip ) ) ? $client_ip : null,
					"X-Retainful-Version"  => RNOC_VERSION,
					"X-Cart-Token"         => $token,
					"Cart-Token"           => $token,
					"app-id"               => $app_id,
					"app_id"               => $app_id,
					"Content-Type"         => 'application/json'
				];
				foreach ( $extra_headers as $key => $value ) {
					$http_args['headers'][ $key ] = $value;
				}
				$cart_hash         = self::getEncryptData( $order_data );
				$body              = [
					'data' => $cart_hash
				];
				$http_args['body'] = trim( wp_json_encode( $body ) );
			}
		} catch ( Exception $e ) {

		}

		return $http_args;
	}
}