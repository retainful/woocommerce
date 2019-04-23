<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Email_After
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
        add_filter('cmb2_render_email_templates', array($this, 'render_email_templates'), 10, 5);
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_abandoned_cart_dashboard_asset_path', plugins_url('', __FILE__));
        wp_enqueue_style('abandoned-cart-email-template', $asset_path . '/css/style.css');
        wp_enqueue_script('abandoned-cart-email-template-js', $asset_path . '/js/main.js');
        wp_localize_script('abandoned-cart-email-template-js', 'email_template', array('email_field_empty' => __('Please enter email Id!', RNOC_TEXT_DOMAIN), 'sure_msg' => __('Are you sure?', RNOC_TEXT_DOMAIN), 'path' => admin_url('admin-ajax.php')));
    }

    /**
     * Render select box field
     */
    public function render_email_templates($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $this->setupAdminScripts();
        ?>
        <div>
            <input type="submit" name="submit-cmb" id="submit-cmb" class="button button-primary no-hide"
                   value="Save">
        </div>
        <div>
            <h3 style="text-align: center;"><?php echo __('Email Templates', RNOC_TEXT_DOMAIN) ?></h3>
        </div>
        <?php
        $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
        $templates = $abandoned_cart->getEmailTemplates();
        ?>
        <p style="text-align: center;"><?php echo __("Add email templates at different intervals to maximize the possibility of recovering your abandoned carts.", RNOC_TEXT_DOMAIN) ?></p>
        <div class="email-templates-list">
            <table width="100%">
                <tr>
                    <th><?php echo __('Template Name', RNOC_TEXT_DOMAIN); ?></th>
                    <th><?php echo __('Template Sent After', RNOC_TEXT_DOMAIN); ?></th>
                    <th><?php echo __('Active?', RNOC_TEXT_DOMAIN); ?></th>
                    <th><?php echo __('Action', RNOC_TEXT_DOMAIN); ?></th>
                </tr>
                <tbody>
                <?php
                if (!empty($templates)) {
                    foreach ($templates as $template) {
                        ?>
                        <tr id="template-no-<?php echo $template->id ?>">
                            <td><?php echo $template->template_name; ?></td>
                            <td><?php echo $template->frequency . ' ' . $template->day_or_hour . ' ' . __('After Abandonment') ?></td>
                            <td>
                                <label class="switch">
                                    <input type="checkbox"
                                           value="1" <?php echo ($template->is_active == 1) ? ' checked' : ''; ?>
                                           class="is-template-active" data-template="<?php echo $template->id ?>">
                                    <span class="slider round"></span>
                                </label>
                            </td>
                            <td>
                                <button type="button" data-template="<?php echo $template->id ?>"
                                        class="button button-green edit-email-template"><?php echo __('Edit', RNOC_TEXT_DOMAIN) ?></button>
                                <button type="button" data-template="<?php echo $template->id ?>"
                                        class="button button-red remove-email-template"><?php echo __('Delete', RNOC_TEXT_DOMAIN) ?></button>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="4">
                            <p class="force-center text-danger"><?php echo __('No email templates found! So, No emails were sent!', RNOC_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <div class="force-center">
                <a href="javascript:" data-path="<?php echo admin_url('admin-ajax.php'); ?>"
                   data-id="0"
                   class="button button-primary create-or-add-template"><?php echo __('Create New Template', RNOC_TEXT_DOMAIN) ?></a>
            </div>
        </div>
        <div class="create-or-edit-template-form">
            <div class="cmb2-metabox cmb-field-list">
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Template Name'); ?></label>
                    </div>
                    <div class="cmb-td">
                        <input name="template_name" type="text" class="regular-text" id="field_template_name">
                        <input name="id" type="hidden" class="regular-text" value="0" id="field_id">
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Template Subject'); ?></label>
                    </div>
                    <div class="cmb-td">
                        <input name="subject" type="text" class="regular-text" id="field_subject">
                        <br>
                        <em><?php echo __('Use <b>{{customer_name}}</b> - To display Customer name', RNOC_TEXT_DOMAIN); ?></em>
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Email Body'); ?></label>
                    </div>
                    <div class="cmb-td">
                        <?php
                        wp_editor('', 'email_template_body', $settings = array('editor_height' => '500'));
                        echo __('You can use following short codes in your email template:<br> <b>{{customer_name}}</b> - To display Customer name<br><b>{{site_url}}</b> - Site link<br> <b>{{cart_recovery_link}}</b> - Link to recover user cart<br><b>{{user_cart}}</b> - Cart details', RNOC_TEXT_DOMAIN)
                        ?>
                    </div>
                </div>
                <div class="cmb-row table-layout">
                    <div class="cmb-th">
                        <label><?php echo __('Send this email in'); ?></label>
                    </div>
                    <div class="cmb-td">
                        <input type="text" class="number_only_field" name="frequency" value="1" id="field_frequency">
                        <select name="day_or_hour" id="field_day_or_hour">
                            <option value="Hours"><?php echo __('Hour(s)', RNOC_TEXT_DOMAIN) ?></option>
                            <option value="Days"><?php echo __('Day(s)', RNOC_TEXT_DOMAIN) ?></option>
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
                <div class="cmb-row table-layout">
                    <div class="cmb-td">
                        <button type="button" data-path="<?php echo admin_url('admin-ajax.php'); ?>"
                                class="button button-primary save-email-template"><?php echo __('Save', RNOC_TEXT_DOMAIN); ?></button>
                        <button type="button" data-path="<?php echo admin_url('admin-ajax.php'); ?>"
                                class="button button-green save-close-email-template"><?php echo __('Save and close', RNOC_TEXT_DOMAIN); ?></button>
                        <button type="button"
                                class="button button-red reload-button"><?php echo __('Close', RNOC_TEXT_DOMAIN); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

$cmb2_field_email_after = new CMB2_Field_Email_After();
