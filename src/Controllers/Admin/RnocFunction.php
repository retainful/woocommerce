<?php

namespace Rnoc\Retainful\Controllers\Admin;

defined('ABSPATH') || exit; // Exit if accessed directly

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;
use Rnoc\Retainful\Api\Imports\Imports;
use Rnoc\Retainful\Helpers\WcFunctions;
use Rnoc\Retainful\IpFiltering;


class RnocFunction
{
    private static $rnoc_settings;

    function __construct()
    {
        self::$rnoc_settings = empty($rnoc_settings) ? new Settings() : self::$rnoc_settings;
    }

    /**
     * Show notices for user..if anything unusually happen in our plugin
     * @param string $message - message to notice users
     */
    public static function showAdminNotice($message = "")
    {
        if (!empty($message)) {
            add_action('admin_notices', function () use ($message) {
                echo '<div class="error notice"><p>' . $message . '</p></div>';
            });
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

    function registerSyncEndPoints()
    {
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
     * Change identity path.
     *
     * @param $option
     * @param $name
     * @param $value
     * @return mixed
     */
    static function changeIdentityPath($option, $name, $value)
    {
        if ($name == '_wc_rnoc_tk_session') {
            $option['path'] = self::$rnoc_settings->getIdentityPath();
        }
        return $option;
    }

    static function includePluginFiles()
    {
        $rnoc_varnish_check = self::$rnoc_settings->getRetainfulSettingValue('rnoc_varnish_check', 'no');
        if ($rnoc_varnish_check === 'no') {
            $woocommerce_functions = new WcFunctions();
            $woocommerce_functions->initWoocommerceSession();
            do_action('rnoc_after_including_plugin_files', $woocommerce_functions, self::class);
        }
    }

    /**
     * Migrate data required for v 1.1.3
     */
    public static function doMigration()
    {
        $is_migrated = get_option('retainful_v_1_1_3_migration_completed', 0);
        if (!$is_migrated) {
            $slug = self::$rnoc_settings->slug;
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
     * Dependency check for our plugin
     */
    public function checkDependencies()
    {
        if (!defined('WC_VERSION')) {
            self::showAdminNotice(__('Woocommerce must be activated for Retainful-Woocommerce to work', RNOC_TEXT_DOMAIN));
        } else {
            if (version_compare(WC_VERSION, '2.5', '<')) {
                self::showAdminNotice(RnocFunction . php__('Your woocommerce version is ', RNOC_TEXT_DOMAIN) . WC_VERSION . __('. Some of the features of Retainful-Woocommerce will not work properly on this woocommerce version.', RNOC_TEXT_DOMAIN));
            }
        }
        if (is_admin()) {
            self::doMigration();
        }
    }

    /**
     * detect woocommerce have been deactivated
     * @param $plugin
     * @param $network_activation
     */
    public static function detectPluginDeactivation($plugin, $network_activation)
    {
        if (in_array($plugin, array('woocommerce/woocommerce.php'))) {
            deactivate_plugins(plugin_basename(__FILE__));
            //Todo - Deactivate this plugin
        }
    }

    public static function removeDependentTables()
    {
    }

    /**
     * All tables required for retainful abandoned cart
     * @return array
     */
    public static function getAbandonedCartTables()
    {
        return array(
            RNOC_PLUGIN_PREFIX . 'abandoned_cart_history',
            RNOC_PLUGIN_PREFIX . 'guest_abandoned_cart_history'
        );
    }


    public static function addStoreHooks()
    {

    }

    public function canActivateIPFilter()
    {
        $settings = self::$rnoc_settings->getAdminSettings();
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
    public function migrationV201()
    {
        $premium_settings = get_option(self::$rnoc_settings->slug . '_premium');
        $admin_settings = self::$rnoc_settings->getAdminSettings();
        $admin_settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] = isset($premium_settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter']) ? $premium_settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] : 0;
        $admin_settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'] = isset($premium_settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses']) ? $premium_settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'] : '';
        update_option(self::$rnoc_settings->slug . '_settings', $admin_settings);
        update_option('is_retainful_v2_0_1_migration_completed', 1);
    }

    /**
     * Run when our plugin get deactivated
     */
    public static function onPluginDeactivation()
    {
        self::removeAllScheduledActions();
        self::$rnoc_settings->removeWebhook();
    }


    /**
     * Remove all actions without any knowledge
     */
    public static function removeAllScheduledActions()
    {
        self::$rnoc_settings->removeFinishedHooks('rnoc_abandoned_clear_abandoned_carts');
        self::$rnoc_settings->removeFinishedHooks('rnoc_abandoned_cart_send_email');
        self::$rnoc_settings->removeFinishedHooks('rnocp_check_user_plan');
    }

    /**
     * check api is valid or not on 3 days once
     */
    public function checkApi()
    {
        $last_checked = get_option('rnoc_last_plan_checked', NULL);
        if (empty($last_checked) || (current_time('timestamp') > intval($last_checked) + 259200)) {
            self::checkUserPlan();
        }
    }

    /**
     * Check and update the user plan
     */
    public static function checkUserPlan()
    {
        $api_key = self::$rnoc_settings->getApiKey();
        $secret_key = self::$rnoc_settings->getSecretKey();
        if (!empty($api_key) && !empty($secret_key)) {
            $api_obj = new RestApi();
            $store_data = array(
                'secret_key' => $api_obj->encryptData($api_key, $secret_key));
            self::$rnoc_settings->isApiEnabled($api_key, $secret_key, $store_data);
        } else {
            self::$rnoc_settings->updateUserAsFreeUser();
        }
        self::$rnoc_settings->removeFinishedHooks('rnocp_check_user_plan', 'publish');
    }

    /**
     * Insert default email template
     * @param $table
     */
    public static function insertDefaultEmailTemplate($table)
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

}