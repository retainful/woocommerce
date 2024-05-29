<?php

namespace Rnoc\App\Modules\Entity;

use DateTime;
use Exception;
use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Controller\Admin\BaseController;
use Rnoc\App\Helpers\Input;
use Rnoc\App\Storage\Cookie;
use Rnoc\App\Storage\PhpSession;
use Rnoc\App\Storage\WCSession;
use Rnoc\App\library\RetainfulApi;
use Rnoc\App\Helpers\WC;
use Rnoc\App\Helpers\Settings as SettingsHelper;

class RestApi {

	protected static $cart_token_key = "rnoc_user_cart_token", $cart_token_key_for_db = "_rnoc_user_cart_token";
	protected static $user_ip_key = "rnoc_user_ip_address", $user_ip_key_for_db = "_rnoc_user_ip_address";
	protected static $cart_tracking_started_key = "rnoc_cart_created_at", $cart_tracking_started_key_for_db = "_rnoc_cart_tracking_started_at";
	protected static $pending_recovery_key = "rnoc_is_pending_recovery", $pending_recovery_key_for_db = "_rnoc_is_pending_recovery";
	protected static $order_placed_date_key_for_db = "_rnoc_order_placed_at", $order_cancelled_date_key_for_db = "_rnoc_order_cancelled_at";
	protected static $order_recovered_key_for_db = "_rnoc_order_recovered";

	protected static $previous_cart_hash_key = "rnoc_previous_cart_hash";
	protected static $cart_hash_key_for_db = "_rnoc_cart_hash";
	protected static $accepts_marketing_key_for_db = "_rnoc_is_buyer_accepts_marketing";
	/** The cipher method name to use to encrypt the cart data */
	const CIPHER_METHOD = 'AES256';
	/** The HMAC hash algorithm to use to sign the encrypted cart data */
	const HMAC_ALGORITHM = 'sha256';


	/**
	 * Get the current user's cart token
	 * @return array|string|null
	 */
	public static function getCartToken() {
		$cart_token = self::retrieveCartToken();
		if ( empty( $cart_token ) ) {
			$cart_token = self::generateCartToken();
			self::setCartToken( $cart_token );
		}

		return apply_filters( 'rnoc_get_cart_token', $cart_token, self::class );
	}

	/**
	 * Set the cart token for the session
	 *
	 * @param $cart_token
	 * @param $user_id
	 */
	public static function setCartToken( $cart_token, $user_id = null ) {
		$cart_token     = apply_filters( 'rnoc_before_set_cart_token', $cart_token, $user_id, self::class );
		$old_cart_token = SettingsHelper::initStorage()->getValue( self::$cart_token_key );
		if ( empty( $old_cart_token ) ) {
			Settings::logMessage( $cart_token, 'setting cart token' );
			$current_time = current_time( 'timestamp', true );
			SettingsHelper::initStorage()->setValue( self::$cart_token_key, $cart_token );
			SettingsHelper::initStorage()->setValue( self::$cart_tracking_started_key, $current_time );
			if ( ! empty( $user_id ) || $user_id = get_current_user_id() ) {
				update_user_meta( $user_id, self::$cart_token_key_for_db, $cart_token );
				self::setCartCreatedDate( $user_id, $current_time );
			}
		}
	}

	/**
	 * @param $price
	 *
	 * @return string
	 */
	public static function formatDecimalPrice( $price ) {
		$decimals = WC::priceDecimals();
		$price    = floatval( $price );

		return round( $price, $decimals );
	}

	/**
	 * @param $price
	 *
	 * @return string
	 */
	public static function formatDecimalPriceRemoveTrailingZeros( $price ) {
		$price         = (float) $price;
		$decimals      = WC::priceDecimals();
		$rounded_price = round( $price, $decimals );

		return number_format( $rounded_price, $decimals, '.', '' );
	}


	/**
	 * Line item total
	 *
	 * @param $item_details
	 *
	 * @return int
	 */
	public static function getLineItemTotal( $item_details ) {
		$line_total     = ! empty( $item_details['line_total'] ) ? $item_details['line_total'] : 0;
		$line_total_tax = 0;
		if ( ! WC::isPriceExcludingTax() ) {
			$line_total_tax = ! empty( $item_details['line_tax'] ) ? $item_details['line_tax'] : 0;
		}
		$total = $line_total + $line_total_tax;

		return apply_filters( 'retainful_get_line_item_total', $total, $line_total, $line_total_tax, $item_details, self::class );
	}

