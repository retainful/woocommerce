<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Api\AbandonedCart\Cart;
use Rnoc\Retainful\Api\AbandonedCart\Checkout;
use Rnoc\Retainful\Api\NextOrderCoupon\CouponManagement;
use Rnoc\Retainful\Api\Popup\Popup;
use Rnoc\Retainful\Api\Referral\ReferralManagement;
use Rnoc\Retainful\Integrations\AfterPay;
use Rnoc\Retainful\Integrations\Currency;
use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Controllers\Site\IpFiltering;

class Route
{
    private static $rnoc, $rnoc_settings, $rnoc_function;

    public static function init()
    {
        self::$rnoc = empty(self::$rnoc) ? new \Rnoc\Retainful\Controllers\Site\OrderCoupon() : self::$rnoc;
        self::$rnoc_settings = empty(self::$rnoc_settings) ? new \Rnoc\Retainful\Admin\Settings() : self::$rnoc_settings;
        self::$rnoc_function = empty($rnoc_function) ? new \Rnoc\Retainful\Controllers\Admin\RnocFunction() : self::$rnoc_function;

        add_filter('woocommerce_set_cookie_options', array(self::$rnoc_function, 'changeIdentityPath'), 10, 3);
        add_action('woocommerce_init', array(self::$rnoc_function, 'includePluginFiles'));
        add_action('woocommerce_init', array(self::$rnoc_settings, 'setIdentityData'));
        //init the retainful premium
        new \Rnoc\Retainful\Premium\RetainfulPremiumMain();
        //ini plugin hooks
        do_action('rnoc_before_init');
        self::addCommonHooks();
        if (is_admin()) {
            self::addAdminHooks();
        }
        do_action('rnoc_after_init');
    }


    /**
     * Register all the required end points
     */

