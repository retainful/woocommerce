<?php
/**
 * Created by PhpStorm.
 * User: cartrabbit
 * Date: 2019-05-15
 * Time: 13:54
 */
if (!class_exists('RetainfulFbCheckboxAddon')) {
    include __DIR__ . '/RetainfulPremiumAddonBase.php';

    class RetainfulFbCheckboxAddon extends RetainfulPremiumAddonBase
    {
        function __construct()
        {
            parent::__construct();
            $this->title = __('Facebook checkbox plugin', RNOC_TEXT_DOMAIN);
            $this->description = __('Collect customer email at the time of adding to cart. This can help recover the cart even if the customer abandon it before checkout', RNOC_TEXT_DOMAIN);
            $this->version = '1.0.0';
            $this->slug = 'fb-checkbox-editor';
            $this->icon = 'dashicons-facebook';
        }

        function init()
        {
            if (is_admin()) {
                add_filter('rnoc_premium_addon_tab', array($this, 'premiumAddonTab'));
                add_filter('rnoc_premium_addon_tab_content', array($this, 'premiumAddonTabContent'));
            }
            if (!is_admin()) {
                $need_fb_checkbox_plugin = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'need_fb_checkbox_plugin'])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'need_fb_checkbox_plugin'] : 0;
                $page_id = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_page_id'])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_page_id'] : null;
                $app_id = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_app_id'])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_app_id'] : null;
                if ($need_fb_checkbox_plugin == 1 && !empty($page_id) && !empty($app_id)) {
                    add_action('woocommerce_before_add_to_cart_form', array($this, 'beforeAddToCartForm'), 100);
                    add_action('wp_enqueue_scripts', array($this, 'enqueueFbScript'));
                    add_filter('script_loader_tag', array($this, 'addAsyncAttribute'), 10, 2);
                    add_action('wp_footer', array($this, 'enqueueScript'));
                }
            }
        }

        function addAsyncAttribute($tag, $handle)
        {
            if ('rnoc-fb-checkbox-plugin' !== $handle) {
                return $tag;
            }
            return str_replace(' src', ' async defer src', $tag);
        }

        function enqueueScript()
        {
            ?>
            <script>
                var rnoc_fbcb_state = false;
                var rnoc_fbcb_user_status;
                var checkbox_holder = document.getElementById("rnoc-fb-messanger-checkbox-holder");
                if (checkbox_holder) {
                    var rnoc_fbcb_messenger_app_id = checkbox_holder.getAttribute("rnoc_fbcb_messenger_app_id");
                    var rnoc_fbcb_user_ref = checkbox_holder.getAttribute("rnoc_fbcb_user_ref");
                    var rnoc_fbcb_page_id = checkbox_holder.getAttribute("page_id");
                    window.fbAsyncInit = function () {
                        FB.init({
                            appId: rnoc_fbcb_messenger_app_id,
                            xfbml: true,
                            version: 'v2.6'
                        });

                        FB.Event.subscribe('messenger_checkbox', function (e) {
                            if (e.event === 'rendered') {
                                console.log("Plugin was rendered");
                            } else if (e.event === 'checkbox') {
                                rnoc_fbcb_state = e.state;
                                console.log("Checkbox state: " + rnoc_fbcb_state);
                            } else if (e.event === 'not_you') {
                                console.log("User clicked 'not you'");
                            } else if (e.event === 'hidden') {
                                console.log("Plugin was hidden");
                            }
                        });
                    };
                }
                jQuery(document).ready(function ($) {
                    $(document).on("click", "button.single_add_to_cart_button", function () {
                        console.log("opt-in button clicked with state " + rnoc_fbcb_state);
                        FB.AppEvents.logEvent('MessengerCheckboxUserConfirmation', null, {
                            'app_id': rnoc_fbcb_messenger_app_id,
                            'page_id': rnoc_fbcb_page_id,
                            'ref': 'rnoc_callback_ref',
                            'user_ref': rnoc_fbcb_user_ref
                        });
                        if (rnoc_fbcb_state === "checked") {
                            console.log("setting user reference via ajax " + rnoc_fbcb_user_ref);
                            $.ajax({
                                url: '<?php echo admin_url('admin-ajax.php') ?>',
                                method: 'POST',
                                dataType: 'json',
                                data: {action: 'rnoc_fbcb_set_user_ref', user_ref: rnoc_fbcb_user_ref},
                                async: true,
                                success: function (response) {
                                },
                                error: function (response) {
                                }
                            });
                        }
                    });
                });
            </script>
            <?php
        }

        function enqueueFbScript()
        {
            $fb_sdk_url = apply_filters('rnoc_enqueue_fb_sdk_script_url', 'https://connect.facebook.net/en_US/sdk.js');
            wp_enqueue_script('rnoc-fb-checkbox-plugin', $fb_sdk_url, array(), RNOC_VERSION, true);
        }

        function beforeAddToCartForm()
        {
            $page_id = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_page_id'])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_page_id'] : null;
            $app_id = (isset($this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_app_id'])) ? $this->premium_addon_settings[RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_app_id'] : null;
            $user_ref = time() . rand(11111111, 999999999);
            //$rnoc_fbcb_user_ref = md5($random);
            ?>
            <div class="fb-messenger-checkbox" id="rnoc-fb-messanger-checkbox-holder"
                 origin="<?php echo home_url('/'); ?>"
                 page_id="<?php echo $page_id; ?>"
                 messenger_app_id="<?php echo $app_id; ?>"
                 user_ref="<?php echo $user_ref; ?>"
                 allow_login="true"
                 size="small"
                 skin="light"
                 center_align="true">
            </div>
            <input type="button" onclick="rnocConfirmOptIn()" value="Confirm Opt-in"/>
            <?php
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
                'title' => __('Facebook checkbox plugin', RNOC_TEXT_DOMAIN),
                'fields' => array(
                    RNOC_PLUGIN_PREFIX . 'need_fb_checkbox_plugin',
                    RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_page_id',
                    RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_app_id',
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
                'name' => __('Enable facebook checkbox plugin', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'need_fb_checkbox_plugin',
                'type' => 'radio_inline',
                'options' => array(
                    '0' => __('No', RNOC_TEXT_DOMAIN),
                    '1' => __('Yes', RNOC_TEXT_DOMAIN)
                ),
                'default' => '0'
            ));
            $general_settings->add_field(array(
                'name' => __('Page id', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_page_id',
                'type' => 'text',
                'default' => '',
                'desc' => ''
            ));
            $general_settings->add_field(array(
                'name' => __('App id', RNOC_TEXT_DOMAIN),
                'id' => RNOC_PLUGIN_PREFIX . 'fb_checkbox_plugin_app_id',
                'type' => 'text',
                'default' => '',
                'desc' => ''
            ));
            return $general_settings;
        }
    }
}