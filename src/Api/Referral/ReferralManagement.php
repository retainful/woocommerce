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
        $rest_api = new RestApi();
        $default_params = array(
            'api_key' => $api_key,
            'order_count' => null,
            'total_spent' => null,
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'id' => null,
            'tags' => array(),
            'digest' => null,
            'accepts_marketing' => null,
            'is_thank_you_page' => (!empty(is_wc_endpoint_url('order-received'))) ? "yes" : "no"
        );
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $order_count = wc_get_customer_order_count($user->ID);
            $total_spent = wc_get_customer_total_spent($user->ID);
            $data = $api_key . 'yes' . $user->user_email . $user->user_firstname . $user->ID . $user->user_lastname . $order_count . $total_spent;
            $secret = $settings->getSecretKey();
            $digest = hash_hmac('sha256', $data, $secret);
            $user_arr = array(
                'order_count' => $order_count,
                'total_spent' => $total_spent,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->user_email,
                'id' => $user->ID,
                'tags' => array(),
                'accepts_marketing' => true,
                'digest' => $digest,
            );
        } else {
            $user_arr = array();
        }
        $params = wp_parse_args($user_arr, $default_params);
        include_once plugin_dir_path(RNOC_FILE) . 'src/templates/referral.php';
    }
}