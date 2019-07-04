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
				<input type="submit" name="submit-cmb" id="submit-cmb" class="button button-primary no-hide" value="Save">
			</div>
		<div class="main_email_tt">
			<div class="email-template">
				<h3><?php echo __('Email Templates', RNOC_TEXT_DOMAIN) ?></h3>			
				<?php
				$abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
				$settings = new \Rnoc\Retainful\Admin\Settings();
				$templates = $abandoned_cart->getEmailTemplates();
				?>
				<p style="text-align: center;"><?php echo __("Add email templates at different intervals to maximize the possibility of recovering your abandoned carts.", RNOC_TEXT_DOMAIN) ?></p>
			</div>
			<div class="force-center1">
                <a href="<?php echo admin_url('admin.php?page=' . $settings->slug . '_abandoned_cart_email_templates&task=create-email-template'); ?>"
                   class="create-new-template"><?php echo __('Create New Template', RNOC_TEXT_DOMAIN) ?></a>
            </div>
        </div>
			
			
			<table class="table">
				<thead class="bg-light">
					<tr class="border-0">
						<th class="border-0"><?php echo __('Template Name', RNOC_TEXT_DOMAIN); ?></th>
						<th class="border-0"><?php echo __('Template Sent After', RNOC_TEXT_DOMAIN); ?></th>
						<th class="border-0"><?php echo __('Active?', RNOC_TEXT_DOMAIN); ?></th>
						<th class="border-0" style="width: 45px;"><?php echo __('Action', RNOC_TEXT_DOMAIN); ?></th>
					</tr>
				</thead>
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
                                <a type="button"
                                   href="<?php echo admin_url('admin.php?page=' . $settings->slug . '_abandoned_cart_email_templates&task=edit-template&template=' . $template->id) ?>"
                                   class="btn_action  btn-view"><span class="dashicons_action_view dashicons dashicons-visibility"></span></a>								   
								
                                <button type="button" data-template="<?php echo $template->id ?>"
                                        class="btn_action  btn-danger remove-email-template"><span class="dashicons_action dashicons dashicons-trash"></span></button>
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
			<div class="table_data_dp"></div>
        <?php
    }
}

$cmb2_field_email_after = new CMB2_Field_Email_After();