	/**
	 * Set the session shipping details
	 *
	 * @param $shipping_address
	 */
	public static function setSessionShippingDetails( $shipping_address ) {
		if ( ! empty( $shipping_address ) ) {
			foreach ( $shipping_address as $key => $value ) {
				$method = 'set_' . $key;
				if ( is_callable( array( WC()->customer, $method ) ) ) {
					WC()->customer->$method( $value );
				}
			}
		}
	}

	/**
	 * Remove the session shipping details
	 */
	public static function removeSessionShippingDetails() {
		SettingsHelper::initStorage()->removeValue( 'rnoc_shipping_address' );
	}

	/**
	 * generate cart hash
	 * @return string
	 */
	public static function generateCartHash() {
		$cart         = WC::getCart();
		$cart_session = array();
		if ( ! empty( $cart ) ) {
			foreach ( $cart as $key => $values ) {
				$cart_session[ $key ] = $values;
				unset( $cart_session[ $key ]['data'] ); // Unset product object.
			}
		}

		return $cart_session ? md5( wp_json_encode( $cart_session ) . WC::getCartTotalForEdit() ) : '';
	}


	/**
	 * retrieve cart token from session
	 *
	 * @param $user_id
	 *
	 * @return array|mixed|string|null
	 */
	public static function retrieveCartToken( $user_id = null ) {

		$user_id = ( $user_id == null ) ? WC::getCurrentUserId() : 0;
		$token   = ! empty( $user_id ) ? get_user_meta( $user_id, self::$cart_token_key_for_db, true ) : SettingsHelper::initStorage()->getValue( self::$cart_token_key );

		return apply_filters( 'rnoc_retrieve_cart_token', $token, $user_id, self::class );
	}

	/**
	 * Recovery link to recover the user cart
	 *
	 * @param $cart_token
	 *
	 * @return string
	 */
	public static function getRecoveryLink( $cart_token ) {
		$data = array( 'cart_token' => $cart_token );
		// encode
		$data = base64_encode( wp_json_encode( $data ) );
		// add hash for easier verification that the checkout URL hasn't been tampered with
		$hash = self::hashTheData( $data );
		$url  = self::getRetainfulApiUrl();
		// returns URL like:
		// pretty permalinks enabled - https://example.com/wc-api/retainful?token=abc123&hash=xyz
		// pretty permalinks disabled - https://example.com?wc-api=retainful&token=abc123&hash=xyz
		return esc_url_raw( add_query_arg( array( 'token' => rawurlencode( $data ), 'hash' => $hash ), $url ) );
	}

	/**
	 * Return the WC API URL for handling Retainful recovery links by accounting
	 * for whether pretty permalinks are enabled or not.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	private static function getRetainfulApiUrl() {
		$scheme = wc_site_is_https() ? 'https' : 'http';

		return get_option( 'permalink_structure' )
			? get_home_url( null, 'wc-api/retainful', $scheme )
			: add_query_arg( 'wc-api', 'retainful', get_home_url( null, null, $scheme ) );
	}

	/**
	 * Hash the data
	 *
	 * @param $data
	 *
	 * @return false|string
	 */
	public static function hashTheData( $data ) {
		$secret = Settings::getSecretKey();

		return hash_hmac( self::HMAC_ALGORITHM, $data, $secret );
	}

