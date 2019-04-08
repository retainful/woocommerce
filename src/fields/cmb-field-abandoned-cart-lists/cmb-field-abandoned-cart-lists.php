<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Abandoned_Cart_Lists
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
        add_filter('cmb2_render_abandoned_cart_lists', array($this, 'render_abandoned_cart_lists'), 10, 5);
    }

    /**
     *
     */
    public function render_abandoned_cart_lists($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $this->setupAdminScripts();
        $abandoned_cart_obj = new \Rnoc\Retainful\AbandonedCart();
        $settings = new \Rnoc\Retainful\Admin\Settings();
        $start_end_dates = $abandoned_cart_obj->start_end_dates;
        $limit = 20;
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
        $cart_type = isset($_GET['cart_type']) ? $_GET['cart_type'] : 'all';
        $page_number = (isset($_GET['page_number'])) ? ($_GET['page_number']) : 1;
        $start = ($page_number - 1) * $limit;
        $total_carts = $abandoned_cart_obj->getAbandonedCartsOfDate($start_date, $end_date, true, 0, 0, $cart_type);
        $count = ($total_carts[0]->count) ? $total_carts[0]->count : 0;
        $cart_lists = $abandoned_cart_obj->getCartLists($start_date, $end_date, $start, $limit, $cart_type);
        $url_arr = array(
            'page' => $settings->slug . '_abandoned_cart',
            'start' => $start_date,
            'end' => $end_date,
            'cart_type' => $cart_type,
            'duration' => $duration
        );
        $url = admin_url('admin.php?' . http_build_query($url_arr));
        $pagConfig = array(
            'baseURL' => $url,
            'totalRows' => $count,
            'perPage' => $limit
        );
        $pagination = new \Rnoc\Retainful\Library\Pagination($pagConfig);
        $current_time = current_time('timestamp');
        ?>
        <table width="100%" class="wp-list-table fixed striped">
            <tr>
                <td width="20"><strong><?php echo __('Id', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Cart Status', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Cart Expired', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Customer / IP', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Customer Type', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Email', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Cart Value', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Action', RNOC_TEXT_DOMAIN); ?></strong></td>
            </tr>
            <?php
            if (!empty($cart_lists)) {
                global $wpdb;
                foreach ($cart_lists as $carts) {
                    $query_guest = "SELECT billing_first_name, billing_last_name, email_id FROM `" . $abandoned_cart_obj->guest_cart_history_table . "` WHERE session_id = %s";
                    $results_guest = $wpdb->get_row($wpdb->prepare($query_guest, $carts->customer_key), OBJECT);
                    $guest_email = $guest_first_name = $guest_last_name = '';
                    if (!empty($results_guest)) {
                        $guest_email = $results_guest->email_id;
                        $guest_first_name = $results_guest->billing_first_name;
                        $guest_last_name = $results_guest->billing_last_name;
                    }
                    ?>
                    <tr>
                        <td>
                            <?php echo $carts->id ?>
                        </td>
                        <td>
                            <?php
                            if ($carts->cart_is_recovered == 1) {
                                echo '<span style=\'color: green\'>' . __("Recovered", RNOC_TEXT_DOMAIN) . '</span>';
                            } else if ($carts->cart_expiry > $current_time) {
                                echo '<span style=\'color: orange\'>' . __("In Progress", RNOC_TEXT_DOMAIN) . '</span>';
                            } else {
                                echo '<span style=\'color: red\'>' . __("Abandoned", RNOC_TEXT_DOMAIN) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            echo date('Y-m-d', $carts->cart_expiry)
                            ?>
                        </td>
                        <td>
                            <?php
                            $user = get_userdata($carts->customer_key);
                            if ($user) {
                                echo $user->first_name . ' ' . $user->last_name;
                            } else if ($carts->order_id) {
                                $meta = get_post_meta($carts->order_id);
                                $result = '';
                                if (isset($meta['_billing_first_name'])) {
                                    $first_name = $meta['_billing_first_name'];
                                    $result = $result . $first_name[0];
                                }
                                if (isset($meta['_billing_last_name'])) {
                                    $last_name = $meta['_billing_last_name'];
                                    $result = $result . ' ' . $last_name[0];
                                }
                                echo $result;
                            } else {
                                if (!empty($guest_first_name) || !empty($guest_last_name)) {
                                    if (!empty($guest_first_name)) {
                                        echo $guest_first_name;
                                    }
                                    if (!empty($guest_last_name)) {
                                        echo ' ' . $guest_last_name;
                                    }
                                } else {
                                    echo $carts->ip_address;
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if (is_numeric($carts->customer_key)) {
                                echo __('REGISTERED', RNOC_TEXT_DOMAIN);
                            } else {
                                echo __('GUEST', RNOC_TEXT_DOMAIN);
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $user = get_userdata($carts->customer_key);
                            if ($user) {
                                echo '<a href="mailto:' . $user->user_email . '">' . $user->user_email . '</a>';
                            } else if ($carts->order_id) {
                                $meta = get_post_meta($carts->order_id);
                                if (isset($meta['_billing_email'])) {
                                    $email = $meta['_billing_email'];
                                    echo '<a href="mailto:' . $email[0] . '">' . $email[0] . '</a>';
                                }
                            } elseif (!empty($guest_email)) {
                                echo '<a href="mailto:' . $guest_email . '">' . $guest_email . '</a>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($carts->cart_total == NULL) {
                                $product_details = json_decode($carts->cart_contents);
                                $line_total = 0;
                                if (false != $product_details && is_object($product_details) && count(get_object_vars($product_details)) > 0) {
                                    foreach ($product_details as $k => $v) {
                                        if ($v->line_subtotal_tax != 0 && $v->line_subtotal_tax > 0) {
                                            $line_total = $line_total + $v->line_total + $v->line_subtotal_tax;
                                        } else {
                                            $line_total = $line_total + $v->line_total;
                                        }
                                    }
                                }
                            } else {
                                $line_total = $carts->cart_total;
                            }
                            echo $abandoned_cart_obj->wc_functions->formatPrice($line_total);
                            ?>
                        </td>
                        <td>
                            <a class="button button-red remove-cart-btn"
                               data-ajax="<?php echo admin_url('admin-ajax.php'); ?>"
                               data-cart="<?php echo $carts->id ?>"><?php echo __('Delete', RNOC_TEXT_DOMAIN) ?></a>
                            <?php
                            if ($carts->cart_is_recovered == 1) {
                                ?>
                                <a href="<?php echo get_edit_post_link($carts->order_id); ?>"
                                   target="_blank"
                                   class="button button-green"><?php echo __('View Order', RNOC_TEXT_DOMAIN); ?></a>
                                <?php
                            } else {
                                $view_cart_vars = array(
                                    'action' => 'view_abandoned_cart',
                                    'cart_id' => $carts->id
                                );
                                $view_cart_url = admin_url('admin-ajax.php?' . http_build_query($view_cart_vars));
                                ?>
                                <a class="button view-cart"
                                   href="<?php echo $view_cart_url; ?>"><?php echo __('View Cart', RNOC_TEXT_DOMAIN) ?></a>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="6">
                        <p><?php echo __('No carts found!', RNOC_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <?php
            }
            ?>
            <tr>
                <td><strong><?php echo __('Id', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Cart Status', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Cart Expired', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Customer / IP', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Customer Type', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Email', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Cart Value', RNOC_TEXT_DOMAIN); ?></strong></td>
                <td><strong><?php echo __('Action', RNOC_TEXT_DOMAIN); ?></strong></td>
            </tr>
            <tr>
                <td colspan="6" align="right">
                    <?php
                    echo $pagination->createLinks();
                    ?>
                </td>
            </tr>
        </table>
        <style>
            #submit-cmb {
                display: none;
            }
        </style>
        <script>
            var no_ajax = true;
            var page_url = '<?php echo $url; ?>';
            var page_number = '<?php echo $page_number; ?>';
        </script>
        <?php
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_abandoned_cart_dashboard_asset_path', plugins_url('', __FILE__));
        wp_enqueue_style('abandoned-cart-dashboard', $asset_path . '/css/main.css');
        wp_enqueue_script('abandoned-cart-cart-fancybox-js', $asset_path . '/js/fancybox.min.js');
        wp_enqueue_script('abandoned-cart-cart-fancybox-init-js', $asset_path . '/js/main.js');
        wp_enqueue_style('abandoned-cart-cart-fancybox-css', $asset_path . '/css/fancybox.min.css');
    }
}

$cmb2_field_abandon_cart_lists = new CMB2_Field_Abandoned_Cart_Lists();
