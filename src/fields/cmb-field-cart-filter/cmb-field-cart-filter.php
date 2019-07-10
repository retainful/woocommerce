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
        $start_end_dates_select = $abandoned_cart->start_end_dates_label;
        $duration = (isset($_GET['duration'])) ? $_GET['duration'] : 'last_seven';
        if ($duration != "custom") {
            $selected_dates = $start_end_dates[$duration]['start_date'] . ' - ' . $start_end_dates[$duration]['end_date'];
        } else if (isset($_GET['start']) && isset($_GET['end'])) {
            $selected_dates = $_GET['start'] . ' - ' . $_GET['start'];
        } else {
            $selected_dates = $start_end_dates['last_seven']['start_date'] . ' - ' . $start_end_dates['last_seven']['end_date'];
        }
        ?>
        <script>
            var start_end_dates = <?php echo json_encode($start_end_dates); ?>;
        </script>
        <div class="abandoned_cart_filter">
            <select id="duration" name="duration" class="custom-select">
                <?php
                foreach ($start_end_dates_select as $key => $value) {
                    ?>
                    <option value="<?php echo $key; ?>" <?php echo ($duration == $key) ? 'selected' : ''; ?>> <?php echo $value; ?></option>
                    <?php
                }
                ?>
            </select>
            <span style=" <?php echo ($duration == 'custom') ? '' : 'display:none'; ?>" class="show_none">
            <b><?php echo __('Choose date', RNOC_TEXT_DOMAIN) ?>:</b>
            <input type="text" name="daterange" id="date_range" value="<?php echo $selected_dates; ?>"/>
            </span>

        </div>
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

new CMB2_Field_Date_Range_Picker();
