<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\Cart;
use Rnoc\Retainful\Api\AbandonedCart\Checkout;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;
use Rnoc\Retainful\Integrations\Currency;
use Rnoc\Retainful\Integrations\MultiLingual;

class Main
{
    public static $init;
    public $rnoc, $admin, $abandoned_cart, $abandoned_cart_api;

    /**
     * Main constructor.
     */
    function __construct()
    {
        $this->rnoc = ($this->rnoc == NULL) ? new OrderCoupon() : $this->rnoc;
        $this->admin = ($this->admin == NULL) ? new Settings() : $this->admin;
        $this->abandoned_cart = ($this->abandoned_cart == NULL) ? new AbandonedCart() : $this->abandoned_cart;
        //$this->abandoned_cart_api = ($this->abandoned_cart_api == NULL) ? new RestApi() : $this->abandoned_cart_api;
        add_action('init', array($this, 'activateEvents'));
        add_action('woocommerce_init', array($this, 'includePluginFiles'));
        if (!$this->admin->isPremiumPluginActive()) {
            //init the retainful premium
            new \Rnoc\Retainful\Premium\RetainfulPremiumMain();
        }
    }

    function includePluginFiles()
    {
        $woocommerce_functions = new WcFunctions();
        $woocommerce_functions->initWoocommerceSession();
    }

    function addDataStores($stores)
    {
        if (!isset($stores['customer-retainful-abandoned-carts'])) {
            $stores['customer-retainful-abandoned-carts'] = 'WC_Customer_Data_Retainful_Store_Session';
        }
        return $stores;
    }

