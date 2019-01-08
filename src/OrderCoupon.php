<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Admin\Settings;

class OrderCoupon
{
    public $wc_functions, $admin;
    protected static $applied_coupons = NULL;

    function __construct()
    {
        $this->wc_functions = new WcFunctions();
        $this->admin = new Settings();
    }

    /**
     * Validate app Id
     */
    function validateAppKey()
    {
        $app_id = isset($_REQUEST['app_id']) ? $_REQUEST['app_id'] : '';
        $response = array();
        if (empty($app_id)) {
            $response['error'] = __('Please enter App-Id', RNOC_TEXT_DOMAIN);
        }
        if (empty($response)) {
            $is_api_enabled = $this->admin->isApiEnabled($app_id);
            if ($is_api_enabled) {
                $response['success'] = __('Successfully connected to Retainful', RNOC_TEXT_DOMAIN);
            } else {
                $response['error'] = __('Please enter Valid App-Id', 'retainful-coupon');
            }
        }
        echo json_encode($response);
        die;
    }

    /**
     * Init the Admin
     */
    function init()
    {
        $this->admin->renderPage();
    }

    /**
     * Attach the coupon details to Order
     * @param $order
     * @param $sent_to_admin
     * @param $plain_text
     * @param $email
     */
    function attachOrderCoupon($order, $sent_to_admin, $plain_text, $email)
    {
        if (!$sent_to_admin) {
            $is_api_enabled = $this->admin->isAppConnected();
            if ($is_api_enabled) {
                $message = "";
                $coupon_code = $this->wc_functions->getOrderMeta($order, '_rnoc_next_order_coupon');
                $coupon_details = $this->getCouponDetails($coupon_code);
                if (!empty($coupon_details)) {
                    $post_id = $coupon_details->ID;
                    $coupon = get_post_meta($post_id);
                    if (!empty($coupon)) {
                        $coupon_type = isset($coupon['coupon_type'][0]) ? $coupon['coupon_type'][0] : 0;
                        $coupon_amount = isset($coupon['coupon_value'][0]) ? $coupon['coupon_value'][0] : 0;
                        if ($coupon_amount > 0) {
                            $string_to_replace = array(
                                '{{coupon_amount}}' => ($coupon_type) ? $this->wc_functions->formatPrice($coupon_amount) : $coupon_amount . '%',
                                '{{coupon_code}}' => $coupon_code,
                                '{{coupon_url}}' => site_url() . '?retainful_coupon_code=' . $coupon_code
                            );
                            $message = $this->admin->getCouponMessage();
                            foreach ($string_to_replace as $key => $value) {
                                $message = str_replace($key, $value, $message);
                            }
                            $request_params = $this->getRequestParams($order);
                            $message .= '<img width="1" height="1" src="' . $this->admin->getPixelTagLink('track', $request_params) . '" />';
                        }
                    }
                }
                echo $message;
            }
        }
    }

    /**
     * Add coupon to checkout
     */
    public static function addCouponToCheckout()
    {
        $coupon_code = WC()->session->get('retainful_coupon_code');
        $cart = WC()->cart;
        if (!empty($cart)) {
            if (!empty($coupon_code) && !WC()->cart->has_discount($coupon_code)) {
                WC()->cart->add_discount($coupon_code);
                WC()->session->__unset('retainful_coupon_code');
            }
        }
    }

    /**
     * Save the coupon code to session
     */
    function setCouponToSession()
    {
        if (isset($_REQUEST['retainful_coupon_code'])) {
            $coupon_code = WC()->session->get('retainful_coupon_code');
            if (empty($coupon_code)) {
                $coupon_code = esc_attr($_REQUEST['retainful_coupon_code']);
                WC()->session->set('retainful_coupon_code', $coupon_code); // Set the coupon code in session
            }
        }
    }

    /**
     * Create the virtual coupon
     * @param $response
     * @param $coupon_code
     * @return array|bool
     */
    function addVirtualCoupon($response, $coupon_code)
    {
        if (empty($coupon_code))
            return $response;
        $coupon_details = $this->isValidCoupon($coupon_code);
        if (!empty($coupon_details)) {
            $is_coupon_already_applied = false;
            if (!empty(self::$applied_coupons) && self::$applied_coupons != $coupon_code)
                $is_coupon_already_applied = true;
            if (isset($coupon_details->ID) && !empty($coupon_details->ID) && !$is_coupon_already_applied) {
                self::$applied_coupons = $coupon_code;
                $discount_type = 'fixed_cart';
                $coupon_type = get_post_meta($coupon_details->ID, 'coupon_type', true);
                $coupon_value = get_post_meta($coupon_details->ID, 'coupon_value', true);
                $remove_coupon = isset($_REQUEST['remove_coupon']) ? $_REQUEST['remove_coupon'] : false;
                if ($remove_coupon == $coupon_code)
                    return false;
                if ($coupon_type == 0)
                    $discount_type = 'percent';
                $coupon = array(
                    'id' => 321123 . rand(2, 9),
                    'amount' => $coupon_value,
                    'individual_use' => false,
                    'product_ids' => array(),
                    'exclude_product_ids' => array(),
                    'usage_limit' => '',
                    'usage_limit_per_user' => '',
                    'limit_usage_to_x_items' => '',
                    'usage_count' => '',
                    'expiry_date' => '',
                    'apply_before_tax' => 'yes',
                    'free_shipping' => false,
                    'product_categories' => array(),
                    'exclude_product_categories' => array(),
                    'exclude_sale_items' => false,
                    'minimum_amount' => '',
                    'maximum_amount' => '',
                    'customer_email' => '',
                    'discount_type' => $discount_type
                );
                return $coupon;
            }
        }
        return $response;
    }


