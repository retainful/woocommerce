<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Cart_Table_Filter
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
        add_filter('cmb2_render_cart_table_filter', array($this, 'render_cart_table_filter'), 10, 5);
    }

    /**
     *
     */
    public function render_cart_table_filter($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
        $start_end_dates = $abandoned_cart->start_end_dates;
        $duration = (isset($_GET['duration'])) ? $_GET['duration'] : 'last_seven';
        if ($duration != "custom") {
            $start_date = $start_end_dates[$duration]['start_date'];
            $end_date = $start_end_dates[$duration]['end_date'];
        } else if (isset($_GET['start']) && isset($_GET['end'])) {
            $start_date = $_GET['start'];
            $end_date = $_GET['start'];
        } else {
            $start_date = $start_end_dates['last_seven']['start_date'];
            $end_date = $start_end_dates['last_seven']['end_date'];
        }
        $date_arr = array(
            'start' => $start_date,
            'end' => $end_date
        );
        $url_arr = array(
            'page' => $abandoned_cart->admin->slug . '_abandoned_cart'
        );
        $url = admin_url('admin.php?' . http_build_query($url_arr));
        $cart_type = isset($_GET['cart_type']) ? $_GET['cart_type'] : 'all';
        ?>
        <div class="abandoned_cart_filter">
			<span class="filter_by">Filter by :</span>
            <input type="hidden" value="<?php echo $cart_type; ?>" id="cart_type">
            <a href="<?php echo $url . '&cart_type=all&' . http_build_query($date_arr) ?>"
               class="wp-ui-filters button-primary-first"><?php echo __('All Carts', RNOC_TEXT_DOMAIN); ?></a>
            <a href="<?php echo $url . '&cart_type=abandoned&' . http_build_query($date_arr) ?>"
               class="wp-ui-filters button-abandoned"><?php echo __('Abandoned Carts', RNOC_TEXT_DOMAIN); ?></a>
            <a href="<?php echo $url . '&cart_type=recovered&' . http_build_query($date_arr) ?>"
               class="wp-ui-filters button-recovered"><?php echo __('Recovered Carts', RNOC_TEXT_DOMAIN); ?></a>
            <?php
            $abandoned_cart_settings = $abandoned_cart->admin->getAdminSettings();
            $is_tracking_enabled = (isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'])) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'] : 1;
            if ($is_tracking_enabled) {
                ?>
                <a href="<?php echo $url . '&cart_type=progress&' . http_build_query($date_arr) ?>"
                   class="wp-ui-filters button-secondary-last"><?php echo __('In-Progress Carts', RNOC_TEXT_DOMAIN); ?></a>
                <?php
            }
            ?>
        </div>
        <?php
    }
}

new CMB2_Field_Cart_Table_Filter();
