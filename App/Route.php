<?php

namespace Rnoc\App;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Rnoc\App\Modules\Entity\Cart;
use Rnoc\App\Modules\Entity\Checkout;
use Rnoc\App\Modules\Entity\RestApi;
use Rnoc\Retainful\Api\NextOrderCoupon\CouponManagement;
use Rnoc\Retainful\Api\Popup\Popup;
use Rnoc\Retainful\Api\Referral\ReferralManagement;
use Rnoc\Retainful\Integrations\AfterPay;
use Rnoc\Retainful\Integrations\Currency;
use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Helpers\Settings as SettingHelpers;
use Rnoc\App\Controller\Admin\BaseController;

class Route {
	private static $base_controller, $settings, $cart;

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
		add_action( 'woocommerce_after_calculate_totals', [ Cart::class, 'syncCartData' ] );
		add_filter( 'script_loader_tag', [ Cart::class, 'addCloudFlareAttrScript' ], 10, 3 );
		add_action( 'wp_loaded', [ cart::class, 'applyAbandonedCartCoupon' ] );
		add_action( 'woocommerce_removed_coupon', [ cart::class, 'removeAbandonedCartCoupon' ] );
		add_filter( 'woocommerce_checkout_fields', [ cart::class, 'guestGdprMessage' ], 10, 1 );
		add_action( 'woocommerce_checkout_after_terms_and_conditions', [ cart::class, 'guestTermGdprMessage' ] );
		add_filter( 'rnoc_can_track_abandoned_carts', [ cart::class, 'isZeroValueCart' ], 15, 2 );
		$cart_tracking_engine = SettingHelpers::get( 'retainful_settings', RNOC_PLUGIN_PREFIX . 'cart_tracking_engine', 'js' );
		if ( $cart_tracking_engine == "php" ) {
			add_action( 'woocommerce_after_calculate_totals', [ Cart::class, 'syncCartData' ] );
		} elseif ( $cart_tracking_engine == 'js' ) {
			add_action( 'wp_footer', [ cart::class, 'renderAbandonedCartTrackingDiv' ] );
			add_filter( 'woocommerce_add_to_cart_fragments', [ cart::class, 'addToCartFragments' ] );
		}
		add_action( 'wp_footer', [ cart::class, 'printRefreshFragmentScript' ] );
		add_action( 'wp_enqueue_scripts', [ Cart::class, 'addCartTrackingScripts' ] );
		//user action
		add_action( 'wp_authenticate', [ Cart::class, 'userLoggedOn' ] );
		add_action( 'user_register', [ Cart::class, 'userSignedUp' ] );
		add_action( 'wp_logout', [ cart::class, 'userLoggedOut' ] );

//      add_action('woocommerce_payment_complete', array($checkout, 'paymentCompleted'));
//      add_action('woocommerce_checkout_update_order_meta', array($checkout, 'checkoutOrderProcessed'));

	}


	/**
	 * Register all the required end points
	 */

	public static function addCommonHooks() {
		SettingHelpers::initStorage();
		add_action( 'wp_ajax_rnoc_track_user_data', [ cart::class, 'setCustomerData' ] );
		add_action( 'woocommerce_cart_loaded_from_session', [ cart::class, 'handlePersistentCart' ] );
		//	add_action( 'woocommerce_api_retainful', [ cart::class, 'recoverUserCart' ] );

	}


}