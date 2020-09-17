<?php
/**
 * Created by PhpStorm.
 * User: cartrabbit
 * Date: 2019-05-15
 * Time: 13:54
 */
if (!class_exists('RetainfulExitIntentPopupAddon')) {
    include __DIR__ . '/RetainfulPremiumAddonBase.php';

    class RetainfulExitIntentPopupAddon extends RetainfulPremiumAddonBase
    {
        function __construct()
        {
            parent::__construct();
            $this->title = __('Exit Intent Popup', RNOC_TEXT_DOMAIN);
            $this->description = __('When customers try to leave your store, stop them by showing a coupon code or just collect their email and catch them later.', RNOC_TEXT_DOMAIN);
            $this->version = '1.0.0';
            $this->slug = 'exit-intent-popup-editor';
            $this->icon = 'dashicons-external';
        }

        function init()
        {
            if (is_admin()) {
                add_filter('cmb2_render_exit_intent_popup_show_settings', array($this, 'exitIntentPopDisplaySettings'), 10, 5);
                add_filter('rnoc_premium_addon_tab', array($this, 'premiumAddonTab'));
                add_filter('rnoc_premium_addon_tab_content', array($this, 'premiumAddonTabContent'));
                add_action('wp_ajax_rnocp_get_exit_intent_popup_template', array($this, 'getPopupTemplateToInsert'));
            }
            $need_ei_popup = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal', 1);
            add_action('wp_enqueue_scripts', array($this, 'enqueueScript'));
            if ($need_ei_popup) {
                add_action('wp_footer', array($this, 'printExitIntentPopup'));
                add_action('wp_ajax_nopriv_set_rnoc_exit_intent_popup_guest_session', array($this, 'setGuestEmailSession'));
                add_action('wp_ajax_set_rnoc_exit_intent_popup_guest_session', array($this, 'setGuestEmailSession'));
                add_action('wp', array($this, 'applyCouponAutomatically'));
            }
        }

        /**
         * popup editor
         * @return string
         */
        function exitIntentPopInsertTemplate()
        {
            ob_start();
            $template_preview_url = RNOCPREMIUM_PLUGIN_URL . 'assets/images/exit-intent-popup/';
            //echo '<pre>';print_r($field);echo'</pre>';
            ?>
            <div class="rnoc-grid">
                <div class="grid-column hover-design align-center">
                    <div class="template-preview"
                         style='background-image: url("<?php echo $template_preview_url; ?>default.png")'>
                    </div>
                    <button data-template="default" type="button"
                            class="insert-exit-intent-popup-template insert-template">
                        <?php echo __('Insert template', RNOC_TEXT_DOMAIN) ?>
                    </button>
                </div>
                <div class="grid-column align-center hover-design">
                    <div class="template-preview"
                         style='background-image: url("<?php echo $template_preview_url ?>default_email_collection.png")'>
                    </div>
                    <button data-template="default_email_collection" type="button"
                            class="insert-exit-intent-popup-template insert-template"><?php echo __('Insert template',
                            RNOC_TEXT_DOMAIN) ?>
                    </button>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Show the settings field
         * @param $field
         * @param $field_escaped_value
         * @param $field_object_id
         * @param $field_object_type
         * @param $field_type_object
         */
        function exitIntentPopDisplaySettings($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
        {
            $field_name = $field->_name();
            $show_option = isset($field_escaped_value['show_option']) ? $field_escaped_value['show_option'] : 'once_per_page';
            $show_count = isset($field_escaped_value['show_count']) ? $field_escaped_value['show_count'] : 1;
            ?>
            <p>
                <label>
                    <select name="<?php echo $field_name ?>[show_option]" id="exit_intent_popup_show_option">
                        <option <?php echo ($show_option == "once_per_page") ? "selected" : ""; ?>
                                value="once_per_page"><?php echo __('Only once per page', RNOC_TEXT_DOMAIN);//Agrisive 1, count 1
                            ?></option>
                        <option <?php echo ($show_option == "every_time_on_customer_exists") ? "selected" : ""; ?>
                                value="every_time_on_customer_exists"><?php echo __('Every time customer tries to exit', RNOC_TEXT_DOMAIN);//Agrisive 1, count 0
                            ?></option>
                        <option <?php echo ($show_option == "show_x_times_per_page") ? "selected" : ""; ?>
                                value="show_x_times_per_page"><?php echo __('X number of times per page on exit', RNOC_TEXT_DOMAIN);//Agrisive 1, count X
                            ?></option>
                        <option <?php echo ($show_option == "once_per_session") ? "selected" : ""; ?>
                                value="once_per_session"><?php echo __('Only once per session', RNOC_TEXT_DOMAIN);//Agrisive 0, count 1
                            ?></option>
                    </select>
                </label>
            </p>
            <p style="display: <?php echo ($show_option == "show_x_times_per_page") ? 'block' : 'none' ?>"
               id="exit_intent_popup_show_count">
                <label>
                    <?php echo __('Number of times', RNOC_TEXT_DOMAIN) ?>
                    <select name="<?php echo $field_name ?>[show_count]">
                        <?php
                        for ($i = 1; $i <= 10; $i++) {
                            ?>
                            <option <?php echo ($show_count == $i) ? "selected" : ""; ?>
                                    value="<?php echo $i ?>"><?php echo $i; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </label>
            </p>
            <script>
                jQuery(document).on('change', '#exit_intent_popup_show_option', function () {
                    var choosed = jQuery(this).val();
                    var count_field = jQuery("#exit_intent_popup_show_count");
                    if (choosed === "show_x_times_per_page") {
                        count_field.show();
                    } else {
                        count_field.hide();
                    }
                });
            </script>
            <?php
        }

        /**
         * Get email template by ID
         */
        function getPopupTemplateToInsert()
        {
            $template_id = (isset($_REQUEST['id'])) ? sanitize_key($_REQUEST['id']) : 0;
            $content = '';
            $success = false;
            if (!empty($template_id)) {
                $override_path = get_theme_file_path('retainful/premium/templates/exit-intent-popups/' . $template_id . '.php');
                $template_path = RNOCPREMIUM_PLUGIN_PATH . 'templates/exit-intent-popups/' . $template_id . '.php';
                if (file_exists($override_path)) {
                    $template_path = $override_path;
                }
                $template = $this->getTemplateContent($template_path);
                if (!empty($template)) {
                    $content = $template;
                    $success = true;
                } else {
                    $content = __('Sorry, Template not found', RNOC_TEXT_DOMAIN);
                }
            }
            wp_send_json(array('success' => $success, 'content' => $content));
        }

        /**
         * set session email and create coupon if available
         */
        function setGuestEmailSession()
        {
            $run_cart_externally = apply_filters('rnoc_need_to_run_ac_in_cloud', false);
            $message = '';
            $error = true;
            $email = sanitize_email($_REQUEST['email']);
            $this->wc_functions->setCustomerEmail($email);
            $gdpr_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance'][0] : array();
            $need_gdpr = $this->getKeyFromArray($gdpr_settings, RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings', 'no_need_gdpr');
            if (in_array($need_gdpr, array("no_need_gdpr", "dont_show_checkbox"))) {
                $is_buyer_accepting_marketing = 1;
            } else {
                $is_buyer_accepting_marketing = isset($_REQUEST['is_buyer_accepting_marketing']) ? sanitize_key($_REQUEST['is_buyer_accepting_marketing']) : 0;
            }
            $this->wc_functions->setSession('is_buyer_accepting_marketing', $is_buyer_accepting_marketing);
            //Check the abandoned cart needs to run externally or not. If it need to run externally, donts process locally
            if (!$run_cart_externally) {
                $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
                $customer = new WC_Customer();
                $user_session_id = $abandoned_cart->getUserSessionKey();
                $customer->set_email($email);
                if (!empty($user_session_id) && !empty($_REQUEST['email'])) {
                    global $wpdb;
                    $query = "SELECT * FROM `" . $abandoned_cart->guest_cart_history_table . "` WHERE session_id = %s";
                    $results = $wpdb->get_row($wpdb->prepare($query, $user_session_id), OBJECT);
                    if (empty($results)) {
                        $insert_guest = "INSERT INTO `" . $abandoned_cart->guest_cart_history_table . "`(email_id, session_id) VALUES ( %s,%s)";
                        $wpdb->query($wpdb->prepare($insert_guest, $email, $user_session_id));
                    } else {
                        $guest_details_id = $results->id;
                        $query_update = "UPDATE `" . $abandoned_cart->guest_cart_history_table . "` SET email_id=%s, shipping_county=%s, shipping_zipcode=%s, shipping_charges=%s, session_id=%s WHERE id=%d";
                        $wpdb->query($wpdb->prepare($query_update, $email, $user_session_id, $guest_details_id));
                    }
                    $error = false;
                } else {
                    if (isset($_REQUEST['email'])) {
                        $error = false;
                    } else {
                        $message = __('Sorry invalid request!', RNOC_TEXT_DOMAIN);
                    }
                }
            } else {
                $error = false;
                $cart_api = new \Rnoc\Retainful\Api\AbandonedCart\Cart();
                $cart_api->syncCartData(true);
            }
            $checkout_url = $this->getCheckoutUrl();
            $cart_url = $this->getCartUrl();
            $url_to_redirect = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success', 'checkout');
            if ($url_to_redirect == "checkout") {
                $url = $checkout_url;
            } elseif ($url_to_redirect == "cart") {
                $url = $cart_url;
            } else {
                $url = '';
            }
            $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon', NULL);
            if (!empty($coupon_code)) {
                $url = $url . '?rnoc_on_exit_coupon_code=' . $coupon_code;
            }
            $response = array('error' => $error, 'message' => $message);
            if (!empty($url)) {
                $response['redirect'] = $url;
            }
            wp_send_json($response);
        }

        /**
         * Email collection form
         * @return mixed|null
         */
        function getEmailCollectionForm()
        {
            $override_path = get_theme_file_path('retainful/premium/templates/exit-intent-popups/email_collection_form.php');
            $template_path = RNOCPREMIUM_PLUGIN_PATH . 'templates/exit-intent-popups/email_collection_form.php';
            if (file_exists($override_path)) {
                $template_path = $override_path;
            }
            $form_designs = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design', array());
            $form_designs = isset($form_designs[0]) ? $form_designs[0] : array();
            $gdpr_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance'][0] : array();
            $final_settings = array(
                'place_holder' => $this->getKeyFromArray($form_designs, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder', __('Enter E-mail address', RNOC_TEXT_DOMAIN)),
                'button_color' => $this->getKeyFromArray($form_designs, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_color', '#ffffff'),
                'button_bg_color' => $this->getKeyFromArray($form_designs, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_bg_color', '#f20561'),
                'button_text' => $this->getKeyFromArray($form_designs, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text', __('Complete Checkout', RNOC_TEXT_DOMAIN)),
                'button_width' => $this->getKeyFromArray($form_designs, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_width', '100%'),
                'button_height' => $this->getKeyFromArray($form_designs, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_height', '100%'),
                'input_height' => $this->getKeyFromArray($form_designs, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_height', '46px'),
                'input_width' => $this->getKeyFromArray($form_designs, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_width', '100%'),
                'rnoc_gdpr_check_box_settings' => $this->getKeyFromArray($gdpr_settings, RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings', 'no_need_gdpr'),
                'rnoc_gdpr_check_box_message' => $this->getKeyFromArray($gdpr_settings, RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message', __('I accept the <a href="#">Terms and conditions</a>', RNOC_TEXT_DOMAIN))
            );
            return $this->getTemplateContent($template_path, $final_settings);
        }

        /**
         * exit intent popup
         */
        function printExitIntentPopup()
        {
            $content = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template', '');
            if (empty($content)) {
                $content = $this->getDefaultPopupTemplate();
            }
            $coupon_code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon', NULL);
            $checkout_url = $this->getCheckoutUrl();
            $cart_url = $this->getCartUrl();
            $coupon_data = "";
            if (!empty($coupon_code)) {
                $coupon_data = '?rnoc_on_exit_coupon_code=' . $coupon_code;
            }
            $email = $this->wc_functions->getCustomerEmail();
            $to_replace = array(
                'coupon_code' => $coupon_code,
                'checkout_url' => $checkout_url . $coupon_data,
                'checkout_url_without_coupon' => $checkout_url,
                'email_collection_form' => ($this->isValidUserToShow() && empty($email)) ? $this->getEmailCollectionForm() : '',
                'cart_url_without_coupon' => $cart_url,
                'cart_url' => $cart_url . $coupon_data
            );
            $to_replace = apply_filters("rnoc_exit_intent_popup_short_codes", $to_replace, $content);
            foreach ($to_replace as $find => $replace) {
                $content = str_replace('{{' . $find . '}}', $replace, $content);
            }
            $custom_style = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style', NULL);
            echo '<div style="display: none;" class="rnoc-ei-popup">' . $content . '<style>' . $custom_style . '</style></div>';
        }

        /**
         * popup template
         * @return string
         */
        function getPopupTemplate()
        {
            $default_template = $this->getDefaultPopupTemplate();
            return $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template', $default_template);
        }

        /**
         * Get the default template
         * @return string
         */
        function getDefaultPopupTemplate()
        {
            $override_path = get_theme_file_path('retainful/premium/templates/exit-intent-popups/default.php');
            $template_path = RNOCPREMIUM_PLUGIN_PATH . 'templates/exit-intent-popups/default.php';
            if (file_exists($override_path)) {
                $template_path = $override_path;
            }
            return $this->getTemplateContent($template_path, array(), 'exit_intent_popup');
        }

        /**
         * enqueue script
         */
        function enqueueScript()
        {
            $premium_settings = array();
            $this->exitIntentPopupSettings($premium_settings);
            if (!wp_script_is('rnoc-premium')) {
                wp_enqueue_script('rnoc-premium', RNOCPREMIUM_PLUGIN_URL . 'assets/js/premium.js', array('jquery'), RNOC_VERSION);
            }
            wp_localize_script('rnoc-premium', 'rnoc_premium_ei_popup', $premium_settings['ei_popup']);
            if (!wp_style_is('rnoc-premium')) {
                wp_enqueue_style('rnoc-premium', RNOCPREMIUM_PLUGIN_URL . 'assets/css/premium.css', array(), RNOC_VERSION);
            }
        }

        /**
         * exit intent popup settings
         * @param $premium_settings
         */
        function exitIntentPopupSettings(&$premium_settings)
        {
            $need_ei_popup = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal', 1);
            $selected_pages = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages', array());
            if ($need_ei_popup == 1 && $this->isValidPagesToDisplay($selected_pages)) {
                $show_settings = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings', array());
                $mobile_settings = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings', array(array()));
                $mobile_settings = isset($mobile_settings[0]) ? $mobile_settings[0] : array();
                $code = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon', '');
                $premium_settings['ei_popup'] = array(
                    'enable' => 'yes',
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'show_for' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to', 'all'),
                    'is_user_logged_in' => is_user_logged_in() ? 'yes' : 'no',
                    'coupon_code' => !empty($code) ? base64_encode($code) : '',
                    'show_once_its_coupon_applied' => ($this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied', '0') == 1) ? 'yes' : 'no',
                    'applied_coupons' => array(),
                    'show_popup' => $this->getKeyFromArray($show_settings, 'show_option', 'once_per_session'),
                    'number_of_times_per_page' => $this->getKeyFromArray($show_settings, 'show_count', '1'),
                    'cookie_expired_at' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life', '1'),
                );
                if ($this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'enable_mobile_support', '0') == 1) {
                    $premium_settings['ei_popup']['mobile'] = array(
                        'enable' => 'yes',
                        'time_delay' => ($this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'enable_delay_trigger', '0') == 1) ? 'yes' : 'no',
                        'delay' => $this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec', '0'),
                        'scroll_distance' => ($this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger', '0') == 1) ? 'yes' : 'no',
                        'distance' => $this->getKeyFromArray($mobile_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance', '0'),
                    );
                } else {
                    $premium_settings['ei_popup']['mobile'] = array(
                        'enable' => 'no'
                    );
                }
            } else {
                $premium_settings['ei_popup'] = array(
                    'enable' => 'no',
                );
            }
        }

        /**
         * apply coupon automatically
         */
        function applyCouponAutomatically()
        {
            if (isset($_REQUEST['rnoc_on_exit_coupon_code'])) {
                $coupon_code = sanitize_text_field($_REQUEST['rnoc_on_exit_coupon_code']);
                $coupon_code = apply_filters("rnoc_exit_intent_before_applying_coupon_code", $coupon_code);
                if (!empty($coupon_code) && !$this->wc_functions->hasDiscount($coupon_code)) {
                    $this->wc_functions->addDiscount($coupon_code);
                    do_action("rnoc_exit_intent_after_applying_coupon_code", $coupon_code);
                }
            }
        }

        /**
         * add the settings tabs
         * @param $settings
         * @return array
         */
        function premiumAddonTab($settings)
        {
            $settings[] = array(
                'id' => $this->slug,
                'icon' => $this->icon,
                'title' => __('Exit intent Popup', RNOC_TEXT_DOMAIN),
                'fields' => array(
                    RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to',
                    RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance',
                    RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design',
                ),
            );
            return $settings;
        }

        /**
         * add settings field to render
         * @param $general_settings
         * @return mixed
         */
        function premiumAddonTabContent($general_settings)
        {
            $general_settings->add_field(array(
                'name' => __('Enable exit intent popup', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0',
                'desc' => __('Exit intent popup will show when, cart contains some items and user tries to leave the site.', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Custom pages to display the pop-up modal on (Optional)', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages',
                'type' => 'pw_multiselect',
                'options' => $this->getPageLists(),
                'attributes' => array(
                    'placeholder' => __('Select Pages', RNOC_TEXT_DOMAIN)
                ),
                'desc' => __('The exit intent popup would be displayed only on the selected pages.If you wish to display the popup in all pages, leave this option empty.', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Show exit intent popup', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to',
                'type' => 'radio_inline',
                'options' => array(
                    'guest' => __('Only for guest', RNOC_TEXT_DOMAIN),
                    'all' => __('Everyone', RNOC_TEXT_DOMAIN),
                    'non_email_users' => __('When a customer has not yet provided an email address', RNOC_TEXT_DOMAIN)
                ),
                'default' => 'all'
            ));
            $general_settings->add_field(array(
                'type' => 'post_search_ajax',
                'limit' => 1,
                'valuefield' => 'title',
                'attributes' => array(
                    'placeholder' => __('Search and select Coupons..', RNOC_TEXT_DOMAIN)
                ),
                'query_args' => array('post_type' => 'shop_coupon', 'post_status' => 'publish'),
                'name' => __('Choose the coupon code', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon',
                'desc' => __('<b>Note</b>:This is a list of coupon codes from WooCommerce -> Coupons. If none found, please create the coupon code in WooCommerce -> Coupons', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Don\'t show exit intent popup once its coupon applied?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('No, keep showing', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes, hide', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0'
            ));
            $general_settings->add_field(array(
                'name' => __('Show exit popup', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings',
                'type' => 'exit_intent_popup_show_settings',
                'default' => 1,
                'desc' => __('The maximum number of times the dialog may be shown on a page, or 0 for unlimited. Only applicable on desktop browsers.', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'type' => 'number'
                )
            ));
            $general_settings->add_field(array(
                'name' => __('Cookie expiry days', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life',
                'type' => 'text',
                'default' => 1,
                'desc' => __('The cookie (when localStorage isn\'t available) expiry age, in days.', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'type' => 'number'
                )
            ));
            $general_settings->add_field(array(
                'name' => __('Button redirects to', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success',
                'type' => 'radio_inline',
                'options' => array(
                    'cart' => __('Cart page', RNOC_TEXT_DOMAIN),
                    'checkout' => __('Checkout page', RNOC_TEXT_DOMAIN),
                    'same' => __('Stay on same page', RNOC_TEXT_DOMAIN),
                ),
                'default' => 'checkout',
                'desc' => __('This controls whether or not the bounce dialog should be shown on every page view or only on the user\'s first.', RNOC_TEXT_DOMAIN),
            ));
            $mobile_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('Mobile popup settings', RNOC_TEXT_DOMAIN),
                    'sortable' => false
                )
            ));
            $general_settings->add_group_field($mobile_settings, array(
                'name' => __('Enable mobile device support', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_mobile_support',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'description' => 'The following settings are used to trigger Exit Popup in mobile devices. Since there are a number of ways a visitor can exit in a mobile (Example: Swipe up), you can consider showing the popup either based on time delay (time spent by the customer in the site) or  scrolling (the distance the customer scrolled a page)',
                'default' => '0'
            ));
            $general_settings->add_group_field($mobile_settings, array(
                'name' => __('Enable time delay based trigger', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_delay_trigger',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0'
            ));
            $general_settings->add_group_field($mobile_settings, array(
                'name' => __('Delay seconds', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec',
                'type' => 'text',
                'default' => 0,
                'desc' => __('rigger the popup after these many seconds a visitor spent time', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'type' => 'number'
                )
            ));
            $general_settings->add_group_field($mobile_settings, array(
                'name' => __('Enable Scroll based trigger', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0'
            ));
            $general_settings->add_group_field($mobile_settings, array(
                'name' => __('Scroll distance', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance',
                'type' => 'text',
                'default' => 0,
                'desc' => __('Trigger the popup after a visitor scrolled the page to the set distance. Its a percentage value. The distance is the % from the top of the page.', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'type' => 'number'
                )
            ));
            //GDPR settings
            $gdpr_compliance_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('GDPR Compliance for collecting E-Mail', RNOC_TEXT_DOMAIN),
                    'sortable' => true
                )
            ));
            $general_settings->add_group_field($gdpr_compliance_settings, array(
                'name' => __('Show GDPR Compliance checkbox ', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings',
                'type' => 'select',
                'default' => 'no_need_gdpr',
                'options' => $this->complianceMessageOptions()
            ));
            $general_settings->add_group_field($gdpr_compliance_settings, array(
                'name' => __('GDPR Compliance message', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message',
                'type' => 'textarea',
                'default' => __('I accept the <a href="#">Terms and conditions</a>', RNOC_TEXT_DOMAIN),
                'desc' => __('You can also use HTML content as well in the message.', RNOC_TEXT_DOMAIN)
            ));
            $before_editor = $this->exitIntentPopInsertTemplate();
            $general_settings->add_field(array(
                'name' => __('Popup template', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template',
                'type' => 'textarea',
                'before' => $before_editor . '<div class="rnoc-grid"> <div class="grid-column">',
                'after' => '<button type="button" class="insert-template" id="rnoc_exit_intent_popup_template_show_preview">' . __("Preview", RNOC_TEXT_DOMAIN) . '</button></div><div class="grid-column" id="exit-intent-popup-preview"></div></div><style id="custom-style-container"></style>',
                'default' => $this->getDefaultPopupTemplate(),
                'desc' => __('Please use the below short codes to show the Coupon details in the message.<br><b>{{coupon_code}}</b> - Coupon code<br><b>{{cart_url}}</b> - Url to redirect to cart page<br><b>{{cart_url_without_coupon}}</b> - Url to redirect to cart page without auto applying coupon<br><b>{{checkout_url}}</b> - Url to redirect user to checkout page<br><b>{{checkout_url_without_coupon}}</b> - Url to redirect user to checkout page without auto apply coupon code<br><b>{{email_collection_form}}</b> - To display email collection form. Note: Email collection form will only show to Guest and Administrator.', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Custom CSS styles', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style',
                'type' => 'textarea',
                'default' => ''
            ));
            //Form design
            $form_design_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('Email collection form design', RNOC_TEXT_DOMAIN),
                    'sortable' => true
                ),
            ));
            $general_settings->add_group_field($form_design_settings, array(
                'name' => __('Email input placeholder', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder',
                'type' => 'text',
                'default' => __('Enter E-mail address', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($form_design_settings, array(
                'name' => __('Email input height', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_height',
                'type' => 'text',
                'default' => '46px',
                'desc' => ''
            ));
            $general_settings->add_group_field($form_design_settings, array(
                'name' => __('Email input width', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_width',
                'type' => 'text',
                'default' => '100%',
                'desc' => ''
            ));
            $general_settings->add_group_field($form_design_settings, array(
                'name' => __('Button text', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text',
                'type' => 'text',
                'default' => __('Complete checkout', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($form_design_settings, array(
                'name' => __('Button color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_color',
                'type' => 'colorpicker',
                'default' => '#ffffff'
            ));
            $general_settings->add_group_field($form_design_settings, array(
                'name' => __('Button background color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_bg_color',
                'type' => 'colorpicker',
                'default' => '#f20561'
            ));
            $general_settings->add_group_field($form_design_settings, array(
                'name' => __('Button height', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_height',
                'type' => 'text',
                'default' => '100%',
                'desc' => ''
            ));
            $general_settings->add_group_field($form_design_settings, array(
                'name' => __('Button width', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_width',
                'type' => 'text',
                'default' => '100%',
                'desc' => ''
            ));
            return $general_settings;
        }
    }
}
