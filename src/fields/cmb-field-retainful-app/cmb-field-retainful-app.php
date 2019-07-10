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
        $asset_path = apply_filters('cmb2_field_retainful_app_asset_path', plugins_url('', __FILE__));
        $admin_settings = new Rnoc\Retainful\Admin\Settings();
        $api = new \Rnoc\Retainful\library\RetainfulApi();
        $is_app_connected = $admin_settings->isAppConnected();
        $this->setupAdminScripts();
        ?>
        <input type="text" name="<?php echo $field_type_object->_name(); ?>" id="retainful_app_id"
               value="<?php echo $field_escaped_value; ?>" class="regular-text"/>
        <input type="hidden" id="retainful_ajax_path" value="<?php echo admin_url('admin-ajax.php') ?>">
        <button type="button" class="button button-primary"
                id="validate_retainful_app_id"><?php echo (!$is_app_connected) ? __('Connect', RNOC_TEXT_DOMAIN) : __('Re-Connect', RNOC_TEXT_DOMAIN); ?></button>
        <img src="<?= $asset_path ?>/images/loader.gif" width="20px;" id="connect-to-retainful-loader"
             style="display: none;"/>
        <?php
        if ($is_app_connected) {
            ?>
            <button type="button" id="disconnect-app-btn"
                    class="button"><?= __('Dis-connect', RNOC_TEXT_DOMAIN) ?></button>
            <?php
        }
        ?>
        <div class="retainful_app_validation_message" style="display:flex;">
            <p style="color:green;"><?php echo ($is_app_connected) ? __('Successfully connected to Retainful', RNOC_TEXT_DOMAIN) : '' ?></p>
            <p style="color: red"><?= (!$is_app_connected && !empty($field_escaped_value)) ? __('You have disconnected from Retainful!') : ''; ?></p>
        </div>
        <?php
        if (!$is_app_connected) {
            ?>
            <div style="display:block;background: #fff;border: 1px solid #eee;color:#333;padding: 20px;max-width: 100%;text-align:center;border-radius: 4px;box-shadow: 0 0 5px 0 #ddd;margin: auto;">
                <p style="font-family:'helvetica',sans-serif;margin: 0 0 20px;">
                    <img src="data:image/png;base64,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"
                         style="max-width: 150px;height: auto;"
                         alt=""></p>

                <h3 style="flex: 1;font-family:'helvetica',sans-serif;margin: 0;font-weight: 600;font-size:25px;color: #333;line-height:1.3;"><?php echo __('Get your API Key for free', RNOC_TEXT_DOMAIN); ?></h3>
                <p style="font-family:'helvetica',sans-serif;margin: 15px 0;color:#777;font-size: 17px;letter-spacing:0.02em;line-height:1.6;">
                    <?php echo __('Increase sales & get more money per customer. Drive repeat purchases by automatically sending a
                single-use coupon for next purchase. Unlock this feature by getting the App ID.', RNOC_TEXT_DOMAIN); ?>
                </p>
                <p style="font-family:'helvetica',sans-serif;margin: 15px 0;color:#777;font-size: 17px;letter-spacing:0.02em;line-height:1.6;">
                    <?php echo __('Unlock this feature by getting the App ID.', RNOC_TEXT_DOMAIN); ?>
                </p>

                <p style="font-family:'helvetica',sans-serif;margin: 20px 0 0;">
                    <a href="https://app.retainful.com" target="_blank"
                       style="font-family:'helvetica',sans-serif;display: inline-block;font-size: 16px;padding: 10px 20px;text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 500;line-height:1.6;"><?php echo __('Get your API Key', RNOC_TEXT_DOMAIN); ?></a>
                </p>
            </div>
            <?php
        } else {
            $plan = $admin_settings->getUserActivePlan();
            ?>
            <div style="display:block;background: #fff;border: 1px solid #eee;color:#333;padding: 20px;max-width: 100%;text-align:center;border-radius: 4px;box-shadow: 0 0 5px 0 #ddd;margin: 20px auto;">
                <p style="font-family:'helvetica',sans-serif;margin: 20px 0;">
                    <img src="data:image/png;base64,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"
                         style="max-width: 150px;height: auto;"
                         alt=""></p>
                <div class="list-app-msg">
                    <div class="dashboard-msg">
                        <p style="font-family:'helvetica',sans-serif;margin: 15px 0;color:#777;font-size: 17px;letter-spacing:0.02em;line-height:1.6;">
                            <?php echo __('Visit Retainful Dashboard to view the analytics for your next order coupons.', RNOC_TEXT_DOMAIN); ?>
                        </p>
                        <a href="<?php echo $api->app_url ?>" target="_blank"
                           style="font-family:'helvetica',sans-serif;display: inline-block;font-size: 16px;padding: 10px 20px;text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 600;line-height:1.6;"><?php echo __('Dashboard', RNOC_TEXT_DOMAIN); ?></a>
                    </div>
                    <?php
                    if (!in_array($plan, array('pro', 'business'))) {
                        ?>
                        <div class="premium-msg">
                            <p style="font-family:'helvetica',sans-serif;margin: 15px 0;color:#777;font-size: 17px;letter-spacing:0.02em;line-height:1.6;">
                                <?php echo __('Upgrade to Premium and get more features like Email Collection during add to cart and Coupon for email entry.', RNOC_TEXT_DOMAIN); ?>
                            </p>
                            <p style="font-family:'helvetica',sans-serif;margin: 20px 0 0;">
                                <a href="<?php echo $api->upgradePremiumUrl(); ?>" target="_blank"
                                   style="font-family:'helvetica',sans-serif;display: inline-block;font-size: 16px;padding: 10px 20px;text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 600;line-height:1.6;"><?php echo __('Upgrade to Premium', RNOC_TEXT_DOMAIN); ?></a>
                            </p>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
        }
        ?>
        <style>
            #submit-cmb {
                display: none;
            }
            .dashboard-msg, .premium-msg{
                display: inline-block;
                padding: 30px;
                box-shadow: 0 2px 4px rgba(126,142,177,.12);
                border: 1px solid #eee;
                border-bottom: 3px solid transparent;
                text-align: center;
                border-radius: 5px;
                margin-bottom: 30px;
                transition: all .3s ease-in-out;
                flex-basis: 30%;
            }
            .list-app-msg{
                display: flex;
                justify-content: center;
            }
            .list-app-msg > div{
                margin-right: 20px;
            }
            .list-app-msg > div:last-child{
                margin-right: 0;
            }
        </style>
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
