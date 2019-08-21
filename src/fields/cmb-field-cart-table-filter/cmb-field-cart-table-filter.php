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
     * render cart table filter
     */
    public function render_cart_table_filter($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
        $cart_type = isset($_GET['cart_type']) ? $_GET['cart_type'] : 'all';
        $start_end_dates = $abandoned_cart->start_end_dates;
        $duration = (isset($_GET['duration'])) ? $_GET['duration'] : 'last_thirty';
        if ($duration != "custom") {
            $start_date = $start_end_dates[$duration]['start_date'];
            $end_date = $start_end_dates[$duration]['end_date'];
        } else if (isset($_GET['start']) && isset($_GET['end'])) {
            $start_date = $_GET['start'];
            $end_date = $_GET['start'];
        } else {
            $start_date = $start_end_dates['last_thirty']['start_date'];
            $end_date = $start_end_dates['last_thirty']['end_date'];
        }
        $date_arr = array(
            'start' => $start_date,
            'end' => $end_date,
            'duration' => $duration,
            'page_number' => 1
        );
        $url_arr = array(
            'page' => $abandoned_cart->admin->slug . '_abandoned_cart'
        );
        $url = admin_url('admin.php?' . http_build_query($url_arr));
        ?>
        <div class="abandoned_cart_filter float-left">
            <select name="bulk_action" id="bulk-action-select" class="custom-select" >
                <option value=""><?php echo __('Bulk Actions', RNOC_TEXT_DOMAIN); ?></option>
                <option value="delete_selected"><?php echo __('Delete Selected', RNOC_TEXT_DOMAIN); ?></option>
                <option value="empty_abandoned_cart_history"><?php echo __('Empty abandoned cart history', RNOC_TEXT_DOMAIN); ?></option>
                <!--<option value="empty_sent_mail_history"><?php /*echo __('Empty the sent mail history', RNOC_TEXT_DOMAIN); */ ?></option>-->
                <option value="empty_email_queue"><?php echo __('Empty E-mail queue', RNOC_TEXT_DOMAIN); ?></option>
            </select>
            <button type="button" id="do-bulk-action" class="wp-ui-filters active"
                    data-ajax="<?php echo admin_url('admin-ajax.php'); ?>"><?php echo __('Apply', RNOC_TEXT_DOMAIN); ?></button>
        </div>
        <div class="abandoned_cart_filter text-right">
            <label class="filter_by"><?php echo __('Filter by', RNOC_TEXT_DOMAIN) ?>
                <select name="cart_type" class="custom-select" id="cart-type-selection">
                    <option value="all" <?php echo ($cart_type == "all") ? 'selected' : ''; ?>><?php echo __('All Carts', RNOC_TEXT_DOMAIN); ?></option>
                    <option value="abandoned" <?php echo ($cart_type == "abandoned") ? 'selected' : ''; ?>><?php echo __('All abandoned Carts', RNOC_TEXT_DOMAIN); ?></option>
                    <option value="recoverable" <?php echo ($cart_type == "recoverable") ? 'selected' : ''; ?>><?php echo __('Recoverable Carts', RNOC_TEXT_DOMAIN); ?></option>
                    <option value="recovered" <?php echo ($cart_type == "recovered") ? 'selected' : ''; ?>><?php echo __('Recovered Carts', RNOC_TEXT_DOMAIN); ?></option>
                    <?php
                    $abandoned_cart_settings = $abandoned_cart->admin->getAdminSettings();
                    $is_tracking_enabled = (isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'])) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'] : 1;
                    if ($is_tracking_enabled) {
                        ?>
                        <option value="progress" <?php echo ($cart_type == "progress") ? 'selected' : ''; ?>><?php echo __('In-Progress Carts', RNOC_TEXT_DOMAIN); ?></option>
                        <?php
                    }
                    ?>
                    <option value="guest_cart" <?php echo ($cart_type == "guest_cart") ? 'selected' : ''; ?>><?php echo __('Guest Carts', RNOC_TEXT_DOMAIN); ?></option>
                    <option value="registered_cart" <?php echo ($cart_type == "registered_cart") ? 'selected' : ''; ?>><?php echo __('Registered Carts', RNOC_TEXT_DOMAIN); ?></option>
                </select>
            </label>
            <span class="filter_by"><?php echo __('Quick Filter'); ?>:</span>
            <input type="hidden" value="<?php echo $cart_type; ?>" id="cart_type">
            <a href="<?php echo $url . '&cart_type=all&' . http_build_query($date_arr) ?>"
               class="wp-ui-filters <?php echo ($cart_type == "all") ? 'active' : ''; ?>"><?php echo __('All Carts', RNOC_TEXT_DOMAIN); ?></a>
            <a href="<?php echo $url . '&cart_type=abandoned&' . http_build_query($date_arr) ?>"
               class="wp-ui-filters <?php echo ($cart_type == "abandoned") ? 'active' : ''; ?>"><?php echo __('All abandoned Carts', RNOC_TEXT_DOMAIN); ?></a>
            <a href="<?php echo $url . '&cart_type=recovered&' . http_build_query($date_arr) ?>"
               class="wp-ui-filters <?php echo ($cart_type == "recovered") ? 'active' : ''; ?>"><?php echo __('Recovered Carts', RNOC_TEXT_DOMAIN); ?></a>
            <?php
            $abandoned_cart_settings = $abandoned_cart->admin->getAdminSettings();
            $is_tracking_enabled = (isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'])) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'] : 1;
            if ($is_tracking_enabled) {
                ?>
                <a href="<?php echo $url . '&cart_type=progress&' . http_build_query($date_arr) ?>"
                   class="wp-ui-filters <?php echo ($cart_type == "progress") ? 'active' : ''; ?>"><?php echo __('In-Progress Carts', RNOC_TEXT_DOMAIN); ?></a>
                <?php
            }
            ?>
        </div>
        <?php
    }
}

new CMB2_Field_Cart_Table_Filter();