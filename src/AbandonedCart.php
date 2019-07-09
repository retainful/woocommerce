<?php

namespace Rnoc\Retainful;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Rnoc\Retainful\Admin\Settings;

class AbandonedCart
{
    protected static $applied_coupons = NULL;
    public $wc_functions, $admin, $cart_history_table, $email_history_table, $email_templates_table, $guest_cart_history_table, $total_order_amount, $total_abandoned_cart_count, $total_recover_amount, $recovered_item;
    public $start_end_dates = array(), $start_end_dates_label = array();

    function __construct()
    {
        global $wpdb;
        $this->wc_functions = new WcFunctions();
        $this->admin = new Settings();
        $this->start_end_dates = array(
            'yesterday' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp') - 24 * 60 * 60))),
            'today' => array('start_date' => date("Y/m/d", (current_time('timestamp'))), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_seven' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 7 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_fifteen' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 15 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_thirty' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 30 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_ninety' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 90 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp')))),
            'last_year_days' => array('start_date' => date("Y/m/d", (current_time('timestamp') - 365 * 24 * 60 * 60)), 'end_date' => date("Y/m/d", (current_time('timestamp'))))
        );
        $this->start_end_dates_label = array(
            'yesterday' => __('Yesterday', RNOC_TEXT_DOMAIN),
            'today' => __('Today', RNOC_TEXT_DOMAIN),
            'last_seven' => __('Last 7 days', RNOC_TEXT_DOMAIN),
            'last_fifteen' => __('Last 15 days', RNOC_TEXT_DOMAIN),
            'last_thirty' => __('Last 30 days', RNOC_TEXT_DOMAIN),
            'last_ninety' => __('Last 90 days', RNOC_TEXT_DOMAIN),
            'last_year_days' => __('Last 365 days', RNOC_TEXT_DOMAIN),
            'custom' => __('Custom', RNOC_TEXT_DOMAIN)
        );
        $this->cart_history_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_cart_history';
        $this->guest_cart_history_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'guest_abandoned_cart_history';
        $this->email_history_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_sent_history';
        $this->email_templates_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_templates';
    }

    /**
     * Save guest data for further processing
     */
    function saveGuestData()
    {
        if (!is_user_logged_in()) {
            global $woocommerce;
            //Can't look up the customer in this situation.
            if (!isset($woocommerce->session)) {
                return;
            }
            //$user_session_id = $this->wc_functions->getPHPSessionCustomerId();
            $user_session_id = $this->getUserSessionKey();
            if (!empty($user_session_id) && isset($_POST['billing_email'])) {
                global $wpdb, $woocommerce;
                //Post details
                $billing_first_name = (isset($_POST['billing_first_name'])) ? sanitize_text_field($_POST['billing_first_name']) : '';
                $billing_last_name = (isset($_POST['billing_last_name'])) ? sanitize_text_field($_POST['billing_last_name']) : '';
                $billing_company = (isset($_POST['billing_company'])) ? sanitize_text_field($_POST['billing_company']) : '';
                $billing_address_1 = (isset($_POST['billing_address_1'])) ? sanitize_text_field($_POST['billing_address_1']) : '';
                $billing_address_2 = (isset($_POST['billing_address_2'])) ? sanitize_text_field($_POST['billing_address_2']) : '';
                $billing_city = (isset($_POST['billing_city'])) ? sanitize_text_field($_POST['billing_city']) : '';
                $billing_state = (isset($_POST['billing_state'])) ? sanitize_text_field($_POST['billing_state']) : '';
                $billing_zipcode = (isset($_POST['billing_postcode'])) ? sanitize_text_field($_POST['billing_postcode']) : '';
                $billing_country = (isset($_POST['billing_country'])) ? sanitize_text_field($_POST['billing_country']) : '';
                $billing_phone = (isset($_POST['billing_phone'])) ? sanitize_text_field($_POST['billing_phone']) : '';
                $billing_email = $_POST['billing_email'];
                $ship_to_billing = (isset($_POST['ship_to_billing'])) ? $_POST['ship_to_billing'] : '';
                $order_notes = (isset($_POST['order_notes'])) ? sanitize_text_field($_POST['order_notes']) : '';
                $shipping_first_name = (isset($_POST['shipping_first_name'])) ? sanitize_text_field($_POST['shipping_first_name']) : '';
                $shipping_last_name = (isset($_POST['shipping_last_name'])) ? sanitize_text_field($_POST['shipping_last_name']) : '';
                $shipping_company = (isset($_POST['shipping_company'])) ? sanitize_text_field($_POST['shipping_company']) : '';
                $shipping_address_1 = (isset($_POST['shipping_address_1'])) ? sanitize_text_field($_POST['shipping_address_1']) : '';
                $shipping_address_2 = (isset($_POST['shipping_address_2'])) ? sanitize_text_field($_POST['shipping_address_2']) : '';
                $shipping_city = (isset($_POST['shipping_city'])) ? sanitize_text_field($_POST['shipping_city']) : '';
                $shipping_state = (isset($_POST['shipping_state'])) ? sanitize_text_field($_POST['shipping_state']) : '';
                $shipping_zipcode = (isset($_POST['shipping_postcode'])) ? sanitize_text_field($_POST['shipping_postcode']) : '';
                $shipping_country = (isset($_POST['shipping_country'])) ? sanitize_text_field($_POST['shipping_country']) : '';
                $shipping_charges = $woocommerce->cart->shipping_total;
                //Check the details already found
                $query = "SELECT * FROM `" . $this->guest_cart_history_table . "` WHERE session_id = %s";
                $results = $wpdb->get_row($wpdb->prepare($query, $user_session_id), OBJECT);
                if (!empty($results)) {
                    $guest_details_id = $results->id;
                    $query_update = "UPDATE `" . $this->guest_cart_history_table . "` SET billing_first_name=%s, billing_last_name=%s, billing_company_name=%s, billing_address_1=%s, billing_address_2=%s, billing_city=%s, billing_county=%s, billing_zipcode=%s, email_id=%s, phone=%s, ship_to_billing=%s, order_notes=%s, shipping_first_name=%s, shipping_last_name=%s, shipping_company_name=%s, shipping_address_1=%s, shipping_address_2=%s, shipping_city=%s, shipping_county=%s, shipping_zipcode=%s, shipping_charges=%s, session_id=%s WHERE id=%d";
                    $wpdb->query($wpdb->prepare($query_update, $billing_first_name, $billing_last_name, $billing_company, $billing_address_1, $billing_address_2, $billing_city, $billing_state, $billing_zipcode, $billing_email, $billing_phone, $ship_to_billing, $order_notes, $shipping_first_name, $shipping_last_name, $shipping_company, $shipping_address_1, $shipping_address_2, $shipping_city, $shipping_state, $shipping_zipcode, $shipping_charges, $user_session_id, $guest_details_id));
                } else {
                    $insert_guest = "INSERT INTO `" . $this->guest_cart_history_table . "`(billing_first_name, billing_last_name, billing_company_name, billing_address_1, billing_address_2, billing_city, billing_county, billing_zipcode, email_id, phone, ship_to_billing, order_notes, shipping_first_name, shipping_last_name, shipping_company_name, shipping_address_1, shipping_address_2, shipping_city, shipping_county, shipping_zipcode, shipping_charges, session_id) VALUES ( %s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)";
                    $wpdb->query($wpdb->prepare($insert_guest, $billing_first_name, $billing_last_name, $billing_company, $billing_address_1, $billing_address_2, $billing_city, $billing_state, $billing_zipcode, $billing_email, $billing_phone, $ship_to_billing, $order_notes, $shipping_first_name, $shipping_last_name, $shipping_company, $shipping_address_1, $shipping_address_2, $shipping_city, $shipping_state, $shipping_zipcode, $shipping_charges, $user_session_id));
                }
            }
        }
    }

    /**
     * get session user key
     * @return array|string|null
     */
    function getUserSessionKey()
    {
        $session_key = $this->wc_functions->getPHPSession(RNOC_PLUGIN_PREFIX . 'user_session_key');
        if (empty($session_key)) {
            $session_key = $this->generateToken();
            $this->wc_functions->setPHPSession(RNOC_PLUGIN_PREFIX . 'user_session_key', $session_key);
        }
        return $session_key;
    }

    /**
     * Generate unique random Key
     * @return string
     */
    function generateToken()
    {
        $rand = rand(10, 100000);
        $id = uniqid();
        return md5($id . $rand);
    }

    /**
     * When user go to checkout page
     * @param $fields
     * @return mixed
     */
    function checkoutViewed($fields)
    {
        $this->userCartUpdated();
        return $fields;
    }

    /**
     * Capture the cart and insert the information of the cart into DataBase.
     */
    function userCartUpdated()
    {
        global $wpdb, $woocommerce;
        $abandoned_cart_settings = $this->admin->getAdminSettings();
        $current_time = current_time('timestamp');
        $cut_off_time_settings = isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time']) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time'] : 60;
        $cart_cut_off_time = intval($cut_off_time_settings) * 60;
        $cart = $woocommerce->cart;
        if (is_user_logged_in()) {
            $customer_id = get_current_user_id();
        } else {
            //Can't look up the customer in this situation.
            if (!isset($woocommerce->session)) {
                return;
            }
            //$customer_id = $this->wc_functions->getPHPSessionCustomerId();
            $customer_id = $this->getUserSessionKey();
        }
        $currency_code = $this->getCurrentCurrencyCode();
        $row = $wpdb->get_row('SELECT * FROM ' . $this->cart_history_table . ' WHERE customer_key = \'' . $customer_id . '\' AND cart_is_recovered = 0 AND order_id IS NULL LIMIT 1', OBJECT);
        if (empty($row)) {
            $crawler_detect = new CrawlerDetect();
            if ($crawler_detect->isCrawler()) {
                return;
            }
            //Don't create a record unless a user has something in their cart
            if (!$cart->cart_contents) {
                return;
            }
            $wpdb->insert($this->cart_history_table,
                array('currency_code' => $currency_code, 'customer_key' => $customer_id, 'cart_contents' => json_encode($this->wc_functions->getCart()), 'cart_expiry' => $current_time + $cart_cut_off_time, 'cart_is_recovered' => 0, 'show_on_funnel_report' => 1, 'ip_address' => $_SERVER['REMOTE_ADDR'], 'item_count' => $cart->cart_contents_count, 'cart_total' => $cart->cart_contents_total),
                array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d')
            );
        } else {
            $update_values = null;
            if (is_checkout() || $current_time - $cart_cut_off_time > $row->cart_expiry) {
                $update_values = array(
                    'cart_contents' => json_encode($this->wc_functions->getCart()),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'item_count' => $cart->cart_contents_count,
                    'cart_total' => $cart->cart_contents_total,
                    'viewed_checkout' => true,
                    'currency_code' => $currency_code
                );
            } else {
                $update_values = array(
                    'cart_contents' => json_encode($this->wc_functions->getCart()),
                    /*'cart_expiry' => $current_time,*/
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'item_count' => $cart->cart_contents_count,
                    'cart_total' => $cart->cart_contents_total,
                    'currency_code' => $currency_code
                );
            }
            $wpdb->update(
                $this->cart_history_table,
                $update_values,
                array(
                    'id' => $row->id
                )
            );
        }
    }

