<?php

namespace Rnoc\Retainful\Admin;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Api\AbandonedCart\RestApi;
use Rnoc\Retainful\Integrations\MultiLingual;
use Rnoc\Retainful\library\RetainfulApi;
use Rnoc\Retainful\WcFunctions;

class Settings
{
    public $slug = 'retainful', $api, $wc_functions;

    /**
     * Settings constructor.
     */
    function __construct()
    {
        $this->api = new RetainfulApi();
        $this->wc_functions = new WcFunctions();
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
     * switch to cloud notice
     * @return string
     */
    function deactivatePremiumPluginNotice()
    {
        if ($this->isPremiumPluginActive()) {
            $deactivate_link = $this->pluginActionLink('retainful-abandoned-cart-premium/retainful-abandoned-cart-premium.php', 'deactivate');
            return '<p style="padding: 2em;background: #ffffff;border: 1px solid #e9e9e9;box-shadow: 0 1px 1px rgba(0,0,0,.05);">' . esc_html__("Premium addons now availale in the Retainful Core plugin itself. So a separate Premium add-ons plugin is not necessary. You can de-activate the plugin.", RNOC_TEXT_DOMAIN) . '<a href="' . $deactivate_link . '" class="button button-primary">' . __('De-activate', RNOC_TEXT_DOMAIN) . '</a></p>';
        }
        return NULL;
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
     * Render the admin pages
     */
    function renderPage()
    {
        add_action('cmb2_admin_init', function () {
            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : NULL;
            if (is_admin() && in_array($page, array('retainful_abandoned_cart', 'retainful_abandoned_cart_email_templates', 'retainful', 'retainful_settings', 'retainful_premium', 'retainful_license', 'retainful_abandoned_cart_sent_emails'))) {
                $this->addScript();
            }
            if ($page == $this->slug . '_license') {
                $move_to_cloud = isset($_GET['move_to_cloud']) ? sanitize_text_field($_GET['move_to_cloud']) : 'no';
                $move_to_local = isset($_GET['move_to_local']) ? sanitize_text_field($_GET['move_to_local']) : 'no';
                if ($move_to_cloud == 'yes') {
                    $this->setAbandonedCartToManageInCloud();
                }
                if ($move_to_local == 'yes') {
                    $this->setAbandonedCartToManageLocally();
                }
                if ($move_to_cloud == 'yes' || $move_to_local == 'yes') {
                    $redirect_url = admin_url('admin.php?page=' . $page);
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
            $notice = NULL;
            $is_app_connected = $this->isAppConnected();
            $run_abandoned_cart_externally = $this->runAbandonedCartExternally();
            if (!$run_abandoned_cart_externally) {
                /*
                 * Adding abandoned cart
                 */
                //Abandoned cart
                $abandoned_cart = new_cmb2_box(array(
                    'id' => RNOC_PLUGIN_PREFIX . 'retainful_abandoned_cart',
                    'title' => __('Retainful - Abandoned Carts', RNOC_TEXT_DOMAIN),
                    'parent_slug' => $this->slug . '_license',
                    'capability' => 'edit_shop_coupons',
                    'object_types' => array('options-page'),
                    'option_key' => $this->slug . '_abandoned_cart',
                    'tab_group' => $this->slug,
                    'tab_title' => __('Abandoned / Recovered Carts', RNOC_TEXT_DOMAIN),
                    'save_button' => __('Save', RNOC_TEXT_DOMAIN)
                ));
                $notice = $this->switchToCloudNotice();
                //Reports
                $abandoned_cart->add_field(array(
                    'name' => __('Select date range', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'date_range_picker',
                    'type' => 'date_range_picker',
                    'before_row' => $notice
                ));
                $abandoned_cart->add_field(array(
                    'name' => '',
                    'id' => RNOC_PLUGIN_PREFIX . 'abandoned_cart_report',
                    'type' => 'abandoned_cart_dashboard'
                ));
                //Cart list
                $abandoned_cart->add_field(array(
                    'name' => '',
                    'id' => RNOC_PLUGIN_PREFIX . 'cart_table_filter',
                    'type' => 'cart_table_filter'
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
                    'parent_slug' => $this->slug . '_license',
                    'capability' => 'edit_shop_coupons',
                    'tab_title' => __('Email Templates', RNOC_TEXT_DOMAIN),
                    'save_button' => __('Save', RNOC_TEXT_DOMAIN)
                ));
                if (!isset($_REQUEST['task'])) {
                    $abandoned_cart_email_templates->add_field(array(
                        'name' => __('"From" Name', RNOC_TEXT_DOMAIN),
                        'id' => RNOC_PLUGIN_PREFIX . 'email_from_name',
                        'type' => 'text',
                        'before_row' => $notice . '<h4>' . __("Abandoned Cart Email Templates", RNOC_TEXT_DOMAIN) . '</h4>',
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
                    $abandoned_cart_email_templates->add_field(array(
                        'name' => '',
                        'id' => RNOC_PLUGIN_PREFIX . 'email_templates_list',
                        'type' => 'email_templates',
                        'desc' => __('When a contact receives your email and clicks reply, which email address should that reply be sent to?', RNOC_TEXT_DOMAIN),
                        'default' => $admin_email
                    ));
                } else {
                    $abandoned_cart_email_templates->add_field(array(
                        'name' => '',
                        'id' => 'email_template_edit',
                        'type' => 'email_template_edit',
                        'before_row' => $notice
                    ));
                }
                //Sent Emails Tab
                $sent_emails_list = new_cmb2_box(array(
                    'id' => RNOC_PLUGIN_PREFIX . 'retainful_sent_emails',
                    'title' => __('Abandoned cart sent E-mails', RNOC_TEXT_DOMAIN),
                    'object_types' => array('options-page'),
                    'option_key' => $this->slug . '_abandoned_cart_sent_emails',
                    'tab_group' => $this->slug,
                    'parent_slug' => $this->slug . '_license',
                    'capability' => 'edit_shop_coupons',
                    'tab_title' => __('Sent E-Mails', RNOC_TEXT_DOMAIN),
                    'save_button' => __('Save', RNOC_TEXT_DOMAIN)
                ));
                $sent_emails_list->add_field(array(
                    'name' => '',
                    'id' => RNOC_PLUGIN_PREFIX . 'sent_emails_list',
                    'type' => 'abandoned_cart_sent_emails',
                    'before_row' => $notice
                ));
            } else {
                $this->licenseTab($run_abandoned_cart_externally);
            }
            //Settings tab
            $general_settings = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_settings',
                'title' => __('Settings', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug . '_settings',
                'tab_group' => $this->slug,
                'vertical_tabs' => true,
                'parent_slug' => $this->slug . '_license',
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Settings', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            if (!$run_abandoned_cart_externally) {
                $general_settings->add_field(array(
                    'name' => __('When to consider a cart as abandoned?', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'cart_abandoned_time',
                    'type' => 'text',
                    'desc' => __('In minutes. Example: You can consider a cart as abandoned 15 minutes after it was added', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'type' => 'number',
                        'min' => 15,
                        'class' => 'number_only_field'
                    ),
                    'default' => 60,
                    'before_row' => $notice
                ));
                $general_settings->add_field(array(
                    'name' => __('How many days to wait before automatically deleting the cart', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'delete_abandoned_order_days',
                    'type' => 'text',
                    'desc' => __('Useful when you wanted the abandoned carts be removed after certain days', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'type' => 'number',
                        'min' => 1,
                        'class' => 'number_only_field'
                    ),
                    'default' => 90
                ));
                $general_settings->add_field(array(
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
                $general_settings->add_field(array(
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
                $general_settings->add_field(array(
                    'name' => __('Show guest cart?', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'show_guest_cart_in_dashboard',
                    'type' => 'radio_inline',
                    'options' => array(
                        0 => __('No', RNOC_TEXT_DOMAIN),
                        1 => __('Yes', RNOC_TEXT_DOMAIN)
                    ),
                    'desc' => __('If not enabled, Guest carts will not shown in your Abandoned cart dashboard.', RNOC_TEXT_DOMAIN),
                    'default' => 1
                ));
            } else {
                $general_settings->add_field(array(
                    'name' => __('Cart tracking engine?', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'cart_tracking_engine',
                    'type' => 'radio_inline',
                    'options' => array(
                        'js' => __('JavaScript (Default,Recommended)', RNOC_TEXT_DOMAIN),
                        'php' => __('PHP', RNOC_TEXT_DOMAIN)
                    ),
                    'default' => 'js'
                ));
                $general_settings->add_field(array(
                    'name' => __('Track Zero value carts / orders', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'track_zero_value_carts',
                    'type' => 'radio_inline',
                    'options' => array(
                        'yes' => __('Yes', RNOC_TEXT_DOMAIN),
                        'no' => __('No', RNOC_TEXT_DOMAIN)
                    ),
                    'default' => 'no'
                ));
                $general_settings->add_field(array(
                    'name' => __('Consider On-Hold order status as abandoned cart?', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status',
                    'type' => 'radio_inline',
                    'options' => array(
                        0 => __('No', RNOC_TEXT_DOMAIN),
                        1 => __('Yes', RNOC_TEXT_DOMAIN)
                    ),
                    'default' => 0
                ));
                $general_settings->add_field(array(
                    'name' => __('Consider Canceled order status as abandoned cart?', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status',
                    'type' => 'radio_inline',
                    'options' => array(
                        0 => __('No', RNOC_TEXT_DOMAIN),
                        1 => __('Yes', RNOC_TEXT_DOMAIN)
                    ),
                    'default' => 1
                ));
                $general_settings->add_field(array(
                    'name' => __('Fix for Cart sync not working', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load',
                    'type' => 'radio_inline',
                    'description' => __('Enable this option only when you dont see your carts in Retainful dashboard ', RNOC_TEXT_DOMAIN),
                    'options' => array(
                        0 => __('No', RNOC_TEXT_DOMAIN),
                        1 => __('Yes', RNOC_TEXT_DOMAIN)
                    ),
                    'default' => 0
                ));
            }
            $general_settings->add_field(array(
                'name' => __('Enable GDPR Compliance?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance',
                'type' => 'radio_inline',
                'options' => array(
                    0 => __('No', RNOC_TEXT_DOMAIN),
                    1 => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 0
            ));
            $general_settings->add_field(array(
                'name' => __('Compliance Message', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'cart_capture_msg',
                'type' => 'textarea',
                'desc' => __('Under GDPR, it is mandatory to inform the users when we track their cart activity in real-time.', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Enable IP filter?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_ip_filter',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 0
            ));
            $general_settings->add_field(array(
                'name' => __('Exclude capturing carts from these IP\'s', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses',
                'type' => 'textarea',
                'default' => '',
                'desc' => __('The plugin will not track carts from these IP\'s. Enter IP in comma seperated format.Example 192.168.1.10,192.168.1.11. Alternatively you can also use 192.168.* , 192.168.10.*, 192.168.1.1-192.168.1.255', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Enable debug log?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_debug_log',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 0
            ));
            $general_settings->add_field(array(
                'name' => __('Session handler', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'handle_storage_using',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    'woocommerce' => __('WooCommerce session (Default)', RNOC_TEXT_DOMAIN),
                    'cookie' => __('Cookie', RNOC_TEXT_DOMAIN),
                    'php' => __('PHP Session', RNOC_TEXT_DOMAIN)
                ),
                'desc' => __('DO NOT change this setting unless you are instructed by the Retainful Support team. WooCommerce session will work for 99% of the shops.', RNOC_TEXT_DOMAIN),
                'default' => 'woocommerce'
            ));
            //Next order tab
            $next_order_coupon = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful',
                'title' => __('Next order coupon', RNOC_TEXT_DOMAIN),
                'parent_slug' => $this->slug . '_license',
                'capability' => 'edit_shop_coupons',
                'object_types' => array('options-page'),
                'option_key' => $this->slug,
                'tab_group' => $this->slug,
                'tab_title' => __('Next order coupon', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN)
            ));
            $next_order_coupon->add_field(array(
                'name' => __('Enable next order coupon?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_next_order_coupon',
                'type' => 'radio',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0',
                'description' => 'A single-use, unique coupon code will be generated by Retainful automatically for the next purchase when the customer places a successful order. You can view these coupon codes at <a target="_blank" href="https://app.retainful.com/">Retainful dashboard</a>',
                'before_row' => '<p class="submit"><input type="submit" name="submit-cmb" id="submit-cmb" class="button button-primary" value="' . __("Save", RNOC_TEXT_DOMAIN) . '"></p>' . $notice
            ));
            $next_order_coupon->add_field(array(
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
            $next_order_coupon->add_field(array(
                'name' => __('Enter the coupon amount (percentage or flat)', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount',
                'type' => 'text',
                'classes' => 'retainful-coupon-group',
                'default' => 10,
                'after' => '<p><b>' . __('Enter the percenage or flat value of the coupon. Example: 10 (for 10 percentage). Note: If this field is empty, No coupon codes will generate!', RNOC_TEXT_DOMAIN) . '</b></p><p id="coupon_amount_error" style="color: red;;"></p>',
                'attributes' => array(
                    'id' => 'app_coupon_value'
                )
            ));
            if ($is_app_connected) {
                $next_order_coupon->add_field(array(
                    'name' => __('Coupon expires in ', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'retainful_expire_days',
                    'type' => 'text_small',
                    'after' => '<p id="coupon_expire_error" style="color: red;"></p>' . __('How many days the coupon is valid? After the entered number of days coupon will automatically expire.<br><b>Note: Please leave empty or put 0 to never expire.</b> <br /> <a href="https://app.retainful.com" target="_blank">Send automatic email follow-ups to the customers before the coupon expires.</a>', RNOC_TEXT_DOMAIN),
                    'desc' => __(' Day(s)', RNOC_TEXT_DOMAIN),
                    'classes' => 'retainful-coupon-group',
                    'default' => 60,
                    'attributes' => array(
                        'type' => 'number',
                        'min' => 0,
                        'id' => 'app_coupon_expire_days'
                    )
                ));
                $next_order_coupon->add_field(array(
                    'name' => __('Coupon expiry date format ', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'expire_date_format',
                    'type' => 'select',
                    'default' => 'F j, Y, g:i a',
                    'options' => $this->getDateFormatOptions()
                ));
            }
            $next_order_coupon->add_field(array(
                'name' => __('Apply coupon to', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_coupon_applicable_to',
                'type' => 'radio',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    'all' => __('Allow any one to apply coupon', RNOC_TEXT_DOMAIN),
                    'validate_on_checkout' => __('Allow the customer to apply coupon, but validate at checkout', RNOC_TEXT_DOMAIN),
                    'login_users' => __('Allow customer to apply coupon only after login (Not Recommended)', RNOC_TEXT_DOMAIN)
                ),
                'default' => 'all'
            ));
            $next_order_coupon->add_field(array(
                'name' => __('Allow next order coupons for orders created in the backend and also for old orders (when resending the email notification)', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'automatically_generate_coupon',
                'type' => 'radio',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '1',
                'after' => '<p><b>' . __('The unique code will be generated when you try re-sending the email notification for an order in the backend', RNOC_TEXT_DOMAIN) . '</b></p>',
            ));
            $next_order_coupon->add_field(array(
                'name' => __('Show next order coupon in order "Thank you" page?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'show_next_order_coupon_in_thankyou_page',
                'type' => 'radio',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0'
            ));
            $next_order_coupon->add_field(array(
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
            $next_order_coupon->add_field(array(
                'name' => __('Coupon message to be included in the order confirmation emails to the customer', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_coupon_message',
                'type' => 'wysiwyg',
                'classes' => 'retainful-coupon-group',
                'default' => '<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">{{coupon_amount}} Off On Your Next Purchase</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">To thank you for being a loyal customer we want to offer you an exclusive voucher for {{coupon_amount}} off your next order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #8D71DB; color: #8d71db; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #8D71DB; display: block; padding: 10px; border: 1px solid #8D71DB; border-radius: 4px; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Go! </a></p></div></div>',
                'desc' => $coupon_msg_desc,
                'after_row' => '<h3>' . __('Coupon Generation Restriction', RNOC_TEXT_DOMAIN) . '&nbsp;<small>' . __("(You can control the generation of a coupon based on the these settings.)", RNOC_TEXT_DOMAIN) . '</small></h3>'
            ));
            $next_order_coupon->add_field(array(
                'name' => __('Order Status', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'preferred_order_status',
                'type' => 'pw_multiselect',
                'options' => $this->availableOrderStatuses(),
                'default' => array('wc-processing', 'wc-completed'),
                'desc' => __('<b>Note</b>: Coupon code will not generate until the order meet the choosed order status.', RNOC_TEXT_DOMAIN),
            ));
            if ($this->isProPlan()) {
                $next_order_coupon->add_field(array(
                    'name' => __('User Role', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'preferred_user_roles',
                    'type' => 'pw_multiselect',
                    'options' => $this->getUserRoles(),
                    'attributes' => array(
                        'placeholder' => __('Select User Roles', RNOC_TEXT_DOMAIN)
                    ),
                    'default' => array('all'),
                    'desc' => __('Coupon codes will generate only for the selected user roles. By default coupon code will generate for all user roles.', RNOC_TEXT_DOMAIN)
                ));
            } else {
                $next_order_coupon->add_field(array(
                    'name' => __('User Role', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'unlock_user_role_feature',
                    'type' => 'unlock_features',
                    'link_only_field' => 1
                ));
            }
            if ($this->isProPlan()) {
                $next_order_coupon->add_field(array(
                    'name' => __('How many coupons a customer can get in his lifetime?', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'limit_per_user',
                    'type' => 'text_small',
                    'attributes' => array(
                        'type' => 'number',
                        'min' => 0
                    ),
                    'default' => 99,
                    'desc' => __('In order to maximize repeat purchases, you should send one unique coupon with every purchase for the next order. However, if you only want the customer to get next order coupons for 5 times in his life, you can set it to 5. In this case, the customer will receive the coupon only for 5 of his orders. starting 6th order, he will not receive the coupon', RNOC_TEXT_DOMAIN)
                ));
            } else {
                $next_order_coupon->add_field(array(
                    'name' => __('How many coupons a customer can get in his lifetime?', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'unlock_limit_per_user_feature',
                    'type' => 'unlock_features',
                    'link_only_field' => 1
                ));
            }
            if ($this->isProPlan()) {
                $next_order_coupon->add_field(array(
                    'name' => __('Minimum order total', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'minimum_sub_total',
                    'type' => 'text_small',
                    'attributes' => array(
                        'type' => 'number',
                        'min' => 0
                    ),
                    'default' => '',
                    'desc' => __('Coupon will generate only if the order total greater then or equal to the given value. Leave empty or put 0 for no restriction.', RNOC_TEXT_DOMAIN)
                ));
            } else {
                $next_order_coupon->add_field(array(
                    'name' => __('Minimum order total', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'unlock_minimum_sub_total_feature',
                    'type' => 'unlock_features',
                    'link_only_field' => 1
                ));
            }
            if ($this->isProPlan()) {
                $next_order_coupon->add_field(array(
                    'name' => __('Do not generate if the following products found in the order', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_products',
                    'type' => 'post_search_ajax',
                    'limit' => 10,
                    'desc' => __('Coupon code will not be generated when these products were found in the order!', RNOC_TEXT_DOMAIN),
                    'attributes' => array(
                        'placeholder' => __('Choose products..', RNOC_TEXT_DOMAIN)
                    ),
                    'query_args' => array(
                        'post_type' => array('product', 'product_variation'),
                        'post_status' => 'publish'
                    )
                ));
            } else {
                $next_order_coupon->add_field(array(
                    'name' => __('Do not generate if the following products found in the order', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'unlock_exclude_generating_coupon_for_products_feature',
                    'type' => 'unlock_features',
                    'link_only_field' => 1
                ));
            }
            if ($this->isProPlan()) {
                $next_order_coupon->add_field(array(
                    'name' => __('Do not generate if the following categories found in the order', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'exclude_generating_coupon_for_categories',
                    'type' => 'pw_multiselect',
                    'options' => $this->getCategories(),
                    'attributes' => array(
                        'placeholder' => __('Select categories', RNOC_TEXT_DOMAIN)
                    ),
                    'desc' => __('Next order coupon will NOT be generated if an order has products from the selected categories.', RNOC_TEXT_DOMAIN),
                    'after_row' => '<h3>' . __('Coupon Usage Restriction', RNOC_TEXT_DOMAIN) . '</h3>'
                ));
            } else {
                $next_order_coupon->add_field(array(
                    'name' => __('Do not generate if the following categories found in the order', RNOC_TEXT_DOMAIN) . '<span class="premium-label">Premium</span>',
                    'id' => RNOC_PLUGIN_PREFIX . 'unlock_exclude_generating_coupon_for_categories_feature',
                    'type' => 'unlock_features',
                    'link_only_field' => 1,
                    'after_row' => '<h3>' . __('Coupon Usage Restriction', RNOC_TEXT_DOMAIN) . '</h3>'
                ));
            }
            if ($is_app_connected) {
                //Usage restrictions
                $next_order_coupon->add_field(array(
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
                $next_order_coupon->add_field(array(
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
                $next_order_coupon->add_field(array(
                    'name' => __('Individual use only', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'individual_use_only',
                    'type' => 'checkbox',
                    'desc' => __('Check this box if the coupon cannot be used in conjunction with other coupons.', RNOC_TEXT_DOMAIN)
                ));
                $next_order_coupon->add_field(array(
                    'name' => __('Exclude sale items', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'exclude_sale_items',
                    'type' => 'checkbox',
                    'desc' => __('Check this box if the coupon should not apply to items on sale. Per-item coupons will only work if the item is not on sale. Per-cart coupons will only work if there are items in the cart that are not on sale.', RNOC_TEXT_DOMAIN)
                ));
                $next_order_coupon->add_field(array(
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
                $next_order_coupon->add_field(array(
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
                $next_order_coupon->add_field(array(
                    'name' => __('Product Categories', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'product_categories',
                    'type' => 'pw_multiselect',
                    'options' => $this->getCategories(),
                    'attributes' => array(
                        'placeholder' => __('Select categories', RNOC_TEXT_DOMAIN)
                    ),
                    'desc' => __('Product categories that the coupon code will be applied to, or that need to be in the cart in order for the "Fixed cart discount" to be applied.', RNOC_TEXT_DOMAIN)
                ));
                $next_order_coupon->add_field(array(
                    'name' => __('Exclude Categories', RNOC_TEXT_DOMAIN),
                    'id' => RNOC_PLUGIN_PREFIX . 'exclude_product_categories',
                    'type' => 'pw_multiselect',
                    'options' => $this->getCategories(),
                    'attributes' => array(
                        'placeholder' => __('Select categories', RNOC_TEXT_DOMAIN)
                    ),
                    'desc' => __('Product categories that the coupon code will not applied to, or cannot be in the cart in order for the "Fixed cart discount" to be applied.', RNOC_TEXT_DOMAIN),
                    'after_row' => '<h3>' . __("Coupon applied response", RNOC_TEXT_DOMAIN) . '</h3>',
                ));
            } else {
                $next_order_coupon->add_field(array(
                    'name' => '',
                    'id' => RNOC_PLUGIN_PREFIX . 'unlock_features',
                    'type' => 'unlock_features',
                    'after_row' => '<h3>' . __("Coupon applied response", RNOC_TEXT_DOMAIN) . '</h3>'
                ));
            }
            $next_order_coupon->add_field(array(
                'name' => __('Enable response popup', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup',
                'type' => 'radio_inline',
                'default' => '1',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN),
                )
            ));
            $popup_msg_desc = __('Please use the below short codes to show the Coupon details in the popup.<br><b>{{coupon_code}}</b> - Coupon code<br><b>{{coupon_amount}}</b> - Coupon amount<br><b>{{shop_url}}</b> - Shop URL<br><b>{{cart_url}}</b> - Cart URL<br><b>{{checkout_url}}</b> - Checkout URL', RNOC_TEXT_DOMAIN);
            $next_order_coupon->add_field(array(
                'name' => __('Popup contents', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_applied_popup_design',
                'type' => 'wysiwyg',
                'default' => $this->appliedCouponDefaultTemplate(),
                'desc' => $popup_msg_desc
            ));
            //Premium Addon
            $tabs_array = array(
                array(
                    'id' => 'general-settings',
                    'icon' => 'dashicons-admin-plugins',
                    'title' => __('Add-ons List', RNOC_TEXT_DOMAIN),
                    'fields' => array(
                        RNOC_PLUGIN_PREFIX . 'premium_addon'
                    ),
                )
            );
            if ($this->isProPlan()) {
                if (defined('RNOC_VERSION')) {
                    $tabs_array = apply_filters('rnoc_premium_addon_tab', $tabs_array);
                }
            }
            /*
             * @since premium version 1.1.2 ip filter was removed.
             * so for core 2.1.0 this was added to support premium 1.1.1 and below
             */
            if (!empty($tabs_array) and is_array($tabs_array)) {
                foreach ($tabs_array as $key => $details) {
                    if (isset($details['id']) && $details['id'] == "do-not-track-ip") {
                        unset($tabs_array[$key]);
                    }
                }
            }
            $premium_addon = new_cmb2_box(array(
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_premium_addon',
                'title' => __('Premium Features', RNOC_TEXT_DOMAIN),
                'object_types' => array('options-page'),
                'option_key' => $this->slug . '_premium',
                'tab_group' => $this->slug,
                'vertical_tabs' => true,
                'parent_slug' => $this->slug . '_license',
                'capability' => 'edit_shop_coupons',
                'tab_title' => __('Premium Features', RNOC_TEXT_DOMAIN),
                'save_button' => __('Save', RNOC_TEXT_DOMAIN),
                'tabs' => $tabs_array
            ));
            $premium_addon->add_field(array(
                'name' => '',
                'id' => RNOC_PLUGIN_PREFIX . 'premium_addon',
                'type' => 'premium_addon_list',
                'default' => '',
                'before_row' => $notice . '<p style="text-align: right"><input type="submit" name="submit-cmb" id="submit-cmb-top" class="button button-primary" value="Save" style="display: none;"></p>'
            ));
            if ($this->isProPlan()) {
                if (defined('RNOC_VERSION')) {
                    //Popup modal settings
                    apply_filters('rnoc_premium_addon_tab_content', $premium_addon);
                }
            }
            if (!$run_abandoned_cart_externally) {
                $this->licenseTab($run_abandoned_cart_externally);
            }
        });
    }

    /**
     * check the premium plugin is active
     * @return bool
     */
    function isPremiumPluginActive()
    {
        return is_plugin_active('retainful-abandoned-cart-premium/retainful-abandoned-cart-premium.php');
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
     * Schedule the action scheduler hooks
     */
    function actionSchedulerHooks()
    {
        $this->scheduleEvents('rnoc_abandoned_clear_abandoned_carts', current_time('timestamp'), array(), 'recurring', 86400);
        $this->scheduleEvents('rnoc_abandoned_cart_send_email', current_time('timestamp'), array(), 'recurring', 900);
        $this->schedulePlanChecker();
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
     * Get license details
     * @param $run_abandoned_cart_externally
     * @return \CMB2
     */
    function licenseTab($run_abandoned_cart_externally)
    {
        $switch_to_plugin_notice = NULL;
        if ($run_abandoned_cart_externally) {
            $is_new_installation = $this->isNewInstallation();
            //If the user is old user then ask user to run abandoned cart to
            if ($is_new_installation == 0) {
                $move_to_cloud_url = admin_url('admin.php?page=' . $this->slug . '_license&move_to_local=yes');
                $switch_to_plugin_notice = '<p style="padding: 2em;background: #ffffff;border: 1px solid #e9e9e9;box-shadow: 0 1px 1px rgba(0,0,0,.05);">' . esc_html__("If you would like to switch back and manage the abandoned carts via the plugin", RNOC_TEXT_DOMAIN) . '&nbsp; <a href="' . $move_to_cloud_url . '">' . esc_html("Click Here!") . '</a></p>';
            }
        }
        //License
        $license_arr = array(
            'capability' => 'edit_shop_coupons',
            'object_types' => array('options-page'),
            'option_key' => $this->slug . '_license',
            'tab_group' => $this->slug,
            'id' => RNOC_PLUGIN_PREFIX . 'license',
            'icon_url' => 'dashicons-controls-repeat',
            'position' => 55.5,
            'title' => __('Retainful', RNOC_TEXT_DOMAIN),
            'tab_title' => (!$run_abandoned_cart_externally) ? __('License', RNOC_TEXT_DOMAIN) : __('Connection'),
            'save_button' => __('Save', RNOC_TEXT_DOMAIN)
        );
        if ((!$run_abandoned_cart_externally)) {
            $license_arr['parent_slug'] = 'woocommerce';
        }
        $license = new_cmb2_box($license_arr);
        $switch_to_cloud_notice = (!$run_abandoned_cart_externally) ? $this->switchToCloudNotice() : NULL;
        $is_production = apply_filters('rnoc_is_production_plugin', true);
        if ($is_production) {
            $license->add_field(array(
                'name' => __('App ID', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_app_id',
                'type' => 'text',
                'default' => '',
                'before_row' => $switch_to_cloud_notice . $this->deactivatePremiumPluginNotice(),
                'desc' => __('Get your App-id <a target="_blank" href="' . $this->api->app_url . 'settings">here</a>', RNOC_TEXT_DOMAIN)
            ));
            //if ($run_abandoned_cart_externally) {
            $license->add_field(array(
                'name' => __('Secret Key', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'retainful_app_secret',
                'type' => 'text',
                'default' => '',
                'desc' => __('Get your Secret key <a target="_blank" href="' . $this->api->app_url . 'settings">here</a>', RNOC_TEXT_DOMAIN)
            ));
            //}
        }
        $license->add_field(array(
            'name' => '',
            'id' => RNOC_PLUGIN_PREFIX . 'retainful_app',
            'type' => 'retainful_app',
            'is_app_in_production' => $is_production,
            'default' => '',
            'desc' => '',
            'after_row' => $switch_to_plugin_notice
        ));
        $license->add_field(array(
            'id' => RNOC_PLUGIN_PREFIX . 'is_retainful_connected',
            'type' => 'hidden',
            'default' => 0,
            'attributes' => array('id' => 'is_retainful_app_connected')
        ));
        return $license;
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
     * Set the option to manage Abandoned cart to manage in cloud
     */
    function setAbandonedCartToManageLocally()
    {
        $this->actionSchedulerHooks();
        update_option('retainful_run_abandoned_cart_in_cloud', 0);
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
        $response = false;
        $is_new_installation = $this->isNewInstallation();
        if ($is_new_installation) {
            $response = true;
        }
        $retainful_run_abandoned_cart_in_cloud = get_option('retainful_run_abandoned_cart_in_cloud', 0);
        if ($retainful_run_abandoned_cart_in_cloud) {
            $response = true;
        }
        $retainful_check_is_new_installation = get_option('retainful_check_is_new_installation', 0);
        if (empty($retainful_check_is_new_installation)) {
            $is_new_installation = $this->isInstalledFresh();
            update_option('retainful_is_new_installation', $is_new_installation);
            update_option('retainful_check_is_new_installation', 1);
            $response = $is_new_installation;
        }
        return apply_filters('retainful_manage_abandon_carts_in_cloud', $response);
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
        $default = array('all' => __('All', RNOC_TEXT_DOMAIN));
        $woo_functions = new WcFunctions();
        $woo_statuses = $woo_functions->getAvailableOrderStatuses();
        if (is_array($woo_statuses)) {
            return array_merge($default, $woo_statuses);
        }
        return $default;
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
        $settings = get_option($this->slug, array());
        $expire_days = isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_expire_days']) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_expire_days'] : 60;
        if (!empty($settings) && $this->isAppConnected() && !empty($expire_days)) {
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
        $asset_path = plugins_url('', __FILE__);
        wp_enqueue_script('retainful-app-main', $asset_path . '/js/app.js', array(), RNOC_VERSION);
        wp_enqueue_style('retainful-admin-css', $asset_path . '/css/main.css', array(), RNOC_VERSION);
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
            $coupon['coupon_amount'] = isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount']) && ($settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount'] > 0) ? $settings[RNOC_PLUGIN_PREFIX . 'retainful_coupon_amount'] : 0;
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