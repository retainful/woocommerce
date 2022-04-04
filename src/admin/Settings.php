<?php

namespace Rnoc\Retainful\Admin;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Api\AbandonedCart\RestApi;
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
     * switch to cloud notice
     * @return string
     */
    function switchToCloudNotice()
    {
        if (!$this->isNewInstallation()) {
            $move_to_cloud_url = admin_url('admin.php?page=' . $this->slug . '_license&move_to_cloud=yes');
            return '<p style="padding: 2em;background: #ffffff;border: 1px solid #e9e9e9;box-shadow: 0 1px 1px rgba(0,0,0,.05);">' . esc_html__("Manage your abandoned carts effectively in Retainful Dashboard & get more features ", RNOC_TEXT_DOMAIN) . '&nbsp; <a class="button-primary align-right" href="' . $move_to_cloud_url . '">' . esc_html("Switch to cloud!") . '</a>&nbsp;<a href="https://www.retainful.com/blog/abandoned-cart-solutions-cloud-based-solutions-vs-self-hosted-plugin-based-solutions" target="_blank">' . __("Learn more", RNOC_TEXT_DOMAIN) . '</a></p>';
        }
        return NULL;
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

    /**
     * generate plugin activate,de-activate or delete link
     * @param $plugin
     * @param string $action
     * @return string
     */
    function pluginActionLink($plugin, $action = 'activate')
    {
        if (strpos($plugin, '/')) {
            $plugin = str_replace('\/', '%2F', $plugin);
        }
        $url = sprintf(admin_url('plugins.php?action=' . $action . '&plugin=%s&plugin_status=all&paged=1&s'), $plugin);
        $_REQUEST['plugin'] = $plugin;
        $url = wp_nonce_url($url, $action . '-plugin_' . $plugin);
        return $url;
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
    }

    /**
     * Validate app Id
     */
    function validateAppKey()
    {
        check_ajax_referer('validate_app_key', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('security breach');
        }
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
     * disconnect the app
     */
    function disconnectLicense()
    {
        check_ajax_referer('rnoc_disconnect_license', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('security breach');
        }
        $license_details = get_option($this->slug . '_license', array());
        $license_details[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'] = 0;
        update_option($this->slug . '_license', $license_details);
        wp_send_json_success(__('App disconnected successfully!', RNOC_TEXT_DOMAIN));
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

    /**
     * validate input against the alpha numeric and spaces
     * @param $field
     * @param $value
     * @param array $params
     * @param array $fields
     * @return bool
     */
    static function validateColor($field, $value, array $params, array $fields)
    {
        return (bool)preg_match('/^#(([0-9a-fA-F]{2}){3}|([0-9a-fA-F]){3})$/', $value);
    }

    /**
     * validate coupon timer post data
     * @param $validator
     */
    function validateCouponTimer($validator)
    {
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'enable_coupon_timer',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'enable_position',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'enable_checkout_button',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'enable_position',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'enable_checkout_button',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'enable_position',
        ), ['0', '1'])->message('This field contains invalid value');
        $validator->rule('color', array(
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_background',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'checkout_button_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_background',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'checkout_button_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_background',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color',
        ))->message('This field accepts only hex color code');
        $validator->rule('min', array(
            RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time'
        ), 0)->message('This field should accepts only positive value');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon',
        ), ['automatically', 'manually'])->message('This field contains invalid value');
        $validator->rule('basicTags', array(
            RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_expired_text',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_message',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_message',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'checkout_button_text',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_timer_message',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings.*.' . RNOC_PLUGIN_PREFIX . 'checkout_button_text',
        ))->message('Script tag and iframe tag were not allowed ');
        if (self::$input->has_post(RNOC_PLUGIN_PREFIX . 'enable_coupon_timer') && self::$input->post(RNOC_PLUGIN_PREFIX . 'enable_coupon_timer', '1') == 1) {
            $validator->rule('required', array(
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time'
            ))->message('This field is required');
            $validator->rule('integer', array(
                RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time'
            ))->message('This fields should contains only number');
        }
        if (!$validator->validate()) {
            wp_send_json_error($validator->errors());
        }
    }

    /**
     * validate coupon timer post data
     * @param $validator
     */
    function validateExitIntentPopup($validator)
    {
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal',
            RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings.*.' . RNOC_PLUGIN_PREFIX . 'enable_mobile_support',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings.*.' . RNOC_PLUGIN_PREFIX . 'enable_delay_trigger',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings.*.' . RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger',
        ), ['0', '1'])->message('This field contains invalid value');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings.show_option',
        ), ['once_per_page', 'every_time_on_customer_exists', 'show_x_times_per_page', 'once_per_session'])->message('This field contains invalid value');
        $validator->rule('array', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages',
        ), ['0', '1'])->message('This field contains invalid value');
        $validator->rule('min', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance',
        ), 1)->message('This field accepts only value greater than or equal to 1');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance.*.' . RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings',
        ), ['no_need_gdpr', 'dont_show_checkbox', 'show_and_check_checkbox', 'show_checkbox'])->message('This field contains invalid value');
        $validator->rule('integer', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance',
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings.show_count',
        ))->message('This field should only accepts number');
        $validator->rule('color', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_color',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_bg_color',
        ))->message('This field accepts only hex color code');
        $validator->rule('basicTags', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance.*.' . RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template',
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style'
        ))->message('Script tag and iframe tag were not allowed ');
        /*$validator->rule('regex', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder',
        ), '/^[\p{L}\p{Nd} .-]+$/')->message('This field should only accepts numbers, alphabets and spaces');*/
        /*        $validator->rule('regex', array(
                    RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style'
                ), '/^[a-z0-9%:\n\t {};.#\[\]"!]+$/')->message('This field should only accepts css values');*/
        $validator->rule('regex', array(
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_width',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_height',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_width',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design.*.' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_height',
        ), '/^[a-z0-9%]+$/')->message('This field should only accepts numbers, lowercase alphabets and percentage symbol');
        if (!$validator->validate()) {
            wp_send_json_error($validator->errors());
        }
    }

    /**
     * validate coupon timer post data
     * @param $validator
     */
    function validateAddToCartPopup($validator)
    {
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'need_modal',
            RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory',
            RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action',
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings.*.' . RNOC_PLUGIN_PREFIX . 'need_coupon',
        ), ['0', '1'])->message('This field contains invalid value');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'modal_show_popup_until',
        ), ['1', '2', '3'])->message('This field contains invalid value');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'close_btn_behavior',
        ), ['add_and_close', 'just_close'])->message('This field contains invalid value');
        $validator->rule('array', array(
            RNOC_PLUGIN_PREFIX . 'modal_display_pages',
        ))->message('This field contains invalid value');
        $validator->rule('regex', array(
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_email_field_width',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_button_field_width',
        ), '/^(\d*\.)?\d+$/')->message('This field should only accepts numbers and decimals');
        $validator->rule('color', array(
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_heading_color',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_color',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_bg_color',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_border_top_color',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_bg_color',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_no_thanks_color',
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color',
        ))->message('This field accepts only hex color code');
        $validator->rule('basicTags', array(
            RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class',
            RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance.*.' . RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message',
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings.*.' . RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template',
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_mail_template',
        ))->message('Script tag and iframe tag were not allowed ');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings.*.' . RNOC_PLUGIN_PREFIX . 'show_woo_coupon',
        ), ['instantly', 'send_via_email', 'both', 'auto_apply_and_redirect', 'send_mail_auto_apply_and_redirect_cart', 'send_mail_auto_apply_and_redirect', 'auto_apply_and_redirect_cart'])->message('This field contains invalid value');
        if (!$validator->validate()) {
            wp_send_json_error($validator->errors());
        }
    }

    function addOrderDetailMetaBoxes($post_type)
    {
        if ('shop_order' === $post_type) {
            add_meta_box('retainful_order_meta', __('Retainful token', RNOC_TEXT_DOMAIN), array($this, 'orderMetaDetails'), $post_type, 'side', 'default');
        }
    }

    function orderMetaDetails()
    {
        global $post_ID;
        $order = wc_get_order($post_ID);
        $order_id = $this->wc_functions->getOrderId($order);
        echo '<p>' . get_post_meta($order_id, "_rnoc_user_cart_token", true) . '</p>';
    }

    /**
     * save premium addon settings
     */
    function savePremiumAddOnSettings()
    {
        check_ajax_referer('rnoc_save_premium_addon_settings', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('security breach');
        }
        $post = self::$input->post();
        $validator = new Validator($post);
        Validator::addRule('float', array(__CLASS__, 'validateFloat'), 'must contain only numbers 0-9 and one dot');
        Validator::addRule('basicTags', array(__CLASS__, 'validateBasicHtmlTags'), 'Only br, strong, span,div, p tags accepted');
        Validator::addRule('color', array(__CLASS__, 'validateColor'), 'must contain only hex color code');
        $this->validateCouponTimer($validator);
        $this->validateExitIntentPopup($validator);
        $this->validateAddToCartPopup($validator);
        $page_slug = $this->slug . '_premium';
        $modal_coupon_settings = self::$input->post(RNOC_PLUGIN_PREFIX . 'modal_coupon_settings', array(), false);
        $exit_intent_popup_template = self::$input->post(RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template', '', false);
        $add_to_cart_coupon_popup_template = isset($modal_coupon_settings[0][RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template']) ? $modal_coupon_settings[0][RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template'] : '';
        $coupon_mail_template = isset($modal_coupon_settings[0][RNOC_PLUGIN_PREFIX . 'coupon_mail_template']) ? $modal_coupon_settings[0][RNOC_PLUGIN_PREFIX . 'coupon_mail_template'] : '';
        $atc_gdpr_settings = self::$input->post(RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance', array(), false);
        $add_to_cart_gdpr_message = isset($atc_gdpr_settings[0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message']) ? $atc_gdpr_settings[0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message'] : '';
        $ei_gdpr_settings = self::$input->post(RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance', array(), false);
        $ei_gdpr_message = isset($ei_gdpr_settings[0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message']) ? $ei_gdpr_settings[0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message'] : '';
        $data = $this->clean($post);
        if (!empty($exit_intent_popup_template)) {
            $data[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template'] = $this->sanitizeBasicHtml($exit_intent_popup_template);
        }
        if (!empty($add_to_cart_coupon_popup_template)) {
            $data[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template'] = $this->sanitizeBasicHtml($add_to_cart_coupon_popup_template);
        }
        if (!empty($add_to_cart_gdpr_message)) {
            $data[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message'] = $this->sanitizeBasicHtml($add_to_cart_gdpr_message);
        }
        if (!empty($ei_gdpr_message)) {
            $data[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance'][0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message'] = $this->sanitizeBasicHtml($ei_gdpr_message);
        }
        if (!empty($coupon_mail_template)) {
            $data[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_mail_template'] = $this->sanitizeBasicHtml($coupon_mail_template);
        }
        $settings = get_option($page_slug, array());
        $page = self::$input->post('addon', 'atcp');
        switch ($page) {
            default:
            case "atcp":
                if (!isset($data[RNOC_PLUGIN_PREFIX . 'modal_display_pages'])) {
                    $data[RNOC_PLUGIN_PREFIX . 'modal_display_pages'] = array();
                }
                break;
            case "ct":
                if (!isset($data[RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages'])) {
                    $data[RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages'] = array();
                }
                break;
            case "eip":
                if (!isset($data[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages'])) {
                    $data[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages'] = array();
                }
                break;
        }
        $data_to_save = wp_parse_args($data, $settings);
        update_option($page_slug, $data_to_save);
        wp_send_json_success(__('Settings successfully saved!', RNOC_TEXT_DOMAIN));
    }

    /**
     * render premium addon page
     */
    function retainfulPremiumAddOnsPage()
    {
        $page_slug = $this->slug . '_premium';
        $available_addon_list = apply_filters('rnoc_get_premium_addon_list', array());
        $base_url = admin_url('admin.php?page=' . $page_slug);
        $add_on = self::$input->get('add-on', null);
        if (!empty($add_on)) {
            $settings = get_option($page_slug, array());
            $default_settings = $this->getDefaultPremiumAddonsValues();
            $settings = wp_parse_args($settings, $default_settings);
            $add_on_slug = sanitize_text_field($add_on);
            require_once dirname(__FILE__) . '/templates/pages/premium-addon-settings.php';
        } else {
            require_once dirname(__FILE__) . '/templates/pages/premium-addons.php';
        }
    }

    /**
     * get the default values for premium addons
     * @return mixed|void
     */
    function getDefaultPremiumAddonsValues()
    {
        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'enable_coupon_timer' => '0',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon' => '',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_display_pages' => array(),
            RNOC_PLUGIN_PREFIX . 'coupon_timer_apply_coupon' => 'automatically',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_time' => '15',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_expired_text' => 'EXPIRED',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_expire_message' => __('Sorry! Instant Offer has expired.', RNOC_TEXT_DOMAIN),
            RNOC_PLUGIN_PREFIX . 'auto_fix_page_reload' => '0',
            RNOC_PLUGIN_PREFIX . 'coupon_timer_top_position_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'enable_position' => '1',
                RNOC_PLUGIN_PREFIX . 'top_bottom_position' => 'top',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_message' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_background' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'checkout_button_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'checkout_button_text' => __('Checkout Now', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'enable_checkout_button' => 1,
            )),
            RNOC_PLUGIN_PREFIX . 'coupon_timer_above_cart_position_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'enable_position' => '1',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_message' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_background' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'checkout_button_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'checkout_button_bg_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'checkout_button_text' => __('Checkout Now', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'enable_checkout_button' => 1,
            )),
            RNOC_PLUGIN_PREFIX . 'coupon_timer_below_discount_position_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'enable_position' => '1',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_message' => __("Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_display_format' => __(" {{minutes}}M {{seconds}}S", RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_timer_background' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_code_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'coupon_timer_coupon_timer_color' => '#000000'
            )),
            RNOC_PLUGIN_PREFIX . 'need_modal' => '0',
            RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory' => '1',
            RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action' => '1',
            RNOC_PLUGIN_PREFIX . 'close_btn_behavior' => 'just_close',
            RNOC_PLUGIN_PREFIX . 'modal_show_popup_until' => '1',
            RNOC_PLUGIN_PREFIX . 'no_conflict_mode' => 'yes',
            RNOC_PLUGIN_PREFIX . 'modal_display_pages' => array(),
            RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class' => '',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'modal_heading' => __('Enter your email to add this item to cart', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_heading_color' => '#000000',
                RNOC_PLUGIN_PREFIX . 'modal_email_placeholder' => __('Email address', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_email_field_width' => 70,
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_text' => __('Add to Cart', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_button_field_width' => 70,
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_bg_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_border_top_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'modal_bg_color' => '#F8F0F0',
                RNOC_PLUGIN_PREFIX . 'modal_not_mandatory_text' => __('No thanks! Add item to cart', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_add_cart_no_thanks_color' => '#f27052',
                RNOC_PLUGIN_PREFIX . 'modal_terms_text' => __('*By completing this, you are signing up to receive our emails. You can unsubscribe at any time.', RNOC_TEXT_DOMAIN),
            )),
            RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings' => 'no_need_gdpr',
                RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message' => __('I accept the <a href="#">Terms and conditions</a>', RNOC_TEXT_DOMAIN),
            )),
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'need_coupon' => '0',
                RNOC_PLUGIN_PREFIX . 'woo_coupon' => '',
                RNOC_PLUGIN_PREFIX . 'modal_sub_heading' => __('Get a discount in your email!', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color' => '#333333',
                RNOC_PLUGIN_PREFIX . 'show_woo_coupon' => 'send_via_email',
                RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template' => '',
                RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject' => __('Your coupon code', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'coupon_mail_template' => '',
            )),
            RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal' => 0,
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages' => array(),
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to' => 'all',
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon' => '',
            RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied' => 0,
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings' => array('show_option' => 'once_per_page', 'show_count' => 1),
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life' => 1,
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template' => '',
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style' => '',
            RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success' => 'checkout',
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings' => 'no_need_gdpr',
                RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message' => __('I accept the <a href="#">Terms and conditions</a>', RNOC_TEXT_DOMAIN),
            )),
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder' => __('Enter E-mail address', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_height' => '46px',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_width' => '100%',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text' => __('Complete checkout', RNOC_TEXT_DOMAIN),
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_color' => '#ffffff',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_bg_color' => '#f20561',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_height' => '100%',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_width' => '100%',
            )),
            RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings' => array(0 => array(
                RNOC_PLUGIN_PREFIX . 'enable_mobile_support' => '0',
                RNOC_PLUGIN_PREFIX . 'enable_delay_trigger' => '0',
                RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec' => '0',
                RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger' => '0',
                RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance' => '0',
            )),
        );
        return apply_filters('rnoc_premium_addon_default_values', $default_settings);
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
     * save the next order coupon settings
     */
    function saveNocSettings()
    {
        check_ajax_referer('rnoc_save_noc_settings', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('security breach');
        }
        $post = self::$input->post();
        $validator = new Validator($post);
        Validator::addRule('float', array(__CLASS__, 'validateFloat'), 'must contain only numbers 0-9 and one dot');
        Validator::addRule('basicTags', array(__CLASS__, 'validateBasicHtmlTags'), 'Only br, strong, span,div, p tags accepted');
        $validator->rule('regex', RNOC_PLUGIN_PREFIX . 'expire_date_format', '/^[a-zA-Z0-9 ,\/:-]+$/')->message('This filed should accepts number, alphabets, hypen, comma, colon and space');
        $validator->rule('array', array(
            RNOC_PLUGIN_PREFIX . 'preferred_order_status',
            RNOC_PLUGIN_PREFIX . 'preferred_user_roles',
            RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_products',
            RNOC_PLUGIN_PREFIX . 'exclude_product_categories',
            RNOC_PLUGIN_PREFIX . 'product_categories',
            RNOC_PLUGIN_PREFIX . 'exclude_products',
            RNOC_PLUGIN_PREFIX . 'products',
        ));
        $validator->rule('in', RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to', ['all', 'validate_on_checkout', 'login_users'])->message('This field contains invalid value');
        $validator->rule('in', RNOC_PLUGIN_PREFIX . 'retainful_add_coupon_message_to', ['woocommerce_email_order_details', 'woocommerce_email_order_meta', 'woocommerce_email_customer_details', 'none'])->message('This field contains invalid value');
        $validator->rule('in', array(
            RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_type',
            RNOC_PLUGIN_PREFIX . 'automatically_generate_coupon',
            RNOC_PLUGIN_PREFIX . 'show_next_order_coupon_in_thankyou_page',
            RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup',
        ), ['0', '1'])->message('This field contains invalid value');
        $validator->rule('float', array(
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount',
            RNOC_PLUGIN_PREFIX . 'minimum_sub_total',
            RNOC_PLUGIN_PREFIX . 'minimum_spend',
            RNOC_PLUGIN_PREFIX . 'maximum_spend',
        ))->message('This field contains invalid value');
        $validator->rule('min', array(
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount',
            RNOC_PLUGIN_PREFIX . 'minimum_sub_total',
            RNOC_PLUGIN_PREFIX . 'minimum_spend',
            RNOC_PLUGIN_PREFIX . 'maximum_spend',
            RNOC_PLUGIN_PREFIX . 'retainful_expire_days',
            RNOC_PLUGIN_PREFIX . 'limit_per_user',
        ), 0)->message('This field should accepts only positive value');
        $validator->rule('integer', array(
            RNOC_PLUGIN_PREFIX . 'retainful_expire_days',
            RNOC_PLUGIN_PREFIX . 'limit_per_user',
        ))->message('This field contains invalid value');;
        $validator->rule('basicTags', array(
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_message',
            RNOC_PLUGIN_PREFIX . 'coupon_applied_popup_design',
        ))->message('This field contains invalid tags script or iframe');;
        if (!$validator->validate()) {
            wp_send_json_error($validator->errors());
        }
        $coupon_msg = self::$input->post(RNOC_PLUGIN_PREFIX . 'retainful_coupon_message', '', false);
        $applied_coupon_msg = self::$input->post(RNOC_PLUGIN_PREFIX . 'coupon_applied_popup_design', '', false);
        $post = self::$input->post();
        $data = $this->clean($post);
        $data[RNOC_PLUGIN_PREFIX . 'retainful_coupon_message'] = $this->sanitizeBasicHtml($coupon_msg);
        $data[RNOC_PLUGIN_PREFIX . 'coupon_applied_popup_design'] = $this->sanitizeBasicHtml($applied_coupon_msg);
        update_option($this->slug, $data);
        wp_send_json_success(__('Settings successfully saved!', RNOC_TEXT_DOMAIN));
    }

    /**
     * default noc settings
     * @return mixed|void
     */
    function getDefaultNocSettings()
    {
        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_type' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount' => '10',
            RNOC_PLUGIN_PREFIX . 'retainful_expire_days' => '60',
            RNOC_PLUGIN_PREFIX . 'expire_date_format' => 'F j, Y',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to' => 'all',
            RNOC_PLUGIN_PREFIX . 'automatically_generate_coupon' => '1',
            RNOC_PLUGIN_PREFIX . 'show_next_order_coupon_in_thankyou_page' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_add_coupon_message_to' => 'woocommerce_email_customer_details',
            RNOC_PLUGIN_PREFIX . 'retainful_coupon_message' => '<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Go! </a></p></div></div>',
            RNOC_PLUGIN_PREFIX . 'preferred_order_status' => array('wc-processing', 'wc-completed'),
            RNOC_PLUGIN_PREFIX . 'preferred_user_roles' => array('all'),
            RNOC_PLUGIN_PREFIX . 'limit_per_user' => 99,
            RNOC_PLUGIN_PREFIX . 'minimum_sub_total' => '',
            RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_products' => array(),
            RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_categories' => array(),
            RNOC_PLUGIN_PREFIX . 'minimum_spend' => '',
            RNOC_PLUGIN_PREFIX . 'maximum_spend' => '',
            RNOC_PLUGIN_PREFIX . 'products' => array(),
            RNOC_PLUGIN_PREFIX . 'exclude_products' => array(),
            RNOC_PLUGIN_PREFIX . 'product_categories' => array(),
            RNOC_PLUGIN_PREFIX . 'exclude_product_categories' => array(),
            RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup' => '1',
            RNOC_PLUGIN_PREFIX . 'coupon_applied_popup_design' => $this->appliedCouponDefaultTemplate(),
        );
        return apply_filters('rnoc_get_default_noc_settings', $default_settings);
    }

    /**
     * next order coupon page
     */
    function nextOrderCouponPage()
    {
        $settings = get_option($this->slug, array());
        $default_settings = $this->getDefaultNocSettings();
        $settings = wp_parse_args($settings, $default_settings);
        $is_app_connected = $this->isAppConnected();
        $expiry_date_format = $this->getDateFormatOptions();
        $apply_coupon_for = array(
            'all' => __('Allow any one to apply coupon', RNOC_TEXT_DOMAIN),
            'validate_on_checkout' => __('Allow the customer to apply coupon, but validate at checkout', RNOC_TEXT_DOMAIN),
            'login_users' => __('Allow customer to apply coupon only after login (Not Recommended)', RNOC_TEXT_DOMAIN)
        );
        $display_coupon_after = array(
            'woocommerce_email_order_details' => __('Order details', RNOC_TEXT_DOMAIN),
            'woocommerce_email_order_meta' => __('Order meta', RNOC_TEXT_DOMAIN),
            'woocommerce_email_customer_details' => __('Customer details', RNOC_TEXT_DOMAIN),
            'none' => __('Do Not Show - Customers will not get next order coupon in the order confirmation email of WooCommerce', RNOC_TEXT_DOMAIN),
        );
        $order_status = $this->availableOrderStatuses();
        $user_roles = $this->getUserRoles();
        $categories = $this->getCategories();
        $is_pro_plan = $this->isProPlan();
        $unlock_premium_link = $this->unlockPremiumLink();
        require_once dirname(__FILE__) . '/templates/pages/next-order-coupon.php';
    }

    /**
     * validate the value is float or not
     * @param $field
     * @param $value
     * @param array $params
     * @param array $fields
     * @return bool
     */
    static function validateFloat($field, $value, array $params, array $fields)
    {
        return (is_numeric($value) || is_float($value));
    }

    /**
     * validate Input Text Html Tags
     *
     * @param $field
     * @param $value
     * @param array $params
     * @param array $fields
     * @return bool
     */
    static function validateBasicHtmlTags($field, $value, array $params, array $fields)
    {
        $value = stripslashes($value);
        $value = html_entity_decode($value);
        $invalid_tags = array("script", "iframe");
        foreach ($invalid_tags as $tag_name) {
            $pattern = "#<\s*?$tag_name\b[^>]*>(.*?)</$tag_name\b[^>]*>#s";;
            preg_match($pattern, $value, $matches);
            //script or iframe found
            if (!empty($matches)) {
                return false;
            }
        }
        return true;
    }

    /**
     * save the settings
     */
    function saveAcSettings()
    {
        check_ajax_referer('rnoc_save_settings', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('security breach');
        }
        $post = self::$input->post();
        $validator = new Validator($post);
        $validator->rule('in', RNOC_PLUGIN_PREFIX . 'cart_tracking_engine', ['js', 'php'])->message('This field contains invalid value');
        $validator->rule('in', RNOC_PLUGIN_PREFIX . 'track_zero_value_carts', ['yes', 'no'])->message('This field contains invalid value');
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
        $data[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'] = $this->sanitizeBasicHtml($cart_capture_msg);
        update_option($this->slug . '_settings', $data);
        wp_send_json_success(__('Settings successfully saved!', RNOC_TEXT_DOMAIN));
    }

    /**
     * retainful ac settings page
     */
    function retainfulSettingsPage()
    {
        $settings = $this->getAdminSettings();
        $default_settings = array(
            RNOC_PLUGIN_PREFIX . 'cart_tracking_engine' => 'js',
            RNOC_PLUGIN_PREFIX . 'track_zero_value_carts' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_referral_widget' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_popup_widget' => 'no',
            RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget' => 'yes',
            RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status' => '1',
            RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status' => '0',
            RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load' => '0',
            RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance' => '0',
            RNOC_PLUGIN_PREFIX . 'cart_capture_msg' => '',
            RNOC_PLUGIN_PREFIX . 'enable_ip_filter' => '0',
            RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses' => '',
            RNOC_PLUGIN_PREFIX . 'enable_debug_log' => '0',
            RNOC_PLUGIN_PREFIX . 'handle_storage_using' => 'woocommerce',
            RNOC_PLUGIN_PREFIX . 'varnish_check' => 'no',
        );
        $settings = wp_parse_args($settings, $default_settings);
        require_once dirname(__FILE__) . '/templates/pages/settings.php';
    }

    function getRetainfulSettingValue($key, $default = null){
        $settings = $this->getAdminSettings();
        return (!empty($key) && isset($settings[$key])) ? $settings[$key]: $default;
    }
    /**
     * register plugin related menus
     */
    function registerMenu()
    {
        add_menu_page('Retainful', 'Retainful', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'), 'dashicons-controls-repeat', 56);
        add_submenu_page('retainful_license', 'Connection', 'Connection', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'));
        add_submenu_page('retainful_license', 'Settings', 'Settings', 'manage_woocommerce', 'retainful_settings', array($this, 'retainfulSettingsPage'));
        $settings = get_option($this->slug . '_settings', array());
        $is_next_order_disable = get_option('retainful_hide_next_order_coupon', 'no');
        if(($is_next_order_disable === 'no' || empty($is_next_order_disable)) && (empty($settings) || count($settings) < 3)){
            update_option('retainful_hide_next_order_coupon', 'yes');
        }
        $can_hide_next_order_coupon = get_option('retainful_hide_next_order_coupon','no');
        if($can_hide_next_order_coupon !== 'yes'){
            add_submenu_page('retainful_license', 'Settings', 'Next order coupon', 'manage_woocommerce', 'retainful', array($this, 'nextOrderCouponPage'));
        }
        add_submenu_page('retainful_license', 'Settings', 'Premium features', 'manage_woocommerce', 'retainful_premium', array($this, 'retainfulPremiumAddOnsPage'));

        //add_submenu_page('woocommerce', 'Retainful', 'Retainful - Abandoned cart', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'));
    }

    /**
     * applied Coupon Default Template
     * @return string
     */
    function appliedCouponDefaultTemplate()
    {
        return '<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_code}} was successfully applied to your cart!</h3><p style="margin:10px auto; ">Enjoy your shopping :)</p><p style="text-align: center; margin: 0;"><a href="{{shop_url}}" style="text-decoration: none;line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff;">Continue shopping!</a></p></div></div>';
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
            'meta_query' => array(
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
        $this->removeFinishedHooks('rnoc_abandoned_cart_send_email', 'pending');
    }

    /**
     * Schedule events to check plan
     */
    function schedulePlanChecker()
    {
        $this->scheduleEvents('rnocp_check_user_plan', current_time('timestamp'), array(), 'recurring', 604800);
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
        return array('rnocp_check_user_plan', 'rnoc_abandoned_clear_abandoned_carts', 'rnoc_abandoned_cart_send_email');
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
            $scheduled_actions = $wpdb->get_results("SELECT ID from `{$wpdb->prefix}posts` where post_title ='{$post_title}' {$post_where} AND  post_type='scheduled-action' LIMIT 500");
            if (!empty($scheduled_actions)) {
                foreach ($scheduled_actions as $action) {
                    if (wp_delete_post($action->ID, true)) {
                        do_action('action_scheduler_deleted_action', $action->ID);
                    }
                }
            }
            //When custom table is being used by scheduler
            $custom_table_name = $wpdb->base_prefix . 'actionscheduler_actions';
            $query = $wpdb->prepare('SHOW TABLES LIKE %s', $custom_table_name);
            $found_table = $wpdb->get_var($query);
            if ($wpdb->get_var($query) == $custom_table_name) {
                $custom_table_where = (!empty($status)) ? "AND status = '{$status}'" : "";
                $scheduled_actions = $wpdb->get_results("SELECT action_id from `{$custom_table_name}` where hook ='{$post_title}' {$custom_table_where} LIMIT 500");
                if (!empty($scheduled_actions)) {
                    foreach ($scheduled_actions as $action) {
                        $deleted = $wpdb->delete($custom_table_name, array('action_id' => $action->action_id), array('%d'));
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
     * Set the option to manage Abandoned cart to manage in cloud
     */
    function setAbandonedCartToManageInCloud()
    {
        $this->unScheduleHooks();
        update_option('retainful_run_abandoned_cart_in_cloud', 1);
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
        $admin_settings = $this->getAdminSettings();
        if (isset($admin_settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log']) && !empty($admin_settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log']) && !empty($message)) {
            try {
                if (is_array($message) || is_object($message)) {
                    $message = json_encode($message);
                }
                $to_print = $log_in_as . ":\n";
                $to_print .= $message;
                $file = fopen(RNOC_LOG_FILE_PATH, 'a');
                $content = "\n\n Time :" . current_time('mysql', true) . ' | ' . $to_print;
                fwrite($file, $content);
                fclose($file);
            } catch (\Exception $e) {
                $e->getMessage();
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
     * Check the current installation is new or not
     * @return bool
     */
    function isInstalledFresh()
    {
        global $wpdb;
        $tables_list = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        $required_tables = array($wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_cart_history', $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_templates');
        if (!empty($tables_list)) {
            foreach ($tables_list as $table_name) {
                if (count(array_intersect($required_tables, $table_name)) > 0) {
                    return 0;
                }
            }
        }
        return 1;
    }

    /**
     * get all available order statuses
     * @return array
     */
    function availableOrderStatuses()
    {
        $woo_functions = new WcFunctions();
        $woo_statuses = $woo_functions->getAvailableOrderStatuses();
        if (is_array($woo_statuses)) {
            if (isset($woo_statuses['wc-pending'])) {
                unset($woo_statuses['wc-pending']);
            }
            return $woo_statuses;
        }
        return array();
    }

    /**
     * Get the user current plan
     * @return mixed|string
     */
    function getUserActivePlan()
    {
        $plan_details = $this->getPlanDetails();
        return strtolower(trim(isset($plan_details['plan']) ? $plan_details['plan'] : 'free'));
    }

    /**
     * Get the user current plan
     * @return mixed|string
     */
    function getUserPlanStatus()
    {
        $plan_details = $this->getPlanDetails();
        return strtolower(trim(isset($plan_details['status']) ? $plan_details['status'] : 'inactive'));
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

    /**
     * is referral widget is required for store
     * @return mixed|void
     */
    function needReferralWidget()
    {
        $settings = $this->getAdminSettings();
        $need_widget = (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_referral_widget']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'enable_referral_widget'])) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_referral_widget'] : 'no';
        return apply_filters("retainful_enable_referral_program", ($need_widget === "yes"));
    }

    function needPopupWidget()
    {
        $settings = $this->getAdminSettings();
        $need_widget = (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_popup_widget']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'enable_popup_widget'])) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_popup_widget'] : 'no';
        return apply_filters("retainful_enable_popup_widget", ($need_widget === "yes"));
    }

    /**
     * is embeded referral widget is required in my account page
     * @return mixed|void
     */
    function needEmbededReferralWidget()
    {
        $settings = $this->getAdminSettings();
        $need_widget = (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget'])) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget'] : 'yes';
        return apply_filters("enable_embeded_referral_widget", ($need_widget === "yes"));
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
     * Get the abandoned cart settings
     * @return array|mixed
     */
    function getPremiumAddonSettings()
    {
        $abandoned_cart = get_option($this->slug . '_premium', array());
        if (empty($abandoned_cart))
            $abandoned_cart = array();
        return $abandoned_cart;
    }

    /**
     * Get the abandoned cart settings
     * @return array|mixed
     */
    function getEmailTemplatesSettings()
    {
        $abandoned_cart_email_templates = get_option($this->slug . '_abandoned_cart_email_templates', array());
        if (empty($abandoned_cart_email_templates))
            $abandoned_cart_email_templates = array();
        return $abandoned_cart_email_templates;
    }

    /**
     * Coupon expire date format list
     * @return array
     */
    function getDateFormatOptions()
    {
        $date_formats = array(
            'F j, Y' => get_date_from_gmt(date('Y-m-d h:i:s'), 'F j, Y'),
            'Y-m-d' => get_date_from_gmt(date('Y-m-d h:i:s'), 'Y-m-d'),
            'Y/m/d' => get_date_from_gmt(date('Y-m-d h:i:s'), 'Y/m/d'),
            'd-m-Y' => get_date_from_gmt(date('Y-m-d h:i:s'), 'd-m-Y'),
            'd/m/Y' => get_date_from_gmt(date('Y-m-d h:i:s'), 'd/m/Y'),
        );
        return apply_filters('rnoc_dateformat_options', $date_formats);
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
        check_ajax_referer('rnoc_delete_expired_coupons', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('security breach');
        }
        $args = array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => 100,
            'meta_query' => array(
                array(
                    'key' => '_rnoc_shop_coupon_type',
                    'value' => array('retainful', 'retainful-referral'),
                    'compare' => 'IN'
                ), array(
                    'key' => 'date_expires',
                    'value' => strtotime(date('Y-m-d h:i:s')),
                    'compare' => '<'
                ), array(
                    'key' => 'usage_count',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        $posts = get_posts($args);
        if ($posts) {
            foreach ($posts as $post) {
                wp_delete_post($post->ID, true);
            }
        }
        wp_send_json_success(array('message' => "successfully deleted"));
    }

    /**
     * Make coupon expire date from order date
     * @param $ordered_date
     * @return array
     */
    function getCouponExpireDate($ordered_date)
    {
        $response = array(
            'woo_coupons' => null,
            'retainful_coupons' => null
        );
        if (empty($ordered_date))
            return $response;
        $settings = get_option($this->slug, array());
        $expire_days = isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_expire_days']) ? intval($settings[RNOC_PLUGIN_PREFIX . 'retainful_expire_days']) : 0;
        if ($this->isAppConnected() && $expire_days > 0) {
            $response['retainful_coupons'] = $this->addDaysToDate($ordered_date, $expire_days);
            $response['woo_coupons'] = $this->addDaysToDate($ordered_date, $expire_days + 1);
        }
        return $response;
    }

    function addDaysToDate($date, $days)
    {
        if ($date && intval($days) > 0) {
            $date = $this->formatDate($date, 'Y-m-d');
            if (!empty($date)) {
                $timestamp = strtotime($date);
                $days_in_seconds = intval($days) * 86400;
                $last_date_timestamp = $timestamp + $days_in_seconds;
                $last_date = date('Y-m-d H:i:s', $last_date_timestamp);
                return $this->formatDate($last_date, \DateTime::ATOM);
            }
        }
        return null;
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
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : "";
        $prefix = substr($page, 0, 9);
        if ($prefix != "retainful") {
            return;
        }
        $asset_path = plugins_url('', __FILE__);
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
     * get coupon date format
     * @return mixed|string
     */
    function getExpireDateFormat()
    {
        $usage_restriction = $this->getUsageRestrictions();
        if (isset($usage_restriction[RNOC_PLUGIN_PREFIX . 'expire_date_format']) && !empty($usage_restriction[RNOC_PLUGIN_PREFIX . 'expire_date_format'])) {
            return $usage_restriction[RNOC_PLUGIN_PREFIX . 'expire_date_format'];
        }
        return 'F j, Y, g:i a';
    }

    /**
     *
     * Get all categories
     * @return array - list of all categories
     */
    function getCategories()
    {
        $categories = array();
        $category_list = get_terms('product_cat', array(
            'orderby' => 'name',
            'order' => 'asc',
            'hide_empty' => false
        ));
        if (!empty($category_list)) {
            foreach ($category_list as $category) {
                if (is_object($category) && isset($category->term_id) && isset($category->name)) {
                    $categories[$category->term_id] = $category->name;
                }
            }
        }
        return $categories;
    }

    /**
     *
     * Get all user roles
     * @return array - list of all user roles
     */
    function getUserRoles()
    {
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $user_roles = array('all' => __('All', RNOC_TEXT_DOMAIN));
        if (!empty($all_roles)) {
            foreach ($all_roles as $role_name => $role) {
                $user_roles[$role_name] = isset($role['name']) ? $role['name'] : '';
            }
        }
        return $user_roles;
    }

    /**
     * get the plan details of the API
     * @return array|mixed
     */
    function getPlanDetails()
    {
        $plan_details = get_option('rnoc_plan_details', array());
        if (empty($plan_details)) {
            $api_key = $this->getApiKey();
            $secret_key = $this->getSecretKey();
            if (!empty($api_key)) {
                $api_obj = new RestApi();
                $store_data = array(
                    'secret_key' => $api_obj->encryptData($api_key, $secret_key));
                $this->isApiEnabled($api_key, $secret_key,$store_data);
            } else {
                $this->updateUserAsFreeUser();
            }
            $plan_details = get_option('rnoc_plan_details', array());
        }
        if (empty($plan_details)) {
            $plan_details = array(
                'plan' => 'free',
                'status' => 'active',
                'expired_on' => 'never'
            );
        }
        return $plan_details;
    }

    /**
     * Check the user plan is pro
     * @return bool
     */
    function isProPlan()
    {
        $plan = $this->getUserActivePlan();
        $status = $this->getUserPlanStatus();
        $plan = strtolower($plan);
        return (in_array($plan, array('pro', 'business', 'professional')) && in_array($status, array('active','trialing')));
    }

    /**
     * Link to unlock premium
     * @return string
     */
    function unlockPremiumLink()
    {
        return '<a href="' . $this->api->upgradePremiumUrl() . '">' . __("Unlock this feature by upgrading to Premium", RNOC_TEXT_DOMAIN) . '</a>';
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
                    $this->updateUserAsFreeUser();
                    return array('error' => $details);
                } else {
                    $this->updatePlanDetails($details);
                    return array('success' => isset($details['message']) ? $details['message'] : NULL);
                }
            } else {
                $this->updateUserAsFreeUser();
                return false;
            }
        } else {
            $this->updateUserAsFreeUser();
            return false;
        }
    }

    /**
     * update user as Free user
     */
    function updateUserAsFreeUser()
    {
        $details = $this->api->getPlanDetails();
        $this->updatePlanDetails($details);
    }

    /**
     * update the plan details
     * @param array $details
     */
    function updatePlanDetails($details = array())
    {
        update_option('rnoc_plan_details', $details);
        update_option('rnoc_last_plan_checked', current_time('timestamp'));
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
     * Get Admin API key
     * @return String|null
     */
    function getCouponMessage()
    {
        $settings = get_option($this->slug, array());
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_message']) && !empty(isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_message']))) {
            return __($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_message'], RNOC_TEXT_DOMAIN);
        } else {
            return __('<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Go! </a></p></div></div>', RNOC_TEXT_DOMAIN);
        }
    }

    /**
     * Check is next order coupon enabled
     * @return bool
     */
    function isNextOrderCouponEnabled()
    {
        $settings = get_option($this->slug, array());
        if (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon']) && empty($settings[RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon'])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * get coupon settings from admin
     * @return array
     */
    function getCouponSettings()
    {
        $coupon = array();
        $settings = get_option($this->slug, array());
        if (!empty($settings)) {
            $coupon['coupon_type'] = isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_type']) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_type'] : 0;
            $coupon['coupon_applicable_to'] = isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to']) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to'] : 'all';
            $coupon['coupon_amount'] = isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount']) && ($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount'] > 0) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount'] : 0;
            $coupon['product_ids'] = isset($settings[RNOC_PLUGIN_PREFIX . 'products']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'products']) ? $settings[RNOC_PLUGIN_PREFIX . 'products'] : array();
            $coupon['exclude_product_ids'] = isset($settings[RNOC_PLUGIN_PREFIX . 'exclude_products']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'exclude_products']) ? $settings[RNOC_PLUGIN_PREFIX . 'exclude_products'] : array();
            $coupon['minimum_amount'] = isset($settings[RNOC_PLUGIN_PREFIX . 'minimum_spend']) && ($settings[RNOC_PLUGIN_PREFIX . 'minimum_spend'] > 0) ? $settings[RNOC_PLUGIN_PREFIX . 'minimum_spend'] : 0;
            $coupon['maximum_amount'] = isset($settings[RNOC_PLUGIN_PREFIX . 'maximum_spend']) && ($settings[RNOC_PLUGIN_PREFIX . 'maximum_spend'] > 0) ? $settings[RNOC_PLUGIN_PREFIX . 'maximum_spend'] : 0;
            $coupon['individual_use'] = isset($settings[RNOC_PLUGIN_PREFIX . 'individual_use_only']) && ($settings[RNOC_PLUGIN_PREFIX . 'individual_use_only'] == 1) ? 'yes' : 'no';
            $coupon['exclude_sale_items'] = isset($settings[RNOC_PLUGIN_PREFIX . 'exclude_sale_items']) && ($settings[RNOC_PLUGIN_PREFIX . 'exclude_sale_items'] == 1) ? 'yes' : 'no';
            $coupon['product_categories'] = isset($settings[RNOC_PLUGIN_PREFIX . 'product_categories']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'product_categories']) ? $settings[RNOC_PLUGIN_PREFIX . 'product_categories'] : array();
            $coupon['exclude_product_categories'] = isset($settings[RNOC_PLUGIN_PREFIX . 'exclude_product_categories']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'exclude_product_categories']) ? $settings[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'] : array();
        }
        return $coupon;
    }

    /**
     * get coupon settings from admin
     * @return array
     */
    function getCouponValidOrderStatuses()
    {
        $statuses = array('wc-processing', 'wc-completed');
        $settings = get_option($this->slug, array());
        if (!empty($settings)) {
            return isset($settings[RNOC_PLUGIN_PREFIX . 'preferred_order_status']) ? $settings[RNOC_PLUGIN_PREFIX . 'preferred_order_status'] : $statuses;
        }
        return $statuses;
    }

    /**
     * get coupon settings from admin
     * @return string
     */
    function showCouponInThankYouPage()
    {
        $show_on_thankyou_page = 0;
        $settings = get_option($this->slug, array());
        if (!empty($settings)) {
            return isset($settings[RNOC_PLUGIN_PREFIX . 'show_next_order_coupon_in_thankyou_page']) ? $settings[RNOC_PLUGIN_PREFIX . 'show_next_order_coupon_in_thankyou_page'] : $show_on_thankyou_page;
        }
        return $show_on_thankyou_page;
    }

    /**
     * get coupon settings from admin
     * @return string
     */
    function enableCouponResponsePopup()
    {
        $enable = 1;
        $settings = get_option($this->slug, array());
        if (!empty($settings)) {
            return isset($settings[RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup']) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup'] : $enable;
        }
        return $enable;
    }

    /**
     * get coupon settings from admin
     * @return array
     */
    function getCouponValidUserRoles()
    {
        $roles = array('all');
        if ($this->isProPlan()) {
            $usage_restrictions = get_option($this->slug, array());
            if (!empty($usage_restrictions)) {
                return isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'preferred_user_roles']) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'preferred_user_roles'] : $roles;
            }
        }
        return $roles;
    }

    /**
     * get coupon Limit per email
     * @return integer
     */
    function getCouponLimitPerUser()
    {
        $limit = 99;
        if ($this->isProPlan()) {
            $settings = get_option($this->slug, array());
            if (!empty($settings)) {
                return isset($settings[RNOC_PLUGIN_PREFIX . 'limit_per_user']) ? $settings[RNOC_PLUGIN_PREFIX . 'limit_per_user'] : $limit;
            }
        }
        return $limit;
    }

    /**
     * get coupon Limit per email
     * @return integer
     */
    function getMinimumOrderTotalForCouponGeneration()
    {
        $minimum_sub_total = 0;
        if ($this->isProPlan()) {
            $settings = get_option($this->slug, array());
            if (!empty($settings)) {
                return isset($settings[RNOC_PLUGIN_PREFIX . 'minimum_sub_total']) ? $settings[RNOC_PLUGIN_PREFIX . 'minimum_sub_total'] : $minimum_sub_total;
            }
        }
        return $minimum_sub_total;
    }

    /**
     * get invalid products for coupon creation
     * @return array
     */
    function getInvalidProductsForCoupon()
    {
        $products = array();
        if ($this->isProPlan()) {
            $settings = get_option($this->slug, array());
            if (!empty($settings)) {
                return isset($settings[RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_products']) ? $settings[RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_products'] : $products;
            }
        }
        return $products;
    }

    /**
     * get invalid categories for coupon creation
     * @return array
     */
    function getInvalidCategoriesForCoupon()
    {
        $categories = array();
        if ($this->isProPlan()) {
            $settings = get_option($this->slug, array());
            if (!empty($settings)) {
                return isset($settings[RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_categories']) ? $settings[RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_categories'] : $categories;
            }
        }
        return $categories;
    }

    /**
     * get coupon settings from admin
     * @return bool
     */
    function autoGenerateCouponsForOldOrders()
    {
        $settings = get_option($this->slug, array());
        if (!empty($settings)) {
            if (isset($settings[RNOC_PLUGIN_PREFIX . 'automatically_generate_coupon']) && $settings[RNOC_PLUGIN_PREFIX . 'automatically_generate_coupon'] == 0) {
                return false;
            }
        }
        return true;
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
     * Coupon only applicable for
     * @return string
     */
    function couponMessageHook()
    {
        $hook = 'woocommerce_email_customer_details';
        $settings = get_option($this->slug, array());
        if (!empty($settings)) {
            $hook = ($settings[RNOC_PLUGIN_PREFIX . 'retainful_add_coupon_message_to']) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_add_coupon_message_to'] : 'woocommerce_email_customer_details';
        }
        return $hook;
    }

    /**
     * Send Coupon details to server
     * @param $url
     * @param $params
     * @return bool
     */
    function sendCouponDetails($url, $params)
    {
        if (!isset($params['app_id'])) {
            $params['app_id'] = $this->getApiKey();
        }
        $url = $this->api->domain . $url;
        $response = $this->api->request($url, $params);
        if (isset($response->success) && $response->success) {
            //Do any stuff if success
            return true;
        } else {
            //Log messages if request get failed
            return false;
        }
    }

    /**
     * Show up the survey form
     */
    function setupSurveyForm()
    {
        if (!apply_filters('rnoc_need_survey_form', true)) return false;
        $survey = new Survey();
        $survey->init(RNOC_PLUGIN_SLUG, 'Retainful - next order coupon for woocommerce', RNOC_TEXT_DOMAIN);
    }
}