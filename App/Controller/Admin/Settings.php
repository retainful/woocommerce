<?php

namespace Rnoc\App\Controller\Admin;
if (!defined('ABSPATH')) exit;

use Rnoc\App\Controller\Admin\Webhooks;
use Rnoc\App\Helpers\WcFunctions;
use Valitron\Validator;

class Settings extends BaseController
{
    /**
     * Get the abandoned cart settings
     * @return array|mixed
     */
    function getAdminSettings()
    {
        $abandoned_cart = get_option($this->slug . '_settings', array());
        if (empty($abandoned_cart))
            $abandoned_cart = array();
        return $abandoned_cart;
    }

    /**
     * register plugin related menus
     */
    function registerMenu()
    {
        $webhook = new Webhooks();
        add_menu_page('Retainful', 'Retainful', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'), 'dashicons-controls-repeat', 56);
        add_submenu_page('retainful_license', 'Connection', 'Connection', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'));
        add_submenu_page('retainful_license', 'Settings', 'Settings', 'manage_woocommerce', 'retainful_settings', array($this, 'retainfulSettingsPage'));
        if (isset($_REQUEST['page']) && in_array($_REQUEST['page'], array('retainful_license', 'retainful_settings'))) {
            $legacy_notice = '<div style="padding: 10px 46px 10px 22px;font-size: 15px;line-height: 1.4;margin-left: -20px;">Unlock the power of fully customizable email capture forms, including Add to Cart and Exit Intent popups, right from your Retainful dashboard. Head over to the Signup Forms section to configure and activate them. Tailor each popup to your brand, track sign-ups efficiently, and entice subscribers with unique coupons. <br/><b style="font-size: 15px;">Please note: Legacy popups will be phased out by April 15. Need help transitioning to the new Sign Up forms? Reach out to us at <a href="mailto:support@retainful.com">support@retainful.com</a> for assistance.</b></div>';
            add_action('admin_notices', function () use ($legacy_notice) {
                echo '<div class="error notice"><p>' . $legacy_notice . '</p></div>';
            });
        }
        if (isset($_REQUEST['page']) && in_array($_REQUEST['page'], array('retainful_license', 'retainful_settings')) && $webhook->isWebhookNoticeShow()) {
            $message = sprintf(__('Webhooks for Retainful seem not present or de-activated. Please go to the WooCommerce <a href="%s" target="_blank">webhooks section</a> and activate them.', RNOC_TEXT_DOMAIN), admin_url('admin.php?page=wc-settings&tab=advanced&section=webhooks'));
            add_action('admin_notices', function () use ($message) {
                echo '<div class="error notice"><p>' . $message . '</p></div>';
            });
        }
    }

    /**
     * page styles
     */
    function initAdminPageStyles()
    {
        $page = self::$input->get('page', null);
        if (is_admin() && in_array($page, array('retainful', 'retainful_settings', 'retainful_premium', 'retainful_license'))) {
            $this->addScript();
        }
    }

    function addScript()
    {
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : "";
        $prefix = substr($page, 0, 9);
        if ($prefix != "retainful") {
            return;
        }
        $asset_path = RNOC_PLUGIN_URL . 'assets/admin';
        //product search select
        wp_enqueue_script('rnoc-select2-js', $this->getWooPluginUrl() . '/assets/js/select2/select2.full.min.js', array('jquery'));
        wp_enqueue_style('rnoc-select2-css', $this->getWooPluginUrl() . '/assets/css/select2.css');
        wp_enqueue_script('woocommerce_admin');
        wp_enqueue_script('retainful-app-main', $asset_path . '/js/app.js', array(), RNOC_VERSION);
        wp_localize_script('retainful-app-main', 'retainful_admin', array(
            'i10n' => array(
                'please_wait' => __('Please wait...', RNOC_TEXT_DOMAIN)
            ),
            'security' => array(
                'get_search_coupon' => wp_create_nonce('rnoc_get_search_coupon'),
            ),
            'ajax_endpoint' => admin_url('admin-ajax.php?action={{action}}&security={{security}}'),
            'search_products_nonce' => wp_create_nonce('search-products'),
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
        wp_enqueue_style('retainful-admin-css', $asset_path . '/css/main.css', array(), RNOC_VERSION);
        wp_enqueue_style('retainful-admin-style-css', $asset_path . '/css/style.css', array(), RNOC_VERSION);
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }


    /**
     * retainful ac settings page
     */
    function retainfulSettingsPage()
    {
        $webhook = new Webhooks();
        $webhook->createWebhook();
        $settings = $this->getAdminSettings();

        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'cart_tracking_engine' => 'js',
            RNOC_PLUGIN_PREFIX . 'enable_background_order_sync' => 'no',
            RNOC_PLUGIN_PREFIX . 'track_zero_value_carts' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_referral_widget' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget' => 'yes',
            RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status' => '1',
            RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load' => '0',
            RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance' => '0',
            RNOC_PLUGIN_PREFIX . 'cart_capture_msg' => 'Keep me up to date on news and exclusive offers',
            RNOC_PLUGIN_PREFIX . 'gdpr_display_position' => 'after_billing_email',
            RNOC_PLUGIN_PREFIX . 'enable_ip_filter' => '0',
            RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses' => '',
            RNOC_PLUGIN_PREFIX . 'enable_debug_log' => '0',
            RNOC_PLUGIN_PREFIX . 'handle_storage_using' => 'woocommerce',
            RNOC_PLUGIN_PREFIX . 'enable_afterpay_action' => 'no',
            RNOC_PLUGIN_PREFIX . 'varnish_check' => 'no',
        );
        $settings = wp_parse_args($settings, $default_settings);

        if (empty($settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'])) {
            $settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'] = 'Keep me up to date on news and exclusive offers';
        }
        require_once RNOC_PLUGIN_PATH . 'App/Views/Admin/settings.php';
    }


    /**
     * render retainful license page
     */
    function retainfulLicensePage()
    {
        $webhook = new Webhooks();
        $settings = get_option($this->slug . '_license', array());
        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => 0,
            RNOC_PLUGIN_PREFIX . 'retainful_app_id' => '',
            RNOC_PLUGIN_PREFIX . 'retainful_app_secret' => '',
        );
        $settings = wp_parse_args($settings, $default_settings);
        require_once RNOC_PLUGIN_PATH . 'App/Views/Admin/connection.php';
        $webhook->createWebhook();
    }

    /**
     * save the settings
     */
    function saveAcSettings()
    {
        WcFunctions::checkSecuritykey('rnoc_save_settings');
        $post = self::$input->post();
        $validator = new Validator($post);
        $validator->rule('in', RNOC_PLUGIN_PREFIX . 'cart_tracking_engine', ['js', 'php'])->message('This field contains invalid value');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'track_zero_value_carts',
            RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'
        ), ['yes', 'no'])->message('This field contains invalid value');
        $validator->rule('in', RNOC_PLUGIN_PREFIX . 'handle_storage_using', ['woocommerce', 'cookie', 'php'])->message('This field contains invalid value');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status',
            RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status',
            RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status',
            RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load',
            RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance',
            RNOC_PLUGIN_PREFIX . 'enable_ip_filter',
            RNOC_PLUGIN_PREFIX . 'enable_debug_log',
        ), ['0', '1'])->message('This field contains invalid value');
        if (!$validator->validate()) {
            wp_send_json_error($validator->errors());
        }
        $cart_capture_msg = self::$input->post(RNOC_PLUGIN_PREFIX . 'cart_capture_msg', '');
        $post = self::$input->post();
        $data = self::$input->clean($post);
        $data[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'] = trim(self::$input->sanitizeBasicHtml($cart_capture_msg));
        update_option($this->slug . '_settings', $data);
        wp_send_json_success(__('Settings successfully saved!', RNOC_TEXT_DOMAIN));
    }

