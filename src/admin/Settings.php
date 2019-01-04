<?php

namespace Rnoc\Retainful\Admin;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\library\RetainfulApi;

class Settings
{
    public $app_prefix = 'rnoc_', $slug = 'retainful', $api;

    /**
     * Settings constructor.
     */
    function __construct()
    {
        $this->api = new RetainfulApi();
    }

    /**
     * Render the admin pages
     */
    function renderPage()
    {
        add_action('cmb2_admin_init', function () {
            //General settings tab
            $general_settings = new_cmb2_box(array(
                'id' => $this->app_prefix . 'retainful',
                'title' => __('Retainful Next Order Coupon', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug,
                'tab_group' => $this->slug,
                'parent_slug' => 'woocommerce',
                'tab_title' => __('Next order coupon', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Retainful App ID', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_app_id',
                'type' => 'retainful_app',
                'default' => '',
                'desc' => __('You can get your App-id from https://www.app.retainful.com', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'id' => $this->app_prefix . 'is_retainful_connected',
                'type' => 'hidden',
                'default' => 0,
                'attributes' => array('id' => 'is_retainful_app_connected')
            ));
            $general_settings->add_field(array(
                'name' => __('Coupon type', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_coupon_type',
                'type' => 'radio',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('Percentage', RNOC_TEXT_DOMAIN),
                    '1' => __('Flat', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0'
            ));
            $general_settings->add_field(array(
                'name' => __('Coupon value', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_coupon_amount',
                'type' => 'text',
                'classes' => 'retainful-coupon-group',
                'default' => ''
            ));
            $general_settings->add_field(array(
                'name' => __('Apply coupon to', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_coupon_applicable_to',
                'type' => 'radio',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    'all' => __('Allow any one to apply coupon', RNOC_TEXT_DOMAIN),
                    'validate_on_checkout' => __('Allow the customer to apply coupon, but validate at checkout', RNOC_TEXT_DOMAIN),
                    'login_users' => __('Allow customer to apply coupon only after login, but validate at checkout', RNOC_TEXT_DOMAIN)
                ),
                'default' => 'all'
            ));
            $general_settings->add_field(array(
                'name' => __('Display coupon message after', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_add_coupon_message_to',
                'type' => 'select',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    /*'woocommerce_email_header' => __('Header', RNOC_TEXT_DOMAIN),*/
                    'woocommerce_email_order_details' => __('Order details', RNOC_TEXT_DOMAIN),
                    'woocommerce_email_order_meta' => __('Order meta', RNOC_TEXT_DOMAIN),
                    'woocommerce_email_customer_details' => __('Customer details', RNOC_TEXT_DOMAIN),
                    /*'woocommerce_email_footer' => __('Footer', RNOC_TEXT_DOMAIN)*/
                ),
                'default' => 'woocommerce_email_customer_details'
            ));
            $general_settings->add_field(array(
                'name' => __('Custom coupon message', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_coupon_message',
                'type' => 'wysiwyg',
                'classes' => 'retainful-coupon-group',
                'default' => '<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p></div></div>',
                'desc' => __('This message will attached to the Order Email.<br>Please use the below short codes to show the Coupon details in the message.<br><b>{{coupon_code}}</b> - Coupon code<br><b>{{coupon_amount}}</b> - Coupon amount<br><br><br>', RNOC_TEXT_DOMAIN)
            ));
        });
    }

    /**
     * Check fo entered API key is valid or not
     * @param string $api_key
     * @return bool
     */
    function isApiEnabled($api_key = "")
    {
        if (empty($api_key))
            $api_key = $this->getApiKey();
        if (!empty($api_key))
            return $this->api->validateApi($api_key);
        else
            return false;
    }

    /**
     * Check fo entered API key is valid or not
     * @return bool
     */
    function isAppConnected()
    {
        $settings = get_option($this->slug);
        if (!empty($settings) && isset($settings[$this->app_prefix . 'is_retainful_connected']) && !empty(isset($settings[$this->app_prefix . 'is_retainful_connected']))) {
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
        $settings = get_option($this->slug);
        if (!empty($settings) && isset($settings[$this->app_prefix . 'retainful_app_id']) && !empty(isset($settings[$this->app_prefix . 'retainful_app_id']))) {
            return $settings[$this->app_prefix . 'retainful_app_id'];
        }
        return NULL;
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    function getCouponMessage()
    {
        $settings = get_option($this->slug);
        if (!empty($settings) && isset($settings[$this->app_prefix . 'retainful_coupon_message']) && !empty(isset($settings[$this->app_prefix . 'retainful_coupon_message']))) {
            return __($settings[$this->app_prefix . 'retainful_coupon_message'], RNOC_TEXT_DOMAIN);
        } else {
            return __('<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p></div></div>', RNOC_TEXT_DOMAIN);
        }
    }

    /**
     * get coupon settings from admin
     * @return array
     */
    function getCouponSettings()
    {
        $coupon = array();
        $settings = get_option($this->slug);
        if (!empty($settings)) {
            $coupon['coupon_type'] = ($settings[$this->app_prefix . 'retainful_coupon_type']) ? $settings[$this->app_prefix . 'retainful_coupon_type'] : 0;
            $coupon['coupon_amount'] = ($settings[$this->app_prefix . 'retainful_coupon_amount']) ? $settings[$this->app_prefix . 'retainful_coupon_amount'] : 0;
        }
        return $coupon;
    }

    /**
     * Coupon only applicable for
     * @return string
     */
    function couponFor()
    {
        $coupon_applicable_for = 'all';
        $settings = get_option($this->slug);
        if (!empty($settings)) {
            $coupon_applicable_for = ($settings[$this->app_prefix . 'retainful_coupon_applicable_to']) ? $settings[$this->app_prefix . 'retainful_coupon_applicable_to'] : 'all';
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
        $settings = get_option($this->slug);
        if (!empty($settings)) {
            $hook = ($settings[$this->app_prefix . 'retainful_add_coupon_message_to']) ? $settings[$this->app_prefix . 'retainful_add_coupon_message_to'] : 'woocommerce_email_customer_details';
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
        $response = $this->api->remoteGet($url, $params, true);
        if (isset($response->success) && $response->success) {
            //Do any stuff if success
            return true;
        } else {
            //Log messages if request get failed
            return false;
        }
    }

    function logResponse($message, $response)
    {
        $plugin_directory = plugin_dir_path(__DIR__);
        $file = $plugin_directory . "/cache/retainful.log";
        $f = fopen($file, 'a');
        fwrite($f, "\n\n Message: \n" . $message);
        fwrite($f, "Data " . json_encode($response));
        fclose($f);

    }

    /**
     * Link to track Email
     * @param $url
     * @param $params
     * @return string
     */
    function getPixelTagLink($url, $params)
    {
        if (!isset($params['app_id'])) {
            $params['app_id'] = $this->getApiKey();
        }
        if (!isset($params['email_open'])) {
            $params['email_open'] = 1;
        }
        if (isset($params['new_coupon'])) {
            $params['applied_coupon'] = $params['new_coupon'];
            unset($params['new_coupon']);
        }
        if (isset($params['order_id'])) {
            unset($params['order_id']);
        }
        if (isset($params['total'])) {
            unset($params['total']);
        }
        return $this->api->emailTrack($url, $params);
    }
}