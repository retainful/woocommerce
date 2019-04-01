<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Date_Range_Picker
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
        add_filter('cmb2_render_date_range_picker', array($this, 'render_date_range_picker'), 10, 5);
    }

    /**
     *
     */
    public function render_date_range_picker($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $this->setupAdminScripts();
        $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
        $start_end_dates = $abandoned_cart->start_end_dates;
        ?>
        <script>
            var start_end_dates = <?php echo json_encode($start_end_dates); ?>;
        </script>
        <select id="rnoc_duration_select" name="rnoc_duration_select">
            <option value="yesterday"> <?php echo __('Yesterday', RNOC_TEXT_DOMAIN); ?></option>
            <option value="today"> <?php echo __('Today', RNOC_TEXT_DOMAIN); ?></option>
            <option value="last_seven" selected=""> <?php echo __('Last 7 days', RNOC_TEXT_DOMAIN); ?></option>
            <option value="last_fifteen"> <?php echo __('Last 15 days', RNOC_TEXT_DOMAIN); ?></option>
            <option value="last_thirty"> <?php echo __('Last 30 days', RNOC_TEXT_DOMAIN); ?></option>
            <option value="last_ninety"> <?php echo __('Last 90 days', RNOC_TEXT_DOMAIN); ?></option>
            <option value="last_year_days"> <?php echo __('Last 365', RNOC_TEXT_DOMAIN); ?></option>
            <option value="custom"> <?php echo __('Custom', RNOC_TEXT_DOMAIN); ?></option>
        </select>
        <span style="display: none" class="show_none">
            <b>Choose date:</b>
            <input type="text" name="daterange"
                   value="<?php echo $start_end_dates['last_seven']['start_date']; ?> - <?php echo $start_end_dates['last_seven']['end_date']; ?>"/>
        </span>
        <style>
            .applyBtn {
                background: #0085ba;
                border-color: #0073aa #006799 #006799;
                box-shadow: 0 1px 0 #006799;
                color: #fff;
                text-decoration: none;
            }
        </style>
        <input type="hidden" id="retainful_ajax_path" value="<?php echo admin_url('admin-ajax.php') ?>">
        <?php
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_abandoned_cart_dashboard_asset_path', plugins_url('', __FILE__));
        wp_enqueue_script('retainful-date-range-picker-moment-js', $asset_path . '/js/moment.min.js');
        wp_enqueue_script('retainful-date-range-date-picker-js', $asset_path . '/js/daterangepicker.js');
        wp_enqueue_script('retainful-date-range-picker-init-js', $asset_path . '/js/date_pick.js', '', '', true);
        wp_enqueue_style('retainful-date-range-picker-css', $asset_path . '/css/daterangepicker.css');
    }
}

$cmb2_field_unlock_usage_restriction = new CMB2_Field_Date_Range_Picker();
