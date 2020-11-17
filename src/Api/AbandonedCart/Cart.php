<?php

namespace Rnoc\Retainful\Api\AbandonedCart;

use Exception;
use Rnoc\Retainful\Integrations\MultiLingual;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use stdClass;

class Cart extends RestApi
{
    /**
     * User logged in the store
     * @param $user_name
     */
    function userLoggedOn($user_name)
    {
        if ($user_name) {
            $user = get_user_by('login', $user_name);
            if (!empty($user)) {
                $this->userSignedUp($user->ID);
            } else {
                $user = get_user_by('email', $user_name);
                if ($user) {
                    $this->userSignedUp($user->ID);
                }
            }
        }
    }

    /**
     * Remove cart token on success logout
     */
    function userLoggedOut()
    {
        global $retainful;
        $retainful::$storage->removeValue($this->cart_token_key);
        $retainful::$storage->removeValue($this->cart_tracking_started_key);
        //$this->removeSessionBillingDetails();
        //$this->removeSessionShippingDetails();
    }

    /**
     * When user signed up
     * @param $user_id
     */
    function userSignedUp($user_id)
    {
        global $retainful;
        $cart_token = $retainful::$storage->getValue($this->cart_token_key);
        if (!empty($cart_token)) {
            update_user_meta($user_id, $this->cart_token_key_for_db, $cart_token);
        }
        $cart_created_at = $retainful::$storage->getValue($this->cart_tracking_started_key);
        if (!empty($cart_created_at)) {
            update_user_meta($user_id, $this->cart_tracking_started_key_for_db, $cart_created_at);
        }
    }

