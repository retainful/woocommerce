<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Admin\Admin;
use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\Cart;
use Rnoc\Retainful\Api\AbandonedCart\Checkout;
use Rnoc\Retainful\Api\AbandonedCart\Storage\Cookie;
use Rnoc\Retainful\Api\AbandonedCart\Storage\PhpSession;
use Rnoc\Retainful\Api\AbandonedCart\Storage\WooSession;
use Rnoc\Retainful\Api\NextOrderCoupon\CouponManagement;
use Rnoc\Retainful\Api\Referral\ReferralManagement;
use Rnoc\Retainful\Integrations\Currency;
use Rnoc\Retainful\library\RetainfulApi;
use Rnoc\Retainful\Premium\RetainfulPremiumMain;

class Main
{
    /**
     * @var self
     */
    public static $init;
    /**
     * @var null|PhpSession|Cookie|WooSession
     */
    public static $storage = null;
    /**
     * @var null | Settings
     */
    public static $settings = null;
    /**
     * @var null|RetainfulApi
     */
    public static $api = null;
    /**
     * @var null|OrderCoupon
     */
    public static $next_order_coupon = null;
    /**
     * @var null|CouponManagement
     */
    public static $coupon_api = null;
    /**
     * @var null|Cart
     */
    public static $abandoned_cart = null;
    /**
     * @var null|Checkout
     */
    public static $abandoned_cart_checkout = null;
    /**
     * @var null|Admin
     */
    public static $plugin_admin = null;
    /**
     * @var null|ReferralManagement
     */
    public static $referral_program = null;
    /**
     * @var null|RetainfulPremiumMain
     */
    public static $premium_features = null;
    /**
     * @var null|WcFunctions
     */
    public static $woocommerce = null;

    /**
     * Main constructor.
     */
    function __construct()
    {
        add_action('woocommerce_init', array($this, 'initClassesAndHooks'), 11);
    }

