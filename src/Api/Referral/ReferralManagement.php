<?php

namespace Rnoc\Retainful\Api\Referral;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;

class ReferralManagement
{
    function printReferralPopup()
    {
        $settings = new Settings();
        $api_key = $settings->getApiKey();
        $secret = $settings->getSecretKey();
        $rest_api = new RestApi();
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
        $default_params = array(
            'digest' => $digest,
            'is_thank_you_page' => (!empty(is_wc_endpoint_url('order-received'))) ? "yes" : "no"
        );
        $params = wp_parse_args($user_arr, $default_params);
        include_once plugin_dir_path(RNOC_FILE) . 'src/templates/referral.php';
    }
}