    /**
     * Show GDPR message to guest user
     * @param $fields
     * @return mixed
     */
    function guestGdprMessage($fields)
    {
        global $retainful;
        $settings = $retainful::$plugin_admin->getAdminSettings();
        $enable_gdpr_compliance = (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'])) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'] : 0;
        if ($enable_gdpr_compliance) {
            if (isset($settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'])) {
                $existing_label = $fields['billing']['billing_email']['label'];
                $fields['billing']['billing_email']['label'] = $existing_label . "<br><small>" . $settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'] . "</small>";
            }
        }
        return $fields;
    }

    /**
     * Show GDPR message to logged in users
     */
    function userGdprMessage()
    {
        global $retainful;
        $settings = $retainful::$plugin_admin->getAdminSettings();
        $enable_gdpr_compliance = (isset($settings[RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'])) ? $settings[RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'] : 0;
        if ($enable_gdpr_compliance) {
            if (isset($settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'])) {
                echo "<p><small>" . __($settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'], RNOC_TEXT_DOMAIN) . "</small></p>";
            }
        }
    }

    /**
     * Track the customer, and set details to session
     */
    function setCustomerData()
    {
        global $retainful;
        if (isset($_POST['billing_email'])) {
            //Post details
            $billing_first_name = (isset($_POST['billing_first_name'])) ? sanitize_text_field($_POST['billing_first_name']) : '';
            $billing_last_name = (isset($_POST['billing_last_name'])) ? sanitize_text_field($_POST['billing_last_name']) : '';
            $billing_company = (isset($_POST['billing_company'])) ? sanitize_text_field($_POST['billing_company']) : '';
            $billing_address_1 = (isset($_POST['billing_address_1'])) ? sanitize_text_field($_POST['billing_address_1']) : '';
            $billing_address_2 = (isset($_POST['billing_address_2'])) ? sanitize_text_field($_POST['billing_address_2']) : '';
            $billing_city = (isset($_POST['billing_city'])) ? sanitize_text_field($_POST['billing_city']) : '';
            $billing_state = (isset($_POST['billing_state'])) ? sanitize_text_field($_POST['billing_state']) : '';
            $billing_zipcode = (isset($_POST['billing_postcode'])) ? sanitize_text_field($_POST['billing_postcode']) : '';
            $billing_country = (isset($_POST['billing_country'])) ? sanitize_text_field($_POST['billing_country']) : '';
            $billing_phone = (isset($_POST['billing_phone'])) ? sanitize_text_field($_POST['billing_phone']) : '';
            $billing_email = sanitize_email($_POST['billing_email']);
            $ship_to_billing = (isset($_POST['ship_to_billing'])) ? $_POST['ship_to_billing'] : 0;
            $order_notes = (isset($_POST['order_notes'])) ? sanitize_text_field($_POST['order_notes']) : '';
            $shipping_first_name = (isset($_POST['shipping_first_name'])) ? sanitize_text_field($_POST['shipping_first_name']) : '';
            $shipping_last_name = (isset($_POST['shipping_last_name'])) ? sanitize_text_field($_POST['shipping_last_name']) : '';
            $shipping_company = (isset($_POST['shipping_company'])) ? sanitize_text_field($_POST['shipping_company']) : '';
            $shipping_address_1 = (isset($_POST['shipping_address_1'])) ? sanitize_text_field($_POST['shipping_address_1']) : '';
            $shipping_address_2 = (isset($_POST['shipping_address_2'])) ? sanitize_text_field($_POST['shipping_address_2']) : '';
            $shipping_city = (isset($_POST['shipping_city'])) ? sanitize_text_field($_POST['shipping_city']) : '';
            $shipping_state = (isset($_POST['shipping_state'])) ? sanitize_text_field($_POST['shipping_state']) : '';
            $shipping_zipcode = (isset($_POST['shipping_postcode'])) ? sanitize_text_field($_POST['shipping_postcode']) : '';
            $shipping_country = (isset($_POST['shipping_country'])) ? sanitize_text_field($_POST['shipping_country']) : '';
            //Billing details
            $billing_address = array(
                'billing_first_name' => $billing_first_name,
                'billing_last_name' => $billing_last_name,
                'billing_company' => $billing_company,
                'billing_address_1' => $billing_address_1,
                'billing_address_2' => $billing_address_2,
                'billing_city' => $billing_city,
                'billing_state' => $billing_state,
                'billing_postcode' => $billing_zipcode,
                'billing_country' => $billing_country,
                'billing_phone' => $billing_phone
            );
            $retainful::$woocommerce->setSession('is_buyer_accepting_marketing', 1);
            $this->setCustomerBillingDetails($billing_address);
            //Shipping to same billing address
            if (!empty($ship_to_billing)) {
                $shipping_address = array(
                    'shipping_first_name' => $shipping_first_name,
                    'shipping_last_name' => $shipping_last_name,
                    'shipping_company' => $shipping_company,
                    'shipping_address_1' => $shipping_address_1,
                    'shipping_address_2' => $shipping_address_2,
                    'shipping_city' => $shipping_city,
                    'shipping_state' => $shipping_state,
                    'shipping_postcode' => $shipping_zipcode,
                    'shipping_country' => $shipping_country,
                );
            } else {
                $shipping_address = array(
                    'shipping_first_name' => $billing_address['billing_first_name'],
                    'shipping_last_name' => $billing_address['billing_last_name'],
                    'shipping_company' => $billing_address['billing_company'],
                    'shipping_address_1' => $billing_address['billing_address_1'],
                    'shipping_address_2' => $billing_address['billing_address_2'],
                    'shipping_city' => $billing_address['billing_city'],
                    'shipping_state' => $billing_address['billing_state'],
                    'shipping_postcode' => $billing_address['billing_postcode'],
                    'shipping_country' => $billing_address['billing_country'],
                );
            }
            $this->setSessionShippingDetails($shipping_address);
            //Billing email
            $retainful::$woocommerce->setCustomerEmail($billing_email);
            //Set update and created date
            $session_created_at = $retainful::$storage->getValue('rnoc_session_created_at');
            $current_time = current_time('timestamp', true);
            if (empty($session_created_at)) {
                $retainful::$storage->setValue('rnoc_session_created_at', $current_time);
            }
        }
        if ($this->isValidCartToTrack()) {
            $cart = $this->getUserCart();
            $encrypted_cart = $this->encryptData($cart);
            wp_send_json_success($encrypted_cart);
        } else {
            //dont send anything
            wp_send_json(array('success' => false));
        }
    }

    /**
     * send the ajax encrypted cart
     */
    function ajaxGetEncryptedCart()
    {
        $cart = $this->getUserCart();
        $encrypted_cart = $this->encryptData($cart);
        wp_send_json_success($encrypted_cart);
    }

    /**
     * get the AC Js tracking engine
     * @return mixed|void
     */
    function getAbandonedCartJsEngineUrl()
    {
        return apply_filters('rnoc_get_abandoned_cart_tracking_js_engine_url', 'https://js.retainful.com/woocommerce/v2/retainful.js?ver=' . RNOC_VERSION);
    }

    /**
     * get the referral url
     * @return mixed|void
     */
    function getReferralUrl()
    {
        return apply_filters('referral_engine_url', 'https://js.retainful.com/woocommerce/v1/referral-wocommerce.js');
    }

    /**
     * Adding the script to track user cart
     */
    function addCartTrackingScripts()
    {
        if (!wp_script_is('wc-cart-fragments', 'enqueued')) {
            wp_enqueue_script('wc-cart-fragments');
        }
        if (!wp_script_is(RNOC_PLUGIN_PREFIX . 'referral', 'enqueued')) {
            wp_enqueue_script(RNOC_PLUGIN_PREFIX . 'referral', $this->getReferralUrl(), array(), RNOC_VERSION, true);
        }
        if (!wp_script_is(RNOC_PLUGIN_PREFIX . 'track-user-cart', 'enqueued')) {
            global $retainful;
            wp_enqueue_script(RNOC_PLUGIN_PREFIX . 'track-user-cart', $this->getAbandonedCartJsEngineUrl(), array('jquery'), RNOC_VERSION, false);
            $user_ip = $this->getClientIp();
            $user_ip = $this->formatUserIP($user_ip);
            $data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'jquery_url' => includes_url('js/jquery/jquery.js'),
                'ip' => $user_ip,
                'version' => RNOC_VERSION,
                'public_key' => $retainful::$plugin_admin->getApiKey(),
                'api_url' => $retainful::$api->getAbandonedCartEndPoint(),
                'tracking_element_selector' => $this->getTrackingElementId(),
                'cart_tracking_engine' => $retainful::$plugin_admin->getCartTrackingEngine()
            );
            $data = apply_filters('rnoc_add_cart_tracking_scripts', $data);
            wp_localize_script(RNOC_PLUGIN_PREFIX . 'track-user-cart', 'retainful_cart_data', $data);
        }
    }

    /**
     * Clean the url
     * @param $good_protocol_url
     * @param $original_url
     * @param $_context
     * @return string
     */
    function uncleanUrl($good_protocol_url, $original_url, $_context)
    {
        if (false !== strpos($original_url, 'data-cfasync')) {
            remove_filter('clean_url', 'unclean_url', 10);
            $url_parts = parse_url($good_protocol_url);
            return $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . "' data-cfasync='false";
        }
        return $good_protocol_url;
    }

    /**
     * Adding script ID attribute
     * @param $src
     * @param $handle
     * @return string
     */
    function addCloudFlareAttrScript($src, $handle)
    {
        if ($handle == RNOC_PLUGIN_PREFIX . 'track-user-cart') {
            return $src . "' data-cfasync='false";
        }
        return $src;
    }

    /**
     * Recover user cart
     */
    function recoverUserCart()
    {
        // recovery URL
        if (!empty($_REQUEST['token']) && !empty($_REQUEST['hash'])) {
            $this->recoverCart();
        }
    }

    /**
     * Add abandon cart coupon automatically
     */
    function applyAbandonedCartCoupon()
    {
        global $retainful;
        if (isset($_REQUEST['retainful_ac_coupon']) && !empty($_REQUEST['retainful_ac_coupon'])) {
            $coupon_code = sanitize_text_field($_REQUEST['retainful_ac_coupon']);
            $retainful::$storage->setValue('rnoc_ac_coupon', $coupon_code);
        }
        $session_coupon = $retainful::$storage->getValue('rnoc_ac_coupon');
        if (!empty($session_coupon)) {
            if ($retainful::$woocommerce->isValidCoupon($session_coupon)) {
                $cart = $retainful::$woocommerce->getCart();
                if (!empty($cart) && !$retainful::$woocommerce->hasDiscount($session_coupon)) {
                    if ($retainful::$woocommerce->addDiscount($session_coupon)) {
                        $retainful::$storage->removeValue('rnoc_ac_coupon');
                    }
                }
            } else {
                $retainful::$storage->removeValue('rnoc_ac_coupon');
            }
        }
    }

    /**
     * User cart updated
     */
    function cartUpdated()
    {
        global $retainful;
        $cart_token = $this->getCartToken();
        if ($cart_token) {
            try {
                $this->syncCartData();
            } catch (Exception $exception) {
                // clear session so a new Retainful order can be created
                if (404 == $exception->getCode()) {
                    $this->removeCartToken();
                    // try to create the order below
                    $cart_token = null;
                }
                //log exception
            }
        }
        if (!$cart_token && !$retainful::$woocommerce->isCartEmpty()) {
            try {
                $this->syncCartData();
            } catch (Exception $exception) {
                //log exception
            }
        }
    }

    /**
     * Check weather Retainful needs to track the cart or not
     * @return bool
     */
    function needToTrackCart()
    {
        global $retainful;
        $cart_hash = $this->generateCartHash();
        $cart_created_at = $this->userCartCreatedAt();
        if (empty($cart_hash) && empty($cart_created_at)) {
            return false;
        } elseif (empty($cart_hash) && !empty($cart_created_at)) {
            return $this->comparePreviousCartHash($cart_hash);
        } elseif (!empty($cart_hash) && empty($cart_created_at)) {
            //TODO What if it fails to create cart created time
            $time = current_time('timestamp', true);
            $retainful::$storage->setValue($this->cart_tracking_started_key, $time);
            if ($user_id = get_current_user_id()) {
                $this->setCartCreatedDate($user_id, $time);
            }
            return $this->comparePreviousCartHash($cart_hash);
        } else {
            return $this->comparePreviousCartHash($cart_hash);
        }
    }

    /**
     * compare old and current cart hash to sync the cart;
     * This will help from tracking same cart multiple times
     * This will also reduce the number of API requests
     * @param $current_cart_hash
     * @return bool
     */
    function comparePreviousCartHash($current_cart_hash)
    {
        global $retainful;
        $old_cart_hash = $retainful::$storage->getValue($this->previous_cart_hash_key);
        $is_not_similar = ($old_cart_hash != $current_cart_hash);
        if ($is_not_similar) {
            $retainful::$storage->setValue($this->previous_cart_hash_key, $current_cart_hash);
        }
        $retainful::$storage->setValue('rnoc_current_cart_hash', $current_cart_hash);
        return $is_not_similar;
    }

    /**
     * Sync cart with the retainful
     * @param bool $force_sync
     */
    function syncCartData($force_sync = false)
    {
        global $retainful;
        if (!$this->isValidCartToTrack()) {
            return;
        }
        if ($force_sync || $this->needToTrackCart()) {
            $cart = $this->getUserCart();
            if (!empty($cart)) {
                $retainful::$plugin_admin->logMessage($cart, 'cart');
                $client_ip = $this->formatUserIP($this->getClientIp());
                $cart_hash = $this->encryptData($cart);
                if (!empty($cart_hash)) {
                    $token = $this->getCartToken();
                    $extra_headers = array(
                        "X-Client-Referrer-IP" => (!empty($client_ip)) ? $client_ip : null,
                        "X-Retainful-Version" => RNOC_VERSION,
                        "X-Cart-Token" => $token,
                        "Cart-Token" => $token,
                    );
                    $this->syncCart($cart_hash, $extra_headers);
                }
            }
        }
    }

    /**
     * Need to track zero value carts or not
     * @param $return
     * @return mixed
     */
    function isZeroValueCart($return)
    {
        global $retainful;
        if ($retainful::$plugin_admin->trackZeroValueCarts() == "no") {
            if (!empty($retainful::$woocommerce->getCart()) && $retainful::$woocommerce->getCartSubTotal() <= 0 && $retainful::$woocommerce->getCartTotalPrice() <= 0) {
                $return = false;
            }
        }
        return $return;
    }

    /**
     * need to track user cart
     * @return bool
     */
    function isValidCartToTrack()
    {
        $crawler_detect = new CrawlerDetect();
        if ($crawler_detect->isCrawler()) {
            return false;
        }
        if ($this->canTrackAbandonedCarts() == false) {
            return false;
        }
        return true;
    }

    /**
     * Handle loading/setting Retainful data for the persistent cart.
     */
    function handlePersistentCart()
    {
        global $retainful;
        // bail for guest users, when the cart is empty, or when doing a WP cron request
        if (!is_user_logged_in() || $retainful::$woocommerce->isCartEmpty() || defined('DOING_CRON')) {
            return NULL;
        }
        $user_id = get_current_user_id();
        $cart_token = get_user_meta($user_id, $this->cart_token_key_for_db, true);
        if ($cart_token && !$this->retrieveCartToken()) {
            // for a logged in user with a persistent cart, set the cart token to the session
            $this->setCartToken($cart_token);
        } elseif (!$cart_token && $this->retrieveCartToken()) {
            // when a guest user with an existing cart logs in, save the cart token to user meta
            $cart_token = $this->retrieveCartToken();
            update_user_meta($user_id, $this->cart_token_key_for_db, $cart_token);
        }
    }

    /**
     * Get the current user's cart token
     * @return array|string|null
     */
    function getCartToken()
    {
        $cart_token = $this->retrieveCartToken();
        if (empty($cart_token)) {
            $cart_token = $this->generateCartToken();
            $this->setCartToken($cart_token);
        }
        return apply_filters('rnoc_get_cart_token', $cart_token, $this);
    }

    /**
     * @param null $user_id
     */
    function removeCartToken($user_id = NULL)
    {
        global $retainful;
        $retainful::$storage->removeValue($this->cart_token_key);
        if ($user_id || ($user_id = get_current_user_id())) {
            delete_user_meta($user_id, $this->cart_token_key_for_db);
            delete_user_meta($user_id, $this->pending_recovery_key_for_db);
        }
    }

    /**
     * Get the line items details
     * @return array
     */
    function getCartLineItemsDetails()
    {
        global $retainful;
        $items = array();
        $cart = $retainful::$woocommerce->getCart();
        if (!empty($cart)) {
            foreach ($cart as $item_key => $item_details) {
                //Deceleration
                $tax_details = array();
                $item_quantity = (isset($item_details['quantity']) && !empty($item_details['quantity'])) ? $item_details['quantity'] : NULL;
                $variant_id = (isset($item_details['variation_id']) && !empty($item_details['variation_id'])) ? $item_details['variation_id'] : 0;
                $product_id = (isset($item_details['product_id']) && !empty($item_details['product_id'])) ? $item_details['product_id'] : 0;
                $is_variable_item = (!empty($variant_id));
                $item = apply_filters('woocommerce_cart_item_product', $item_details['data'], $item_details, $item_key);
                if (empty($item)) {
                    if (!empty($variant_id)) {
                        $item = $retainful::$woocommerce->getProduct($variant_id);
                    } elseif (!empty($product_id)) {
                        $item = $retainful::$woocommerce->getProduct($product_id);
                    }
                }
                $line_tax = (isset($item_details['line_tax']) && !empty($item_details['line_tax'])) ? $item_details['line_tax'] : 0;
                if ($line_tax > 0) {
                    $tax_details[] = array(
                        'rate' => 0,
                        'zone' => 'province',
                        'price' => $this->formatDecimalPriceRemoveTrailingZeros($line_tax),
                        'title' => 'tax',
                        'source' => 'WooCommerce',
                        'position' => 1,
                        'compare_at' => 0,
                    );
                }
                $image_url = $retainful::$woocommerce->getProductImageSrc($item);
                if (!empty($item) && !empty($item_quantity)) {
                    $item_array = array(
                        'key' => $item_key,
                        'sku' => $retainful::$woocommerce->getItemSku($item),
                        'price' => $this->formatDecimalPriceRemoveTrailingZeros($retainful::$woocommerce->getCartItemPrice($item)),
                        'title' => $retainful::$woocommerce->getItemName($item),
                        'vendor' => 'woocommerce',
                        'taxable' => ($line_tax != 0),
                        'quantity' => $item_quantity,
                        'tax_lines' => $tax_details,
                        'line_price' => $this->formatDecimalPriceRemoveTrailingZeros($this->getLineItemTotal($item_details)),
                        'product_id' => $product_id,
                        'variant_id' => $variant_id,
                        'variant_price' => $this->formatDecimalPriceRemoveTrailingZeros(($is_variable_item) ? $retainful::$woocommerce->getCartItemPrice($item) : 0),
                        'variant_title' => ($is_variable_item) ? $retainful::$woocommerce->getItemName($item) : 0,
                        'image_url' => $image_url,
                        'product_url' => $retainful::$woocommerce->getProductUrl($item, $item_details),
                        'user_id' => NULL,
                        'properties' => array()
                    );
                    $items[] = apply_filters('rnoc_get_cart_line_item_details', $item_array, $cart, $item_key, $item, $item_details);
                }
            }
        }
        return apply_filters("rnoc_get_abandoned_cart_line_items", $items, $cart);
    }

    /**
     * Line item total
     * @param $item_details
     * @return int
     */
    function getLineItemTotal($item_details)
    {
        global $retainful;
        $line_total = (isset($item_details['line_total']) && !empty($item_details['line_total'])) ? $item_details['line_total'] : 0;
        if (!$retainful::$woocommerce->isPriceExcludingTax()) {
            $line_total_tax = (isset($item_details['line_tax']) && !empty($item_details['line_tax'])) ? $item_details['line_tax'] : 0;
        } else {
            $line_total_tax = 0;
        }
        $total = $line_total + $line_total_tax;
        return apply_filters('retainful_get_line_item_total', $total, $line_total, $line_total_tax, $item_details, $this);
    }

    /**
     * get the cart tax details
     * @return array
     */
    function getCartTaxDetails()
    {
        global $retainful;
        $tax_details = $retainful::$woocommerce->getCartTaxes();
        $taxes = array();
        if (!empty($tax_details)) {
            foreach ($tax_details as $key => $tax_detail) {
                $taxes[] = array(
                    'rate' => 0,
                    'price' => $this->formatDecimalPrice((isset($tax_detail->amount)) ? $tax_detail->amount : 0),
                    'title' => (isset($tax_detail->label)) ? $tax_detail->label : 'Tax'
                );
            }
        }
        return $taxes;
    }

    /**
     * Currency details for cart
     * @param $cart_total
     * @param $current_currency_code
     * @param $default_currency_code
     * @return array
     */
    function getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code)
    {
        if ($current_currency_code != $default_currency_code) {
            $exchange_rate = apply_filters('rnoc_get_currency_rate', $cart_total, $current_currency_code);
            $shop_cart_total = $this->convertToCurrency($cart_total, $exchange_rate);
        } else {
            $shop_cart_total = $cart_total;
        }
        $details = array(
            'shop_money' => array(
                'amount' => $shop_cart_total,
                'currency_code' => $default_currency_code
            ),
            'presentment_money' => array(
                'amount' => $cart_total,
                'currency_code' => $current_currency_code
            )
        );
        return apply_filters('rnoc_get_cart_currency_details', $details, $current_currency_code, $default_currency_code);
    }

    /**
     * get user IP details
     * @return array|mixed|string|null
     */
    function getUserIPDetails()
    {
        $user_ip = $this->retrieveUserIp();
        if (empty($user_ip)) {
            $user_ip = $this->getClientIp();
            $user_ip = $this->formatUserIP($user_ip);
        }
        return $user_ip;
    }

    /**
     * Preprocess cart required for API call
     * @return array
     */
    function getUserCart()
    {
        global $retainful;
        $language_helper = new MultiLingual();
        $current_language = $language_helper->getCurrentLanguage();
        $customer_details = $this->getCustomerDetails();
        $cart_token = $this->getCartToken();
        $current_currency_code = $this->getCurrentCurrencyCode();
        $default_currency_code = $retainful::$plugin_admin->getBaseCurrency();
        $cart_created_at = $this->userCartCreatedAt();
        $cart_total = $this->formatDecimalPrice($retainful::$woocommerce->getCartTotalPrice());
        $cart_hash = $this->generateCartHash();
        $consider_on_hold_order_as_ac = $this->considerOnHoldAsAbandoned();
        $recovered_at = $retainful::$storage->getValue('rnoc_recovered_at');
        $cart = array(
            'cart_type' => 'cart',
            'treat_on_hold_as_complete' => ($consider_on_hold_order_as_ac == 0),
            'plugin_version' => RNOC_VERSION,
            'cart_hash' => $cart_hash,
            'ip' => $this->getUserIPDetails(),
            'id' => $cart_token,
            'name' => '#' . $cart_token,
            'email' => (isset($customer_details['email'])) ? $customer_details['email'] : NULL,
            'token' => $cart_token,
            'currency' => $default_currency_code,
            'customer' => $customer_details,
            'tax_lines' => $this->getCartTaxDetails(),
            'total_tax' => $retainful::$woocommerce->getCartTotalTax(),
            'cart_token' => $cart_token,
            'created_at' => $this->formatToIso8601($cart_created_at),
            'line_items' => $this->getCartLineItemsDetails(),
            'updated_at' => $this->formatToIso8601(''),
            'total_price' => $cart_total,
            'completed_at' => NULL,
            'discount_codes' => $retainful::$woocommerce->getAppliedDiscounts(),
            'shipping_lines' => array(),
            'subtotal_price' => $this->formatDecimalPrice($retainful::$woocommerce->getCartSubTotal()),
            'total_price_set' => $this->getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code),
            'taxes_included' => (!$retainful::$woocommerce->isPriceExcludingTax()),
            'customer_locale' => $current_language,
            'order_status' => NULL,
            'total_discounts' => $this->formatDecimalPrice($retainful::$woocommerce->getCartTotalDiscount()),
            'shipping_address' => $this->getCustomerShippingAddressDetails(),
            'billing_address' => $this->getCustomerBillingAddressDetails(),
            'presentment_currency' => $current_currency_code,
            'abandoned_checkout_url' => $this->getRecoveryLink($cart_token),
            'total_line_items_price' => $this->formatDecimalPrice($retainful::$woocommerce->getCartTotal()),
            'buyer_accepts_marketing' => $this->isBuyerAcceptsMarketing(),
            'client_session' => $retainful::$woocommerce->getClientSession(),
            'woocommerce_totals' => $this->getCartTotals(),
            'recovered_at' => (!empty($recovered_at)) ? $this->formatToIso8601($recovered_at) : NULL,
            'recovered_by_retainful' => ($retainful::$storage->getValue('rnoc_recovered_by_retainful')) ? true : false,
            'recovered_cart_token' => $retainful::$storage->getValue('rnoc_recovered_cart_token'),
            'client_details' => $this->getClientDetails()
        );
        return apply_filters('rnoc_get_user_cart', $cart);
    }

    /**
     * get cart totals
     * @return array
     */
    function getCartTotals()
    {
        global $retainful;
        return array(
            'total_price' => $this->formatDecimalPrice($retainful::$woocommerce->getCartTotalPrice()),
            'subtotal_price' => $this->formatDecimalPrice($retainful::$woocommerce->getCartSubTotal()),
            'total_tax' => $this->formatDecimalPrice($retainful::$woocommerce->getCartTaxTotal() + $retainful::$woocommerce->getCartShippingTaxTotal()),
            'total_discounts' => $this->formatDecimalPrice($retainful::$woocommerce->getCartDiscountTotal()),
            'total_shipping' => $this->formatDecimalPrice($retainful::$woocommerce->getCartShippingTotal()),
            'fee_items' => $this->getCartFeeDetails(),
        );
    }

    /**
     * get cart fee details
     * @return array
     */
    function getCartFeeDetails()
    {
        global $retainful;
        $fee_items = array();
        if ($fees = $retainful::$woocommerce->getCartFees()) {
            foreach ($fees as $fee) {
                $fee_items[] = array(
                    'title' => html_entity_decode($fee->name),
                    'key' => $fee->id,
                    'amount' => $this->formatDecimalPrice($fee->amount)
                );
            }
        }
        return $fee_items;
    }

    /**
     * Recover the user cart
     */
    function recoverCart()
    {
        global $retainful;
        $checkout_url = $retainful::$woocommerce->getCheckoutUrl();
        try {
            $this->reCreateCart();
        } catch (Exception $exception) {
        }
        if (!empty($_GET)) {
            foreach ($_GET as $key => $value) {
                if (!in_array($key, array("token", "hash", "wc-api"))) {
                    $checkout_url = add_query_arg($key, $value, $checkout_url);
                }
            }
        }
        $checkout_url = apply_filters('retainful_recovery_redirect_url', $checkout_url);
        wp_safe_redirect($checkout_url);
        exit;
    }

    function printRefreshFragmentScript()
    {
        if ($this->refreshFragmentsOnPageLoad()) {
            ?>
            <script>
                jQuery(window).load(function (e) {
                    jQuery(document.body).trigger('wc_fragment_refresh');
                });
            </script>
            <?php
        }
    }

    /**
     * Recreate cart
     * @return bool
     * @throws Exception
     */
    function reCreateCart()
    {
        global $retainful;
        $data = wc_clean(rawurldecode($_REQUEST['token']));
        $hash = wc_clean($_REQUEST['hash']);
        if ($this->isHashMatches($hash, $data)) {
            // decode
            $data = json_decode(base64_decode($data));
            // readability
            $cart_token = isset($data->cart_token) ? $data->cart_token : NULL;
            if (!empty($cart_token)) {
                if (empty($cart_token)) {
                    throw new Exception('Cart token missed');
                }
                $app_id = $retainful::$plugin_admin->getApiKey();
                $data = $retainful::$api->retrieveCartDetails($app_id, $cart_token);
                //When the cart details from API was empty, then we no need to proceed further
                if (empty($data)) {
                    return false;
                }
                do_action('rnoc_before_recreate_cart', $data);
                $order_id = $this->getOrderIdFromCartToken($cart_token);
                $note = __('Customer visited Retainful order recovery URL.', RNOC_TEXT_DOMAIN);
                if ($order_id && $order = $retainful::$woocommerce->getOrder($order_id)) {
                    // re-enable a cancelled order for payment
                    if ($retainful::$woocommerce->hasOrderStatus($order, 'cancelled')) {
                        $retainful::$woocommerce->setOrderStatus($order, 'pending', $note);
                    } else {
                        $retainful::$woocommerce->setOrderNote($order, $note);
                    }
                    $redirect = $retainful::$woocommerce->isOrderNeedPayment($order) ? $retainful::$woocommerce->getOrderPaymentURL($order) : $retainful::$woocommerce->getOrderReceivedURL($order);
                    $retainful::$storage->setValue($this->pending_recovery_key, true);
                    // set (or refresh, if already set) session
                    $retainful::$woocommerce->setSessionCookie(true);
                    wp_safe_redirect($redirect);
                    exit;
                }
                $is_buyer_accept_marketing = (isset($data->buyer_accepts_marketing) && $data->buyer_accepts_marketing) ? 1 : 0;
                $retainful::$woocommerce->setSession('is_buyer_accepting_marketing', $is_buyer_accept_marketing);
                $user_currency = isset($data->presentment_currency) ? $data->presentment_currency : $retainful::$woocommerce->getDefaultCurrency();
                apply_filters('rnoc_set_current_currency_code', $user_currency);
                $retainful::$storage->setValue('rnoc_recovered_at', current_time('timestamp', true));
                $retainful::$storage->setValue('rnoc_recovered_by_retainful', 1);
                $retainful::$storage->setValue('rnoc_recovered_cart_token', $cart_token);
                // check if cart is associated with a registered user / persistent cart
                $user_id = $this->getUserIdFromCartToken($cart_token);
                $cart_recreated = false;
                // order id is associated with a registered user
                if ($user_id && $this->loginUser($user_id)) {
                    // save order note to be applied after redirect
                    update_user_meta($user_id, $this->order_note_key_for_db, $note);
                    $current_cart = $retainful::$woocommerce->getCart();
                    if (empty($current_cart)) {
                        $cart_recreated = false;
                    } else {
                        $cart_recreated = true;
                    }
                }
                $cart_recreated = apply_filters('rnoc_cart_re_created', $cart_recreated, $data);
                if (!$cart_recreated) {
                    // set customer note in session, if present
                    $retainful::$storage->setValue($this->order_note_key, $note);
                    // guest user
                    $this->reCreateCartForGuestUsers($data);
                }
                $this->populateSessionDetails($data);
                $cart_session = $retainful::$woocommerce->getSession('cart');
                if (empty($cart_session)) {
                    $client_session = isset($data->client_session) ? $data->client_session : array();
                    if (!empty($client_session)) {
                        $cart = json_decode(wp_json_encode($client_session->cart), true);
                        if (!empty($cart)) {
                            $retainful::$woocommerce->setSession('cart', $cart);
                        }
                    } else {
                        $cart_contents = isset($data->cart_contents) ? $data->cart_contents : array();
                        $this->recreateCartFromCartContents($cart_contents);
                    }
                }
            }
        }
        return false;
    }

    /**
     * get the tracking data
     * @return array
     */
    function getTrackingCartData()
    {
        global $retainful;
        $cart = $this->getUserCart();
        $retainful::$plugin_admin->logMessage($cart, 'cart');
        $data = array(
            'cart_token' => $this->getCartToken(),
            'cart_hash' => $this->generateCartHash(),
            'data' => $this->encryptData($cart)
        );
        return apply_filters('rnoc_get_tracking_data', $data);
    }

    /**
     * render the tracking div
     */
    function renderAbandonedCartTrackingDiv()
    {
        $data = array();
        $cart_created_at = $this->userCartCreatedAt();
        if ($this->isValidCartToTrack() && !empty($cart_created_at)) {
            $data = $this->getTrackingCartData();
        }
        echo $this->getCartTrackingDiv($data);
    }

    /**
     * get the abandoned cart tracking div element
     * @param array $cart_data
     * @return string
     */
    function getCartTrackingDiv($cart_data = array())
    {
        $tracking_div = sprintf(
            '<div id="%1$s" style="display: none !important;">%2$s</div>',
            esc_attr($this->getTrackingElementId()),
            esc_html(wp_json_encode($cart_data)));
        return apply_filters('rnoc_get_cart_tracking_div', $tracking_div, $cart_data);
    }

    /**
     * Add to
     * @param $fragments
     * @return mixed
     */
    function addToCartFragments($fragments)
    {
        global $retainful;
        $selector = 'div#' . $this->getTrackingElementId();
        $data = array();
        $cart_created_at = $this->userCartCreatedAt();
        if (empty($cart_created_at)) {
            $this->needToTrackCart();
            $cart_created_at = $this->userCartCreatedAt();
        }
        if ($this->isValidCartToTrack()) {
            if (!empty($cart_created_at)) {
                $data = $this->getTrackingCartData();
            } else {
                $force_refresh = $retainful::$storage->getValue('rnoc_force_refresh_cart');
                if (empty($force_refresh) && !empty($retainful::$woocommerce->getCart())) {
                    $retainful::$storage->setValue('rnoc_force_refresh_cart', 1);
                    $data = array('force_refresh_carts' => 1);
                }
            }
        }
        $fragments[$selector] = $this->getCartTrackingDiv($data);
        return $fragments;
    }

    /**
     * Gets the tracking element ID.
     * @return string
     */
    public function getTrackingElementId()
    {
        return apply_filters('retainful_abandoned_cart_tracking_element_id', 'retainful-abandoned-cart-data');
    }

    /**
     * populate cart from session data
     * @param $data
     */
    function populateSessionDetails($data)
    {
        global $retainful;
        $customer_email = isset($data->email) ? $data->email : '';
        //Setting the email
        $retainful::$woocommerce->setCustomerEmail($customer_email);
        $billing_details = isset($data->billing_address) ? $data->billing_address : new stdClass();
        $billing_address = array(
            'billing_first_name' => isset($billing_details->first_name) ? $billing_details->first_name : NULL,
            'billing_last_name' => isset($billing_details->last_name) ? $billing_details->last_name : NULL,
            'billing_state' => isset($billing_details->province_code) ? $billing_details->province_code : NULL,
            'billing_phone' => isset($billing_details->phone) ? $billing_details->phone : NULL,
            'billing_postcode' => isset($billing_details->zip) ? $billing_details->zip : NULL,
            'billing_city' => isset($billing_details->city) ? $billing_details->city : NULL,
            'billing_country' => isset($billing_details->country) ? $billing_details->country : NULL,
            'billing_address_1' => isset($billing_details->address1) ? $billing_details->address1 : NULL,
            'billing_address_2' => isset($billing_details->address2) ? $billing_details->address2 : NULL,
            'billing_company' => isset($billing_details->company) ? $billing_details->company : NULL
        );
        $this->setCustomerBillingDetails($billing_address);
        $shipping_details = isset($data->shipping_address) ? $data->shipping_address : new stdClass();
        $shipping_address = array(
            'shipping_first_name' => isset($shipping_details->first_name) ? $shipping_details->first_name : NULL,
            'shipping_last_name' => isset($shipping_details->last_name) ? $shipping_details->last_name : NULL,
            'shipping_state' => isset($shipping_details->province_code) ? $shipping_details->province_code : NULL,
            'shipping_postcode' => isset($shipping_details->zip) ? $shipping_details->zip : NULL,
            'shipping_city' => isset($shipping_details->city) ? $shipping_details->city : NULL,
            'shipping_country' => isset($shipping_details->country) ? $shipping_details->country : NULL,
            'shipping_address_1' => isset($shipping_details->address1) ? $shipping_details->address1 : NULL,
            'shipping_address_2' => isset($shipping_details->address2) ? $shipping_details->address2 : NULL
        );
        $this->setSessionShippingDetails($shipping_address);
    }

    /**
     * Returns $coupons, with any invalid coupons removed
     *
     * @param array $coupons array of string coupon codes
     * @return array $coupons with any invalid codes removed
     * @since 2.1.4
     */
    private function getValidCoupons($coupons)
    {
        global $retainful;
        $valid_coupons = array();
        if ($coupons) {
            foreach ($coupons as $coupon) {
                $coupon_code = isset($coupon->code) ? $coupon->code : NULL;
                $coupon_code = apply_filters('rnoc_recover_cart_before_validate_coupon', $coupon_code, $coupon);
                if (!empty($coupon_code) && $retainful::$woocommerce->isValidCoupon($coupon_code)) {
                    $valid_coupons[] = $coupon_code;
                }
            }
        }
        $valid_coupons = apply_filters("rnoc_recover_cart_coupons", $valid_coupons);
        return $valid_coupons;
    }

    /**
     * Recreate user guest cart
     * @param $data
     */
    function reCreateCartForGuestUsers($data)
    {
        global $retainful;
        // set Retainful data in session
        $this->setCartToken($data->cart_token);
        $retainful::$woocommerce->setSession($this->pending_recovery_key, true);
        $created_at = isset($data->created_at) ? strtotime($data->created_at) : current_time('mysql', true);
        $this->setCartCreatedDate(null, $created_at);
        //$cart = isset($data->line_items) ? $data->line_items : array();
        $data = apply_filters('rnoc_abandoned_cart_recover_guest_cart', $data);
        $client_session = isset($data->client_session) ? $data->client_session : array();
        if (!empty($client_session)) {
            $cart = json_decode(wp_json_encode($client_session->cart), true);
            if (!empty($cart)) {
                $applied_coupons = isset($data->discount_codes) ? $data->discount_codes : array();
                $chosen_shipping_methods = (array)$client_session->chosen_shipping_methods;
                $shipping_method_counts = (array)$client_session->shipping_method_counts;
                $chosen_payment_method = $client_session->chosen_payment_method;
                // base session data
                $retainful::$woocommerce->setSession('cart', $cart);
                $retainful::$woocommerce->setSession('applied_coupons', $this->getValidCoupons($applied_coupons));
                $retainful::$woocommerce->setSession('chosen_shipping_methods', $chosen_shipping_methods);
                $retainful::$woocommerce->setSession('shipping_method_counts', $shipping_method_counts);
                $retainful::$woocommerce->setSession('chosen_payment_method', $chosen_payment_method);
            }
        } else {
            $cart_contents = isset($data->cart_contents) ? $data->cart_contents : array();
            $this->recreateCartFromCartContents($cart_contents);
        }
        // set (or refresh, if already set) session
        $retainful::$woocommerce->setSessionCookie(true);
    }

    /**
     * @param $cart_contents
     */
    function recreateCartFromCartContents($cart_contents)
    {
        global $retainful;
        if (!empty($cart_contents)) {
            $retainful::$woocommerce->emptyUserCart();
            $retainful::$woocommerce->clearWooNotices();
            $remove_list = $this->mustCartItemsKeys();
            foreach ($cart_contents as $key => $cart_item) {
                $array_cart_item = json_decode(wp_json_encode($cart_item), true);
                $this->unsetFromArray($array_cart_item, $remove_list);
                if (!is_array($array_cart_item)) {
                    $array_cart_item = array();
                }
                $variant_id = isset($cart_item->variation_id) ? $cart_item->variation_id : 0;
                $variation = isset($cart_item->variation) ? $cart_item->variation : array();
                if (is_object($variation)) {
                    $variation = json_decode(wp_json_encode($variation), true);
                }
                $retainful::$woocommerce->addToCart($cart_item->product_id, $variant_id, $cart_item->quantity, $variation, $array_cart_item);
            }
        }
    }

    /**
     * Contains the list of keys that every cart ites have
     * @return array
     */
    function mustCartItemsKeys()
    {
        return array(
            'key',
            'line_tax',
            'quantity',
            'variation',
            'line_total',
            'product_id',
            'line_tax_data',
            'line_subtotal_tax',
            'variation_id',
            'data_hash',
            'line_subtotal',
            'data'
        );
    }

    /**
     * remove key value pairs from list
     * @param $full_list
     * @param array $remove_list
     */
    function unsetFromArray(&$full_list, $remove_list = array())
    {
        if (!empty($remove_list)) {
            foreach ($remove_list as $key) {
                if (isset($full_list[$key])) {
                    unset($full_list[$key]);
                }
            }
        }
    }

    /**
     * Check if a user is allowed to be logged in for cart recovery
     *
     * @param int $user_id WP_User id
     * @return bool
     * @since 1.0.0
     */
    private function allowCartRecoveryUserLogin($user_id)
    {
        $allow_user_login = apply_filters('wc_retainful_allow_cart_recovery_user_login', !user_can($user_id, 'edit_others_posts'), $user_id);
        return (bool)$allow_user_login;
    }

    /**
     * Login the user if the user is registered user
     * @param $user_id
     * @return bool
     */
    function loginUser($user_id)
    {
        $logged_in = false;
        if (is_user_logged_in()) {
            // another user is logged in
            if ((int)$user_id !== get_current_user_id()) {
                wp_logout();
                // log the current user out, log in the new one
                if ($this->allowCartRecoveryUserLogin($user_id)) {
                    //"Another user is logged in, logging them out & logging in user {$user_id}"
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                    update_user_meta($user_id, $this->pending_recovery_key_for_db, true);
                    $logged_in = true;
                    // safety check fail: do not let an admin to be logged in automatically
                } else {
                    wc_add_notice(__('Note: Auto-login disabled when recreating cart for WordPress Admin account. Checking out as guest.', RNOC_TEXT_DOMAIN));
                    //"Not logging in user {$user_id} with admin rights"
                }
            } else {
                //'User is already logged in'
            }
        } else {
            // log the user in automatically
            if ($this->allowCartRecoveryUserLogin($user_id)) {
                //User is not logged in, logging in;
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                update_user_meta($user_id, $this->pending_recovery_key_for_db, true);
                $logged_in = true;
                // safety check fail: do not let an admin to be logged in automatically
            } else {
                wc_add_notice(__('Note: Auto-login disabled when recreating cart for WordPress Admin account. Checking out as guest.', RNOC_TEXT_DOMAIN));
                //"Not logging in user {$user_id} with admin rights"
            }
        }
        //'Cart recreated from persistent cart'
        return $logged_in;
    }

    /**
     * Get Order ID from cart token
     * @param $cart_token
     * @return string|null
     */
    function getOrderIdFromCartToken($cart_token)
    {
        if (empty($cart_token)) {
            return NULL;
        }
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
			SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = '{$this->cart_token_key_for_db}'
			AND meta_value = %s
		", $cart_token));
    }

    /**
     * Get User ID from cart token
     * @param $cart_token
     * @return string|null
     */
    function getUserIdFromCartToken($cart_token)
    {
        if (empty($cart_token)) {
            return NULL;
        }
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
			SELECT user_id
			FROM {$wpdb->usermeta}
			WHERE meta_key = '{$this->cart_token_key_for_db}'
			AND meta_value = %s
		", $cart_token));
    }

    /**
     * Check the hash matches or not
     * @param $hash
     * @param $data
     * @return bool
     */
    function isHashMatches($hash, $data)
    {
        $is_valid_hash = false;
        if (hash_equals($this->hashTheData($data), $hash)) {
            $is_valid_hash = true;
        }
        return $is_valid_hash;
    }

    /**
     * Hash the data
     * @param $data
     * @return false|string
     */
    function hashTheData($data)
    {
        global $retainful;
        $secret = $retainful::$plugin_admin->getSecretKey();
        return hash_hmac(self::HMAC_ALGORITHM, $data, $secret);
    }

    /**
     * Get the customer details
     * @return array
     */
    function getCustomerDetails()
    {
        global $retainful;
        $billing_email = $retainful::$woocommerce->getCustomerEmail();
        $customer_id = $retainful::$woocommerce->getCustomerId();
        if ($user_id = get_current_user_id()) {
            $user_data = wp_get_current_user();
            if (empty($billing_email)) {
                $billing_email = $user_data->user_email;
            }
            $created_at = $updated_at = strtotime($user_data->user_registered);
            $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
            $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
            $billing_state = get_user_meta($user_id, 'billing_state', true);
            $billing_phone = get_user_meta($user_id, 'billing_phone', true);
        } else {
            $user_id = 0;
            $created_at = $retainful::$storage->getValue('rnoc_session_created_at');
            $updated_at = current_time('timestamp', true);
            $billing_details = $this->getCustomerCheckoutDetails('billing');
            if (empty($billing_details)) {
                $billing_details = array();
            }
            $billing_phone = isset($billing_details['billing_phone']) ? $billing_details['billing_phone'] : NULL;
            $billing_first_name = isset($billing_details['billing_first_name']) ? $billing_details['billing_first_name'] : NULL;
            $billing_last_name = isset($billing_details['billing_last_name']) ? $billing_details['billing_last_name'] : NULL;
            $billing_state = isset($billing_details['billing_state']) ? $billing_details['billing_state'] : NULL;
        }
        return array(
            'id' => $user_id,
            'customer_id' => $customer_id,
            'email' => $billing_email,
            'phone' => $billing_phone,
            'state' => $billing_state,
            'last_name' => $billing_last_name,
            'first_name' => $billing_first_name,
            'currency' => NULL,
            'created_at' => $this->formatToIso8601($created_at),
            'updated_at' => $this->formatToIso8601($updated_at),
            'total_spent' => null,
            'orders_count' => null,
            'last_order_id' => null,
            'verified_email' => true,
            'last_order_name' => NULL,
            'accepts_marketing' => true,
        );
    }

    /**
     * Get the shipping address of the customer
     * @return array
     */
    function getCustomerShippingAddressDetails()
    {
        if ($user_id = get_current_user_id()) {
            $shipping_first_name = get_user_meta($user_id, 'shipping_first_name', true);
            $shipping_last_name = get_user_meta($user_id, 'shipping_last_name', true);
            $shipping_address_1 = get_user_meta($user_id, 'shipping_address_1', true);
            $shipping_city = get_user_meta($user_id, 'shipping_city', true);
            $shipping_state = get_user_meta($user_id, 'shipping_state', true);
            $shipping_postcode = get_user_meta($user_id, 'shipping_postcode', true);
            $shipping_country = get_user_meta($user_id, 'shipping_country', true);
            $shipping_address_2 = '';
        } else {
            $shipping_details = $this->getCustomerCheckoutDetails('shipping');
            if (empty($shipping_details)) {
                $shipping_details = array();
            }
            $shipping_postcode = isset($shipping_details['shipping_postcode']) ? $shipping_details['shipping_postcode'] : NULL;
            $shipping_city = isset($shipping_details['shipping_city']) ? $shipping_details['shipping_city'] : NULL;
            $shipping_first_name = isset($shipping_details['shipping_first_name']) ? $shipping_details['shipping_first_name'] : NULL;
            $shipping_last_name = isset($shipping_details['shipping_last_name']) ? $shipping_details['shipping_last_name'] : NULL;
            $shipping_country = isset($shipping_details['shipping_country']) ? $shipping_details['shipping_country'] : NULL;
            $shipping_state = isset($shipping_details['shipping_state']) ? $shipping_details['shipping_state'] : NULL;
            $shipping_address_1 = isset($shipping_details['shipping_address_1']) ? $shipping_details['shipping_address_1'] : NULL;
            $shipping_address_2 = isset($shipping_details['shipping_address_2']) ? $shipping_details['shipping_address_2'] : NULL;
        }
        return array(
            'zip' => $shipping_postcode,
            'city' => $shipping_city,
            'name' => $shipping_first_name . ' ' . $shipping_last_name,
            'phone' => NULL,
            'company' => NULL,
            'country' => $shipping_country,
            'address1' => $shipping_address_1,
            'address2' => $shipping_address_2,
            'latitude' => '',
            'province' => $shipping_state,
            'last_name' => $shipping_last_name,
            'longitude' => '',
            'first_name' => $shipping_first_name,
            'country_code' => $shipping_country,
            'province_code' => $shipping_state,
        );
    }

    /**
     * Get the billing address of the customer
     * @return array
     */
    function getCustomerBillingAddressDetails()
    {
        if ($user_id = get_current_user_id()) {
            $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
            $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
            $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
            $billing_city = get_user_meta($user_id, 'billing_city', true);
            $billing_state = get_user_meta($user_id, 'billing_state', true);
            $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
            $billing_country = get_user_meta($user_id, 'billing_country', true);
            $billing_phone = get_user_meta($user_id, 'billing_phone', true);
            $billing_address_2 = '';
            $billing_company = '';
        } else {
            $billing_details = $this->getCustomerCheckoutDetails('billing');
            if (empty($billing_details)) {
                $billing_details = array();
            }
            $billing_postcode = isset($billing_details['billing_postcode']) ? $billing_details['billing_postcode'] : NULL;
            $billing_city = isset($billing_details['billing_city']) ? $billing_details['billing_city'] : NULL;
            $billing_first_name = isset($billing_details['billing_first_name']) ? $billing_details['billing_first_name'] : NULL;
            $billing_last_name = isset($billing_details['billing_last_name']) ? $billing_details['billing_last_name'] : NULL;
            $billing_country = isset($billing_details['billing_country']) ? $billing_details['billing_country'] : NULL;
            $billing_state = isset($billing_details['billing_state']) ? $billing_details['billing_state'] : NULL;
            $billing_address_1 = isset($billing_details['billing_address_1']) ? $billing_details['billing_address_1'] : NULL;
            $billing_address_2 = isset($billing_details['billing_address_2']) ? $billing_details['billing_address_2'] : NULL;
            $billing_phone = isset($billing_details['billing_phone']) ? $billing_details['billing_phone'] : NULL;
            $billing_company = isset($billing_details['billing_company']) ? $billing_details['billing_company'] : NULL;
        }
        return array(
            'zip' => $billing_postcode,
            'city' => $billing_city,
            'name' => $billing_first_name . ' ' . $billing_last_name,
            'phone' => $billing_phone,
            'company' => $billing_company,
            'country' => $billing_country,
            'address1' => $billing_address_1,
            'address2' => $billing_address_2,
            'latitude' => '',
            'province' => $billing_state,
            'last_name' => $billing_last_name,
            'longitude' => '',
            'first_name' => $billing_first_name,
            'country_code' => $billing_country,
            'province_code' => $billing_state,
        );
    }
}