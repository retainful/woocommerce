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
        if (empty($params)) return array();
        $start_date = !empty($params['start_date']) ? $params['start_date'] : '0000-00-00 00:00:00';
        $end_date = !empty($params['end_date']) ? $params['end_date'] : date('Y-m-d H:i:s');
        global $wpdb;
        $query = $wpdb->prepare("SELECT {$wpdb->prefix}posts.ID FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_status != %s AND post_date between %s AND %s ORDER BY ID ASC LIMIT %d", array(0, 'trash', $start_date, $end_date, (int)$params['limit']));
        return $wpdb->get_results($query);

    }


    /**
     * get order count
     * @return string|null
     */
    protected function getProductCount($params)
    {
        if (empty($params)) return null;
        $start_date = !empty($params['start_date']) ? $params['start_date'] : '0000-00-00 00:00:00';
        $end_date = !empty($params['end_date']) ? $params['end_date'] : date('Y-m-d H:i:s');
        global $wpdb;
        $query = $wpdb->prepare("SELECT COUNT(DISTINCT {$wpdb->prefix}posts.ID) FROM {$wpdb->prefix}posts WHERE post_type IN ('product') AND ID > %d AND post_status != %s AND post_date between %s AND %s", array(0, 'trash', $start_date, $end_date));
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
            'limit' => 10,
            'id' => 0,
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
            'items' => array()
        );
        foreach ($products as $product_data) {
            $response['items'][] = $this->getProductData($product_data->ID);
        }
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

    function changeWebHookHeaderProduct($http_args, $product_id, $webhook_id)
    {
        if ($webhook_id <= 0 || !class_exists('WC_Webhook') || !self::$settings->isConnectionActive()) return $http_args;
        try {
            $webhook = new \WC_Webhook($webhook_id);
            $topic = $webhook->get_topic();
            $topic_status = self::$settings->getWebHookStatus();

            if (!isset($topic_status[$topic]) || !$topic_status[$topic]) {
                return $http_args;
            }
            $delivery_url = $webhook->get_delivery_url();
            $site_delivery_url = self::$settings->getDeliveryUrl($topic);

            if ($delivery_url != $site_delivery_url || $product_id <= 0) {
                return $http_args;
            }
            $product_data = $this->getProductData($product_id);

//            self::$settings->logMessage($product_data, 'import product data');
            if (!empty($product_data)) {
                $app_id = self::$settings->getApiKey();
                $extra_headers = array(
                    "X-Retainful-Version" => RNOC_VERSION,
                    "app_id" => $app_id,
                    "Content-Type" => 'application/json'
                );
                foreach ($extra_headers as $key => $value) {
                    $http_args['headers'][$key] = $value;
                }
                $cart_hash = $this->encryptData($product_data);
                $body = array(
                    'data' => $cart_hash
                );
                $http_args['body'] = trim(wp_json_encode($body));
//                self::$settings->logMessage($http_args, 'http import product data');
            }
        } catch (Exception $e) {

        }
        return $http_args;
    }

    protected function getProductData($product_id)
    {
        if (empty($product_id)) return;
        $product = self::$woocommerce->getProduct($product_id);
        $product_variation = array();
        $gallery_image_ids = $product->get_gallery_image_ids();
        $gallery_image = !empty($gallery_image_ids) ? array_map('wp_get_attachment_url', $gallery_image_ids) : [];
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
        $product_data = [
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
            'gallery_image' => $gallery_image,
        ];

        return $product_data;
    }

}