    /**
     * User logged in the store
     * @param $user_name
     */
    function userLoggedOn($user_name)
    {
        if ($user_name) {
            $user = get_user_by('login', $user_name);
            if (!empty($user)) {
                $this->userSignedUp($user->ID);
            } else {
                $user = get_user_by('email', $user_name);
                if ($user) {
                    $this->userSignedUp($user->ID);
                }
            }
        }
    }

    /**
     * When user signed up
     * @param $user_id
     */
    function userSignedUp($user_id)
    {
        global $wpdb;
        global $woocommerce;
        //Don't create a record unless a user is logging in with something in their cart
        if (!$woocommerce->cart->cart_contents) {
            return;
        }
        //Can't look up the customer in this situation.
        if (!isset($woocommerce->session)) {
            return;
        }
        //$customer_id = $this->wc_functions->getPHPSessionCustomerId();
        $customer_id = $this->getUserSessionKey();
        $row = $wpdb->get_row('SELECT * FROM ' . $this->cart_history_table . ' WHERE customer_key = \'' . $customer_id . '\' AND cart_is_recovered = 0 AND order_id IS NULL LIMIT 1', OBJECT);
        if (!empty($row)) {
            $wpdb->query('DELETE FROM ' . $this->cart_history_table . ' WHERE customer_key = \'' . $user_id . '\' AND cart_is_recovered = 0 AND order_id IS NULL');
            $wpdb->update(
                $this->cart_history_table,
                array('customer_key' => $user_id), array('id' => $row->id), array('%s')
            );
        }
    }