	/**
	 * Get the client IP address
	 * @return mixed|string
	 */
	public static function getClientIp() {
		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$client_ip = $_SERVER['HTTP_X_REAL_IP'];
		} elseif ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$client_ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
			$client_ip = $_SERVER['HTTP_X_FORWARDED'];
		} elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
			$client_ip = $_SERVER['HTTP_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
			$client_ip = $_SERVER['HTTP_FORWARDED'];
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$client_ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$client_ip = '';
		}

		return $client_ip;
	}

	/**
	 * retrieve User IP address
	 *
	 * @param null $user_id
	 *
	 * @return array|mixed|string|null
	 */
	public static function retrieveUserIp( $user_id = null ) {
		$ip = ! empty( $user_id ) ? get_user_meta( $user_id, self::$user_ip_key_for_db ) : self::getClientIp();

		return self::formatUserIP( $ip );
	}

	/**
	 * Sometimes the IP address returne is not formatted quite well.
	 * So it requires a basic formating.
	 *
	 * @param $ip
	 *
	 * @return String
	 */
	public static function formatUserIP( $ip ) {
		//check for commas in the IP
		$ip = trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $ip ) ) ) ) );

		return (string) $ip;
	}

	/**
	 * generate the random cart token
	 * @return string
	 */
	public static function generateCartToken() {
		try {
			$data    = random_bytes( 16 );
			$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
			$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10
			$token   = vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
		} catch ( Exception $e ) {
			// fall back to mt_rand if random_bytes is unavailable
			$token = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				// 32 bits for "time_low"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
				// 16 bits for "time_mid"
				mt_rand( 0, 0xffff ),
				// 16 bits for "time_hi_and_version",
				// four most significant bits holds version number 4
				mt_rand( 0, 0x0fff ) | 0x4000,
				// 16 bits, 8 bits for "clk_seq_hi_res",
				// 8 bits for "clk_seq_low",
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand( 0, 0x3fff ) | 0x8000,
				// 48 bits for "node"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
			);
		}

		return md5( $token . time() );
	}

	/**
	 * Checks whether an order is recovered.
	 *
	 * @param int|string $order_id order ID
	 *
	 * @return bool
	 */
	public function isOrderRecovered( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		return (bool) WC::getOrderMeta( $order, $this->order_recovered_key_for_db );
	}


	/**
	 * Format the date to ISO8601
	 *
	 * @param $timestamp
	 *
	 * @return string|null
	 */
	public static function formatToIso8601( $timestamp ) {
		if ( empty( $timestamp ) ) {
			$timestamp = current_time( 'timestamp', true );
		}
		if ( is_object( $timestamp ) && $timestamp instanceof \WC_DateTime ) {
			$timestamp = $timestamp->getTimestamp();
		}

		try {
			$date      = date( 'Y-m-d H:i:s', $timestamp );
			$date_time = new DateTime( $date );

			return $date_time->format( DateTime::ATOM );
		} catch ( Exception $e ) {
			return null;
		}
	}


	/**
	 * Encrypt the cart
	 *
	 * @param $data
	 * @param $secret
	 *
	 * @return string
	 */
	public static function encryptData( $data, $secret = null ) {
		if ( extension_loaded( 'openssl' ) ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				$data = wp_json_encode( $data );
			}
			try {
				if ( empty( $secret ) ) {
					$secret = Settings::getSecretKey();
				}
				$iv_len          = openssl_cipher_iv_length( self::CIPHER_METHOD );
				$iv              = openssl_random_pseudo_bytes( $iv_len );
				$cipher_text_raw = openssl_encrypt( $data, self::CIPHER_METHOD, $secret, OPENSSL_RAW_DATA, $iv );
				$hmac            = hash_hmac( self::HMAC_ALGORITHM, $cipher_text_raw, $secret, true );

				return base64_encode( bin2hex( $iv ) . ':retainful:' . bin2hex( $hmac ) . ':retainful:' . bin2hex( $cipher_text_raw ) );
			} catch ( Exception $e ) {
				return null;
			}
		}

		return null;
	}


	/**
	 * Get the date of cart tracing started
	 *
	 * @param $user_id
	 *
	 * @return array|mixed|string|null
	 */
	public static function userCartCreatedAt( $user_id = null ) {
		$user_id = WC::getCurrentUserId();

		return empty( $user_id ) ? SettingsHelper::initStorage()->getValue( self::$cart_tracking_started_key ) : WC::getUserMeta( $user_id, self::$cart_tracking_started_key_for_db, true );
	}

	/**
	 * When user start adding to cart
	 *
	 * @param null $user_id
	 * @param null $time
	 *
	 * @return array|mixed|string|null
	 */
	public static function setCartCreatedDate( $user_id = null, $time = null ) {
		if ( empty( $time ) ) {
			$time = current_time( 'timestamp', true );
		}
		if ( ! empty( $user_id ) || $user_id = get_current_user_id() ) {
			update_user_meta( $user_id, self::$cart_tracking_started_key_for_db, $time );
		}

		return $time;
	}

	/**
	 * Synchronize cart with SaaS
	 *
	 * @param $cart_details
	 * @param $extra_headers
	 *
	 * @return array|bool|mixed|object|string
	 */
	public static function syncCart( $cart_details, $extra_headers ) {
		$app_id   = Settings::getApiKey();
		$response = false;
		if ( ! empty( $cart_details ) ) {
			Settings::logMessage( 'PHP', 'synced by' );
			$response = RetainfulApi::syncCartDetails( $app_id, $cart_details, $extra_headers );
		}

		return $response;
	}

	/**
	 * Check is buyer accepts marketing
	 * @return bool
	 */
	public static function isBuyerAcceptsMarketing() {
		$enable_gdpr_compliance = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance', 0 );
		if ( $enable_gdpr_compliance ) {
			return in_array( WC::getSession( 'is_buyer_accepting_marketing' ), array( 1, 'true' ) );
		}

		return true;
	}


	/**
	 * get the client details
	 *
	 * @param null $order
	 *
	 * @return mixed|void
	 */
	public static function getClientDetails( $order = null ) {
		$client_details = array(
			'accept_language' => self::getUserAcceptLanguage( $order )
		);

		return apply_filters( 'rnoc_get_client_details', $client_details, $order );
	}


	/**
	 * get the user accept language
	 *
	 * @param null $order
	 *
	 * @return mixed|string|null
	 */
	public static function getUserAcceptLanguage( $order = null ) {
		if ( ! empty( $order ) ) {
			return WC::getOrderMeta( $order, '_rnoc_get_http_accept_language' );
		}
		$lang = ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? trim( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) : '';

		return ! empty( $lang ) ? substr( $lang, 0, 2 ) : '';

	}

	public static function getCustomerDetails( $order = null, $type = 'cart' ) {
		$customer_details   = array();
		$user_id            = WC::getCurrentUserId();
		$billing_email      = WC::getCustomerEmail();
		$billing_phone      = ! empty( $billing_details['billing_phone'] ) ? $billing_details['billing_phone'] : null;
		$billing_state      = ! empty( $billing_details['billing_state'] ) ? $billing_details['billing_state'] : null;
		$billing_last_name  = ! empty( $billing_details['billing_last_name'] ) ? $billing_details['billing_last_name'] : null;
		$billing_first_name = ! empty( $billing_details['billing_first_name'] ) ? $billing_details['billing_first_name'] : null;
		$created_at         = (int) SettingsHelper::initStorage()->getValue( 'rnoc_session_created_at' );  //add the storage settings
		$updated_at         = current_time( 'timestamp', true );
		if ( ! empty( $user_id ) ) {
			$user_data          = WC::getCurrentUser();
			$billing_email      = ! empty( $billing_email ) ? $billing_email : $user_data->user_email;
			$billing_phone      = empty( $user_data->billing_phone ) ? $billing_phone : $user_data->billing_phone;
			$billing_state      = empty( $user_data->billing_state ) ? $billing_state : $user_data->billing_state;
			$billing_last_name  = empty( $user_data->billing_last_name ) ? $billing_last_name : $user_data->billing_last_name;
			$billing_first_name = empty( $user_data->billing_first_name ) ? $billing_first_name : $user_data->billing_first_name;
		}
		if ( ! empty( $order ) && is_object( $order ) ) {
			$customer_orders = WC::getCustomerOrdersByEmail( $billing_email );
			$total_spent     = 0;
			if ( is_array( $customer_orders ) ) {
				foreach ( $customer_orders as $customer_order ) {
					if ( $customer_order instanceof \WC_Order ) {
						$total_spent = $total_spent + WC::getOrderTotal( $customer_order );
					}
				}
			}
			$last_order_id = null;
			if ( ! empty( $customer_orders ) ) {
				$last_order_id = ! empty( $customer_orders[0] ) && is_object( $customer_orders[0] ) && method_exists( $customer_orders[0], 'get_id' ) ? $customer_orders[0]->get_id() : null;
			}
		}
		$customer_details = array(
			'id'                => $user_id,
			'email'             => $billing_email,
			'phone'             => $billing_phone,
			'state'             => $billing_state,
			'last_name'         => $billing_last_name,
			'first_name'        => $billing_first_name,
			'currency'          => Settings::getBaseCurrency(),
			'created_at'        => Input::formatToIso8601( $created_at ),
			'updated_at'        => Input::formatToIso8601( $updated_at ),
			'verified_email'    => true,
			'last_order_name'   => null,
			'accepts_marketing' => true,
			'user_roles'        => WC::getUserRoles( $billing_email )
		);
		if ( $type == 'order' ) {
			$customer_details['total_spent']    = ! empty( $total_spent ) ? $total_spent : 0;
			$customer_details['orders_count']   = ! empty( $customer_orders ) && is_array( $customer_orders ) ? count( $customer_orders ) : 0;
			$customer_details['lasst_order_id'] = ! empty( $last_order_id ) ? $last_order_id : 0;
		}


		return $customer_details;
	}

	/**
	 * Customer address mapping fields
	 * @return array
	 */
	public static function getAddressMapFields() {
		$fields = array(
			'first_name',
			'last_name',
			'state',
			'phone',
			'postcode',
			'city',
			'country',
			'address_1',
			'address_2',
			'company'
		);

		return apply_filters( 'rnoc_get_checkout_mapping_fields', $fields );
	}


	public static function getAddressDetails( $type = 'billing' ) {
		$address_details = self::getCustomerCheckoutDetails( $type );
		$first_name      = empty( $address_details[ $type . '_first_name' ] ) ? $address_details[ $type . '_first_name' ] : null;
		$last_name       = empty( $address_details[ $type . '_last_name' ] ) ? $address_details[ $type . '_last_name' ] : null;
		$user_id         = WC::getCurrentUserId();
		if ( ! empty( $user_id ) ) {
			$user_data  = WC::getCurrentUser();
			$first_name = ! empty( $user_data->first_name ) ? $user_data->first_name : $address_details[ $type . '_first_name' ];
			$last_name  = ! empty( $user_data->last_name ) ? $user_data->last_name : $address_details[ $type . '_last_name' ];
		}
		$fields = array(
			$type . '_address_1' => '',
			$type . '_city'      => '',
			$type . '_state'     => '',
			$type . '_postcode'  => '',
			$type . '_country'   => '',
			$type . '_phone'     => '',
			$type . '_address_2' => '',
			$type . '_company'   => ''
		);

		foreach ( $fields as $key => $value ) {
			if ( isset( $user_id ) && $user_id > 0 ) {
				$address_value = get_user_meta( $user_id, $key, true );
			}
			if ( empty( $address_value ) ) {
				$address_value = isset( $billing_details[ $key ] ) ? $billing_details[ $key ] : $value;
			}
			$fields[ $key ] = $address_value;
		}

		return array(
			'zip'           => $fields[ $type . '_postcode' ],
			'city'          => $fields[ $type . '_city' ],
			'name'          => $first_name . ' ' . $last_name,
			'phone'         => $fields[ $type . '_phone' ],
			'company'       => $fields[ $type . '_company' ],
			'country'       => $fields[ $type . '_country' ],
			'address1'      => $fields[ $type . '_address_1' ],
			'address2'      => $fields[ $type . '_address_2' ],
			'province'      => $fields[ $type . '_state' ],
			'last_name'     => $last_name,
			'first_name'    => $first_name,
			'country_code'  => $fields[ $type . '_country' ],
			'province_code' => $fields[ $type . '_state' ],
		);
	}

	/**
	 * Get the customer billing details
	 *
	 * @param $type
	 *
	 * @return array
	 */
	public static function getCustomerCheckoutDetails( $type = "billing" ) {
		$fields                = self::getAddressMapFields();
		$checkout_field_values = array();
		if ( ! empty( $fields ) ) {
			foreach ( $fields as $key ) {
				$method = 'get_' . $type . '_' . $key;
				if ( is_callable( array( WC()->customer, $method ) ) ) {
					$checkout_field_values[ $type . '_' . $key ] = WC()->customer->$method();
				}
			}
		}

		return $checkout_field_values;
	}

	/**
	 * Set the customer  details
	 *
	 * @param $address
	 */
	public static function setCustomerAddressDetails( $address, $type = 'billing' ) {
		if ( ! empty( $address ) ) {
			foreach ( $address as $key => $value ) {
				$method = 'set_' . $type . '_' . $key;
				if ( is_callable( array( WC()->customer, $method ) ) ) {
					WC()->customer->$method( $value );
				}
			}
		}

	}

	/**
	 * Get the line items details
	 * @return array
	 */
	public static function getLineItemsDetails( $cart = null, $type = 'cart' ) {
		$items = array();
		$cart  = ( $type == 'order' ) && ! empty( $cart ) ? $cart : WC::getCart();


		if ( ! empty( $cart ) ) {
			foreach ( $cart as $item_key => $item_details ) {

				//Deceleration
				$tax_details   = array();
				$item_quantity = ! empty( $item_details['quantity'] ) ? $item_details['quantity'] : null;
				$variant_id    = ! empty( $item_details['variation_id'] ) ? $item_details['variation_id'] : 0;
				$product_id    = ! empty( $item_details['product_id'] ) ? $item_details['product_id'] : 0;
				$cat_ids       = ! empty( $product_id ) && $product_id > 0 ? WC::getProductCategoryIds( $product_id ) : array();
				if ( empty( $item ) ) {
					if ( ! empty( $variant_id ) ) {
						$item = WC::getProduct( $variant_id );
					} elseif ( ! empty( $product_id ) ) {
						$item = WC::getProduct( $product_id );
					}
				}

				$line_tax = ( ! empty( $item_details['line_tax'] ) ) ? $item_details['line_tax'] : 0;
				if ( $line_tax > 0 ) {
					$tax_details[] = array(
						'rate'       => 0,
						'zone'       => 'province',
						'price'      => self::formatDecimalPriceRemoveTrailingZeros( $line_tax ),
						'title'      => 'tax',
						'source'     => 'WooCommerce',
						'position'   => 1,
						'compare_at' => 0,
					);
				}
				$image_url = WC::getProductImageSrc( $item );
				if ( ! empty( $item ) && ! empty( $item_quantity ) ) {
					$item_array = array(
						'key'           => $item_key,
						'sku'           => WC::getItemSku( $item ),
						'price'         => self::formatDecimalPriceRemoveTrailingZeros( WC::getCartItemPrice( $item ) ),
						'title'         => WC::getItemName( $item ),
						'taxable'       => ( $line_tax != 0 ),
						'quantity'      => $item_quantity,
						'tax_lines'     => $tax_details,
						'line_price'    => self::formatDecimalPriceRemoveTrailingZeros( self::getLineItemTotal( $item_details ) ),
						'product_id'    => $product_id,
						'cat_ids'       => implode( ',', $cat_ids ),
						'cat_names'     => WC::getProductCategoryName( $product_id ),
						'variant_id'    => $variant_id,
						'variant_price' => self::formatDecimalPriceRemoveTrailingZeros( ! empty( $variant_id ) ? WC::getCartItemPrice( $item ) : 0 ),
						'variant_title' => ! empty( $variant_id ) ? WC::getItemName( $item ) : '',
						'image_url'     => $image_url,
						'product_url'   => WC::getProductUrl( $item ),
						'properties'    => array()
					);
					$items[]    = apply_filters( 'rnoc_get_cart_line_item_details', $item_array, $cart, $item_key, $item, $item_details );

				}
			}
		}


		return ( $type == 'cart' ) ? apply_filters( "rnoc_get_abandoned_cart_line_items", $items, $cart ) : $items;
	}


	/**
	 * get the user agent of client
	 *
	 * @param null $order
	 *
	 * @return mixed|string|null
	 */
	public static function getUserAgent( $order = null ) {
		if ( ! empty( $order ) ) {
			return Wc::getOrderMeta( $order, '_rnoc_get_http_user_agent' );
		} else {
			$user_agent = Input::get( 'HTTP_USER_AGENT', '', 'server' );
			if ( ! empty( $user_agent ) ) {
				return $user_agent;
			}
		}

		return '';
	}

	/**
	 * need to track carts or not
	 *
	 * @param string $ip_address
	 * @param $order null | \WC_Order | \WC_Cart
	 *
	 * @return bool
	 */
	public static function canTrackAbandonedCarts( $ip_address = null, $order = null ) {
		if ( apply_filters( 'rnoc_is_cart_has_valid_ip', true, $ip_address ) && apply_filters( 'rnoc_can_track_abandoned_carts', true, $order ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check the order has valid order statuses
	 *
	 * @param $order_status
	 *
	 * @return bool
	 */
	public static function isOrderHasValidOrderStatus( $order_status ) {
		$invalid_order_status         = apply_filters( 'rnoc_abandoned_cart_invalid_order_statuses', array(
			'pending',
			'failed',
			'checkout-draft',
			'trash',
			'cancelled',
			'refunded'
		) );
		$consider_on_hold_order_as_ac = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 1 );
		if ( $consider_on_hold_order_as_ac == 1 ) {
			$invalid_order_status[] = 'on-hold';
		}
		$invalid_order_status = array_unique( $invalid_order_status );

		return ( ! in_array( $order_status, $invalid_order_status ) );
	}

}