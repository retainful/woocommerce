<?php

namespace Rnoc\App\Modules\Entity;

use Exception;
use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Helpers\Input;
use Rnoc\App\Helpers\WC;
use Rnoc\App\Helpers\Currency;
use Rnoc\App\Helpers\Settings as SettingsHelper;
use Rnoc\App\library\RetainfulApi;
use Rnoc\App\Modules\Integrations\MultiLingual;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use stdClass;

class Cart extends RestApi {
	/**
	 * Sync cart with the retainful
	 *
	 * @param bool $force_sync
	 */
	public static function syncCartData( $force_sync = false ) {
		if ( ! self::isValidCartToTrack() ) {
			return;
		}
		if ( $force_sync || self::needToTrackCart() ) {
			$cart = self::getUserCart();

			if ( ! empty( $cart ) ) {
				Settings::logMessage( $cart, 'cart' );
				$client_ip = self::formatUserIP( self::getClientIp() );
				$cart_hash = self::encryptData( $cart );
				if ( ! empty( $cart_hash ) ) {
					$token         = self::getCartToken();
					$extra_headers = array(
						"X-Client-Referrer-IP" => ( ! empty( $client_ip ) ) ? $client_ip : null,
						"X-Retainful-Version"  => RNOC_VERSION,
						"X-Cart-Token"         => $token,
						"Cart-Token"           => $token,
					);
					self::syncCart( $cart_hash, $extra_headers );
				}
			}
		}
	}


	/**
	 * get the cart tax details
	 * @return array
	 */
	public static function getCartTaxDetails() {
		$tax_details = WC::getCartTaxes();
		$taxes       = array();
		if ( ! empty( $tax_details ) ) {
			foreach ( $tax_details as $key => $tax_detail ) {
				$taxes[] = array(
					'rate'  => 0,
					'price' => self::formatDecimalPrice( ( isset( $tax_detail->amount ) ) ? $tax_detail->amount : 0 ),
					'title' => ( isset( $tax_detail->label ) ) ? $tax_detail->label : 'Tax'
				);
			}
		}

		return $taxes;
	}


	/**
	 * get user IP details
	 * @return array|mixed|string|null
	 */
	public static function getUserIPDetails() {
		return empty( $user_ip ) ? self::formatUserIP( self::getClientIp() ) : self::retrieveUserIp();
	}

	/**
	 * Preprocess cart required for API call
	 * @return array
	 */
	public static function getUserCart() {

		$current_language             = MultiLingual::getCurrentLanguage();
		$customer_details             = self::getCustomerDetails();
		$cart_token                   = self::getCartToken();
		$current_currency_code        = Currency::getCurrentCurrencyCode();
		$default_currency_code        = Settings::getBaseCurrency();
		$cart_created_at              = self::userCartCreatedAt();
		$cart_total                   = self::formatDecimalPrice( WC::getCartTotalPrice() );
		$cart_hash                    = self::generateCartHash();
		$consider_on_hold_order_as_ac = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 0 );
		$cart                         = array(
			'cart_type'                 => 'cart',
			'treat_on_hold_as_complete' => ( $consider_on_hold_order_as_ac == 0 ),
			'cart_hash'                 => $cart_hash,
			'ip'                        => self::getUserIPDetails(),
			'id'                        => $cart_token,
			'email'                     => ( isset( $customer_details['email'] ) ) ? $customer_details['email'] : null,
			'token'                     => $cart_token,
			'currency'                  => $default_currency_code,
			'customer'                  => $customer_details,
			'tax_lines'                 => self::getCartTaxDetails(),
			'total_tax'                 => WC::getCartTotalTax(),
			'cart_token'                => $cart_token,
			'created_at'                => self::formatToIso8601( $cart_created_at ),
			'line_items'                => self::getLineItemsDetails(),
			'updated_at'                => self::formatToIso8601( '' ),
			'total_price'               => $cart_total,
			'completed_at'              => null,
			'discount_codes'            => WC::getAppliedDiscounts(),
			'shipping_lines'            => array(),
			'subtotal_price'            => self::formatDecimalPrice( WC::getCartSubTotal() ),
			'total_price_set'           => Currency::getCurrencyDetails( $cart_total, $current_currency_code, $default_currency_code ),
			'taxes_included'            => ( ! WC::isPriceExcludingTax() ),
			'customer_locale'           => $current_language,
			'order_status'              => null,
			'total_discounts'           => self::formatDecimalPrice( WC::getCartTotalDiscount() ),
			'shipping_address'          => self::getAddressDetails( 'shipping' ),
			'billing_address'           => self::getAddressDetails( 'billing' ),
			'presentment_currency'      => $current_currency_code,
			'abandoned_checkout_url'    => self::getRecoveryLink( $cart_token ),
			'total_line_items_price'    => self::formatDecimalPrice( WC::getCartTotal() ),
			'buyer_accepts_marketing'   => self::isBuyerAcceptsMarketing(),
			'client_session'            => WC::getClientSession(),
			'woocommerce_totals'        => self::getCartTotals(),
			'recovered_at'              => ( ! empty( $recovered_at ) ) ? self::formatToIso8601( $recovered_at ) : null,
			'recovered_by_retainful'    => SettingsHelper::initStorage()->getValue( 'rnoc_recovered_by_retainful' ) ? true : false,
			'recovered_cart_token'      => SettingsHelper::initStorage()->getValue( 'rnoc_recovered_cart_token' ),
			'client_details'            => self::getClientDetails()
		);

