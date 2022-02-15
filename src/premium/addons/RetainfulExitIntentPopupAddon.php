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
                add_action('rnoc_premium_addon_settings_page_' . $this->slug(), array($this, 'premiumAddonTabContent'), 10, 3);
                add_action('wp_ajax_rnocp_get_exit_intent_popup_template', array($this, 'getPopupTemplateToInsert'));
            }
            add_action('wp_ajax_nopriv_set_rnoc_exit_intent_popup_guest_session', array($this, 'setGuestEmailSession'));
            //To support the logged in user
            add_action('wp_ajax_set_rnoc_exit_intent_popup_guest_session', array($this, 'setGuestEmailSession'));
            //add_action('rnoc_exit_intent_after_applying_coupon_code', array($this, 'exitIntentCouponApplied'));
            add_action('woocommerce_applied_coupon', array($this, 'couponApplied'));
            add_action('wp', array($this, 'siteInit'));
        }

        /**
         * Set coupon code applied
         */
        function exitIntentCouponApplied()
        {
            $this->wc_functions->setSession('rnoc_exit_intent_coupon_code_applied', 1);
        }

        function couponApplied($coupon_code)
        {
            $eip_coupon = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon', '');
            if (strtolower($eip_coupon) == strtolower($coupon_code)) {
                $this->wc_functions->setSession('rnoc_exit_intent_coupon_code_applied', 1);
            }
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
         * popup template
         * @return string
         */
        function getPopupTemplate()
        {
            $default_template = $this->getDefaultPopupTemplate();
            return $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template', $default_template);
        }

        /**
         * Check the user is capable for doing task
         * @return bool
         */
        function isValidUserToShow()
        {
            if (current_user_can('administrator')) {
                return true;
            } elseif (!is_user_logged_in()) {
                return true;
            }
            return false;
        }

        /**
         * init the addon
         */
        function siteInit()
        {
            if (defined('RNOC_VERSION')) {
                if (version_compare(RNOC_VERSION, '1.1.5', '>')) {
                    $this->admin = new Rnoc\Retainful\Admin\Settings();
                    $this->wc_functions = new \Rnoc\Retainful\WcFunctions();
                    $this->applyCouponAutomatically();
                    $need_popup = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal', 0);
                    if ($need_popup == 0) {
                        return false;
                    }
                    $need_exit_intent_modal_after_coupon_applied = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied', 1);
                    if ($need_exit_intent_modal_after_coupon_applied == 1) {
                        $is_coupon_applied = $this->wc_functions->getSession('rnoc_exit_intent_coupon_code_applied');
                        if ($is_coupon_applied) {
                            return false;
                        }
                    }
                    $modal_display_pages = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages', array());
                    if (!$this->isValidPagesToDisplay($modal_display_pages)) {
                        return false;
                    }
                    $need_popup_for = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to', "all");
                    if ($need_popup_for == "non_email_users") {
                        $customer_email = $this->wc_functions->getCustomerEmail();
                        if (!empty($customer_email)) {
                            return false;
                        }
                    } elseif ($need_popup_for == "guest" && is_user_logged_in()) {
                        return false;
                    }
                    $show_popup = apply_filters('rnoc_need_to_show_exit_intent_popup', true);
                    if ($show_popup) {
                        add_action('wp_footer', array($this, 'exitIntentPopup'), 10);
                        add_action('wp_enqueue_scripts', array($this, 'addSiteScripts'));
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
                //remove
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
         * Add the site scripts needed for addon
         */
        function addSiteScripts()
        {
            $load_exit_intent_popup_scripts_after = apply_filters('rnoc_load_exit_intent_popup_scripts_after', array('jquery'));
            wp_enqueue_script('rnoc-exit-intent-popup', RNOCPREMIUM_PLUGIN_URL . 'assets/js/exit-intent-popup.js', $load_exit_intent_popup_scripts_after, RNOC_VERSION);
            if (!wp_style_is('rnoc-popup')) {
                wp_enqueue_style('rnoc-popup', RNOCPREMIUM_PLUGIN_URL . 'assets/css/popup.css', array(), RNOC_VERSION);
            }
            $show_settings = $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings', 1);
            $show_option = isset($show_settings['show_option']) ? $show_settings['show_option'] : 'once_per_page';
            $show_count = isset($show_settings['show_count']) ? $show_settings['show_count'] : 1;
            $mobile_popup_settings = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0]) && !empty($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0] : array();
            $delay = $scroll_distance = 0;
            if ($this->getKeyFromArray($mobile_popup_settings, RNOC_PLUGIN_PREFIX . 'enable_mobile_support', 0) == 1) {
                if ($this->getKeyFromArray($mobile_popup_settings, RNOC_PLUGIN_PREFIX . 'enable_delay_trigger', 0) == 1) {
                    $delay = (int)$this->getKeyFromArray($mobile_popup_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec', 0);
                }
                if ($this->getKeyFromArray($mobile_popup_settings, RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger', 0) == 1) {
                    $scroll_distance = (int)$this->getKeyFromArray($mobile_popup_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance', 0);
                }
            }
            $settings = array(
                'show_option' => $show_option,
                'cookie_life' => (int)$this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life', 100),
                'maxDisplay' => (int)$show_count,
                'distance' => $scroll_distance,
                'delay' => $delay,
                'cookieLife' => (int)$this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life', 1),
                'storeName' => RNOC_PLUGIN_PREFIX . 'exit_intent_popup',
                'consider_cart_created_as_hash' => 'no',
                'show_only_for' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to', "all"),
                'jquery_url' => includes_url('js/jquery/jquery.js'),
                'show_when_its_coupon_applied' => (int)$this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied', 1),
                'coupon_code' => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon', '')
            );
            $settings = apply_filters('rnoc_load_exit_intent_popup_settings', $settings);
            $exit_popup_settings_script = 'retainful_premium_exit_intent_popup = ';
            $exit_popup_settings_script .= wp_json_encode($settings) ;

            wp_add_inline_script('rnoc-exit-intent-popup', $exit_popup_settings_script, 'before');
        }

        /**
         * popup html
         */
        function exitIntentPopup()
        {
            if (!is_admin()) {
                $content = $this->getPopupTemplate();
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
                $final_settings = array(
                    "show_for_admin" => current_user_can('administrator'),
                    "template" => $content,
                    "add_on_slug" => 'rnoc-exit-intent-popup-add-on',
                    "custom_style" => $this->getKeyFromArray($this->premium_addon_settings, RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style', NULL),
                    "no_thanks_action" => 1,
                    "is_email_mandatory" => 1
                );
                echo $this->getTemplateContent(RNOCPREMIUM_PLUGIN_PATH . 'templates/popup_display.php', $final_settings, $this->slug);
            }
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
         * get the current url
         * @return string|null
         */
        function getCurrentUrl()
        {
            global $wp;
            return home_url($wp->request);
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
                <input type="hidden" name="addon" value="eip">
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal'; ?>"><?php
                                esc_html_e('Enable exit intent popup?', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description">
                                <?php
                                esc_html_e('Exit intent popup will show when, cart contains some items and user tries to leave the site.', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages'; ?>"><?php
                                esc_html_e('Custom pages to display the pop-up modal on (Optional)', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <select multiple="multiple"
                                    name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages[]'; ?>"
                                    class="rnoc-multi-select"
                                    id="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages'; ?>">
                                <?php
                                if (!empty($pages)) {
                                    foreach ($pages as $key => $label) {
                                        ?>
                                        <option value="<?php echo $key ?>" <?php if (in_array($key, $settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_pages'])) {
                                            echo "selected";
                                        } ?>><?php echo $label ?></option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php
                                echo __('The exit intent popup would be displayed only on the selected pages.If you wish to display the popup in all pages, leave this option empty.', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to'; ?>"><?php
                                esc_html_e('Show exit intent popup', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to'; ?>"
                                       type="radio"
                                       value="guest" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to'] == 'guest') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Only for guest', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to'; ?>"
                                       type="radio"
                                       value="all" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to'] == 'all') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Everyone', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to'; ?>"
                                       type="radio"
                                       value="non_email_users" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_display_to'] == 'non_email_users') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('When a customer has not yet provided an email address', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon'; ?>"><?php
                                esc_html_e('Choose the coupon code', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon'; ?>"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon'; ?>"
                                   class="search-and-select-coupon"
                                   autocomplete="off"
                                   placeholder="<?php esc_html_e('Search for a coupon code', RNOC_TEXT_DOMAIN); ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_modal_coupon']); ?>">
                            <p class="description">
                                <b>Note</b>:This is a list of coupon codes from WooCommerce -> Coupons. If none found,
                                please create the coupon code in WooCommerce -> Coupons
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied'; ?>"><?php
                                esc_html_e('Don\'t show exit intent popup once its coupon applied?', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes, hide', RNOC_TEXT_DOMAIN); ?>
                            </label><br>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'need_exit_intent_modal_after_coupon_applied'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No, keep showing', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="exit_intent_popup_show_option"><?php
                                esc_html_e('Show exit popup', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <?php
                            $show_option = $settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings']['show_option'];
                            $show_count = $settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings']['show_count'];
                            ?>
                            <select name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings[show_option]' ?>"
                                    id="exit_intent_popup_show_option">
                                <option <?php if ($show_option == 'once_per_page') {
                                    echo 'selected';
                                } ?>
                                        value="once_per_page"><?php esc_html_e('Only once per page', RNOC_TEXT_DOMAIN); ?></option>
                                <option <?php if ($show_option == 'every_time_on_customer_exists') {
                                    echo 'selected';
                                } ?> value="every_time_on_customer_exists"><?php esc_html_e('Every time customer tries to exit', RNOC_TEXT_DOMAIN); ?></option>
                                <option <?php if ($show_option == 'show_x_times_per_page') {
                                    echo 'selected';
                                } ?> value="show_x_times_per_page"><?php esc_html_e('X number of times per page on exit', RNOC_TEXT_DOMAIN); ?></option>
                                <option <?php if ($show_option == 'once_per_session') {
                                    echo 'selected';
                                } ?> value="once_per_session"><?php esc_html_e('Only once per session', RNOC_TEXT_DOMAIN); ?></option>
                            </select>
                            <label id="show_x_times_per_page_val">
                                <?php echo __('Number of times', RNOC_TEXT_DOMAIN) ?>
                                <select name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_show_settings[show_count]' ?>">
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
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life'; ?>"><?php
                                esc_html_e('Cookie expiry days', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life'; ?>"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life'; ?>"
                                   type="text" class="regular-text"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_modal_cookie_life']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success'; ?>"><?php
                                esc_html_e('Where to redirect after entering email?', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success'; ?>"
                                       type="radio"
                                       value="cart" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success'] == 'cart') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Cart page', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success'; ?>"
                                       type="radio"
                                       value="checkout" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success'] == 'checkout') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Checkout page', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success'; ?>"
                                       type="radio"
                                       value="same" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_modal_redirect_on_success'] == 'same') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Same page', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description">
                                <?php
                                echo __('This controls whether or not the bounce dialog should be shown on every page view or only on the user\'s first.', RNOC_TEXT_DOMAIN);
                                ?>
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('Mobile popup settings', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $mobile_popup_settings = RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings[0]'
                    ?>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_mobile_support'; ?>"><?php
                                esc_html_e('Enable mobile device support', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $mobile_popup_settings . '[' . RNOC_PLUGIN_PREFIX . 'enable_mobile_support]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_mobile_support'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $mobile_popup_settings . '[' . RNOC_PLUGIN_PREFIX . 'enable_mobile_support]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_mobile_support'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description">
                                The following settings are used to trigger Exit Popup in mobile devices. Since there are
                                a number of ways a visitor can exit in a mobile (Example: Swipe up), you can consider
                                showing the popup either based on time delay (time spent by the customer in the site) or
                                scrolling (the distance the customer scrolled a page)
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_delay_trigger'; ?>"><?php
                                esc_html_e('Enable time delay based trigger', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $mobile_popup_settings . '[' . RNOC_PLUGIN_PREFIX . 'enable_delay_trigger]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_delay_trigger'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $mobile_popup_settings . '[' . RNOC_PLUGIN_PREFIX . 'enable_delay_trigger]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_delay_trigger'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec'; ?>"><?php
                                esc_html_e('Delay seconds', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $mobile_popup_settings . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec]'; ?>"
                                       type="text" class="regular-text"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_delay_sec']); ?>">
                            </label>
                            <p class="description">
                                Trigger the popup after these many seconds a visitor spent time
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope=" row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger'; ?>"><?php
                                esc_html_e('Enable Scroll based trigger', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $mobile_popup_settings . '[' . RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger]'; ?>"
                                       type="radio"
                                       value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger'] == '1') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                            </label>
                            <label>
                                <input name="<?php echo $mobile_popup_settings . '[' . RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger]'; ?>"
                                       type="radio"
                                       value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0][RNOC_PLUGIN_PREFIX . 'enable_scroll_distance_trigger'] == '0') {
                                    echo "checked";
                                } ?>>
                                <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance'; ?>"><?php
                                esc_html_e('Scroll distance', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $mobile_popup_settings . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance]'; ?>"
                                       type="text" class="regular-text"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_mobile_settings'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_modal_distance']); ?>">
                            </label>
                            <p class="description">
                                Trigger the popup after a visitor scrolled the page to the set distance. Its a
                                percentage value. The distance is the % from the top of the page.
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('Popup design', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <td colspan="2">
                            <?php
                            echo $this->exitIntentPopInsertTemplate();
                            ?>
                            <div class="rnoc-grid">
                                <div class="grid-column">
                                    <textarea id="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template'; ?>"
                                              name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template'; ?>"
                                              cols="50" rows="20"
                                    ><?php
                                        $template = $settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_template'];
                                        if (empty($template)) {
                                            $template = $this->getDefaultPopupTemplate();
                                        }
                                        echo $template;
                                        ?>
                                    </textarea>
                                    <button type="button" class="insert-template"
                                            id="rnoc_exit_intent_popup_template_show_preview"><?php echo __("Preview", RNOC_TEXT_DOMAIN); ?>
                                    </button>
                                </div>
                                <div class="grid-column" id="exit-intent-popup-preview"></div>
                            </div>
                            <div>
                                <?php
                                echo __('Please use the below short codes to show the Coupon details in the message.<br><b>{{coupon_code}}</b> - Coupon code<br><b>{{cart_url}}</b> - Url to redirect to cart page<br><b>{{cart_url_without_coupon}}</b> - Url to redirect to cart page without auto applying coupon<br><b>{{checkout_url}}</b> - Url to redirect user to checkout page<br><b>{{checkout_url_without_coupon}}</b> - Url to redirect user to checkout page without auto apply coupon code<br><b>{{email_collection_form}}</b> - To display email collection form. Note: Email collection form will only show to Guest and Administrator.', RNOC_TEXT_DOMAIN)
                                ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style'; ?>"><?php
                                esc_html_e('Custom CSS styles', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <textarea id="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style'; ?>"
                                      name="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style'; ?>"
                                      cols="50" rows="10"
                            ><?php
                                echo $settings[RNOC_PLUGIN_PREFIX . 'exit_intent_modal_custom_style'];
                                ?>
                            </textarea>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <div class="rnoc-tag">
                    <?php
                    echo __('Email collection form design', RNOC_TEXT_DOMAIN)
                    ?>
                </div>
                <table class="form-table" role="presentation">
                    <?php
                    $email_Collection_form_design_name = RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design[0]'
                    ?>
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder'; ?>"><?php
                                esc_html_e('Email input placeholder', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $email_Collection_form_design_name . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_placeholder']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_height'; ?>"><?php
                                esc_html_e('Email input height', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $email_Collection_form_design_name . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_height]'; ?>"
                                       type="text" class="regular-text"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_height']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_width'; ?>"><?php
                                esc_html_e('Email input width', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $email_Collection_form_design_name . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_width]'; ?>"
                                       type="text" class="regular-text"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_email_width']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text'; ?>"><?php
                                esc_html_e('Button text', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <input name="<?php echo $email_Collection_form_design_name . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text]'; ?>"
                                   type="text" class="regular-text"
                                   id="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text'; ?>"
                                   value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_text']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_color'; ?>"><?php
                                esc_html_e('Button color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $email_Collection_form_design_name . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_bg_color'; ?>"><?php
                                esc_html_e('Button background color', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $email_Collection_form_design_name . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_bg_color]'; ?>"
                                       type="text" class="rnoc-color-field"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_bg_color']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_height'; ?>"><?php
                                esc_html_e('Button height', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $email_Collection_form_design_name . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_height]'; ?>"
                                       type="text" class="regular-text"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_height']); ?>">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_width'; ?>"><?php
                                esc_html_e('Button width', RNOC_TEXT_DOMAIN);
                                ?></label>
                        </th>
                        <td>
                            <label>
                                <input name="<?php echo $email_Collection_form_design_name . '[' . RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_width]'; ?>"
                                       type="text" class="regular-text"
                                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_design'][0][RNOC_PLUGIN_PREFIX . 'exit_intent_popup_form_button_width']); ?>">
                            </label>
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
                    $gdpr_compliance_name = RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance[0]'
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
                                        <option value="<?php echo $key ?>" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance'][0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_settings'] == $key) {
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
                                    cols="50"><?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'exit_intent_popup_gdpr_compliance'][0][RNOC_PLUGIN_PREFIX . 'gdpr_compliance_checkbox_message']); ?>
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
                <?php
            }
        }
    }
}
