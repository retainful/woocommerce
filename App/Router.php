<?php

namespace RNOC\App;

use RNOC\App\Controllers\Admin\Settings;
use RNOC\App\Controllers\Site\Popups;
use RNOC\App\Controllers\Site\RestApi;
use RNOC\App\Modules\AbandonedCart\Cart;
use RNOC\App\Helpers\Settings as SettingsHelper;
use RNOC\App\Modules\AbandonedCart\Order;

defined( 'ABSPATH' ) || exit;

class Router {
	/**
	 * init hooks.
	 *
	 * @return void
	 */
	public static function init() {

		do_action( RNOC_PLUGIN_PREFIX . 'before_init' );
		self::addCommonHooks();
		if ( is_admin() ) {
			self::addAdminHooks();
		} else {
			self::addStoreHooks();
		}
		do_action( RNOC_PLUGIN_PREFIX . 'after_init' );
	}

	/**
	 * Add common hooks.
	 *
	 * @return void
	 */
	public static function addCommonHooks() {
		//Register deactivation hook
		//register_deactivation_hook( RNOC_FILE, [ Common::class, 'onPluginDeactivation' ] );
		// Rest api
		add_action( 'rest_api_init', [ RestApi::class, 'registerEndPoints' ] );
		add_action( 'rnocp_check_user_plan', [ Settings::class, 'checkUserPlan' ] );
		$secret           = SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );
		$app_key          = SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '', 'license' );
		$is_app_connected = SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', '', 'license' );
		if ( ! empty( $secret ) && ! empty( $app_key ) && $is_app_connected ) {
			$cart = new Cart();
			add_action( 'wp_enqueue_scripts', [ $cart, 'addCartTrackingScripts' ] );
			add_action( 'wp_ajax_rnoc_track_user_data', [ $cart, 'setCustomerData' ] );
			add_action( 'wp_ajax_nopriv_rnoc_track_user_data', [ $cart, 'setCustomerData' ] );

			add_action( 'woocommerce_api_retainful', [ $cart, 'recoverUserCart' ] );

			//add_action('wp_loaded', array($cart, 'applyAbandonedCartCoupon'));
			//add_action('woocommerce_removed_coupon', array($cart, 'removeNextOrderCouponFromCart'));
			$cart_tracking_engine = SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'cart_tracking_engine', 'js' );
			if ( $cart_tracking_engine == 'php' ) {
				//add_action('woocommerce_after_calculate_totals', array($cart, 'syncCartData'));
			} else {
				//Js tracking
				add_action( 'wp_footer', [ $cart, 'renderCartTrackingDiv' ] );
				add_filter( 'woocommerce_add_to_cart_fragments', [ $cart, 'getCartFragments' ] );
				add_action( 'wp_ajax_rnoc_cart_item_change', [ $cart, 'getCartTrackingUpdatedData' ] );
				add_action( 'wp_ajax_nopriv_rnoc_cart_item_change', [ $cart, 'getCartTrackingUpdatedData' ] );
			}
			//add_action('wp_footer', array($cart, 'printRefreshFragmentScript'));

			//add_action('wp_authenticate', array($cart, 'userLoggedOn'));
			//add_action( 'user_register', array( $cart, 'userSignedUp' ) );
			//add_action('wp_logout', array($cart, 'userLoggedOut'));
			$order = new Order();
			//add_action('woocommerce_thankyou', array($checkout, 'payPageOrderCompletion'));
			//add_action('woocommerce_payment_complete', array($checkout, 'paymentCompleted'));
			add_action( 'woocommerce_checkout_update_order_meta', [ $order, 'checkoutOrderProcessed' ] );
			add_action( 'woocommerce_store_api_checkout_update_order_meta', [ $order, 'apiCheckoutOrderProcessed' ] );
			//add_action('woocommerce_order_status_changed', array($checkout, 'orderStatusChanged'), 15, 3);
			// handle placed orders
			add_action('woocommerce_order_status_changed', array($order, 'orderUpdated'), 11, 1);

			//triggers when admin changes the order
			add_action( 'wp_footer', [ $order, 'setRetainfulOrderData' ] );

			add_action( 'woocommerce_process_shop_order_meta', [ $order, 'orderUpdatedShopBackend' ], 50, 2 );
			add_filter( 'woocommerce_webhook_http_args', [ $order, 'changeWebHookHeader' ], 10, 3 );

		}

	}

	/**
	 * Admin side hooks.
	 *
	 * @return void
	 */
	public static function addAdminHooks() {
		add_action( 'admin_menu', [ Settings::class, 'addMenu' ] );
		add_action( 'wp_after_admin_bar_render', [ Settings::class, 'schedulePlanChecker' ] );
		add_action( 'admin_enqueue_scripts', [ Settings::class, 'addAdminScript' ] );
		//app connection
		add_action( 'wp_ajax_rnoc_connection', [ Settings::class, 'connect' ] );
		add_action( 'wp_ajax_rnoc_disconnect', [ Settings::class, 'disConnect' ] );
		//save settings
		add_action( 'wp_ajax_rnoc_save_settings', [ Settings::class, 'saveSettings' ] );

	}

	/**
	 * Store side hooks.
	 *
	 * @return void
	 */
	public static function addStoreHooks() {
		//Popups
		if ( SettingsHelper::isProPlan() && SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'enable_referral_widget', 'no' ) == 'yes' ) {
			add_action( 'wp_footer', [ Popups::class, 'printReferralPopup' ] );
		}
		if ( SettingsHelper::isProPlan() && SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup', 'no' ) == 'yes' ) {
			// Cookie update hooks
			add_filter( 'woocommerce_set_cookie_options', [ Popups::class, 'changeIdentityPath' ], 10, 3 );
			add_action( 'woocommerce_init', [ Popups::class, 'setIdentityData' ] );
			add_action( 'user_register', [ Popups::class, 'setRegisterIdentity' ] );
			add_action( 'wp_login', [ Popups::class, 'setLoginIdentity' ], 10, 2 );
			add_action( 'wp_enqueue_scripts', [ Popups::class, 'addPopupScript' ] );
			add_action( 'wp_footer', [ Popups::class, 'printPopup' ] );
		}
		$secret           = SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'license' );
		$app_key          = SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'retainful_app_id', '', 'license' );
		$is_app_connected = SettingsHelper::get( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', '', 'license' );
		if ( ! empty( $secret ) && ! empty( $app_key ) && $is_app_connected ) {
			$cart = new Cart();
			add_action( 'woocommerce_cart_loaded_from_session', [ $cart, 'handlePersistentCart' ] );
			//add_filter('woocommerce_checkout_fields', array($cart, 'guestGdprMessage'), 10, 1);
			//add_action('woocommerce_checkout_after_terms_and_conditions', array($cart, 'guestTermGdprMessage'));
		}

	}

}