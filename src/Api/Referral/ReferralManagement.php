<?php

namespace Rnoc\Retainful\Api\Referral;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;

class ReferralManagement
{
    function printReferralPopup()
    {
        global $retainful;
        $api_key = $retainful::$plugin_admin->getApiKey();
        $secret = $retainful::$plugin_admin->getSecretKey();
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $order_count = wc_get_customer_order_count($user->ID);
            $total_spent = wc_get_customer_total_spent($user->ID);
            $user_arr = array(
                'api_key' => $api_key,
                'accepts_marketing' => '1',
                'email' => strval($user->user_email),
                'first_name' => strval($user->first_name),
                'id' => strval($user->ID),
                'last_name' => strval($user->last_name),
                'order_count' => strval($order_count),
                'tags' => '',
                'total_spent' => strval($total_spent),
            );
        } else {
            $user_arr = array(
                'api_key' => $api_key,
                'accepts_marketing' => '0',
                'email' => '',
                'first_name' => '',
                'id' => '',
                'last_name' => '',
                'order_count' => '',
                'tags' => '',
                'total_spent' => '',
            );
        }
        $data = implode('', $user_arr);
        $digest = hash_hmac('sha256', $data, $secret);
        $account_url = esc_url(get_permalink(get_option('woocommerce_myaccount_page_id')));
        $default_params = array(
            'digest' => $digest,
            'window' => array(
                'is_thank_you_page' => (!empty(is_wc_endpoint_url('order-received'))),
                'customer_id' => $user_arr['id'],
                'customer_email' => $user_arr['email'],
                'login_url' => $account_url,
                'register_url' => $account_url,
            )
        );
        $params = wp_parse_args($user_arr, $default_params);
        include_once plugin_dir_path(RNOC_FILE) . 'src/templates/referral.php';
    }
}