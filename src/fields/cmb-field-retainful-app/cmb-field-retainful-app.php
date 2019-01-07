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
        <div class="retainful_app_validation_message" style="display:flex;"><p
                    style="color:green;"><?php echo ($is_app_connected) ? __('Successfully connected to Retainful', RNOC_TEXT_DOMAIN) : '' ?></p>
        </div>
        <div style="display:block;background: #fff;border: 1px solid #eee;color:#333;padding: 20px;max-width: 100%;text-align:center;border-radius: 4px;box-shadow: 0 0 5px 0 #ddd;margin: auto;">
            <p style="font-family:'helvetica',sans-serif;margin: 0 0 20px;">
                <img src="https://www.retainful.com/images/retainful-logo.png" style="max-width: 150px;height: auto;"
                     alt=""></p>
            <?php
            if (!$is_app_connected) {
                ?>
                <h3 style="flex: 1;font-family:'helvetica',sans-serif;margin: 0;font-weight: 600;font-size:25px;color: #333;line-height:1.3;"><?php echo __('Get your API Key for free', RNOC_TEXT_DOMAIN); ?></h3>
                <p style="font-family:'helvetica',sans-serif;margin: 15px 0;color:#777;font-size: 17px;letter-spacing:0.02em;line-height:1.6;">
                    <?php echo __('Increase sales & get more money per customer. Drive repeat purchases by automatically sending a
                single-use coupon for next purchase.', RNOC_TEXT_DOMAIN); ?>
                </p>
                <p style="font-family:'helvetica',sans-serif;margin: 20px 0 0;">
                    <a href="https://app.retainful.com" target="_blank"
                       style="font-family:'helvetica',sans-serif;display: inline-block;font-size: 16px;padding: 10px 20px;text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 500;line-height:1.6;"><?php echo __('Get your API Key', RNOC_TEXT_DOMAIN); ?></a>
                </p>
                <?php
            } else {
                ?>
                <p style="font-family:'helvetica',sans-serif;margin: 15px 0;color:#777;font-size: 17px;letter-spacing:0.02em;line-height:1.6;">
                    <?php echo __('Increase sales & get more money per customer. Drive repeat purchases by automatically sending a
                single-use coupon for next purchase.', RNOC_TEXT_DOMAIN); ?>
                </p>
                <p style="font-family:'helvetica',sans-serif;margin: 20px 0 0;">
                    <a href="https://app.retainful.com/dashboard" target="_blank"
                       style="font-family:'helvetica',sans-serif;display: inline-block;font-size: 16px;padding: 10px 20px;text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 500;line-height:1.6;"><?php echo __('Visit Dashboard!', RNOC_TEXT_DOMAIN); ?></a>
                </p>
                <?php
            }
            ?>
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
