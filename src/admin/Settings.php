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
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Settings', RNOC_TEXT_DOMAIN),
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
                'default' => '',
                'after' => '<p id="coupon_amount_error" style="color: red;;"></p>',
                'attributes' => array(
                    'id' => 'app_coupon_value'
                )
            ));
            $general_settings->add_field(array(
                'name' => __('Apply coupon to', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_coupon_applicable_to',
                'type' => 'radio',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    'all' => __('Allow any one to apply coupon', RNOC_TEXT_DOMAIN),
                    'validate_on_checkout' => __('Allow the customer to apply coupon, but validate at checkout', RNOC_TEXT_DOMAIN),
                    'login_users' => __('Allow customer to apply coupon only after login', RNOC_TEXT_DOMAIN)
                ),
                'default' => 'all'
            ));
            $general_settings->add_field(array(
                'name' => __('Display coupon message after', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_add_coupon_message_to',
                'type' => 'select',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    'woocommerce_email_order_details' => __('Order details', RNOC_TEXT_DOMAIN),
                    'woocommerce_email_order_meta' => __('Order meta', RNOC_TEXT_DOMAIN),
                    'woocommerce_email_customer_details' => __('Customer details', RNOC_TEXT_DOMAIN)
                ),
                'default' => 'woocommerce_email_customer_details'
            ));

            $coupon_msg_desc = __('This message will attached to the Order Email.<br>Please use the below short codes to show the Coupon details in the message.<br><b>{{coupon_code}}</b> - Coupon code<br><b>{{coupon_amount}}</b> - Coupon amount<br><b>{{coupon_url}}</b> - Url to apply coupon automatically', RNOC_TEXT_DOMAIN);
            $pro_feature_coupon_msg_desc = __('<br><b>{{coupon_expiry_date}}</b> - Coupon expiry date(If coupon does not have any expiry days,then this will not attach to the message).<br>', RNOC_TEXT_DOMAIN);
            if ($this->isAppConnected()) {
                $coupon_msg_desc .= $pro_feature_coupon_msg_desc;
            }
            $general_settings->add_field(array(
                'name' => __('Custom coupon message', RNOC_TEXT_DOMAIN),
                'id' => $this->app_prefix . 'retainful_coupon_message',
                'type' => 'wysiwyg',
                'classes' => 'retainful-coupon-group',
                'default' => '<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Go! </a></p></div></div>',
                'desc' => $coupon_msg_desc
            ));
            //Usage restrictions
            $usage_restrictions = new_cmb2_box(array(
                'id' => $this->app_prefix . 'retainful_usage_restriction',
                'title' => __('Retainful Coupon Usage Restrictions', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug . '_usage_restriction',
                'tab_group' => $this->slug,
                'parent_slug' => $this->slug,
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Usage restriction', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            if ($this->isAppConnected()) {
                $usage_restrictions->add_field(array(
                    'name' => __('Minimum spend', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'minimum_spend',
                    'type' => 'text',
                    'desc' => __('Set the minimum spend(subtotal) allowed to use the coupon.', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'type' => 'number',
                        'min' => 0
                    ),
                    'default' => ''
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Maximum spend', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'maximum_spend',
                    'type' => 'text',
                    'desc' => __('Set the maximum spend(subtotal) allowed to use the coupon.', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'type' => 'number',
                        'min' => 0
                    ),
                    'default' => ''
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Coupon expires in ', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'retainful_expire_days',
                    'type' => 'text_small',
                    'after' => '<p id="coupon_expire_error" style="color: red;"></p>' . __('After the entered number of days coupon will automatically expired.<br><b>Note: Please leave empty or put 0 to never expire.</b>', RNOC_TEXT_DOMAIN),
                    'desc' => __(' Day(s)', RNOC_TEXT_DOMAIN),
                    'classes' => 'retainful-coupon-group',
                    'default' => '',
                    'attributes' => array(
                        'type' => 'number',
                        'min' => 0,
                        'id' => 'app_coupon_expire_days'
                    )
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Individual use only', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'individual_use_only',
                    'type' => 'checkbox',
                    'desc' => __('Check this box if the coupon cannot be used in conjunction with other coupons.', RNOC_TEXT_DOMAIN)
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Exclude sale items', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'exclude_sale_items',
                    'type' => 'checkbox',
                    'desc' => __('Check this box if the coupon should not apply to items on sale. Per-item coupons will only work if the item is not on sale. Per-cart coupons will only work if there are items in the cart that are not on sale.', RNOC_TEXT_DOMAIN)
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Products', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'products',
                    'type' => 'post_search_ajax',
                    'limit' => 10,
                    'desc' => __('Product that the coupon code will be applied to, or that need to be in the cart in order for the "Fixed cart discount" to be applied.', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'placeholder' => __('Choose products..', RNOC_TEXT_DOMAIN)
                    ),
                    'query_args' => array(
                        'post_type' => array('product', 'product_variation'),
                        'post_status' => 'publish'
                    )
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Exclude products', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'exclude_products',
                    'type' => 'post_search_ajax',
                    'limit' => 10,
                    'desc' => __('Product that the coupon code will not applied to, or cannot be in the cart in order for the "Fixed cart discount" to be applied.', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'placeholder' => __('Choose products..', RNOC_TEXT_DOMAIN)
                    ),
                    'query_args' => array(
                        'post_type' => array('product', 'product_variation'),
                        'post_status' => 'publish'
                    )
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Product Categories', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'product_categories',
                    'type' => 'pw_multiselect',
                    'options' => $this->getCategories(),
                    'attributes' => array(
                        'placeholder' => __('Select categories', RNOC_TEXT_DOMAIN)
                    ),
                    'desc' => __('Product categories that the coupon code will be applied to, or that need to be in the cart in order for the "Fixed cart discount" to be applied.', RNOC_TEXT_DOMAIN)
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Exclude Categories', RNOC_TEXT_DOMAIN),
                    'id' => $this->app_prefix . 'exclude_product_categories',
                    'type' => 'pw_multiselect',
                    'options' => $this->getCategories(),
                    'attributes' => array(
                        'placeholder' => __('Select categories', RNOC_TEXT_DOMAIN)
                    ),
                    'desc' => __('Product categories that the coupon code will not applied to, or cannot be in the cart in order for the "Fixed cart discount" to be applied.', RNOC_TEXT_DOMAIN)
                ));
            } else {
                $usage_restrictions->add_field(array(
                    'name' => '',
                    'id' => $this->app_prefix . 'unlock_usage_restriction',
                    'type' => 'unlock_usage_restriction'
                ));
            }
        });
        $this->addScript();
    }

    /**
     * Make coupon expire date from order date
     * @param $ordered_date
     * @return string|null
     */
    function getCouponExpireDate($ordered_date)
    {
        if (empty($ordered_date))
            return NULL;
        $settings = get_option($this->slug . '_usage_restriction', array());
        if (!empty($settings) && $this->isAppConnected() && isset($settings[$this->app_prefix . 'retainful_expire_days']) && !empty($settings[$this->app_prefix . 'retainful_expire_days'])) {
            try {
                $expiry_date = new \DateTime($ordered_date);
                $expiry_date->add(new \DateInterval('P' . $settings[$this->app_prefix . 'retainful_expire_days'] . 'D'));
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
        $asset_path = plugins_url('', __FILE__);
        wp_enqueue_script('retainful-app-main', $asset_path . '/js/app.js');
    }

    /**
     * Get coupon usage restriction details
     * @return array
     */
    function getUsageRestrictions()
    {
        if ($this->isAppConnected()) {
            $usage_restrictions = get_option($this->slug . '_usage_restriction', array());
            if (empty($usage_restrictions))
                $usage_restrictions = array();
            return array_merge($usage_restrictions, array('app_prefix' => $this->app_prefix));
        } else {
            return array();
        }
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
                $categories[$category->term_id] = $category->name;
            }
        }
        return $categories;
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
        $settings = get_option($this->slug, array());
        if (!empty($settings) && isset($settings[$this->app_prefix . 'is_retainful_connected']) && !empty($settings[$this->app_prefix . 'is_retainful_connected'])) {
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
        $settings = get_option($this->slug, array());
        if (!empty($settings) && isset($settings[$this->app_prefix . 'retainful_app_id']) && !empty($settings[$this->app_prefix . 'retainful_app_id'])) {
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
        $settings = get_option($this->slug, array());
        if (!empty($settings) && isset($settings[$this->app_prefix . 'retainful_coupon_message']) && !empty(isset($settings[$this->app_prefix . 'retainful_coupon_message']))) {
            return __($settings[$this->app_prefix . 'retainful_coupon_message'], RNOC_TEXT_DOMAIN);
        } else {
            return __('<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Go! </a></p></div></div>', RNOC_TEXT_DOMAIN);
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
        $settings = get_option($this->slug, array());
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
        $settings = get_option($this->slug, array());
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
        $response = $this->api->request($url, $params, true);
        if (isset($response->success) && $response->success) {
            //Do any stuff if success
            return true;
        } else {
            //Log messages if request get failed
            return false;
        }
    }

    /**
     * Log the message for further usage
     * @param $message
     * @param $response
     */
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
        if (isset($params['applied_coupon'])) {
            unset($params['applied_coupon']);
        }
        return $this->api->emailTrack($url, $params);
    }
}