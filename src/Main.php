<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Admin\Settings;

class Main
{
    public static $init;
    public $rnoc, $admin;

    /**
     * Initiate the plugin
     * @return Main
     */
    public static function instance()
    {
        return self::$init = (self::$init == NULL) ? new self() : self::$init;
    }

    /**
     * Main constructor.
     */
    function __construct()
    {
        $this->rnoc = ($this->rnoc == NULL) ? new OrderCoupon() : $this->rnoc;
        $this->admin = ($this->admin == NULL) ? new Settings() : $this->admin;
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
        //Get events
        add_action('woocommerce_checkout_update_order_meta', array($this->rnoc, 'createNewCoupon'), 10, 2);
        add_action('woocommerce_payment_complete', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array($this->rnoc, 'onAfterPayment'), 10, 1);

        add_action('woocommerce_get_shop_coupon_data', array($this->rnoc, 'addVirtualCoupon'), 10, 2);
        add_action('wp_head', array($this->rnoc, 'setCouponToSession'));
        add_action('woocommerce_cart_loaded_from_session', array($this->rnoc, 'addCouponToCheckout'), 10);

        //Attach coupon to email
        $hook = $this->admin->couponMessageHook();
        if (!empty($hook))
            add_action($hook, array($this->rnoc, 'attachOrderCoupon'), 10, 4);

        //Validate key
        add_action('wp_ajax_validateAppKey', array($this->rnoc, 'validateAppKey'));
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
        add_action('woocommerce_cart_updated', array(&$this, 'wcal_store_cart_timestamp'));

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
        //Create history table if table is not already exists
        $history_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_cart_history';
        $history_query = "CREATE TABLE IF NOT EXISTS $history_table_name (
                             `id` int(11) NOT NULL AUTO_INCREMENT,
                             `user_id` int(11) NOT NULL,
                             `abandoned_cart_info` text COLLATE utf8_unicode_ci NOT NULL,
                             `abandoned_cart_time` int(11) NOT NULL,
                             `cart_ignored` enum('0','1') COLLATE utf8_unicode_ci NOT NULL,
                             `recovered_cart` int(11) NOT NULL,
                             `user_type` text,
                             `unsubscribe_link` enum('0','1') COLLATE utf8_unicode_ci NOT NULL,
                             `session_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
                             PRIMARY KEY (`id`)
                             ) $rnoc_collate";
        $wpdb->query($history_query);
        //Create history table for guest if table is not already exists
        $guest_history_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . "guest_abandoned_cart_history";
        $guest_history_query = "CREATE TABLE IF NOT EXISTS $guest_history_table_name (
                `id` int(15) NOT NULL AUTO_INCREMENT,
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
        $wpdb->query($guest_history_query);
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