    /**
     * Register all the required end points
     */
    function registerEndPoints()
    {
        //Register custom endpoint for API
        register_rest_route('retainful-api/v1', '/verify', array(
            'methods' => 'POST',
            'callback' => array($this, 'verifyAppId')
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
        //Deactivation survey form
        if (is_admin()) {
            add_action('admin_init', array($this->admin, 'setupSurveyForm'), 10);
        }
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
        //Activate CMB2 functions
        $this->rnoc->init();
        new Currency();
        if ($this->admin->isNextOrderCouponEnabled()) {
            //Get events
            add_action('woocommerce_checkout_update_order_meta', array($this->rnoc, 'createNewCoupon'), 10, 2);
            add_action('woocommerce_order_status_changed', array($this->rnoc, 'onAfterPayment'), 10, 1);
            add_action('woocommerce_get_shop_coupon_data', array($this->rnoc, 'addVirtualCoupon'), 10, 2);
            add_action('rnoc_initiated', array($this->rnoc, 'setCouponToSession'));
            add_action('wp_loaded', array($this->rnoc, 'addCouponToCheckout'), 10);
            //Attach coupon to email
            $hook = $this->admin->couponMessageHook();
            if (!empty($hook))
                add_action($hook, array($this->rnoc, 'attachOrderCoupon'), 10, 4);
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
        //Validate key
        add_action('wp_ajax_validate_app_key', array($this->rnoc, 'validateAppKey'));
        //Settings link
        add_filter('plugin_action_links_' . RNOC_BASE_FILE, array($this->rnoc, 'pluginActionLinks'));
        $run_installation_externally = $this->admin->runAbandonedCartExternally();
        if ($run_installation_externally) {
            //If the user is old user then ask user to run abandoned cart to
            $is_app_connected = $this->admin->isAppConnected();
            $secret_key = $this->admin->getSecretKey();
            $app_id = $this->admin->getApiKey();
            if ($is_app_connected && !empty($secret_key) && !empty($app_id)) {
                add_action('wp_loaded', array($this->admin, 'schedulePlanChecker'));
                /*
                * Retainful abandoned cart api
                */
                $cart = new Cart();
                $checkout = new Checkout();
                add_filter('script_loader_src', array($cart, 'addCloudFlareAttrScript'), 10, 2);
                add_filter('clean_url', array($cart, 'uncleanUrl'), 10, 3);
                //Sync the order by the scheduled events
                add_action('retainful_sync_abandoned_cart_order', array($checkout, 'syncOrderByScheduler'), 1);
                add_action('wp_ajax_rnoc_track_user_data', array($cart, 'setCustomerData'));
                add_action('wp_ajax_nopriv_rnoc_track_user_data', array($cart, 'setCustomerData'));
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
                add_filter('rnoc_can_track_abandoned_carts', array($cart, 'isZeroValueCart'), 15);
                $cart_tracking_engine = $this->admin->getCartTrackingEngine();
                if ($cart_tracking_engine == "php") {
                    //PHP tracking
                    add_action('woocommerce_after_calculate_totals', array($cart, 'syncCartData'));
                } else {
                    //Js tracking
                    add_action('wp_footer', array($cart, 'renderAbandonedCartTrackingDiv'));
                    add_filter('woocommerce_add_to_cart_fragments', array($cart, 'addToCartFragments'));
                }
                add_action('wp_enqueue_scripts', array($cart, 'addCartTrackingScripts'));
                add_action('wp_authenticate', array($cart, 'userLoggedOn'));
                add_action('user_register', array($cart, 'userSignedUp'));
                add_action('wp_logout', array($cart, 'userLoggedOut'));
                //Set order as recovered
                // handle payment complete, from a direct gateway
                //add_action('woocommerce_new_order', array($checkout, 'purchaseComplete'));
                add_filter('woocommerce_thankyou', array($checkout, 'payPageOrderCompletion'));
                add_action('woocommerce_payment_complete', array($checkout, 'paymentCompleted'));
                add_action('woocommerce_checkout_order_processed', array($checkout, 'checkoutOrderProcessed'));
                add_filter('woocommerce_payment_successful_result', array($checkout, 'maybeUpdateOrderOnSuccessfulPayment'), 10, 2);
                // handle updating Retainful order data after a successful payment, for certain gateways
                add_action('woocommerce_order_status_changed', array($checkout, 'orderStatusChanged'), 15, 3);
                // handle placed orders
                add_action('woocommerce_order_status_changed', array($checkout, 'orderUpdated'), 10, 1);
                add_action('woocommerce_update_order', array($checkout, 'orderUpdated'), 10, 1);
                //Todo: multi currency and multi lingual
                #add_action('wp_login', array($this->abandoned_cart_api, 'userCartUpdated'));
            } else {
                $connect_txt = (!empty($secret_key) && !empty($app_id)) ? __('connect', RNOC_TEXT_DOMAIN) : __('re-connect', RNOC_TEXT_DOMAIN);
                $notice = '<p>' . sprintf(__("Please <a href='" . admin_url('admin.php?page=retainful_license') . "'>%s</a> with Retainful to track and manage abandoned carts. ", RNOC_TEXT_DOMAIN), $connect_txt) . '</p>';
                $this->showAdminNotice($notice);
            }
        } else {
            /*
            * Retainful abandoned cart
            */
            $track_user_cart = apply_filters('rnoc_track_abandoned_carts', 1);
            //Track and log user cart
            if ($track_user_cart) {
                add_action('woocommerce_cart_updated', array($this->abandoned_cart, 'userCartUpdated'));
                add_filter('woocommerce_checkout_fields', array($this->abandoned_cart, 'checkoutViewed'));
                add_action('wp_authenticate', array($this->abandoned_cart, 'userLoggedOn'));
                add_action('user_register', array($this->abandoned_cart, 'userSignedUp'));
                //track guest user
                add_action('wp_footer', array($this->abandoned_cart, 'addTrackUserJs'));
                add_action('wp_ajax_nopriv_save_retainful_guest_data', array($this->abandoned_cart, 'saveGuestData'));
            }
            //recover user cart
            add_action('wp_loaded', array($this->abandoned_cart, 'recoverUserCart'), 99, 1);
            add_action('woocommerce_new_order', array($this->abandoned_cart, 'purchaseComplete'));
            //Add tracking message
            add_filter('woocommerce_checkout_fields', array($this->abandoned_cart, 'guestGdprMessage'), 10, 1);
            add_action('woocommerce_after_add_to_cart_button', array($this->abandoned_cart, 'userGdprMessage'), 10);
            add_action('woocommerce_before_shop_loop', array($this->abandoned_cart, 'userGdprMessage'), 10);
            //Add custom cron schedule events
            add_action('wp_loaded', array($this->admin, 'actionSchedulerHooks'));
            add_action('rnoc_abandoned_clear_abandoned_carts', array($this->abandoned_cart, 'clearAbandonedCarts'));
            add_action('rnoc_abandoned_cart_send_email', array($this->abandoned_cart, 'sendAbandonedCartEmails'));
            //add_action('wp_loaded', array($this->abandoned_cart, 'sendAbandonedCartEmails'));
            //Process abandoned cart after user place order
            add_action('woocommerce_order_status_pending_to_processing_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
            add_action('woocommerce_order_status_pending_to_completed_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
            add_action('woocommerce_order_status_pending_to_on-hold_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
            add_action('woocommerce_order_status_failed_to_processing_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
            add_action('woocommerce_order_status_failed_to_completed_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
            //Admin
            add_action('wp_ajax_view_abandoned_cart', array($this->abandoned_cart, 'viewAbandonedCart'));
            add_action('wp_ajax_remove_abandoned_cart', array($this->abandoned_cart, 'removeAbandonedCart'));
            add_action('wp_ajax_remove_abandoned_cart_multiple', array($this->abandoned_cart, 'removeAbandonedCartMultiple'));
            add_action('wp_ajax_empty_abandoned_cart_history', array($this->abandoned_cart, 'emptyTheAbandonedCartHistory'));
            add_action('wp_ajax_empty_email_queue', array($this->abandoned_cart, 'emptyTheQueueTable'));
            add_action('wp_ajax_rnoc_save_email_template', array($this->abandoned_cart, 'saveEmailTemplate'));
            add_action('wp_ajax_rnoc_activate_or_deactivate_template', array($this->abandoned_cart, 'changeTemplateStatus'));
            add_action('wp_ajax_rnoc_remove_template', array($this->abandoned_cart, 'removeTemplate'));
            add_action('wp_ajax_rnoc_send_sample_email', array($this->abandoned_cart, 'sendSampleEmail'));
            add_action('wp_ajax_rnoc_get_template_by_id', array($this->abandoned_cart, 'getEmailTemplate'));
            //Support for old users
            $is_abandoned_tables_created = get_option('retainful_abandoned_cart_table_created', 0);
            $is_abandoned_emails_tables_created = get_option('retainful_abandoned_emails_table_created', 0);
            $is_abandoned_emails_queue_tables_created = get_option('retainful_abandoned_email_queue_table_created', 0);
            if (!$is_abandoned_tables_created || !$is_abandoned_emails_tables_created || !$is_abandoned_emails_queue_tables_created) {
                $this->createRequiredTables();
            }
            $is_retainful_v1_2_0_migration_completed = get_option('is_retainful_v1_2_0_migration_completed', 0);
            if (!$is_retainful_v1_2_0_migration_completed) {
                $this->migrationV120();
            }
            $is_retainful_v1_2_3_migration_completed = get_option('is_retainful_v1_2_3_migration_completed', 0);
            if (!$is_retainful_v1_2_3_migration_completed) {
                $this->migrationV123();
            }
            $is_retainful_v1_2_5_migration_completed = get_option('is_retainful_v1_2__5_migration_completed', 0);
            if (!$is_retainful_v1_2_5_migration_completed) {
                $this->migrationV125();
            }
        }
        $is_retainful_v2_0_1_migration_completed = get_option('is_retainful_v2_0_1_migration_completed', 0);
        if (!$is_retainful_v2_0_1_migration_completed) {
            $this->migrationV201();
        }
        //Premium check
        add_action('rnocp_check_user_plan', array($this, 'checkUserPlan'));
        /**
         * Ip filtering
         */
        $settings = $this->admin->getAdminSettings();
        if (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter']) && isset($settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'])) {
            $ip = $settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'];
            $ip_filter = new IpFiltering($ip);
            add_filter('rnoc_can_track_abandoned_carts', array($ip_filter, 'trackAbandonedCart'), 10, 2);
        }
        do_action('rnoc_initiated');
        $this->checkApi();
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
     * Migration to v1.2.0
     */
    function migrationV120()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_cart_history';
        $query = "ALTER TABLE {$table_name} ADD COLUMN `currency_code` VARCHAR (255) DEFAULT NULL";
        $wpdb->query($query);
        update_option('is_retainful_v1_2_0_migration_completed', '1');
    }

    /**
     * Migration to v1.2.5
     */
    function migrationV125()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_templates';
        $query = "ALTER TABLE {$table_name} ADD COLUMN `extra` TEXT DEFAULT NULL";
        $wpdb->query($query);
        $this->modifyEmailTemplatesDate();
        $history_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_sent_history';
        $history_query = "ALTER TABLE {$history_table_name} ADD COLUMN `subject` TEXT DEFAULT NULL";
        $wpdb->query($history_query);
        update_option('is_retainful_v1_2__5_migration_completed', '1');
    }

    /**
     * Modify email templates send_after_time
     */
    function modifyEmailTemplatesDate()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_templates';
        $query = "ALTER TABLE {$table_name} ADD COLUMN `send_after_time` bigint(20) DEFAULT NULL";
        $wpdb->query($query);
        $query = "SELECT id,frequency,day_or_hour FROM {$table_name} ";
        $templates = $wpdb->get_results($query);
        if (!empty($templates)) {
            $hour_seconds = 3600;//60*60
            $day_seconds = 86400;
            foreach ($templates as $template) {
                $time = ($template->day_or_hour == "Days") ? $day_seconds : $hour_seconds;
                $time_to_send_template_after = $template->frequency * $time;
                $wpdb->update($table_name, array('send_after_time' => $time_to_send_template_after), array('id' => $template->id), array('%d', '%d'));
            }
        }
    }

    /**
     * Migration to v1.2.3
     */
    function migrationV123()
    {
        global $wpdb;
        $lang_helper = new MultiLingual();
        $default_language = $lang_helper->getDefaultLanguage();
        $history_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_cart_history';
        $emails_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_templates';
        $query = "ALTER TABLE {$history_table_name} ADD COLUMN `language_code` VARCHAR (255) DEFAULT NULL";
        $email_query = "ALTER TABLE {$emails_table_name} ADD COLUMN `language_code` VARCHAR (255) DEFAULT NULL";
        $update_default_language_query = "UPDATE `{$emails_table_name}` SET `language_code` = '{$default_language}' WHERE `language_code` IS NULL;";
        $wpdb->query($query);
        $wpdb->query($email_query);
        $wpdb->query($update_default_language_query);
        update_option('is_retainful_v1_2_3_migration_completed', '1');
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
            $this->admin->isApiEnabled($api_key, $secret_key);
        } else {
            $this->admin->updateUserAsFreeUser();
        }
        $this->admin->removeFinishedHooks('rnocp_check_user_plan', 'publish');
    }

    /**
     * Create required tabled needed for retainful abandoned cart
     */
    function createRequiredTables()
    {
        global $wpdb;
        $rnoc_collate = '';
        if ($wpdb->has_cap('collation')) {
            $rnoc_collate = $wpdb->get_charset_collate();
        }
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        //Create history table if table is not already exists
        $queue_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_email_queue';
        $queue_table_query = "CREATE TABLE IF NOT EXISTS $queue_table_name (
                            id int AUTO_INCREMENT, 
                            template_id int NOT NULL, 
                            cart_id int NOT NULL, 
                            is_completed int NOT NULL, 
                            run_at bigint(20) NOT NULL, 
                            extra_data longtext,
                            PRIMARY KEY (`id`)
                            ) $rnoc_collate";
        dbDelta($queue_table_query);
        $history_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_cart_history';
        $history_query = "CREATE TABLE IF NOT EXISTS $history_table_name (
                            id int AUTO_INCREMENT, 
                            customer_key char(32) NOT NULL, 
                            cart_contents longtext NOT NULL, 
                            cart_expiry bigint(20) NOT NULL, 
                            cart_is_recovered tinyint(1) NOT NULL, 
                            ip_address char(32), 
                            item_count int NOT NULL, 
                            order_id int,
                            viewed_checkout tinyint(1) NOT NULL DEFAULT 0,
                            show_on_funnel_report tinyint(1) NOT NULL DEFAULT 0,
                            cart_total decimal(15,2),
                            PRIMARY KEY (`id`)
                            ) $rnoc_collate";
        dbDelta($history_query);
        //Create history table for guest if table is not already exists
        $guest_history_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . "guest_abandoned_cart_history";
        $guest_history_query = "CREATE TABLE IF NOT EXISTS $guest_history_table_name (
                `id` int(15) NOT NULL AUTO_INCREMENT,
                `session_id` varchar(50),
                `billing_first_name` text,
                `billing_last_name` text,
                `billing_company_name` text,
                `billing_address_1` text,
                `billing_address_2` text,
                `billing_city` text,
                `billing_county` text,
                `billing_zipcode` text,
                `email_id` text,
                `phone` text,
                `ship_to_billing` text,
                `order_notes` text,
                `shipping_first_name` text,
                `shipping_last_name` text,
                `shipping_company_name` text,
                `shipping_address_1` text,
                `shipping_address_2` text,
                `shipping_city` text,
                `shipping_county` text,
                `shipping_zipcode` double,
                `shipping_charges` double,
                PRIMARY KEY (`id`)
                ) $rnoc_collate AUTO_INCREMENT=63000000";
        dbDelta($guest_history_query);
        $sent_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . "email_sent_history";
        $email_sent_query = "CREATE TABLE IF NOT EXISTS $sent_table_name (
                        `id` int(11) NOT NULL auto_increment,
                        `template_id` varchar(40) collate utf8_unicode_ci NOT NULL,
                        `abandoned_order_id` int(11) NOT NULL,
                        `sent_time` datetime NOT NULL,
                        `sent_email_id` text COLLATE utf8_unicode_ci NOT NULL,
                        PRIMARY KEY  (`id`)
                        ) $rnoc_collate AUTO_INCREMENT=1 ";
        dbDelta($email_sent_query);
        $email_templates_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . "email_templates";
        $email_templates_query = "CREATE TABLE $email_templates_table_name (
                                  `id` int(11) NOT NULL AUTO_INCREMENT,
                                  `subject` text NOT NULL,
                                  `body` mediumtext NOT NULL,
                                  `is_active` enum('0','1') NOT NULL,
                                  `template_name` text NOT NULL,
                                  `frequency` int(11) NOT NULL,
                                  `default_template` enum('0','1') NOT NULL,
                                  `day_or_hour` enum('Days','Hours') NOT NULL,
                                  PRIMARY KEY  (`id`)
                                ) $rnoc_collate AUTO_INCREMENT=1";
        dbDelta($email_templates_query);
        $this->insertDefaultEmailTemplate($email_templates_table_name);
        update_option('retainful_abandoned_emails_table_created', '1');
        update_option('retainful_abandoned_cart_table_created', '1');
        update_option('retainful_abandoned_email_queue_table_created', '1');
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
        $this->doMigration();
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