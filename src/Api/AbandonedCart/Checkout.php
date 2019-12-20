<?php

namespace Rnoc\Retainful\Api\AbandonedCart;

use Exception;

class Checkout extends RestApi
{
    function __construct()
    {
        parent::__construct();
    }

    function recoverHeldOrders()
    {
        $recover_held_orders = apply_filters('rnoc_recover_held_orders', 'no');
        return ($recover_held_orders == "no");
    }

    /**
     * purchase complete
     * @param $order_id
     * @return null
     */
    function purchaseComplete($order_id)
    {
        if (empty($order_id)) {
            return NULL;
        }
        //TODO remove carthash from session after success place order
        $cart_token = $this->retrieveCartToken();
        if (!empty($cart_token)) {
            $cart_created_at = $this->userCartCreatedAt();
            $user_ip = $this->retrieveUserIp();
            $is_buyer_accepts_marketing = ($this->isBuyerAcceptsMarketing()) ? 1 : 0;
            $cart_hash = self::$woocommerce->getSession('rnoc_current_cart_hash');
            $recovered_at = self::$woocommerce->getSession('rnoc_recovered_at');
            $recovered_by = self::$woocommerce->getSession('rnoc_recovered_by_retainful');
            $recovered_cart_token = self::$woocommerce->getSession('rnoc_recovered_cart_token');
            self::$woocommerce->setOrderMeta($order_id, $this->cart_token_key_for_db, $cart_token);
            self::$woocommerce->setOrderMeta($order_id, $this->cart_hash_key_for_db, $cart_hash);
            self::$woocommerce->setOrderMeta($order_id, $this->cart_tracking_started_key_for_db, $cart_created_at);
            self::$woocommerce->setOrderMeta($order_id, $this->user_ip_key_for_db, $user_ip);
            self::$woocommerce->setOrderMeta($order_id, $this->accepts_marketing_key_for_db, $is_buyer_accepts_marketing);
            self::$woocommerce->setOrderMeta($order_id, '_rnoc_recovered_at', $recovered_at);
            self::$woocommerce->setOrderMeta($order_id, '_rnoc_recovered_by', $recovered_by);
            self::$woocommerce->setOrderMeta($order_id, '_rnoc_recovered_cart_token', $recovered_cart_token);
            $this->markOrderAsPendingRecovery($order_id);
            //$this->unsetOrderTempData();
        }
        return NULL;
    }

