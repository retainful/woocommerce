<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Edit_Email_Template
{
    /**
     * Current version number
     */
    const VERSION = '1.0.0';

    /**
     * Initialize the plugin by hooking into CMB2
     */
    public function __construct()
    {
        add_filter('cmb2_render_email_template_edit', array($this, 'render_email_template_edit'), 10, 5);
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_email_template_preview', plugins_url('', __FILE__));
        wp_enqueue_script('abandoned-cart-email-preview', $asset_path . '/js/main.js');
        wp_localize_script('abandoned-cart-email-preview', 'email_template', array('email_field_empty' => __('Please enter email Id!', RNOC_TEXT_DOMAIN), 'sure_msg' => __('Are you sure?', RNOC_TEXT_DOMAIN), 'path' => admin_url('admin-ajax.php')));
    }

    /**
     * Render select box field
     */
    public function render_email_template_edit($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $this->setupAdminScripts();
        $template_id = (isset($_REQUEST['template'])) ? sanitize_key($_REQUEST['template']) : 0;
        $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
        $template = $abandoned_cart->getTemplate($template_id);
        $settings = new \Rnoc\Retainful\Admin\Settings();
        $api = new \Rnoc\Retainful\library\RetainfulApi();
        $language_helper = new \Rnoc\Retainful\Integrations\MultiLingual();
        $default_language = $language_helper->getDefaultLanguage();
        $extra_fields = (isset($template->extra)) ? $template->extra : '{}';
        $extra_data = json_decode($extra_fields, true);
        ?>
        </form>
        <div class="rnoc-alert"
             style="display:none;padding: 15px;position: fixed;top:40px;right: 10px;min-width: 250px;z-index: 9999;border-radius: 5px">
        </div>
        <style>
            .error-alert {
                background: red;
                color: white;
            }

            .success-alert {
                background: green;
                color: white;
            }
        </style>
        <h3>
            <?php
            echo __('Add Or Edit Email Template');
            ?>
        </h3>
        <div class="create-or-edit-template-form">
            <div class="cmb2-metabox cmb-field-list">
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label for="field_template_name"><?php echo __('Template Name', RNOC_TEXT_DOMAIN); ?></label>
                    </div>
                    <div class="cmb-td">
                        <input name="template_name" type="text" class="regular-text"
                               value="<?php echo isset($template->template_name) ? $template->template_name : '' ?>"
                               id="field_template_name">
                        <input type="hidden" name="language_code"
                               value="<?php echo(isset($_GET['language_code']) ? $_GET['language_code'] : $default_language) ?>"/>
                        <input name="id" type="hidden" class="regular-text"
                               value="<?php echo isset($template->id) ? $template->id : 0 ?>" id="field_id">
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label for="field_subject"><?php echo __('Template Subject', RNOC_TEXT_DOMAIN); ?></label>
                    </div>
                    <div class="cmb-td">
                        <input name="subject" type="text"
                               value="<?php echo isset($template->subject) ? $template->subject : '' ?>"
                               class="regular-text" id="field_subject">
                        <br>
                        <em><?php echo __('Use <b>{{customer_name}}</b> - To display Customer name', RNOC_TEXT_DOMAIN); ?></em>
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label for="field_coupon_code"><?php echo __('Coupon code for recovery email', RNOC_TEXT_DOMAIN); ?></label>
                    </div>
                    <div class="cmb-td">
                        <?php
                        if ($settings->isProPlan()) {
                            ?>
                            <select name="extra[coupon_code]"
                                    class="regular-text" id="field_coupon_code">
                                <option value=""><?php echo __('Select', RNOC_TEXT_DOMAIN) ?></option>
                                <?php
                                $selected = isset($extra_data['coupon_code']) ? $extra_data['coupon_code'] : '';
                                $coupons_list = $this->getWooCouponCodes();
                                if (!empty($coupons_list)) {
                                    foreach ($coupons_list as $key => $value) {
                                        ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($key == $selected) ? 'selected' : '' ?>> <?php echo $value; ?></option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                            <br>
                            <em><?php echo __('<b>Note</b>:This is a list of coupon codes from WooCommerce -> Coupons. If none found, please create the coupon code in WooCommerce -> Coupons', RNOC_TEXT_DOMAIN); ?></em>
                            <?php
                        } else {
                            echo $settings->unlockPremiumLink();
                        }
                        ?>
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Is Email template active?', RNOC_TEXT_DOMAIN); ?></label>
                    </div>
                    <div class="cmb-td">
                        <select name="active" id="field_is_active">
                            <option value="1" <?php echo isset($template->is_active) ? ($template->is_active == "1") ? "selected" : "" : '' ?>><?php echo __('Yes', RNOC_TEXT_DOMAIN) ?></option>
                            <option value="0" <?php echo isset($template->is_active) ? ($template->is_active == "0") ? "selected" : "" : '' ?>><?php echo __('No', RNOC_TEXT_DOMAIN) ?></option>
                        </select>
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Email Body', RNOC_TEXT_DOMAIN); ?></label>
                    </div>
                    <div class="cmb-td">
                        <div class="rnoc-grid">
                            <?php
                            for ($i = 1; $i <= 3; $i++) {
                                ?>
                                <div class="grid-column">
                                    <div class="template-preview"
                                         style="background-image: url('<?php echo RNOC_PLUGIN_URL . 'src/admin/templates/preview/' . $i . '.png'; ?>')"></div>
                                    <button data-template="<?php echo $i; ?>" type="button" class="insert-template"
                                            data-type="free"><?php echo __('Insert template', RNOC_TEXT_DOMAIN); ?></button>
                                </div>
                                <?php
                            }
                            $premium_templates = array();
                            $premium_templates = apply_filters('rnoc_premium_templates_list', $premium_templates);
                            if (empty($premium_templates)) {
                                for ($i = 1; $i <= 3; $i++) {
                                    ?>
                                    <div class="grid-column">
                                        <div class="template-preview"
                                             style="background-image: url('<?php echo RNOC_PLUGIN_URL . 'src/admin/templates/preview/premium-' . $i . '.png'; ?>')">
                                            <div class="overlay"><?php echo __('Premium', RNOC_TEXT_DOMAIN); ?></div>
                                        </div>
                                        <div class="get-now-btn"><a
                                                    href="<?php echo $api->upgradePremiumUrl(); ?>"><span
                                                        class="dashicons dashicons-lock"></span><?php echo __('Upgrade to Premium to Unlock', RNOC_TEXT_DOMAIN); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                foreach ($premium_templates as $id) {
                                    ?>
                                    <div class="grid-column">
                                        <div class="template-preview"
                                             style="background-image: url('<?php echo RNOC_PLUGIN_URL . 'src/admin/templates/preview/premium-' . $id . '.png'; ?>')">
                                            <div class="overlay"><?php echo __('Premium', RNOC_TEXT_DOMAIN); ?></div>
                                        </div>
                                        <button data-template="<?php echo $id; ?>" type="button" class="insert-template"
                                                data-type="premium"><?php echo __('Insert template', RNOC_TEXT_DOMAIN); ?></button>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        <?php
                        wp_editor(isset($template->body) ? $template->body : '', 'email_template_body');
                        echo __('You can use following short codes in your email template:<br> <b>{{customer_name}}</b> - To display Customer name<br><b>{{site_url}}</b> - Site link<br> <b>{{cart_recovery_link}}</b> - Link to recover user cart<br><b>{{user_cart}}</b> - Cart details<br>', RNOC_TEXT_DOMAIN);
                        if ($settings->isProPlan()) {
                            echo __('<b>{{recovery_coupon}}</b> - Coupon code to send along with recovery mail,Please choose the coupon above.', RNOC_TEXT_DOMAIN);
                        }
                        ?>
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Send this email in', RNOC_TEXT_DOMAIN); ?></label>
                    </div>
                    <div class="cmb-td">
                        <input type="text" class="number_only_field" name="frequency"
                               value="<?php echo isset($template->frequency) ? $template->frequency : 1 ?>"
                               id="field_frequency">
                        <select name="day_or_hour" id="field_day_or_hour">
                            <option value="Hours" <?php echo isset($template->day_or_hour) ? ($template->day_or_hour == "Hours") ? "selected" : "" : '' ?>><?php echo __('Hour(s)', RNOC_TEXT_DOMAIN) ?></option>
                            <option value="Days" <?php echo isset($template->day_or_hour) ? ($template->day_or_hour == "Days") ? "selected" : "" : '' ?>><?php echo __('Day(s)', RNOC_TEXT_DOMAIN) ?></option>
                        </select>
                        <em><?php echo __('after cart is abandoned.', RNOC_TEXT_DOMAIN); ?></em>
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Send a test email to', RNOC_TEXT_DOMAIN); ?></label>
                    </div>
                    <div class="cmb-td">
                        <input type="text" class="regular-text" id="test_mail_to">
                        <button type="button" data-path="<?php echo admin_url('admin-ajax.php'); ?>"
                                class="send-test-email button"><?php echo __('Send a test Email', RNOC_TEXT_DOMAIN); ?></button>
                        <span id="sending_email_loader"
                              style="display: none;"><?php echo __('Sending...', RNOC_TEXT_DOMAIN); ?></span>
                        <br>
                        <em><?php echo __('Enter email id to receive an test email.', RNOC_TEXT_DOMAIN); ?></em>
                    </div>
                </div>
                <?php
                do_action('rnoc_email_templates_extra_fields', $template_id);
                ?>
                <div class="cmb-row table-layout">
                    <div class="cmb-td">
                        <button type="button" data-path="<?php echo admin_url('admin-ajax.php'); ?>"
                                class="button button-primary save-email-template"><?php echo __('Save', RNOC_TEXT_DOMAIN); ?></button>
                        <button type="button" data-path="<?php echo admin_url('admin-ajax.php'); ?>"
                                data-redirectto="<?php echo admin_url('admin.php?page=' . $settings->slug . '_abandoned_cart_email_templates'); ?>&language_code=<?php echo (isset($_GET['language_code'])) ? $_GET['language_code'] : $default_language ?>"
                                class="button button-green save-close-email-template"><?php echo __('Save and close', RNOC_TEXT_DOMAIN); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=' . $settings->slug . '_abandoned_cart_email_templates'); ?>"
                           class="button button-red reload-button"><?php echo __('Close', RNOC_TEXT_DOMAIN); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <form>
            <style>
                #submit-cmb {
                    display: none;
                }

                .rnoc-grid {
                    width: 100%;
                    margin: 20px 0;
                    display: flex;
                }

                .grid-column {
                    display: inline-block;
                    width: 15%;
                    margin: 0.5%;
                    padding: 5px;
                    box-shadow: 0 2px 4px rgba(126, 142, 177, .12);
                    border: 1px solid #eeeeee;
                    border-bottom: 3px solid transparent;
                    text-align: center;
                    border-radius: 5px;
                    margin-bottom: 30px;
                    transition: all .3s ease-in-out;
                }

                .grid-column:hover {
                    box-shadow: 0 5px 25px 0 rgba(0, 0, 100, .1);
                    border-bottom: 3px solid #7abe4d;
                }

                .grid-column .insert-template {
                    display: inline-block;
                    padding: 10px 30px;
                    text-align: center;
                    font-size: 15px;
                    font-weight: 500;
                    margin: 20px 0 10px;
                    text-transform: capitalize;
                    white-space: nowrap;
                    border-radius: 4px;
                    box-sizing: border-box;
                    transition: .2s;
                    text-decoration: none;
                    background: #6772e5;
                    border: 1px solid #6772e5;
                    color: #ffffff;
                    cursor: pointer;
                }

                .grid-column .overlay {
                    position: relative;
                    background: linear-gradient(136.14deg, #8cce17 0%, #54B22E 100%);
                    color: #fff;
                    font-size: 13px;
                    font-weight: 600;
                    padding: 8px;
                    border-radius: 0 20px 20px 0;
                    display: inline-block;
                    float: left;
                    box-shadow: 0 2px 4px rgba(126, 142, 177, .12);
                }

                .grid-column .get-now-btn a {
                    display: inline-block;
                    padding: 10px 30px;
                    text-align: center;
                    font-size: 15px;
                    font-weight: 500;
                    margin: 20px 0 10px;
                    text-transform: capitalize;
                    border-radius: 4px;
                    box-sizing: border-box;
                    transition: .2s;
                    text-decoration: none;
                    background: red;
                    border: 1px solid red;
                    color: #ffffff;
                    line-height: 25px;
                }

                .template-preview {
                    height: 300px;
                    background-size: cover;
                    background-repeat: no-repeat;
                    -webkit-transition: background-position 2s ease-in-out;
                    -moz-transition: background-position 2s ease-in-out;
                    -ms-transition: background-position 2s ease-in-out;
                    -o-transition: background-position 2s ease-in-out;
                    transition: background-position 2s ease-in-out;
                }

                .template-preview:hover {
                    background-position: 0 -50px;
                }
            </style>
        <?php
    }

    /**
     * select coupon
     * @return array
     */
    function getWooCouponCodes()
    {
        $posts = get_posts(array('post_type' => 'shop_coupon', 'post_status' => 'publish'));
        return wp_list_pluck($posts, 'post_title', 'post_title');
    }
}

new CMB2_Field_Edit_Email_Template();
