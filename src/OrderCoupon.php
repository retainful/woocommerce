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
     * Add settings link
     * @param $links
     * @return array
     */
    function pluginActionLinks($links)
    {
        if ($this->admin->runAbandonedCartExternally()) {
            $action_links = array(
                'license' => '<a href="' . admin_url('admin.php?page=retainful_license') . '">' . __('Connection', RNOC_TEXT_DOMAIN) . '</a>',
                'premium_add_ons' => '<a href="' . admin_url('admin.php?page=retainful_premium') . '">' . __('Add-ons', RNOC_TEXT_DOMAIN) . '</a>',
            );
        } else {
            $action_links = array(
                'abandoned_carts' => '<a href="' . admin_url('admin.php?page=retainful_abandoned_cart') . '">' . __('Abandoned carts', RNOC_TEXT_DOMAIN) . '</a>',
                'premium_add_ons' => '<a href="' . admin_url('admin.php?page=retainful_premium') . '">' . __('Add-ons', RNOC_TEXT_DOMAIN) . '</a>',
                'settings' => '<a href="' . admin_url('admin.php?page=retainful_settings') . '">' . __('Settings', RNOC_TEXT_DOMAIN) . '</a>',
                'license' => '<a href="' . admin_url('admin.php?page=retainful_license') . '">' . __('License', RNOC_TEXT_DOMAIN) . '</a>',
            );
        }
        return array_merge($action_links, $links);
    }

    /**
     * Send required details for email customizer
     * @param $order_coupon_data
     * @param $order
     * @param $sending_email
     * @return array
     */
    function wooEmailCustomizerRetainfulCouponContent($order_coupon_data, $order, $sending_email)
    {
        $order_id = $this->wc_functions->getOrderId($order);
        $coupon_code = $this->wc_functions->getPostMeta($order_id, '_rnoc_next_order_coupon');
        if (!empty($coupon_code)) {
            $coupon_details = $this->getCouponByCouponCode($coupon_code);
            if (!empty($coupon_details)) {
                $post_id = $coupon_details->ID;
                $coupon_amount = get_post_meta($post_id, 'coupon_value', true);
                if ($coupon_amount > 0) {
                    $coupon_type = get_post_meta($post_id, 'coupon_type', true);
                    $order_coupon_data = array(
                        'wec_next_order_coupon_code' => $coupon_code,
                        'wec_next_order_coupon' => $coupon_code,
                        'wec_next_order_coupon_value' => ($coupon_type) ? $this->wc_functions->formatPrice($coupon_amount) : $coupon_amount . '%',
                        'woo_mb_site_url_link_with_coupon' => site_url() . '?retainful_coupon_code=' . $coupon_code,
                    );
                }
            }
        }
        return $order_coupon_data;
    }

    /**
     * Register retainful short codes with Email Customizer
     * @param $short_codes
     * @return mixed
     */
    function wooEmailCustomizerRegisterRetainfulShortCodes($short_codes)
    {
        if ($this->admin->isAppConnected()) {
            $short_codes['retainful_coupon_expiry_date'] = "Next order coupon expiry date";
        }
        return $short_codes;
    }

    /**
     * assign values for short codes
     * @param $short_codes
     * @param $order
     * @param $sending_email
     * @return mixed
     */
    function wooEmailCustomizerRetainfulShortCodesValues($short_codes, $order, $sending_email)
    {
        if ($this->admin->isAppConnected()) {
            $short_codes['retainful_coupon_expiry_date'] = '';
            $coupon_code = $this->wc_functions->getOrderMeta($order, '_rnoc_next_order_coupon');
            if (!empty($coupon_code)) {
                $coupon_details = $this->getCouponByCouponCode($coupon_code);
                if (!empty($coupon_details)) {
                    $post_id = $coupon_details->ID;
                    $coupon_expiry_date = get_post_meta($post_id, 'coupon_expired_on', true);
                    if (!empty($coupon_expiry_date)) {
                        $date_format = $this->admin->getExpireDateFormat();
                        $short_codes['retainful_coupon_expiry_date'] = $this->formatDate($coupon_expiry_date, $date_format);
                    }
                }
            }
        }
        return $short_codes;
    }

    /**
     * Give url to Email customizer
     * @return mixed
     */
    function wooEmailCustomizerRetainfulSettingsUrl()
    {
        return admin_url("admin.php?page=retainful");
    }

    /**
     * Run the scheduled cron tasks with retainful
     * @param $order_id
     * @return bool
     *
     */
    function cronSendCouponDetails($order_id)
    {
        if (!isset($order_id) || empty($order_id))
            return false;
        $api_key = $this->admin->getApiKey();
        if ($this->admin->isAppConnected() && !empty($api_key)) {
            $order = $this->wc_functions->getOrder($order_id);
            $this->updateAppliedCouponDetails($order_id, $order);
            $request_params = $this->getRequestParams($order);
            $this->admin->logMessage($request_params, 'next order coupon');
            $request_params['app_id'] = $api_key;
            return $this->admin->sendCouponDetails('track', $request_params);
        }
        return false;
    }

    /**
     * Show coupon in thankyou page
     * @param $order_id
     */
    function showCouponInThankYouPage($order_id)
    {
        if (!empty($this->admin->showCouponInThankYouPage())) {
            $order = $this->wc_functions->getOrder($order_id);
            $this->attachOrderCoupon($order, false);
        }
    }

    /**
     * Attach the coupon details to Order
     * @param $order
     * @param $sent_to_admin
     * @param $plain_text
     * @param $email
     */
    function attachOrderCoupon($order, $sent_to_admin = false, $plain_text = '', $email = '')
    {
        $order_id = $this->wc_functions->getOrderId($order);
        $coupon_code = '';
        if ($this->admin->autoGenerateCouponsForOldOrders()) {
            //Create new coupon if coupon not found for order while sending the email
            $coupon_code = $this->createNewCoupon($order_id, array());
            //$this->scheduleSync($order_id);
        }
        if (empty($coupon_code)) {
            $coupon_code = $this->wc_functions->getOrderMeta($order, '_rnoc_next_order_coupon');
        }
        if (!empty($coupon_code)) {
            $message = "";
            $coupon_details = $this->getCouponByCouponCode($coupon_code);
            if (!empty($coupon_details)) {
                $post_id = $coupon_details->ID;
                $coupon_amount = get_post_meta($post_id, 'coupon_value', true);
                if ($coupon_amount > 0) {
                    $coupon_type = get_post_meta($post_id, 'coupon_type', true);
                    $coupon_url = add_query_arg('retainful_coupon_code', $coupon_code, site_url());
                    $string_to_replace = array(
                        '{{coupon_amount}}' => ($coupon_type) ? $this->wc_functions->formatPrice($coupon_amount) : $coupon_amount . '%',
                        '{{coupon_code}}' => $coupon_code,
                        'http://{{coupon_url}}' => $coupon_url,
                        'https://{{coupon_url}}' => $coupon_url,
                        '{{coupon_url}}' => $coupon_url
                    );
                    $coupon_expiry_date = get_post_meta($post_id, 'coupon_expired_on', true);
                    if (!empty($coupon_expiry_date)) {
                        $date_format = $this->admin->getExpireDateFormat();
                        $string_to_replace['{{coupon_expiry_date}}'] = $this->formatDate($coupon_expiry_date, $date_format);
                    } else {
                        $string_to_replace['{{coupon_expiry_date}}'] = '';
                    }
                    $message = $this->admin->getCouponMessage();
                    $message = str_replace(array_keys($string_to_replace), $string_to_replace, $message);
                }
            }
            $message = apply_filters('rnoc_before_displaying_next_order_coupon', $message, $order);
            echo $message;
            do_action('rnoc_after_displaying_next_order_coupon');
        }
    }

    /**
     * Format the date
     * @param $date
     * @param string $format
     * @return string|null
     */
    function formatDate($date, $format = "F j, Y, g:i a")
    {
        if (empty($date))
            return NULL;
        if (function_exists('get_date_from_gmt')) {
            return get_date_from_gmt(date('Y-m-d H:i:s', strtotime($date)), $format);
        } else {
            try {
                $date = new \DateTime($date);
                return $date->format($format);
            } catch (\Exception $e) {
                return NULL;
            }
        }
    }

    /**
     * Add coupon to checkout
     */
    public function addCouponToCheckout()
    {
        $coupon_code = $this->wc_functions->getSession('retainful_coupon_code');
        if (!empty($coupon_code) && !empty($this->wc_functions->getCart()) && !$this->wc_functions->hasDiscount($coupon_code)) {
            //Do not apply coupon until the coupon is valid
            if ($this->checkCouponBeforeCouponApply($coupon_code)) {
                $this->wc_functions->addDiscount($coupon_code);
            }
        }
    }

    /**
     * show applied coupon popup
     */
    function showAppliedCouponPopup()
    {
        if (isset($_GET['noc-cta']) && $_GET['noc-cta'] == 1) {
            return;
        }
        if (isset($_GET['retainful_coupon_code']) && !empty($_GET['retainful_coupon_code'])) {
            $coupon_code = sanitize_text_field($_GET['retainful_coupon_code']);
            $settings = $this->admin->getUsageRestrictions();
            $need_popup = (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup'])) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_coupon_applied_popup'] : 1;
            $popup_content = (isset($settings[RNOC_PLUGIN_PREFIX . 'coupon_applied_popup_design'])) ? $settings[RNOC_PLUGIN_PREFIX . 'coupon_applied_popup_design'] : $this->admin->appliedCouponDefaultTemplate();
            if ($need_popup && !empty($popup_content)) {
                $override_path = get_theme_file_path('retainful/templates/applied_coupon_popup.php');
                $cart_template_path = RNOC_PLUGIN_PATH . 'src/admin/templates/applied_coupon_popup.php';
                if (file_exists($override_path)) {
                    $cart_template_path = $override_path;
                }
                if (file_exists($cart_template_path)) {
                    $coupon_details = $this->getCouponByCouponCode($coupon_code);
                    if (!empty($coupon_details)) {
                        $post_id = $coupon_details->ID;
                        $coupon_amount = get_post_meta($post_id, 'coupon_value', true);
                        if ($coupon_amount > 0) {
                            $coupon_type = get_post_meta($post_id, 'coupon_type', true);
                            $coupon_array = array(
                                'coupon_amount' => ($coupon_type) ? $this->wc_functions->formatPrice($coupon_amount) : $coupon_amount . '%',
                                'coupon_code' => $coupon_code,
                                'shop_url' => add_query_arg(array('retainful_coupon_code' => $coupon_code, 'noc-cta' => 1), $this->wc_functions->getShopUrl()),
                                'cart_url' => add_query_arg(array('retainful_coupon_code' => $coupon_code, 'noc-cta' => 1), $this->wc_functions->getCartUrl()),
                                'checkout_url' => add_query_arg(array('retainful_coupon_code' => $coupon_code, 'noc-cta' => 1), $this->wc_functions->getCheckoutUrl()),
                            );
                            foreach ($coupon_array as $key => $val) {
                                $popup_content = str_replace('{{' . $key . '}}', $val, $popup_content);
                            }
                            include $cart_template_path;
                            $this->wc_functions->setSession('rnoc_is_coupon_applied_popup_showed', 1);
                        }
                    }
                }
            }
        }
    }

    /**
     * Remove coupon on user request
     * @param $remove_coupon
     */
    function removeCouponFromCart($remove_coupon)
    {
        $coupon_code = $this->wc_functions->getSession('retainful_coupon_code');
        if (strtoupper($remove_coupon) == strtoupper($coupon_code) && $this->checkCouponBeforeCouponApply($remove_coupon)) {
            $this->removeCouponFromSession();
        }
    }

    /**
     * Remove Coupon from session
     */
    function removeCouponFromSession()
    {
        $coupon_code = $this->wc_functions->getSession('retainful_coupon_code');
        if (!empty($coupon_code)) {
            $this->wc_functions->removeSession('retainful_coupon_code');
        }
    }

    /**
     * Check that coupon is validated in retainful usage restriction
     * @param $coupon_code
     * @return bool
     */
    public function checkCouponBeforeCouponApply($coupon_code)
    {
        if (empty($coupon_code))
            return false;
        $return = array();
        $coupon_details = $this->isValidCoupon($coupon_code, null, array('rnoc_order_coupon'));
        if (!empty($coupon_details)) {
            $usage_restrictions = $this->admin->getUsageRestrictions();
            //Return true if there is any usage restriction
            if (empty($usage_restrictions))
                return true;
            //Check for coupon expired or not
            $coupon_expiry_date = get_post_meta($coupon_details->ID, 'coupon_expired_on', true);
            if (!empty($coupon_expiry_date) && current_time('timestamp', true) > strtotime($coupon_expiry_date)) {
                array_push($return, false);
            }
            $cart_total = $this->wc_functions->getCartTotal();
            //Check for minimum spend
            $minimum_spend = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend']) && $usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend'] > 0) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend'] : '';
            if (!empty($minimum_spend) && $cart_total < $minimum_spend) {
                array_push($return, false);
            }
            //Check for maximum spend
            $maximum_spend = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend']) && $usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend'] > 0) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend'] : '';
            if (!empty($maximum_spend) && $cart_total > $maximum_spend) {
                array_push($return, false);
            }
            $products_in_cart = $this->wc_functions->getProductIdsInCart();
            //Check the cart having only sale items
            $sale_products_in_cart = $this->wc_functions->getSaleProductIdsInCart();
            if ((count($sale_products_in_cart) >= count($products_in_cart)) && isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_sale_items'])) {
                array_push($return, false);
            }
            //Check for must in cart products
            $must_in_cart_products = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'products'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'products'] : array();
            if (!empty($must_in_cart_products) && count(array_intersect($must_in_cart_products, $products_in_cart)) == 0) {
                array_push($return, false);
            }
            $categories_in_cart = $this->wc_functions->getCategoryIdsOfProductInCart();
            //Check for must in categories of cart
            $must_in_cart_categories = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'product_categories'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'product_categories'] : array();
            if (!empty($must_in_cart_categories) && count(array_intersect($must_in_cart_categories, $categories_in_cart)) == 0) {
                array_push($return, false);
            }
            //Check for must in cart products and exclude products in cart are given
            $must_not_in_cart_products = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_products'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_products'] : array();
            $must_not_in_cart_categories = (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'] : array();
            if (array_intersect($must_not_in_cart_products, $must_in_cart_products) || array_intersect($must_in_cart_categories, $must_not_in_cart_categories)) {
                array_push($return, false);
            }
        } else {
            $this->removeCouponFromSession();
        }
        if (in_array(false, $return))
            return false;
        return true;
    }

    /**
     * Save the coupon code to session
     */
    function setCouponToSession()
    {
        $request_coupon_code = null;
        if (isset($_REQUEST['retainful_coupon_code'])) {
            $request_coupon_code = sanitize_text_field($_REQUEST['retainful_coupon_code']);
        }
        if (isset($_REQUEST['retainful_ac_coupon'])) {
            $request_coupon_code = sanitize_text_field($_REQUEST['retainful_ac_coupon']);
        }
        $coupon_code = $this->wc_functions->getSession('retainful_coupon_code');
        if (!empty($request_coupon_code) && empty($coupon_code)) {
            $coupon_details = $this->getCouponByCouponCode($request_coupon_code);
            if (!empty($coupon_details)) {
                $this->wc_functions->setSession('retainful_coupon_code', $request_coupon_code); // Set the coupon code in session
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
        $coupon_details = $this->isValidCoupon($coupon_code, null, array('rnoc_order_coupon'));
        if (!empty($coupon_details)) {
            $is_coupon_already_applied = false;
            /*if (!empty(self::$applied_coupons) && self::$applied_coupons != $coupon_code)
                $is_coupon_already_applied = true;*/
            if (isset($coupon_details->ID) && !empty($coupon_details->ID) && !$is_coupon_already_applied) {
                self::$applied_coupons = $coupon_code;
                $discount_type = 'fixed_cart';
                $usage_restrictions = $this->admin->getUsageRestrictions();
                $coupon_type = get_post_meta($coupon_details->ID, 'coupon_type', true);
                $coupon_value = get_post_meta($coupon_details->ID, 'coupon_value', true);
                $coupon_expiry_date = get_post_meta($coupon_details->ID, 'coupon_expired_on', true);
                if ($coupon_type == 0)
                    $discount_type = 'percent';
                $coupon = array(
                    'id' => 321123 . rand(2, 9),
                    'amount' => $coupon_value,
                    'individual_use' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'individual_use_only'])) ? true : false,
                    'product_ids' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'products'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'products'] : array(),
                    'excluded_product_ids' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_products'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_products'] : array(),
                    //'exclude_product_ids' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_products'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_products'] : array(),
                    'usage_limit' => '',
                    'usage_limit_per_user' => '',
                    'limit_usage_to_x_items' => '',
                    'usage_count' => '',
                    'expiry_date' => $coupon_expiry_date,
                    'date_expires' => !empty($coupon_expiry_date) ? strtotime($coupon_expiry_date) : $coupon_expiry_date,
                    'apply_before_tax' => 'no',
                    'free_shipping' => false,
                    'product_categories' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'product_categories'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'product_categories'] : array(),
                    'excluded_product_categories' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'] : array(),
                    //'exclude_product_categories' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'])) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_product_categories'] : array(),
                    'exclude_sale_items' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'exclude_sale_items'])) ? true : false,
                    'minimum_amount' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend']) && $usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend'] > 0) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'minimum_spend'] : '',
                    'maximum_amount' => (isset($usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend']) && $usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend'] > 0) ? $usage_restrictions[RNOC_PLUGIN_PREFIX . 'maximum_spend'] : '',
                    'customer_email' => '',
                    'discount_type' => $discount_type,
                    'virtual' => true
                );
                return $coupon;
            }
        }
        return $response;
    }

    /**
     * Process after the Place order
     * @param $order_id
     * @return bool
     */
    function onAfterPayment($order_id)
    {
        if (empty($order_id))
            return false;
        $order = $this->wc_functions->getOrder($order_id);
        $this->updateAppliedCouponDetails($order_id, $order);
        $request_params = $this->getRequestParams($order);
        if (isset($request_params['applied_coupon']) && !empty($request_params['applied_coupon'])) {
            $coupon_details = $this->isValidCoupon($request_params['applied_coupon'], $order, array('rnoc_order_coupon'));
            if (!empty($coupon_details)) {
                $my_post = array(
                    'ID' => $coupon_details->ID,
                    'post_status' => 'expired',
                );
                wp_update_post($my_post);
            }
        }
        //Create new coupon if coupon not found for order while sending the email
        $this->createNewCoupon($order_id, array());
        //$this->scheduleSync($order_id);
        return true;
    }

    /**
     * Schedule sync
     * @param $order_id
     */
    function scheduleSync($order_id)
    {
        $is_api_enabled = $this->admin->isAppConnected();
        if ($is_api_enabled) {
            //Handle API Requests
            $api_key = $this->admin->getApiKey();
            if (!empty($api_key)) {
                $woocommerce_version = rnocGetInstalledWoocommerceVersion();
                if (version_compare($woocommerce_version, '3.5', '>=')) {
                    $hook = 'retainful_cron_sync_coupon_details';
                    $meta_key = '_rnoc_order_id';
                    $this->admin->scheduleEvents($hook, current_time('timestamp') + 60, array($meta_key => $order_id));
                } else {
                    //For old versions directly sync the cou[on details
                    $this->cronSendCouponDetails($order_id);
                }
            }
        }
    }

    /**
     * Check the given Coupon code is valid or not
     * @param $coupon
     * @param null $order
     * @param string[] $coupon_type
     * @return String|null
     */
    function isValidCoupon($coupon, $order = NULL, $coupon_type = array('rnoc_order_coupon', 'shop_coupon'))
    {
        $coupon_details = $this->getCouponByCouponCode($coupon, $coupon_type);
        if (!empty($coupon_details) && $coupon_details->post_status == "publish") {
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
            if ($coupon_only_for == 'all') {
                return $coupon_details;
            } else if ($coupon_only_for == 'login_users') {
                $user_id = get_post_meta($coupon_details->ID, 'user_id', true);
                if ($current_user_id == $user_id) return $coupon_details;
            } else {
                $user_id = get_post_meta($coupon_details->ID, 'user_id', true);
                $email = get_post_meta($coupon_details->ID, 'email', true);
                if (!empty($current_user_id) || !empty($current_email)) {
                    if ($current_user_id == $user_id || $current_email == $email)
                        return $coupon_details;
                } else if (empty($current_user_id) && empty($current_email)) {
                    return $coupon_details;
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
        if (empty($order)) return array();
        $new_coupon = $this->wc_functions->getOrderMeta($order, '_rnoc_next_order_coupon');
        $coupon_details = $this->getCouponByCouponCode($new_coupon);
        $expire_date = $apply_url = '';
        if (!empty($coupon_details)) {
            if (isset($coupon_details->ID) && !empty($coupon_details->ID)) {
                $coupon_expiry_date = get_post_meta($coupon_details->ID, 'coupon_expired_on', true);
                if (!empty($coupon_expiry_date)) {
                    $expire_date = strtotime($coupon_expiry_date);
                }
                $apply_url = add_query_arg('retainful_coupon_code', $new_coupon, site_url());
            }
        }
        return array(
            'order_id' => $this->wc_functions->getOrderId($order),
            'email' => $this->wc_functions->getOrderEmail($order),
            'firstname' => $this->wc_functions->getOrderFirstName($order),
            'lastname' => $this->wc_functions->getOrderLastName($order),
            'total' => $this->wc_functions->getOrderTotal($order),
            'new_coupon' => $this->wc_functions->getOrderMeta($order, '_rnoc_next_order_coupon'),
            'applied_coupon' => $this->wc_functions->getOrderMeta($order, '_rnoc_next_order_coupon_applied'),
            'order_date' => strtotime($this->wc_functions->getOrderDate($order)),
            'expired_at' => $expire_date,
            'apply_url' => $apply_url
        );
    }

    /**
     * Check for order has valid order status to generate coupon
     * @param $order
     * @return bool
     */
    function hasValidOrderStatus($order)
    {
        $status = true;
        $valid_order_statuses = $this->admin->getCouponValidOrderStatuses();
        if (!empty($valid_order_statuses)) {
            if (!in_array('all', $valid_order_statuses)) {
                $order_status = 'wc-' . $this->wc_functions->getStatus($order);
                if ($order_status == "wc-pending" || !in_array($order_status, $valid_order_statuses)) {
                    $status = false;
                }
            }
        }
        return apply_filters("rnoc_is_order_has_valid_order_status", $status, $order);
    }

    /**
     * Check for order user has valid user roles to generate coupon
     * @param $order
     * @return bool
     */
    function hasValidUserRoles($order)
    {
        $status = true;
        $valid_user_roles = $this->admin->getCouponValidUserRoles();
        if (!empty($valid_user_roles)) {
            if (!in_array('all', $valid_user_roles)) {
                $order_roles = $this->getUserRoleFromOrder($order);
                if (count(array_intersect($order_roles, $valid_user_roles)) == 0) {
                    $status = false;
                }
            }
        }
        return apply_filters("rnoc_is_order_has_valid_user_role", $status, $order);
    }

    /**
     * get the user role from order object
     * @param $order
     * @return array
     */
    function getUserRoleFromOrder($order)
    {
        $user = $this->wc_functions->getOrderUser($order);
        if (empty($user)) {
            $user_email = $this->wc_functions->getOrderEmail($order);
            $user = $this->wc_functions->getUserByEmail($user_email);
        }
        if (!empty($user)) {
            $user_roles = isset($user->roles) ? $user->roles : array();
            return $user_roles;
        }
        return array();
    }

    /**
     * check the user has valid limit
     * @param $order
     * @return bool
     */
    function isValidCouponLimit($order)
    {
        $status = true;
        $limit = $this->admin->getCouponLimitPerUser();
        if (!empty($limit)) {
            $order_email = $this->wc_functions->getOrderEmail($order);
            $args = array(
                'posts_per_page' => -1,
                'post_type' => array('rnoc_order_coupon', 'shop_coupon'),
                'meta_key' => 'email',
                'meta_value' => $order_email
            );
            $posts_query = new \WP_Query($args);
            $count = $posts_query->post_count;
            if ($count >= $limit) {
                $status = false;
            }
        }
        return apply_filters("rnoc_is_order_has_valid_limit", $status, $order);
    }

    /**
     * check the user has valid limit
     * @param $order
     * @return bool
     */
    function isMinimumOrderTotalReached($order)
    {
        $status = true;
        $minimum_total = $this->admin->getMinimumOrderTotalForCouponGeneration();
        if (!empty($minimum_total)) {
            $order_total = $this->wc_functions->getOrderTotal($order);
            $status = ($order_total >= $minimum_total);
        }
        return apply_filters("rnoc_is_minimum_sub_total_reached", $status, $order);
    }

    /**
     * Check the order has valid order items to generate coupon
     * @param $order
     * @return bool
     */
    function hasValidProductsToGenerateCoupon($order)
    {
        $status = true;
        $invalid_products = $this->admin->getInvalidProductsForCoupon();
        if (!empty($invalid_products)) {
            $cart = $this->wc_functions->getOrderItems($order);
            if (!empty($cart)) {
                foreach ($cart as $item_key => $item_details) {
                    $variant_id = (isset($item_details['variation_id']) && !empty($item_details['variation_id'])) ? $item_details['variation_id'] : 0;
                    $product_id = (isset($item_details['product_id']) && !empty($item_details['product_id'])) ? $item_details['product_id'] : 0;
                    $id = (!empty($variant_id)) ? $variant_id : $product_id;
                    if (in_array($id, $invalid_products)) {
                        $status = false;
                        break;
                    }
                }
            }
        }
        return apply_filters("rnoc_is_order_has_valid_products", $status, $order);
    }

    /**
     * Check the order has valid order items to generate coupon
     * @param $order
     * @return bool
     */
    function hasValidCategoriesToGenerateCoupon($order)
    {
        $status = true;
        $invalid_categories = $this->admin->getInvalidCategoriesForCoupon();
        if (!empty($invalid_categories)) {
            $cart = $this->wc_functions->getOrderItems($order);
            if (!empty($cart)) {
                foreach ($cart as $item_key => $item_details) {
                    $product_id = (isset($item_details['product_id']) && !empty($item_details['product_id'])) ? $item_details['product_id'] : 0;
                    $product_category_ids = $this->wc_functions->getProductCategoryIds($product_id);
                    if (is_array($product_category_ids) && is_array($invalid_categories)) {
                        if (count(array_intersect($invalid_categories, $product_category_ids)) > 0) {
                            $status = false;
                            break;
                        }
                    }
                }
            }
        }
        return apply_filters("rnoc_is_order_has_valid_categories", $status, $order);
    }

    /**
     * Create new coupon
     * @param $order_id
     * @param $data
     * @return bool
     */
    function createNewCoupon($order_id, $data)
    {
        if (!$this->admin->isNextOrderCouponEnabled()) {
            return false;
        }
        $order_id = sanitize_key($order_id);
        if (empty($order_id)) return false;
        $order = $this->wc_functions->getOrder($order_id);
        if (!$order) {
            return false;
        }
        $coupon_settings = $this->admin->getCouponSettings();
        if (!$this->hasValidCategoriesToGenerateCoupon($order) || !$this->hasValidProductsToGenerateCoupon($order) || !$this->isValidCouponLimit($order) || !$this->isMinimumOrderTotalReached($order) || !$this->hasValidOrderStatus($order) || !$this->hasValidUserRoles($order) || !isset($coupon_settings['coupon_amount']) || empty($coupon_settings['coupon_amount'])) {
            return NULL;
        }
        $email = $this->wc_functions->getOrderEmail($order);
        //Sometime email not found in the order object when order created from backend. So, get  from the request
        if (empty($email)) {
            $email = (isset($_REQUEST['_billing_email']) && !empty($_REQUEST['_billing_email'])) ? $_REQUEST['_billing_email'] : '';
        }
        $coupon = $this->isCouponFound($order_id);
        $order_date = $this->wc_functions->getOrderDate($order);
        if (empty($coupon)) {
            $new_coupon_code = strtoupper(uniqid());
            $new_coupon_code = chunk_split($new_coupon_code, 5, '-');
            $new_coupon_code = rtrim($new_coupon_code, '-');
            $this->addNewCouponToOrder($new_coupon_code, $order_id, $email, $order_date);
        } else {
            $new_coupon_code = strtoupper($coupon);
            $coupon_details = $this->getCouponByCouponCode($coupon);
            if (empty($coupon_details)) {
                $this->addNewCouponToOrder($new_coupon_code, $order_id, $email, $order_date);
            }
        }
        $new_coupon_code = sanitize_text_field($new_coupon_code);
        if (empty($new_coupon_code))
            return NULL;
        update_post_meta($order_id, '_rnoc_next_order_coupon', $new_coupon_code);
        $this->updateAppliedCouponDetails($order_id, $order);
        return $new_coupon_code;
    }

    /**
     * Update used coupon details of the order
     * @param $order_id
     * @param $order
     */
    function updateAppliedCouponDetails($order_id, $order)
    {
        $used_coupons = $this->wc_functions->getUsedCoupons($order);
        if (!empty($used_coupons)) {
            foreach ($used_coupons as $used_coupon) {
                if (empty($used_coupon))
                    continue;
                $coupon_details = $this->getCouponByCouponCode($used_coupon);
                if (!empty($coupon_details)) {
                    if (get_post_meta($coupon_details->ID, '_rnoc_shop_coupon_type', true) == 'retainful') {
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
        $coupon_details = $this->getCouponByCouponCode($coupon);
        if (!empty($coupon_details)) {
            $coupon_only_for = $this->admin->couponFor();
            if ($coupon_only_for == 'all') {
                return $coupon_details;
            } else if ($coupon_only_for == 'login_users') {
                $user_id = get_post_meta($coupon_details->ID, 'user_id', true);
                $current_user_id = get_current_user_id();
                if ($current_user_id == $user_id) return $coupon_details;
            } else {
                $user_id = get_post_meta($coupon_details->ID, 'user_id', true);
                $email = get_post_meta($coupon_details->ID, 'email', true);
                $current_email = $this->getCurrentEmail();
                $current_user_id = get_current_user_id();
                if ($current_user_id == $user_id || $current_email == $email) return $coupon_details;
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
        $postData = isset($_REQUEST['post_data']) ? wc_clean($_REQUEST['post_data']) : '';
        $postDataArray = array();
        if (is_string($postData) && $postData != '') {
            parse_str($postData, $postDataArray);
        }
        $postBillingEmail = isset($_REQUEST['billing_email']) ? sanitize_email($_REQUEST['billing_email']) : '';
        if ($postBillingEmail != '') {
            $postDataArray['billing_email'] = $postBillingEmail;
        }
        if (!get_current_user_id()) {
            $order_id = isset($_REQUEST['order-received']) ? sanitize_key($_REQUEST['order-received']) : 0;
            if ($order_id) {
                $order = $this->wc_functions->getOrder($order_id);
                $postDataArray['billing_email'] = $this->wc_functions->getOrderEmail($order);
            }
        }
        $user_email = '';
        if (isset($postDataArray['billing_email']) && $postDataArray['billing_email'] != '') {
            $user_email = $postDataArray['billing_email'];
        } else if ($user_id = get_current_user_id()) {
            $user_email = get_user_meta($user_id, 'billing_email', true);
            if ($user_email != '' && !empty($user_email)) {
                return $user_email;
            } else {
                $user_details = get_userdata($user_id);
                if (isset($user_details->data->user_email) && $user_details->data->user_email != '') {
                    $user_email = $user_details->data->user_email;
                    return $user_email;
                }
            }
        }
        return sanitize_email($user_email);
    }

    /**
     * Save Coupon to order
     * @param $new_coupon_code
     * @param $order_id
     * @param $email
     * @param $order_date
     * @return int|\WP_Error
     */
    function addNewCouponToOrder($new_coupon_code, $order_id, $email, $order_date)
    {
        $new_coupon_code = sanitize_text_field($new_coupon_code);
        $order_id = sanitize_text_field($order_id);
        $email = sanitize_email($email);
        if (empty($new_coupon_code) || empty($order_id) || empty($email))
            return NULL;
        $post = array(
            'post_title' => $new_coupon_code,
            'post_name' => $new_coupon_code . '-' . $order_id,
            'post_content' => '',
            'post_type' => 'shop_coupon',
            'post_status' => 'publish'
        );
        $id = wp_insert_post($post, true);
        if ($id) {
            $settings = $this->admin->getCouponSettings();
            $expired_date = $this->admin->getCouponExpireDate($order_date);
            $coupon_type = isset($settings['coupon_type']) ? sanitize_text_field($settings['coupon_type']) : '0';
            add_post_meta($id, 'discount_type', ($coupon_type == 0) ? "percent" : "fixed_cart");
            $amount = isset($settings['coupon_amount']) ? sanitize_text_field($settings['coupon_amount']) : '0';
            add_post_meta($id, 'coupon_amount', $amount);
            //
            if (isset($settings['minimum_amount']) && ($settings['minimum_amount'] > 0)) {
                add_post_meta($id, 'minimum_amount', floatval($settings['minimum_amount']));
            }
            if (isset($settings['maximum_amount']) && ($settings['maximum_amount'] > 0)) {
                add_post_meta($id, 'maximum_amount', floatval($settings['maximum_amount']));
            }
            add_post_meta($id, 'individual_use', isset($settings['individual_use']) ? $settings['individual_use'] : 'no');
            add_post_meta($id, 'exclude_sale_items', isset($settings['exclude_sale_items']) ? $settings['exclude_sale_items'] : 'no');
            if (isset($settings['product_ids']) && !empty($settings['product_ids'])) {
                add_post_meta($id, 'product_ids', implode(',', $settings['product_ids']));
            }
            if (isset($settings['exclude_product_ids']) && !empty($settings['exclude_product_ids'])) {
                add_post_meta($id, 'exclude_product_ids', implode(',', $settings['exclude_product_ids']));
            }
            if (isset($settings['product_categories']) && !empty($settings['product_categories'])) {
                add_post_meta($id, 'product_categories', $settings['product_categories']);
            }
            if (isset($settings['exclude_product_categories']) && !empty($settings['exclude_product_categories'])) {
                add_post_meta($id, 'exclude_product_categories', $settings['exclude_product_categories']);
            }
            if (isset($settings['coupon_applicable_to']) && !empty($settings['coupon_applicable_to']) && $settings['coupon_applicable_to'] != "all") {
                add_post_meta($id, 'customer_email', $email);
            }
            add_post_meta($id, 'usage_limit', '1');
            add_post_meta($id, 'usage_limit_per_user', '1');
            if (isset($expired_date['woo_coupons']) && !empty($expired_date['woo_coupons'])) {
                add_post_meta($id, 'expiry_date', $expired_date['woo_coupons']);
                if (!empty($expired_date['woo_coupons'])) {
                    add_post_meta($id, 'date_expires', strtotime($expired_date['woo_coupons']));
                }
            }
            add_post_meta($id, 'apply_before_tax', 'yes');
            add_post_meta($id, 'free_shipping', 'no');
            add_post_meta($id, '_rnoc_shop_coupon_type', 'retainful');
            //old
            add_post_meta($id, 'email', $email);
            $order = $this->wc_functions->getOrder($order_id);
            $user_id = $this->wc_functions->getOrderUserId($order);
            add_post_meta($id, 'user_id', $user_id);
            add_post_meta($id, 'order_id', $order_id);
            add_post_meta($id, 'rnoc_order_id', $order_id);
            add_post_meta($id, 'coupon_type', $coupon_type);
            add_post_meta($id, 'coupon_value', $amount);
            if (isset($expired_date['retainful_coupons']) && !empty($expired_date['retainful_coupons'])) {
                add_post_meta($id, 'coupon_expired_on', $expired_date['retainful_coupons']);
            }
            do_action('rnoc_after_create_virtual_coupon',$id,$settings,$order_id,$email);
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
        if (empty($order_id)) return NULL;
        $post_args = array('post_type' => array('rnoc_order_coupon', 'shop_coupon'), 'numberposts' => '1', 'meta_key' => 'order_id', 'meta_value' => $order_id);
        $posts = get_posts($post_args);
        if (!empty($posts)) {
            foreach ($posts as $post) {
                if (isset($post->ID)) {
                    $post_order_id = get_post_meta($post->ID, 'order_id', true);
                    if (($post_order_id == $order_id) && isset($post->post_title)) {
                        return $post->post_title;
                    }
                }
            }
        }
        return NULL;
    }

    /**
     * @param $coupon_code
     * @param string[] $coupon_type
     * @return Object|null
     */
    function getCouponByCouponCode($coupon_code, $coupon_type = array('rnoc_order_coupon', 'shop_coupon'))
    {
        $coupon_code = sanitize_text_field($coupon_code);
        if (empty($coupon_code)) return NULL;
        $post_args = array('post_type' => $coupon_type, 'numberposts' => '1', 'title' => strtoupper($coupon_code));
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
}