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
        $base_currency = $abandoned_cart_obj->getBaseCurrency();
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
        $show_only = isset($_GET['show_only']) ? $_GET['show_only'] : 'all';
        $cart_type = isset($_GET['cart_type']) ? $_GET['cart_type'] : 'all';
        $page_number = (isset($_GET['page_number'])) ? ($_GET['page_number']) : 1;
        $start = ($page_number - 1) * $limit;
        $total_carts = $abandoned_cart_obj->getAbandonedCartsOfDate($start_date, $end_date, true, 0, 0, $cart_type, $show_only);
        $count = ($total_carts[0]->count) ? $total_carts[0]->count : 0;
        $cart_lists = $abandoned_cart_obj->getCartLists($start_date, $end_date, $start, $limit, $cart_type, $show_only);
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
        <table class="retainful_abandoned_table">
            <thead class="bg-light">
            <tr class="border-0">
                <th class="border-0" style="width: 40px;">
                    <input type="checkbox" name="select_all" id="select_all_abandoned_carts">
                </th>
                <th class="border-0" style="width: 40px;"><?php echo __('Id', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0"><?php echo __('Cart Status', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0"><?php echo __('Date', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0"><?php echo __('Customer / IP', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0"><?php echo __('Email', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0"><?php echo __('Cart Value', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0" style="width: 90px;"><?php echo __('Action', RNOC_TEXT_DOMAIN); ?></th>
            </tr>
            </thead>
            <tbody>
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
                            <input type="checkbox" name="delete_selected_carts[]" class="abandon-cart-list"
                                   value="<?php echo $carts->id ?>"/>
                        </td>
                        <td>
                            <?php echo $carts->id ?>
                        </td>
                        <?php
                        if ($carts->cart_is_recovered == 1) {
                            ?>
                            <td class="rcd-clr"><span
                                        class="status-dot rcd-bg mr-1"><?php echo __("Recovered", RNOC_TEXT_DOMAIN); ?></span>
                            </td>
                            <?php
                        } else if ($carts->cart_expiry > $current_time) {
                            ?>
                            <td class="in-prs-clr"><span
                                        class="status-dot in-prs-bg mr-1"><?php echo __("In Progress", RNOC_TEXT_DOMAIN); ?></span>
                            </td>
                            <?php
                        } else {
                            ?>
                            <td class="abd-clr"><span
                                        class="status-dot abd-bg mr-1"><?php echo __("Abandoned", RNOC_TEXT_DOMAIN); ?></span>
                            </td>
                            <?php
                        }
                        ?>
                        <td>
                            <?php
                            echo date('Y-m-d H:i A', $carts->cart_expiry)
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
                            <?php
                            if (is_numeric($carts->customer_key)) {
                                ?>
                                <span class="customer-type user_dt"><?php echo __('Registered', RNOC_TEXT_DOMAIN) ?></span>
                                <?php
                            } else {
                                ?>
                                <span class="customer-type guest_dt"><?php echo __('Guest', RNOC_TEXT_DOMAIN) ?></span>
                                <?php
                            }
                            ?>
                        </td>
                        <td class="email-section">
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
                            } else { ?>
                                <span style="color:#888888"><?php echo __('This is guest cart', RNOC_TEXT_DOMAIN); ?></span>
                                <?php
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            /*if ($carts->cart_total == NULL) {*/
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
                            /*} else {
                                $line_total = $carts->cart_total;
                            }*/
                            echo $abandoned_cart_obj->wc_functions->formatPrice($line_total, array('currency' => $carts->currency_code));
                            if ($base_currency !== $carts->currency_code && !empty($carts->currency_code) && !empty($base_currency)) {
                                $exchange_rate = $abandoned_cart_obj->getCurrencyRate($carts->currency_code);
                                $base_currency_price = $abandoned_cart_obj->convertToCurrency($line_total, $exchange_rate);
                                $base_currency_price = $abandoned_cart_obj->wc_functions->formatPrice($base_currency_price);
                                if (!empty($base_currency_price)) {
                                    echo "&nbsp;<b>({$base_currency_price})</b>";
                                }
                            }
                            ?>
                        </td>
                        <td class="action-section">
                            <?php
                            if ($carts->cart_is_recovered == 1) {
                                ?>
                                <a class="btn_plg btn-view"
                                   href="<?php echo get_edit_post_link($carts->order_id); ?>"
                                   target="_blank"><span
                                            class="dashicons_action dashicons dashicons-visibility"></span></a>
                                <?php
                            } else {
                                $view_cart_vars = array(
                                    'action' => 'view_abandoned_cart',
                                    'cart_id' => $carts->id
                                );
                                $view_cart_url = admin_url('admin-ajax.php?' . http_build_query($view_cart_vars));
                                ?>
                                <a class="btn_plg btn-view view-cart" href="<?php echo $view_cart_url; ?>"><span
                                            class="dashicons_action dashicons dashicons-visibility"></span></a>
                                <?php
                            }
                            ?>
                            <a class="btn_plg btn-danger remove-cart-btn" href="javascript:;"
                               data-ajax="<?php echo admin_url('admin-ajax.php'); ?>"
                               data-cart="<?php echo $carts->id ?>"><span
                                        class="dashicons_action dashicons dashicons-trash"></span></a>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="8">
                        <p><?php echo __('No carts found!', RNOC_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
        <?php
        if (!empty($cart_lists)) {
            ?>
            <div class="table_data_dp">
                <?php
                echo $pagination->createLinks();
                ?>
            </div>
            <?php
        }
        ?>
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

    /**±
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_abandoned_cart_dashboard_asset_path', plugins_url('', __FILE__));
        wp_enqueue_style('abandoned-cart-dashboard', $asset_path . '/css/main.css', array(), RNOC_VERSION);
        wp_enqueue_script('abandoned-cart-cart-fancybox-js', $asset_path . '/js/fancybox.min.js', array(), RNOC_VERSION);
        wp_enqueue_script('abandoned-cart-cart-fancybox-init-js', $asset_path . '/js/main.js', array(), RNOC_VERSION);
        wp_enqueue_style('abandoned-cart-cart-fancybox-css', $asset_path . '/css/fancybox.min.css', array(), RNOC_VERSION);
    }
}

$cmb2_field_abandon_cart_lists = new CMB2_Field_Abandoned_Cart_Lists();
