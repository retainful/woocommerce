<?php

namespace Rnoc\Retainful\Admin;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\library\RetainfulApi;

class Settings
{
    public $slug = 'retainful', $api;

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
            $is_app_connected = $this->isAppConnected();
            //General settings tab
            $general_settings = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful',
                'title' => __('Retainful - Abandoned Carts', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug,
                'tab_group' => $this->slug,
                'parent_slug' => 'woocommerce',
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Next order coupon', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Retainful App ID', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_app_id',
                'type' => 'retainful_app',
                'default' => '',
                'desc' => __('You can get your App-id from https://www.app.retainful.com', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'is_retainful_connected',
                'type' => 'hidden',
                'default' => 0,
                'attributes' => array('id' => 'is_retainful_app_connected')
            ));
            $general_settings->add_field(array(
                'name' => __('Coupon type', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_coupon_type',
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
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount',
                'type' => 'text',
                'classes' => 'retainful-coupon-group',
                'default' => '',
                'after' => '<p><b>' . __('Note: If this field is empty, No coupon codes will generate!', RNOC_TEXT_DOMAIN) . '</b></p><p id="coupon_amount_error" style="color: red;;"></p>',
                'attributes' => array(
                    'id' => 'app_coupon_value'
                )
            ));
            $general_settings->add_field(array(
                'name' => __('Apply coupon to', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to',
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
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_add_coupon_message_to',
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
            if ($is_app_connected) {
                $coupon_msg_desc .= $pro_feature_coupon_msg_desc;
            }
            $general_settings->add_field(array(
                'name' => __('Custom coupon message', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_coupon_message',
                'type' => 'wysiwyg',
                'classes' => 'retainful-coupon-group',
                'default' => '<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Go! </a></p></div></div>',
                'desc' => $coupon_msg_desc
            ));
            //Usage restrictions
            $usage_restrictions = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_usage_restriction',
                'title' => __('Retainful Coupon Usage Restrictions', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug . '_usage_restriction',
                'tab_group' => $this->slug,
                'parent_slug' => $this->slug,
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Coupon usage restriction', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            if ($is_app_connected) {
                $usage_restrictions->add_field(array(
                    'name' => __('Minimum spend', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'minimum_spend',
                    'type' => 'text',
                    'desc' => __('Set the minimum spend(subtotal) allowed to use the coupon.', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'type' => 'number',
                        'class' => 'number_only_field',
                        'min' => 0
                    ),
                    'default' => ''
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Maximum spend', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'maximum_spend',
                    'type' => 'text',
                    'desc' => __('Set the maximum spend(subtotal) allowed to use the coupon.', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'type' => 'number',
                        'class' => 'number_only_field',
                        'min' => 0
                    ),
                    'default' => ''
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Coupon expires in ', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'retainful_expire_days',
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
                    'name' => __('Coupon expire date format ', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'expire_date_format',
                    'type' => 'select',
                    'default' => 'F j, Y, g:i a',
                    'options' => $this->getDateFormatOptions()
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Individual use only', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'individual_use_only',
                    'type' => 'checkbox',
                    'desc' => __('Check this box if the coupon cannot be used in conjunction with other coupons.', RNOC_TEXT_DOMAIN)
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Exclude sale items', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'exclude_sale_items',
                    'type' => 'checkbox',
                    'desc' => __('Check this box if the coupon should not apply to items on sale. Per-item coupons will only work if the item is not on sale. Per-cart coupons will only work if there are items in the cart that are not on sale.', RNOC_TEXT_DOMAIN)
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Products', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'products',
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
                    'id' => RNOC_PLUGIN_PREFIX . 'exclude_products',
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
                    'id' => RNOC_PLUGIN_PREFIX . 'product_categories',
                    'type' => 'pw_multiselect',
                    'options' => $this->getCategories(),
                    'attributes' => array(
                        'placeholder' => __('Select categories', RNOC_TEXT_DOMAIN)
                    ),
                    'desc' => __('Product categories that the coupon code will be applied to, or that need to be in the cart in order for the "Fixed cart discount" to be applied.', RNOC_TEXT_DOMAIN)
                ));
                $usage_restrictions->add_field(array(
                    'name' => __('Exclude Categories', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'exclude_product_categories',
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
                    'id' => RNOC_PLUGIN_PREFIX . 'unlock_usage_restriction',
                    'type' => 'unlock_usage_restriction'
                ));
            }
            /*
             * Adding abandoned cart
             */
            //Abandoned cart
            $abandoned_cart = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_abandoned_cart',
                'title' => __('Retainful Abandoned Carts', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug . '_abandoned_cart',
                'tab_group' => $this->slug,
                'parent_slug' => $this->slug,
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Abandoned / Recovered Carts', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            $abandoned_cart->add_field(array(
                'name' => __('Select date range', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'date_range_picker',
                'type' => 'date_range_picker'
            ));
            $abandoned_cart->add_field(array(
                'name' => '',
                'id' => RNOC_PLUGIN_PREFIX . 'abandoned_cart_dashboard',
                'type' => 'abandoned_cart_lists'
            ));
            //Email templates
            $abandoned_cart_email_templates = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_abandoned_cart_email_templates',
                'title' => __('Retainful Abandoned Cart Email Templates', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug . '_abandoned_cart_email_templates',
                'tab_group' => $this->slug,
                'parent_slug' => $this->slug,
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Email Templates', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            $abandoned_cart_email_templates->add_field(array(
                'name' => __('"From" Name', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'email_from_name',
                'type' => 'text',
                'before_row' => '<h4>' . __("Abandoned Cart Email Templates", RNOC_TEXT_DOMAIN) . '</h4>',
                'desc' => __('Enter the name that should appear in the email sent.', RNOC_TEXT_DOMAIN),
                'default' => 'Admin'
            ));
            $admin_email = get_option('admin_email');
            $abandoned_cart_email_templates->add_field(array(
                'name' => __('"From" Address', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'email_from_address',
                'type' => 'text',
                'desc' => __('Email address from which the reminder emails should be sent.', RNOC_TEXT_DOMAIN),
                'default' => $admin_email
            ));
            $abandoned_cart_email_templates->add_field(array(
                'name' => __('"Reply To " Address', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'email_reply_address',
                'type' => 'text',
                'desc' => __('When a contact receives your email and clicks reply, which email address should that reply be sent to?', RNOC_TEXT_DOMAIN),
                'default' => $admin_email
            ));
            $template_path = plugin_dir_url(__FILE__);
            $email_template = wp_remote_fopen($template_path . 'templates/default-1.html');
            $group_field_id = $abandoned_cart_email_templates->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'templates',
                'type' => 'group',
                'description' => __('Add email templates at different intervals to maximize the possibility of recovering your abandoned carts.', RNOC_TEXT_DOMAIN),
                'options' => array(
                    'group_title' => __('Email Template {#}', RNOC_TEXT_DOMAIN),
                    'add_button' => __('Add Another Template', RNOC_TEXT_DOMAIN),
                    'remove_button' => __('Remove Template', RNOC_TEXT_DOMAIN),
                    'sortable' => false,
                    'closed' => true
                ),
            ));
            $abandoned_cart_email_templates->add_group_field($group_field_id, array(
                'name' => __('Email Subject', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'template_subject',
                'type' => 'text',
                'default' => 'Hey {{customer_name}}!! You left something in your cart'
            ));
            $abandoned_cart_email_templates->add_group_field($group_field_id, array(
                'name' => __('Email Body', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'template_body',
                'type' => 'wysiwyg',
                'default' => $email_template,
                'desc' => __('You can use following short codes in your email template:<br> <b>{{customer_name}}</b> - To display Customer name<br><b>{{site_url}}</b> - Site link<br> <b>{{cart_recovery_link}}</b> - Link to recover user cart<br><b>{{user_cart}}</b> - Cart details', RNOC_TEXT_DOMAIN)
            ));
            $abandoned_cart_email_templates->add_group_field($group_field_id, array(
                'name' => __('Send this email in', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'template_sent_after',
                'type' => 'email_after',
                'default' => '1',
                'desc' => __(' after cart is abandoned.', RNOC_TEXT_DOMAIN),
                'attributes' => array('class' => 'number_only_field')
            ));
            $abandoned_cart_email_templates->add_group_field($group_field_id, array(
                'name' => __('Active?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'template_active',
                'type' => 'select',
                'options' => array(
                    1 => __('Yes', RNOC_TEXT_DOMAIN),
                    0 => __('No', RNOC_TEXT_DOMAIN)
                ),
                'default' => 1
            ));
            //Reporting
            $abandoned_cart_dashboard = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_abandoned_cart_dashboard',
                'title' => __('Retainful Abandoned Cart Dashboard', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug . '_abandoned_cart_dashboard',
                'tab_group' => $this->slug,
                'parent_slug' => $this->slug,
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Reports', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            $abandoned_cart_dashboard->add_field(array(
                'name' => __('Select date range', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'date_range_picker',
                'type' => 'date_range_picker'
            ));
            $abandoned_cart_dashboard->add_field(array(
                'name' => '',
                'id' => RNOC_PLUGIN_PREFIX . 'abandoned_cart_dashboard',
                'type' => 'abandoned_cart_dashboard'
            ));
            //Abandoned cart Settings
            $abandoned_cart_settings = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_abandoned_cart_settings',
                'title' => __('Retainful Abandoned Cart Settings', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug . '_abandoned_cart_settings',
                'tab_group' => $this->slug,
                'parent_slug' => $this->slug,
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Abandoned cart settings', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            $abandoned_cart_settings->add_field(array(
                'name' => __('When to consider a cart as abandoned?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'cart_abandoned_time',
                'type' => 'text',
                'desc' => __('In minutes. Example: You can consider a cart as abandoned 15 minutes after it was added', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'type' => 'number',
                    'min' => 1,
                    'class' => 'number_only_field'
                ),
                'default' => 60
            ));
            $abandoned_cart_settings->add_field(array(
                'name' => __('How many days to wait before automatically deleting the cart', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'delete_abandoned_order_days',
                'type' => 'text',
                'desc' => __('Useful when you wanted the abandoned carts be removed after certain days', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'type' => 'number',
                    'min' => 1,
                    'class' => 'number_only_field'
                ),
                'default' => ''
            ));
            $abandoned_cart_settings->add_field(array(
                'name' => __('Should the store administrator get a notification when a cart is recovered', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'email_admin_on_recovery',
                'type' => 'radio_inline',
                'options' => array(
                    0 => __('No', RNOC_TEXT_DOMAIN),
                    1 => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'desc' => __('Useful if you wanted to get notified when a cart is recovered.', RNOC_TEXT_DOMAIN),
                'default' => 0
            ));
            $abandoned_cart_settings->add_field(array(
                'name' => __('Track real-time carts?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'track_real_time_cart',
                'type' => 'radio_inline',
                'options' => array(
                    0 => __('No', RNOC_TEXT_DOMAIN),
                    1 => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'desc' => __('If not enabled, only carts that are abandoned gets tracked (i.e, after customer leaves the site)', RNOC_TEXT_DOMAIN),
                'default' => 1
            ));
            $abandoned_cart_settings->add_field(array(
                'name' => __('Compliance: Message to show when tracking real-time carts', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'cart_capture_msg',
                'type' => 'textarea',
                'desc' => __('Under GDPR, it is mandatory to inform the users when we track their cart activity in real-time. If you are not tracking, you can leave this empty', RNOC_TEXT_DOMAIN)
            ));
        });
        if (is_admin()) {
            $this->addScript();
        }
    }

    /**
     * Get the abandoned cart settings
     * @return array|mixed
     */
    function getAbandonedCartSettings()
    {
        $abandoned_cart = get_option($this->slug . '_abandoned_cart_settings', array());
        if (empty($abandoned_cart))
            $abandoned_cart = array();
        return $abandoned_cart;
    }

    /**
     * Get the abandoned cart settings
     * @return array|mixed
     */
    function getEmailTemplates()
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
     * Make coupon expire date from order date
     * @param $ordered_date
     * @return string|null
     */
    function getCouponExpireDate($ordered_date)
    {
        if (empty($ordered_date))
            return NULL;
        $settings = get_option($this->slug . '_usage_restriction', array());
        if (!empty($settings) && $this->isAppConnected() && isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_expire_days']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'retainful_expire_days'])) {
            try {
                $expiry_date = new \DateTime($ordered_date);
                $expiry_date->add(new \DateInterval('P' . $settings[RNOC_PLUGIN_PREFIX . 'retainful_expire_days'] . 'D'));
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
        wp_enqueue_style('retainful-admin-css', $asset_path . '/css/main.css');
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
        $settings = get_option($this->slug, array());
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id'])) {
            return $settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id'];
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
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_message']) && !empty(isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_message']))) {
            return __($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_message'], RNOC_TEXT_DOMAIN);
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
            $coupon['coupon_type'] = ($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_type']) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_type'] : 0;
            $coupon['coupon_amount'] = ($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount']) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount'] : 0;
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