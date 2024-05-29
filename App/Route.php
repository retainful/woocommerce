<?php

namespace Rnoc\App;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Rnoc\App\Modules\Entity\Cart;
use Rnoc\App\Modules\Entity\Order;
use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Helpers\Settings as SettingHelper;

class Route {
	public static function init() {
		//before init hook
		do_action( 'rnoc_before_init' );
		self::addCommonHooks();
		if ( is_admin() ) {
			self::addAdminHooks();
		} else {
			self::addSiteHooks();
		}
		do_action( 'rnoc_after_init' );
	}


	public static function addAdminHooks() {
		add_action( 'admin_menu', [ Settings::class, 'registerMenu' ] );
		add_action( 'admin_enqueue_scripts', [ Settings::class, 'initAdminPageStyles' ] );
		add_action( 'wp_ajax_rnoc_save_settings', [ Settings::class, 'saveAcSettings' ] );
		add_action( 'wp_ajax_rnoc_disconnect_license', [ Settings::class, 'disconnectLicense' ] );
		//Validate key
		add_action( 'wp_ajax_validate_app_key', array( Settings::class, 'validateAppKey' ) );
	}


	/**
	 * @return void
	 */
	public static function addSiteHooks() {
		/*
		* Retainful abandoned cart api
		*/
		add_filter( 'script_loader_tag', [ Cart::class, 'addCloudFlareAttrScript' ], 10, 3 );
		//add_action( 'wp_loaded', [ Cart::class, 'applyAbandonedCartCoupon' ] );
		//add_action( 'woocommerce_removed_coupon', [ Cart::class, 'removeAbandonedCartCoupon' ] );
		add_filter( 'woocommerce_checkout_fields', [ Cart::class, 'addGuestGDPRField' ] );
		add_action( 'woocommerce_checkout_after_terms_and_conditions', [ Cart::class, 'addGuestTermGDPRField' ] );
		$cart_tracking_engine = SettingHelper::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'cart_tracking_engine', 'js' );
		if ( $cart_tracking_engine == "php" ) {
			add_action( 'woocommerce_after_calculate_totals', [ Cart::class, 'syncCartData' ] );
		} elseif ( $cart_tracking_engine == 'js' ) {
			add_action( 'wp_footer', [ Cart::class, 'renderAbandonedCartTrackingDiv' ] );
			add_filter( 'woocommerce_add_to_cart_fragments', [ Cart::class, 'addToCartFragments' ] );
		}
		add_action( 'wp_footer', [ Cart::class, 'printRefreshFragmentScript' ] );
		add_action( 'wp_enqueue_scripts', [ Cart::class, 'addCartTrackingScripts' ] );

		//user action
		add_action( 'wp_authenticate', [ Cart::class, 'userLoggedOn' ] );
		add_action( 'user_register', [ Cart::class, 'userSignedUp' ] );
		add_action( 'wp_logout', [ Cart::class, 'userLoggedOut' ] );

		add_action( 'wp_footer', [ Order::class, 'setRetainfulOrderData' ] );
		add_action( 'woocommerce_thankyou', array( Order::class, 'payPageOrderCompletion' ) );
		add_action( 'woocommerce_payment_complete', [ Order::class, 'paymentCompleted' ] );
		add_action( 'woocommerce_checkout_update_order_meta', [ Order::class, 'checkoutOrderProcessed' ] );
		add_action( 'woocommerce_order_status_changed', [ Order::class, 'orderStatusChanged' ], 15, 3 );


	}


	/**
	 * Register all the required end points
	 */

	public static function addCommonHooks() {
		add_action( 'wp_ajax_rnoc_track_user_data', [ Cart::class, 'setCustomerData' ] );
		add_action( 'woocommerce_cart_loaded_from_session', [ Cart::class, 'loadCartToken' ] );
		//add_action( 'woocommerce_api_retainful', [ cart::class, 'recoverUserCart' ] );
	}


}