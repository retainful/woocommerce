<?php

namespace Rnoc\Retainful;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\Cart;
use Rnoc\Retainful\Api\AbandonedCart\Checkout;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;
use Rnoc\Retainful\Api\Imports\Imports;
use Rnoc\Retainful\Api\Imports\Products;
use Rnoc\Retainful\Api\Imports\Category;
use Rnoc\Retainful\Api\NextOrderCoupon\CouponManagement;
use Rnoc\Retainful\Api\Popup\Popup;
use Rnoc\Retainful\Integrations\AfterPay;
use Rnoc\Retainful\Integrations\Currency;
use Rnoc\Retainful\library\RetainfulApi;
use Rnoc\Retainful\Api\TrackProduct\TrackProduct;

class Main {
	public static $init;
	public $rnoc, $admin, $abandoned_cart_api;

	/**
	 * Main constructor.
	 */
	function __construct() {
		$this->rnoc  = ( $this->rnoc == null ) ? new OrderCoupon() : $this->rnoc;
		$this->admin = ( $this->admin == null ) ? new Settings() : $this->admin;
		add_filter( 'woocommerce_set_cookie_options', array( $this, 'changeIdentityPath' ), 10, 3 );
		add_action( 'init', array( $this, 'activateEvents' ) );
		add_action( 'woocommerce_init', array( $this, 'includePluginFiles' ) );
		//add_action('woocommerce_init',array($this->admin,'createWebhook'));
		add_action( 'woocommerce_init', array( $this->admin, 'setIdentityData' ) );
	}

	/**
	 * Change identity path.
	 *
	 * @param $option
	 * @param $name
	 * @param $value
	 *
	 * @return mixed
	 */
	function changeIdentityPath( $option, $name, $value ) {
		if ( $name == '_wc_rnoc_tk_session' ) {
			$option['path'] = $this->admin->getIdentityPath();
		}

		return $option;
	}

	function includePluginFiles() {
		$rnoc_varnish_check = $this->admin->getRetainfulSettingValue( 'rnoc_varnish_check', 'no' );
		if ( $rnoc_varnish_check === 'no' ) {
			$woocommerce_functions = new WcFunctions();
			$woocommerce_functions->initWoocommerceSession();
			do_action( 'rnoc_after_including_plugin_files', $woocommerce_functions, $this );
		}
	}

