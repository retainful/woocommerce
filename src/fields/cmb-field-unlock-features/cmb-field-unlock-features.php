<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Unlock_Features
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
        add_filter('cmb2_render_unlock_features', array($this, 'render_unlock_features'), 10, 5);
    }

    /**
     * features unlock field
     * @param $field
     * @param $field_escaped_value
     * @param $field_object_id
     * @param $field_object_type
     * @param $field_type_object
     */
    public function render_unlock_features($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $for = isset($field->args['for']) ? $field->args['for'] : 'pro-coupon-usage-restriction';
        $unlock_message = isset($field->args['unlock_message']) ? $field->args['unlock_message'] : __('Sign up to unlock coupon rules for FREE!', RNOC_TEXT_DOMAIN);
        $link_only_field = isset($field->args['link_only_field']) ? $field->args['link_only_field'] : 0;
        $admin = new \Rnoc\Retainful\Admin\Settings();
        if ($link_only_field == 1) {
            echo $admin->unlockPremiumLink();
        } else {
            $redirect_url = isset($field->args['redirect_url']) ? $field->args['redirect_url'] : admin_url('admin.php?page=' . $admin->slug . '_license');
            ?>
            <style>
                .overlay-container {
                    position: relative;
                }

                .overlay-image {
                    opacity: 1;
                    display: block;
                    width: 100%;
                    height: auto;
                    transition: .5s ease;
                    backface-visibility: hidden;
                }

                .container-middle {
                    transition: .5s ease;
                    opacity: 0;
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    -ms-transform: translate(-50%, -50%);
                    text-align: center;
                }

                .overlay-container:hover .overlay-image {
                    opacity: 0.3;
                }

                .overlay-container:hover .container-middle {
                    opacity: 1;
                }

                .overlay-text {
                    font-size: 16px;
                    padding: 16px 32px;
                }
            </style>
            <div class="overlay-container">
                <img src="<?php echo RNOC_PLUGIN_URL ?>src/assets/images/<?php echo $for; ?>.png" width="100%"
                     class="overlay-image">
                <div class="container-middle">
                    <div class="overlay-text"><span
                                class="dashicons-lock dashicons"></span><?php echo $unlock_message ?>
                        <br><br>
                        <a href="<?php echo $redirect_url; ?>"
                           target="_blank"><?php echo __('Click Here!', RNOC_TEXT_DOMAIN); ?></a>
                    </div>
                </div>
            </div>
            <?php
        }
    }
}

$cmb2_field_unlock_features = new CMB2_Field_Unlock_Features();
