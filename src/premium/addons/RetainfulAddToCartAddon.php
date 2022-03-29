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
                add_action('rnoc_premium_addon_settings_page_' . $this->slug(), array($this, 'premiumAddonTabContent'), 10, 3);
            }
            add_action('wp_ajax_nopriv_set_rnoc_guest_session', array($this, 'setGuestEmailSession'));
            add_action('wp_ajax_nopriv_rnoc_popup_closed', array($this, 'popupClosed'));
            //To support the logged in user
            add_action('wp_ajax_set_rnoc_guest_session', array($this, 'setGuestEmailSession'));
            add_action('wp_ajax_rnoc_popup_closed', array($this, 'popupClosed'));
            add_action('wp_footer', array($this, 'enqueue_script'));
            add_action('wp', array($this, 'siteInit'));
            add_action('woocommerce_add_to_cart', array($this, 'productAddedToCart'));
        }

        /**
         * after product added to cart
         */
        function productAddedToCart()
        {
            if (isset($_POST['rnoc_email_popup']) && !empty($_POST['rnoc_email_popup'])) {
                $email = sanitize_text_field($_POST['rnoc_email_popup']);
                $this->wc_functions->setCustomerEmail($email);
            }
        }

        function enqueue_script()
        {
            ?>
            <script>
                jQuery(document).ready(function ($) {
                    $(document).on('added_to_cart', function (fragment, cart_hash, this_button) {
                        rnoc_redirect_coupon_popup();
                    });

                    function rnoc_redirect_coupon_popup() {
                        try {
                            let is_once_redirected = sessionStorage.getItem("rnoc_instant_coupon_is_redirected");
                            if (is_once_redirected && is_once_redirected === "no") {
                                let redirect_url = sessionStorage.getItem("rnoc_instant_coupon_popup_redirect");
                                sessionStorage.setItem("rnoc_instant_coupon_is_redirected", "yes");
                                window.location.href = redirect_url;
                            }
                        } catch (err) {
                            return false;
                        }
                    }

                    rnoc_redirect_coupon_popup();
                });
            </script>
            <?php
        }

        /**
         * Enqueue scripts and styles
         */
        function setupAdminScripts()
        {
            wp_register_style('rnoc-popup', RNOCPREMIUM_PLUGIN_URL . '/assets/css/popup.css', array(), RNOC_VERSION);
            if (!wp_style_is('rnoc-popup')) {
                wp_enqueue_style('rnoc-popup');
            }
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
                'rnoc_popup_form_open' => (is_admin()) ? '' : '<form id="rnoc_popup_form" class="rnoc-lw-wrap">',
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
                'rnoc_modal_email_field_width' => 70,
                'rnoc_modal_button_field_width' => 70,
                'rnoc_close_btn_behavior' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'close_btn_behavior', 'just_close'),
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
                    $email = $this->wc_functions->getCustomerEmail();
                    if (!empty($email)) {
                        return false;
                    }
                    $show_popup = apply_filters('rnoc_need_to_show_atc_popup', true);
                    $is_checkout_page = apply_filters("rnoc_need_atc_popup_in_checkout_page", is_checkout());
                    if ($show_popup && $is_checkout_page == false) {
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
            $message = '';
            $email = sanitize_email($_REQUEST['email']);
            $gdpr_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0] : array();
            $need_gdpr = $this->getKeyFromArray($gdpr_settings, RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings', 'no_need_gdpr');
            if (in_array($need_gdpr, array("no_need_gdpr", "dont_show_checkbox"))) {
                $is_buyer_accepting_marketing = 1;
            } else {
                $is_buyer_accepting_marketing = isset($_REQUEST['is_buyer_accepting_marketing']) ? sanitize_key($_REQUEST['is_buyer_accepting_marketing']) : 0;
            }
            $this->wc_functions->initWoocommerceSession();
            $this->wc_functions->setSession('is_buyer_accepting_marketing', $is_buyer_accepting_marketing);
            $this->wc_functions->setCustomerEmail($email);
            do_action('rnoc_after_atcp_assigning_email_to_customer', $email, $this);
            $this->admin->logMessage($email, 'Add to cart email collection popup email entered');
            $coupon_details = "";
            $show_coupon_popup = false;
            $coupon_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0] : array();
            $redirect_url = null;
            if ($this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'need_coupon', 0) == 1) {
                $coupon_code = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'woo_coupon');
                if (!empty($coupon_code)) {
                    $show_woo_coupon = $this->getKeyFromArray($coupon_settings, RNOC_PLUGIN_PREFIX . 'show_woo_coupon', 'send_via_email');
                    switch ($show_woo_coupon) {
                        case "auto_apply_and_redirect":
                            $this->wc_functions->addDiscount($coupon_code);
                            $redirect_url = $this->getCheckoutUrl();
                            break;
                        case "auto_apply_and_redirect_cart":
                            $this->wc_functions->addDiscount($coupon_code);
                            $redirect_url = $this->getCartUrl();
                            break;
                        case "send_mail_auto_apply_and_redirect":
                            $this->sendEmail($email, $coupon_settings);
                            $this->wc_functions->addDiscount($coupon_code);
                            $redirect_url = $this->getCheckoutUrl();
                            break;
                        case "send_mail_auto_apply_and_redirect_cart":
                            $this->sendEmail($email, $coupon_settings);
                            $this->wc_functions->addDiscount($coupon_code);
                            $redirect_url = $this->getCartUrl();
                            break;
                        case "instantly":
                            $show_coupon_popup = true;
                            $coupon_details = $this->getCouponPopupContent($coupon_settings);
                            break;
                        case "both":
                            $show_coupon_popup = true;
                            $this->sendEmail($email, $coupon_settings);
                            $coupon_details = $this->getCouponPopupContent($coupon_settings);
                            break;
                        default:
                        case "send_via_email":
                            $this->sendEmail($email, $coupon_settings);
                            break;
                    }
                }
            }
            wp_send_json(array('error' => false, 'message' => $message, 'redirect' => $redirect_url, 'coupon_instant_popup_content' => $coupon_details, 'show_coupon_instant_popup' => $show_coupon_popup));
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

        function getAtcPopupUrl()
        {
            $suffix = '.min';
            if (defined('SCRIPT_DEBUG')) {
                $suffix = SCRIPT_DEBUG ? '' : '.min';
            }
            return apply_filters('retainful_atc_popup_url', RNOCPREMIUM_PLUGIN_URL . 'assets/js/atc-popup'.$suffix.'.js');
        }

        /**
         * Add the site scripts needed for addon
         */
        function addSiteScripts()
        {
            if (!wp_script_is('rnoc-add-to-cart')) {
                wp_enqueue_script('rnoc-add-to-cart', $this->getAtcPopupUrl(), array('wc-add-to-cart', 'wc-add-to-cart-variation'), RNOC_VERSION);
            }
            $modal_show_popup_until = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_show_popup_until', 1);
            $close_btn_behavior = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'close_btn_behavior', 'just_close');
            $modal_params = array(
                'hide_modal_after_show' => $modal_show_popup_until,
                'close_btn_behavior' => $close_btn_behavior,
                'jquery_url' => includes_url('js/jquery/jquery.js'),
                "enable_add_to_cart_popup" => ($this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_modal', 0) == 0) ? "no" : "yes",
                "is_email_mandatory" => ($this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory', 1) == 1) ? "yes" : "no",
                "no_thanks_action" => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action', 1),
                "show_popup_until" => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'modal_show_popup_until', 1),
                "no_conflict_mode" => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'no_conflict_mode', 'yes'),
            );
            $modal_show = 'retainful_premium_add_to_cart_collection_popup_condition = ';
            $modal_show .= wp_json_encode($modal_params) ;

            wp_add_inline_script('rnoc-add-to-cart', $modal_show, 'before');
            $modal_popup_extra_classes = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class', null);
            $extra_classes = !empty($modal_popup_extra_classes) ? array('add_to_cart_button_classes' => $modal_popup_extra_classes) : array();
            $classes_list = apply_filters('retainful_premium_add_to_cart_collection_button_classes', $extra_classes);
            if (!empty($classes_list)) {
                $modal_popup_extra_classes_script = 'retainful_premium_add_to_cart_collection = ';
                $modal_popup_extra_classes_script .= wp_json_encode($classes_list);
                wp_add_inline_script('rnoc-add-to-cart', $modal_popup_extra_classes_script, 'before');
            }
        }

        /**
         * Add the site scripts needed for addon
         */
        function addSiteInstantCouponScripts()
        {
            if (!wp_script_is('rnoc-add-to-cart')) {
                wp_enqueue_script('rnoc-add-to-cart', $this->getAtcPopupUrl(), array('wc-add-to-cart', 'wc-add-to-cart-variation'), RNOC_VERSION);
                $modal_show = array(
                    'jquery_url' => includes_url('js/jquery/jquery.js')
                );
                wp_localize_script('rnoc-add-to-cart', 'retainful_premium_add_to_cart_collection_popup_condition', $modal_show);
            }
            if (!wp_style_is('rnoc-popup')) {
                wp_enqueue_style('rnoc-popup', RNOCPREMIUM_PLUGIN_URL . 'assets/css/popup.css', array(), RNOC_VERSION);
            }
        }

        /**
         * popup html
         */
        function addToCartPopup()
        {
            $final_settings = array(
                "show_for_admin" => current_user_can('administrator'),
                "template" => $this->getPopupTemplate(),
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
            return __('<div style="text-align: center;"><div class="coupon-block"><h3 style="font-size: 25px; font-weight: 500; color: #222; margin: 0 0 15px;">Your coupon code</h3><p style="font-size: 16px; font-weight: 500; color: #555; line-height: 1.6; margin: 15px 0 20px;">Thank you for shopping with us! We want to offer you an exclusive coupon for your order!</p><p style="text-align: center;"><span style="line-height: 1.6; font-size: 18px; font-weight: 500; background: #ffffff; padding: 10px 20px; border: 2px dashed #f27052; color: #f27052; text-decoration: none;">{{coupon_code}}</span></p><p style="text-align: center; margin: 0;"><a style="line-height: 1.8; font-size: 16px; font-weight: 500; background: #f27052; width: fit-content; padding: 10px; border: 1px solid #f27052; color: #ffffff; text-decoration: none;" href="{{coupon_url}}">Shop Now! </a></p></div></div>', RNOC_TEXT_DOMAIN);
        }

        /**
         * Default popup template
         * @return string
         */
        function getDefaultPopupTemplate()
        {
            return __('<div class="rnoc-ip-container" style="padding: 20px; background: #F8F0F0; border-radius: 15px;"><div class="rnoc-ip-inner" style="padding: 20px; text-align: center;"><div class="rnoc-ip-heading" style="padding: 0px 12px; margin-bottom: 0; font-size: 35px; color: #1f1e1f; font-weight: 600; line-height: 45px;"> Your coupon code</div><p class="rnoc-ip-sub-heading" style="font-size: 20px; padding: 0px 15px; line-height: 20px; margin-bottom: 15px; margin-top: 28px;">Get 10% off on any item when you buy today</p><div class="rnoc-ip-coupon" style="padding: 0px; color: #2f2e35;"><div class="rnoc-ip-coupon-inner" style="text-align: center; padding: 20px 30px;"><a class="rnoc-ip-coupon-code" style="width: 60%; padding: 12px 20%; background: #ffffff; border-radius: 4px; font-size: 16px; font-weight: 600; color: #2f2e35; text-align: center; line-height: 1.33333; margin-top: 0px; margin-bottom: 5px; border: 1px dashed #2f2e35; text-decoration: none;display:inline-block;" href="{{coupon_url}}">{{coupon_code}}</a> <a class="rnoc-ip-coupon-apply-btn" style="padding: 12px 20px; background: #f27052; border: none; border-radius: 4px; font-size: 18px; font-weight: 600; color: white; text-align: center; line-height: 1.33333; text-decoration: none;display:inline-block;" href="{{coupon_url}}">Apply coupon</a></div> <a class="rnoc-ip-coupon-description" style="text-decoration: none; font-size: 16px; line-height: 24px; color: #6c6b70 !important; font-weight: 500!important; margin-bottom: -15px;" href="#">*Not valid with other coupon codes</a></div></div></div>', RNOC_TEXT_DOMAIN);
        }

        /**
         * @param $settings
         * @param $base_url
         * @param $add_on_slug
         */
        function premiumAddonTabContent($settings, $base_url, $add_on_slug)
        {
            if ($this->slug() == $add_on_slug) {
                $pages = $this->getPageLists();
                $coupon_codes = $this->getWooCouponCodes();
                ?>
                <input type="hidden" name="addon" value="atcp">
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'need_modal'; ?>"><?php
                                esc_html_e('Enable Add to cart popup modal?', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'need_modal'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'need_modal_1'; ?>"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'need_modal'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'need_modal'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'need_modal_0'; ?>"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'need_modal'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory'; ?>"><?php
                                esc_html_e('Email address is mandatory?', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_email_is_mandatory'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action'; ?>"><?php
                                esc_html_e('No thanks action', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Allow adding item to cart (Show "No thanks" link)', RNOC_TEXT_DOMAIN); ?>
                            </label><br>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_no_thanks_action'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Do not allow adding the item to cart and Do not show "No thanks" link', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'close_btn_behavior'; ?>"><?php
                                esc_html_e('Close button behavior', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'close_btn_behavior'; ?>"
                                       type="radio"
                                       value="add_and_close" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'close_btn_behavior'] == 'add_and_close') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Add item to cart and close', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'close_btn_behavior'; ?>"
                                       type="radio"
                                       value="just_close" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'close_btn_behavior'] == 'just_close') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Just close the popup', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_show_popup_until'; ?>"><?php
                                esc_html_e('Show E-mail collection popup', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'modal_show_popup_until'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_show_popup_until'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Until user provides an E-Mail address', RNOC_TEXT_DOMAIN); ?>
                            </label><br>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'modal_show_popup_until'; ?>"
                                       type="radio"
                                       value="2" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_show_popup_until'] == '2') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Until user clicks "No thanks" link (It will stop showing once user clicked no thanks)', RNOC_TEXT_DOMAIN); ?>
                            </label><br>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'modal_show_popup_until'; ?>"
                                       type="radio"
                                       value="3" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_show_popup_until'] == '3') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Until user clicks close button of the popup (It will stop when user clicks the close button once)', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_display_pages'; ?>"><?php
                                esc_html_e('Custom pages to display the pop-up modal on (Optional)', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <select multiple="multiple"
                                    name="<?php echo RNOC_PLUGIN_PREFIX . 'modal_display_pages[]'; ?>"
                                    class="rnoc-multi-select"
                                    id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_display_pages'; ?>">
                                <?php
                                if (!empty($pages)) {
                                    foreach ($pages as $key => $label) {
                                        ?>
                                        <option value="<?php echo $key ?>" <?php if (in_array($key, $settings[RNOC_PLUGIN_PREFIX . 'modal_display_pages'])) {
                                            echo "selected";
                                        } ?>><?php echo $label ?></option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php
                                echo __('The add to cart popup would be displayed only on the selected pages.If you wish to display the popup in all pages, leave this option empty.', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class'; ?>"><?php
                                esc_html_e('Custom classes', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                        <textarea name="<?php echo RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class'; ?>"
                                  rows="5" cols="50"
                                  id="<?php echo RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class'; ?>"><?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_extra_class']); ?>
                        </textarea>
                            <p class="description">
                                <?php
                                echo __('Very helpful for custom designed Add to cart button.<b>Example:</b> .add-to-cart,.custom-add-to-cart-button', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php  echo RNOC_PLUGIN_PREFIX . 'no_conflict_mode'; ?>"><?php
                                esc_html_e('Enable no conflict mode ?', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'no_conflict_mode'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'no_conflict_mode_yes'; ?>"
                                       value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'no_conflict_mode'] == 'yes') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'no_conflict_mode'; ?>"
                                       type="radio"
                                       id="<?php echo RNOC_PLUGIN_PREFIX . 'no_conflict_mode_no'; ?>"
                                       value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'no_conflict_mode'] == 'no') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description">
                                <?php
                                echo __('DO NOT change this option unless recommended by the support team.  By default, the popup javascript is compatible and runs in no-conflict mode with other scripts that bind to the Add to cart button. But if you find any conflicts with other scripts, you can set this to NO and try.', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('Popup Design', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $modal_design_name = RNOC_PLUGIN_PREFIX . 'modal_design_settings[0]'
                    ?>
                    <tbody>
                    <tr>
                        <td colspan="2">
                            <div style="width: 60%;margin: 0 auto;">
                                <?php
                                $this->setupAdminScripts();
                                echo $this->getPopupTemplate();
                                ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_heading'; ?>"><?php
                                esc_html_e('Modal heading', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_heading]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_heading'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_heading']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_heading_color'; ?>"><?php
                                esc_html_e('Modal heading color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_heading_color]'; ?>"
                                   type="text" class="rnoc-color-field"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_heading_color'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_heading_color']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_email_placeholder'; ?>"><?php
                                esc_html_e('Email placeholder', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_email_placeholder]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_email_placeholder'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_email_placeholder']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_email_field_width'; ?>"><?php
                                esc_html_e('Email field width(%)', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_email_field_width]'; ?>"
                                   type="number" class="regular-text"
                                   step="any"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_email_field_width'; ?>"
                                   value="<?php echo isset($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_email_field_width']) ? floatval($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_email_field_width']) : 70; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_add_cart_text'; ?>"><?php
                                esc_html_e('Add to cart button text', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_text]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_add_cart_text'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_add_cart_text']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_button_field_width'; ?>"><?php
                                esc_html_e('Add to cart button width(%)', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_button_field_width]'; ?>"
                                   type="number" class="regular-text"
                                   step="any"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_button_field_width'; ?>"
                                   value="<?php echo isset($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_button_field_width']) ? floatval($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_button_field_width']) : 70; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_add_cart_color'; ?>"><?php
                                esc_html_e('Add to cart button color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_add_cart_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_add_cart_bg_color'; ?>"><?php
                                esc_html_e('Add to cart button background color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_bg_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_add_cart_bg_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_add_cart_border_top_color'; ?>"><?php
                                esc_html_e('Popup top border color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_border_top_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_add_cart_border_top_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_bg_color'; ?>"><?php
                                esc_html_e('Add to cart popup background color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_bg_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_bg_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_not_mandatory_text'; ?>"><?php
                                esc_html_e('Not mandatory text', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_not_mandatory_text]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_not_mandatory_text'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_not_mandatory_text']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_add_cart_no_thanks_color'; ?>"><?php
                                esc_html_e('No thanks link color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_add_cart_no_thanks_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_add_cart_no_thanks_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_terms_text'; ?>"><?php
                                esc_html_e('Terms', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $modal_design_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_terms_text]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_terms_text'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_design_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_terms_text']); ?>">
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('GDPR Compliance for collecting E-Mail', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $gdpr_compliance_name = RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance[0]'
                    ?>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings'; ?>"><?php
                                esc_html_e('Show GDPR Compliance checkbox', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <select name="<?php echo $gdpr_compliance_name . '[' . RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings]'; ?>">
                                    <?php
                                    foreach ($this->complianceMessageOptions() as $key => $label) {
                                        ?>
                                        <option value="<?php echo $key ?>" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings'] == $key) {
                                            echo 'selected';
                                        } ?> ><?php echo $label ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message'; ?>"><?php
                                esc_html_e('GDPR Compliance message', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                            <textarea
                                    name="<?php echo $gdpr_compliance_name . '[' . RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message]'; ?>"
                                    rows="10"
                                    cols="50"><?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'add_to_cart_popup_gdpr_compliance'][0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message']); ?>
                            </textarea>
                            </label>
                            <p class="description">
                                <?php
                                echo __('You can also use HTML content as well in the message.', RNOC_TEXT_DOMAIN)
                                ?>
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('Coupon settings - Reward customers with a coupon for providing their email address', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $modal_coupon_settings_name = RNOC_PLUGIN_PREFIX . 'modal_coupon_settings[0]';
                    $coupon_codes = $this->getWooCouponCodes();
                    ?>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'need_coupon'; ?>"><?php
                                esc_html_e('Enable coupon reward for providing email address', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'need_coupon]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'need_coupon'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'need_coupon]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'need_coupon'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description">
                                <?php
                                echo __('You can reward your visitors with a coupon code when they provide their email address via the Add-to-cart popup.', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'woo_coupon'; ?>"><?php
                                esc_html_e('Choose the coupon code for the reward', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'woo_coupon]'; ?>"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'woo_coupon'; ?>"
                                   class="search-and-select-coupon"
                                   autocomplete="off"
                                   placeholder="<?php esc_html_e('Search for a coupon code', RNOC_TEXT_DOMAIN); ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'woo_coupon']); ?>">
                            <p class="description">
                                <b>Note</b>:This is a list of coupon codes from WooCommerce -> Coupons. If none found,
                                please create the coupon code in WooCommerce -> Coupons
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_sub_heading'; ?>"><?php
                                esc_html_e('Reward message to show on the popup', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_sub_heading]'; ?>"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_sub_heading'; ?>"
                                   class="regular-text"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_sub_heading']); ?>">
                            <p class="description">
                                <b>Note</b>:You need to enable coupon
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color'; ?>"><?php
                                esc_html_e('Message text color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color]'; ?>"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color'; ?>"
                                   class="rnoc-color-field"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'modal_sub_heading_color']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'show_woo_coupon'; ?>"><?php
                                esc_html_e('Choose how to reveal the reward coupon', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <select name="<?php echo $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'show_woo_coupon]'; ?>"
                                    id="<?php echo RNOC_PLUGIN_PREFIX . 'show_woo_coupon'; ?>">
                                <?php
                                $options = array(
                                    "instantly" => __("Instantly using a popup", RNOC_TEXT_DOMAIN),
                                    "send_via_email" => __("Send an email", RNOC_TEXT_DOMAIN),
                                    "both" => __("Show instantly using a popup and also send an email", RNOC_TEXT_DOMAIN),
                                    "auto_apply_and_redirect" => __("Auto apply coupon and redirect to checkout", RNOC_TEXT_DOMAIN),
                                    "auto_apply_and_redirect_cart" => __("Auto apply coupon and redirect to cart", RNOC_TEXT_DOMAIN),
                                    "send_mail_auto_apply_and_redirect" => __("Send email, auto apply and redirect to checkout", RNOC_TEXT_DOMAIN),
                                    "send_mail_auto_apply_and_redirect_cart" => __("Send email, auto apply and redirect to cart", RNOC_TEXT_DOMAIN),
                                );
                                foreach ($options as $key => $label) {
                                    ?>
                                    <option value="<?php echo $key ?>" <?php if ($key == $settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'show_woo_coupon']) {
                                        echo "selected";
                                    } ?>><?php echo $label; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                            <p class="description">
                                How to show the reward coupon to customers
                            </p>
                        </td>
                    </tr>
                    <tr id="row_atcp_template">
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template'; ?>"><?php
                                esc_html_e('Response Popup template', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <?php
                            $email_template = $settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template'];
                            if (empty($email_template)) {
                                $email_template = $this->getDefaultPopupTemplate();
                            }
                            wp_editor($email_template, 'add_to_cart_coupon_popup_template', array('textarea_name' => $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'add_to_cart_coupon_popup_template]'));
                            ?>
                            <p class="description">
                                Please use the below short codes to show the Coupon details in the message.<br><b>{{coupon_code}}</b>
                                - Coupon code<br><b>{{coupon_url}}</b> - Url to apply coupon automatically
                            </p>
                        </td>
                    </tr>
                    <tr class="row_atcp_mail_template">
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject'; ?>"><?php
                                esc_html_e('Email subject', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject]'; ?>"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject'; ?>"
                                   class="regular-text"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_mail_template_subject']); ?>">
                        </td>
                    </tr>
                    <tr class="row_atcp_mail_template">
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'coupon_mail_template'; ?>"><?php
                                esc_html_e('Email template (Used for the email that is sent when customer enters his email in the Add to Cart Popup)', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <?php
                            $email_template = $settings[RNOC_PLUGIN_PREFIX . 'modal_coupon_settings'][0][RNOC_PLUGIN_PREFIX . 'coupon_mail_template'];
                            if (empty($email_template)) {
                                $email_template = $this->getDefaultEmailTemplate();
                            }
                            wp_editor($email_template, 'coupon_mail_template', array('textarea_name' => $modal_coupon_settings_name . '[' . RNOC_PLUGIN_PREFIX . 'coupon_mail_template]'));
                            ?>
                            <p class="description">
                                Please use the below short codes to show the Coupon details in the message.<br><b>{{coupon_code}}</b>
                                - Coupon code<br><b>{{coupon_url}}</b> - Url to apply coupon automatically
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <?php
            }
        }
    }
}