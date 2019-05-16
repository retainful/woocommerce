<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Upgrade_Premium
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
        add_filter('cmb2_render_upgrade_premium', array($this, 'render_upgrade_premium'), 10, 5);
    }

    /**
     * features unlock field
     * @param $field
     * @param $field_escaped_value
     * @param $field_object_id
     * @param $field_object_type
     * @param $field_type_object
     */
    public function render_upgrade_premium($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        ?>
        <div class="overlay-container">
            hsi
        </div>
        <?php
    }
}

new CMB2_Field_Upgrade_Premium();
