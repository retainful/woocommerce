<?php

namespace Rnoc\Retainful\Api\Referral;
class ReferralManagement
{
    /**
     * create coupons
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    static function getCustomer(\WP_REST_Request $request)
    {
        global $retainful;
        $requestParams = $request->get_params();
        $defaultRequestParams = array(
            'email' => '',
            'digest' => ''
        );
        $params = wp_parse_args($requestParams, $defaultRequestParams);
        if (!empty($params['email']) && is_email($params['email']) && is_string($params['digest']) && !empty($params['digest'])) {
            $secret = $retainful::$plugin_admin->getSecretKey();
            $reverse_hmac = hash_hmac('sha256', $params['email'], $secret);
            if (hash_equals($reverse_hmac, $params['digest'])) {
                $user = get_user_by_email($params['email']);
                if (!empty($user) && $user instanceof \WP_User) {
                    $status = 200;
                    $order_count = wc_get_customer_order_count($user->ID);
                    $total_spent = wc_get_customer_total_spent($user->ID);
                    $response = array(
                        'id' => strval($user->ID),
                        'email' => strval($user->user_email),
                        'first_name' => strval($user->first_name),
                        'last_name' => strval($user->last_name),
                        'accepts_marketing' => '1',
                        'order_count' => strval($order_count),
                        'total_spent' => strval($total_spent),
                        'tags' => '',
                        'success' => true,
                        'RESPONSE_CODE' => 'CUSTOMER_DATA_FOUND'
                    );
                } else {
                    $status = 404;
                    $response = array('success' => false, 'RESPONSE_CODE' => 'CUSTOMER_NOT_FOUND', 'message' => 'Customer not found!');
                }
            } else {
                $status = 400;
                $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security breached!');
            }
        } else {
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!');
        }
        return new \WP_REST_Response($response, $status);
    }

    /**
     * echo the referral div
     */
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