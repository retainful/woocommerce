<?php

namespace Rnoc\Retainful\Api\NextOrderCoupon;

use Valitron\Validator;

class CouponManagement
{
    static function validateRestCoupon($data, &$validate, &$errors)
    {
        $validator = new Validator($data);
        $validator->rule('slug', array(
            'coupon_code',
        ));
        $validator->rule('dateFormat', 'expiry_date', 'Y-m-d');
        $validator->rule('in', array(
            'individual_use',
            'free_shipping',
        ), array('yes', 'no'));
        $validator->rule('in', array(
            'discount_type',
        ), array('percent', 'fixed_cart', 'fixed_product'));
        $validator->rule('array', array(
            'product_ids',
            'exclude_product_ids',
            'product_categories',
            'exclude_product_categories',
        ));
        $validator->rule('numeric', array(
            'coupon_amount',
            'minimum_amount',
            'maximum_amount',
        ));
        $validator->rule('integer', array(
            'usage_limit',
            'usage_limit_per_user',
            'product_ids.*',
            'exclude_product_ids.*',
            'product_categories.*',
            'exclude_product_categories.*',
        ));
        $validator->rule('email', 'customer_email');
        $validate = $validator->validate();
        $errors = $validator->errors();
    }

    /**
     * create coupons
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    static function createRestCoupon(\WP_REST_Request $request)
    {
        $defaultParams = array(
            'coupon_code' => null,
            'discount_type' => 'fixed_cart',
            'free_shipping' => 'no',
            'coupon_amount' => 0,
            'minimum_amount' => 0,
            'maximum_amount' => 0,
            'expiry_date' => null,
            'individual_use' => 'yes',
            'usage_limit' => 1,
            'usage_limit_per_user' => 1,
            'customer_email' => null,
            'product_ids' => array(),
            'exclude_product_ids' => array(),
            'product_categories' => array(),
            'exclude_product_categories' => array(),
        );
        $requestParams = $request->get_params();
        $params = wp_parse_args($requestParams, $defaultParams);
        $is_valid_data = true;
        $errors = array();
        self::validateRestCoupon($params, $is_valid_data, $errors);
        if ($is_valid_data) {
            $old_coupon = self::getCouponByCouponCode($params['coupon_code']);
            if (!empty($old_coupon) && $old_coupon instanceof \WP_Post) {
                $coupon_id = $old_coupon->ID;
            } else {
                $new_coupon = array(
                    'post_title' => $params['coupon_code'],
                    'post_name' => $params['coupon_code'] . '-' . rand(1, 2000000),
                    'post_content' => '',
                    'post_type' => 'shop_coupon',
                    'post_status' => 'publish'
                );
                $coupon_id = wp_insert_post($new_coupon, true);
            }
            $status = 200;
            if (!empty($coupon_id)) {
                if (!empty($params)) {
                    foreach ($params as $meta_key => $meta_value) {
                        if (in_array($meta_key, array('product_ids', 'exclude_product_ids')) && is_array($meta_value)) {
                            $meta_value = implode(',', $meta_value);
                        }
                        update_post_meta($coupon_id, $meta_key, $meta_value);
                    }
                }
                $response = array('success' => true, 'CODE' => 'COUPON_CREATED_OR_UPDATE', 'message' => 'Coupon code created or updated successfully!');
            } else {
                $response = array('success' => false, 'CODE' => 'UNABLE_TO_CREATE_OR_UPDATE', 'message' => 'Coupon code was not created!');
            }
        } else {
            $status = 400;
            $response = $errors;
        }
        return new \WP_REST_Response($response, $status);
    }

    /**
     * @param $coupon_code
     * @return Object|null| \WP_Post
     */
    static function getCouponByCouponCode($coupon_code)
    {
        $coupon_code = sanitize_text_field($coupon_code);
        if (empty($coupon_code)) return NULL;
        $post_args = array('post_type' => array('shop_coupon'), 'numberposts' => '1', 'title' => $coupon_code);
        $posts = get_posts($post_args);
        if (!empty($posts)) {
            foreach ($posts as $post) {
                if (strtoupper($post->post_title) == strtoupper($coupon_code)) {
                    return $post;
                }
            }
        }
        return NULL;
    }

    /**
     * link to view retainful coupon
     * @param $types
     * @return mixed
     */
    function viewsEditShopCoupon($types)
    {
        // Add NOC link.
        if (current_user_can('manage_woocommerce')) {
            $class = (isset($_GET['filter-by']) && 'retainful-next-order-coupon' == $_GET['filter-by']) ? 'current' : '';
            $admin_url = admin_url('edit.php?post_type=shop_coupon');
            $query_string = add_query_arg(array('filter-by' => rawurlencode('retainful-next-order-coupon')), $admin_url);
            $query = new \WP_Query(array('post_type' => 'shop_coupon', 'meta_key' => '_rnoc_shop_coupon_type', 'meta_value' => 'retainful'));
            $types['retainful'] = '<a href="' . esc_url($query_string) . '" class="' . esc_attr($class) . '">' . __('Retainful - Next order coupons', 'woocommerce') . ' (' . $query->found_posts . ')</a>';
//            $expired_coupons_query = new \WP_Query(array('post_type' => 'shop_coupon', 'meta_key' => '_rnoc_shop_coupon_type', 'meta_value' => 'retainful'));
//            echo '<pre>';print_r($expired_coupons_query);echo '</pre>';die;
//            $types['retainful_expired_coupons'] = $expired_coupons_query->found_posts;
        }
        return $types;
    }

    /**
     * query to filter
     * @param $query_vars
     * @return mixed
     */
    function requestQuery($query_vars)
    {
        global $typenow;
        if ($typenow == "shop_coupon") {
            if (isset($_GET['filter-by']) && 'retainful-next-order-coupon' == sanitize_text_field($_GET['filter-by'])) {
                $query_vars['meta_key'] = "_rnoc_shop_coupon_type";
                $query_vars['meta_value'] = "retainful";
            }
        }
        return $query_vars;
    }
}