    /**
     * Init all classes and its hooks
     */
    function initClassesAndHooks()
    {
        /**
         * Init classes
         */
        self::$settings = (is_null(self::$settings)) ? new Settings() : self::$settings;
        self::$woocommerce = (is_null(self::$woocommerce)) ? new WcFunctions() : self::$woocommerce;
        self::$next_order_coupon = (is_null(self::$next_order_coupon)) ? new OrderCoupon() : self::$next_order_coupon;
        self::$abandoned_cart = (is_null(self::$abandoned_cart)) ? new Cart() : self::$abandoned_cart;
        self::$abandoned_cart_checkout = (is_null(self::$abandoned_cart_checkout)) ? new Checkout() : self::$abandoned_cart_checkout;
        self::$plugin_admin = (is_null(self::$plugin_admin)) ? new Admin() : self::$plugin_admin;
        self::$referral_program = (is_null(self::$referral_program)) ? new ReferralManagement() : self::$referral_program;
        self::$coupon_api = (is_null(self::$coupon_api)) ? new CouponManagement() : self::$coupon_api;
        $storage_handler = self::$plugin_admin->getStorageHandler();
        switch ($storage_handler) {
            case "php";
                self::$storage = new PhpSession();
                break;
            case "cookie";
                self::$storage = new Cookie();
                break;
            default:
            case "woocommerce":
                self::$storage = new WooSession();
                break;
        }
        self::$api = empty(self::$api) ? new RetainfulApi() : self::$api;
        $is_app_connected = self::$plugin_admin->isAppConnected();
        if (self::$plugin_admin->isProPlan() && $is_app_connected) {
            self::$premium_features = (is_null(self::$premium_features)) ? new RetainfulPremiumMain() : self::$premium_features;
        }
        //Remove scheduled hooks
        register_deactivation_hook(RNOC_FILE, array($this, 'onPluginDeactivation'));
        add_filter('views_edit-shop_coupon', array(self::$coupon_api, 'viewsEditShopCoupon'));
        add_filter('request', array(self::$coupon_api, 'requestQuery'));
        /**
         * Init class hooks
         */
        self::$woocommerce->initWoocommerceSession();
        if (is_admin() || is_blog_admin()) {
            add_action('admin_init', array(self::$plugin_admin, 'setupSurveyForm'), 10);
            add_action('admin_menu', array(self::$plugin_admin, 'registerMenu'));
            self::$plugin_admin->initAdminPageStyles();
            //Validate key
            add_action('wp_ajax_validate_app_key', array(self::$plugin_admin, 'validateAppKey'));
            add_action('wp_ajax_rnoc_get_search_coupon', array(self::$plugin_admin, 'getSearchedCoupons'));
            add_action('wp_ajax_rnoc_disconnect_license', array(self::$plugin_admin, 'disconnectLicense'));
            add_action('wp_ajax_rnoc_save_settings', array(self::$plugin_admin, 'saveAcSettings'));
            add_action('wp_ajax_rnoc_save_noc_settings', array(self::$plugin_admin, 'saveNocSettings'));
            add_action('wp_ajax_rnoc_save_premium_addon_settings', array(self::$plugin_admin, 'savePremiumAddOnSettings'));
            //Settings link
            add_filter('plugin_action_links_' . RNOC_BASE_FILE, array(self::$plugin_admin, 'pluginActionLinks'));
            //Check plan
            $this->checkApi();
        }
        //add end points
        add_action('rest_api_init', array($this, 'registerEndPoints'));
        //Check for dependencies
        add_action('plugins_loaded', array($this, 'checkDependencies'));
        new Currency();
        if (self::$plugin_admin->isNextOrderCouponEnabled()) {
            //Get events
            add_action('woocommerce_checkout_update_order_meta', array(self::$next_order_coupon, 'createNewCoupon'), 10, 2);
            add_action('woocommerce_order_status_changed', array(self::$next_order_coupon, 'onAfterPayment'), 10, 1);
            add_action('woocommerce_get_shop_coupon_data', array(self::$next_order_coupon, 'addVirtualCoupon'), 10, 2);
            add_action('rnoc_create_new_next_order_coupon', array(self::$next_order_coupon, 'createNewCoupon'), 10, 2);
            add_action('rnoc_initiated', array(self::$next_order_coupon, 'setCouponToSession'));
            add_action('wp_loaded', array(self::$next_order_coupon, 'addCouponToCheckout'), 30);
            //Attach coupon to email
            $hook = self::$plugin_admin->couponMessageHook();
            if (!empty($hook))
                add_action($hook, array(self::$next_order_coupon, 'attachOrderCoupon'), 10, 4);
            //add action for filter
            add_action('rnoc_show_order_coupon', array(self::$next_order_coupon, 'attachOrderCoupon'), 10, 4);
            //Sync the coupon details with retainful
            add_action('retainful_cron_sync_coupon_details', array(self::$next_order_coupon, 'cronSendCouponDetails'), 1);
            //Remove coupon code after placing order
            add_action('woocommerce_thankyou', array(self::$next_order_coupon, 'removeCouponFromSession'), 10, 1);
            // Show coupon in order thankyou page
            add_action('woocommerce_thankyou', array(self::$next_order_coupon, 'showCouponInThankYouPage'), 10, 1);
            //Remove Code from session
            add_action('woocommerce_removed_coupon', array(self::$next_order_coupon, 'removeCouponFromCart'));
            /*
             * Support for woocommerce email customizer
             */
            add_filter('woo_email_drag_and_drop_builder_retainful_settings_url', array(self::$next_order_coupon, 'wooEmailCustomizerRetainfulSettingsUrl'));
            //Tell Email customizes about handling coupons..
            add_filter('woo_email_drag_and_drop_builder_handling_retainful', '__return_true');
            //set coupon details for Email customizer
            add_filter('woo_email_drag_and_drop_builder_retainful_next_order_coupon_data', array(self::$next_order_coupon, 'wooEmailCustomizerRetainfulCouponContent'), 10, 3);
            //sent retainful additional short codes
            add_filter('woo_email_drag_and_drop_builder_load_additional_shortcode', array(self::$next_order_coupon, 'wooEmailCustomizerRegisterRetainfulShortCodes'), 10);
            add_filter('woo_email_drag_and_drop_builder_load_additional_shortcode_data', array(self::$next_order_coupon, 'wooEmailCustomizerRetainfulShortCodesValues'), 10, 3);
            add_filter('wp_footer', array(self::$next_order_coupon, 'showAppliedCouponPopup'));
        }
        $this->canActivateIPFilter();
        //If the user is old user then ask user to run abandoned cart to
        $secret_key = self::$plugin_admin->getSecretKey();
        $app_id = self::$plugin_admin->getApiKey();
        if ($is_app_connected && !empty($secret_key) && !empty($app_id)) {
            add_action('wp_loaded', array(self::$plugin_admin, 'schedulePlanChecker'),31);
            if (self::$plugin_admin->isProPlan()) {
                add_action('wp_footer', array(self::$referral_program, 'printReferralPopup'));
                add_action('wp_enqueue_scripts', array(self::$referral_program, 'referralProgramScripts'));
            }
            add_filter('script_loader_src', array(self::$abandoned_cart, 'addCloudFlareAttrScript'), 10, 2);
            add_filter('clean_url', array(self::$abandoned_cart, 'uncleanUrl'), 10, 3);
            //Sync the order by the scheduled events
            add_filter('woocommerce_checkout_fields', array(self::$abandoned_cart_checkout, 'moveEmailFieldToTop'));
            add_action('retainful_sync_abandoned_cart_order', array(self::$abandoned_cart_checkout, 'syncOrderByScheduler'), 1);
            add_action('wp_ajax_rnoc_track_user_data', array(self::$abandoned_cart, 'setCustomerData'));
            add_action('wp_ajax_nopriv_rnoc_track_user_data', array(self::$abandoned_cart, 'setCustomerData'));
            add_action('wp_ajax_rnoc_ajax_get_encrypted_cart', array(self::$abandoned_cart, 'ajaxGetEncryptedCart'));
            add_action('wp_ajax_nopriv_rnoc_ajax_get_encrypted_cart', array(self::$abandoned_cart, 'ajaxGetEncryptedCart'));
            add_action('woocommerce_cart_loaded_from_session', array(self::$abandoned_cart, 'handlePersistentCart'));
            //add_action('wp_login', array($cart, 'userLoggedIn'));
            add_action('woocommerce_api_retainful', array(self::$abandoned_cart, 'recoverUserCart'));
            add_action('wp_loaded', array(self::$abandoned_cart, 'applyAbandonedCartCoupon'));
            //Add tracking message
            if (is_user_logged_in()) {
                add_action('woocommerce_after_add_to_cart_button', array(self::$abandoned_cart, 'userGdprMessage'), 10);
                add_action('woocommerce_before_shop_loop', array(self::$abandoned_cart, 'userGdprMessage'), 10);
            }
            add_filter('woocommerce_checkout_fields', array(self::$abandoned_cart, 'guestGdprMessage'), 10, 1);
            add_action('wp_footer', array(self::$abandoned_cart_checkout, 'setRetainfulOrderData'));
            add_filter('rnoc_can_track_abandoned_carts', array(self::$abandoned_cart, 'isZeroValueCart'), 15);
            $cart_tracking_engine = self::$plugin_admin->getCartTrackingEngine();
            if ($cart_tracking_engine == "php") {
                //PHP tracking
                add_action('woocommerce_after_calculate_totals', array(self::$abandoned_cart, 'syncCartData'));
            } else {
                //Js tracking
                add_action('wp_footer', array(self::$abandoned_cart, 'renderAbandonedCartTrackingDiv'));
                add_filter('woocommerce_add_to_cart_fragments', array(self::$abandoned_cart, 'addToCartFragments'));
            }
            add_action('wp_footer', array(self::$abandoned_cart, 'printRefreshFragmentScript'));
            add_action('wp_enqueue_scripts', array(self::$abandoned_cart, 'addCartTrackingScripts'));
            add_action('wp_authenticate', array(self::$abandoned_cart, 'userLoggedOn'));
            add_action('user_register', array(self::$abandoned_cart, 'userSignedUp'));
            add_action('wp_logout', array(self::$abandoned_cart, 'userLoggedOut'));
            //Set order as recovered
            // handle payment complete, from a direct gateway
            //add_action('woocommerce_new_order', array($checkout, 'purchaseComplete'));
            add_filter('woocommerce_thankyou', array(self::$abandoned_cart_checkout, 'payPageOrderCompletion'));
            add_action('woocommerce_payment_complete', array(self::$abandoned_cart_checkout, 'paymentCompleted'));
            add_action('woocommerce_checkout_order_processed', array(self::$abandoned_cart_checkout, 'checkoutOrderProcessed'));
            add_filter('woocommerce_payment_successful_result', array(self::$abandoned_cart_checkout, 'maybeUpdateOrderOnSuccessfulPayment'), 10, 2);
            // handle updating Retainful order data after a successful payment, for certain gateways
            add_action('woocommerce_order_status_changed', array(self::$abandoned_cart_checkout, 'orderStatusChanged'), 15, 3);
            // handle placed orders
            add_action('woocommerce_order_status_changed', array(self::$abandoned_cart_checkout, 'orderUpdated'), 11, 1);
            add_action('woocommerce_update_order', array(self::$abandoned_cart_checkout, 'orderUpdated'), 10, 1);
            //Todo: multi currency and multi lingual
            #add_action('wp_login', array($this->abandoned_cart_api, 'userCartUpdated'));
        } else {
            $connect_txt = (!empty($secret_key) && !empty($app_id)) ? __('connect', RNOC_TEXT_DOMAIN) : __('re-connect', RNOC_TEXT_DOMAIN);
            $notice = '<p>' . sprintf(__("Please <a href='" . admin_url('admin.php?page=retainful_license') . "'>%s</a> with Retainful to track and manage abandoned carts. ", RNOC_TEXT_DOMAIN), $connect_txt) . '</p>';
            $this->showAdminNotice($notice);
        }
        $is_retainful_v2_0_1_migration_completed = get_option('is_retainful_v2_0_1_migration_completed', 0);
        if (!$is_retainful_v2_0_1_migration_completed) {
            $this->migrationV201();
        }
        //Premium check
        add_action('rnocp_check_user_plan', array($this, 'checkUserPlan'));
        do_action('rnoc_initiated');
    }