    /**
     * get active emails
     * @return array|object|null
     */
    function getActiveEmailTemplates()
    {
        global $wpdb;
        $email_template_query = "SELECT * FROM `" . $this->email_templates_table . "` WHERE  is_active = %s ";
        return $wpdb->get_results($wpdb->prepare($email_template_query, 1));
    }

    /**
     * Automatically send customer recovery email
     * This function will call in every 5 minutes
     */
    function sendAbandonedCartEmails()
    {
        $email_templates = $this->getActiveEmailTemplates();
        $email_templates_settings = $this->admin->getEmailTemplatesSettings();
        if (!empty($email_templates)) {
            $hour_seconds = 3600;//60*60
            $day_seconds = 86400;//60*60*24
            foreach ($email_templates as $template) {
                if ($template->frequency && !empty($template->body)) {
                    global $wpdb;
                    $time = ($template->day_or_hour == "Days") ? $day_seconds : $hour_seconds;
                    $current_time = current_time('timestamp');
                    $time_to_send_template_after = $template->frequency * $time;
                    $cart_time = $current_time - $time_to_send_template_after;
                    $to_remain_query = "SELECT * FROM `" . $this->cart_history_table . "` WHERE  cart_expiry < %d AND cart_is_recovered = %d AND order_id IS NULL";
                    $to_remain_histories = $wpdb->get_results($wpdb->prepare($to_remain_query, $cart_time, 0));
                    if (!empty($to_remain_histories)) {
                        foreach ($to_remain_histories as $history) {
                            if (isset($history->cart_contents)) {
                                $cart_details = json_decode($history->cart_contents);
                                if (!empty($cart_details)) {
                                    $history_id = $history->id;
                                    //Check each email template is sent or not
                                    $email_sent_history_query = "SELECT * FROM `" . $this->email_history_table . "` WHERE  template_id = %s AND abandoned_order_id = %d";
                                    $email_sent_history = $wpdb->get_results($wpdb->prepare($email_sent_history_query, $template->id, $history_id));
                                    if (empty($email_sent_history)) {
                                        $user_email = $user_first_name = $user_last_name = '';
                                        $customer_key = $history->customer_key;
                                        if (!is_numeric($customer_key)) {
                                            $query_guest = "SELECT billing_first_name, billing_last_name, email_id FROM `" . $this->guest_cart_history_table . "` WHERE session_id = %s";
                                            $results_guest = $wpdb->get_row($wpdb->prepare($query_guest, $customer_key), OBJECT);
                                            if (!empty($results_guest) && isset($results_guest->email_id)) {
                                                $user_email = $results_guest->email_id;
                                                $user_first_name = $results_guest->billing_first_name;
                                                $user_last_name = $results_guest->billing_last_name;
                                            }
                                        } else if (is_numeric($customer_key)) {
                                            $user_email = get_user_meta($customer_key, 'billing_email', true);
                                            $user_first_name = get_user_meta($customer_key, 'billing_first_name', true);
                                            $user_last_name = get_user_meta($customer_key, 'billing_last_name', true);
                                            $user_data = get_userdata($customer_key);
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
                                        //Process only if user email found
                                        if (!empty($user_email)) {
                                            $customer_name = $user_first_name . ' ' . $user_last_name;
                                            $email_subject = $template->subject;
                                            if (empty($email_subject)) {
                                                $email_subject = 'Hey {{customer_name}} You left something in your cart';
                                            }
                                            $email_subject = str_replace('{{customer_name}}', $customer_name, $email_subject);
                                            $email_body = stripslashes($template->body);
                                            $cart_html = $this->getCartTable($cart_details, $history->currency_code);
                                            //Log about emil sent
                                            $email_sent_query = "INSERT INTO `" . $this->email_history_table . "` ( template_id, abandoned_order_id, sent_time, sent_email_id ) VALUES ( %s, %s, '" . current_time('mysql') . "', %s )";
                                            $wpdb->query($wpdb->prepare($email_sent_query, $template->id, $history_id, $user_email));
                                            $site_url = site_url();
                                            $cart_page_link = $this->wc_functions->getCartUrl();
                                            $need_to_encode = array(
                                                'url' => $cart_page_link,
                                                'email_sent' => $wpdb->insert_id,
                                                'abandoned_cart_id' => $history_id,
                                                'session_id' => $customer_key
                                            );
                                            $encoding_cart = http_build_query($need_to_encode);
                                            $validate_cart = $this->encryptValidate($encoding_cart);
                                            $cart_recovery_link = $site_url . '/?retainful_cart_action=recover&validate=' . $validate_cart;
                                            $replace = array(
                                                'customer_name' => $customer_name,
                                                'site_url' => $site_url,
                                                'cart_recovery_link' => $cart_recovery_link,
                                                'user_cart' => $cart_html,
                                                'site_footer' => '&copy; ' . date('Y') . ' ' . get_bloginfo('name') . __(' All rights reserved.', RNOC_TEXT_DOMAIN)
                                            );
                                            foreach ($replace as $short_code => $short_code_value) {
                                                $email_body = str_replace('{{' . $short_code . '}}', $short_code_value, $email_body);
                                            }
                                            $from_name = (isset($email_templates_settings[RNOC_PLUGIN_PREFIX . 'email_from_name'])) ? $email_templates_settings[RNOC_PLUGIN_PREFIX . 'email_from_name'] : 'Admin';
                                            $admin_email = get_option('admin_email');
                                            $from_address = (isset($email_templates_settings[RNOC_PLUGIN_PREFIX . 'email_from_address'])) ? $email_templates_settings[RNOC_PLUGIN_PREFIX . 'email_from_address'] : $admin_email;
                                            $replay_address = (isset($email_templates_settings[RNOC_PLUGIN_PREFIX . 'email_reply_address'])) ? $email_templates_settings[RNOC_PLUGIN_PREFIX . 'email_reply_address'] : $admin_email;
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
     * get the cart table by the cart data
     * @param $cart_details
     * @param $currency_code
     * @return string
     */
    function getCartTable($cart_details, $currency_code)
    {
        $cart_total = $item_subtotal = $item_total = 0;
        $sub_line_prod_name = $cart_line_items = '';
        $currency_arg = array('currency' => $currency_code);
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
            $item_total_display = $this->wc_functions->formatPrice($item_total, $currency_arg);
            $item_subtotal = $this->wc_functions->formatPrice($item_subtotal, $currency_arg);
            if (function_exists('preg_match')) {
                $product = $this->wc_functions->getProduct($product_id);
                $image_html = $this->wc_functions->getProductImage($product);
                preg_match('@src="([^"]+)"@', $image_html, $match);;
                $image_url = array_pop($match);
            } else {
                $image_url = wp_get_attachment_url(get_post_thumbnail_id($product_id));
            }
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
                                                <td style="padding-top: 20px;padding-bottom: 20px;">' . $product_name . '</td>
                                                <td>' . $quantity_total . '</td>
                                                <td>' . $item_subtotal . '</td>
                                                <td>' . $item_total_display . '</td>
                                            </tr>';
            $cart_total += $item_total;
            $item_subtotal = $item_total = 0;
        }
        $cart_total = $this->wc_functions->formatPrice($cart_total, $currency_arg);
        $cart_html = '
                                        <table width="100%">
                                            <thead>
                                            <tr align="center">
                                                <th style="padding-top: 20px;padding-bottom: 20px;">' . __("Item", RNOC_TEXT_DOMAIN) . '</th>
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
                                            <tr align="right">
                                                <th colspan="4" style="padding-bottom: 10px;padding-top: 10px;">' . __('Cart Total', RNOC_TEXT_DOMAIN) . ':</th>
                                                <td align="center">' . $cart_total . '</td>
                                            </tr>
                                            </tfoot>
                                        </table>
                                        ';
        return $cart_html;
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
     * Clear the cart after X Days
     */
    function clearAbandonedCarts()
    {
        $abandoned_cart_settings = $this->admin->getAdminSettings();
        $cart_expiry_days = intval((isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'delete_abandoned_order_days']) && !empty($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'delete_abandoned_order_days'])) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'delete_abandoned_order_days'] : 90);
        if ($cart_expiry_days) {
            global $wpdb;
            $delete_ac_after_days_time = $cart_expiry_days * 86400;
            $current_time = current_time('timestamp');
            $check_time = $current_time - $delete_ac_after_days_time;
            $query = "SELECT id FROM `" . $this->cart_history_table . "" . "` WHERE cart_is_recovered = 0 AND cart_expiry < %s";
            $carts = $wpdb->get_results($wpdb->prepare($query, $check_time));
            if (!empty($carts)) {
                foreach ($carts as $cart) {
                    $wpdb->delete($this->cart_history_table, array('id' => $cart->id));
                }
            }
        }
        //Remove all hooks
        $this->removeFinishedHooks();
    }

    /**
     * Remove all hooks and schedule once
     * @return bool
     */
    function removeFinishedHooks()
    {
        global $wpdb;
        $wpdb->query("delete from `" . $wpdb->prefix . "posts` where (post_title like '%rnoc_abandoned_cart_send_email%' OR post_title like '%rnoc_abandoned_clear_abandoned_carts%') AND post_status like 'publish' AND post_type='scheduled-action'");
        return true;
    }

    /**
     * delete abandoned cart from admin panel
     */
    function removeAbandonedCart()
    {
        if (isset($_REQUEST['cart_id']) && !empty($_REQUEST['cart_id'])) {
            global $wpdb;
            $row = $wpdb->get_row("SELECT id FROM `" . $this->cart_history_table . "` WHERE id = " . $_REQUEST['cart_id'], OBJECT);
            if (!empty($row)) {
                $wpdb->delete($this->cart_history_table, array('id' => $_REQUEST['cart_id']));
                wp_send_json(array('success' => true));
            }
        }
    }

    /**
     * recover cart info
     * @return mixed
     */
    function recoverUserCart()
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
                    $abandoned_cart_history_query = "SELECT cart_contents,customer_key,currency_code FROM `" . $this->cart_history_table . "` WHERE id = %d AND cart_is_recovered=0";
                    $abandoned_cart_history_results = $wpdb->get_row($wpdb->prepare($abandoned_cart_history_query, $abandoned_cart_id), OBJECT);
                    $user_id = 0;
                    if (!empty($abandoned_cart_history_results)) {
                        $user_id = $abandoned_cart_history_results->customer_key;
                        $this->wc_functions->setPHPSession(RNOC_PLUGIN_PREFIX . 'recovered_cart_id', $abandoned_cart_id);
                        //if guest
                        if (!is_numeric($user_id)) {
                            $this->autoLoadUserCart($abandoned_cart_history_results->cart_contents, $abandoned_cart_id, $session_id);
                        } else {
                            // if registered user
                            $user = wp_set_current_user($user_id);
                            $user_login = $user->data->user_login;
                            wp_set_auth_cookie($user_id);
                            wc_load_persistent_cart($user_login, $user);
                            do_action('wp_login', $user_login, $user);
                            if (isset($sign_in) && is_wp_error($sign_in)) {
                                echo $sign_in->get_error_message();
                                exit;
                            }
                        }
                        apply_filters('rnoc_set_current_currency_code', $abandoned_cart_history_results->currency_code);
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
     * Save cart info to session
     * @param $cart_info
     * @param $abandoned_cart_id
     * @param $session_id
     */
    function autoLoadUserCart($cart_info, $abandoned_cart_id, $session_id)
    {
        global $wpdb;
        $cart_items = $this->wc_functions->getCart();
        $products_in_cart = array();
        if (!empty($cart_items)) {
            foreach ($cart_items as $cart_key => $cart_item) {
                $product_id = (isset($cart_item['variation_id']) && !empty($cart_item['variation_id'])) ? $cart_item['variation_id'] : $cart_item['product_id'];
                $products_in_cart[$product_id] = array('quantity' => $cart_item['quantity'], 'key' => $cart_key);
            }
        }
        $saved_cart = json_decode($cart_info, true);
        if (!empty($saved_cart)) {
            foreach ($saved_cart as $a => $b) {
                $product_id = (isset($b['variation_id']) && !empty($b['variation_id'])) ? $b['variation_id'] : $b['product_id'];
                if (array_key_exists($product_id, $products_in_cart)) {
                    $quantity = $b['quantity'] + $products_in_cart[$product_id]['quantity'];
                    $this->wc_functions->setQuantity($products_in_cart[$product_id]['key'], $quantity);
                } else {
                    $this->wc_functions->addToCart($b['product_id'], $b['variation_id'], $b['quantity'], $b['variation']);
                }
            }
        }
        $current_customer_key = $this->getUserSessionKey();
        $wpdb->update($this->cart_history_table, array('customer_key' => $current_customer_key, 'cart_expiry' => current_time('timestamp'), 'cart_contents' => json_encode($this->wc_functions->getCart())), array('customer_key' => $current_customer_key), array('%s', '%s', '%d'));
        $abandoned_cart_history_query = "SELECT id FROM `" . $this->cart_history_table . "` WHERE id= %d";
        $abandoned_cart_history_results = $wpdb->get_row($wpdb->prepare($abandoned_cart_history_query, $abandoned_cart_id), OBJECT);
        if (!empty($abandoned_cart_history_results)) {
            $wpdb->query('DELETE FROM ' . $this->cart_history_table . ' WHERE id = ' . $abandoned_cart_history_results->id);
        }
        $row = $wpdb->get_row("SELECT id FROM `" . $this->guest_cart_history_table . "` WHERE session_id = '" . $current_customer_key . "'", OBJECT);
        if (empty($row)) {
            $insert_guest = "INSERT INTO `" . $this->guest_cart_history_table . "`(billing_first_name, billing_last_name, billing_company_name, billing_address_1, billing_address_2, billing_city, billing_county, billing_zipcode, email_id, phone, ship_to_billing, order_notes, shipping_first_name, shipping_last_name, shipping_company_name, shipping_address_1, shipping_address_2, shipping_city, shipping_county, shipping_zipcode, shipping_charges)
            SELECT billing_first_name, billing_last_name, billing_company_name, billing_address_1, billing_address_2, billing_city, billing_county, billing_zipcode, email_id, phone, ship_to_billing, order_notes, shipping_first_name, shipping_last_name, shipping_company_name, shipping_address_1, shipping_address_2, shipping_city, shipping_county, shipping_zipcode, shipping_charges FROM `" . $this->guest_cart_history_table . "` WHERE session_id = '" . $session_id . "'";
            $insert = $wpdb->query($insert_guest);
            if ($insert) {
                $wpdb->update($this->guest_cart_history_table, array('session_id' => $current_customer_key), array('id' => $wpdb->insert_id), array('%s'));
            }
        }
    }

    /**
     * When user place the order
     * @param $order_id
     */
    function purchaseComplete($order_id)
    {
        global $wpdb, $woocommerce;
        $current_time = current_time('timestamp');
        if (is_user_logged_in()) {
            $customer_id = get_current_user_id();
        } else {
            //Can't look up the customer in this situation.
            if (!isset($woocommerce->session)) {
                return;
            }
            //$customer_id = $this->wc_functions->getPHPSessionCustomerId();
            $customer_id = $this->getUserSessionKey();
        }
        $row = $wpdb->get_row('SELECT * FROM ' . $this->cart_history_table . ' WHERE customer_key = \'' . $customer_id . '\' AND cart_is_recovered = 0 AND order_id IS NULL LIMIT 1', OBJECT);
        if (!empty($row)) {
            if ($current_time < $row->cart_expiry) {
                $wpdb->update($this->cart_history_table, array(/*'cart_expiry' => $current_time,*/
                    'order_id' => $order_id), array('id' => $row->id), array(/*'%s',*/
                    '%d'));
            } else {
                $wpdb->update($this->cart_history_table, array('cart_is_recovered' => 1,/* 'cart_expiry' => $current_time,*/
                    'order_id' => $order_id), array('id' => $row->id), array('%s',/* '%d',*/
                        '%d')
                );
            }
        }
    }

    /**
     * Notify customer about the order is recovered
     * @param $order_id
     */
    function notifyAdminOnRecovery($order_id)
    {
        $abandoned_cart_settings = $this->admin->getAdminSettings();
        $email_admin_recovery = isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'email_admin_on_recovery']) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'email_admin_on_recovery'] : 0;
        if ($email_admin_recovery) {
            global $wpdb;
            $row = $wpdb->get_row('SELECT * FROM ' . $this->cart_history_table . ' WHERE cart_is_recovered=1 AND order_id = \'' . $order_id . '\' LIMIT 1', OBJECT);
            if (!empty($row)) {
                $recovered_email_sent = get_post_meta($order_id, 'woocommerce_retainful_recovered_email_sent', true);
                if ('yes' != $recovered_email_sent) {
                    $order = $this->wc_functions->getOrder($order_id);
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
            $base_currency = $this->getBaseCurrency();
            $current_time = current_time('timestamp');
            foreach ($cart_histories as $key => $value) {
                $product_details = json_decode($value->cart_contents);
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
                if ($value->cart_is_recovered == 1) {
                    $recovered_carts += 1;
                    $recovered_total += $line_total;
                } else if ($value->cart_expiry > $current_time) {
                } else {
                    $abandoned_cart += 1;
                    $abandoned_total += $line_total;
                }
                if ($base_currency !== $value->currency_code && !empty($value->currency_code) && !empty($base_currency)) {
                    $exchange_rate = $this->getCurrencyRate($value->currency_code);
                    $abandoned_total = $this->convertToCurrency($abandoned_total, $exchange_rate);
                    $recovered_total = $this->convertToCurrency($recovered_total, $exchange_rate);
                }
            }
        }
        return array(
            'recovered_carts' => $recovered_carts,
            'recovered_total' => $this->wc_functions->formatPrice($recovered_total),
            'abandoned_carts' => $abandoned_cart,
            'abandoned_total' => $this->wc_functions->formatPrice($abandoned_total)
        );
    }

    /**
     * Get the abandoned cart of particular timestamp
     * @param $start_date
     * @param $end_date
     * @param bool $count_only
     * @param int $start
     * @param int $limit
     * @param string $cart_type
     * @return array|object|null
     */
    function getAbandonedCartsOfDate($start_date, $end_date, $count_only = false, $start = 0, $limit = 0, $cart_type = 'all')
    {
        if (empty($start_date) || empty($end_date)) {
            return array();
        }
        global $wpdb;
        $blank_cart_info_guest = '[]';
        $blank_cart = '""';
        $start_date = strtotime($start_date . ' 00:01:01');
        $end_date = strtotime($end_date . ' 23:59:59');
        $current_time = current_time('timestamp');
        if ($count_only) {
            $select = 'COUNT(id) as count';
        } else {
            $select = 'id,cart_expiry,cart_contents,cart_is_recovered,NULL AS cart_value,customer_key,ip_address,order_id,viewed_checkout,cart_total,currency_code';
        }
        $offset_limit = '';
        if (!empty($limit)) {
            $offset_limit = 'LIMIT ' . $limit . ' OFFSET ' . $start;
        }
        $get_only = '';
        $abandoned_cart_settings = $this->admin->getAdminSettings();
        $is_tracking_enabled = (isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'])) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'] : 1;
        if (!$is_tracking_enabled) {
            $get_only = ' AND cart_expiry <' . $current_time . ' ';
        }
        if ($cart_type == 'recovered') {
            $get_only = ' AND cart_is_recovered=1 ';
        } else {
            if ($cart_type == 'abandoned') {
                $get_only = ' AND cart_is_recovered=0 AND cart_expiry <' . $current_time . ' ';
            } else if ($cart_type == 'progress') {
                $get_only = ' AND cart_is_recovered=0 AND cart_expiry >' . $current_time . ' ';
            }
        }
        $query = "SELECT $select
                  FROM $this->cart_history_table
                  WHERE cart_contents NOT LIKE '$blank_cart_info_guest' AND cart_contents NOT LIKE '$blank_cart'
                  AND(cart_is_recovered = 1 OR(cart_is_recovered = 0 AND order_id IS NULL))
                  /*AND id IN(
                      SELECT id FROM $this->cart_history_table 
                      WHERE
                          ip_address IS NOT NULL AND ip_address NOT IN(
                              SELECT ip_address FROM  $this->cart_history_table 
                              WHERE ip_address IS NOT NULL GROUP BY ip_address, ROUND(cart_expiry, -1) HAVING COUNT(*) > 1
                          )
                      UNION
                      SELECT id FROM  $this->cart_history_table 
                      WHERE ip_address IS NULL
                  )*/ AND cart_expiry >=  $start_date  AND cart_expiry <= $end_date " . $get_only . "
                ORDER BY cart_expiry DESC " . $offset_limit;
        return $wpdb->get_results($query);
    }

    /**
     * get all email templates
     * @return array|object|null
     */
    function getEmailTemplates()
    {
        global $wpdb;
        $query = "SELECT t.template_name, t.id, t.is_active, t.frequency, t.day_or_hour, t.default_template, t.subject, (select count(id) from {$wpdb->prefix}" . RNOC_PLUGIN_PREFIX . "email_sent_history where template_id = t.id) AS emails_sent FROM {$wpdb->prefix}" . RNOC_PLUGIN_PREFIX . "email_templates AS t;";
        return $wpdb->get_results($query);
    }

    /**
     * get the abandoned cart details
     * @param $start_date
     * @param $end_date
     * @param $offset
     * @param $limit
     * @param $cart_type
     * @return array
     */
    function getCartLists($start_date, $end_date, $offset, $limit, $cart_type)
    {
        return $this->getAbandonedCartsOfDate($start_date, $end_date, false, $offset, $limit, $cart_type);
    }

    /**
     * View cart details
     */
    function viewAbandonedCart()
    {
        if (isset($_REQUEST['cart_id']) && !empty($_REQUEST['cart_id'])) {
            global $wpdb;
            $row = $wpdb->get_row('SELECT * FROM ' . $this->cart_history_table . ' WHERE id = \'' . $_REQUEST['cart_id'] . '\' LIMIT 1', OBJECT);
            if (!empty($row)) {
                $cart_details = json_decode($row->cart_contents);
                if (!empty($cart_details)) {
                    echo
                        '<div style="min-width:600px">
                            ' . $this->getCartTable($cart_details, $row->currency_code) . '
                        </div>';
                    die;
                }
            }
        }
    }

    /**
     * Show GDPR message to guest user
     * @param $fields
     * @return mixed
     */
    function guestGdprMessage($fields)
    {
        $abandoned_cart_settings = $this->admin->getAdminSettings();
        $is_tracking_enabled = (isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'])) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'] : 1;
        if (!is_user_logged_in() && $is_tracking_enabled) {
            if (isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg']) && !empty($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'])) {
                $existing_label = $fields['billing']['billing_email']['label'];
                $fields['billing']['billing_email']['label'] = $existing_label . "<br><small>" . $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'] . "</small>";
            }
        }
        return $fields;
    }

    /**
     * Show GDPR message to logged in users
     */
    function userGdprMessage()
    {
        $abandoned_cart_settings = $this->admin->getAdminSettings();
        $is_tracking_enabled = (isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'])) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'track_real_time_cart'] : 1;
        if (is_user_logged_in() && $is_tracking_enabled) {
            if (isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg']) && !empty($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'])) {
                echo "<p><small>" . __($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'], RNOC_TEXT_DOMAIN) . "</small></p>";
            }
        }
    }

    /**
     * Create or update template view
     */
    function saveEmailTemplate()
    {
        $template_id = 0;
        if (isset($_REQUEST['data']) && !empty($_REQUEST['data'])) {
            $data = $_REQUEST['data'];
            if (isset($data['id'])) {
                $template = $this->getTemplate($data['id']);
                $template_name = sanitize_text_field((isset($data['template_name'])) ? $data['template_name'] : '');
                $subject = sanitize_text_field((isset($data['subject'])) ? $data['subject'] : '');
                $body = stripslashes((isset($data['body'])) ? $data['body'] : '');
                $frequency = intval((isset($data['frequency'])) ? $data['frequency'] : 1);
                $day_or_hour = sanitize_text_field((isset($data['day_or_hour'])) ? $data['day_or_hour'] : 'Hours');
                $is_active = intval((isset($data['active'])) ? $data['active'] : 1);
                global $wpdb;
                if (!empty($template)) {
                    $template_id = $template->id;
                    $query_update = "UPDATE `" . $this->email_templates_table . "` SET template_name=%s, subject=%s, body=%s, frequency=%s, day_or_hour=%s, is_active=%s WHERE id=%d";
                    $wpdb->query($wpdb->prepare($query_update, $template_name, $subject, $body, $frequency, $day_or_hour, $is_active, $template_id));
                } else {
                    $insert_query = "INSERT INTO `" . $this->email_templates_table . "`(template_name, subject, body, frequency, day_or_hour, is_active) VALUES ( %s,%s,%s,%d,%s,%s)";
                    $wpdb->query($wpdb->prepare($insert_query, $template_name, $subject, $body, $frequency, $day_or_hour, $is_active));
                    $template_id = $wpdb->insert_id;
                }
            }
        }
        wp_send_json(array('id' => $template_id, 'success' => true, 'message' => __('Template saved successfully!', RNOC_TEXT_DOMAIN)));
    }

    /**
     * Change the template status
     */
    function changeTemplateStatus()
    {
        $response = array();
        if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
            $template = $this->getTemplate(intval($_REQUEST['id']));
            $is_active = (isset($_REQUEST['is_active'])) ? $_REQUEST['is_active'] : 0;
            if (!empty($template)) {
                global $wpdb;
                $query_update = "UPDATE `" . $this->email_templates_table . "` SET is_active=%s WHERE id=%d";
                $wpdb->query($wpdb->prepare($query_update, $is_active, $template->id));
                if (!empty($is_active)) {
                    $response['message'] = __('Template activated successfully!', RNOC_TEXT_DOMAIN);
                } else {
                    $response['message'] = __('Template de-activated successfully!', RNOC_TEXT_DOMAIN);
                }
            } else {
                $response['error'] = true;
                $response['message'] = __('Invalid Request', RNOC_TEXT_DOMAIN);
            }
        } else {
            $response['error'] = true;
            $response['message'] = __('Invalid Request', RNOC_TEXT_DOMAIN);
        }
        wp_send_json($response);
    }

    /**
     * Remove the template
     */
    function removeTemplate()
    {
        $response = array();
        if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
            $template = $this->getTemplate(intval($_REQUEST['id']));
            if (!empty($template)) {
                global $wpdb;
                $query_update = "DELETE FROM `" . $this->email_templates_table . "` WHERE id=%d";
                $wpdb->query($wpdb->prepare($query_update, $template->id));
            } else {
                $response['error'] = true;
                $response['message'] = __('Invalid Request', RNOC_TEXT_DOMAIN);
            }
        } else {
            $response['error'] = true;
            $response['message'] = __('Invalid Request', RNOC_TEXT_DOMAIN);
        }
        wp_send_json($response);
    }

    /**
     * Remove the template
     */
    function sendSampleEmail()
    {
        $response = array();
        if (isset($_REQUEST['email_to']) && !empty($_REQUEST['email_to']) && isset($_REQUEST['body']) && !empty($_REQUEST['body'])) {
            $template = stripslashes($_REQUEST['body']);
            $send_to = sanitize_text_field($_REQUEST['email_to']);
            $customer_name = 'Customer Name';
            $admin_email = 'test@test.com';
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $customer_name = $current_user->user_login;
                $admin_email = $current_user->user_email;
            }
            $email_subject = sanitize_text_field($_REQUEST['subject']);
            if (empty($email_subject)) {
                $email_subject = 'Hey {{customer_name}} You left something in your cart';
            }
            $email_subject = str_replace('{{customer_name}}', $customer_name, $email_subject);
            $cart_html = '
                <table width="100%">
                    <thead>
                    <tr align="center">
                        <th style="padding-top: 20px;padding-bottom: 20px;">' . __("Item", RNOC_TEXT_DOMAIN) . '</th>
                        <th>' . __("Name", RNOC_TEXT_DOMAIN) . '</th>
                        <th>' . __("Quantity", RNOC_TEXT_DOMAIN) . '</th>
                        <th>' . __("Price", RNOC_TEXT_DOMAIN) . '</th>
                        <th>' . __("Line Subtotal", RNOC_TEXT_DOMAIN) . '</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td><img src="' . RNOC_PLUGIN_URL . 'src/assets/images/sample-product.jpg" width="100px"></td>
                        <td>' . __("Sample product", RNOC_TEXT_DOMAIN) . '</td>
                        <td>' . __("2", RNOC_TEXT_DOMAIN) . '</td>
                        <td>' . $this->wc_functions->formatPrice(100) . '</td>
                        <td>' . $this->wc_functions->formatPrice(200) . '</td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr align="right">
                        <th colspan="4" style="padding-bottom: 10px;padding-top: 10px;">' . __('Cart Total', RNOC_TEXT_DOMAIN) . ':</th>
                        <td align="center">' . $this->wc_functions->formatPrice(200) . '</td>
                    </tr>
                    </tfoot>
                </table>';
            $replace = array(
                'customer_name' => $customer_name,
                'site_url' => '',
                'cart_recovery_link' => '',
                'user_cart' => $cart_html,
                'site_footer' => '&copy; ' . date('Y') . ' ' . get_bloginfo('name') . __(' All rights reserved.', RNOC_TEXT_DOMAIN)
            );
            foreach ($replace as $short_code => $short_code_value) {
                $template = str_replace('{{' . $short_code . '}}', $short_code_value, $template);
            }
            $from_name = 'Admin';
            $from_address = $admin_email;
            $replay_address = $admin_email;
            //Prepare for sending emails
            $headers = "From: " . $from_name . " <" . $from_address . ">" . "\r\n";
            $headers .= "Content-Type: text/html" . "\r\n";
            $headers .= "Reply-To:  " . $replay_address . " " . "\r\n";
            //Send mail
            wc_mail($send_to, $email_subject, $template, $headers);
        } else {
            $response['error'] = true;
            $response['message'] = __('Email address and Email body required', RNOC_TEXT_DOMAIN);
        }
        wp_send_json($response);
    }

    /**
     * Get email template by ID
     */
    function getEmailTemplate()
    {
        $template_id = (isset($_REQUEST['id'])) ? sanitize_key($_REQUEST['id']) : 0;
        $template_type = (isset($_REQUEST['type'])) ? sanitize_key($_REQUEST['type']) : 'free';
        $content = '';
        $success = false;
        if (!empty($template_id) && !empty($template_type)) {
            if ($template_type == "free") {
                if (file_exists(RNOC_PLUGIN_PATH . 'src/admin/templates/default-' . $template_id . '.html')) {
                    ob_start();
                    include RNOC_PLUGIN_PATH . 'src/admin/templates/default-' . $template_id . '.html';
                    $content = ob_get_clean();
                    $success = true;
                } else {
                    $content = __('Sorry, Template not found', RNOC_TEXT_DOMAIN);
                }
            } else {
                $template = apply_filters('rnoc_get_email_template_by_id', $template_id);
                if (!empty($template)) {
                    $content = $template;
                    $success = true;
                } else {
                    $content = __('Sorry, Template not found', RNOC_TEXT_DOMAIN);
                }
            }
        }
        wp_send_json(array('success' => $success, 'content' => $content));
    }

    /**
     * get template by id
     * @param $id
     * @return array|object|null
     */
    function getTemplate($id)
    {
        $id = intval($id);
        global $wpdb;
        $query = "SELECT * FROM `" . $this->email_templates_table . "` WHERE id = %d";
        return $wpdb->get_row($wpdb->prepare($query, $id), OBJECT);
    }

    /**
     * get the active currency code
     * @return String|null
     */
    function getCurrentCurrencyCode()
    {
        $default_currency = $this->getBaseCurrency();
        return apply_filters('rnoc_get_current_currency_code', $default_currency);
    }

    /**
     * Convert price to another price as per currency rate
     * @param $price
     * @param $rate
     * @return float|int
     */
    function convertToCurrency($price, $rate)
    {
        if (!empty($price) && !empty($rate)) {
            return $price / $rate;
        }
        return $price;
    }

    /**
     * get the rate for particular currency code
     * @param $currency_code
     * @return float|null
     */
    function getCurrencyRate($currency_code)
    {
        $val = 0;
        return apply_filters('rnoc_get_currency_rate', $val, $currency_code);
    }

    /**
     * Check the site has multi currency
     * @return bool
     */
    function getBaseCurrency()
    {
        return $this->wc_functions->getDefaultCurrency();
    }
}