		if ( ! empty( $cart_token ) ) {
			$referrer_automation_id = WC::getSession( $cart_token . '_referrer_automation_id' );
			if ( ! empty( $referrer_automation_id ) ) {
				$cart['referrer_automation_id'] = $referrer_automation_id;
			}
		}

		return apply_filters( 'rnoc_get_user_cart', $cart );
	}

	/**
	 * get cart totals
	 * @return array
	 */
	public static function getCartTotals() {
		return array(
			'total_price'     => self::formatDecimalPrice( WC::getCartTotalPrice() ),
			'subtotal_price'  => self::formatDecimalPrice( WC::getCartSubTotal() ),
			'total_tax'       => self::formatDecimalPrice( WC::getCartTaxTotal() + WC::getCartShippingTaxTotal() ),
			'total_discounts' => self::formatDecimalPrice( WC::getCartDiscountTotal() ),
			'total_shipping'  => self::formatDecimalPrice( WC::getCartShippingTotal() ),
			'fee_items'       => self::getCartFeeDetails(),
		);
	}

	/**
	 * get cart fee details
	 * @return array
	 */
	public static function getCartFeeDetails() {
		$fee_items = array();
		if ( $fees = WC::getCartFees() ) {
			foreach ( $fees as $fee ) {
				$fee_items[] = array(
					'title'  => html_entity_decode( $fee->name ),
					'key'    => $fee->id,
					'amount' => self::formatDecimalPrice( $fee->amount )
				);
			}
		}

		return $fee_items;
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
	 * need to track user cart
	 * @return bool
	 */
	public static function isValidCartToTrack() {
		$crawler_detect = new CrawlerDetect();
		if ( $crawler_detect->isCrawler() ) {
			return false;
		}
		if ( ! self::canTrackAbandonedCarts() ) {
			return false;
		}

		return true;
	}


	/**
	 * Check weather Retainful needs to track the cart or not
	 * @return bool
	 */
	public static function needToTrackCart() {
		$cart_hash       = self::generateCartHash();
		$cart_created_at = self::userCartCreatedAt();
		if ( empty( $cart_hash ) && empty( $cart_created_at ) ) {
			return false;
		} elseif ( empty( $cart_hash ) && ! empty( $cart_created_at ) ) {
			return self::comparePreviousCartHash( $cart_hash );
		} elseif ( ! empty( $cart_hash ) && empty( $cart_created_at ) ) {
			//TODO What if it fails to create cart created time
			$time = current_time( 'timestamp', true );
			SettingsHelper::initStorage()->setValue( self::$cart_tracking_started_key, $time );
			if ( $user_id = get_current_user_id() ) {
				self::setCartCreatedDate( $user_id, $time );
			}

			return self::comparePreviousCartHash( $cart_hash );
		} else {
			return self::comparePreviousCartHash( $cart_hash );
		}
	}

	/**
	 * compare old and current cart hash to sync the cart;
	 * This will help from tracking same cart multiple times
	 * This will also reduce the number of API requests
	 *
	 * @param $current_cart_hash
	 *
	 * @return bool
	 */
	public static function comparePreviousCartHash( $current_cart_hash ) {
		$old_cart_hash  = SettingsHelper::initStorage()->getValue( self::$previous_cart_hash_key );
		$is_not_similar = ( $old_cart_hash != $current_cart_hash );
		if ( $is_not_similar ) {
			SettingsHelper::initStorage()->setValue( self::$previous_cart_hash_key, $current_cart_hash );
		}
		SettingsHelper::initStorage()->setValue( 'rnoc_current_cart_hash', $current_cart_hash );

		return $is_not_similar;
	}

	/**
	 * Adding script ID attribute
	 *
	 * @param $src
	 * @param $handle
	 *
	 * @return string
	 */
	public static function addCloudFlareAttrScript( $tag, $handle, $src ) {
		if ( $handle === RNOC_PLUGIN_PREFIX . 'track-user-cart' ) {
			$escapedHandle = esc_attr( $handle );
			$scriptTag     = "<script src='{$src}' id='{$escapedHandle}-js' data-cfasync='false' defer></script>";

			return apply_filters( 'rnoc_add_attr_script', $scriptTag, $handle, $src );
		}

		return $tag;
	}

	/**
	 * Adding the script to track user cart
	 */
	public static function addCartTrackingScripts() {

		if ( ! wp_script_is( 'wc-cart-fragments', 'enqueued' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}

		if ( ! wp_script_is( RNOC_PLUGIN_PREFIX . 'track-user-cart', 'enqueued' ) ) {
			$asset_path = RNOC_PLUGIN_URL . 'assets/js/abandoned_cart.js';
			wp_enqueue_script( RNOC_PLUGIN_PREFIX . 'track-user-cart', $asset_path );
			$user_ip              = self::getClientIp();
			$user_ip              = self::formatUserIP( $user_ip );
			$cart_tracking_engine = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'cart_tracking_engine', 'js' );
			$data                 = array(
				'ajax_url'                  => admin_url( 'admin-ajax.php' ),
				'jquery_url'                => includes_url( 'js/jquery/jquery.js' ),
				'ip'                        => $user_ip,
				'version'                   => RNOC_VERSION,
				'public_key'                => Settings::getApiKey(),
				'api_url'                   => RetainfulApi::getAbandonedCartEndPoint(),
				'tracking_element_selector' => self::getTrackingElementId(),
				'cart_tracking_engine'      => $cart_tracking_engine
			);
			$data                 = apply_filters( 'rnoc_add_cart_tracking_scripts', $data );
			wp_localize_script( RNOC_PLUGIN_PREFIX . 'track-user-cart', 'retainful_cart_data', $data );
		}
	}

	/**
	 * Gets the tracking element ID.
	 * @return string
	 */
	public static function getTrackingElementId() {
		return apply_filters( 'retainful_abandoned_cart_tracking_element_id', 'retainful-abandoned-cart-data' );
	}

	/**
	 * get the AC Js tracking engine
	 * @return mixed|void
	 */
	public static function getAbandonedCartJsEngineUrl() {
		$asset_path = RNOC_PLUGIN_URL . 'assets/js/abandoned_cart.js';

		return apply_filters( 'rnoc_get_abandoned_cart_tracking_js_engine_url', $asset_path . 'js/abandoned_cart.js' );
	}

	/**
	 * User logged in the store
	 *
	 * @param $user_name
	 */
	public static function userLoggedOn( $user_name ) {
		if ( $user_name ) {
			$user = WC::getUserBy( 'login', $user_name );
			if ( ! empty( $user ) ) {
				self::userSignedUp( $user->ID );
			} else {
				$user = WC::getUserBy( 'email', $user_name );
				if ( $user ) {
					self::userSignedUp( $user->ID );
				}
			}
		}
	}


	/**
	 * When user signed up
	 *
	 * @param $user_id
	 */
	public static function userSignedUp( $user_id ) {
		$cart_token = SettingsHelper::initStorage()->getValue( self::$cart_token_key );
		if ( ! empty( $cart_token ) ) {
			WC::updateUserMeta( $user_id, self::$cart_token_key_for_db, $cart_token );
		}
		$cart_created_at = SettingsHelper::initStorage()->getValue( self::$cart_tracking_started_key );
		if ( ! empty( $cart_created_at ) ) {
			WC::updateUserMeta( $user_id, self::$cart_tracking_started_key_for_db, $cart_created_at );
		}
	}

	/**
	 * Remove cart token on success logout
	 */
	public static function userLoggedOut() {
		SettingsHelper::initStorage()->removeValue( self::$cart_token_key );
		SettingsHelper::initStorage()->removeValue( self::$cart_tracking_started_key );
	}


	/**
	 * Track the customer, and set details to session
	 */
	public static function setCustomerData() {

		$billing_email = Input::post( 'billing_email', 'thomascartrabbit@gmail.com' );

		if ( ! empty( $billing_email ) ) {

			$billing_address        = self::getAddressDetails();
			$enable_gdpr_compliance = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance', '' );

			$is_buyer_accepting_marketing = true;
			if ( isset( $enable_gdpr_compliance ) ) {
				$allow_gdpr                   = Input::post( 'allow_gdpr', 'false' );
				$is_buyer_accepting_marketing = ( $allow_gdpr == 'true' );
			}

			WC::setSession( 'is_buyer_accepting_marketing', $is_buyer_accepting_marketing );
			self::setCustomerAddressDetails( $billing_address );
			//shipping address fields
			$shipping_address = self::getAddressDetails( 'shipping' );

			//Shipping to same billing address
			$ship_to_billing = Input::post( 'ship_to_billing', 0 );
			$address_fields  = self::getAddressMapFields();
			if ( intval( $ship_to_billing ) < 1 ) {
				foreach ( $address_fields as $field ) {
					$shipping_field_name                      = 'shipping_' . $field;
					$billing_field_name                       = 'billing_' . $field;
					$shipping_address[ $shipping_field_name ] = ! empty( $billing_address[ $billing_field_name ] ) ? $billing_address[ $billing_field_name ] : '';
				}
			}
			self::setCustomerAddressDetails( $shipping_address, 'shipping' );

			//Billing email

			WC::setCustomerEmail( $billing_email );
			SettingsHelper::setIdentity( $billing_email );

			//Set update and created date
			$session_created_at = SettingsHelper::initStorage()->getValue( 'rnoc_session_created_at' );
			$current_time       = current_time( 'timestamp', true );
			if ( empty( $session_created_at ) ) {
				SettingsHelper::initStorage()->setValue( 'rnoc_session_created_at', $current_time );
			}
		}

		if ( empty( self::isValidCartToTrack() ) ) {
			wp_send_json( array( 'success' => false ) );
		}
		$cart_token = Input::post( 'cart_token', '' );
		if ( empty( self::retrieveCartToken() ) && ! empty( $cart_token ) ) {
			self::setCartToken( $cart_token );
		}
		$cart           = self::getUserCart();
		$encrypted_cart = self::encryptData( $cart );
		wp_send_json_success( $encrypted_cart );
	}


	/**
	 * Handle loading/setting Retainful data for the persistent cart.
	 */
	public static function handlePersistentCart() {
		// bail for guest users, when the cart is empty, or when doing a WP cron request
		if ( ! is_user_logged_in() || wc::isCartEmpty() || defined( 'DOING_CRON' ) ) {
			return null;
		}
		$user_id    = WC::getCurrentUserId();
		$cart_token = WC::getUserMeta( $user_id, self::$cart_token_key_for_db, true );
		if ( $cart_token && ! self::retrieveCartToken() ) {
			// for a logged in user with a persistent cart, set the cart token to the session
			self::setCartToken( $cart_token );
		} elseif ( ! $cart_token && self::retrieveCartToken() ) {
			// when a guest user with an existing cart logs in, save the cart token to user meta
			$cart_token = self::retrieveCartToken();
			update_user_meta( $user_id, self::$cart_token_key_for_db, $cart_token );
		}
	}

	/**
	 * Add abandon cart coupon automatically
	 */
	public static function applyAbandonedCartCoupon() {
		if ( is_admin() ) {
			return;
		}
		$retainful_ac_coupon = Input::get( 'retainful_ac_coupon' );
		if ( ! empty( $retainful_ac_coupon ) ) {
			$coupon_code = sanitize_text_field( $retainful_ac_coupon );
			SettingsHelper::initStorage()->setValue( 'rnoc_ac_coupon', $coupon_code );
		}
		$session_coupon = SettingsHelper::initStorage()->getValue( 'rnoc_ac_coupon' );
		if ( ! empty( $session_coupon ) ) {
			if ( WC::isValidCoupon( $session_coupon ) ) {
				$cart = WC::getCart();
				if ( ! empty( $cart ) && ! WC::hasDiscount( $session_coupon ) ) {
					if ( WC::addDiscount( $session_coupon ) ) {
						SettingsHelper::initStorage()->removeValue( 'rnoc_ac_coupon' );
					}
				}
			}
		}

	}


	/**
	 * remove the Abandoned coupon
	 *
	 * @param $remove_coupon
	 *
	 * @return void
	 */
	public static function removeAbandonedCartCoupon( $remove_coupon ) {
		$coupon_code = SettingsHelper::initStorage()->getValue( 'rnoc_ac_coupon' );
		if ( strtoupper( $remove_coupon ) == strtoupper( $coupon_code ) ) {
			SettingsHelper::initStorage()->removeValue( 'rnoc_ac_coupon' );
		}
	}

	/**
	 * Show GDPR message under email address.
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public static function guestGdprMessage( $fields ) {

		$enable_gdpr_compliance = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance', 0 );
		$message                = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'cart_capture_msg', 'Keep me up to date on news and exclusive offers' );
		$field_name             = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'gdpr_display_position', 'after_billing_email' );
		if ( $enable_gdpr_compliance && $field_name == 'after_billing_email' && ! empty( $fields['billing']['billing_email'] ) ) {
			$fields['billing'][ RNOC_PLUGIN_PREFIX . 'allow_gdpr' ] = [
				'label'    => __( $message, RNOC_TEXT_DOMAIN ),
				'type'     => 'checkbox',
				'priority' => $fields['billing']['billing_email']['priority'],
				'default'  => (int) self::isBuyerAcceptsMarketing()
			];
		}

		return $fields;
	}

	/**
	 * Show GDPR message to  under the terms and condition.
	 *
	 * @return void
	 */
	public static function guestTermGdprMessage() {
		$enable_gdpr_compliance = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance', 0 );
		$field_name             = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'gdpr_display_position', 'after_billing_email' );
		$message                = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'cart_capture_msg', 'Keep me up to date on news and exclusive offers' );
		if ( $enable_gdpr_compliance && $field_name == 'after_term_and_condition' && $message ) {
			echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" 
            name="' . RNOC_PLUGIN_PREFIX . 'allow_gdpr' . '" id="' . RNOC_PLUGIN_PREFIX . 'allow_gdpr' . '" ' . ( self::isBuyerAcceptsMarketing() ? 'checked="checked"' : '' ) . ' />
					<span class="woocommerce-terms-and-conditions-checkbox-text">' . __( $message, RNOC_TEXT_DOMAIN ) . ' ' . __( '(optional)', RNOC_TEXT_DOMAIN ) . '</span>';
		}
	}


	/**
	 * Need to track zero value carts or not
	 *
	 * @param $return
	 * @param $order
	 *
	 * @return mixed
	 */
	public static function isZeroValueCart( $return, $order = false ) {
		$track_zero_cart_value = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'track_zero_value_carts', 'no' );
		if ( $track_zero_cart_value == "no" ) {
			if ( ! empty( WC::getCart() ) && WC::getCartSubTotal() <= 0 && WC::getCartTotalPrice() <= 0 ) {
				return false;
			}
			if ( $order instanceof \WC_Order && ! empty( WC::getOrderItems( $order ) ) && WC::getOrderSubTotal( $order ) <= 0 && WC::getOrderTotal( $order ) <= 0 ) {
				return false;
			}
		}

		return $return;
	}


	/**
	 * render the tracking div
	 */
	public static function renderAbandonedCartTrackingDiv() {

		$data            = array();
		$cart_created_at = self::userCartCreatedAt();
		if ( self::isValidCartToTrack() && ! empty( $cart_created_at ) ) {
			$data = self::getTrackingCartData();
		}

		echo self::getCartTrackingDiv( $data );

	}


	/**
	 * get the abandoned cart tracking div element
	 *
	 * @param array $cart_data
	 *
	 * @return string
	 */
	public static function getCartTrackingDiv( $cart_data = array() ) {

		$tracking_div = sprintf(
			'<div id="%1$s" style="display: none !important;">%2$s</div>',
			esc_attr( self::getTrackingElementId() ),
			esc_html( wp_json_encode( $cart_data ) ) );

		return apply_filters( 'rnoc_get_cart_tracking_div', $tracking_div, $cart_data );
	}

	/**
	 * get the tracking data
	 * @return array
	 */
	public static function getTrackingCartData() {
		$cart = self::getUserCart();
		Settings::logMessage( $cart, 'cart' );
		$data = array(
			'cart_token' => self::getCartToken(),
			'cart_hash'  => self::generateCartHash(),
			'data'       => self::encryptData( $cart )
		);

		return apply_filters( 'rnoc_get_tracking_data', $data );
	}

	/**
	 * Add to
	 *
	 * @param $fragments
	 *
	 * @return mixed
	 */
	public static function addToCartFragments( $fragments ) {
		$selector        = 'div#' . self::getTrackingElementId();
		$data            = array();
		$cart_created_at = self::userCartCreatedAt();
		if ( empty( $cart_created_at ) ) {
			self::needToTrackCart();
			$cart_created_at = self::userCartCreatedAt();
		}
		if ( self::isValidCartToTrack() ) {
			$force_refresh = SettingsHelper::initStorage()->getValue( 'rnoc_force_refresh_cart' );
			if ( empty( $force_refresh ) && ! empty( WC::getCart() ) ) {
				SettingsHelper::initStorage()->setValue( 'rnoc_force_refresh_cart', 1 );
				$data = array( 'force_refresh_carts' => 1 );
			}
			if ( ! empty( $cart_created_at ) ) {
				$data = self::getTrackingCartData();
			}
		}
		$fragments[ $selector ] = self::getCartTrackingDiv( $data );


		return $fragments;
	}

	/**
	 *
	 * @return void
	 */
	public static function printRefreshFragmentScript() {
		$refreshFragmentsOnPageLoad = SettingsHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load', 0 );

		if ( $refreshFragmentsOnPageLoad ) {
			?>
            <script>
                jQuery(window).load(function (e) {
                    jQuery(document.body).trigger('wc_fragment_refresh');
                });
            </script>
			<?php
		}
	}

//	/**
//	 * Recover user cart
//	 */
//	function recoverUserCart() {
//		// recovery URL
//		if ( ! empty( $_REQUEST['token'] ) && ! empty( $_REQUEST['hash'] ) ) {
//			self::recoverCart();
//		}
//	}


}