    /**
     * @param $order_id
     */
    function onAfterPayment($order_id)
    {
        $is_api_enabled = $this->admin->isAppConnected();
        if ($is_api_enabled) {
            $order = $this->wc_functions->getOrder($order_id);
            $request_params = $this->getRequestParams($order);
            if (isset($request_params['applied_coupon']) && !empty($request_params['applied_coupon'])) {
                $coupon_details = $this->isValidCoupon($request_params['applied_coupon'], $order);
                if (!empty($coupon_details)) {
                    $my_post = array(
                        'ID' => $coupon_details->ID,
                        'post_status' => 'expired',
                    );
                    wp_update_post($my_post);
                }
            }
            //Handle API Requests
            $this->admin->sendCouponDetails('track', $request_params);
        }
    }

    /**
     * Check the given Coupon code is valid or not
     * @param $coupon
     * @param null $order
     * @return String|null
     */
    function isValidCoupon($coupon, $order = NULL)
    {
        $post_args = array('post_type' => 'rnoc_order_coupon', 'numberposts' => '1', 'post_status' => 'publish', 'title' => strtoupper($coupon));
        $posts = get_posts($post_args);
        if (!empty($posts)) {
            $coupon_only_for = $this->admin->couponFor();
            $current_user_id = $current_email = '';
            if ($coupon_only_for != 'all') {
                if (!empty($order)) {
                    $current_user_id = $this->wc_functions->getOrderUserId($order);
                    if ($coupon_only_for != 'login_users') {
                        $current_email = $this->wc_functions->getOrderEmail($order);
                    }
                } else {
                    $current_user_id = get_current_user_id();
                    if ($coupon_only_for != 'login_users') {
                        $current_email = $this->getCurrentEmail();
                    }
                }
            }
            foreach ($posts as $post) {
                if ($coupon_only_for == 'all') {
                    return $post;
                } else if ($coupon_only_for == 'login_users') {
                    $user_id = get_post_meta($post->ID, 'user_id', true);
                    if ($current_user_id == $user_id) return $post;
                } else {
                    $user_id = get_post_meta($post->ID, 'user_id', true);
                    $email = get_post_meta($post->ID, 'email', true);
                    if (!empty($current_user_id) || !empty($current_email)) {
                        if ($current_user_id == $user_id || $current_email == $email)
                            return $post;
                    } else if (empty($current_user_id) && empty($current_email)) {
                        return $post;
                    }
                }
            }
        }
        return NULL;
    }

    /**
     * Get all the params required for API
     * @param $order
     * @return array
     */
    function getRequestParams($order)
    {
        return array(
            'order_id' => $this->wc_functions->getOrderId($order),
            'email' => $this->wc_functions->getOrderEmail($order),
            'firstname' => $this->wc_functions->getOrderFirstName($order),
            'lastname' => $this->wc_functions->getOrderLastName($order),
            'total' => $this->wc_functions->getOrderTotal($order),
            'new_coupon' => $this->wc_functions->getOrderMeta($order, '_rnoc_next_order_coupon'),
            'applied_coupon' => $this->wc_functions->getOrderMeta($order, '_rnoc_next_order_coupon_applied'),
            'order_date' => strtotime($this->wc_functions->getOrderDate($order))
        );
    }

    /**
     * @param $order_id
     * @param $data
     */
    function createNewCoupon($order_id, $data)
    {
        $is_api_enabled = $this->admin->isAppConnected();
        if ($is_api_enabled) {
            $coupon = $this->isCouponFound($order_id);
            $order = $this->wc_functions->getOrder($order_id);
            if (empty($coupon)) {
                $email = $this->wc_functions->getOrderEmail($order);
                $new_coupon_code = strtoupper(uniqid());
                $new_coupon_code = chunk_split($new_coupon_code, 5, '-');
                $new_coupon_code = rtrim($new_coupon_code, '-');
                $this->addNewCouponToOrder($new_coupon_code, $order_id, $email);
            } else {
                $new_coupon_code = strtoupper($coupon);
            }
            update_post_meta($order_id, '_rnoc_next_order_coupon', $new_coupon_code);
            $used_coupons = $this->wc_functions->getUsedCoupons($order);
            if (!empty($used_coupons)) {
                foreach ($used_coupons as $used_coupon) {
                    $coupon_details = $this->getCouponDetails($used_coupon);
                    if (!empty($coupon_details)) {
                        update_post_meta($order_id, '_rnoc_next_order_coupon_applied', strtoupper($used_coupon));
                        update_post_meta($coupon_details->ID, 'applied_for', $order_id);
                    }
                }
            }
        }
    }

