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
        add_filter('cmb2_render_email_after', array($this, 'render_email_after'), 10, 5);
    }

    /**
     * Render select box field
     */
    public function render_email_after($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        if (isset($field_escaped_value['send_in'])) {
            $send_in = $field_escaped_value['send_in'];
        } else {
            $send_in = 'hour';
        }
        ?>
        <input type="text" class="number_only_field" name="<?php echo $field->args['_name']; ?>[value]"
               value="<?php echo isset($field_escaped_value['value']) ? $field_escaped_value['value'] : 1; ?>">
        <select name="<?php echo $field->args['_name']; ?>[send_in]">
            <option value="hour" <?php echo ($send_in == "hour") ? 'selected' : ''; ?>><?php echo __('Hour(s)', RNOC_TEXT_DOMAIN); ?></option>
            <option value="day" <?php echo ($send_in == "day") ? 'selected' : ''; ?>><?php echo __('Day(s)', RNOC_TEXT_DOMAIN); ?></option>
        </select>
        <em><?php echo $field->args['description']; ?></em>
        <style>
            .wp-admin select {

                vertical-align: top !important;
            }
        </style>
        <?php
    }
}

$cmb2_field_email_after = new CMB2_Field_Email_After();
