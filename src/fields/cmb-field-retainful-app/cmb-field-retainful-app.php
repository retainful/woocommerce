<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Retainful_App
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
        add_filter('cmb2_render_retainful_app', array($this, 'render_retainful_app'), 10, 5);
    }

    /**
     * Render select box field
     */
    public function render_retainful_app($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $admin_settings = new Rnoc\Retainful\Admin\Settings();
        $is_app_connected = $admin_settings->isAppConnected();
        $this->setupAdminScripts();
        ?>
        <input type="text" name="<?php echo $field_type_object->_name(); ?>" id="retainful_app_id"
               value="<?php echo $field_escaped_value; ?>" class="regular-text"/>
        <input type="hidden" id="retainful_ajax_path" value="<?php echo admin_url('admin-ajax.php') ?>">
        <button type="button" class="button button-primary"
                id="validate_retainful_app_id"><?php echo (!$is_app_connected) ? __('Connect', RNOC_TEXT_DOMAIN) : __('Re-Connect', RNOC_TEXT_DOMAIN); ?></button>
        <div style="float: right;background: #fff;border: 1px solid #eee;color:#333;margin: 0 5px;padding: 5px 10px;display:inline-block;align-items:center;border-radius: 4px;box-shadow: 0 0 5px 0 #ddd;">
            <h3 style="display:inline-block;font-family:'helvetica',sans-serif;margin: 0;font-weight: 600;font-size:18px;color: #333;"><?php echo __('Signup for free, Get your API key now!', RNOC_TEXT_DOMAIN) ?></h3>
            <p style="font-family:'helvetica',sans-serif;margin: 0;display:inline-block;"><a
                        href="https://app.retainful.com" target="_blank"
                        style="font-family:'helvetica',sans-serif;margin: 0 5px;display: inline-block;padding: 10px 20px;text-decoration: none;color:#fff;background:#385FF7;border-radius: 4px;font-weight: 600;"><?php echo __('Get your API Key', RNOC_TEXT_DOMAIN); ?></a>
            </p>
        </div>
        <br>
        <div class="retainful_app_validation_message"><p
                    style="color:green;"><?php echo ($is_app_connected) ? __('Successfully connected to Retainful', RNOC_TEXT_DOMAIN) : '' ?></p>
        </div>

        <?php
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_retainful_app_asset_path', plugins_url('', __FILE__));
        wp_enqueue_script('retainful-app', $asset_path . '/js/script.js');
    }
}

$cmb2_field_retainful_app = new CMB2_Field_Retainful_App();