    public static function addCommonHooks()
    {
        //Register deactivation hook
        register_deactivation_hook(RNOC_FILE, array(self::$rnoc_function, 'onPluginDeactivation'));
        //add_action('retainful_plugin_activated', array(self::class, 'createRequiredTables'));
        //add end points
        add_action('rest_api_init', array(self::$rnoc_function, 'registerEndPoints'));
        //Detect woocommerce plugin deactivation
        add_action('deactivated_plugin', array(self::$rnoc_function, 'detectPluginDeactivation'), 10, 2);
        //Check for dependencies
        add_action('plugins_loaded', array(self::$rnoc_function, 'checkDependencies'));
        add_action('rnocp_activation_trigger', array(self::$rnoc_function, 'checkUserPlan'));
        add_filter('rnoc_need_to_run_ac_in_cloud', array(self::$rnoc_function, 'needToRunAbandonedCartExternally'));

        //initialise currency helper
        new Currency();
        $can_hide_next_order_coupon = get_option('retainful_hide_next_order_coupon', 'no');
        $show_deprecate_message = isset($_REQUEST['page']) && in_array($_REQUEST['page'], array('retainful_license', 'retainful_settings', 'retainful', 'retainful_premium'));
        if (is_admin() && $show_deprecate_message && self::$rnoc_settings->isNextOrderCouponEnabled() && $can_hide_next_order_coupon == 'no') {
            $notice = '<p>' . __("The Next Order Coupon feature inside the plugin and its tab/menu will soon be removed from the Retainful plugin. Migrate your Next Order Coupon campaign to the Automations now. A detailed guide <a href='https://help.retainful.com/migration#next-order-coupon' target='_blank'>here</a>", RNOC_TEXT_DOMAIN) . '</p>';
            self::$rnoc_function::showAdminNotice($notice);
        }

        if (self::$rnoc_settings->isNextOrderCouponEnabled()) {
            //Get events
            add_action('woocommerce_checkout_update_order_meta', array(self::$rnoc, 'createNewCoupon'), 10, 2);
            add_action('woocommerce_order_status_changed', array(self::$rnoc, 'onAfterPayment'), 10, 1);
            add_action('woocommerce_get_shop_coupon_data', array(self::$rnoc, 'addVirtualCoupon'), 10, 2);
            add_action('rnoc_create_new_next_order_coupon', array(self::$rnoc, 'createNewCoupon'), 10, 2);
            add_action('rnoc_initiated', array(self::$rnoc, 'setCouponToSession'));
            add_action('wp_loaded', array(self::$rnoc, 'addCouponToCheckout'), 10);
            //Attach coupon to email
            $hook = self::$rnoc_settings->couponMessageHook();
            if (!empty($hook) && $hook != "none") {
                add_action($hook, array(self::$rnoc, 'attachOrderCoupon'), 10, 4);
            }
            //add action for filter
            add_action('rnoc_show_order_coupon', array(self::$rnoc, 'attachOrderCoupon'), 10, 4);
            //Sync the coupon details with retainful
            add_action('retainful_cron_sync_coupon_details', array(self::$rnoc, 'cronSendCouponDetails'), 1);
            //Remove coupon code after placing order
            add_action('woocommerce_thankyou', array(self::$rnoc, 'removeCouponFromSession'), 10, 1);
            // Show coupon in order thankyou page
            add_action('woocommerce_thankyou', array(self::$rnoc, 'showCouponInThankYouPage'), 10, 1);
            //Remove Code from session
            add_action('woocommerce_removed_coupon', array(self::$rnoc, 'removeCouponFromCart'));
            /*
             * Support for woocommerce email customizer
             */
            add_filter('woo_email_drag_and_drop_builder_retainful_settings_url', array(self::$rnoc, 'wooEmailCustomizerRetainfulSettingsUrl'));
            //Tell Email customizes about handling coupons..
            add_filter('woo_email_drag_and_drop_builder_handling_retainful', '__return_true');
            //set coupon details for Email customizer
            add_filter('woo_email_drag_and_drop_builder_retainful_next_order_coupon_data', array(self::$rnoc, 'wooEmailCustomizerRetainfulCouponContent'), 10, 3);
            //sent retainful additional short codes
            add_filter('woo_email_drag_and_drop_builder_load_additional_shortcode', array(self::$rnoc, 'wooEmailCustomizerRegisterRetainfulShortCodes'), 10);
            add_filter('woo_email_drag_and_drop_builder_load_additional_shortcode_data', array(self::$rnoc, 'wooEmailCustomizerRetainfulShortCodesValues'), 10, 3);
            add_filter('wp_footer', array(self::$rnoc, 'showAppliedCouponPopup'));

        }
        /**
         * Ip filtering
         */
        $ip_filter = new IpFiltering();
        $ip_filter->canActivateIPFilter();
        $is_app_connected = self::$rnoc_settings->isAppConnected();
        $secret_key = self::$rnoc_settings->getSecretKey();
        $app_id = self::$rnoc_settings->getApiKey();

        $run_installation_externally = self::$rnoc_settings->runAbandonedCartExternally();
        if ($run_installation_externally) {
            //If the user is old user then ask user to run abandoned cart to
            /*$is_app_connected = self::$rnoc_settings->isAppConnected();
            $secret_key = self::$rnoc_settings->getSecretKey();
            $app_id = self::$rnoc_settings->getApiKey();*/
            if ($is_app_connected && !empty($secret_key) && !empty($app_id)) {
                add_action('rest_api_init', array(self::$rnoc_function, 'registerSyncEndPoints'));
                if (is_admin()) {
                    add_action('wp_after_admin_bar_render', array(self::$rnoc_settings, 'schedulePlanChecker'));
                }
                /*
                * Retainful abandoned cart api
                */
                $cart = new Cart();
                $checkout = new Checkout();
                $need_referral_program = self::$rnoc_settings->needReferralWidget();
                $need_popup_widget = self::$rnoc_settings->needPopupWidget();
                if (self::$rnoc_settings->isProPlan() && ($need_referral_program || $need_popup_widget)) {
                    if ($need_referral_program) {
                        $referral_program = new ReferralManagement();
                        add_action('wp_footer', array($referral_program, 'printReferralPopup'));
                        $need_embeded_referral_program = self::$rnoc_settings->needEmbededReferralWidget();
                        if ($need_embeded_referral_program) {
                            add_action('woocommerce_account_dashboard', array($referral_program, 'printEmbededReferralPopup'));
                        }
                    }
                    if ($need_popup_widget) {
                        $popup = new Popup();
                        add_action('user_register', array($popup, 'userRegister'));
                        add_action('wp_login', array($popup, 'userLogin'), 10, 2);
                        add_action('wp_enqueue_scripts', array($popup, 'addPopupScripts'));
                        add_action('wp_footer', array($popup, 'printPopup'));
                    }
                }
                add_filter('script_loader_tag', array($cart, 'addCloudFlareAttrScript'), 10, 3);
                //add_filter('clean_url', array($cart, 'uncleanUrl'), 10, 3);
                //Sync the order by the scheduled events
                add_action('retainful_sync_abandoned_cart_order', array($checkout, 'syncOrderByScheduler'), 1);
                add_action('wp_ajax_rnoc_track_user_data', array($cart, 'setCustomerData'));
                add_action('wp_ajax_nopriv_rnoc_track_user_data', array($cart, 'setCustomerData'));
                add_action('wp_ajax_rnoc_ajax_get_encrypted_cart', array($cart, 'ajaxGetEncryptedCart'));
                add_action('wp_ajax_nopriv_rnoc_ajax_get_encrypted_cart', array($cart, 'ajaxGetEncryptedCart'));
                add_action('woocommerce_cart_loaded_from_session', array($cart, 'handlePersistentCart'));
                //add_action('wp_login', array($cart, 'userLoggedIn'));
                add_action('woocommerce_api_retainful', array($cart, 'recoverUserCart'));
                add_action('wp_loaded', array($cart, 'applyAbandonedCartCoupon'));
                add_action('woocommerce_removed_coupon', array($cart, 'removeNextOrderCouponFromCart'));
                //Add tracking message
                /*if (is_user_logged_in()) {
                    add_action('woocommerce_after_add_to_cart_button', array($cart, 'userGdprMessage'), 10);
                    add_action('woocommerce_before_shop_loop', array($cart, 'userGdprMessage'), 10);
                }*/
                add_filter('woocommerce_checkout_fields', array($cart, 'guestGdprMessage'), 10, 1);
                add_action('woocommerce_checkout_after_terms_and_conditions', array($cart, 'guestTermGdprMessage'));
                add_action('wp_footer', array($checkout, 'setRetainfulOrderData'));
                add_filter('rnoc_can_track_abandoned_carts', array($cart, 'isZeroValueCart'), 15, 2);
                $cart_tracking_engine = self::$rnoc_settings->getCartTrackingEngine();
                if ($cart_tracking_engine == "php") {
                    //PHP tracking
                    add_action('woocommerce_after_calculate_totals', array($cart, 'syncCartData'));
                } else {
                    //Js tracking
                    add_action('wp_footer', array($cart, 'renderAbandonedCartTrackingDiv'));
                    add_filter('woocommerce_add_to_cart_fragments', array($cart, 'addToCartFragments'));
                }
                add_action('wp_footer', array($cart, 'printRefreshFragmentScript'));
                add_action('wp_enqueue_scripts', array($cart, 'addCartTrackingScripts'));
                add_action('wp_authenticate', array($cart, 'userLoggedOn'));
                add_action('user_register', array($cart, 'userSignedUp'));
                add_action('wp_logout', array($cart, 'userLoggedOut'));
                //Set order as recovered
                // handle payment complete, from a direct gateway
                //add_action('woocommerce_new_order', array($checkout, 'purchaseComplete'));
                add_action('woocommerce_thankyou', array($checkout, 'payPageOrderCompletion'));
                add_action('woocommerce_payment_complete', array($checkout, 'paymentCompleted'));
                add_action('woocommerce_checkout_update_order_meta', array($checkout, 'checkoutOrderProcessed'));
                add_action('woocommerce_store_api_checkout_update_order_meta', array($checkout, 'apiCheckoutOrderProcessed'));
                //      add_filter('woocommerce_payment_successful_result', array($checkout, 'maybeUpdateOrderOnSuccessfulPayment'), 10, 2);
                // handle updating Retainful order data after a successful payment, for certain gateways
                add_action('woocommerce_order_status_changed', array($checkout, 'orderStatusChanged'), 15, 3);
                // handle placed orders
                add_action('woocommerce_order_status_changed', array($checkout, 'orderUpdated'), 11, 1);
                //triggers when admin pdate the order
                add_action('woocommerce_process_shop_order_meta', array($checkout, 'OrderUpdatedShopBackend'), 50, 2);

                //add_action('woocommerce_update_order', array($checkout, 'orderUpdated'), 10, 1);
                add_filter('woocommerce_webhook_http_args', array($checkout, 'changeWebHookHeader'), 10, 3);
                //Todo: multi currency and multi lingual
                //add_action('wp_login', array(self::abandoned_cart_api, 'userCartUpdated'));
                if (self::$rnoc_settings->isAfterPayEnabled()) {
                    new AfterPay();
                }

            } else {
                if (is_admin()) {
                    $connect_txt = (!empty($secret_key) && !empty($app_id)) ? __('connect', RNOC_TEXT_DOMAIN) : __('re-connect', RNOC_TEXT_DOMAIN);
                    $notice = '<p>' . sprintf(__("Please <a href='" . admin_url('admin.php?page=retainful_license') . "'>%s</a> with Retainful to track and manage abandoned carts. ", RNOC_TEXT_DOMAIN), $connect_txt) . '</p>';
                    self::$rnoc_function::showAdminNotice($notice);
                }
            }
        } else {
            //remove
        }
        //Premium check
        add_action('rnocp_check_user_plan', array(self::$rnoc_function, 'checkUserPlan'));
        do_action('rnoc_initiated');
    }

