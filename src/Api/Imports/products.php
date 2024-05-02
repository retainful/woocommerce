<?php

namespace Rnoc\Retainful\Api\Imports;
if (!defined('ABSPATH')) exit;


use Rnoc\Retainful\Api\AbandonedCart\Order;

class products extends Order
{
    /**
     * Hash verification
     * @param $data
     * @param $hash_value
     * @return bool
     */
    protected function hashVerification($data, $hash_value)
    {

        if (!is_array($data) || !is_string($hash_value)) {
            return false;
        }
        $data = json_encode($data);
        $secret = self::$settings->getSecretKey();
        $reverse_hmac = hash_hmac('sha256', $data, $secret);
        return hash_equals($reverse_hmac, $hash_value);
    }

    /**
     * get orders
     * @param $params
     * @return array
     */
    protected function getProducts($params)
    {
        $start_date = !empty($params['start_date']) ? $params['start_date'] : '0000-00-00 00:00:00';
        $end_date = !empty($params['end_date']) ? $params['end_date'] : date('Y-m-d H:i:s');
        global $wpdb;
        $query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_date between %s AND %s ORDER BY ID ASC LIMIT %d", array(0, $start_date, $end_date, (int)$params['limit']));
        return $wpdb->get_results($query);

    }

    protected function getProductData($product_post_data)
    {
        if (empty($product_post_data) && !is_array($product_post_data)) return;
        $product_data = [];
        foreach ($product_post_data as $data) {
            $product = self::$woocommerce->getProduct($data->ID);
            $product_variation = array();
            // $vendor_id = self::$woocommerce->getProduct($data->ID, '_wcpv_vendor_id');
            $vendor_id = get_post_meta($data->ID, '_wcpv_vendor_id', true);
            $vendor_name = get_the_title($vendor_id);
            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();
                foreach ($variations as $variation) {
                    $variation_id = $variation['variation_id'];
                    $variation_obj = wc_get_product($variation_id);
                    $product_variation[] = [
                        'variation_id' => $variation_id,
                        'variation_name' => $variation_obj->get_name(),
                        'variation_description' => $variation_obj->get_description(),
                        'variation_price' => $variation_obj->get_price(),
                        'variation_sku' => $variation_obj->get_sku(),
                        'variation_stock_quantity' => $variation_obj->get_stock_quantity(),
                        'variation_image' => $variation_obj->get_image(),
                        'variation_total_sales' => $variation_obj->get_total_sales(),
                    ];
                }
            }

            $product_data[] = [
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'product_description' => $product->get_description(),
                'product_price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'status' => $product->get_status(),
                'product_sku' => $product->get_sku(),
                'product_stock_quantity' => $product->get_stock_quantity(),
                'product_image' => $product->get_image(),
                'product_category' => wp_get_post_terms($product->get_id(), 'product_cat'),
                'product_tag' => wp_get_post_terms($product->get_id(), 'product_tag'),
                'total_sales' => $product->get_total_sales(),
                'variant' => $product_variation,
                'vendor' => $vendor_name
            ];
        }

        return $product_data;
    }

    /**
     * get order count
     * @return string|null
     */
    protected function getProductCount($params)
    {
        global $wpdb;
        $start_date = !empty($params['start_date']) ? $params['start_date'] : '0000-00-00 00:00:00';
        $end_date = !empty($params['end_date']) ? $params['end_date'] : date('Y-m-d H:i:s');
        $query = $wpdb->prepare("SELECT COUNT(DISTINCT {$wpdb->prefix}posts.ID) FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_date between %s AND %s", array(0, $start_date, $end_date));
        return $wpdb->get_var($query);
    }

    /**
     * create coupons
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    function getSyncProducts(\WP_REST_Request $request)
    {
        $request_params = $request->get_params();
        $default_request_params = array(
            'limit' => 30,
            'since_id' => 0,
            'status' => 'any',
            'digest' => ''
        );
        $params = wp_parse_args($request_params, $default_request_params);
        self::$settings->logMessage($params, 'API Orders get request');
//        if (is_array($params['limit']) || empty($params['digest']) || !is_string($params['digest']) || empty($params['limit']) || $params['since_id'] < 0 || $params['status'] != 'any') {
//            self::$settings->logMessage($params, 'API Orders data missing');
//            $status = 400;
//            $response = array('success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!');
//            return new \WP_REST_Response($response, $status);
//        }
//        self::$settings->logMessage($params, 'API Orders data matched');
//        if (!$this->hashVerification(array('limit' => (int)$params['limit'], 'since_id' => (int)$params['since_id'], 'status' => (string)$params['status']), $params['digest'])) {
//            self::$settings->logMessage($params, 'API Orders request digest not matched');
//            $status = 400;
//            $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security validation failed');
//            return new \WP_REST_Response($response, $status);
//        }
        $products = $this->getProducts($params);

        //Do like his response
        $response = array(
            'success' => true,
            'RESPONSE_CODE' => 'Ok',
            'items' => $this->getProductData($products)
        );
        $status = 200;
        return new \WP_REST_Response($response, $status);
    }

    /**
     * Get Order Count via Rest api
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getSyncProductCount(\WP_REST_Request $request)
    {
        $request_params = $request->get_params();
        $default_request_params = array(
            'status' => 'any',
            'digest' => ''
        );
        $params = wp_parse_args($request_params, $default_request_params);
//        self::$settings->logMessage($params, 'API Orders get request');
//        if (empty($params['digest']) || !is_string($params['digest']) || $params['status'] != 'any') {
//            self::$settings->logMessage($params, 'API Order Count data missing');
//            $status = 400;
//            $response = array('success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!');
//            return new \WP_REST_Response($response, $status);
//        }
//        self::$settings->logMessage($params, 'API Order Count data matched');
//        if (!$this->hashVerification(array('status' => $params['status']), $params['digest'])) {
//            self::$settings->logMessage($params, 'API Order Count request digest not matched');
//            $status = 400;
//            $response = array('success' => false, 'RESPONSE_CODE' => 'SECURITY_BREACH', 'message' => 'Security validation failed!');
//            return new \WP_REST_Response($response, $status);
//        }
        $response = array(
            'success' => true,
            'RESPONSE_CODE' => 'Ok',
            'total_count' => (int)$this->getProductCount($params)
            //'total_count' => is_array($orders) ? count($orders) : 0
        );
        $status = 200;
        return new \WP_REST_Response($response, $status);
    }

}