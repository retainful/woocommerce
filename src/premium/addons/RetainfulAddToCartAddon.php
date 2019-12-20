<?php
/**
 * Created by PhpStorm.
 * User: cartrabbit
 * Date: 2019-05-15
 * Time: 13:54
 */
if (!class_exists('RetainfulAddToCartAddon')) {
    include __DIR__ . '/RetainfulPremiumAddonBase.php';

    class RetainfulAddToCartAddon extends RetainfulPremiumAddonBase
    {
        function __construct()
        {
            parent::__construct();
            $this->title = __('Add-to-Cart Email Collection Popup', RNOC_TEXT_DOMAIN);
            $this->description = __('Collect customer email at the time of adding to cart. This can help recover the cart even if the customer abandon it before checkout', RNOC_TEXT_DOMAIN);
            $this->version = '1.0.0';
            $this->slug = 'add-to-cart-popup-editor';
            $this->icon = 'dashicons-cart';
        }

        function init()
        {
            if (is_admin()) {
                add_filter('cmb2_render_popup_preview', array($this, 'renderPopupPreview'), 10, 5);
                add_filter('rnoc_premium_addon_tab', array($this, 'premiumAddonTab'));
                add_filter('rnoc_premium_addon_tab_content', array($this, 'premiumAddonTabContent'));
            }
            add_action('wp_ajax_nopriv_set_rnoc_guest_session', array($this, 'setGuestEmailSession'));
            add_action('wp_ajax_nopriv_rnoc_popup_closed', array($this, 'popupClosed'));
            //To support the logged in user
            add_action('wp_ajax_set_rnoc_guest_session', array($this, 'setGuestEmailSession'));
            add_action('wp_ajax_rnoc_popup_closed', array($this, 'popupClosed'));
            add_action('wp', array($this, 'siteInit'));
            add_filter('woocommerce_checkout_fields', array($this, 'addCheckoutEmail'));
        }

        /**
         * @param $fields
         * @return mixed
         */
        function addCheckoutEmail($fields)
        {
            $user_email = $this->wc_functions->getSession('rnoc_user_billing_email');
            $fields['billing']['billing_email']['default'] = $user_email;
            return $fields;
        }

        /**
         * Render select box field
         */
        public function renderPopupPreview($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
        {
            echo $this->getPopupTemplate();
        }

        /**
         * popup template
         * @return string
         */
        function getPopupTemplate()
        {
            $coupon_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0] : array();
            $gdpr_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0] : array();
            $coupon_message = '';
            $coupon_message_color = '#333333';
            if ($this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'need_coupon', 0) == 1) {
                $coupon_code = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'woo_coupon', NULL);
                if (!empty($coupon_code)) {
                    $coupon_message = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'modal_sub_heading', 'Get a discount in your email!');
                    $coupon_message_color = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color', '#333333');
                }
            }
            $default_settings = array(
                'rnoc_popup_form_open' => (is_admin()) ? '' : '<form id="rnoc_popup_form">',
                'rnoc_modal_heading' => __('Enter your email to add this item to cart', RNOC_TEXT_DOMAIN),
                'rnoc_modal_heading_color' => '#000000',
                'rnoc_modal_sub_heading' => $coupon_message,
                'rnoc_modal_sub_heading_color' => $coupon_message_color,
                'rnoc_modal_email_placeholder' => __('Email address', RNOC_TEXT_DOMAIN),
                'rnoc_modal_add_cart_text' => __('Add to Cart', RNOC_TEXT_DOMAIN),
                'rnoc_modal_add_cart_color' => '#ffffff',
                'rnoc_modal_add_cart_bg_color' => '#f27052',
                'rnoc_modal_add_cart_border_top_color' => '#f27052',
                'rnoc_modal_add_cart_no_thanks_color' => '#f27052',
                'rnoc_modal_bg_color' => '#F8F0F0',
                'rnoc_modal_not_mandatory_text' => __('No thanks! Add item to cart', RNOC_TEXT_DOMAIN),
                'rnoc_modal_terms_text' => __('*By completing this, you are signing up to receive our emails. You can unsubscribe at any time.', RNOC_TEXT_DOMAIN),
                'rnoc_coupon_message' => '',
                'rnoc_no_thanks_action' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action', 1),
                'rnoc_modal_show_popup_until' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_show_popup_until', 1),
                'rnoc_popup_email_field' => (!is_admin()) ? '' : 'readonly',
                'rnoc_popup_form_close' => (is_admin()) ? '' : '</form>',
                'rnoc_gdpr_check_box_settings' => $this->getKeyFromArray($gdpr_settings, RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings', 'no_need_gdpr'),
                'rnoc_gdpr_check_box_message' => $this->getKeyFromArray($gdpr_settings, RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message', __('I accept the <a href="#">Terms and conditions</a>', RNOC_TEXT_DOMAIN))
            );
            $choosed_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0] : $default_settings;
            $final_settings = array_merge($default_settings, $choosed_settings);
            $override_path = get_theme_file_path('retainful/premium/templates/popup.php');
            $popup_template_path = RNOCPREMIUM_PLUGIN_PATH . 'templates/popup.php';
            if (file_exists($override_path)) {
                $popup_template_path = $override_path;
            }
            return $this->getTemplateContent($popup_template_path, $final_settings, $this->slug);
        }

        /**
         * init the addon
         */
        function siteInit()
        {
            if (defined('RNOC_VERSION') && $this->isValidUserToShow()) {
                if (version_compare(RNOC_VERSION, '1.1.5', '>')) {
                    $this->admin = new Rnoc\Retainful\Admin\Settings();
                    $this->wc_functions = new \Rnoc\Retainful\WcFunctions();
                    $this->applyCouponAutomatically();
                    if (is_admin()) {
                        add_action('wp_footer', array($this, 'addPopupEditor'));
                    }
                    $need_popup = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_modal', 0);
                    if ($need_popup == 0) {
                        return false;
                    }
                    add_action('wp_enqueue_scripts', array($this, 'addSiteInstantCouponScripts'));
                    $modal_display_pages = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_display_pages', array());
                    if (!$this->isValidPagesToDisplay($modal_display_pages)) {
                        return false;
                    }
                    $is_popup_closed_by_user = $this->wc_functions->getSession('rnoc_popup_closed_by_user');
                    if (!empty($is_popup_closed_by_user)) {
                        return false;
                    }
                    $run_cart_externally = apply_filters('rnoc_need_to_run_ac_in_cloud', false);
                    $show_popup = false;
                    if ($run_cart_externally) {
                        $email = $this->wc_functions->getSession('rnoc_user_billing_email');
                        if (!empty($email)) {
                            return false;
                        }
                        $show_popup = true;
                    } else {
                        $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
                        $user_session_id = $abandoned_cart->getUserSessionKey();
                        if (!empty($user_session_id)) {
                            global $wpdb;
                            $query = "SELECT * FROM `" . $abandoned_cart->guest_cart_history_table . "` WHERE session_id = %s";
                            $results = $wpdb->get_row($wpdb->prepare($query, $user_session_id), OBJECT);
                            if (empty($results)) {
                                $show_popup = true;
                            }
                        }
                    }
                    if ($show_popup) {
                        add_action('wp_enqueue_scripts', array($this, 'addSiteScripts'));
                        add_action('wp_footer', array($this, 'addToCartPopup'), 10);
                    }
                }
            }
            return true;
        }

        /**
         * apply coupon automatically
         */
        function applyCouponAutomatically()
        {
            if (isset($_REQUEST['retainful_email_coupon_code'])) {
                $coupon_code = sanitize_text_field($_REQUEST['retainful_email_coupon_code']);
                if (!empty($coupon_code) && !$this->wc_functions->hasDiscount($coupon_code)) {
                    $this->wc_functions->addDiscount($coupon_code);
                }
            }
        }

        /**
         * Popup showed and closed
         */
        function popupClosed()
        {
            $popup_action = isset($_POST['popup_action']) ? sanitize_key($_POST['popup_action']) : 1;
            $this->wc_functions->setSession('rnoc_popup_closed_by_user', $popup_action);
            wp_send_json_success();
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
            $gdpr_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0] : array();
            $need_gdpr = $this->getKeyFromArray($gdpr_settings, RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings', 'no_need_gdpr');
            if (in_array($need_gdpr, array("no_need_gdpr", "dont_show_checkbox"))) {
                $is_buyer_accepting_marketing = 1;
            } else {
                $is_buyer_accepting_marketing = isset($_REQUEST['is_buyer_accepting_marketing']) ? sanitize_key($_REQUEST['is_buyer_accepting_marketing']) : 0;
            }
            $this->wc_functions->setSession('is_buyer_accepting_marketing', $is_buyer_accepting_marketing);
            $this->wc_functions->setSession('rnoc_user_billing_email', $email);
            $this->wc_functions->setPHPSession('rnoc_php_user_billing_email', $email);
            $this->admin->logMessage($email, 'Add to cart email collection popup email entered');
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
                    if (empty($_REQUEST['email'])) {
                        $error = false;
                    } else {
                        $message = __('Sorry invalid request!', RNOC_TEXT_DOMAIN);
                    }
                }
            } else {
                $error = false;
            }
            $coupon_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0] : array();
            if ($this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'need_coupon', 0) == 1) {
                $coupon_code = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'woo_coupon');
                if (!empty($coupon_code)) {
                    $show_woo_coupon = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'show_woo_coupon', 'send_via_email');
                    switch ($show_woo_coupon) {
                        case "both":
                            $this->sendEmail($email, $coupon_settings);
                            break;
                        default:
                        case "send_via_email":
                            $this->sendEmail($email, $coupon_settings);
                            break;
                    }
                }
            }
            wp_send_json(array('error' => $error, 'message' => $message));
        }

        /**
         * send coupon details via mail
         * @param $email
         * @param $coupon_settings
         * @return string
         */
        function sendEmail($email, $coupon_settings)
        {
            $message = __('Thanks for providing Email.', RNOC_TEXT_DOMAIN);
            $coupon_code = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'woo_coupon');
            if (!empty($coupon_code)) {
                $headers = $this->getMailHeaders();
                $mail_content = $this->getMailCouponContent($coupon_settings);
                $mail_subject = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject', __('You got a new coupon code, Grab it now!', RNOC_TEXT_DOMAIN));
                wc_mail($email, $mail_subject, $mail_content, $headers);
                $message = __('We have sent the coupon code to your email.', RNOC_TEXT_DOMAIN);
            }
            return $message;
        }

        /**
         * @param $coupon_settings
         * @return mixed|null
         */
        function getCouponPopupContent($coupon_settings)
        {
            $final_settings = array(
                "show_for_admin" => current_user_can('administrator'),
                "template" => $this->getMailCouponContent($coupon_settings, "popup"),
                "custom_style" => '',
                "add_on_slug" => 'rnoc-add-to-cart-add-on-instant-coupon',
                "no_thanks_action" => 1,
                "is_email_mandatory" => 1
            );
            return $this->getTemplateContent(RNOCPREMIUM_PLUGIN_PATH . 'templates/popup_display.php', $final_settings, $this->slug);
        }

        /**
         * get the content to
         * @param $coupon_settings
         * @param $template
         * @return mixed|string
         */
        function getMailCouponContent($coupon_settings, $template = "mail")
        {
            $coupon_code = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'woo_coupon');
            $mail_content = "";
            if (!empty($coupon_code)) {
                $mail_content = ($template == "mail") ? $this->getMailContent() : $this->getPopupContent();
                $cart_page_link = $this->wc_functions->getCartUrl();
                $string_to_replace = array(
                    '{{coupon_code}}' => $coupon_code,
                    '{{coupon_url}}' => $cart_page_link . '?retainful_email_coupon_code=' . $coupon_code
                );
                foreach ($string_to_replace as $find => $replace) {
                    $mail_content = str_replace($find, $replace, $mail_content);
                }
            }
            return $mail_content;
        }

        /**
         * content for sending mail
         * @return string
         */
        function getMailContent()
        {
            $coupon_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0] : array();
            if ($this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'coupon_mail_template')) {
                $mail_content = __($coupon_settings[RNOC_PLUGIN_PREFIX . 'coupon_mail_template'], RNOC_TEXT_DOMAIN);
            } else {
                $mail_content = $this->getDefaultEmailTemplate();
            }
            return $mail_content;
        }

        /**
         * content for showing popup
         * @return string
         */
        function getPopupContent()
        {
            $coupon_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0] : array();
            if ($this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template')) {
                $content = __($coupon_settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template'], RNOC_TEXT_DOMAIN);
            } else {
                $content = $this->getDefaultPopupTemplate();
            }
            return $content;
        }

        /**
         * Mail headers
         * @return mixed|string
         */
        function getMailHeaders()
        {
            $email_templates_settings = $this->admin->getEmailTemplatesSettings();
            $admin_email = get_option('admin_email');
            $details = array(
                "from_name" => $this->getKeyFromArray($email_templates_settings, RNOC_PLUGIN_PREFIX . 'email_from_name', __('Admin', RNOC_TEXT_DOMAIN)),
                "from_address" => $this->getKeyFromArray($email_templates_settings, RNOC_PLUGIN_PREFIX . 'email_from_address', $admin_email),
                "replay_address" => $this->getKeyFromArray($email_templates_settings, RNOC_PLUGIN_PREFIX . 'email_reply_address', $admin_email)
            );
            $details = apply_filters('rnocp_reward_coupon_mail_sender_details', $details);
            extract($details);
            $charset = (function_exists('get_bloginfo')) ? get_bloginfo('charset') : 'UTF-8';
            //Prepare for sending emails
            $headers = array(
                "From: \"$from_name\" <$from_address>",
                "Return-Path: <" . $from_address . ">",
                "Reply-To: \"" . $from_name . "\" <" . $replay_address . ">",
                "X-Mailer: PHP" . phpversion(),
                "Content-Type: text/html; charset=\"" . $charset . "\""
            );
            $header = implode("\n", $headers);
            $header = apply_filters('rnocp_modify_coupon_email_headers', $header);
            return $header;
        }

        /**
         * Add the site scripts needed for addon
         */
        function addSiteScripts()
        {
            wp_enqueue_script('rnoc-add-to-cart', RNOCPREMIUM_PLUGIN_URL . 'assets/js/popup.js', array('wc-add-to-cart', 'wc-add-to-cart-variation'), RNOC_VERSION);
            $modal_show_popup_until = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_show_popup_until', 1);
            $modal_show = array(
                'hide_modal_after_show' => $modal_show_popup_until,
                'jquery_url' => includes_url('js/jquery/jquery.js'),
                "enable_add_to_cart_popup" => ($this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_modal', 0) == 0) ? "no" : "yes",
                "is_email_mandatory" => ($this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory', 1) == 1) ? "yes" : "no",
                "no_thanks_action" => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action', 1),
                "show_popup_until" => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_show_popup_until', 1),
            );
            wp_localize_script('rnoc-add-to-cart', 'retainful_premium_add_to_cart_collection_popup_condition', $modal_show);
            $modal_popup_extra_classes = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class', null);
            $extra_classes = !empty($modal_popup_extra_classes) ? array('add_to_cart_button_classes' => $modal_popup_extra_classes) : array();
            $classes_list = apply_filters('retainful_premium_add_to_cart_collection_button_classes', $extra_classes);
            if (!empty($classes_list)) {
                wp_localize_script('rnoc-add-to-cart', 'retainful_premium_add_to_cart_collection', $classes_list);
            }
        }

        /**
         * Add the site scripts needed for addon
         */
        function addSiteInstantCouponScripts()
        {
            wp_enqueue_script('rnoc-add-to-cart-instant-popup', RNOCPREMIUM_PLUGIN_URL . 'assets/js/add_to_cart_coupon_popup.js', array('wc-add-to-cart', 'wc-add-to-cart-variation'), RNOC_VERSION);
            wp_enqueue_style('rnoc-add-to-cart', RNOCPREMIUM_PLUGIN_URL . 'assets/css/popup.css', array(), RNOC_VERSION);
        }

        /**
         * show coupon popup
         * @return string
         */
        function getAddToCartCouponPopup()
        {
            $coupon_details = "";
            $show_coupon_popup = false;
            $coupon_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0] : array();
            if ($this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'need_coupon', 0) == 1) {
                $coupon_code = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'woo_coupon');
                if (!empty($coupon_code)) {
                    $show_woo_coupon = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'show_woo_coupon', 'send_via_email');
                    switch ($show_woo_coupon) {
                        case "instantly":
                            $show_coupon_popup = true;
                            $coupon_details = $this->getCouponPopupContent($coupon_settings);
                            break;
                        case "both":
                            $show_coupon_popup = true;
                            $coupon_details = $this->getCouponPopupContent($coupon_settings);
                            break;
                        default:
                        case "send_via_email":
                            break;
                    }
                }
            }
            $encoded = wp_json_encode(array("coupon_instant_popup_content" => $coupon_details, "show_coupon_instant_popup" => $show_coupon_popup));
            return "<script>var rnoc_add_to_cart_coupon_popup={$encoded}</script>";
        }

        /**
         * popup html
         */
        function addToCartPopup()
        {
            $template = $this->getPopupTemplate();
            $final_settings = array(
                "show_for_admin" => current_user_can('administrator'),
                "template" => $template . $this->getAddToCartCouponPopup(),
                "custom_style" => '',
                "add_on_slug" => 'rnoc-add-to-cart-add-on',
                "no_thanks_action" => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action', 1),
                "is_email_mandatory" => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory', 1)
            );
            if (!is_admin()) {
                echo $this->getTemplateContent(RNOCPREMIUM_PLUGIN_PATH . 'templates/popup_display.php', $final_settings, $this->slug);
            }
        }

        /**
         * Default email template
         * @return string
         */
        function getDefaultEmailTemplate()
        {
            return __('<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">You have new Discount code</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">We want to offer you an exclusive voucher for your order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #f27052; color: #f27052; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #f27052; width: fit-content; padding: 10px; border: 1px solid #f27052; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Go! </a></p></div></div>', RNOC_TEXT_DOMAIN);
        }

        /**
         * Default popup template
         * @return string
         */
        function getDefaultPopupTemplate()
        {
            return __('<div style="padding: 20px; background: #F8F0F0; border-radius: 15px;"><div style="padding: 20px; text-align: center;"><div style="padding: 0px 12px; margin-bottom: 0; font-size: 35px; color: #1f1e1f; font-weight: 600; line-height: 45px;">You have new Discount code</div><p style="font-size: 20px; padding: 0px 15px; line-height: 20px; margin-bottom: 15px; margin-top: 28px;">Get 15% off on any item when you buy today</p><div style="padding: 0px; color: #2f2e35;"><div style="text-align: center; padding: 20px 30px;"><a style="width: 60%; padding: 12px 20%; background: #ffffff; border-radius: 4px; font-size: 16px; font-weight: 600; color: #2f2e35; text-align: center; line-height: 1.33333; margin-top: 0px; margin-bottom: 5px; border: 1px dashed #2f2e35; text-decoration: none;" href="{{coupon_url}}">{{coupon_code}}</a> <a style="padding: 12px 20px; background: #f27052; border: none; border-radius: 4px; font-size: 18px; font-weight: 600; color: white; text-align: center; line-height: 1.33333; text-decoration: none;" href="{{coupon_url}}">Use a coupon</a></div><a style="text-decoration: none; font-size: 16px; line-height: 24px; color: #6c6b70 !important; font-weight: 500!important; margin-bottom: -15px;" href="#">*Not valid with other discount codes</a></div></div></div>', RNOC_TEXT_DOMAIN);
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
                'title' => __('Add to cart Popup', RNOC_TEXT_DOMAIN),
                'fields' => array(
                    RNOC_PLUGIN_PREFIX . 'popup_preview',
                    RNOC_PLUGIN_PREFIX . 'modal_design_settings',
                    RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory',
                    RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance',
                    RNOC_PLUGIN_PREFIX . 'modal_coupon_settings',
                    RNOC_PLUGIN_PREFIX . 'need_modal',
                    RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class',
                    RNOC_PLUGIN_PREFIX . 'modal_add_cart_border_top_color',
                    RNOC_PLUGIN_PREFIX . 'modal_bg_color',
                    RNOC_PLUGIN_PREFIX . 'modal_add_cart_no_thanks_color',
                    RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action',
                    RNOC_PLUGIN_PREFIX . 'modal_show_popup_until',
                    RNOC_PLUGIN_PREFIX . 'modal_display_pages',
                    RNOC_PLUGIN_PREFIX . 'woo_coupons'
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
                'name' => __('Enable Add to cart popup modal', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'need_modal',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0'
            ));
            $general_settings->add_field(array(
                'name' => __('Email address is mandatory ?', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory',
                'type' => 'radio_inline',
                'classes' => 'retainful-coupon-group',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => 1
            ));
            $general_settings->add_field(array(
                'name' => __('No thanks action', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('Do not allow adding the item to cart and Do not show "No thanks" link', RNOC_TEXT_DOMAIN),
                    '1' => __('Allow adding item to cart (Show "No thanks" link)', RNOC_TEXT_DOMAIN)
                ),
                'default' => 1
            ));
            $general_settings->add_field(array(
                'name' => __('Show E-mail collection popup', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_show_popup_until',
                'type' => 'radio',
                'options' => array(
                    '1' => __('Until user provides an E-Mail address', RNOC_TEXT_DOMAIN),
                    '2' => __('Until user clicks "No thanks" link (It will stop showing once user clicked no thanks)', RNOC_TEXT_DOMAIN),
                    '3' => __('Until user clicks close button of the popup (It will stop when user clicks the close button once)', RNOC_TEXT_DOMAIN)
                ),
                'default' => 1
            ));
            $general_settings->add_field(array(
                'name' => __('Custom pages to display the pop-up modal on (Optional)', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_display_pages',
                'type' => 'pw_multiselect',
                'options' => $this->getPageLists(),
                'attributes' => array(
                    'placeholder' => __('Select Pages', RNOC_TEXT_DOMAIN)
                ),
                'desc' => __('The add to cart popup would be displayed only on the selected pages.If you wish to display the popup in all pages, leave this option empty.', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_field(array(
                'name' => __('Custom classes', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class',
                'type' => 'textarea',
                'default' => '',
                'desc' => __('Very helpful for custom designed Add to cart button.<b>Example:</b> .add-to-cart,.custom-add-to-cart-button', RNOC_TEXT_DOMAIN)
            ));
            //GDPR settings
            $gdpr_compliance_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance',
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
            //coupon settings
            $popup_coupon_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'modal_coupon_settings',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('Coupon settings - Incentivize the customers for entering their email', RNOC_TEXT_DOMAIN),
                    'sortable' => true
                )
            ));
            $general_settings->add_group_field($popup_coupon_settings, array(
                'name' => __('Enable Coupon ', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'need_coupon',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0',
                'desc' => __('Please enable the add to cart popup for email collection. Only then the users would be able to avail the coupons you set here.', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($popup_coupon_settings, array(
                'name' => __('Coupon message on popup', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_sub_heading',
                'type' => 'text',
                'default' => __('Get a discount in your email!', RNOC_TEXT_DOMAIN),
                'desc' => __('<b>Note:</b> You need to enable coupon.')
            ));
            $general_settings->add_group_field($popup_coupon_settings, array(
                'name' => __('Coupon message color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color',
                'type' => 'colorpicker',
                'default' => '#333333'
            ));
            $general_settings->add_group_field($popup_coupon_settings, array(
                'name' => __('Choose the coupon code', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'woo_coupon',
                'type' => 'pw_select',
                'options' => $this->getWooCouponCodes(),
                'attributes' => array(
                    'placeholder' => __('Select Coupon', RNOC_TEXT_DOMAIN)
                ),
                'desc' => __('<b>Note</b>:This is a list of coupon codes from WooCommerce -> Coupons. If none found, please create the coupon code in WooCommerce -> Coupons', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($popup_coupon_settings, array(
                'name' => __('Show coupon code', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'show_woo_coupon',
                'type' => 'radio_inline',
                'options' => array(
                    "instantly" => __("Instantly using a popup", RNOC_TEXT_DOMAIN),
                    "send_via_email" => __("Send an email", RNOC_TEXT_DOMAIN),
                    "both" => __("Show instantly and also send an email", RNOC_TEXT_DOMAIN),
                ),
                'attributes' => array(
                    'placeholder' => __('Choose coupon settings', RNOC_TEXT_DOMAIN)
                ),
                'default' => "send_via_email",
                'desc' => __('How to show the coupon to customers', RNOC_TEXT_DOMAIN)
            ));
            $general_settings->add_group_field($popup_coupon_settings, array(
                'name' => __('Popup template', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template',
                'type' => 'wysiwyg',
                'default' => $this->getDefaultPopupTemplate(),
                'desc' => __('Please use the below short codes to show the Coupon details in the message.<br><b>{{coupon_code}}</b> - Coupon code<br><b>{{coupon_url}}</b> - Url to apply coupon automatically', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'data-conditional-id' => RNOC_PLUGIN_PREFIX . 'show_woo_coupon',
                    'data-conditional-value' => wp_json_encode(array('both', 'instantly')),
                )
            ));
            $general_settings->add_group_field($popup_coupon_settings, array(
                'name' => __('Email subject', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject',
                'type' => 'text',
                'default' => __('You got a new coupon code, Grab it now!', RNOC_TEXT_DOMAIN),
                'desc' => __('Email subject for sending the coupon mail.', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'data-conditional-id' => RNOC_PLUGIN_PREFIX . 'show_woo_coupon',
                    'data-conditional-value' => wp_json_encode(array('both', 'send_via_email')),
                ),
            ));
            $general_settings->add_group_field($popup_coupon_settings, array(
                'name' => __('Email template (Used for the email that is sent when customer enters his email in the Add to Cart Popup)', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'coupon_mail_template',
                'type' => 'wysiwyg',
                'default' => $this->getDefaultEmailTemplate(),
                'desc' => __('Please use the below short codes to show the Coupon details in the message.<br><b>{{coupon_code}}</b> - Coupon code<br><b>{{coupon_url}}</b> - Url to apply coupon automatically', RNOC_TEXT_DOMAIN),
                'attributes' => array(
                    'data-conditional-id' => RNOC_PLUGIN_PREFIX . 'show_woo_coupon',
                    'data-conditional-value' => wp_json_encode(array('both', 'send_via_email')),
                )
            ));
            //Modal design
            $popup_design_settings = $general_settings->add_field(array(
                'id' => RNOC_PLUGIN_PREFIX . 'modal_design_settings',
                'type' => 'group',
                'repeatable' => false,
                'options' => array(
                    'group_title' => __('Popup Design', RNOC_TEXT_DOMAIN),
                    'sortable' => true
                ),
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => 'Sample Preview (Customize the texts and colours below)',
                'id' => RNOC_PLUGIN_PREFIX . 'popup_preview',
                'type' => 'popup_preview',
                'default' => __('Please enter your email', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Modal heading', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_heading',
                'type' => 'text',
                'default' => __('Enter your email to add this item to cart', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Modal heading color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_heading_color',
                'type' => 'colorpicker',
                'default' => '#000000'
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Email placeholder', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_email_placeholder',
                'type' => 'text',
                'default' => __('Email address', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Add to cart button text', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_add_cart_text',
                'type' => 'text',
                'default' => __('Add to Cart', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Add to cart button color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_add_cart_color',
                'type' => 'colorpicker',
                'default' => '#ffffff'
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Add to cart button background color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_add_cart_bg_color',
                'type' => 'colorpicker',
                'default' => '#f27052'
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Popup top border color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_add_cart_border_top_color',
                'type' => 'colorpicker',
                'default' => '#f27052'
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Add to cart popup background color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_bg_color',
                'type' => 'colorpicker',
                'default' => '#F8F0F0'
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Not mandatory text', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_not_mandatory_text',
                'type' => 'text',
                'default' => __('No thanks! Add item to cart', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('No thanks link color', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_add_cart_no_thanks_color',
                'type' => 'colorpicker',
                'default' => '#f27052'
            ));
            $general_settings->add_group_field($popup_design_settings, array(
                'name' => __('Terms', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'modal_terms_text',
                'type' => 'text',
                'default' => __('*By completing this, you are signing up to receive our emails. You can unsubscribe at any time.', RNOC_TEXT_DOMAIN),
                'desc' => ''
            ));
            return $general_settings;
        }
    }
}