    public static function addAdminHooks()
    {
        //Deactivation survey form
        add_action('admin_init', array(self::$rnoc_settings, 'setupSurveyForm'), 10);
        $coupon_api = new CouponManagement();
        add_filter('views_edit-shop_coupon', array($coupon_api, 'viewsEditShopCoupon'));
        add_action('manage_posts_extra_tablenav', array($coupon_api, 'showDeleteButton'));
        add_filter('woocommerce_coupon_options', array($coupon_api, 'showCouponOrderDetails'));
        add_filter('request', array($coupon_api, 'requestQuery'));
        add_action('admin_menu', array(self::$rnoc_settings, 'registerMenu'));
        add_action('admin_enqueue_scripts', array(self::$rnoc_settings, 'initAdminPageStyles'));
        //Validate key
        add_action('wp_ajax_validate_app_key', array(self::$rnoc_settings, 'validateAppKey'));
        add_action('wp_ajax_rnoc_get_search_coupon', array(self::$rnoc_settings, 'getSearchedCoupons'));
        add_action('wp_ajax_rnoc_disconnect_license', array(self::$rnoc_settings, 'disconnectLicense'));
        add_action('wp_ajax_rnoc_save_settings', array(self::$rnoc_settings, 'saveAcSettings'));
        //add_filter('wp_ajax_rnoc_create_order_update_webhook',array(self::$rnoc_settings,'saveNewWebhook'),10);
        add_action('wp_ajax_rnoc_save_noc_settings', array(self::$rnoc_settings, 'saveNocSettings'));
        add_action('wp_ajax_rnoc_save_premium_addon_settings', array(self::$rnoc_settings, 'savePremiumAddOnSettings'));
        add_action('wp_ajax_rnoc_delete_expired_coupons', array(self::$rnoc_settings, 'deleteUnusedExpiredCoupons'));
        //Settings link
        add_filter('plugin_action_links_' . RNOC_BASE_FILE, array(self::$rnoc, 'pluginActionLinks'));
        if (apply_filters('rnoc_show_order_token_in_order', false)) {
            add_action('add_meta_boxes', array(self::$rnoc_settings, 'addOrderDetailMetaBoxes'), 20);
        }
        $is_retainful_v2_0_1_migration_completed = get_option('is_retainful_v2_0_1_migration_completed', 0);
        if (!$is_retainful_v2_0_1_migration_completed) {
            self::$rnoc_function->migrationV201();
        }
        self::$rnoc_function->checkApi();
    }
}