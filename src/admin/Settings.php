<?php

namespace Rnoc\Retainful\Admin;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Api\AbandonedCart\RestApi;
use Rnoc\Retainful\Api\AbandonedCart\Storage\Cookie;
use Rnoc\Retainful\Helpers\Input;
use Rnoc\Retainful\Integrations\MultiLingual;
use Rnoc\Retainful\library\RetainfulApi;
use Rnoc\Retainful\WcFunctions;
use Valitron\Validator;

class Settings
{
    public $slug = 'retainful', $api, $wc_functions;
    public static $input = null;

    /**
     * Settings constructor.
     */
    function __construct()
    {
        $this->api = new RetainfulApi();
        $this->wc_functions = new WcFunctions();
        if (is_null(self::$input)) {
            self::$input = new Input();
        }
    }


    /**
     * page styles
     */
    function initAdminPageStyles()
    {
        $page = self::$input->get('page', null);
        if (is_admin() && in_array($page, array('retainful', 'retainful_settings', 'retainful_license'))) {
            $this->addScript();
        }
    }


    /**
     * render retainful license page
     */
    function retainfulLicensePage()
    {
        $settings = get_option($this->slug . '_license', array());
        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => 0,
            RNOC_PLUGIN_PREFIX . 'retainful_app_id' => '',
            RNOC_PLUGIN_PREFIX . 'retainful_app_secret' => '',
        );
        $settings = wp_parse_args($settings, $default_settings);
        require_once dirname(__FILE__) . '/templates/pages/connection.php';
        $this->createWebhook();
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
        $app_id = isset($_REQUEST['app_id']) ? sanitize_text_field(wp_unslash($_REQUEST['app_id'])) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $secret_key = isset($_REQUEST['secret_key']) ? sanitize_text_field(wp_unslash($_REQUEST['secret_key'])) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $options_data = array(
            RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_app_id' => $app_id,
            RNOC_PLUGIN_PREFIX . 'retainful_app_secret' => $secret_key
        );
        $slug = $this->slug;
        //Save app id before validate key
        update_option($slug . '_license', $options_data);
        $response = array();
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
                $response['error'] = __('Please check the entered details', 'retainful-next-order-coupon-for-woocommerce');
            }
        }
        wp_send_json($response);
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
        wp_send_json_success(__('App disconnected successfully!', 'retainful-next-order-coupon-for-woocommerce'));
    }

    /**
     * sanitize the basic html tags
     * @param $html
     * @return mixed|void
     */
    function sanitizeBasicHtml($html)
    {
        try {
            $html = stripslashes($html);
            $html = html_entity_decode($html);
            $allowed_html = array();
            $tags = array(
                'div', 'a', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'b', 'strong', 'i', 'img', 'br'
            );
            foreach ($tags as $tag) {
                $allowed_html[$tag] = array(
                    'class' => array(),
                    'id' => array(),
                    'style' => array()
                );
                if ($tag == 'a') {
                    $allowed_html[$tag]['href'] = array();
                }
                if ($tag == 'img') {
                    $allowed_html[$tag]['src'] = array();
                    $allowed_html[$tag]['width'] = array();
                    $allowed_html[$tag]['height'] = array();
                }
            }
            $allowed_html = apply_filters('rnoc_sanitize_allowed_basic_html_tags', $allowed_html);
            $sanitized_html = wp_kses($html, $allowed_html);
            return apply_filters('rnoc_sanitize_basic_html', $sanitized_html, $html, $allowed_html);
        } catch (\Exception $e) {
            return '';
        }
    }


    function addOrderDetailMetaBoxes($post_type)
    {
        if ('shop_order' === $post_type) {
            add_meta_box('retainful_order_meta', __('Retainful token', 'retainful-next-order-coupon-for-woocommerce'), array($this, 'orderMetaDetails'), $post_type, 'side', 'default');
        }
    }

    function orderMetaDetails()
    {
        global $post_ID;
        $order = wc_get_order($post_ID);
        $order_id = $this->wc_functions->getOrderId($order);
        echo '<p>' . esc_html($this->wc_functions->getOrderMeta($order, "_rnoc_user_cart_token")). '</p>';
    }


    /**
     * clean the data
     * @param $var
     * @return array|string
     */
    function clean($var)
    {
        if (is_array($var)) {
            return array_map(array($this, 'clean'), $var);
        } else {
            return is_scalar($var) ? sanitize_text_field($var) : $var;
        }
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
        $data = $this->clean($post);
        $data[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'] = trim($this->sanitizeBasicHtml($cart_capture_msg));
        update_option($this->slug . '_settings', $data);
        wp_send_json_success(__('Settings successfully saved!', 'retainful-next-order-coupon-for-woocommerce'));
    }


    /**
     * Create order sync webhook.
     *
     * @return void
     */
    function createWebhook()
    {

	    if (is_admin()) {
            if ($this->isConnectionActive()) {
                $hook_status = $this->getWebHookStatus();
                if (isset($hook_status['order.updated']) && !$hook_status['order.updated']) {
                    $this->addNewWebhook();
                }
                if (isset($hook_status['order.created']) && !$hook_status['order.created']) {
                    $this->addNewWebHook('order.created');
                }
                if (isset($hook_status['product.updated']) && !$hook_status['product.updated']) {
                    $this->addNewWebHook('product.updated');
                }
                if (isset($hook_status['product.created']) && !$hook_status['product.created']) {
                    $this->addNewWebHook('product.created');
                }
                if (isset($hook_status['product.deleted']) && !$hook_status['product.deleted']) {
                    $this->addNewWebHook('product.deleted');
                }
	            if (isset($hook_status['category.updated']) && !$hook_status['category.updated']) {
		            $this->addNewWebHook('category.updated');
	            }
	            if (isset($hook_status['category.created']) && !$hook_status['category.created']) {
		            $this->addNewWebHook('category.created');
	            }
	            if (isset($hook_status['category.deleted']) && !$hook_status['category.deleted']) {
		            $this->addNewWebHook('category.deleted');
	            }
            } else {
                $this->removeWebhook();
            }
        }
    }

    /**
     * Remove retainful webhook.
     *
     * @return void
     */
    function removeWebhook()
    {
        if (!class_exists('WC_Data_Store') || !class_exists('\Rnoc\Retainful\library\RetainfulApi') || !function_exists('wc_get_webhook')) {
            return;
        }
        try {
            $data_store = \WC_Data_Store::load('webhook');
            $args = array(
                'limit' => -1,
                'offset' => 0,
            );
            $webhooks = $data_store->search_webhooks($args);
            foreach ($webhooks as $webhook_id) {
                $webhook = wc_get_webhook($webhook_id);
				$webhook_details = self::getWebhookDetails($webhook_id);
	            if (empty($webhook_details) || !isset($webhook_details->topic)) {
		            continue;
	            }

	            $topic = $webhook_details->topic;
                $delivery_url = $webhook->get_delivery_url();
                $site_delivery_url = $this->getDeliveryUrl($topic);
                if ($delivery_url != $site_delivery_url) {
                    continue;
                }
                $webhook->delete();
            }
        } catch (\Exception $e) {

        }
    }

	public static function getWebhookDetails($webhook_id){
		global $wpdb;
		$webhook_id = intval($webhook_id); // Ensure it's an integer
		 return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_webhooks WHERE webhook_id = %d", $webhook_id)); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
    /**
     * Get Webhooks status.
     * @return array
     */
    function getWebHookStatus()
    {
        $topics = [
            'order.updated' => false,
            'order.created' => false,
            'product.created' => false,
            'product.updated' => false,
            'product.deleted' => false,
	        'category.created' => false,
            'category.updated' => false,
            'category.deleted' => false
        ];
        try {
            if(!class_exists('WC_Data_Store')){
                return $topics;
            }
            $data_store = \WC_Data_Store::load('webhook');
            $args = array(
                'limit' => -1,
                'offset' => 0,
            );
            $webhooks = $data_store->search_webhooks($args);

            foreach ($webhooks as $webhook_id) {
                $webhook = wc_get_webhook($webhook_id);
                if (empty($webhook)) {
                    continue;
                }
                $topic = $webhook->get_topic();
                $delivery_url = $webhook->get_delivery_url();
                $site_delivery_url = $this->getDeliveryUrl($topic);


                if ($delivery_url != $site_delivery_url) {
                    continue;
                }
                if (isset($topics[$webhook->get_topic()])) {
                    $topics[$webhook->get_topic()] = true;
                }
            }
        } catch (\Exception $e) {

        }
        return $topics;
    }

    function getDeliveryUrl($topic)
    {
        if (empty($topic)) return;
        $url = '';
        if (in_array($topic, array('order.created', 'order.updated'))) {
            $url = $this->api->getDomain() . 'woocommerce/webhooks/checkout';
        } elseif (in_array($topic, array('product.created', 'product.updated', 'product.deleted'))) {
            $url = apply_filters('change_product_webhook_delivery_url', $this->api->getProductDomain().'/event/woocommerce/products');
        } elseif (in_array($topic, array('category.created', 'category.updated', 'category.deleted'))) {
	        $url = apply_filters('change_category_webhook_delivery_url',$this->api->getProductDomain().'/event/woocommerce/category');
        }
        return $url;
    }

    /**
     * Add new webhook.
     *
     * @param $topic
     * @return bool
     */
    protected function addNewWebHook($topic = 'order.updated')
    {

		if (!in_array($topic, array('order.updated', 'order.created', 'product.updated', 'product.created', 'product.deleted', 'category.updated', 'category.created', 'category.deleted'))) {
            return false;
        }
        try {
            $name = '';
            switch ($topic) {
                case 'order.updated':
                    $name = 'Retainful Order Update';
                    break;
                case 'order.created':
                    $name = 'Retainful Order created';
                    break;
                case 'product.updated':
                    $name = 'Retainful product Update';
                    break;
                case 'product.created':
                    $name = 'Retainful product created';
                    break;
                case 'product.deleted':
                    $name = 'Retainful product deleted';
                    break;
	            case 'category.created':
		            $name = 'Retainful Category Created';
		            break;
	            case 'category.updated':
		            $name = 'Retainful Category Updated';
		            break;
	            case 'category.deleted':
		            $name = 'Retainful Category Deleted';
		            break;
            }

            if(!class_exists('WC_Webhook')){
                return false;
            }
            $webhook = new \WC_Webhook();
            // $name = $topic == 'order.updated' ? sanitize_text_field(wp_unslash('Retainful Order Update')) : sanitize_text_field(wp_unslash('Retainful Order Create'));
            $webhook->set_name(sanitize_text_field($name));
            if (!$webhook->get_user_id()) {
                $webhook->set_user_id(get_current_user_id());
            }
            //
            $webhook->set_status('active');
            $delivery_url = $this->getDeliveryUrl($topic);
            $webhook->set_delivery_url($delivery_url);
            $secret = wp_generate_password(50, true, true);
            $webhook->set_secret($secret);



	        if (in_array($topic, ['category.created', 'category.updated', 'category.deleted'])) {
		        $webhook->set_topic(  $topic );
	        } elseif (wc_is_webhook_valid_topic($topic)) {
		        $webhook->set_topic($topic);
	        }

            $rest_api_versions = wc_get_webhook_rest_api_versions();
            $webhook->set_api_version(end($rest_api_versions)); // WPCS: input var okay, CSRF ok.
            $webhook_id = $webhook->save();
            if ($webhook_id > 0) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * retainful ac settings page
     */
    function retainfulSettingsPage()
    {
        $this->createWebhook();
        $settings = $this->getAdminSettings();

        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'cart_tracking_engine' => 'js',
            RNOC_PLUGIN_PREFIX . 'enable_background_order_sync' => 'no',
            RNOC_PLUGIN_PREFIX . 'track_zero_value_carts' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_referral_widget' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup' => 'yes',
            RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget' => 'no',
            RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status' => '1',
            RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load' => '0',
            RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance' => '0',
            RNOC_PLUGIN_PREFIX . 'cart_capture_msg' => 'Keep me up to date on news and exclusive offers',
            RNOC_PLUGIN_PREFIX . 'gdpr_display_position' => 'after_billing_email',
            RNOC_PLUGIN_PREFIX . 'enable_sms_consent' => '0',
            RNOC_PLUGIN_PREFIX . 'sms_capture_msg' => 'Keep me up to date on news and exclusive offers',
            RNOC_PLUGIN_PREFIX . 'sms_consent_display_position' => 'after_billing_email',
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
	    if (isset($settings[RNOC_PLUGIN_PREFIX . 'sms_capture_msg']) && empty($settings[RNOC_PLUGIN_PREFIX . 'sms_capture_msg'])) {
		    $settings[RNOC_PLUGIN_PREFIX . 'sms_capture_msg'] = 'Keep me up to date on news and exclusive offers';
	    }

	    require_once dirname(__FILE__) . '/templates/pages/settings.php';
    }

    function getRetainfulSettingValue($key, $default = null)
    {
        $settings = $this->getAdminSettings();
        return (!empty($key) && isset($settings[$key])) ? $settings[$key] : $default;
    }

    /**
     * register plugin related menus
     */
    function registerMenu()
    {
        add_menu_page('Retainful', 'Retainful', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'), 'dashicons-controls-repeat', 56);
        add_submenu_page('retainful_license', 'Connection', 'Connection', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'));
        add_submenu_page('retainful_license', 'Settings', 'Settings', 'manage_woocommerce', 'retainful_settings', array($this, 'retainfulSettingsPage'));
		$page = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($page && in_array($page, array('retainful_license', 'retainful_settings')) && $this->isWebhookNoticeShow()) {
	        /* translators: %s: Webhook redirect url */
	        $message = sprintf(__('Webhooks for Retainful seem not present or de-activated. Please go to the WooCommerce <a href="%s" target="_blank">webhooks section</a> and activate them.', 'retainful-next-order-coupon-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=advanced&section=webhooks'));
            add_action('admin_notices', function () use ($message) {
                echo '<div class="error notice"><p>' . wp_kses_post($message) . '</p></div>';
            });
        }
    }

    /**
     * Is need to show webhook notice.
     *
     * @return bool
     */
    function isWebhookNoticeShow()
    {

        if (!$this->isConnectionActive()) {
            return false;
        }
        if (!class_exists('WC_Data_Store') || !function_exists('wc_get_webhook')) {
            return false;
        }
        $webhook_status = array(
            'order_created' => false,
            'order_updated' => false,
            'product_created' => false,
            'product_updated' => false,
            'product_deleted' => false,
	        'category_created' => false,
            'category_updated' => false,
            'category_deleted' => false,
        );
        try {
            $data_store = \WC_Data_Store::load('webhook');
            $args = array(
                'limit' => -1,
                'offset' => 0,
            );

            $webhooks = $data_store->search_webhooks($args);
            foreach ($webhooks as $webhook_id) {
                $webhook = wc_get_webhook($webhook_id);
                if (empty($webhook)) {
                    continue;
                }
                $topic = $webhook->get_topic();
                $delivery_url = $webhook->get_delivery_url();
                $site_delivery_url = $this->getDeliveryUrl($topic);
                if ($delivery_url != $site_delivery_url) {
                    continue;
                }

                $status = $webhook->get_status();
                if ($status == 'active' && $topic == 'order.created') {
                    $webhook_status['order_created'] = true;
                }
                if ($status == 'active' && $topic == 'order.updated') {
                    $webhook_status['order_updated'] = true;
                }
                if ($status == 'active' && $topic == 'product.created') {
                    $webhook_status['product_created'] = true;
                }
                if ($status == 'active' && $topic == 'product.updated') {
                    $webhook_status['product_updated'] = true;
                }
                if ($status == 'active' && $topic == 'product.deleted') {
                    $webhook_status['product_deleted'] = true;
                }
	            if ($status == 'active' && $topic == 'category.created') {
		            $webhook_status['category_created'] = true;
	            }
	            if ($status == 'active' && $topic == 'category.updated') {
		            $webhook_status['category_updated'] = true;
	            }
	            if ($status == 'active' && $topic == 'category.deleted') {
		            $webhook_status['category_deleted'] = true;
	            }
            }
        } catch (\Exception $e) {

        }

        return in_array(false, $webhook_status);
    }

    /**
     * Check any pending hooks already exists
     * @param $meta_value
     * @param $hook
     * @param $meta_key
     * @return bool|mixed
     */
    function hasAnyActiveScheduleExists($hook, $meta_value, $meta_key)
    {
        $actions = new \WP_Query(array(
            'post_title' => $hook,
            'post_status' => 'pending',
            'post_type' => 'scheduled-action',
            'meta_query' => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array(
                    'key' => $meta_key,
                    'value' => $meta_value,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));
        return $actions->have_posts();
    }

    /**
     * un schedule hooks
     */
    function unScheduleHooks()
    {
        $this->removeFinishedHooks('rnoc_abandoned_clear_abandoned_carts', 'pending');
    }


    /**
     * Add post meta
     * @param $post_id
     * @param $args
     * @return false|int
     */
    function addPostMeta($post_id, $args)
    {
        if (!empty($args)) {
            foreach ($args as $meta_key => $meta_value) {
                add_post_meta($post_id, $meta_key, $meta_value);
            }
            return true;
        }
        return false;
    }

    /**
     * Schedule events
     * @param $hook
     * @param $timestamp
     * @param array $args
     * @param string $type
     * @param null $interval_in_seconds
     * @param string $group
     */
    function scheduleEvents($hook, $timestamp, $args = array(), $type = "single", $interval_in_seconds = NULL, $group = '')
    {
        if (class_exists('ActionScheduler')) {
            switch ($type) {
                case "recurring":
                    if (!$this->nextScheduledAction($hook)) {
                        \ActionScheduler::factory()->recurring($hook, $args, $timestamp, $interval_in_seconds, $group);
                    }
                    break;
                case 'single':
                default:
                    $action_id = \ActionScheduler::factory()->single($hook, $args, $timestamp);
                    $this->addPostMeta($action_id, $args);
                    break;
            }
        } else {
            switch ($type) {
                case "recurring":
                    if (function_exists('as_schedule_recurring_action') && function_exists('as_next_scheduled_action')) {
                        if (!as_next_scheduled_action($hook)) {
                            as_schedule_recurring_action($timestamp, $interval_in_seconds, $hook, $args, $group);
                        }
                    }
                    break;
                case 'single':
                default:
                    if (function_exists('as_schedule_single_action')) {
                        $action_id = as_schedule_single_action($timestamp, $hook, $args);
                        $this->addPostMeta($action_id, $args);
                    }
                    break;
            }
        }
    }

    /**
     * @param string $hook
     * @param array $args
     * @param string $group
     *
     * @return int|bool The timestamp for the next occurrence, or false if nothing was found
     */
    function nextScheduledAction($hook, $args = NULL, $group = '')
    {
        $params = array();
        if (is_array($args)) {
            $params['args'] = $args;
        }
        if (!empty($group)) {
            $params['group'] = $group;
        }
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '4.0', '>=')) {
            $params['status'] = \ActionScheduler_Store::STATUS_RUNNING;
            $job_id = \ActionScheduler::store()->find_action($hook, $params);
            if (!empty($job_id)) {
                return true;
            }
            $params['status'] = \ActionScheduler_Store::STATUS_PENDING;
            $job_id = \ActionScheduler::store()->find_action($hook, $params);
            if (empty($job_id)) {
                return false;
            }
            $job = \ActionScheduler::store()->fetch_action($job_id);
            $scheduled_date = $job->get_schedule()->get_date();
            if ($scheduled_date) {
                return (int)$scheduled_date->format('U');
            } elseif (NULL === $scheduled_date) { // pending async action with NullSchedule
                return true;
            }
            return false;
        } else {
            $job_id = \ActionScheduler::store()->find_action($hook, $params);
            if (empty($job_id)) {
                return false;
            }
            $job = \ActionScheduler::store()->fetch_action($job_id);
            $next = $job->get_schedule()->next();
            if ($next) {
                return (int)($next->format('U'));
            }
            return false;
        }
    }

    /**
     * All the available scheduled actions post name
     * @return array
     */
    protected function availableScheduledActions()
    {
        return array( 'rnoc_abandoned_clear_abandoned_carts');
    }

    /**
     * Remove all hooks and schedule once
     * @param $post_title
     * @param $status
     * @return bool
     */
    function removeFinishedHooks($post_title, $status = "")
    {
        try {
            $available_action_names = $this->availableScheduledActions();
            if (!empty($post_title) && !in_array($post_title, $available_action_names)) {
                return false;
            }
            global $wpdb;
            //when post table is using by scheduler
            $post_where = (!empty($status)) ? "AND post_status = '{$status}'" : "";
            $scheduled_actions = $wpdb->get_results($wpdb->prepare("SELECT ID from `{$wpdb->prefix}posts` where post_title =%s {$post_where} AND  post_type=%s LIMIT %d",$post_title,'scheduled-action',500)); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if (!empty($scheduled_actions)) {
                foreach ($scheduled_actions as $action) {
                    if (wp_delete_post($action->ID, true)) {
                        do_action('action_scheduler_deleted_action', $action->ID);
                    }
                }
            }
            //When custom table is being used by scheduler
            $custom_table_name = $wpdb->base_prefix . 'actionscheduler_actions';
            //$query = $wpdb->prepare('SHOW TABLES LIKE %s', $custom_table_name);
            //$found_table = $wpdb->get_var($query);
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $custom_table_name)) == $custom_table_name) { //phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $custom_table_where = (!empty($status)) ? "AND status = '{$status}'" : "";
                $scheduled_actions = $wpdb->get_results("SELECT action_id from `{$custom_table_name}` where hook ='{$post_title}' {$custom_table_where} LIMIT 500"); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                if (!empty($scheduled_actions)) {
                    foreach ($scheduled_actions as $action) {
                        $deleted = $wpdb->delete($custom_table_name, array('action_id' => $action->action_id), array('%d')); //phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        if (!empty($deleted)) {
                            do_action('action_scheduler_deleted_action', $action->action_id);
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            return false;
        }
    }


    /**
     * @return mixed|void
     */
    function isNewInstallation()
    {
        return get_option('retainful_is_new_installation', 1);
    }

    /**
     * check abandoned cart need to run locally or externally
     * @return bool|mixed|void
     */
    function runAbandonedCartExternally()
    {
        return true;
    }

    /**
     * Create log file named retainful.log
     * @param $message
     * @param $log_in_as
     */
    function logMessage($message, $log_in_as = "checkout")
    {
		return;
	    $admin_settings = $this->getAdminSettings();

	    if ( isset($admin_settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log']) && !empty($admin_settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log']) && !empty($message) ) {
			if(function_exists('wc_get_logger')){
				if (is_array($message) || is_object($message)) {
					$message = json_encode($message);
				}
				$to_print = $log_in_as . ":\n" . $message;
				wc_get_logger()->add('Retainful',$to_print);
			}
	    }
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
     * get the cart tracking engine
     * @return mixed|string
     */
    function getCartTrackingEngine()
    {
        $settings = $this->getAdminSettings();
        return (isset($settings[RNOC_PLUGIN_PREFIX . 'cart_tracking_engine']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'])) ? $settings[RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'] : 'js';
    }

    function needPopupWidget()
    {
        $settings = $this->getAdminSettings();
        $need_widget = (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup'])) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup'] : 'no';
        return apply_filters("retainful_enable_popup_widget", ($need_widget === "yes"));
    }

    function isAfterPayEnabled()
    {
        $settings = $this->getAdminSettings();
        $need_afterpay = (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_afterpay_action']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'enable_afterpay_action'])) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_afterpay_action'] : 'no';
        return apply_filters("retainful_enable_afterpay_action", ($need_afterpay === "yes"));
    }

    /**
     * get the cart tracking engine
     * @return mixed|string
     */
    function trackZeroValueCarts()
    {
        $settings = $this->getAdminSettings();
        return (isset($settings[RNOC_PLUGIN_PREFIX . 'track_zero_value_carts']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'])) ? $settings[RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'] : 'no';
    }


    /**
     *
     */
    function getSearchedCoupons()
    {
        check_ajax_referer('rnoc_get_search_coupon', 'security');
        if (current_user_can('manage_woocommerce')) {
            $search_code = self::$input->get('coupon');
            $args = array(
                "post_type" => "shop_coupon",
                "numberposts" => 10,
                "s" => $search_code,
                "post_status" => "publish"
            );
            $coupon_codes = get_posts($args);
            if (empty($coupon_codes)) {
                wp_send_json_error('No Coupons found!');
            } else {
                $result = array();
                foreach ($coupon_codes as $coupon_code_post) {
                    /**
                     * @var $coupon_code_post \WP_Post
                     */
                    $coupon_code = $coupon_code_post->post_title;
                    $result[$coupon_code] = $coupon_code;
                }
                wp_send_json_success($result);
            }
        } else {
            wp_send_json_error('You don\'t had enough right to search coupons');
        }
    }

    function deleteUnusedExpiredCoupons()
    {
        WcFunctions::checkSecuritykey('rnoc_delete_expired_coupons');
        for ($i = 0; $i < 10; $i++) {
            $posts = $this->getDiscountData();
            if (empty($posts)) break;
            foreach ($posts as $post) {
                wp_delete_post($post->ID, true);
            }
        }
        wp_send_json_success(array('message' => "successfully deleted"));
    }

    function getDiscountData()
    {
        $args = array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => 100,
            'meta_query' => array(  //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array(
                    'key' => '_rnoc_shop_coupon_type',
                    'value' => array('retainful', 'retainful-referral'),
                    'compare' => 'IN'
                ), array(
                    'key' => 'date_expires',
                    'value' => strtotime(gmdate('Y-m-d h:i:s')),
                    'compare' => '<'
                ), array(
                    'key' => 'usage_count',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        return get_posts($args);
    }


    /**
     * @param $date
     * @param $format
     * @return string|null
     */
    function formatDate($date, $format)
    {
        try {
            $date_obj = new \DateTime($date);
            return $date_obj->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Add admin scripts
     */
    function addScript()
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : "";  //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $prefix = substr($page, 0, 9);
        if ($prefix != "retainful") {
            return;
        }
        $asset_path = plugins_url('', __FILE__);
        //product search select
        wp_enqueue_script('rnoc-select2-js', $this->getWooPluginUrl() . '/assets/js/select2/select2.full.min.js', array('jquery'), RNOC_VERSION,['in_footer' => true]);
        wp_enqueue_style('rnoc-select2-css', $this->getWooPluginUrl() . '/assets/css/select2.css',[],RNOC_VERSION);
        wp_enqueue_script('woocommerce_admin');
        wp_enqueue_script('retainful-app-main', $asset_path . '/js/app.js', array(), RNOC_VERSION,['in_footer' => true]);
        wp_localize_script('retainful-app-main', 'retainful_admin', array(
            'i10n' => array(
                'please_wait' => __('Please wait...', 'retainful-next-order-coupon-for-woocommerce')
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
     * get woocommerce plugin url
     * @return string|null
     */
    function getWooPluginUrl()
    {
        if (function_exists('WC')) {
            return WC()->plugin_url();
        }
        return NULL;
    }

    /**
     * Get coupon usage restriction details
     * @return array
     */
    function getUsageRestrictions()
    {
        if ($this->isAppConnected()) {
            $usage_restrictions = get_option($this->slug, array());
            if (empty($usage_restrictions))
                $usage_restrictions = array();
            return $usage_restrictions;
        } else {
            return array();
        }
    }

    /**
     * Check fo entered API key is valid or not
     * @param string $api_key
     * @param string $secret_key
     * @param string $store_data
     * @return bool|array
     */
    function isApiEnabled($api_key = "", $secret_key = NULL, $store_data = NULL)
    {
        if (empty($api_key)) {
            $api_key = $this->getApiKey();
        }
        if (empty($secret_key)) {
            $secret_key = $this->getSecretKey();
        }
        if (empty($store_data)) {
            $store_data = $this->storeDetails($api_key, $secret_key);
        }
        if (!empty($api_key)) {
            if ($details = $this->api->validateApi($api_key, $store_data)) {
                if (empty($details) || is_string($details)) {
                    return array('error' => $details);
                } else {
                    return array('success' => isset($details['message']) ? $details['message'] : NULL);
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * License settings
     * @return mixed|void
     */
    function getLicenseDetails()
    {
        return get_option($this->slug . '_license', array());
    }

    /**
     * Check connection is active or not.
     *
     * @return bool
     */
    function isConnectionActive()
    {
        $secret_key = $this->getSecretKey();
        $app_id = $this->getApiKey();
        if ($this->isAppConnected() && !empty($secret_key) && !empty($app_id)) {
            return true;
        }
        return false;
    }

    /**
     * Check fo entered API key is valid or not
     * @return bool
     */
    function isAppConnected()
    {
        $settings = $this->getLicenseDetails();
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'is_retainful_connected']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'])) {
            return true;
        }
        return false;
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    function getApiKey()
    {
        $settings = $this->getLicenseDetails();
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id'])) {
            return $settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id'];
        }
        return NULL;
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    function getSecretKey()
    {
        $settings = $this->getLicenseDetails();
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_secret']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_secret'])) {
            return $settings[RNOC_PLUGIN_PREFIX . 'retainful_app_secret'];
        }
        return NULL;
    }

    /**
     * Check the site has multi currency
     * @return bool
     */
    function getBaseCurrency()
    {
        $base_currency = $this->wc_functions->getDefaultCurrency();
        return apply_filters('rnoc_get_default_currency_code', $base_currency);
    }

    /**
     * Check the site has multi currency
     * @return bool
     */
    function getAllAvailableCurrencies()
    {
        $base_currency = $this->wc_functions->getDefaultCurrency();
        $currencies = array($base_currency);
        return apply_filters('rnoc_get_available_currencies', $currencies);
    }

    /**
     * Get the store details
     * @param $api_key
     * @param $secret_key
     * @return array
     */
    function storeDetails($api_key, $secret_key)
    {
        $scheme = wc_site_is_https() ? 'https' : 'http';
        $country_details = get_option('woocommerce_default_country');
        list($country_code, $state_code) = explode(':', $country_details);
        $lang_helper = new MultiLingual();
        $default_language = $lang_helper->getDefaultLanguage();
        $api_obj = new RestApi();
        $details = array(
            'woocommerce_app_id' => $api_key,
            'secret_key' => $api_obj->encryptData($api_key, $secret_key),
            'id' => NULL,
            'name' => get_option('blogname'),
            'email' => get_option('admin_email'),
            'domain' => get_home_url(null, null, $scheme),
            'address1' => get_option('woocommerce_store_address', NULL),
            'address2' => get_option('woocommerce_store_address_2', NULL),
            'currency' => $this->getBaseCurrency(),
            'city' => get_option('woocommerce_store_city', NULL),
            'zip' => get_option('woocommerce_store_postcode', NULL),
            'country' => NULL,
            'timezone' => $this->getSiteTimeZone(),
            'weight_unit' => get_option('woocommerce_weight_unit'),
            'country_code' => $country_code,
            'province_code' => $state_code,
            'force_ssl' => (get_option('woocommerce_force_ssl_checkout', 'no') == 'yes'),
            'enabled_presentment_currencies' => $this->getAllAvailableCurrencies(),
            'primary_locale' => $default_language
        );
        return $details;
    }

    /**
     * Get the timezone of the site
     * @return mixed|void
     */
    function getSiteTimeZone()
    {
        $time_zone = get_option('timezone_string');
        if (empty($time_zone)) {
            $time_zone = get_option('gmt_offset');
        }
        return $time_zone;
    }


    /**
     * Check is next order coupon enabled
     * @return bool
     */
    function isNextOrderCouponEnabled()
    {
        $settings = get_option($this->slug, array());
        return isset($settings[RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon']);
    }


    /**
     * Coupon only applicable for
     * @return string
     */
    function couponFor()
    {
        $coupon_applicable_for = 'all';
        $settings = get_option($this->slug, array());
        if (!empty($settings)) {
            $coupon_applicable_for = ($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to']) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to'] : 'all';
        }
        return $coupon_applicable_for;
    }


    /**
     * Show up the survey form
     */
    function setupSurveyForm()
    {
        if (!apply_filters('rnoc_need_survey_form', true)) return false;
        $survey = new Survey();
        $survey->init(RNOC_PLUGIN_SLUG, 'Retainful - next order coupon for woocommerce', 'retainful-next-order-coupon-for-woocommerce');
    }

    /**
     * Check is background order sync enabled
     * @return bool
     */
    function isBackgroundOrderSyncEnabled()
    {
        $settings = $this->getAdminSettings();
        return isset($settings[RNOC_PLUGIN_PREFIX . 'enable_background_order_sync']) && $settings[RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'] == 'yes';
    }

    /**
     * Set identity.
     *
     * @param $value
     * @return void
     */
    function setIdentity($value = '')
    {
        if (!$this->isCustomerPage() || empty($value) || !$this->needPopupWidget()) return;
        $cookie = new Cookie();
        $cookie_data = ['email' => trim($value)];
        $cookie->removeValue('_wc_rnoc_tk_session');
        if (function_exists('wc_setcookie')) {
            wc_setcookie('_wc_rnoc_tk_session', base64_encode(json_encode($cookie_data)), strtotime('+30 days'));
        }

        //$cookie->setCookieValue('_wc_rnoc_tk_session', base64_encode(json_encode($cookie_data)), strtotime('+30 days'));
    }

    /**
     * Get identity path.
     *
     * @return string
     */
    function getIdentityPath()
    {
        $path = preg_replace('|https?://[^/]+|i', '', get_option('home'));
        return !empty($path) ? $path : '/';
    }

    /**
     * Get identity.
     *
     * @param $key
     * @param $default_value
     * @return mixed|string|null
     */
    function getIdentity($key, $default_value = '')
    {
        $cookie = new Cookie();
        if ($cookie->hasKey($key)) {
            return $cookie->getValue($key);
        }
        return $default_value;
    }

    /**
     * Set login identity data.
     *
     * @return void
     */
    function setIdentityData()
    {
        $customer_billing_email = $this->wc_functions->getCustomerBillingEmail();
        if ($this->isCustomerPage() && empty($customer_billing_email)) {
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $cookie_email = $this->getIdentity('_wc_rnoc_tk_session');
                if (is_object($user) && !empty($user->user_email) && empty($cookie_email)) {
                    $this->setIdentity($user->user_email);
                }
            }
            $cookie_email = $this->getIdentity('_wc_rnoc_tk_session');
            if (!empty($cookie_email)) {
                $cookie_email = json_decode(base64_decode($cookie_email), true);
                if (is_array($cookie_email) && !empty($cookie_email['email']))
                    $this->wc_functions->setCustomerEmail($cookie_email['email']);
            }
        }
    }

    /**
     * Is customer page.
     *
     * @return bool
     */
    function isCustomerPage()
    {
        if (function_exists( 'is_ajax' ) && is_ajax()) {
            return true;
        }
        return !is_admin();
    }

}
