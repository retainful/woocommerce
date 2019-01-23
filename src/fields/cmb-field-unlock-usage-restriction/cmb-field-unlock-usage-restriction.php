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
        ?>
        <div style="text-align: center">
            <p>
                <?php echo __('Please connect to Retainful Inorder to use this features!', RNOC_TEXT_DOMAIN) ?>
            </p>
            <input type="hidden" id="unlock_usage_restriction" value="1" />
        </div>
        <?php
    }
}

$cmb2_field_unlock_usage_restriction = new CMB2_Field_Unlock_Usage_Restriction();
