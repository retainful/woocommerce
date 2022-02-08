<?php

namespace Rnoc\Retainful\Api\NextOrderCoupon;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Helpers\Input;
use Valitron\Validator;

class CouponManagement
{
    /**
     * validate the $_POST
     * @param $data
     * @param $validate
     * @param $errors
     */
    static function validateRestCoupon($data, &$validate, &$errors)
    {
        $validator = new Validator($data);
        $validator->rule('slug', array(
            'coupon_code',
        ));
        $validator->rule('dateFormat', 'expiry_date', 'Y-m-d');
        $validator->rule('in', array(
            'value_type',
        ), array('percentage', 'fixed_amount'));
        $validator->rule('in', array(
            'target_type',
        ), array('shipping_line', 'line_item'));
        $validator->rule('numeric', array(
            'value'
        ));
        $validator->rule('integer', array(
            'usage_limit',
            'usage_limit_per_user',
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
        $admin = new Settings();
        $requestParams = $request->get_params();
        $defaultRequestParams = array(
            'discount_rule' => array(),
            'digest' => ''
        );
        $params = wp_parse_args($requestParams, $defaultRequestParams);
        $admin->logMessage($params, 'API coupon created request');
        if (is_array($params['discount_rule']) && !empty($params['discount_rule']) && is_string($params['digest']) && !empty($params['digest'])) {
            $secret = $admin->getSecretKey();
            $to_hash = array(
                'value_type' => (isset($params['discount_rule']['value_type'])) ? $params['discount_rule']['value_type'] : "",
                'value' => (isset($params['discount_rule']['value'])) ? $params['discount_rule']['value'] : "",
                'coupon_code' => (isset($params['discount_rule']['coupon_code'])) ? $params['discount_rule']['coupon_code'] : "",
            );
            $cipher_text_raw = json_encode($to_hash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $reverse_hmac = hash_hmac('sha256', $cipher_text_raw, $secret);
            if (hash_equals($reverse_hmac, $params['digest'])) {
                $admin->logMessage($reverse_hmac, 'API request digest matched');
                $defaultRuleParams = array(
                    'coupon_code' => null,
                    'usage_limit' => 1,
                    'usage_limit_per_user' => 1,
                    'value_type' => 'percentage',
                    'value' => 0,
                    'target_type' => 'line_item',
                    'customer_email' => null,
                    'ends_at' => null,
                    'prerequisite_subtotal_range' => array('greater_than_or_equal_to' => 0),
                );
                $ruleParams = wp_parse_args($params['discount_rule'], $defaultRuleParams);
                $admin->logMessage($ruleParams, 'API coupon request');
                $is_valid_data = true;
                $errors = array();
                self::validateRestCoupon($ruleParams, $is_valid_data, $errors);
                $admin->logMessage($errors, 'API request errors');
                if ($is_valid_data) {
                    $data = array(
                        'coupon_code' => $ruleParams['coupon_code'],
                        'discount_type' => ($ruleParams['value_type'] == "fixed_amount") ? 'fixed_cart' : 'percent',
                        'free_shipping' => ($ruleParams['target_type'] == "shipping_line") ? 'yes' : 'no',
                        'coupon_amount' => ($ruleParams['value'] < 0) ? floatval($ruleParams['value']) * -1 : 0,
                        'minimum_amount' => (floatval($ruleParams['prerequisite_subtotal_range']['greater_than_or_equal_to']) > 0) ? floatval($ruleParams['prerequisite_subtotal_range']['greater_than_or_equal_to']) : null,
                        'maximum_amount' => 0,
                        'expiry_date' => (!empty($ruleParams['ends_at'])) ? $ruleParams['ends_at'] : null,
                        'date_expires' => (!empty($ruleParams['ends_at'])) ? strtotime($ruleParams['ends_at']) : null,
                        'usage_limit' => $ruleParams['usage_limit'],
                        'usage_limit_per_user' => $ruleParams['usage_limit_per_user'],
                        'individual_use' => 'yes',
                        'customer_email' => $ruleParams['customer_email'],
                        'product_ids' => array(),
                        'exclude_product_ids' => array(),
                        'product_categories' => array(),
                        'exclude_product_categories' => array(),
                        '_rnoc_shop_coupon_type' => 'retainful-referral'
                    );
                    $data = apply_filters('rnoc_before_create_rest_coupon',$data,$ruleParams,$params);
                    $old_coupon = self::getCouponByCouponCode($data['coupon_code']);
                    if (!empty($old_coupon) && $old_coupon instanceof \WP_Post) {
                        $coupon_id = $old_coupon->ID;
                    } else {
                        $new_coupon = array(
                            'post_title' => $data['coupon_code'],
                            'post_name' => $data['coupon_code'] . '-' . rand(1, 2000000),
                            'post_content' => '',
                            'post_type' => 'shop_coupon',
                            'post_status' => 'publish'
                        );
                        $coupon_id = wp_insert_post($new_coupon, true);
                    }
                    $status = 200;
                    if (!empty($coupon_id)) {
                        if (!empty($data)) {
                            foreach ($data as $meta_key => $meta_value) {
                                if (in_array($meta_key, array('product_ids', 'exclude_product_ids')) && is_array($meta_value)) {
                                    $meta_value = implode(',', $meta_value);
                                }
                                update_post_meta($coupon_id, $meta_key, $meta_value);
                            }
                        }
                        $response = array('success' => true, 'RESPONSE_CODE' => 'COUPON_CODE_CREATED_OR_UPDATED', 'external_price_rule_id' => $coupon_id, 'code' => $data['coupon_code']);
                    } else {
                        $response = array('success' => false, 'RESPONSE_CODE' => 'UNABLE_TO_CREATE_OR_UPDATE', 'message' => 'Coupon code was not created!');
                    }
                } else {
                    $status = 400;
                    $response = $errors;
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
     * show the NOC details
     * @param $coupon_id
     */
    function showCouponOrderDetails($coupon_id)
    {
        if (!empty($coupon_id)) {
            $order_id = intval(get_post_meta($coupon_id, 'order_id', true));
            if ($order_id && $order_id > 0) {
                $order = wc_get_order($order_id);
                $order_url = $order->get_edit_order_url();
                echo '<p class="form-field "><label>Coupon generated for</label><span>Order #' . $order_id . ' | <a target="_blank" href="' . $order_url . '">View Order</a></span></p>';
            }
        }
    }

    function showDeleteButton($which){
        $input = new Input();
        $post_type = $input->post_get('post_type','');
        if($post_type === 'shop_coupon' && $which === 'top'){
            echo '<a id="delete-expired-rtl-coupons"  class="button" style="margin-left: 10%;">'.__('Delete Expired retainful coupons','woocommerce').'</a><script>
                  jQuery(document).on("click","#delete-expired-rtl-coupons",function (){
                      jQuery.post( "' . admin_url("admin-ajax.php") . '?action=rnoc_delete_expired_coupons&security=' . wp_create_nonce('rnoc_delete_expired_coupons') . '", function( data ) {
                          window.location.reload();
                      });
                  })
            </script>';
        }
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
            $referral_class = (isset($_GET['filter-by']) && 'retainful-referral-coupon' == $_GET['filter-by']) ? 'current' : '';
            $referral_query_string = add_query_arg(array('filter-by' => rawurlencode('retainful-referral-coupon')), $admin_url);
            $referral_query = new \WP_Query(array('post_type' => 'shop_coupon', 'meta_key' => '_rnoc_shop_coupon_type', 'meta_value' => 'retainful-referral'));
            $types['retainful_referral'] = '<a href="' . esc_url($referral_query_string) . '" class="' . esc_attr($referral_class) . '">' . __('Retainful - coupons', 'woocommerce') . ' (' . $referral_query->found_posts . ')</a>';
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
            } else if (isset($_GET['filter-by']) && 'retainful-referral-coupon' == sanitize_text_field($_GET['filter-by'])) {
                $query_vars['meta_key'] = "_rnoc_shop_coupon_type";
                $query_vars['meta_value'] = "retainful-referral";
            }
        }
        return $query_vars;
    }
}