	/**
	 * Register all the required end points
	 */
	function registerEndPoints() {
		//Register custom endpoint for API
		register_rest_route( 'retainful-api/v1', '/verify', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( $this, 'verifyAppId' )
		) );
		register_rest_route( 'retainful-api/v1', '/coupon', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'Rnoc\Retainful\Api\NextOrderCoupon\CouponManagement::createRestCoupon'
		) );
		register_rest_route( 'retainful-api/v1', '/customer', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'Rnoc\Retainful\Api\Referral\ReferralManagement::getCustomer'
		) );
	}

	function registerSyncEndPoints() {
		$import = new Imports();
		register_rest_route('retainful-api/v1', '/orders', array(
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => array($import, 'getSyncOrders')
		));
		register_rest_route('retainful-api/v1', '/orders/count', array(
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => array($import, 'getSyncOrderCount')
		));

		$product = new Products();
		register_rest_route('retainful-api/v1', '/products', array(
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => array($product, 'getSyncProducts')
		));
		register_rest_route('retainful-api/v1', '/products/count', array(
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => array($product, 'getSyncProductCount')
		));
		register_rest_route('retainful-api/v1', '/category/count', array(
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => array(Category::class, 'getCategoryCount')
		));
		register_rest_route('retainful-api/v1', '/category', array(
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => array(Category::class, 'getCategory')
		));

	}

	/**
	 * verify the app id
	 *
	 * @param $data
	 *
	 * @return \WP_REST_Response
	 */
	function verifyAppId( $data ) {
		$app_id             = sanitize_text_field( $data->get_param( 'app_id' ) );
		$app_secret         = sanitize_text_field( $data->get_param( 'app_secret' ) );
		$site_url           = site_url();
		$entered_app_id     = $this->admin->getApiKey();
		$entered_secret_key = $this->admin->getSecretKey();
		$is_app_connected   = $this->admin->isAppConnected();
		$response_code      = null;
		if ( empty( $entered_secret_key ) && empty( $entered_app_id ) ) {
			$response_code = 'INSTALLED_NO_APP_ID_AND_NO_SECRET_KEY_FOUND';
		} elseif ( empty( $entered_app_id ) ) {
			$response_code = 'INSTALLED_NO_APP_ID_FOUND';
		} elseif ( empty( $entered_secret_key ) ) {
			$response_code = 'INSTALLED_NO_SECRET_KEY_FOUND';
		} elseif ( ! empty( $entered_app_id ) && $app_id != $entered_app_id ) {
			$response_code = 'INSTALLED_DIFFERENT_APP_ID';
		} elseif ( ! empty( $entered_secret_key ) && $app_secret != $entered_secret_key ) {
			$response_code = 'INSTALLED_DIFFERENT_SECRET_KEY';
		} elseif ( ! empty( $entered_secret_key ) && ! empty( $entered_secret_key ) && ! $is_app_connected ) {
			$response_code = 'INSTALLED_NOT_CONNECTED';
		} elseif ( ! empty( $entered_app_id ) && ! empty( $entered_secret_key ) && $app_secret == $entered_secret_key && $app_id == $entered_app_id && $is_app_connected ) {
			$response_code = 'INSTALLED_CONNECTED';
		} else {
			$response_code = 'UNKNOWN_ERROR';
		}
		$response        = array(
			'success' => ( $response_code == 'INSTALLED_CONNECTED' ) ? true : false,
			'message' => '',
			'code'    => $response_code,
			'data'    => array(
				'domain' => $site_url
			)
		);
		$response_object = new \WP_REST_Response( $response );
		$response_object->set_status( 200 );

		return $response_object;
	}

	/**
	 * Check the woocommerce ac need to run externally
	 *
	 * @param $need_ac_externally
	 *
	 * @return bool|mixed|void
	 */
	function needToRunAbandonedCartExternally( $need_ac_externally ) {
		$need_ac_externally = $this->admin->runAbandonedCartExternally();

		return $need_ac_externally;
	}

	/**
	 * Activate the required events
	 */
	function activateEvents() {
		//Register deactivation hook
		register_deactivation_hook( RNOC_FILE, array( $this, 'onPluginDeactivation' ) );
		//add_action('retainful_plugin_activated', array($this, 'createRequiredTables'));
		//add end points
		add_action( 'rest_api_init', array( $this, 'registerEndPoints' ) );
		//Detect woocommerce plugin deactivation
		add_action( 'deactivated_plugin', array( $this, 'detectPluginDeactivation' ), 10, 2 );
		//Check for dependencies
		add_action( 'plugins_loaded', array( $this, 'checkDependencies' ) );
		add_action( 'rnocp_activation_trigger', array( $this, 'checkUserPlan' ) );
		add_filter( 'rnoc_need_to_run_ac_in_cloud', array( $this, 'needToRunAbandonedCartExternally' ) );
		if ( is_admin() ) {
			//Deactivation survey form
			add_action( 'admin_init', array( $this->admin, 'setupSurveyForm' ), 10 );
			$coupon_api = new CouponManagement();
			add_filter( 'views_edit-shop_coupon', array( $coupon_api, 'viewsEditShopCoupon' ) );
			add_action( 'manage_posts_extra_tablenav', array( $coupon_api, 'showDeleteButton' ) );
			add_filter( 'woocommerce_coupon_options', array( $coupon_api, 'showCouponOrderDetails' ) );
			add_filter( 'request', array( $coupon_api, 'requestQuery' ) );
			add_action( 'admin_menu', array( $this->admin, 'registerMenu' ) );
			$this->admin->initAdminPageStyles();
			//Validate key
			add_action( 'wp_ajax_validate_app_key', array( $this->admin, 'validateAppKey' ) );
			add_action( 'wp_ajax_rnoc_get_search_coupon', array( $this->admin, 'getSearchedCoupons' ) );
			add_action( 'wp_ajax_rnoc_disconnect_license', array( $this->admin, 'disconnectLicense' ) );
			add_action( 'wp_ajax_rnoc_save_settings', array( $this->admin, 'saveAcSettings' ) );
			//add_filter('wp_ajax_rnoc_create_order_update_webhook',array($this->admin,'saveNewWebhook'),10);
			add_action( 'wp_ajax_rnoc_delete_expired_coupons', array( $this->admin, 'deleteUnusedExpiredCoupons' ) );
			//Settings link
			add_filter( 'plugin_action_links_' . RNOC_BASE_FILE, array( $this->rnoc, 'pluginActionLinks' ) );
			if ( apply_filters( 'rnoc_show_order_token_in_order', false ) ) {
				add_action( 'add_meta_boxes', array( $this->admin, 'addOrderDetailMetaBoxes' ), 20 );
			}
		}
		//initialise currency helper
		new Currency();

		/**
		 * Ip filtering
		 */
		$this->canActivateIPFilter();
		$is_app_connected = $this->admin->isAppConnected();
		$secret_key       = $this->admin->getSecretKey();
		$app_id           = $this->admin->getApiKey();

		$run_installation_externally = $this->admin->runAbandonedCartExternally();
		if ( $run_installation_externally ) {
			//If the user is old user then ask user to run abandoned cart to
			/*$is_app_connected = $this->admin->isAppConnected();
			$secret_key = $this->admin->getSecretKey();
			$app_id = $this->admin->getApiKey();*/
			if ( $is_app_connected && ! empty( $secret_key ) && ! empty( $app_id ) ) {
				add_action( 'rest_api_init', array( $this, 'registerSyncEndPoints' ) );

				/*
				* Retainful abandoned cart api
				*/
				$cart                  = new Cart();
				$checkout              = new Checkout();
				$need_popup_widget     = $this->admin->needPopupWidget();
				if ( $need_popup_widget ) {
					$popup = new Popup();
					add_action( 'user_register', array( $popup, 'userRegister' ) );
					add_action( 'wp_login', array( $popup, 'userLogin' ), 10, 2 );
					add_action( 'wp_enqueue_scripts', array( $popup, 'addPopupScripts' ) );
					add_action( 'wp_footer', array( $popup, 'printPopup' ) );

					//apply popup coupon
					add_action('wp_ajax_rnoc_apply_popup_coupon', array($popup, 'addPopupCouponToSession'));
					add_action('wp_ajax_nopriv_rnoc_apply_popup_coupon', array($popup, 'addPopupCouponToSession')); // For non-logged-in users
					add_action( 'wp_loaded', array( $popup, 'applyPopupCoupon' ), 10 );
				}
				add_filter( 'script_loader_tag', array( $cart, 'addCloudFlareAttrScript' ), 10, 3 );
				//add_filter('clean_url', array($cart, 'uncleanUrl'), 10, 3);
				//Sync the order by the scheduled events
				add_action( 'retainful_sync_abandoned_cart_order', array( $checkout, 'syncOrderByScheduler' ), 1 );
				add_action( 'wp_ajax_rnoc_track_user_data', array( $cart, 'setCustomerData' ) );
				add_action( 'wp_ajax_nopriv_rnoc_track_user_data', array( $cart, 'setCustomerData' ) );
				add_action( 'wp_ajax_rnoc_ajax_get_encrypted_cart', array( $cart, 'ajaxGetEncryptedCart' ) );
				add_action( 'wp_ajax_nopriv_rnoc_ajax_get_encrypted_cart', array( $cart, 'ajaxGetEncryptedCart' ) );
				add_action( 'woocommerce_cart_loaded_from_session', array( $cart, 'handlePersistentCart' ) );
				//add_action('wp_login', array($cart, 'userLoggedIn'));
				add_action( 'woocommerce_api_retainful', array( $cart, 'recoverUserCart' ) );
				add_action( 'wp_loaded', array( $cart, 'applyAbandonedCartCoupon' ) );
				add_action( 'woocommerce_removed_coupon', array( $cart, 'removeNextOrderCouponFromCart' ) );
				//Add tracking message
				/*if (is_user_logged_in()) {
					add_action('woocommerce_after_add_to_cart_button', array($cart, 'userGdprMessage'), 10);
					add_action('woocommerce_before_shop_loop', array($cart, 'userGdprMessage'), 10);
				}*/
				add_filter( 'woocommerce_checkout_fields', array( $cart, 'guestGdprMessage' ), 10, 1 );
				add_action( 'woocommerce_checkout_after_terms_and_conditions', array( $cart, 'guestTermGdprMessage' ) );
				add_action( 'wp_footer', array( $checkout, 'setRetainfulOrderData' ) );
				add_filter( 'rnoc_can_track_abandoned_carts', array( $cart, 'isZeroValueCart' ), 15, 2 );
				$cart_tracking_engine = $this->admin->getCartTrackingEngine();
				if ( $cart_tracking_engine == "php" ) {
					//PHP tracking
					add_action( 'woocommerce_after_calculate_totals', array( $cart, 'syncCartData' ) );
				} else {
					//Js tracking
					add_action( 'wp_footer', array( $cart, 'renderAbandonedCartTrackingDiv' ) );
					add_filter( 'woocommerce_add_to_cart_fragments', array( $cart, 'addToCartFragments' ) );
				}
				add_action( 'wp_footer', array( $cart, 'printRefreshFragmentScript' ) );
				add_action( 'wp_enqueue_scripts', array( $cart, 'addCartTrackingScripts' ) );
				add_action( 'wp_authenticate', array( $cart, 'userLoggedOn' ) );
				add_action( 'user_register', array( $cart, 'userSignedUp' ) );
				add_action( 'wp_logout', array( $cart, 'userLoggedOut' ) );
				//Set order as recovered
				// handle payment complete, from a direct gateway
				//add_action('woocommerce_new_order', array($checkout, 'purchaseComplete'));
				add_action( 'woocommerce_thankyou', array( $checkout, 'payPageOrderCompletion' ) );
				add_action( 'woocommerce_payment_complete', array( $checkout, 'paymentCompleted' ) );
				add_action( 'woocommerce_checkout_update_order_meta', array( $checkout, 'checkoutOrderProcessed' ) );
				add_action( 'woocommerce_store_api_checkout_update_order_meta', array(
					$checkout,
					'apiCheckoutOrderProcessed'
				) );
				//      add_filter('woocommerce_payment_successful_result', array($checkout, 'maybeUpdateOrderOnSuccessfulPayment'), 10, 2);
				// handle updating Retainful order data after a successful payment, for certain gateways
				add_action( 'woocommerce_order_status_changed', array( $checkout, 'orderStatusChanged' ), 15, 3 );
				// handle placed orders
				add_action( 'woocommerce_order_status_changed', array( $checkout, 'orderUpdated' ), 11, 1 );
				//triggers when admin pdate the order
				add_action( 'woocommerce_process_shop_order_meta', array( $checkout, 'orderUpdatedShopBackend' ), 50, 2 );

				$product = new Products();

				add_filter( 'woocommerce_valid_webhook_resources',function($resources){
					$resources[] = 'category';
					return $resources;
				});

				//add_action('woocommerce_update_order', array($checkout, 'orderUpdated'), 10, 1);
				add_filter( 'woocommerce_webhook_http_args',function( $http_args, $order_id, $webhook_id) use ($product,$checkout){
					if ( $webhook_id <= 0 || ! class_exists( 'WC_Webhook' ) || ! $this->admin->isConnectionActive() ) {
						return $http_args;
					}
					try {
						$webhook = new \WC_Webhook( $webhook_id );
						$topic   = $webhook->get_topic();
						if(in_array($topic,['category.updated', 'category.created', 'category.deleted'])) {
							$http_args = Category::changeWebHookHeaderCategory( $http_args, $order_id, $webhook_id);
						}elseif (in_array($topic,['order.updated', 'order.created'])){
							$http_args = $checkout->changeWebHookHeader( $http_args, $order_id, $webhook_id);
						}elseif (in_array($topic,['product.updated', 'product.created', 'product.deleted'])){
							$http_args = $product->changeWebHookHeaderProduct( $http_args, $order_id, $webhook_id);
						}
					}catch ( \Exception $e) {

					}
					return $http_args;
				}, 10, 3 );

		        //track viewed product
				add_action('template_redirect',[ TrackProduct::class, 'trackViewedProduct' ]);
				add_action('created_product_cat', [Category::class,'createCategory'], 10, 2);
				add_action('edited_product_cat', [Category::class,'updateCategory'], 10, 2);
				add_action('delete_product_cat', [Category::class,'deleteCategory'], 10, 2);
				add_action('retainful_category', [Category::class, 'categoryCallback'], 10, 2);

				//Todo: multi currency and multi lingual
				//add_action('wp_login', array($this->abandoned_cart_api, 'userCartUpdated'));
				if ( $this->admin->isAfterPayEnabled() ) {
					new AfterPay();
				}

			} else {
				if ( is_admin() ) {
					$connect_txt = ( ! empty( $secret_key ) && ! empty( $app_id ) ) ? __( 'connect', 'retainful-next-order-coupon-for-woocommerce' ) : __( 're-connect', 'retainful-next-order-coupon-for-woocommerce' );
					/* translators: %s: get connection url */
					$notice      = sprintf(__('Please with Retainful to track and manage abandoned carts. %s' ,'retainful-next-order-coupon-for-woocommerce'),"<a href='".esc_url(admin_url( 'admin.php?page=retainful_license' ))."'>$connect_txt</a>");
					$this->showAdminNotice( $notice );
				}
			}
		} else {
			//remove
		}

		//Premium check
		do_action( 'rnoc_initiated' );

	}

	function canActivateIPFilter() {
		$settings = $this->admin->getAdminSettings();
		if ( isset( $settings[ RNOC_PLUGIN_PREFIX . 'enable_ip_filter' ] ) && ! empty( $settings[ RNOC_PLUGIN_PREFIX . 'enable_ip_filter' ] ) && isset( $settings[ RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses' ] ) && ! empty( $settings[ RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses' ] ) ) {
			$ip = $settings[ RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses' ];
			if ( ! empty( $ip ) ) {
				$ip_filter = new IpFiltering( $ip );
				add_filter( 'rnoc_is_cart_has_valid_ip', array( $ip_filter, 'trackAbandonedCart' ), 10, 2 );
			}
		}
	}


	/**
	 * Run when our plugin get deactivated
	 */
	function onPluginDeactivation() {
		$this->removeAllScheduledActions();
		$this->admin->removeWebhook();
	}


	/**
	 * Remove all actions without any knowledge
	 */
	function removeAllScheduledActions() {
		$this->admin->removeFinishedHooks( 'rnoc_abandoned_clear_abandoned_carts' );
	}


	/**
	 * Initiate the plugin
	 * @return Main
	 */
	public static function instance() {
		return self::$init = ( self::$init == null ) ? new self() : self::$init;
	}



	/**
	 * detect woocommerce have been deactivated
	 *
	 * @param $plugin
	 * @param $network_activation
	 */
	function detectPluginDeactivation( $plugin, $network_activation ) {
		if ( in_array( $plugin, array( 'woocommerce/woocommerce.php' ) ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			//Todo - Deactivate this plugin
		}
	}

	/**
	 * Dependency check for our plugin
	 */
	function checkDependencies() {
		if ( ! defined( 'WC_VERSION' ) ) {
			$this->showAdminNotice( __( 'Woocommerce must be activated for Retainful-Woocommerce to work', 'retainful-next-order-coupon-for-woocommerce' ) );
		} else {
			if ( version_compare( WC_VERSION, '2.5', '<' ) ) {
				$this->showAdminNotice( __( 'Your woocommerce version is ', 'retainful-next-order-coupon-for-woocommerce' ) . WC_VERSION . __( '. Some of the features of Retainful-Woocommerce will not work properly on this woocommerce version.', 'retainful-next-order-coupon-for-woocommerce' ) );
			}
		}
	}



	/**
	 * Show notices for user..if anything unusually happen in our plugin
	 *
	 * @param   string  $message  - message to notice users
	 */
	function showAdminNotice( $message = "" ) {
		if ( ! empty( $message ) ) {
			add_action( 'admin_notices', function () use ( $message ) {
				echo wp_kses_post('<div class="error notice"><p>' . wp_kses_post($message) . '</p></div>');
			} );
		}
	}
}