    /**
     * disconnect the app
     */
    function disconnectLicense()
    {
        WcFunctions::checkSecuritykey('rnoc_disconnect_license');
        $license_details = get_option($this->slug . '_license', array());
        $license_details[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'] = 0;
        update_option($this->slug . '_license', $license_details);
        wp_send_json_success(__('App disconnected successfully!', RNOC_TEXT_DOMAIN));
    }

    /**
     * Validate app Id
     */
    function validateAppKey()
    {
        WcFunctions::checkSecuritykey('validate_app_key');
        $post = self::$input->post();
        $validator = new Validator($post);
        $validator->rule('required', ['app_id', 'secret_key']);
        $validator->rule('slug', ['app_id', 'secret_key']);
        if (!$validator->validate()) {
            $response['error'] = $validator->errors();
            wp_send_json($response);
        }
        $is_production = apply_filters('rnoc_is_production_plugin', true);
        if (!$is_production) {
            wp_send_json_error('You can only change you App-Id and Secret key in production store!', 500);
        }
        $app_id = isset($_REQUEST['app_id']) ? sanitize_text_field($_REQUEST['app_id']) : '';
        $secret_key = isset($_REQUEST['secret_key']) ? sanitize_text_field($_REQUEST['secret_key']) : '';
        $options_data = array(
            RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_app_id' => $app_id,
            RNOC_PLUGIN_PREFIX . 'retainful_app_secret' => $secret_key
        );
        $slug = $this->slug;
        //Save app id before validate key
        update_option($slug . '_license', $options_data);
        $response = array();
        $this->updateUserAsFreeUser();
        if (empty($response)) {
            $api_response = $this->isApiEnabled($app_id, $secret_key);
            if (isset($api_response['success'])) {
                //Change app id status
                $options_data[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'] = 1;
                update_option($slug . '_license', $options_data);
                $response['success'] = $api_response['success'];
            } elseif (isset($api_response['error'])) {
                $response['error'] = $api_response['error'];
            } else {
                $response['error'] = __('Please check the entered details', RNOC_TEXT_DOMAIN);
            }
        }
        wp_send_json($response);
    }

    /**
     * get where to save the temp data
     * @return mixed|string
     */
    function getStorageHandler()
    {
        $admin_settings = $this->getAdminSettings();
        if (isset($admin_settings[RNOC_PLUGIN_PREFIX . 'handle_storage_using']) && !empty($admin_settings[RNOC_PLUGIN_PREFIX . 'handle_storage_using'])) {
            return $admin_settings[RNOC_PLUGIN_PREFIX . 'handle_storage_using'];
        } else {
            return "woocommerce";
        }
    }

}