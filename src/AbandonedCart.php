<?php

namespace Rnoc\Retainful;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Integrations\MultiLingual;

class AbandonedCart
{
    protected static $applied_coupons = NULL;
    public $wc_functions, $admin, $cart_history_table, $email_queue_table, $email_history_table, $email_templates_table, $guest_cart_history_table, $total_order_amount, $total_abandoned_cart_count, $total_recover_amount, $recovered_item;
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
        $this->email_queue_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'abandoned_email_queue';
        $this->email_templates_table = $wpdb->prefix . RNOC_PLUGIN_PREFIX . 'email_templates';
    }

    /**
     * Save guest data for further processing
     */
    function saveGuestData()
    {
        if ($this->canTrackAbandonedCarts() == false) {
            return;
        }
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
     * Schedule email templates to queue
     * @param $cart_id
     * @param $expired_time
     */
    function scheduleEmailTemplate($cart_id, $expired_time)
    {
        global $wpdb;
        $query = "SELECT template.id,template.send_after_time FROM `{$this->email_templates_table}` as template WHERE template.id NOT IN (select template_id FROM `{$this->email_queue_table}` WHERE cart_id = {$cart_id}) AND template.id NOT IN (select template_id FROM `{$this->email_history_table}` WHERE abandoned_order_id = {$cart_id}) AND template.language_code = (SELECT `language_code` FROM `{$this->cart_history_table}` WHERE id={$cart_id}) ORDER BY template.send_after_time ASC LIMIT 1";
        $result = $wpdb->get_row($query);
        if (!empty($result)) {
            $last_sent_email_query = "SELECT sent_time FROM {$this->email_history_table} WHERE abandoned_order_id = {$cart_id} ORDER BY id DESC";
            $last_sent_email = $wpdb->get_row($last_sent_email_query);
            if (!empty($last_sent_email)) {
                $last_sent_time = strtotime($last_sent_email->sent_time);
                $current_template_next_sent_time = current_time('timestamp') + $result->send_after_time;
                $difference = $current_template_next_sent_time - $last_sent_time;
                if ($difference < 0) {
                    $difference = $result->send_after_time;
                }
                $run_at = current_time('timestamp') + $difference;
            } else {
                $run_at = $expired_time + $result->send_after_time;
            }
            $insert_query = "INSERT INTO `{$this->email_queue_table}` (template_id, cart_id, is_completed,run_at) VALUES ('%d','%d','%d','%d')";
            $wpdb->query($wpdb->prepare($insert_query, array($result->id, $cart_id, 0, $run_at)));
        }
    }

    /**
     * Capture the cart and insert the information of the cart into DataBase.
     */
    function userCartUpdated()
    {
        if ($this->canTrackAbandonedCarts() == false) {
            return;
        }
        global $wpdb, $woocommerce;
        $abandoned_cart_settings = $this->admin->getAdminSettings();
        $current_time = current_time('timestamp');
        $cut_off_time_settings = isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time']) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'cart_abandoned_time'] : 60;
        $cart_cut_off_time = intval($cut_off_time_settings) * 60;
        $cart = $woocommerce->cart;
        if (is_user_logged_in()) {
            $customer_id = get_current_user_id();
            $customer_session_key = $this->getUserSessionKey();
            $guest_row = $wpdb->get_row('SELECT id FROM ' . $this->cart_history_table . ' WHERE customer_key = \'' . $customer_session_key . '\' AND cart_is_recovered = 0 AND order_id IS NULL LIMIT 1', OBJECT);
            if (!empty($guest_row)) {
                $wpdb->update(
                    $this->cart_history_table,
                    array('customer_key' => $customer_id),
                    array('id' => $guest_row->id)
                );
            }
        } else {
            //Can't look up the customer in this situation.
            if (!isset($woocommerce->session)) {
                return;
            }
            //$customer_id = $this->wc_functions->getPHPSessionCustomerId();
            $customer_id = $this->getUserSessionKey();
        }
        $currency_code = $this->getCurrentCurrencyCode();
        $language_helper = new MultiLingual();
        $active_language = $language_helper->getCurrentLanguage();
        $language_code = (!empty($active_language)) ? $active_language : $language_helper->getDefaultLanguage();
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
                array('language_code' => $language_code, 'currency_code' => $currency_code, 'customer_key' => $customer_id, 'cart_contents' => json_encode($this->wc_functions->getCart()), 'cart_expiry' => $current_time + $cart_cut_off_time, 'cart_is_recovered' => 0, 'show_on_funnel_report' => 1, 'ip_address' => $_SERVER['REMOTE_ADDR'], 'item_count' => $cart->cart_contents_count, 'cart_total' => $cart->cart_contents_total),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d')
            );
            $this->scheduleEmailTemplate($wpdb->insert_id, $current_time + $cart_cut_off_time);
        } else {
            $update_values = null;
            if (is_checkout() || $current_time - $cart_cut_off_time > $row->cart_expiry) {
                $update_values = array(
                    'cart_contents' => json_encode($this->wc_functions->getCart()),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'item_count' => $cart->cart_contents_count,
                    'cart_total' => $cart->cart_contents_total,
                    'viewed_checkout' => true,
                    'currency_code' => $currency_code,
                    'language_code' => $language_code
                );
            } else {
                $update_values = array(
                    'cart_contents' => json_encode($this->wc_functions->getCart()),
                    /*'cart_expiry' => $current_time,*/
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'item_count' => $cart->cart_contents_count,
                    'cart_total' => $cart->cart_contents_total,
                    'currency_code' => $currency_code,
                    'language_code' => $language_code
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
        if ($this->canTrackAbandonedCarts() == false) {
            return;
        }
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
        if ($this->canTrackAbandonedCarts() == false) {
            return;
        }
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
     * Delete the queue
     * @param $queue_id
     */
    function deleteQueue($queue_id)
    {
        global $wpdb;
        $wpdb->delete($this->email_queue_table, array('id' => $queue_id), $where_format = null);
    }

    /**
     * Automatically send customer recovery email
     * This function will call in every 5 minutes
     */
    function sendAbandonedCartEmails()
    {
        global $wpdb;
        $this->removeFinishedHooks('rnoc_abandoned_cart_send_email', 'publish');
        $current_time = current_time('timestamp');
        $to_remain_history_query = "SELECT history.*,queue.id as queue_id,template.language_code as template_language_code,queue.template_id as template_id,template.subject,template.extra ,template.body FROM `{$this->email_queue_table}` AS queue LEFT JOIN `{$this->cart_history_table}` AS history ON history.id = queue.cart_id LEFT JOIN `{$this->email_templates_table}` as template ON template.id = queue.template_id WHERE queue.is_completed = 0 AND queue.run_at < {$current_time} AND history.cart_is_recovered = 0 AND history.order_id IS NULL";
        $to_remain_histories = $wpdb->get_results($to_remain_history_query);
        if (!empty($to_remain_histories)) {
            $email_templates_settings = $this->admin->getEmailTemplatesSettings();
            foreach ($to_remain_histories as $history) {
                if (isset($history->cart_contents) && $history->template_language_code == $history->language_code) {
                    $cart_details = json_decode($history->cart_contents);
                    if (!empty($cart_details)) {
                        $history_id = $history->id;
                        //Check each email template is sent or not
                        $email_sent_history_query = "SELECT * FROM `" . $this->email_history_table . "` WHERE  template_id = %d AND abandoned_order_id = %d";
                        $email_sent_history = $wpdb->get_results($wpdb->prepare($email_sent_history_query, $history->template_id, $history_id));
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
                                $email_subject = $history->subject;
                                if (empty($email_subject)) {
                                    $email_subject = 'Hey {{customer_name}} You left something in your cart';
                                }
                                $email_subject = str_replace('{{customer_name}}', $customer_name, $email_subject);
                                $email_body = stripslashes($history->body);
                                $cart_html = $this->getCartTable($cart_details, $history->currency_code);
                                //Log about emil sent
                                $email_sent_query = "INSERT INTO `" . $this->email_history_table . "` ( template_id, abandoned_order_id, sent_time, sent_email_id,subject ) VALUES ( %s, %s, %s, %s, %s )";
                                $wpdb->query($wpdb->prepare($email_sent_query, $history->template_id, $history_id, current_time('mysql'), $user_email, $email_subject));
                                $this->scheduleEmailTemplate($history->id, $history->cart_expiry);
                                $this->deleteQueue($history->queue_id);
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
                                $cart_recovery_link = $site_url . '/?retainful_cart_action=recover&validate=' . $validate_cart . '&lang=' . $history->language_code;
                                $extra_fields = (isset($history->extra)) ? $history->extra : '{}';
                                $extra_data = json_decode($extra_fields, true);
                                $selected_coupon = isset($extra_data['coupon_code']) ? $extra_data['coupon_code'] : '';
                                $replace = array(
                                    'customer_name' => $customer_name,
                                    'site_url' => $site_url,
                                    'cart_recovery_link' => $cart_recovery_link,
                                    'user_cart' => $cart_html,
                                    'recovery_coupon' => $selected_coupon,
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
                                $charset = (function_exists('get_bloginfo')) ? get_bloginfo('charset') : 'UTF-8';
                                //Prepare for sending emails
                                $headers = array(
                                    "From: \"$from_name\" <$from_address>",
                                    "Return-Path: <" . $from_address . ">",
                                    "Reply-To: \"" . $from_name . "\" <" . $replay_address . ">",
                                    "MIME-Version: 1.0",
                                    "X-Mailer: PHP" . phpversion(),
                                    "Content-Type: text/html; charset=\"" . $charset . "\""
                                );
                                $header = implode("\n", $headers);
                                //Send mail
                                wc_mail($user_email, $email_subject, $email_body, $header);
                            }
                        }
                    }
                }
            }
        }
        return;
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
        $line_items = array();
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
            $line_items[] = array('name' => $product_name, 'image_url' => $image_url, 'quantity_total' => $quantity_total, 'item_subtotal' => $item_subtotal, 'item_total_display' => $item_total_display);
            $cart_total += $item_total;
            $item_subtotal = $item_total = 0;
        }
        $cart_total = $this->wc_functions->formatPrice($cart_total, $currency_arg);
        $override_path = get_theme_file_path('retainful/templates/abandoned_cart.php');
        $cart_template_path = RNOC_PLUGIN_PATH . 'src/admin/templates/abandoned_cart.php';
        if (file_exists($override_path)) {
            $cart_template_path = $override_path;
        }
        return $this->getTemplateContent($cart_template_path, array('line_items' => $line_items, 'cart_total' => $cart_total));
    }

    /**
     * get the template content
     * @param $path
     * @param array $params
     * @return mixed|null
     */
    function getTemplateContent($path, $params = array())
    {
        if (file_exists($path)) {
            ob_start();
            extract($params);
            include $path;
            return ob_get_clean();
        }
        return NULL;
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
        $this->removeFinishedHooks('rnoc_abandoned_clear_abandoned_carts', 'publish');
    }

    /**
     * All the available scheduled actions post name
     * @return array
     */
    protected function availableScheduledActions()
    {
        return array('rnocp_check_user_plan', 'rnoc_abandoned_clear_abandoned_carts', 'rnoc_abandoned_cart_send_email');
    }
    /**
     * Remove all hooks and schedule once
     * @param $post_title
     * @param $status
     * @return bool
     */
    function removeFinishedHooks($post_title, $status = "")
    {
        $available_action_names = $this->availableScheduledActions();
        if (!empty($post_title) && !in_array($post_title, $available_action_names)) {
            return false;
        }
        global $wpdb;
        $res = true;
        $where = "";
        if (!empty($status)) {
            $where = "AND post_status = '" . $status . "'";
        }
        $scheduled_actions = $wpdb->get_results("SELECT ID from `" . $wpdb->prefix . "posts` where post_title ='" . $post_title . "' {$where} AND  post_type='scheduled-action'");
        if (!empty($scheduled_actions)) {
            foreach ($scheduled_actions as $action) {
                if (!wp_delete_post($action->ID, true)) {
                    $res = false;
                }
            }
        }
        return $res;
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
     * delete multiple abandoned cart from admin panel
     */
    function removeAbandonedCartMultiple()
    {
        if (isset($_REQUEST['cart_list']) && is_array($_REQUEST['cart_list']) && !empty($_REQUEST['cart_list'])) {
            global $wpdb;
            foreach ($_REQUEST['cart_list'] as $cart_id) {
                $row = $wpdb->get_row("SELECT id FROM `" . $this->cart_history_table . "` WHERE id = " . $cart_id, OBJECT);
                if (!empty($row)) {
                    $wpdb->delete($this->cart_history_table, array('id' => $cart_id));
                }
            }
            wp_send_json(array('success' => true));
        }
    }

    /**
     * recover cart info
     */
    function recoverUserCart()
    {
        if (isset($_GET['retainful_cart_action']) && isset($_GET['validate'])) {
            if ($_GET['retainful_cart_action'] == 'recover' && !empty($_GET['validate'])) {
                global $wpdb;
                $this->wc_functions->startPHPSession();
                $cart_link = $this->decryptValidate($_GET['validate']);
                parse_str($cart_link);
                if (isset($url) && isset($abandoned_cart_id) && isset($session_id) && isset($email_sent)) {
                    $abandoned_cart_history_query = "SELECT cart_contents,customer_key,currency_code FROM `" . $this->cart_history_table . "` WHERE id = %d AND cart_is_recovered=0";
                    $abandoned_cart_history_results = $wpdb->get_row($wpdb->prepare($abandoned_cart_history_query, $abandoned_cart_id), OBJECT);
                    $user_id = 0;
                    if (!empty($abandoned_cart_history_results)) {
                        $user_id = $abandoned_cart_history_results->customer_key;
                        $this->wc_functions->setPHPSession(RNOC_PLUGIN_PREFIX . 'recovered_cart_id', $abandoned_cart_id);
                        $this->autoLoadUserCart($abandoned_cart_history_results->cart_contents, $abandoned_cart_id, $session_id);
                        apply_filters('rnoc_set_current_currency_code', $abandoned_cart_history_results->currency_code);
                    }
                    if (empty($user_id)) {
                        wc_add_notice(__('It seems, your cart has expired!'),'error');
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
                    $admin_email = get_option('admin_email');
                    $charset = (function_exists('get_bloginfo')) ? get_bloginfo('charset') : 'UTF-8';
                    $headers = array(
                        "From: \"Admin\" <$admin_email>",
                        "Return-Path: <" . $admin_email . ">",
                        "Reply-To: \"Admin\" <" . $admin_email . ">",
                        "MIME-Version: 1.0",
                        "X-Mailer: PHP" . phpversion(),
                        "Content-Type: text/html; charset=\"" . $charset . "\""
                    );
                    $header = implode("\n", $headers);
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
                    wc_mail($admin_email, $email_subject, $email_body, $header);
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
        if ($this->canTrackAbandonedCarts() == false) {
            return;
        }
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
     * @param string $show_only
     * @return array|object|null
     */
    function getAbandonedCartsOfDate($start_date, $end_date, $count_only = false, $start = 0, $limit = 0, $cart_type = 'all', $show_only = 'all')
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
        if ($cart_type == "guest_cart") {
            $get_only = ' AND Length(customer_key) > 31 ';
        } elseif ($cart_type == "registered_cart") {
            $get_only = ' AND Length(customer_key) < 31 ';
        } elseif ($cart_type == 'recovered') {
            $get_only = ' AND cart_is_recovered=1 ';
        } else {
            if ($cart_type == 'abandoned') {
                $get_only = ' AND cart_is_recovered=0 AND cart_expiry <' . $current_time . ' ';
            } else if ($cart_type == 'progress') {
                $get_only = ' AND cart_is_recovered=0 AND cart_expiry >' . $current_time . ' ';
            }
        }
        $show_guest_cart = intval((isset($abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'show_guest_cart_in_dashboard'])) ? $abandoned_cart_settings[RNOC_PLUGIN_PREFIX . 'show_guest_cart_in_dashboard'] : 1);
        if (empty($show_guest_cart)) {
            $get_only = ' AND Length(customer_key) < 32 ';
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
     * Empty the abandoned cart history table
     */
    function emptyTheAbandonedCartHistory()
    {
        global $wpdb;
        $truncate_query = "TRUNCATE TABLE `{$this->cart_history_table}`";
        $wpdb->query($truncate_query);
        wp_send_json(array('success' => true));
    }

    /**
     * Empty the abandoned cart history table
     */
    function emptyTheQueueTable()
    {
        global $wpdb;
        $truncate_query = "TRUNCATE TABLE `{$this->email_queue_table}`";
        $wpdb->query($truncate_query);
        wp_send_json(array('success' => true));
    }

    /**
     * get all email templates
     * @param $language_code string
     * @return array|object|null
     */
    function getEmailTemplates($language_code = NULL)
    {
        global $wpdb;
        $query = "SELECT t.template_name, t.id, t.is_active, t.frequency, t.day_or_hour, t.default_template, t.subject, (select count(id) from {$wpdb->prefix}" . RNOC_PLUGIN_PREFIX . "email_sent_history where template_id = t.id) AS emails_sent FROM {$wpdb->prefix}" . RNOC_PLUGIN_PREFIX . "email_templates AS t ";
        if (!empty($language_code)) {
            $query .= " WHERE t.language_code='{$language_code}'";
        }
        $query .= ' ORDER BY t.send_after_time ASC';
        return $wpdb->get_results($query);
    }

    /**
     * get the abandoned cart details
     * @param $start_date
     * @param $end_date
     * @param $offset
     * @param $limit
     * @param $cart_type
     * @param $show_only
     * @return array
     */
    function getCartLists($start_date, $end_date, $offset, $limit, $cart_type, $show_only)
    {
        return $this->getAbandonedCartsOfDate($start_date, $end_date, false, $offset, $limit, $cart_type, $show_only);
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
        $data = $_REQUEST;
        if (isset($data['id'])) {
            $template = $this->getTemplate($data['id']);
            $template_name = sanitize_text_field((isset($data['template_name'])) ? $data['template_name'] : '');
            $subject = sanitize_text_field((isset($data['subject'])) ? $data['subject'] : '');
            $body = stripslashes((isset($data['body'])) ? $data['body'] : '');
            $frequency = intval((isset($data['frequency'])) ? $data['frequency'] : 1);
            $day_or_hour = sanitize_text_field((isset($data['day_or_hour'])) ? $data['day_or_hour'] : 'Hours');
            $time = ($day_or_hour == "Days") ? 86400 : 3600;
            $time_to_send_template_after = $frequency * $time;
            $is_active = intval((isset($data['active'])) ? $data['active'] : 1);
            $extra = isset($data['extra']) && is_array($data['extra']) ? json_encode($data['extra']) : '{}';
            $lang_helper = new MultiLingual();
            $default_lang = $lang_helper->getDefaultLanguage();
            $language_code = sanitize_text_field((isset($data['language_code'])) ? $data['language_code'] : $default_lang);
            global $wpdb;
            if (!empty($template)) {
                $template_id = $template->id;
                $query_update = "UPDATE `" . $this->email_templates_table . "` SET template_name=%s, subject=%s, body=%s, frequency=%s, day_or_hour=%s, is_active=%s,extra=%s, language_code=%s, send_after_time=%d WHERE id=%d";
                $wpdb->query($wpdb->prepare($query_update, $template_name, $subject, $body, $frequency, $day_or_hour, $is_active, $extra, $language_code, $time_to_send_template_after, $template_id));
            } else {
                $insert_query = "INSERT INTO `" . $this->email_templates_table . "`(template_name, subject, body, frequency, day_or_hour, is_active,language_code,extra,send_after_time) VALUES ( %s,%s,%s,%d,%s,%s,%s,%s,%d)";
                $wpdb->query($wpdb->prepare($insert_query, $template_name, $subject, $body, $frequency, $day_or_hour, $is_active, $language_code, $extra, $time_to_send_template_after));
                $template_id = $wpdb->insert_id;
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
            $coupon_code = stripslashes(isset($_REQUEST['coupon_code']) ? $_REQUEST['coupon_code'] : '');
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
            $override_path = get_theme_file_path('retainful/templates/abandoned_cart.php');
            $cart_template_path = RNOC_PLUGIN_PATH . 'src/admin/templates/abandoned_cart.php';
            if (file_exists($override_path)) {
                $cart_template_path = $override_path;
            }
            $line_items[] = array('name' => 'Sample product', 'image_url' => RNOC_PLUGIN_URL . 'src/assets/images/sample-product.jpg', 'quantity_total' => 2, 'item_subtotal' => $this->wc_functions->formatPrice(100), 'item_total_display' => $this->wc_functions->formatPrice(200));
            $cart_html = $this->getTemplateContent($cart_template_path, array('line_items' => $line_items, 'cart_total' => $this->wc_functions->formatPrice(200)));
            $replace = array(
                'customer_name' => $customer_name,
                'site_url' => '',
                'recovery_coupon' => $coupon_code,
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
            $charset = (function_exists('get_bloginfo')) ? get_bloginfo('charset') : 'UTF-8';
            //Prepare for sending emails
            $headers = array(
                "From: \"$from_name\" <$from_address>",
                "Return-Path: <" . $from_address . ">",
                "Reply-To: \"" . $from_name . "\" <" . $replay_address . ">",
                "MIME-Version: 1.0",
                "X-Mailer: PHP" . phpversion(),
                "Content-Type: text/html; charset=\"" . $charset . "\""
            );
            $header = implode("\n", $headers);
            //Send mail
            wc_mail($send_to, $email_subject, $template, $header);
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

    /**
     * get the emails sent history
     * @param $start
     * @param $limit
     * @param string $order_by
     * @param string $order_by_value
     * @return array|object|null
     */
    function sentEmailsHistory($start, $limit, $order_by = "sent_time", $order_by_value = "DESC")
    {
        global $wpdb;
        $email_sent_history_query = "SELECT history.sent_time,history.sent_email_id,(CASE WHEN history.subject IS NULL THEN template.subject ELSE history.subject END) as subject FROM `{$this->email_history_table}` as history LEFT JOIN `{$this->email_templates_table}` as template ON history.template_id = template.id ORDER BY {$order_by} {$order_by_value} LIMIT {$limit} OFFSET {$start}";
        return $wpdb->get_results($email_sent_history_query);
    }

    /**
     * get the count of number of emails sent
     * @return mixed
     */
    function getTotalEmailsSent()
    {
        global $wpdb;
        $email_sent_history_count_query = "SELECT count(id) as total_mails_sent FROM `" . $this->email_history_table . "`";
        $count = $wpdb->get_row($email_sent_history_count_query);
        return $count->total_mails_sent;
    }

    /**
     * need to track carts or not
     */
    function canTrackAbandonedCarts()
    {
        if (!apply_filters('rnoc_can_track_abandoned_carts', true)) {
            return false;
        }
        return true;
    }
}