    /**
     * Get Coupon Details by coupon code
     * @param $coupon
     * @return String|null
     */
    function getCouponDetails($coupon)
    {
        $post_args = array('post_type' => 'rnoc_order_coupon', 'numberposts' => '1', 'title' => strtoupper($coupon));
        $posts = get_posts($post_args);
        if (!empty($posts)) {
            $coupon_only_for = $this->admin->couponFor();
            foreach ($posts as $post) {
                if ($coupon_only_for == 'all') {
                    return $post;
                } else if ($coupon_only_for == 'login_users') {
                    $user_id = get_post_meta($post->ID, 'user_id', true);
                    $current_user_id = get_current_user_id();
                    if ($current_user_id == $user_id) return $post;
                } else {
                    $user_id = get_post_meta($post->ID, 'user_id', true);
                    $email = get_post_meta($post->ID, 'email', true);
                    $current_email = $this->getCurrentEmail();
                    $current_user_id = get_current_user_id();
                    if ($current_user_id == $user_id || $current_email == $email) return $post;
                }
            }
        }
        return NULL;
    }

    /**
     * Get Order Email
     * @return mixed|string|null
     */
    function getCurrentEmail()
    {
        $postData = isset($_REQUEST['post_data']) ? $_REQUEST['post_data'] : '';
        $postDataArray = array();
        if (is_string($postData) && $postData != '') {
            parse_str($postData, $postDataArray);
        }
        $postBillingEmail = isset($_REQUEST['billing_email']) ? $_REQUEST['billing_email'] : '';
        if ($postBillingEmail != '') {
            $postDataArray['billing_email'] = $postBillingEmail;
        }
        if (!get_current_user_id()) {
            $order_id = isset($_REQUEST['order-received']) ? $_REQUEST['order-received'] : 0;
            if ($order_id) {
                $order = $this->wc_functions->getOrder($order_id);
                $postDataArray['billing_email'] = $this->wc_functions->getOrderEmail($order);
            }
        }
        $user_email = '';
        if (isset($postDataArray['billing_email']) && $postDataArray['billing_email'] != '') {
            $user_email = $postDataArray['billing_email'];
        } else if (get_current_user_id()) {
            $user_email = get_user_meta(get_current_user_id(), 'billing_email', true);
            if ($user_email != '' && !empty($user_email)) {
                return $user_email;
            } else {
                $user_details = get_userdata(get_current_user_id());
                if (isset($user_details->data->user_email) && $user_details->data->user_email != '') {
                    $user_email = $user_details->data->user_email;
                    return $user_email;
                }
            }
        }
        return $user_email;
    }

    /**
     * Save Coupon to order
     * @param $new_coupon_code
     * @param $order_id
     * @param $email
     * @return int|\WP_Error
     */
    function addNewCouponToOrder($new_coupon_code, $order_id, $email)
    {
        $post = array(
            'post_title' => $new_coupon_code,
            'post_name' => $new_coupon_code . '-' . $order_id,
            'post_content' => 'Virtual coupon code created through Retainful Next order coupon',
            'post_type' => 'rnoc_order_coupon',
            'post_status' => 'publish'
        );
        $id = wp_insert_post($post, true);
        if ($id) {
            $settings = $this->admin->getCouponSettings();
            add_post_meta($id, 'order_id', $order_id);
            add_post_meta($id, 'email', $email);
            $user_id = get_current_user_id();
            add_post_meta($id, 'user_id', $user_id);
            add_post_meta($id, 'coupon_type', isset($settings['coupon_type']) ? $settings['coupon_type'] : '0');
            add_post_meta($id, 'coupon_value', isset($settings['coupon_amount']) ? $settings['coupon_amount'] : '0');
        }
        return $id;
    }

    /**
     * Check is coupon found
     * @param $order_id
     * @return String|null
     */
    function isCouponFound($order_id)
    {
        $post_args = array('post_type' => 'rnoc_order_coupon', 'numberposts' => '1', 'post_status' => 'publish', 'meta_key' => 'order_id', 'meta_value' => $order_id);
        $posts = get_posts($post_args);
        if (!empty($posts)) {
            if (isset($posts[0]->post_title)) {
                return $posts[0]->post_title;
            }
        }
        return NULL;
    }
}