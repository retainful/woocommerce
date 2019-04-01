<?php

namespace Rnoc\Retainful;

use Rnoc\Retainful\Admin\Settings;

class AbandonedCart
{
    public $wc_functions, $admin, $cart_history_table, $email_history_table, $guest_cart_history_table, $total_order_amount, $total_abandoned_cart_count, $total_recover_amount, $recovered_item;
    protected static $applied_coupons = NULL;
    public $start_end_dates = array();

    function __construct()
    {
        global $wpdb;
        $this->wc_functions = new WcFunctions();
        $this->admin = new Settings();
        $this->start_end_dates = array('yesterday' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp') - 7 * 24 * 60 * 60))),
            'today' => array('start_date' => date("Y/m/d", (current_time('timestamp'))), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_seven' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 7 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_fifteen' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 15 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_thirty' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 30 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_ninety' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 90 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_year_days' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 365 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp'))))
        );
        $this->cart_history_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_cart_history';
        $this->guest_cart_history_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'guest_abandoned_cart_history';
        $this->email_history_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_sent_history';
    }

    /**
     * Save guest data for further processing
     */
    function saveGuestData()
    {
        if (!is_user_logged_in()) {
            $user_session_id = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'user_session_id');
            if (!empty($user_session_id) && isset($_POST['billing_email'])) {
                global $wpdb, $woocommerce;
                //Post details
                $billing_first_name = (isset($_POST['billing_first_name'])) ? sanitize_text_field($_POST['billing_first_name']) : '';
                $billing_last_name = (isset($_POST['billing_last_name'])) ? sanitize_text_field($_POST['billing_last_name']) : '';
                $billing_email = $_POST['billing_email'];
                $billing_zipcode = (isset($_POST['billing_postcode'])) ? sanitize_text_field($_POST['billing_postcode']) : '';
                $shipping_zipcode = (isset($_POST['shipping_postcode'])) ? sanitize_text_field($_POST['shipping_postcode']) : '';
                $shipping_charges = $woocommerce->cart->shipping_total;
                //Check the details already found
                $query = "SELECT * FROM `" . $this->guest_cart_history_table . "` WHERE session_id = %s";
                $results = $wpdb->get_results($wpdb->prepare($query, $user_session_id));
                if (!empty($results) && $results[0]->id) {
                    $guest_details_id = $results[0]->id;
                    $query_update = "UPDATE `" . $this->cart_history_table . "` SET billing_first_name=%s, billing_last_name=%s, email_id=%s, billing_zipcode=%s,shipping_zipcode=%s,shipping_charges=%s,session_id=%s WHERE id=%d";
                    $wpdb->query($wpdb->prepare($query_update, $billing_first_name, $billing_last_name, $billing_email, $billing_zipcode, $shipping_zipcode, $shipping_charges, $user_session_id, $guest_details_id));
                } else {
                    $insert_guest = "INSERT INTO `" . $this->guest_cart_history_table . "`( billing_first_name, billing_last_name, email_id, billing_zipcode, shipping_zipcode, shipping_charges, session_id ) VALUES ( %s,%s,%s,%s,%s,%s,%s)";
                    $wpdb->query($wpdb->prepare($insert_guest, $billing_first_name, $billing_last_name, $billing_email, $billing_zipcode, $shipping_zipcode, $shipping_charges, $user_session_id));
                }
            }
        }
    }

    /**
     * Generate unique Key
     * @return string
     */
    function generateUniqueKey()
    {
        return md5(microtime() . rand());
    }

    /**
     * Capture the cart and insert the information of the cart into DataBase.
     */
    function userCartUpdated()
    {
        global $wpdb;
        $abandoned_cart_settings = $this->admin->getAbandonedCartSettings();
        $current_time = current_time('timestamp');
        $cut_off_time_settings = isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time']) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time'] : 60;
        $cart_cut_off_time = intval($cut_off_time_settings) * 60;
        $cart_abandoned_on = $current_time + $cart_cut_off_time;
        $cart_ignored = 0;
        $recovered_cart = 0;
        //for logged-in users
        $cart['cart'] = $this->wc_functions->getSessionCart();
        $user_session_id = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'user_session_id');
        if (!empty($cart['cart'])) {
            $user_cart_info = json_encode($cart);
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $user_type = "REGISTERED";
                if (empty($user_session_id)) {
                    $unique_session_id = $this->generateUniqueKey();
                    $insert_query = "INSERT INTO `" . $this->cart_history_table . "` ( user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored,recovered_cart, user_type, session_id ) VALUES ( %d, %s, %d, %s, %d, %s, %s )";
                    $wpdb->query($wpdb->prepare($insert_query, $user_id, $user_cart_info, $cart_abandoned_on, $cart_ignored, $recovered_cart, $user_type, $unique_session_id));
                    $abandoned_cart_id = $wpdb->insert_id;
                    $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id', $abandoned_cart_id);
                    $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'user_session_id', $unique_session_id);
                } else {
                    $query = "SELECT * FROM `" . $this->cart_history_table . "` WHERE cart_ignored = %s AND recovered_cart = %d AND session_id=%s";
                    $results = $wpdb->get_row($wpdb->prepare($query, $cart_ignored, $recovered_cart, $user_session_id));
                    if (empty($results)) {
                        $recovery_session = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'user_recovered');
                        if (empty($recovery_session)) {
                            $unique_session_id = $this->generateUniqueKey();
                            $insert_query = "INSERT INTO `" . $this->cart_history_table . "` ( user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored,recovered_cart, user_type, session_id ) VALUES ( %d, %s, %d, %s, %d, %s, %s )";
                            $wpdb->query($wpdb->prepare($insert_query, $user_id, $user_cart_info, $cart_abandoned_on, $cart_ignored, $recovered_cart, $user_type, $unique_session_id));
                            $abandoned_cart_id = $wpdb->insert_id;
                            $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id', $abandoned_cart_id);
                            $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'user_session_id', $unique_session_id);
                        } else {
                            $query_update = "UPDATE `" . $this->cart_history_table . "` SET user_id=%d, abandoned_cart_info=%s,  user_type=%s WHERE session_id=%s";
                            $wpdb->query($wpdb->prepare($query_update, $user_id, $user_cart_info, $user_type, $user_session_id));
                        }
                    } elseif (isset($results->abandoned_cart_time) && $results->abandoned_cart_time > $current_time) {
                        $query_update = "UPDATE `" . $this->cart_history_table . "` SET user_id=%d, abandoned_cart_info=%s, user_type=%s WHERE session_id=%s";
                        $wpdb->query($wpdb->prepare($query_update, $user_id, $user_cart_info, $user_type, $user_session_id));
                    }
                }
            } else {
                //For guest users
                $user_id = 0;
                $user_type = 'GUEST';
                //Todo : set track from cart page
                $track_guest_user_cart_from_cart = isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_guest_cart_from_cart_page']) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_guest_cart_from_cart_page'] : 1;
                if (empty($user_session_id)) {
                    $unique_session_id = $this->generateUniqueKey();
                    $insert_query = "INSERT INTO `" . $this->cart_history_table . "` ( user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored,recovered_cart, user_type, session_id ) VALUES ( %d, %s, %d, %s, %d, %s, %s )";
                    $wpdb->query($wpdb->prepare($insert_query, $user_id, $user_cart_info, $cart_abandoned_on, $cart_ignored, $recovered_cart, $user_type, $unique_session_id));
                    $abandoned_cart_id = $wpdb->insert_id;
                    $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id', $abandoned_cart_id);
                    $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'user_session_id', $unique_session_id);
                } else {
                    $query = "SELECT * FROM `" . $this->cart_history_table . "` WHERE cart_ignored = %s AND recovered_cart = %d AND session_id=%s";
                    $results = $wpdb->get_row($wpdb->prepare($query, $cart_ignored, $recovered_cart, $user_session_id));
                    if (empty($results)) {
                        $recovery_session = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'user_recovered');
                        if (empty($recovery_session)) {
                            $unique_session_id = $this->generateUniqueKey();
                            $insert_query = "INSERT INTO `" . $this->cart_history_table . "` ( user_id, abandoned_cart_info, abandoned_cart_time, cart_ignored,recovered_cart, user_type, session_id ) VALUES ( %d, %s, %d, %s, %d, %s, %s )";
                            $wpdb->query($wpdb->prepare($insert_query, $user_id, $user_cart_info, $cart_abandoned_on, $cart_ignored, $recovered_cart, $user_type, $unique_session_id));
                            $abandoned_cart_id = $wpdb->insert_id;
                            $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id', $abandoned_cart_id);
                            $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'user_session_id', $unique_session_id);
                        } else {
                            $query_update = "UPDATE `" . $this->cart_history_table . "` SET user_id=%d, abandoned_cart_info=%s,  user_type=%s WHERE session_id=%s";
                            $wpdb->query($wpdb->prepare($query_update, $user_id, $user_cart_info, $user_type, $user_session_id));
                        }
                    } elseif (isset($results->abandoned_cart_time) && $results->abandoned_cart_time > $current_time) {
                        $query_update = "UPDATE `" . $this->cart_history_table . "` SET user_id=%d, abandoned_cart_info=%s, user_type=%s WHERE session_id=%s";
                        $wpdb->query($wpdb->prepare($query_update, $user_id, $user_cart_info, $user_type, $user_session_id));
                    }
                }
            }
        } else if (empty($cart['cart']) && $user_session_id != "") {
            $wpdb->delete($this->cart_history_table, array('session_id' => $user_session_id));
            $this->wc_functions->removePhpSession(RNOC_PLUGIN_PREFIX . 'user_session_id');
        }
    }

    /**
     * Automatically send customer recovery email
     * This function will call in every 5 minutes
     */
    function sendAbandonedCartEmails()
    {
        $email_templates = $this->admin->getEmailTemplates();
        if (isset($email_templates[RNOC_PLUGIN_PREFIX . 'templates_list']) && !empty($email_templates[RNOC_PLUGIN_PREFIX . 'templates_list'])) {
            foreach ($email_templates[RNOC_PLUGIN_PREFIX . 'templates_list'] as $template_id => $template) {
                if (isset($template[RNOC_PLUGIN_PREFIX . 'template_sent_after']) && $template[RNOC_PLUGIN_PREFIX . 'template_sent_after'] && isset($template[RNOC_PLUGIN_PREFIX . 'template_active']) && $template[RNOC_PLUGIN_PREFIX . 'template_active'] && isset($template[RNOC_PLUGIN_PREFIX . 'template_body']) && !empty($template[RNOC_PLUGIN_PREFIX . 'template_body'])) {
                    global $wpdb;
                    $current_time = current_time('timestamp');
                    $time_to_send_template_after = $template[RNOC_PLUGIN_PREFIX . 'template_sent_after'] * 3600;
                    $cart_time = $current_time - $time_to_send_template_after;
                    $to_remain_query = "SELECT * FROM `" . $this->cart_history_table . "` WHERE  abandoned_cart_time < %d AND recovered_cart = %d";
                    $to_remain_histories = $wpdb->get_results($wpdb->prepare($to_remain_query, $cart_time, 0));
                    if (!empty($to_remain_histories)) {
                        foreach ($to_remain_histories as $history) {
                            if (isset($history->abandoned_cart_info)) {
                                $abandoned_cart_info = json_decode($history->abandoned_cart_info);
                                $cart_details = $abandoned_cart_info->cart;
                                if (!empty($cart_details)) {
                                    $history_id = $history->id;
                                    //Check each email template is sent or not
                                    $email_sent_history_query = "SELECT * FROM `" . $this->email_history_table . "` WHERE  template_id = %s AND abandoned_order_id = %d";
                                    $email_sent_history = $wpdb->get_results($wpdb->prepare($email_sent_history_query, $template_id, $history_id));
                                    if (empty($email_sent_history)) {
                                        $user_email = $user_first_name = $user_last_name = '';
                                        $session_id = $history->session_id;
                                        if ($history->user_type == "GUEST") {
                                            $query_guest = "SELECT billing_first_name, billing_last_name, email_id FROM `" . $this->guest_cart_history_table . "` WHERE session_id = %s";
                                            $results_guest = $wpdb->get_results($wpdb->prepare($query_guest, $session_id));
                                            if (!empty($results_guest) && isset($results_guest[0]->email_id)) {
                                                $user_email = $results_guest[0]->email_id;
                                                $user_first_name = $results_guest[0]->billing_first_name;
                                                $user_last_name = $results_guest[0]->billing_last_name;
                                            }
                                        } else {
                                            if ($history->user_id) {
                                                $user_email = get_user_meta($history->user_id, 'billing_email', true);
                                                $user_first_name = get_user_meta($history->user_id, 'billing_first_name', true);
                                                $user_last_name = get_user_meta($history->user_id, 'billing_last_name', true);
                                                $user_data = get_userdata($history->user_id);
                                                if (isset($user_first_name) && $user_first_name == "") {
                                                    if (isset($user_data->display_name)) {
                                                        $user_first_name = $user_data->display_name;
                                                    }
                                                }
                                                if (isset($user_email) && $user_email == "") {
                                                    if (isset($user_data->user_email)) {
                                                        $user_email = $user_data->user_email;
                                                    }
                                                }
                                            }
                                        }
                                        //Process only if user email found
                                        if (!empty($user_email)) {
                                            $customer_name = $user_first_name . ' ' . $user_last_name;
                                            $email_subject = $template[RNOC_PLUGIN_PREFIX . 'template_subject'];
                                            if (empty($email_subject)) {
                                                $email_subject = 'Hey {{customer_name}} You left something in your cart';
                                            }
                                            $email_subject = str_replace('{{customer_name}}', $customer_name, $email_subject);
                                            $email_body = $template[RNOC_PLUGIN_PREFIX . 'template_body'];
                                            $abandoned_cart_info = json_decode($history->abandoned_cart_info);
                                            $cart_details = $abandoned_cart_info->cart;
                                            $cart_total = $item_subtotal = $item_total = 0;
                                            $sub_line_prod_name = $cart_line_items = '';
                                            foreach ($cart_details as $key => $cart) {
                                                $quantity_total = $cart->quantity;
                                                $product_id = $cart->product_id;
                                                $prod_name = get_post($product_id);
                                                $product_name = $prod_name->post_title;
                                                if ($sub_line_prod_name == '') {
                                                    $sub_line_prod_name = $product_name;
                                                }
                                                // Item subtotal is calculated as product total including taxes
                                                if ($cart->line_tax != 0 && $cart->line_tax > 0) {
                                                    $item_subtotal = $item_subtotal + $cart->line_total + $cart->line_tax;
                                                } else {
                                                    $item_subtotal = $item_subtotal + $cart->line_total;
                                                }
                                                //  Line total
                                                $item_total = $item_subtotal;
                                                $item_subtotal = $item_subtotal / $quantity_total;
                                                $item_total_display = $this->wc_functions->formatPrice($item_total);
                                                $item_subtotal = $this->wc_functions->formatPrice($item_subtotal);
                                                //$product = $this->wc_functions->getProduct($product_id);
                                                //$image_url = $this->wc_functions->getProductImage($product);
                                                $image_url = wp_get_attachment_url(get_post_thumbnail_id($product_id));
                                                if (isset($cart->variation_id) && '' != $cart->variation_id) {
                                                    $variation_id = $cart->variation_id;
                                                    $variation = $this->wc_functions->getProduct($variation_id);
                                                    $product_name = $this->wc_functions->getProductName($variation);
                                                }
                                                $cart_line_items .= '
                                            <tr align="center">
                                                <td>
                                                    <img src="' . $image_url . '" width="100px" />
                                                </td>
                                                <td>' . $product_name . '</td>
                                                <td>' . $quantity_total . '</td>
                                                <td>' . $item_subtotal . '</td>
                                                <td>' . $item_total_display . '</td>
                                            </tr>';
                                                $cart_total += $item_total;
                                                $item_subtotal = $item_total = 0;
                                            }
                                            $cart_total = $this->wc_functions->formatPrice($cart_total);
                                            $cart_html = '
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <thead>
                                            <tr>
                                                <th>' . __("Item", RNOC_TEXT_DOMAIN) . '</th>
                                                <th>' . __("Name", RNOC_TEXT_DOMAIN) . '</th>
                                                <th>' . __("Quantity", RNOC_TEXT_DOMAIN) . '</th>
                                                <th>' . __("Price", RNOC_TEXT_DOMAIN) . '</th>
                                                <th>' . __("Line Subtotal", RNOC_TEXT_DOMAIN) . '</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            ' . $cart_line_items . '
                                            </tbody>
                                            <tfoot>
                                            <tr align=“center”>
                                                <td colspan=“3"></td>
                                                <th>' . __('Cart Total', RNOC_TEXT_DOMAIN) . ':</th>
                                                <td>' . $cart_total . '</td>
                                            </tr>
                                            </tfoot>
                                        </table>
                                        ';
                                            //Log about emil sent
                                            $email_sent_query = "INSERT INTO `" . $this->email_history_table . "` ( template_id, abandoned_order_id, sent_time, sent_email_id ) VALUES ( %s, %s, '" . current_time('mysql') . "', %s )";
                                            $wpdb->query($wpdb->prepare($email_sent_query, $template_id, $history_id, $user_email));
                                            $site_url = site_url();
                                            $cart_page_link = $this->wc_functions->getCartUrl();
                                            $need_to_encode = array(
                                                'url' => $cart_page_link,
                                                'email_sent' => $wpdb->insert_id,
                                                'abandoned_cart_id' => $history_id,
                                                'session_id' => $history->session_id
                                            );
                                            $encoding_cart = http_build_query($need_to_encode);
                                            $validate_cart = $this->encryptValidate($encoding_cart);
                                            $cart_recovery_link = $site_url . '/?retainful_cart_action=recover&validate=' . $validate_cart;
                                            $replace = array(
                                                'site_url' => $site_url,
                                                'cart_recovery_link' => $cart_recovery_link,
                                                'user_cart' => $cart_html,
                                                'site_footer' => '&copy; ' . date('Y') . ' ' . get_bloginfo('name') . ' All rights reserved.'
                                            );
                                            foreach ($replace as $short_code => $short_code_value) {
                                                $email_body = str_replace('{{' . $short_code . '}}', $short_code_value, $email_body);
                                            }
                                            $from_name = (isset($email_templates[RNOC_PLUGIN_PREFIX . 'email_from_name'])) ? $email_templates[RNOC_PLUGIN_PREFIX . 'email_from_name'] : 'Admin';
                                            $admin_email = get_option('admin_email');
                                            $from_address = (isset($email_templates[RNOC_PLUGIN_PREFIX . 'email_from_address'])) ? $email_templates[RNOC_PLUGIN_PREFIX . 'email_from_address'] : $admin_email;
                                            $replay_address = (isset($email_templates[RNOC_PLUGIN_PREFIX . 'email_reply_address'])) ? $email_templates[RNOC_PLUGIN_PREFIX . 'email_reply_address'] : $admin_email;
                                            //Prepare for sending emails
                                            $headers = "From: " . $from_name . " <" . $from_address . ">" . "\r\n";
                                            $headers .= "Content-Type: text/html" . "\r\n";
                                            $headers .= "Reply-To:  " . $replay_address . " " . "\r\n";
                                            //Send mail
                                            wc_mail($user_email, $email_subject, $email_body, $headers);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Encrypt the key
     * @param $key
     * @return string
     */
    function encryptValidate($key)
    {
        $key = base64_encode($key);
        $key = str_rot13($key);
        return urlencode($key);
    }

    /**
     * Decrypt the key
     * @param $key
     * @return string
     */
    function decryptValidate($key)
    {
        $key = urldecode($key);
        $key = str_rot13($key);
        return urldecode(base64_decode($key));
    }

    /**
     * Register new Cron timings
     * @param $schedules
     * @return mixed
     */
    function registerNewCronType($schedules)
    {
        $schedules['5_minutes_rnoc_woocommerce'] = array(
            'interval' => 300, // 5 minutes in seconds
            'display' => __('Once Every Five Minutes'),
        );
        return $schedules;
    }

    /**
     * recover cart info
     * @param $template
     * @return mixed
     */
    function recoverUserCart($template)
    {
        if (isset($_GET['retainful_cart_action']) && isset($_GET['validate'])) {
            if ($_GET['retainful_cart_action'] == 'recover' && !empty($_GET['validate'])) {
                global $wpdb;
                if (session_id() === '') {
                    //session has not started
                    session_start();
                }
                $cart_link = $this->decryptValidate($_GET['validate']);
                parse_str($cart_link);
                if (isset($url) && isset($abandoned_cart_id) && isset($session_id) && isset($email_sent)) {
                    $abandoned_cart_history_query = "SELECT abandoned_cart_info,user_id,user_type FROM `" . $this->cart_history_table . "` WHERE id = %d";
                    $abandoned_cart_history_results = $wpdb->get_results($wpdb->prepare($abandoned_cart_history_query, $abandoned_cart_id));
                    $user_id = 0;
                    if (isset($abandoned_cart_history_results) && count($abandoned_cart_history_results) > 0) {
                        $user_id = $abandoned_cart_history_results[0]->user_id;
                        $user_type = $abandoned_cart_history_results[0]->user_type;
                        $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id', $abandoned_cart_id);
                        $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'user_session_id', $session_id);
                        $this->wc_functions->setPhpSession(RNOC_PLUGIN_PREFIX . 'user_recovered', $email_sent);
                        //if guest
                        if ($user_type == "GUEST") {
                            $this->autoLoadUserCart($abandoned_cart_history_results[0]->abandoned_cart_info);
                        } else {
                            // if registered user
                            $user = wp_set_current_user($user_id);
                            $user_login = $user->data->user_login;
                            wp_set_auth_cookie($user_id);
                            wc_load_persistent_cart($user_login, $user);
                            //This will only autoload if user's current cart is empty
                            $this->autoLoadUserCart($abandoned_cart_history_results[0]->abandoned_cart_info);
                            do_action('wp_login', $user_login, $user);
                            if (isset($sign_in) && is_wp_error($sign_in)) {
                                echo $sign_in->get_error_message();
                                exit;
                            }
                        }
                    }
                    if (empty($user_id)) {
                        echo "Link expired";
                        die;
                    }
                    if ($email_sent > 0 && is_numeric($email_sent)) {
                        wp_redirect($url);
                    }
                }
            }
        }
        return $template;
    }

    /**
     * Save cart info to session
     * @param $cart_info
     */
    function autoLoadUserCart($cart_info)
    {
        global $woocommerce;
        $user_cart = $woocommerce->session->cart;
        if (empty($user_cart)) {
            $saved_cart = json_decode($cart_info, true);
            $c = array();
            $cart_contents_total = $cart_contents_weight = $cart_contents_count = $cart_contents_tax = $total = $subtotal = $subtotal_ex_tax = $tax_total = 0;
            if (count($saved_cart) > 0) {
                foreach ($saved_cart as $key => $value) {
                    foreach ($value as $a => $b) {
                        $c['product_id'] = $b['product_id'];
                        $c['variation_id'] = $b['variation_id'];
                        $c['variation'] = $b['variation'];
                        $c['quantity'] = $b['quantity'];
                        $product_id = $b['product_id'];
                        $c['data'] = $this->wc_functions->getProduct($product_id);
                        $c['line_total'] = $b['line_total'];
                        $c['line_tax'] = $cart_contents_tax;
                        $c['line_subtotal'] = $b['line_subtotal'];
                        $c['line_subtotal_tax'] = $cart_contents_tax;
                        $value_new[$a] = $c;
                        $cart_contents_total = $b['line_subtotal'] + $cart_contents_total;
                        $cart_contents_count = $cart_contents_count + $b['quantity'];
                        $total = $total + $b['line_total'];
                        $subtotal = $subtotal + $b['line_subtotal'];
                        $subtotal_ex_tax = $subtotal_ex_tax + $b['line_subtotal'];
                        $saved_cart_data[$key] = $value_new;
                    }
                }
                $woocommerce->session->cart = $saved_cart['cart'];
                $woocommerce->session->cart_contents_total = $cart_contents_total;
                $woocommerce->session->cart_contents_weight = $cart_contents_weight;
                $woocommerce->session->cart_contents_count = $cart_contents_count;
                $woocommerce->session->cart_contents_tax = $cart_contents_tax;
                $woocommerce->session->total = $total;
                $woocommerce->session->subtotal = $subtotal;
                $woocommerce->session->subtotal_ex_tax = $subtotal_ex_tax;
                $woocommerce->session->tax_total = $tax_total;
                $woocommerce->session->shipping_taxes = array();
                $woocommerce->session->taxes = array();
                $woocommerce->session->ac_customer = array();
                $woocommerce->cart->cart_contents = $saved_cart['cart'];
                $woocommerce->cart->cart_contents_total = $cart_contents_total;
                $woocommerce->cart->cart_contents_weight = $cart_contents_weight;
                $woocommerce->cart->cart_contents_count = $cart_contents_count;
                $woocommerce->cart->cart_contents_tax = $cart_contents_tax;
                $woocommerce->cart->total = $total;
                $woocommerce->cart->subtotal = $subtotal;
                $woocommerce->cart->subtotal_ex_tax = $subtotal_ex_tax;
                $woocommerce->cart->tax_total = $tax_total;

            }
        }
    }

    function purchaseComplete()
    {

    }


    /**
     * It will check the WooCommerce order status. If the order status is pending or failed the we will keep that cart record
     * as an abandoned cart.
     * It will be executed after order placed.
     * @param $order_status
     * @param $order_id
     * @return mixed
     */
    function afterOrderComplete($order_status, $order_id)
    {
        if ('pending' != $order_status && 'failed' != $order_status) {
            global $wpdb;
            $order = $this->wc_functions->getOrder($order_id);
            $get_abandoned_id_of_order = get_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', true);
            $get_sent_email_id_of_order = get_post_meta($order_id, 'woocommerce_retainful_recover_order_placed_sent_id', true);
            /*
             * Here, in this condition we are checking that if abandoned cart id has any record for the reminder
             * email is sent or not.
             * If the reminder email is sent to the abandoned cart id the mark that cart as a recovered.
             */
            if (isset($get_sent_email_id_of_order) && '' != $get_sent_email_id_of_order) {
                $query_order = "UPDATE $this->cart_history_table SET recovered_cart = '" . $order_id . "', cart_ignored = '1' WHERE id = '" . $get_abandoned_id_of_order . "' ";
                $wpdb->query($query_order);
                $this->wc_functions->setOrderNote($order, __('This order was abandoned & subsequently recovered.', RNOC_TEXT_DOMAIN));
                delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', $get_abandoned_id_of_order);
                delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed_sent_id', $get_sent_email_id_of_order);
            } else if (isset($get_abandoned_id_of_order) && '' != $get_abandoned_id_of_order) {
                /*
                 * If the recover email has not sent then we will delete the abandoned cart data.
                 */
                $get_abandoned_cart_user_id_query = "SELECT user_id FROM  $this->cart_history_table  WHERE id = %d ";
                $get_abandoned_cart_user_id_results = $wpdb->get_results($wpdb->prepare($get_abandoned_cart_user_id_query, $get_abandoned_id_of_order));
                if (count($get_abandoned_cart_user_id_results) > 0) {
                    $user_id = $get_abandoned_cart_user_id_results[0]->user_id;
                    if ($user_id >= 63000000) {
                        $wpdb->delete($this->guest_cart_history_table, array('id' => $user_id));
                    }
                    $wpdb->delete($this->cart_history_table, array('id' => $get_abandoned_id_of_order));
                    delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', $get_abandoned_id_of_order);
                }
            }
        }
        return $order_status;
    }

    /**
     * When customer clicks on the "Place Order" button on the checkout page, it will identify if we need to keep that cart or
     * delete it.
     * @param $order_id
     */
    function orderPlaced($order_id)
    {
        $abandoned_order_id = $this->wc_functions->getPhpSession('rnoc_email_sent_id');
        if ($abandoned_order_id != '') {
            $account_password_check = 'no';
            global $wpdb;
            //if user becomes the registered user
            if (isset($_POST['account_password']) && '' != $_POST['account_password']) {
                $abandoned_cart_id_new_user = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id');
                $user_id_of_guest = $this->wc_functions->getPhpSession('rnoc_user_id');
                //delete the guest record. As it become the logged in user
                $get_abandoned_cart_id_guest_results = array();
                if (isset($user_id_of_guest) && '' != $user_id_of_guest) {
                    $get_abandoned_cart_id_guest_query = "SELECT id FROM `" . $this->cart_history_table . "` WHERE user_id = %d ORDER BY id DESC";
                    $get_abandoned_cart_id_guest_results = $wpdb->get_results($wpdb->prepare($get_abandoned_cart_id_guest_query, $user_id_of_guest));
                }
                if (is_array($get_abandoned_cart_id_guest_results) && count($get_abandoned_cart_id_guest_results) > 0) {
                    $abandoned_order_id_of_guest = $get_abandoned_cart_id_guest_results[0]->id;
                    $wpdb->delete($this->cart_history_table, array('id' => $abandoned_order_id_of_guest));
                }
                if (isset($abandoned_cart_id_new_user) && '' != $abandoned_cart_id_new_user) {
                    //it is the new registered users cart id
                    $wpdb->delete($this->cart_history_table, array('id' => $abandoned_cart_id_new_user));
                }
                $account_password_check = 'yes';
            }

            $create_account = 'no';
            //if user becomes the regisetred user
            if (isset($_POST['createaccount']) && $_POST['createaccount'] != '' && 'no' == $account_password_check) {
                $abandoned_cart_id_new_user = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id');
                $user_id_of_guest = '';
                if (isset($_SESSION['user_id']) && '' != $_SESSION['user_id']) {
                    $user_id_of_guest = $_SESSION['user_id'];
                }
                // Delete the guest record. As it become the logged in user
                $get_abandoned_cart_id_guest_results = array();
                if (isset($user_id_of_guest) && '' != $user_id_of_guest) {
                    $get_abandoned_cart_id_guest_query = "SELECT id FROM `" . $this->cart_history_table . "` WHERE user_id = %d ORDER BY id DESC";
                    $get_abandoned_cart_id_guest_results = $wpdb->get_results($wpdb->prepare($get_abandoned_cart_id_guest_query, $user_id_of_guest));
                }
                if (is_array($get_abandoned_cart_id_guest_results) && count($get_abandoned_cart_id_guest_results) > 0) {
                    $abandoned_order_id_of_guest = $get_abandoned_cart_id_guest_results[0]->id;
                    $wpdb->delete($this->cart_history_table, array('id' => $abandoned_order_id_of_guest));
                }
                // It is the new registered users cart id
                if (isset($user_id_of_guest) && '' != $user_id_of_guest) {
                    $wpdb->delete($this->cart_history_table, array('id' => $abandoned_cart_id_new_user));
                }
                $create_account = 'yes';
            }

            if ('no' == $account_password_check && 'no' == $create_account) {
                if (isset($_SESSION['user_id']) && '' != $_SESSION['user_id']) {
                    $user_id_of_guest = $_SESSION['user_id'];
                    $get_abandoned_cart_id_guest_query = "SELECT id FROM `" . $this->cart_history_table . "` WHERE user_id = %d ORDER BY id DESC";
                    $get_abandoned_cart_id_guest_results = $wpdb->get_results($wpdb->prepare($get_abandoned_cart_id_guest_query, $user_id_of_guest));

                    if (is_array($get_abandoned_cart_id_guest_results) && count($get_abandoned_cart_id_guest_results) > 0) {
                        $abandoned_order_id_of_guest = $get_abandoned_cart_id_guest_results[0]->id;
                        $wpdb->delete($this->cart_history_table, array('id' => $abandoned_order_id_of_guest));
                    }
                }
            }
            add_post_meta($order_id, 'woocommerce_retainful_recover_order_placed_sent_id', $abandoned_order_id);
            if (isset($abandoned_order_id) && '' != $abandoned_order_id) {
                add_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', $abandoned_order_id);
            }
        } else if ($this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id') != '') {
            global $wpdb;
            $current_time = current_time('timestamp');
            $cart_abandoned_time = '';
            $abandoned_cart_id = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id');
            $get_abandoned_cart_id_guest_results = array();
            if ($abandoned_cart_id != '') {
                $get_abandoned_cart_query = "SELECT abandoned_cart_time FROM `" . $this->cart_history_table . "` WHERE id = %d ";
                $get_abandoned_cart_results = $wpdb->get_results($wpdb->prepare($get_abandoned_cart_query, $abandoned_cart_id));
                if (is_array($get_abandoned_cart_id_guest_results) && count($get_abandoned_cart_results) > 0) {
                    $cart_abandoned_time = $get_abandoned_cart_results[0]->abandoned_cart_time;
                }
                $abandoned_cart_settings = $this->admin->getAbandonedCartSettings();
                $cut_off_time_settings = isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time']) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time'] : 60;
                $cut_off_time = $cut_off_time_settings * 60;
                $compare_time = $current_time - $cut_off_time;
                if ($compare_time > $cart_abandoned_time) {
                    // cart is declared as abandoned
                    add_post_meta($order_id, 'recover_order_placed', $abandoned_cart_id);
                } else {
                    /*
                     * Cart order is placed within the cutoff time.
                     *  We will delete that abandoned cart.
                     */
                    // If user becomes the registered user
                    if (isset($_POST['account_password']) && '' != $_POST['account_password']) {
                        $abandoned_cart_id_new_user = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'abandoned_cart_id');
                        $user_id_of_guest = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'user_id');
                        // Delete the guest record. As it become the logged in user
                        $wpdb->delete($this->cart_history_table, array('user_id' => $user_id_of_guest));
                        $wpdb->delete($this->guest_cart_history_table, array('id' => $user_id_of_guest));

                        // It is the new registered users cart id
                        $wpdb->delete($this->cart_history_table, array('id' => $abandoned_cart_id_new_user));
                    } else {
                        /*
                         * It will delete the order from history table if the order is placed before any email sent to
                         * the user.
                         */
                        $wpdb->delete($this->cart_history_table, array('id' => $abandoned_cart_id));
                        // This user id is set for the guest users.
                        if ($this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'user_id') != '') {
                            $user_id_of_guest = $this->wc_functions->getPhpSession(RNOC_PLUGIN_PREFIX . 'user_id');
                            $wpdb->delete($this->guest_cart_history_table, array('id' => $user_id_of_guest));
                        }
                    }
                }
            }
        }

    }

    /**
     * Notify customer about the order is recovered
     * @param $order_id
     */
    function notifyAdminOnRecovery($order_id)
    {
        $abandoned_cart_settings = $this->admin->getAbandonedCartSettings();
        $email_admin_recovery = isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'email_admin_on_recovery']) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'email_admin_on_recovery'] : 0;
        if ($email_admin_recovery) {
            $order = $this->wc_functions->getOrder($order_id);
            $recovered_email_sent = get_post_meta($order_id, 'woocommerce_retainful_recovered_email_sent', true);
            $created_via = get_post_meta($order_id, '_created_via', true);
            $check_order_is_recovered = $this->isOrderRecovered($order_id);
            if ('checkout' == $created_via && 'yes' != $recovered_email_sent && true === $check_order_is_recovered) {
                $email_heading = __('New Customer Order - Recovered', RNOC_TEXT_DOMAIN);
                $email_subject = __('New Customer Order - Recovered', RNOC_TEXT_DOMAIN);
                $user_email = get_option('admin_email');
                $headers[] = "From: Admin <" . $user_email . ">";
                $headers[] = "Content-Type: text/html";
                // Buffer
                ob_start();
                // Get mail template
                wc_get_template('emails/admin-new-order.php', array(
                    'order' => $order,
                    'email_heading' => $email_heading,
                    'sent_to_admin' => false,
                    'plain_text' => false,
                    'email' => true
                ));
                // Get contents
                $email_body = ob_get_clean();
                wc_mail($user_email, $email_subject, $email_body, $headers);
                update_post_meta($order_id, 'woocommerce_retainful_recovered_email_sent', 'yes');
            }
        }
    }


    /**
     * For sending Recovery Email to Admin, we will check that order is recovered or not.
     * @param int | string $order_id Order id
     * @return boolean true | false
     * @globals mixed $wpdb
     * @since 2.3
     */
    function isOrderRecovered($order_id)
    {
        global $wpdb;
        $recover_order_query = "SELECT `recovered_cart` FROM `" . $this->cart_history_table . "` WHERE `recovered_cart` = %d";
        $recover_order_query_result = $wpdb->get_results($wpdb->prepare($recover_order_query, $order_id));
        if (count($recover_order_query_result) > 0) {
            return true;
        }
        return false;
    }

    /**
     * Check the cari is not empty
     * @param $cart
     * @return bool
     */
    function checkCartTotal($cart)
    {
        foreach ($cart as $k => $v) {
            if ($v->line_total != 0 && $v->line_total > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update abandoned cart status if the order has been placed before sending the reminder emails.
     * @param $check_email_sent_to_cart
     * @param $cart_time
     * @param $user_id
     * @param $user_type
     * @param $cart_id
     * @param $user_email
     * @return bool
     */
    function updateAbandonedCartStatusForPlacedOrders($check_email_sent_to_cart, $cart_time, $user_id, $user_type, $cart_id, $user_email)
    {
        if ($user_id >= '63000000' && 'GUEST' == $user_type) {
            $updated_value = $this->updateGuestStatus($cart_id, $cart_time, $check_email_sent_to_cart, $user_email);
            if (1 == $updated_value) {
                return true;
            }
        } elseif ($user_id < '63000000' && 'REGISTERED' == $user_type) {
            $updated_value = $this->updateLoggedInUserStatus($cart_id, $cart_time, $check_email_sent_to_cart, $user_email);
            if (1 == $updated_value) {
                return true;
            }
        }
        return false;
    }

    /**
     *  It will update the Loggedin users abandoned cart staus if the order has been placed before sending the reminder emails.
     * @param $cart_id
     * @param $abandoned_cart_time
     * @param $check_email_sent_to_cart
     * @param $user_billing_email
     * @return int
     */
    function updateLoggedInUserStatus($cart_id, $abandoned_cart_time, $check_email_sent_to_cart, $user_billing_email)
    {
        global $wpdb;
        $query_email_id = "SELECT wpm.post_id, wpost.post_date, wpost.post_status  FROM `" . $wpdb->prefix . "postmeta` AS wpm LEFT JOIN `" . $wpdb->prefix . "posts` AS wpost ON wpm.post_id =  wpost.ID WHERE wpm.meta_key = '_billing_email' AND wpm.meta_value = %s AND wpm.post_id = wpost.ID Order BY wpm.post_id DESC LIMIT 1";
        $results_query_email = $wpdb->get_results($wpdb->prepare($query_email_id, $user_billing_email));

        if (count($results_query_email) > 0) {
            $current_time = current_time('timestamp');
            $today_date = date('Y-m-d', $current_time);
            $order_date_time = $results_query_email[0]->post_date;
            $order_date = substr($order_date_time, 0, 10);
            if ($order_date == $today_date) {
                if (0 != $check_email_sent_to_cart) {
                    $query = "SELECT `post_id` FROM `" . $wpdb->prefix . "postmeta` WHERE  meta_value = %s";
                    $results = $wpdb->get_results($wpdb->prepare($query, $cart_id));
                    if (count($results) > 0) {
                        $order_id = $results[0]->post_id;
                        $order = $this->wc_functions->getOrder($order_id);
                        $query_order = "UPDATE `" . $this->cart_history_table . "` SET recovered_cart= '" . $order_id . "', cart_ignored = '1' WHERE id = '" . $cart_id . "' ";
                        $wpdb->query($query_order);
                        $this->wc_functions->setOrderNote($order, __('This order was abandoned & subsequently recovered.', RNOC_TEXT_DOMAIN));
                        delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', $cart_id);
                        delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed_sent_id', $check_email_sent_to_cart);
                    }
                } else {
                    $query_ignored = "UPDATE `" . $this->cart_history_table . "` SET cart_ignored = '1' WHERE id ='" . $cart_id . "'";
                    $wpdb->query($query_ignored);
                }
                return 1;
            } else if (strtotime($order_date_time) >= $abandoned_cart_time) {
                $query_ignored = "UPDATE `" . $this->cart_history_table . "` SET cart_ignored = '1' WHERE id ='" . $cart_id . "'";
                $wpdb->query($query_ignored);
                return 1;
            } else if ("wc-pending" == $results_query_email[0]->post_status || "wc-failed" == $results_query_email[0]->post_status) {
                return 0;
            }
        }
        return 0;
    }

    /**
     * It will update the Guest users abandoned cart staus if the order has been placed before sending the reminder emails.
     * @param $cart_id
     * @param $abandoned_cart_time
     * @param $check_email_sent_to_cart
     * @param $user_email_address
     * @return int
     */
    function updateGuestStatus($cart_id, $abandoned_cart_time, $check_email_sent_to_cart, $user_email_address)
    {
        global $wpdb;
        $query_email_id = "SELECT wpm.post_id, wpost.post_date, wpost.post_status  FROM `" . $wpdb->prefix . "postmeta` AS wpm LEFT JOIN `" . $wpdb->prefix . "posts` AS wpost ON wpm.post_id = wpost.ID WHERE wpm.meta_key = '_billing_email' AND wpm.meta_value = %s AND wpm.post_id = wpost.ID AND wpost.post_type = 'shop_order' Order BY wpm.post_id   DESC LIMIT 1";
        $results_query_email = $wpdb->get_results($wpdb->prepare($query_email_id, $user_email_address));
        /* This will check that For abc@abc.com email address we have order for todays date in WC post table */
        if (count($results_query_email) > 0) {
            $current_time = current_time('timestamp');
            $today_date = date('Y-m-d', $current_time);
            $order_date_with_time = $results_query_email[0]->post_date;
            $order_date = substr($order_date_with_time, 0, 10);
            if ($order_date == $today_date) {
                /**
                 * in some case the cart is recovered but it is not marked as the recovered. So here we check if any
                 * record is found for that cart id if yes then update the record respectively.
                 */
                if (0 != $check_email_sent_to_cart) {
                    $query = "SELECT `post_id` FROM `" . $wpdb->prefix . "postmeta` WHERE  meta_value = %s";
                    $results = $wpdb->get_results($wpdb->prepare($query, $cart_id));
                    if (count($results) > 0) {
                        $order_id = $results[0]->post_id;
                        $order = $this->wc_functions->getOrder($order_id);
                        $query_order = "UPDATE `" . $this->cart_history_table . "` SET recovered_cart= '" . $order_id . "', cart_ignored = '1' WHERE id = '" . $cart_id . "' ";
                        $wpdb->query($query_order);
                        $this->wc_functions->setOrderNote($order, __('This order was abandoned & subsequently recovered.', RNOC_TEXT_DOMAIN));
                        delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', $cart_id);
                        delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed_sent_id', $check_email_sent_to_cart);
                    }
                } else {
                    $query_ignored = "UPDATE `" . $this->cart_history_table . "` SET cart_ignored = '1' WHERE id ='" . $cart_id . "'";
                    $wpdb->query($query_ignored);
                }
                return 1;
            } else if (strtotime($order_date_with_time) > $abandoned_cart_time) {
                $query_ignored = "UPDATE `" . $this->cart_history_table . "` SET cart_ignored = '1' WHERE id ='" . $cart_id . "'";
                $wpdb->query($query_ignored);
                return 1;
            } else if ("wc-pending" == $results_query_email[0]->post_status || "wc-failed" == $results_query_email[0]->post_status) {
                /**
                 * If the post status are pending or failed  the send them for abandoned cart reminder emails.
                 */
                return 0;
            }
        }
        return 0;
    }

    /**
     * Add requires js need to track guest
     */
    function addTrackUserJs()
    {
        $asset_path = plugins_url('', __FILE__);
        wp_enqueue_script(RNOC_PLUGIN_PREFIX . 'capture_guest_details', $asset_path . '/assets/js/track_guest.js', '', '', true);
        wp_localize_script(RNOC_PLUGIN_PREFIX . 'capture_guest_details', 'retainful_guest_capture_params', array('ajax_url' => admin_url('admin-ajax.php')));
    }

    /**
     * When user places the order and reach the order recieved page, then it will check if it is abandoned cart and subsequently recovered or not.
     * @param $order
     */
    function afterSessionDelivery($order)
    {
        if ('' === session_id()) {
            //session has not started
            session_start();
        }
        global $wpdb;
        $order_id = $this->wc_functions->getOrderId($order);
        $abandoned_guest_cart_history_table_name = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'guest_abandoned_cart_history';
        $get_abandoned_id_of_order = get_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', true);
        if (isset($get_abandoned_id_of_order) && '' != $get_abandoned_id_of_order) {
            $get_abandoned_id_of_order = get_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', true);
            $get_sent_email_id_of_order = get_post_meta($order_id, 'woocommerce_retainful_recover_order_placed_sent_id', true);
            $update_query = "UPDATE `" . $this->cart_history_table . "` SET recovered_cart= %d, cart_ignored = %s WHERE id = %d";
            $wpdb->query($wpdb->prepare($update_query, $order_id, 1, $get_abandoned_id_of_order));
            $this->wc_functions->setOrderNote($order, __('This order was abandoned & subsequently recovered.', RNOC_TEXT_DOMAIN));
            delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed', $get_abandoned_id_of_order);
            delete_post_meta($order_id, 'woocommerce_retainful_recover_order_placed_sent_id', $get_sent_email_id_of_order);
        }
        $user_id = get_current_user_id();
        if ($user_id == "") {
            $user_id = $this->wc_functions->getPhpSession('rnoc_user_id');
            //  Set the session variables to blanks
            $this->wc_functions->removeSession('rnoc_guest_first_name');
            $this->wc_functions->removeSession('rnoc_guest_last_name');
            $this->wc_functions->removeSession('rnoc_guest_email');
            $this->wc_functions->removeSession('rnoc_user_id');
        }
        delete_user_meta($user_id, '_woocommerce_retainful_persistent_cart_time');
        delete_user_meta($user_id, '_woocommerce_retainful_persistent_temp_cart_time');
        // get all latest abandoned carts that were modified
        $cart_ignored = 0;
        $recovered_cart = 0;
        $query = "SELECT * FROM `" . $this->cart_history_table . "` WHERE user_id = %d AND cart_ignored = %s AND recovered_cart = %d ORDER BY id DESC LIMIT 1";
        $results = $wpdb->get_results($wpdb->prepare($query, $user_id, $cart_ignored, $recovered_cart));
        if (count($results) > 0) {
            if (get_user_meta($user_id, '_woocommerce_retainful_cart_modified', true) == md5("yes") ||
                get_user_meta($user_id, '_woocommerce_retainful_cart_modified', true) == md5("no")) {
                $updated_cart_ignored = 1;
                $query_order = "UPDATE `" . $this->cart_history_table . "` SET recovered_cart = %d, cart_ignored = %s WHERE id = %d ";
                $wpdb->query($wpdb->prepare($query_order, $order_id, $updated_cart_ignored, $results[0]->id));
                delete_user_meta($user_id, '_woocommerce_retainful_cart_modified');
                delete_post_meta($order_id, 'woocommerce_retainful_recovered_email_sent', 'yes');
            } else {
                $delete_query = "DELETE FROM `" . $this->cart_history_table . "` WHERE id= %d ";
                $wpdb->query($wpdb->prepare($delete_query, $results[0]->id));
            }
        } else {
            $email_id = $this->wc_functions->getOrderEmail($order);
            $query = "SELECT * FROM `" . $abandoned_guest_cart_history_table_name . "` WHERE email_id = %s";
            $results_id = $wpdb->get_results($wpdb->prepare($query, $email_id));

            if ($results_id) {
                $record_status = "SELECT * FROM `" . $this->cart_history_table . "` WHERE user_id = %d AND recovered_cart = %s";
                $results_status = $wpdb->get_results($wpdb->prepare($record_status, $results_id[0]->id, 0));
                if ($results_status) {
                    if (get_user_meta($results_id[0]->id, '_woocommerce_retainful_cart_modified', true) == md5("yes") ||
                        get_user_meta($results_id[0]->id, '_woocommerce_retainful_cart_modified', true) == md5("no")) {
                        $query_order = "UPDATE `" . $this->cart_history_table . "` SET recovered_cart= %d, cart_ignored = %s WHERE id=%d";
                        $wpdb->query($wpdb->prepare($query_order, $order_id, 1, $results_status[0]->id));
                        delete_user_meta($results_id[0]->id, '_woocommerce_retainful_cart_modified');
                        delete_post_meta($order_id, 'woocommerce_retainful_recovered_email_sent', 'yes');
                    } else {
                        $delete_guest = "DELETE FROM `" . $abandoned_guest_cart_history_table_name . "` WHERE id = %d";
                        $wpdb->query($wpdb->prepare($delete_guest, $results_id[0]->id));
                        $delete_query = "DELETE FROM `" . $this->cart_history_table . "` WHERE user_id=%d";
                        $wpdb->query($wpdb->prepare($delete_query, $results_id[0]->id));
                    }
                }
            }
        }
    }


    /**
     * Get the abandoned cart of particular timestamp
     * @param $start_date
     * @param $end_date
     * @return array|object|null
     */
    function getAbandonedCartsOfDate($start_date, $end_date)
    {
        if (empty($start_date) || empty($end_date)) {
            return array();
        }
        global $wpdb;
        $blank_cart_info = '{"cart":[]}';
        $blank_cart_info_guest = '[]';
        $blank_cart = '""';
        $start_date = strtotime($start_date . ' 00:01:01');
        $end_date = strtotime($end_date . ' 23:59:59');
        $abandoned_cart_settings = $this->admin->getAbandonedCartSettings();
        $cut_off_time_settings = isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time']) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time'] : 60;
        $cut_off_time = $cut_off_time_settings * 60;
        $current_time = current_time('timestamp');
        $compare_time = $current_time - $cut_off_time;
        $recovered_carts_query = "SELECT * FROM `" . $this->cart_history_table . "`WHERE abandoned_cart_time >= %d AND abandoned_cart_time <= %d AND recovered_cart > 0 AND abandoned_cart_time <= '$compare_time' AND abandoned_cart_info NOT LIKE '%$blank_cart_info%' AND abandoned_cart_info NOT LIKE '$blank_cart_info_guest' AND abandoned_cart_info NOT LIKE '$blank_cart' ORDER BY recovered_cart desc";
        //$wpdb->get_results($wpdb->prepare($recovered_carts_query, $start_date, $end_date));
        $abandoned_carts_query = "SELECT * FROM " . $this->cart_history_table . " WHERE abandoned_cart_time >= %d AND abandoned_cart_time <= %d AND abandoned_cart_time <= '$compare_time' AND abandoned_cart_info NOT LIKE '%$blank_cart_info%' AND abandoned_cart_info NOT LIKE '$blank_cart_info_guest' AND abandoned_cart_info NOT LIKE '$blank_cart' AND (cart_ignored <> '1') OR (cart_ignored = '1' AND recovered_cart > 0)";
        return $abandoned_carts_results = $wpdb->get_results($wpdb->prepare($abandoned_carts_query, $start_date, $end_date));
        /*$recovered_item = $recovered_total = $count_carts = $total_value = $order_total = 0;
        $return_recovered_orders = array();
        $per_page = 10;
        $i = 0;
        foreach ($abandoned_carts_results as $key => $value) {
            $count_carts += 1;
            $cart_detail = json_decode($value->abandoned_cart_info);
            $product_details = new \stdClass();
            if (isset($cart_detail->cart)) {
                $product_details = $cart_detail->cart;
            }
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
            $total_value += $line_total;
        }
        $this->total_order_amount = $total_value;
        $this->total_abandoned_cart_count = $count_carts;
        $recovered_order_total = 0;
        $this->total_recover_amount = round($recovered_order_total, $number_decimal);
        $this->recovered_item = 0;
        $table_data = "";
        foreach ($recovered_carts as $key => $value) {
            if ($value->recovered_cart != 0) {
                $return_recovered_orders[$i] = new \stdClass();
                $recovered_id = $value->recovered_cart;
                $rec_order = get_post_meta($recovered_id);
                try {
                    //$woo_order = $this->wc_functions->getOrder($recovered_id);
                    $recovered_item += 1;
                    if (isset($rec_order) && $rec_order != false) {
                        $recovered_total += $rec_order['_order_total'][0];
                    }
                    $date_format = date_i18n(get_option('date_format'), $value->abandoned_cart_time);
                    $time_format = date_i18n(get_option('time_format'), $value->abandoned_cart_time);
                    $abandoned_date = $date_format . ' ' . $time_format;
                    $abandoned_order_id = $value->id;
                    $billing_first_name = $billing_last_name = $billing_email = '';
                    $recovered_order_total = 0;

                    if (isset($rec_order['_billing_first_name'][0])) {
                        $billing_first_name = $rec_order['_billing_first_name'][0];
                    }

                    if (isset($rec_order['_billing_last_name'][0])) {
                        $billing_last_name = $rec_order['_billing_last_name'][0];
                    }

                    if (isset($rec_order['_billing_email'][0])) {
                        $billing_email = $rec_order['_billing_email'][0];
                    }

                    if (isset($rec_order['_order_total'][0])) {
                        $recovered_order_total = $rec_order['_order_total'][0];
                    }

                    $return_recovered_orders[$i]->user_name = $billing_first_name . " " . $billing_last_name;
                    $return_recovered_orders[$i]->user_email_id = $billing_email;
                    $return_recovered_orders[$i]->created_on = $abandoned_date;
                    $return_recovered_orders[$i]->recovered_id = $recovered_id;
                    $return_recovered_orders[$i]->abandoned_date = $value->abandoned_cart_time;
                    $return_recovered_orders[$i]->order_total = wc_price($recovered_order_total);

                    $this->recovered_item = $recovered_item;
                    $this->total_recover_amount = round(($recovered_order_total + $this->total_recover_amount), $number_decimal);
                    $i++;
                } catch (\Exception $e) {

                }
            }
        }*/
    }

    /**
     * get abandoned cart dashboard for ajax request
     */
    function getAjaxDetailsForDashboard()
    {
        if (isset($_REQUEST['start']) && isset($_REQUEST['end'])) {
            $start_date = $_REQUEST['start'];
            $end_date = $_REQUEST['end'];
        } else {
            $start_date = $this->start_end_dates['last_seven']['start_date'];
            $end_date = $this->start_end_dates['last_seven']['end_date'];
        }
        $response = $this->getStaticsForDashboard($start_date, $end_date);
        wp_send_json($response);
    }

    /**
     * get the abandoned cart details
     * @param $start_date
     * @param $end_date
     * @return array
     */
    function getStaticsForDashboard($start_date, $end_date)
    {
        $cart_histories = $this->getAbandonedCartsOfDate($start_date, $end_date);
        $recovered_carts = $recovered_total = $abandoned_cart = $abandoned_total = 0;
        if (!empty($cart_histories)) {
            foreach ($cart_histories as $key => $value) {
                $cart_detail = json_decode($value->abandoned_cart_info);
                $product_details = new \stdClass();
                if (isset($cart_detail->cart)) {
                    $product_details = $cart_detail->cart;
                }
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
                $abandoned_cart += 1;
                $abandoned_total += $line_total;
                if ($value->recovered_cart > 0) {
                    $recovered_carts += 1;
                    $recovered_total += $line_total;
                }
            }
        }
        return array(
            'recovered_carts' => $recovered_carts,
            'recovered_total' => $this->wc_functions->formatPrice($recovered_total),
            'abandoned_carts' => $abandoned_cart,
            'abandoned_total' => $this->wc_functions->formatPrice($abandoned_total)
        );
        /*return array(
            'recovered_carts' => $this->recovered_item,
            'recovered_total' => $this->wc_functions->formatPrice($this->total_recover_amount),
            'abandoned_carts' => $this->total_abandoned_cart_count,
            'abandoned_total' => $this->wc_functions->formatPrice($this->total_order_amount)
        );*/
    }
}