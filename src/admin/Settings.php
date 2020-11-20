<?php

namespace Rnoc\Retainful\Admin;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Helpers\Input;
use Rnoc\Retainful\Integrations\MultiLingual;
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
        if ((is_admin() || is_blog_admin()) && in_array($page, array('retainful', 'retainful_settings', 'retainful_premium', 'retainful_license'))) {
            $this->addScript();
        }
    }

    /**
     * Add settings link
     * @param $links
     * @return array
     */
    function pluginActionLinks($links)
    {
        $action_links = array(
            'license' => '<a href="' . admin_url('admin.php?page=retainful_license') . '">' . __('Connection', RNOC_TEXT_DOMAIN) . '</a>',
            'premium_add_ons' => '<a href="' . admin_url('admin.php?page=retainful_premium') . '">' . __('Add-ons', RNOC_TEXT_DOMAIN) . '</a>',
        );
        return array_merge($action_links, $links);
    }

    /**
     * render retainful license page
     */
    function retainfulLicensePage()
    {
        global $retainful;
        $settings = $retainful::$settings->get('connection', null, array(), true);
        require_once dirname(__FILE__) . '/templates/pages/connection.php';
    }

    /**
     * Validate app Id
     */
    function validateAppKey()
    {
        global $retainful;
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
        $app_id = self::$input->post('app_id');
        $secret_key = self::$input->post('secret_key');
        $options_data = array(
            RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => '0',
            RNOC_PLUGIN_PREFIX . 'retainful_app_id' => $app_id,
            RNOC_PLUGIN_PREFIX . 'retainful_app_secret' => $secret_key
        );
        //Save app id before validate key
        $retainful::$settings->set('connection', $options_data);
        $response = array();
        $this->updateUserAsFreeUser();
        if (empty($response)) {
            $api_response = $this->isApiEnabled($app_id, $secret_key);
            if (isset($api_response['success'])) {
                //Change app id status
                $options_data[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'] = 1;
                $retainful::$settings->set('connection', $options_data);
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
        global $retainful;
        check_ajax_referer('rnoc_disconnect_license', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('security breach');
        }
        $license_details = $retainful::$settings->get('connection', null, array(), true);
        $license_details[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'] = 0;
        $retainful::$settings->set('connection', $license_details);
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
                    'style' => array(),
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
        /*$validator->rule('regex', array(
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_email_placeholder',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_text',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_not_mandatory_text',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_terms_text',
            RNOC_PLUGIN_PREFIX . 'modal_design_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_heading',
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings.*.' . RNOC_PLUGIN_PREFIX . 'modal_sub_heading',
            RNOC_PLUGIN_PREFIX . 'modal_coupon_settings.*.' . RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject',
        ), '/^[\p{L}\p{Nd} .!:*,-]+$/')->message('This field should only accepts numbers, alphabets and spaces');*/
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

    /**
     * save premium addon settings
     */
    function savePremiumAddOnSettings()
    {
        global $retainful;
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
        $settings = $retainful::$settings->get('premium', null, array(), true);
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
        $retainful::$settings->set('premium', $data_to_save);
        wp_send_json_success(__('Settings successfully saved!', RNOC_TEXT_DOMAIN));
    }

    /**
     * render premium addon page
     */
    function retainfulPremiumAddOnsPage()
    {
        global $retainful;
        $page_slug = $this->slug . '_premium';
        $available_addon_list = apply_filters('rnoc_get_premium_addon_list', array());
        $base_url = $retainful::$settings->getPageUrl('premium');
        $add_on = self::$input->get('add-on', null);
        if (!empty($add_on)) {
            $settings = $retainful::$settings->get('premium', null, array(), true);
            $add_on_slug = sanitize_text_field($add_on);
            require_once dirname(__FILE__) . '/templates/pages/premium-addon-settings.php';
        } else {
            require_once dirname(__FILE__) . '/templates/pages/premium-addons.php';
        }
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
            return is_scalar($var) ? stripslashes(sanitize_text_field($var)) : $var;
        }
    }

    /**
     * save the next order coupon settings
     */
    function saveNocSettings()
    {
        global $retainful;
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
        $validator->rule('in', RNOC_PLUGIN_PREFIX . 'retainful_add_coupon_message_to', ['woocommerce_email_order_details', 'woocommerce_email_order_meta', 'woocommerce_email_customer_details'])->message('This field contains invalid value');
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
        $retainful::$settings->set('next_order_coupon', $data);
        wp_send_json_success(__('Settings successfully saved!', RNOC_TEXT_DOMAIN));
    }

    /**
     * next order coupon page
     */
    function nextOrderCouponPage()
    {
        global $retainful;
        $settings = $retainful::$settings->get('next_order_coupon', null, array(), true);
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
            'woocommerce_email_customer_details' => __('Customer details', RNOC_TEXT_DOMAIN)
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
        global $retainful;
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
            RNOC_PLUGIN_PREFIX . 'move_email_field_to_top',
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
        $retainful::$settings->set('general_settings', $data);
        wp_send_json_success(__('Settings successfully saved!', RNOC_TEXT_DOMAIN));
    }

    /**
     * retainful ac settings page
     */
    function retainfulSettingsPage()
    {
        global $retainful;
        $settings = $retainful::$settings->get('general_settings', null, array(), true);
        require_once dirname(__FILE__) . '/templates/pages/settings.php';
    }

    /**
     * move top of the field
     * @return bool
     */
    function moveEmailFieldToTop()
    {
        global $retainful;
        $move_to_top = $retainful::$settings->get('general_settings', RNOC_PLUGIN_PREFIX . 'move_email_field_to_top', 0);
        return ($move_to_top == 1);
    }

    /**
     * register plugin related menus
     */
    function registerMenu()
    {
        add_menu_page('Retainful', 'Retainful', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'), 'dashicons-controls-repeat', 56);
        add_submenu_page('retainful_license', 'Connection', 'Connection', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'));
        add_submenu_page('retainful_license', 'Settings', 'Settings', 'manage_woocommerce', 'retainful_settings', array($this, 'retainfulSettingsPage'));
        add_submenu_page('retainful_license', 'Settings', 'Next order coupon', 'manage_woocommerce', 'retainful', array($this, 'nextOrderCouponPage'));
        add_submenu_page('retainful_license', 'Settings', 'Premium features', 'manage_woocommerce', 'retainful_premium', array($this, 'retainfulPremiumAddOnsPage'));
        //add_submenu_page('woocommerce', 'Retainful', 'Retainful - Abandoned cart', 'manage_woocommerce', 'retainful_license', array($this, 'retainfulLicensePage'));
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
        $available_action_names = $this->availableScheduledActions();
        if (!empty($post_title) && !in_array($post_title, $available_action_names)) {
            return false;
        }
        global $wpdb;
        $res = true;
        $where = "";
        if (!empty($status)) {
            $where = "AND post_status = '" . $status . "'";
        }
        $scheduled_actions = $wpdb->get_results("SELECT ID from `" . $wpdb->prefix . "posts` where post_title ='" . $post_title . "' {$where} AND  post_type='scheduled-action'");
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
     * Create log file named retainful.log
     * @param $message
     * @param $log_in_as
     */
    function logMessage($message, $log_in_as = "checkout")
    {
        global $retainful;
        $enable_log = $retainful::$settings->get('general_settings', RNOC_PLUGIN_PREFIX . 'enable_debug_log', 0);
        if ($enable_log == 1) {
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
        global $retainful;
        return $retainful::$settings->get('general_settings', RNOC_PLUGIN_PREFIX . 'handle_storage_using', "woocommerce");
    }

    /**
     * get all available order statuses
     * @return array
     */
    function availableOrderStatuses()
    {
        global $retainful;
        $woo_statuses = $retainful::$woocommerce->getAvailableOrderStatuses();
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
     * get the cart tracking engine
     * @return mixed|string
     */
    function getCartTrackingEngine()
    {
        global $retainful;
        return $retainful::$settings->get('general_settings', RNOC_PLUGIN_PREFIX . 'cart_tracking_engine', 'js');
    }

    /**
     * get the cart tracking engine
     * @return mixed|string
     */
    function trackZeroValueCarts()
    {
        global $retainful;
        return $retainful::$settings->get('general_settings', RNOC_PLUGIN_PREFIX . 'track_zero_value_carts', 'no');
    }

    /**
     * Get the abandoned cart settings
     * @return array|mixed
     */
    function getPremiumAddonSettings()
    {
        global $retainful;
        return $retainful::$settings->get('premium', '', array(), true);
    }

    /**
     * Coupon expire date format list
     * @return array
     */
    function getDateFormatOptions()
    {
        return array(
            'jS D M g:i a' => get_date_from_gmt(date('Y-m-d h:i:s'), 'jS D M g:i a'),
            'jS D M, Y g:i a' => get_date_from_gmt(date('Y-m-d h:i:s'), 'jS D M, Y g:i a'),
            'F j, Y, g:i a' => get_date_from_gmt(date('Y-m-d h:i:s'), 'F j, Y, g:i a'),
            'Y-m-d' => get_date_from_gmt(date('Y-m-d h:i:s'), 'Y-m-d'),
            'Y-m-d h:i:s' => get_date_from_gmt(date('Y-m-d h:i:s'), 'Y-m-d h:i:s'),
            'Y-m-d h:i a' => get_date_from_gmt(date('Y-m-d h:i:s'), 'Y-m-d h:i a'),
            'd/m/Y' => get_date_from_gmt(date('Y-m-d h:i:s'), 'd/m/Y'),
            'd/m/Y h:i:s' => get_date_from_gmt(date('Y-m-d h:i:s'), 'd/m/Y h:i:s'),
            'd/m/Y h:i a' => get_date_from_gmt(date('Y-m-d h:i:s'), 'd/m/Y h:i a'),
        );
    }

    /**
     * search for coupons
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

    /**
     * Make coupon expire date from order date
     * @param $ordered_date
     * @return string|null
     */
    function getCouponExpireDate($ordered_date)
    {
        global $retainful;
        if (empty($ordered_date))
            return NULL;
        $expire_days = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'retainful_expire_days', 60);
        if ($this->isAppConnected() && !empty($expire_days)) {
            try {
                $expiry_date = new \DateTime($ordered_date);
                $expiry_date->add(new \DateInterval('P' . $expire_days . 'D'));
                return $expiry_date->format(\DateTime::ATOM);
            } catch (\Exception $e) {
                return NULL;
            }
        }
        return NULL;
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
        global $retainful;
        return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'expire_date_format', 'F j, Y, g:i a');
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
                $this->isApiEnabled($api_key, $secret_key);
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
        return (in_array($plan, array('pro', 'business', 'professional')) && in_array($status, array('active')));
    }

    /**
     * Link to unlock premium
     * @return string
     */
    function unlockPremiumLink()
    {
        global $retainful;
        return '<a href="' . $retainful::$api->upgradePremiumUrl() . '">' . __("Unlock this feature by upgrading to Premium", RNOC_TEXT_DOMAIN) . '</a>';
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
        global $retainful;
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
            if ($details = $retainful::$api->validateApi($api_key, $store_data)) {
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
        global $retainful;
        $details = $retainful::$api->getPlanDetails();
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
     * Check fo entered API key is valid or not
     * @return bool
     */
    function isAppConnected()
    {
        global $retainful;
        $is_connected = $retainful::$settings->get('connection', RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0);
        return ($is_connected == 1);
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    function getApiKey()
    {
        global $retainful;
        return $retainful::$settings->get('connection', RNOC_PLUGIN_PREFIX . 'retainful_app_id', null);
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    function getSecretKey()
    {
        global $retainful;
        return $retainful::$settings->get('connection', RNOC_PLUGIN_PREFIX . 'retainful_app_secret', null);
    }

    /**
     * Check the site has multi currency
     * @return bool
     */
    function getBaseCurrency()
    {
        global $retainful;
        $base_currency = $retainful::$woocommerce->getDefaultCurrency();
        return apply_filters('rnoc_get_default_currency_code', $base_currency);
    }

    /**
     * Check the site has multi currency
     * @return bool
     */
    function getAllAvailableCurrencies()
    {
        global $retainful;
        $base_currency = $retainful::$woocommerce->getDefaultCurrency();
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
        global $retainful;
        $scheme = wc_site_is_https() ? 'https' : 'http';
        $country_details = get_option('woocommerce_default_country');
        list($country_code, $state_code) = explode(':', $country_details);
        $lang_helper = new MultiLingual();
        $default_language = $lang_helper->getDefaultLanguage();
        return array(
            'woocommerce_app_id' => $api_key,
            'secret_key' => $retainful::$abandoned_cart->encryptData($api_key, $secret_key),
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
        global $retainful;
        $noc_message = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'retainful_coupon_message', null);
        if (!empty($noc_message)) {
            return __($noc_message, RNOC_TEXT_DOMAIN);
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
        global $retainful;
        $is_enabled = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon', '0');
        return ($is_enabled == 1);
    }

    /**
     * get coupon settings from admin
     * @return array
     */
    function getCouponSettings()
    {
        global $retainful;
        $coupon = array();
        $coupon['coupon_type'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'retainful_coupon_type', 0);
        $coupon['coupon_applicable_to'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to', 'all');
        $coupon['coupon_amount'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount', 0);
        $coupon['product_ids'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'products', array());
        $coupon['exclude_product_ids'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'exclude_products', array());
        $coupon['minimum_amount'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'minimum_spend', 0);
        $coupon['maximum_amount'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'maximum_spend', 0);
        $coupon['individual_use'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'individual_use_only', 'no');
        $coupon['exclude_sale_items'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'exclude_sale_items', 'no');
        $coupon['product_categories'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'product_categories', array());
        $coupon['exclude_product_categories'] = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'exclude_product_categories', array());
        return $coupon;
    }

    /**
     * get coupon settings from admin
     * @return array
     */
    function getCouponValidOrderStatuses()
    {
        global $retainful;
        $statuses = array('wc-processing', 'wc-completed');
        return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'preferred_order_status', $statuses);
    }

    /**
     * get coupon settings from admin
     * @return string
     */
    function showCouponInThankYouPage()
    {
        global $retainful;
        $show = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'show_next_order_coupon_in_thankyou_page', 0);
        return ($show == 1);
    }

    /**
     * get coupon settings from admin
     * @return string
     */
    function enableCouponResponsePopup()
    {
        global $retainful;
        return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup', 1);
    }

    /**
     * get coupon settings from admin
     * @return array
     */
    function getCouponValidUserRoles()
    {
        $roles = array('all');
        if ($this->isProPlan()) {
            global $retainful;
            return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'preferred_user_roles', $roles);
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
            global $retainful;
            return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'limit_per_user', $limit);
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
            global $retainful;
            return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'minimum_sub_total', $minimum_sub_total);
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
            global $retainful;
            return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_products', $products);
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
            global $retainful;
            return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_categories', $categories);
        }
        return $categories;
    }

    /**
     * get coupon settings from admin
     * @return bool
     */
    function autoGenerateCouponsForOldOrders()
    {
        global $retainful;
        $auto_generate = $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'automatically_generate_coupon', 1);
        return ($auto_generate == 1);
    }

    /**
     * Coupon only applicable for
     * @return string
     */
    function couponFor()
    {
        global $retainful;
        return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to', 'all');
    }

    /**
     * Coupon only applicable for
     * @return string
     */
    function couponMessageHook()
    {
        global $retainful;
        return $retainful::$settings->get('next_order_coupon', RNOC_PLUGIN_PREFIX . 'retainful_add_coupon_message_to', 'woocommerce_email_customer_details');
    }

    /**
     * Send Coupon details to server
     * @param $url
     * @param $params
     * @return bool
     */
    function sendCouponDetails($url, $params)
    {
        global $retainful;
        if (!isset($params['app_id'])) {
            $params['app_id'] = $this->getApiKey();
        }
        $url = $retainful::$api->domain . $url;
        $response = $retainful::$api->request($url, $params);
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
        return null;
    }
}