<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Unlock_Usage_Restriction
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
        add_filter('cmb2_render_unlock_usage_restriction', array($this, 'render_unlock_usage_restriction'), 10, 5);
    }

    /**
     * Render select box field
     */
    public function render_unlock_usage_restriction($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $admin = new \Rnoc\Retainful\Admin\Settings();
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
            <img src="<?php echo RNOC_PLUGIN_URL ?>src/assets/images/pro-coupon-usage-restriction.png" width="100%"
                 class="overlay-image">
            <div class="container-middle">
                <div class="overlay-text"><span
                            class="dashicons-lock dashicons"></span><?php echo __('Unlock this features!', RNOC_TEXT_DOMAIN) ?>
                    <br><br>
                    <a href="<?php echo admin_url('admin.php?page='.$admin->slug.'_license'); ?>"><?php echo __('Click Here!', RNOC_TEXT_DOMAIN); ?></a>
                </div>
            </div>
            <input type="hidden" id="unlock_usage_restriction" value="1"/>
        </div>
        <?php
    }
}

$cmb2_field_unlock_usage_restriction = new CMB2_Field_Unlock_Usage_Restriction();
