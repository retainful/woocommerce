<?php

namespace Rnoc\Retainful\Api\Referral;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\WcFunctions;

class ReferralManagement
{
    /**
     * create coupons
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    static function getCustomer(\WP_REST_Request $request)
    {
        $admin = new Settings();
        $wc = new WcFunctions();
        $requestParams = $request->get_params();
        $defaultRequestParams = array(
            'email' => '',
            'digest' => ''
        );
        $params = wp_parse_args($requestParams, $defaultRequestParams);
        $admin->logMessage($params, 'Customer API request params ');
        if (!empty($params['email']) && is_email($params['email']) && is_string($params['digest']) && !empty($params['digest'])) {
            $secret = $admin->getSecretKey();
            $reverse_hmac = hash_hmac('sha256', $params['email'], $secret);
            if (hash_equals($reverse_hmac, $params['digest'])) {
                $admin->logMessage($reverse_hmac, 'customer API request digest matched');
                $user = get_user_by_email($params['email']);
                $status = 200;
                if (!empty($user) && $user instanceof \WP_User) {
                    $order_count = $wc->getCustomerTotalOrders($user->user_email);
                    $total_spent = $wc->getCustomerTotalSpent($user->user_email);
                    $response = array(array(
                        'id' => strval($user->ID),
                        'email' => strval($user->user_email),
                        'first_name' => strval($user->first_name),
                        'last_name' => strval($user->last_name),
                        'accepts_marketing' => '1',
                        'order_count' => strval($order_count),
                        'total_spent' => strval($total_spent),
                        'tags' => '',
                    ));
                    $admin->logMessage($user, 'API request customer found');
                } else {
                    $response = array();
                    $admin->logMessage(array(), 'API request customer not found');
                }
            } else {
                $admin->logMessage($reverse_hmac, 'API request digest not matched');
                $status = 400;
                $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security breached!');
            }
        } else {
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!');
        }
        $admin->logMessage($response, 'API request response');
        return new \WP_REST_Response($response, $status);
    }

    /**
     * get the referral url
     * @return mixed|void
     */
    function getReferralUrl()
    {
        return apply_filters('referral_engine_url', 'https://js.retainful.com/woocommerce/v1/widget.js');
    }
    /**
     * echo the embeded referral div
     */
    function printEmbededReferralPopup()
    {
        echo '<div class="retainful-referral-embed-widget" id="retainful-referral-embed-widget"></div>';
    }

    /**
     * echo the referral div
     */
    function printReferralPopup()
    {
        global $wp;
        $admin = new Settings();
        $wc = new WcFunctions();
        $api_key = $admin->getApiKey();
        $secret = $admin->getSecretKey();
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if($admin->needReferralWidget()){
                $total_spent = $wc->getCustomerTotalSpentFromSession($user->user_email);
                $order_count = $wc->getCustomerTotalOrdersFromSession($user->user_email);
            }
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
        $window_obj_email = $user_arr['email'];
        $is_thank_you_page = (!empty(is_wc_endpoint_url('order-received')));
        $order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 0;
        if ($is_thank_you_page) {
            $wc->removeTotalOrdersAndSpentFromSession();
        }
        if ($is_thank_you_page && empty($window_obj_email) && !empty($order_id)) {
            $order = wc_get_order($order_id);
            if ($order instanceof \WC_Order) {
                $window_obj_email = $wc->getBillingEmail($order);
            }
        }
        $default_params = array(
            'digest' => $digest,
            'referral_url' => $this->getReferralUrl(),
            'window' => array(
                'is_thank_you_page' => $is_thank_you_page,
                'customer_id' => $user_arr['id'],
                'customer_email' => $window_obj_email,
                'login_url' => apply_filters('rnoc_referral_login_url', $account_url),
                'register_url' => apply_filters('rnoc_referral_register_url', $account_url),
            )
        );
        $params = wp_parse_args($user_arr, $default_params);
        include_once plugin_dir_path(RNOC_FILE) . 'src/templates/referral.php';
    }
}