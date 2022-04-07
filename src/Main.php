<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\Cart;
use Rnoc\Retainful\Api\AbandonedCart\Checkout;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;
use Rnoc\Retainful\Api\NextOrderCoupon\CouponManagement;
use Rnoc\Retainful\Api\Referral\ReferralManagement;
use Rnoc\Retainful\Integrations\Currency;

class Main
{
    public static $init;
    public $rnoc, $admin, $abandoned_cart_api;

    /**
     * Main constructor.
     */
    function __construct()
    {
        $this->rnoc = ($this->rnoc == NULL) ? new OrderCoupon() : $this->rnoc;
        $this->admin = ($this->admin == NULL) ? new Settings() : $this->admin;
        add_action('init', array($this, 'activateEvents'));
        add_action('woocommerce_init', array($this, 'includePluginFiles'));
        //init the retainful premium
        new \Rnoc\Retainful\Premium\RetainfulPremiumMain();
    }

    function includePluginFiles()
    {
        $rnoc_varnish_check = $this->admin->getRetainfulSettingValue('rnoc_varnish_check', 'no');
        if ($rnoc_varnish_check === 'no') {
            $woocommerce_functions = new WcFunctions();
            $woocommerce_functions->initWoocommerceSession();
            do_action('rnoc_after_including_plugin_files', $woocommerce_functions, $this);
        }
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
        $entered_app_id = $this->admin->getApiKey();
        $entered_secret_key = $this->admin->getSecretKey();
        $is_app_connected = $this->admin->isAppConnected();
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
            'success' => ($response_code == 'INSTALLED_CONNECTED') ? true : false,
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
     * Check the woocommerce ac need to run externally
     * @param $need_ac_externally
     * @return bool|mixed|void
     */
    function needToRunAbandonedCartExternally($need_ac_externally)
    {
        $need_ac_externally = $this->admin->runAbandonedCartExternally();
        return $need_ac_externally;
    }

    /**
     * Activate the required events
     */
    function activateEvents()
    {
        //Register deactivation hook
        register_deactivation_hook(RNOC_FILE, array($this, 'onPluginDeactivation'));
        add_action('retainful_plugin_activated', array($this, 'createRequiredTables'));
        //add end points
        add_action('rest_api_init', array($this, 'registerEndPoints'));
        //Detect woocommerce plugin deactivation
        add_action('deactivated_plugin', array($this, 'detectPluginDeactivation'), 10, 2);
        //Check for dependencies
        add_action('plugins_loaded', array($this, 'checkDependencies'));
        add_action('rnocp_activation_trigger', array($this, 'checkUserPlan'));
        add_filter('rnoc_need_to_run_ac_in_cloud', array($this, 'needToRunAbandonedCartExternally'));
        if (is_admin()) {
            //Deactivation survey form
            add_action('admin_init', array($this->admin, 'setupSurveyForm'), 10);
            $coupon_api = new CouponManagement();
            add_filter('views_edit-shop_coupon', array($coupon_api, 'viewsEditShopCoupon'));
            add_action('manage_posts_extra_tablenav', array($coupon_api, 'showDeleteButton'));
            add_filter('woocommerce_coupon_options', array($coupon_api, 'showCouponOrderDetails'));
            add_filter('request', array($coupon_api, 'requestQuery'));
            add_action('admin_menu', array($this->admin, 'registerMenu'));
            $this->admin->initAdminPageStyles();
            //Validate key
            add_action('wp_ajax_validate_app_key', array($this->admin, 'validateAppKey'));
            add_action('wp_ajax_rnoc_get_search_coupon', array($this->admin, 'getSearchedCoupons'));
            add_action('wp_ajax_rnoc_disconnect_license', array($this->admin, 'disconnectLicense'));
            add_action('wp_ajax_rnoc_save_settings', array($this->admin, 'saveAcSettings'));
            add_action('wp_ajax_rnoc_save_noc_settings', array($this->admin, 'saveNocSettings'));
            add_action('wp_ajax_rnoc_save_premium_addon_settings', array($this->admin, 'savePremiumAddOnSettings'));
            add_action('wp_ajax_rnoc_delete_expired_coupons', array($this->admin, 'deleteUnusedExpiredCoupons'));
            //Settings link
            add_filter('plugin_action_links_' . RNOC_BASE_FILE, array($this->rnoc, 'pluginActionLinks'));
            if (apply_filters('rnoc_show_order_token_in_order', false)) {
                add_action('add_meta_boxes', array($this->admin, 'addOrderDetailMetaBoxes'), 20);
            }
        }
        //initialise currency helper
        new Currency();
        if ($this->admin->isNextOrderCouponEnabled()) {
            //Get events
            add_action('woocommerce_checkout_update_order_meta', array($this->rnoc, 'createNewCoupon'), 10, 2);
            add_action('woocommerce_order_status_changed', array($this->rnoc, 'onAfterPayment'), 10, 1);
            add_action('woocommerce_get_shop_coupon_data', array($this->rnoc, 'addVirtualCoupon'), 10, 2);
            add_action('rnoc_create_new_next_order_coupon', array($this->rnoc, 'createNewCoupon'), 10, 2);
            add_action('rnoc_initiated', array($this->rnoc, 'setCouponToSession'));
            add_action('wp_loaded', array($this->rnoc, 'addCouponToCheckout'), 10);
            //Attach coupon to email
            $hook = $this->admin->couponMessageHook();
            if (!empty($hook) && $hook != "none") {
                add_action($hook, array($this->rnoc, 'attachOrderCoupon'), 10, 4);
            }
            //add action for filter
            add_action('rnoc_show_order_coupon', array($this->rnoc, 'attachOrderCoupon'), 10, 4);
            //Sync the coupon details with retainful
            add_action('retainful_cron_sync_coupon_details', array($this->rnoc, 'cronSendCouponDetails'), 1);
            //Remove coupon code after placing order
            add_action('woocommerce_thankyou', array($this->rnoc, 'removeCouponFromSession'), 10, 1);
            // Show coupon in order thankyou page
            add_action('woocommerce_thankyou', array($this->rnoc, 'showCouponInThankYouPage'), 10, 1);
            //Remove Code from session
            add_action('woocommerce_removed_coupon', array($this->rnoc, 'removeCouponFromCart'));
            /*
             * Support for woocommerce email customizer
             */
            add_filter('woo_email_drag_and_drop_builder_retainful_settings_url', array($this->rnoc, 'wooEmailCustomizerRetainfulSettingsUrl'));
            //Tell Email customizes about handling coupons..
            add_filter('woo_email_drag_and_drop_builder_handling_retainful', '__return_true');
            //set coupon details for Email customizer
            add_filter('woo_email_drag_and_drop_builder_retainful_next_order_coupon_data', array($this->rnoc, 'wooEmailCustomizerRetainfulCouponContent'), 10, 3);
            //sent retainful additional short codes
            add_filter('woo_email_drag_and_drop_builder_load_additional_shortcode', array($this->rnoc, 'wooEmailCustomizerRegisterRetainfulShortCodes'), 10);
            add_filter('woo_email_drag_and_drop_builder_load_additional_shortcode_data', array($this->rnoc, 'wooEmailCustomizerRetainfulShortCodesValues'), 10, 3);
            add_filter('wp_footer', array($this->rnoc, 'showAppliedCouponPopup'));
        }
        /**
         * Ip filtering
         */
        $this->canActivateIPFilter();
        $run_installation_externally = $this->admin->runAbandonedCartExternally();
        if ($run_installation_externally) {
            //If the user is old user then ask user to run abandoned cart to
            $is_app_connected = $this->admin->isAppConnected();
            $secret_key = $this->admin->getSecretKey();
            $app_id = $this->admin->getApiKey();
            if ($is_app_connected && !empty($secret_key) && !empty($app_id)) {
                if (is_admin()) {
                    add_action('wp_after_admin_bar_render', array($this->admin, 'schedulePlanChecker'));
                }
                /*
                * Retainful abandoned cart api
                */
                $cart = new Cart();
                $checkout = new Checkout();
                $need_referral_program = $this->admin->needReferralWidget();
                $need_popup_widget = $this->admin->needPopupWidget();
                if ($this->admin->isProPlan() && ($need_referral_program || $need_popup_widget)) {
                    $referral_program = new ReferralManagement();
                    add_action('wp_footer', array($referral_program, 'printReferralPopup'));
                    $need_embeded_referral_program = $this->admin->needEmbededReferralWidget();
                    if ($need_embeded_referral_program) {
                        add_action('woocommerce_account_dashboard', array($referral_program, 'printEmbededReferralPopup'));
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
                //Add tracking message
                if (is_user_logged_in()) {
                    add_action('woocommerce_after_add_to_cart_button', array($cart, 'userGdprMessage'), 10);
                    add_action('woocommerce_before_shop_loop', array($cart, 'userGdprMessage'), 10);
                }
                add_filter('woocommerce_checkout_fields', array($cart, 'guestGdprMessage'), 10, 1);
                add_action('wp_footer', array($checkout, 'setRetainfulOrderData'));
                add_filter('rnoc_can_track_abandoned_carts', array($cart, 'isZeroValueCart'), 15, 2);
                $cart_tracking_engine = $this->admin->getCartTrackingEngine();
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
                add_action('woocommerce_checkout_order_processed', array($checkout, 'checkoutOrderProcessed'));
                add_filter('woocommerce_payment_successful_result', array($checkout, 'maybeUpdateOrderOnSuccessfulPayment'), 10, 2);
                // handle updating Retainful order data after a successful payment, for certain gateways
                add_action('woocommerce_order_status_changed', array($checkout, 'orderStatusChanged'), 15, 3);
                // handle placed orders
                add_action('woocommerce_order_status_changed', array($checkout, 'orderUpdated'), 11, 1);
                add_action('woocommerce_update_order', array($checkout, 'orderUpdated'), 10, 1);
                //Todo: multi currency and multi lingual
                #add_action('wp_login', array($this->abandoned_cart_api, 'userCartUpdated'));
            } else {
                if (is_admin()) {
                    $connect_txt = (!empty($secret_key) && !empty($app_id)) ? __('connect', RNOC_TEXT_DOMAIN) : __('re-connect', RNOC_TEXT_DOMAIN);
                    $notice = '<p>' . sprintf(__("Please <a href='" . admin_url('admin.php?page=retainful_license') . "'>%s</a> with Retainful to track and manage abandoned carts. ", RNOC_TEXT_DOMAIN), $connect_txt) . '</p>';
                    $this->showAdminNotice($notice);
                }
            }
        } else {
            //remove
        }
        //Premium check
        add_action('rnocp_check_user_plan', array($this, 'checkUserPlan'));
        do_action('rnoc_initiated');
        if (is_admin()) {
            $is_retainful_v2_0_1_migration_completed = get_option('is_retainful_v2_0_1_migration_completed', 0);
            if (!$is_retainful_v2_0_1_migration_completed) {
                $this->migrationV201();
            }
            $this->checkApi();
        }
    }

    function canActivateIPFilter()
    {
        $settings = $this->admin->getAdminSettings();
        if (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter']) && isset($settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'])) {
            $ip = $settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'];
            if (!empty($ip)) {
                $ip_filter = new IpFiltering($ip);
                add_filter('rnoc_is_cart_has_valid_ip', array($ip_filter, 'trackAbandonedCart'), 10, 2);
            }
        }
    }

    /**
     * Migration for 2.1.0
     */
    function migrationV201()
    {
        $premium_settings = get_option($this->admin->slug . '_premium');
        $admin_settings = $this->admin->getAdminSettings();
        $admin_settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] = isset($premium_settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter']) ? $premium_settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] : 0;
        $admin_settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'] = isset($premium_settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses']) ? $premium_settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'] : '';
        update_option($this->admin->slug . '_settings', $admin_settings);
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
        $this->admin->removeFinishedHooks('rnoc_abandoned_clear_abandoned_carts');
        $this->admin->removeFinishedHooks('rnoc_abandoned_cart_send_email');
        $this->admin->removeFinishedHooks('rnocp_check_user_plan');
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
        $api_key = $this->admin->getApiKey();
        $secret_key = $this->admin->getSecretKey();
        if (!empty($api_key) && !empty($secret_key)) {
            $api_obj = new RestApi();
            $store_data = array(
                'secret_key' => $api_obj->encryptData($api_key, $secret_key));
            $this->admin->isApiEnabled($api_key, $secret_key,$store_data);
        } else {
            $this->admin->updateUserAsFreeUser();
        }
        $this->admin->removeFinishedHooks('rnocp_check_user_plan', 'publish');
    }

    /**
     * Insert default email template
     * @param $table
     */
    function insertDefaultEmailTemplate($table)
    {
        ob_start();
        include(RNOC_PLUGIN_PATH . 'src/admin/templates/default-1.html');
        $content = ob_get_clean();
        $email_body = addslashes($content);
        ob_start();
        include(RNOC_PLUGIN_PATH . 'src/admin/templates/default-2.html');
        $content1 = ob_get_clean();
        $email_body1 = addslashes($content1);
        ob_start();
        include(RNOC_PLUGIN_PATH . 'src/admin/templates/default-3.html');
        $content2 = ob_get_clean();
        $email_body2 = addslashes($content2);
        global $wpdb;
        $default_template = $wpdb->get_row('SELECT id FROM ' . $table . ' WHERE default_template = "1"');
        if (empty($default_template)) {
            $template_subject = "Hey {{customer_name}}!! You left something in your cart";
            $query = 'INSERT INTO `' . $table . '` ( subject, body, is_active, frequency, day_or_hour, default_template,template_name )VALUES ( "' . $template_subject . '","' . $email_body . '","1","1","Hours","1","initial"),( "' . $template_subject . '","' . $email_body1 . '","0","1","Hours","6","After 6 hours"),( "' . $template_subject . '","' . $email_body2 . '","0","1","Days","1","After 1 day")';
            $wpdb->query($query);
        }
    }

    /**
     * Initiate the plugin
     * @return Main
     */
    public static function instance()
    {
        return self::$init = (self::$init == NULL) ? new self() : self::$init;
    }

    function removeDependentTables()
    {
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
     * detect woocommerce have been deactivated
     * @param $plugin
     * @param $network_activation
     */
    function detectPluginDeactivation($plugin, $network_activation)
    {
        if (in_array($plugin, array('woocommerce/woocommerce.php'))) {
            deactivate_plugins(plugin_basename(__FILE__));
            //Todo - Deactivate this plugin
        }
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
        if (is_admin()) {
            $this->doMigration();
        }
    }

    /**
     * Migrate data required for v 1.1.3
     */
    function doMigration()
    {
        $is_migrated = get_option('retainful_v_1_1_3_migration_completed', 0);
        if (!$is_migrated) {
            $slug = $this->admin->slug;
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