    /**
     * Order had some changes
     * @param $order_id
     * @return void|null
     */
    function orderUpdated($order_id)
    {
        if (empty($order_id)) {
            return null;
        }
        $order = self::$woocommerce->getOrder($order_id);
        $cart_token = self::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db);
        if (empty($cart_token)) {
            //todo: generate and set token
            return;
        }
        $order_status = self::$woocommerce->getStatus($order);
        $order_cancelled_at = self::$woocommerce->getOrderMeta($order, $this->order_cancelled_date_key_for_db);
        // handle order cancellation
        if (!$order_cancelled_at && 'cancelled' === $order_status) {
            $order_cancelled_at = current_time('timestamp', true);
            self::$woocommerce->setOrderMeta($order_id, $this->order_cancelled_date_key_for_db, $order_cancelled_at);
            $this->unsetOrderTempData();
        }
        $order_obj = new Order();
        $order_data = $order_obj->getOrderData($order);
        $order_data['cancelled_at'] = (!empty($order_cancelled_at)) ? $this->formatToIso8601($order_cancelled_at) : NULL;
        self::$settings->logMessage($order_data);
        $cart_hash = $this->encryptData($order_data);
        if (!empty($cart_hash)) {
            $this->syncCart($cart_hash);
        }
    }

    /**
     * Clear any persistent cart session data for logged in customers
     * @param int $order_id order ID
     * @param string $old_status
     * @param string $new_status
     */
    public function orderStatusChanged($order_id, $old_status, $new_status)
    {
        global $wp;
        try {
            // PayPal IPN request
            if (!empty($wp->query_vars['wc-api']) && 'WC_Gateway_Paypal' === $wp->query_vars['wc-api']) {
                $order = self::$woocommerce->getOrder($order_id);
                // PayPal order is completed or authorized: clear any user session
                // data so that we don't have to rely on the thank-you page rendering
                if ((self::$woocommerce->isOrderPaid($order) || $new_status == 'on-hold') && ($user_id = self::$woocommerce->getOrderUserId($order))) {
                    delete_user_meta($user_id, '_woocommerce_persistent_cart_' . get_current_blog_id());
                    if ($this->isPendingRecovery($user_id)) {
                        $this->markOrderAsPendingRecovery($order_id);
                    }
                    if ($this->retrieveCartToken($user_id)) {
                        $this->removeTempDataForUser($user_id);
                    }
                }
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Check the order is placed or not
     * @param $order
     * @param $old_status
     * @param $new_status
     * @return bool|mixed|void
     */
    function isPlaced($order, $old_status, $new_status)
    {
        $placed = self::$woocommerce->isOrderPaid($order) || ($new_status === 'on-hold' && !$this->recoverHeldOrders());
        $placed = apply_filters('rnoc_abandoned_cart_is_order_get_placed', $placed, $order, $old_status, $new_status, $this);
        return $placed;
    }

    /**
     * HAndle order completion in order page
     * @param $order_id
     */
    function payPageOrderCompletion($order_id)
    {
        $this->unsetOrderTempData();
    }

    /**
     * @param $order_id
     */
    function checkoutOrderProcessed($order_id)
    {
        if ($this->isPendingRecovery()) {
            $this->markOrderAsPendingRecovery($order_id);
        }
        try {
            $cart_token = $this->retrieveCartToken();
            if (!empty($cart_token)) {
                $order = self::$woocommerce->getOrder($order_id);
               $this->purchaseComplete($order_id);
                $order_obj = new Order();
                $cart = $order_obj->getOrderData($order);
                self::$settings->logMessage($cart);
                $cart_hash = $this->encryptData($cart);
                if (!empty($cart_hash)) {
                    $this->syncCart($cart_hash);
                }
                //$this->unsetOrderTempData();
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Assign cart token for Order
     * @param $cart_token
     * @param $order_id
     */
    function setOrderCartToken($cart_token, $order_id)
    {
        if (empty($cart_token)) {
            $cart_token = $this->generateCartToken();
        }
        self::$woocommerce->setOrderMeta($order_id, $this->cart_token_key_for_db, $cart_token);
    }

    /**
     * Update order on successful payment
     * @param $result
     * @param $order_id
     * @return mixed
     */
    function maybeUpdateOrderOnSuccessfulPayment($result, $order_id)
    {
        $order = self::$woocommerce->getOrder($order_id);
        if (!$cart_token = self::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db)) {
            return $result;
        }
        $order_obj = new Order();
        $cart = $order_obj->getOrderData($order);
        self::$settings->logMessage($cart);
        $cart_hash = $this->encryptData($cart);
        if (!empty($cart_hash)) {
            $this->syncCart($cart_hash);
        }
        //$this->unsetOrderTempData();
        return $result;
    }

    /**
     * Payment completed
     * @param $order_id
     */
    function paymentCompleted($order_id)
    {
        if (self::$woocommerce->getOrder($order_id)) {
            self::$woocommerce->setCustomerPayingForOrder($order_id);
        }
        $cart_token = $this->retrieveCartToken();
        if (!empty($cart_token)) {
            $this->unsetOrderTempData();
        }
    }

    /**
     * Unset the temporary cart token and order data
     * @param null $user_id
     */
    function unsetOrderTempData($user_id = NULL)
    {
        self::$woocommerce->removeSession($this->cart_token_key);
        self::$woocommerce->removeSession($this->pending_recovery_key);
        self::$woocommerce->removeSession($this->cart_tracking_started_key);
        self::$woocommerce->removeSession($this->previous_cart_hash_key);
        self::$woocommerce->removeSession($this->user_ip_key);
        //This was set in plugin since 2.0.4
        self::$woocommerce->removeSession('rnoc_recovered_at');
        self::$woocommerce->removeSession('rnoc_recovered_by_retainful');
        self::$woocommerce->removeSession('rnoc_recovered_cart_token');
        if ($user_id || ($user_id = get_current_user_id())) {
            $this->removeTempDataForUser($user_id);
        }
    }

    /**
     * Delete temp data of the user
     * @param $user_id
     */
    function removeTempDataForUser($user_id)
    {
        delete_user_meta($user_id, $this->cart_token_key_for_db);
        delete_user_meta($user_id, $this->pending_recovery_key_for_db);
        delete_user_meta($user_id, $this->cart_tracking_started_key_for_db);
        delete_user_meta($user_id, $this->user_ip_key_for_db);
    }

    /**
     * Mark order as pending recovery
     * @param $order_id
     */
    function markOrderAsPendingRecovery($order_id)
    {
        /*$order = self::$woocommerce->getOrder($order_id);
        if (!$order instanceof \WC_Order) {
            return;
        }*/
        self::$woocommerce->setOrderMeta($order_id, $this->pending_recovery_key_for_db, true);
    }

    /**
     * retrieve data from session and populate fields
     * @param $fields
     * @return mixed
     */
    function setCheckoutFieldsDefaultValues($fields)
    {
        $fields['billing']['billing_email']['default'] = self::$woocommerce->getSession('rnoc_user_billing_email');
        //Set the billing details for checkout fields
        $billing_address = self::$woocommerce->getSession('rnoc_billing_address');
        if (!empty($billing_address) && is_array($billing_address)) {
            foreach ($billing_address as $billing_key => $billing_value) {
                if (isset($fields['billing'][$billing_key])) {
                    $fields['billing'][$billing_key]['default'] = $billing_value;
                }
            }
        }
        //Set the shipping details for checkout fields
        $shipping_address = self::$woocommerce->getSession('rnoc_shipping_address');
        if (!empty($shipping_address) && is_array($shipping_address)) {
            foreach ($shipping_address as $shipping_key => $shipping_value) {
                if (isset($fields['shipping'][$shipping_key])) {
                    $fields['shipping'][$shipping_key]['default'] = $shipping_value;
                }
            }
        }
        return $fields;
    }
}