    /**
     * Register all the required end points
     */
    function registerEndPoints()
    {
        //Register custom endpoint for API
        register_rest_route('retainful-api/v1', '/verify', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'verifyAppId')
        ));
        register_rest_route('retainful-api/v1', '/coupon', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => 'Rnoc\Retainful\Api\NextOrderCoupon\CouponManagement::createRestCoupon'
        ));
        register_rest_route('retainful-api/v1', '/customer', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => 'Rnoc\Retainful\Api\Referral\ReferralManagement::getCustomer'
        ));
    }

    /**
     * verify the app id
     * @param $data
     * @return \WP_REST_Response
     */
    function verifyAppId($data)
    {
        $app_id = sanitize_text_field($data->get_param('app_id'));
        $app_secret = sanitize_text_field($data->get_param('app_secret'));
        $site_url = site_url();
        $entered_app_id = self::$plugin_admin->getApiKey();
        $entered_secret_key = self::$plugin_admin->getSecretKey();
        $is_app_connected = self::$plugin_admin->isAppConnected();
        $response_code = NULL;
        if (empty($entered_secret_key) && empty($entered_app_id)) {
            $response_code = 'INSTALLED_NO_APP_ID_AND_NO_SECRET_KEY_FOUND';
        } elseif (empty($entered_app_id)) {
            $response_code = 'INSTALLED_NO_APP_ID_FOUND';
        } elseif (empty($entered_secret_key)) {
            $response_code = 'INSTALLED_NO_SECRET_KEY_FOUND';
        } elseif (!empty($entered_app_id) && $app_id != $entered_app_id) {
            $response_code = 'INSTALLED_DIFFERENT_APP_ID';
        } elseif (!empty($entered_secret_key) && $app_secret != $entered_secret_key) {
            $response_code = 'INSTALLED_DIFFERENT_SECRET_KEY';
        } elseif (!empty($entered_secret_key) && !empty($entered_secret_key) && !$is_app_connected) {
            $response_code = 'INSTALLED_NOT_CONNECTED';
        } elseif (!empty($entered_app_id) && !empty($entered_secret_key) && $app_secret == $entered_secret_key && $app_id == $entered_app_id && $is_app_connected) {
            $response_code = 'INSTALLED_CONNECTED';
        } else {
            $response_code = 'UNKNOWN_ERROR';
        }
        $response = array(
            'success' => ($response_code == 'INSTALLED_CONNECTED'),
            'message' => '',
            'code' => $response_code,
            'data' => array(
                'domain' => $site_url
            )
        );
        $response_object = new \WP_REST_Response($response);
        $response_object->set_status(200);
        return $response_object;
    }

    /**
     * can activate IP filter
     */
    function canActivateIPFilter()
    {
        global $retainful;
        $enable_ip_filter = $retainful::$settings->get('general_settings', RNOC_PLUGIN_PREFIX . 'enable_ip_filter', 0);
        $ignored_ips = $retainful::$settings->get('general_settings', RNOC_PLUGIN_PREFIX . 'enable_ip_filter', '');
        if (!empty($enable_ip_filter) && !empty($ignored_ips)) {
            if (!empty($ip)) {
                $ip_filter = new IpFiltering($ignored_ips);
                add_filter('rnoc_is_cart_has_valid_ip', array($ip_filter, 'trackAbandonedCart'), 10, 2);
            }
        }
    }

    /**
     * Migration for 2.1.0
     */
    function migrationV201()
    {
        global $retainful;
        $admin_settings = $retainful::$settings->get('general_settings', null, array(), true);
        $admin_settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] = $retainful::$settings->get('premium', RNOC_PLUGIN_PREFIX . 'enable_ip_filter', 0);
        $admin_settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'] = $retainful::$settings->get('premium', RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses', '');
        $retainful::$settings->set('general_settings', $admin_settings);
        update_option('is_retainful_v2_0_1_migration_completed', 1);
    }

    /**
     * Run when our plugin get deactivated
     */
    function onPluginDeactivation()
    {
        $this->removeAllScheduledActions();
    }

    /**
     * Remove all actions without any knowledge
     */
    function removeAllScheduledActions()
    {
        self::$plugin_admin->removeFinishedHooks('rnoc_abandoned_clear_abandoned_carts');
        self::$plugin_admin->removeFinishedHooks('rnoc_abandoned_cart_send_email');
        self::$plugin_admin->removeFinishedHooks('rnocp_check_user_plan');
    }

    /**
     * check api is valid or not on 3 days once
     */
    function checkApi()
    {
        $last_checked = get_option('rnoc_last_plan_checked', NULL);
        if (empty($last_checked) || (current_time('timestamp') > intval($last_checked) + 259200)) {
            $this->checkUserPlan();
        }
    }

    /**
     * Check and update the user plan
     */
    function checkUserPlan()
    {
        $api_key = self::$plugin_admin->getApiKey();
        $secret_key = self::$plugin_admin->getSecretKey();
        if (!empty($api_key) && !empty($secret_key)) {
            self::$plugin_admin->isApiEnabled($api_key, $secret_key);
        } else {
            self::$plugin_admin->updateUserAsFreeUser();
        }
        self::$plugin_admin->removeFinishedHooks('rnocp_check_user_plan', 'publish');
    }

    /**
     * Initiate the plugin
     * @return Main
     */
    public static function instance()
    {
        return self::$init = is_null(self::$init) ? new self() : self::$init;
    }

    /**
     * All tables required for retainful abandoned cart
     * @return array
     */
    function getAbandonedCartTables()
    {
        return array(
            RNOC_PLUGIN_PREFIX . 'abandoned_cart_history',
            RNOC_PLUGIN_PREFIX . 'guest_abandoned_cart_history'
        );
    }

    /**
     * Dependency check for our plugin
     */
    function checkDependencies()
    {
        if (!defined('WC_VERSION')) {
            $this->showAdminNotice(__('Woocommerce must be activated for Retainful-Woocommerce to work', RNOC_TEXT_DOMAIN));
        } else {
            if (version_compare(WC_VERSION, '2.5', '<')) {
                $this->showAdminNotice(__('Your woocommerce version is ', RNOC_TEXT_DOMAIN) . WC_VERSION . __('. Some of the features of Retainful-Woocommerce will not work properly on this woocommerce version.', RNOC_TEXT_DOMAIN));
            }
        }
        $this->doMigration();
    }

    /**
     * Migrate data required for v 1.1.3
     */
    function doMigration()
    {
        //TODO: Remove migration
        $is_migrated = get_option('retainful_v_1_1_3_migration_completed', 0);
        if (!$is_migrated) {
            $slug = self::$plugin_admin->slug;
            $retainful_page = get_option($slug, array());
            $licence_page = get_option($slug . '_license', array());
            $usage_restriction_page = get_option($slug . '_usage_restriction', array());
            if (empty($licence_page)) {
                $licence_data = array(
                    RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => (isset($retainful_page[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'])) ? $retainful_page[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'] : 0,
                    RNOC_PLUGIN_PREFIX . 'retainful_app_id' => (isset($retainful_page[RNOC_PLUGIN_PREFIX . 'retainful_app_id'])) ? $retainful_page[RNOC_PLUGIN_PREFIX . 'retainful_app_id'] : '',
                    RNOC_PLUGIN_PREFIX . 'retainful_app_secret' => (isset($retainful_page[RNOC_PLUGIN_PREFIX . 'retainful_app_secret'])) ? $retainful_page[RNOC_PLUGIN_PREFIX . 'retainful_app_secret'] : ''
                );
                update_option($slug . '_license', $licence_data);
            }
            unset($retainful_page[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'], $retainful_page[RNOC_PLUGIN_PREFIX . 'retainful_app_id']);
            $retainful_data = array_merge($retainful_page, $usage_restriction_page);
            update_option($slug, $retainful_data);
            delete_option($slug . '_usage_restriction');
            $abandoned_cart_data = get_option($slug . '_abandoned_cart_settings', array());
            update_option($slug . '_settings', $abandoned_cart_data);
            delete_option($slug . '_abandoned_cart_settings');
            update_option('retainful_v_1_1_3_migration_completed', 1);
        }
    }

    /**
     * Show notices for user..if anything unusually happen in our plugin
     * @param string $message - message to notice users
     */
    function showAdminNotice($message = "")
    {
        if (!empty($message)) {
            add_action('admin_notices', function () use ($message) {
                echo '<div class="error notice"><p>' . $message . '</p></div>';
            });
        }
    }
}