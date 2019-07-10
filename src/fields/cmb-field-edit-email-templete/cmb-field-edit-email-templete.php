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
                        <label for="field_template_name"><?php echo __('Template Name'); ?></label>
                    </div>
                    <div class="cmb-td">
                        <input name="template_name" type="text" class="regular-text"
                               value="<?php echo isset($template->template_name) ? $template->template_name : '' ?>"
                               id="field_template_name">
                        <input name="id" type="hidden" class="regular-text"
                               value="<?php echo isset($template->id) ? $template->id : 0 ?>" id="field_id">
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label for="field_subject"><?php echo __('Template Subject'); ?></label>
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
                        <label><?php echo __('Email Body'); ?></label>
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
                                                        class="dashicons dashicons-lock"></span><?php echo __('Unlock', RNOC_TEXT_DOMAIN); ?>
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
                        echo __('You can use following short codes in your email template:<br> <b>{{customer_name}}</b> - To display Customer name<br><b>{{site_url}}</b> - Site link<br> <b>{{cart_recovery_link}}</b> - Link to recover user cart<br><b>{{user_cart}}</b> - Cart details', RNOC_TEXT_DOMAIN)
                        ?>
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Send this email in'); ?></label>
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
                        <label><?php echo __('Send a test email to'); ?></label>
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
                                data-redirectto="<?php echo admin_url('admin.php?page=' . $settings->slug . '_abandoned_cart_email_templates'); ?>"
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
                    cursor: pointer;
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
                    white-space: nowrap;
                    border-radius: 4px;
                    box-sizing: border-box;
                    transition: .2s;
                    text-decoration: none;
                    background: red;
                    border: 1px solid red;
                    color: #ffffff;
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
}

new CMB2_Field_Edit_Email_Template();
