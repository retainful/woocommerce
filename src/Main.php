<?php

namespace Rnoc\Retainful;

if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Integrations\Currency;

class Main
{
    public static $init;
    public $rnoc, $admin, $abandoned_cart;

    /**
     * Main constructor.
     */
    function __construct()
    {
        $this->rnoc = ($this->rnoc == NULL) ? new OrderCoupon() : $this->rnoc;
        $this->admin = ($this->admin == NULL) ? new Settings() : $this->admin;
        $this->abandoned_cart = ($this->abandoned_cart == NULL) ? new AbandonedCart() : $this->abandoned_cart;
        $this->activateEvents();
    }

    /**
     * Activate the required events
     */
    function activateEvents()
    {
        //Create and alter the tables for abandoned carts and also check for woocommerce installed
        register_activation_hook(RNOC_FILE, array($this, 'validatePluginActivation'));
        //Detect woocommerce plugin deactivation
        add_action('deactivated_plugin', array($this, 'detectPluginDeactivation'), 10, 2);
        //Check for dependencies
        add_action('plugins_loaded', array($this, 'checkDependencies'));
        //Activate CMB2 functions
        add_action('init', function () {
            $this->rnoc->init();
        });
        new Currency();
        do_action('rnoc_initiated');
        //Get events
        add_action('woocommerce_checkout_update_order_meta', array($this->rnoc, 'createNewCoupon'), 10, 2);
        add_action('woocommerce_payment_complete', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_get_shop_coupon_data', array($this->rnoc, 'addVirtualCoupon'), 10, 2);
        add_action('woocommerce_init', array($this->rnoc, 'setCouponToSession'));
        add_action('woocommerce_cart_loaded_from_session', array($this->rnoc, 'addCouponToCheckout'), 10);
        //Attach coupon to email
        $hook = $this->admin->couponMessageHook();
        if (!empty($hook))
            add_action($hook, array($this->rnoc, 'attachOrderCoupon'), 10, 4);
        //Validate key
        add_action('wp_ajax_validate_app_key', array($this->rnoc, 'validateAppKey'));
        //Settings link
        add_filter('plugin_action_links_' . RNOC_BASE_FILE, array($this->rnoc, 'pluginActionLinks'));
        //Sync the coupon details with retainful
        add_action('retainful_cron_sync_coupon_details', array($this->rnoc, 'cronSendCouponDetails'), 1);
        //Remove coupon code after placing order
        add_action('woocommerce_thankyou', array($this->rnoc, 'removeCouponFromSession'), 10, 1);
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
        /*
         * Retainful abandoned cart
         */
        //Track and log user cart
        add_action('woocommerce_cart_updated', array($this->abandoned_cart, 'userCartUpdated'));
        add_filter('woocommerce_checkout_fields', array($this->abandoned_cart, 'checkoutViewed'));
        add_action('wp_authenticate', array($this->abandoned_cart, 'userLoggedOn'));
        add_action('user_register', array($this->abandoned_cart, 'userSignedUp'));
        //track guest user
        add_action('woocommerce_after_checkout_billing_form', array($this->abandoned_cart, 'addTrackUserJs'));
        add_action('wp_ajax_nopriv_save_retainful_guest_data', array($this->abandoned_cart, 'saveGuestData'));
        //recover user cart
        add_filter('template_include', array($this->abandoned_cart, 'recoverUserCart'), 99, 1);
        add_action('woocommerce_new_order', array($this->abandoned_cart, 'purchaseComplete'));
        //Add tracking message
        add_filter('woocommerce_checkout_fields', array($this->abandoned_cart, 'guestGdprMessage'), 10, 1);
        add_action('woocommerce_after_add_to_cart_button', array($this->abandoned_cart, 'userGdprMessage'), 10);
        add_action('woocommerce_before_shop_loop', array($this->abandoned_cart, 'userGdprMessage'), 10);
        //Add custom cron schedule events
        add_action('plugins_loaded', array($this, 'actionSchedulerHooks'));
        add_action('rnoc_abandoned_clear_abandoned_carts', array($this->abandoned_cart, 'clearAbandonedCarts'));
        add_action('rnoc_abandoned_cart_send_email', array($this->abandoned_cart, 'sendAbandonedCartEmails'));
        //add_action('woocommerce_init', array($this->abandoned_cart, 'sendAbandonedCartEmails'));
        //Process abandoned cart after user place order
        add_action('woocommerce_order_status_pending_to_processing_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
        add_action('woocommerce_order_status_pending_to_completed_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
        add_action('woocommerce_order_status_pending_to_on-hold_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
        add_action('woocommerce_order_status_failed_to_processing_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
        add_action('woocommerce_order_status_failed_to_completed_notification', array($this->abandoned_cart, 'notifyAdminOnRecovery'));
        //Admin
        add_action('wp_ajax_view_abandoned_cart', array($this->abandoned_cart, 'viewAbandonedCart'));
        add_action('wp_ajax_remove_abandoned_cart', array($this->abandoned_cart, 'removeAbandonedCart'));
        add_action('wp_ajax_rnoc_save_email_template', array($this->abandoned_cart, 'saveEmailTemplate'));
        add_action('wp_ajax_rnoc_activate_or_deactivate_template', array($this->abandoned_cart, 'changeTemplateStatus'));
        add_action('wp_ajax_rnoc_remove_template', array($this->abandoned_cart, 'removeTemplate'));
        add_action('wp_ajax_rnoc_send_sample_email', array($this->abandoned_cart, 'sendSampleEmail'));
        add_action('wp_ajax_rnoc_get_template_by_id', array($this->abandoned_cart, 'getEmailTemplate'));
        $is_abandoned_tables_created = get_option('retainful_abandoned_cart_table_created', 0);
        $is_abandoned_emails_tables_created = get_option('retainful_abandoned_emails_table_created', 0);
        if (!$is_abandoned_tables_created || !$is_abandoned_emails_tables_created) {
            $this->createRequiredTables();
        }
        $is_retainful_v1_2_0_migration_completed = get_option('is_retainful_v1_2_0_migration_completed', 0);
        if (!$is_retainful_v1_2_0_migration_completed) {
            $this->migrationV120();
        }
        //Premium check
        add_action('rnocp_check_user_plan', array($this, 'checkUserPlan'));
        $this->checkApi();
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
     * Schedule the action scheduler hooks
     */
    function actionSchedulerHooks()
    {
        if (function_exists('as_next_scheduled_action')) {
            if (!as_next_scheduled_action('rnoc_abandoned_clear_abandoned_carts')) {
                as_schedule_recurring_action(time(), 86400, 'rnoc_abandoned_clear_abandoned_carts');
            }
            if (!as_next_scheduled_action('rnocp_check_user_plan')) {
                as_schedule_recurring_action(time(), 604800, 'rnocp_check_user_plan');
            }
            if (!as_next_scheduled_action('rnoc_abandoned_cart_send_email')) {
                as_schedule_recurring_action(time(), 300, 'rnoc_abandoned_cart_send_email');
            }
        }
    }

    /**
     * Check and update the user plan
     */
    function checkUserPlan()
    {
        $api_key = $this->admin->getApiKey();
        if (!empty($api_key)) {
            $this->admin->isApiEnabled($api_key);
            $this->removeFinishedHooks();
        }
    }

    /**
     * Remove all hooks and schedule once
     * @return bool
     */
    function removeFinishedHooks()
    {
        global $wpdb;
        $res = true;
        $scheduled_actions = $wpdb->get_results("SELECT ID from `" . $wpdb->prefix . "posts` where post_title like '%rnocp_check_user_plan%' AND post_status like 'publish' AND  post_type='scheduled-action'");
        if (!empty($scheduled_actions)) {
            foreach ($scheduled_actions as $action) {
                if (!wp_delete_post($action->ID, true)) {
                    $res = false;
                }
            }
        }
        return $res;
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
        global $wpdb;
        $default_template = $wpdb->get_row('SELECT id FROM ' . $table . ' WHERE default_template = "1"');
        if (empty($default_template)) {
            $template_subject = "Hey {{customer_name}}!! You left something in your cart";
            $query = 'INSERT INTO `' . $table . '` ( subject, body, is_active, frequency, day_or_hour, default_template,template_name )VALUES ( "' . $template_subject . '","' . $email_body . '","1","1","Hours","1","initial")';
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

    /**
     * Check and abort if PHP version is is less them 5.6 and does not met the required woocommerce version
     */
    function validatePluginActivation()
    {
        if (version_compare(phpversion(), '5.6', '<')) {
            exit(__('Retainful-woocommerce requires minimum PHP version of 5.6', RNOC_TEXT_DOMAIN));
        }
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            exit(__('Woocommerce must installed and activated in-order to use Retainful-Woocommerce!', RNOC_TEXT_DOMAIN));
        } else {
            if (!function_exists('get_plugins'))
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            $plugin_folder = get_plugins('/' . 'woocommerce');
            $plugin_file = 'woocommerce.php';
            $wc_installed_version = NULL;
            $wc_required_version = '2.5';
            if (isset($plugin_folder[$plugin_file]['Version'])) {
                $wc_installed_version = $plugin_folder[$plugin_file]['Version'];
            }
            if (version_compare($wc_required_version, $wc_installed_version, '>=')) {
                exit(__('Retainful-woocommerce requires minimum Woocommerce version of ', RNOC_TEXT_DOMAIN) . ' ' . $wc_required_version . '. ' . __('But your Woocommerce version is ', RNOC_TEXT_DOMAIN) . ' ' . $wc_installed_version);
            }
        }
        //Create abandoned cart related tables
        $this->createRequiredTables();
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
                    RNOC_PLUGIN_PREFIX . 'retainful_app_id' => (isset($retainful_page[RNOC_PLUGIN_PREFIX . 'retainful_app_id'])) ? $retainful_page[RNOC_PLUGIN_PREFIX . 'retainful_app_id'] : ''
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