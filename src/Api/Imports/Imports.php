<?php

namespace Rnoc\Retainful\Api\Imports;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\WcFunctions;

class Imports
{
    /**
     * create coupons
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    static function getCustomers(\WP_REST_Request $request)
    {
        $admin = new Settings();
        $request_params = $request->get_params();
        $default_request_params = array(
            'limit' => 10,
            'offset' => 0,
            'status' => 'any',
            'digest' => ''
        );
        $params = wp_parse_args($request_params, $default_request_params);
        $admin->logMessage($params, 'API Customers get request');
        if(!is_array($params['limit']) || empty($params['digest']) || !is_string($params['digest']) || empty($params['limit']) || $params['offset'] < 0 || $params['status'] != 'any'){
            $admin->logMessage($params, 'API Customers data missing');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!');
            return new \WP_REST_Response($response, $status);
        }
        $admin->logMessage($params, 'API Customers data matched');
        $secret = $admin->getSecretKey();
        $reverse_hmac = hash_hmac('sha256', json_encode(array($params['limit'],$params['offset'],$params['status'])), $secret);
        if (!hash_equals($reverse_hmac, $params['digest'])) {
            $admin->logMessage($reverse_hmac, 'API Customers request digest not matched');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security breached!');
            return new \WP_REST_Response($response, $status);
        }
        $admin->logMessage($reverse_hmac, 'API Customers request digest matched');

        $customer_query = new \WP_User_Query(array('orderby' => 'ID', 'order' => 'ASC','offset' => $params['offset'], 'number' => $params['limit']));
        //Do like his response

        $response = array();
        $status = 200;
        return new \WP_REST_Response($response, $status);
    }
    /**
     * create coupons
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    static function getOrders(\WP_REST_Request $request)
    {
        $admin = new Settings();
        $request_params = $request->get_params();
        $default_request_params = array(
            'limit' => 10,
            'offset' => 0,
            'status' => 'any',
            'digest' => ''
        );
        $params = wp_parse_args($request_params, $default_request_params);
        $admin->logMessage($params, 'API Orders get request');
        if(!is_array($params['limit']) || empty($params['digest']) || !is_string($params['digest']) || empty($params['limit']) || $params['offset'] < 0 || $params['status'] != 'any'){
            $admin->logMessage($params, 'API Orders data missing');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!');
            return new \WP_REST_Response($response, $status);
        }
        $admin->logMessage($params, 'API Orders data matched');
        $secret = $admin->getSecretKey();
        $reverse_hmac = hash_hmac('sha256', json_encode(array($params['limit'],$params['offset'],$params['status'])), $secret);
        if (!hash_equals($reverse_hmac, $params['digest'])) {
            $admin->logMessage($reverse_hmac, 'API Orders request digest not matched');
            $status = 400;
            $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security breached!');
            return new \WP_REST_Response($response, $status);
        }
        $admin->logMessage($reverse_hmac, 'API Orders request digest matched');
        $orders = wc_get_orders(array('orderby' => 'id', 'order' => 'ASC','offset' => $params['offset'], 'limit' => $params['limit']));
        //Do like his response
        $response = array();
        $status = 200;
        return new \WP_REST_Response($response, $status);
    }

}