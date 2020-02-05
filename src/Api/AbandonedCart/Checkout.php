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
            $cart_hash = $this->getSessionForCustomer('cart_hash', 'rnoc_current_cart_hash');
            $recovered_at = $this->getSessionForCustomer('recovered_at', 'rnoc_recovered_at');
            $recovered_by = $this->getSessionForCustomer('recovered_by_retainful', 'rnoc_recovered_by_retainful');
            $recovered_cart_token = $this->getSessionForCustomer('recovered_cart_token', 'rnoc_recovered_cart_token');
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
        if ($this->needInstantOrderSync()) {
            $this->syncOrder($order_id);
        } else {
            $this->scheduleCartSync($order_id);
        }
    }

    /**
     * Sync the order with API
     * @param $order_id
     * @return void|null
     */
    function syncOrder($order_id)
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
     * Sync the order
     * @param $order_id
     */
    function syncOrderByScheduler($order_id)
    {
        $this->syncOrder($order_id);
    }

    /**
     * schedule the sync of the cart
     * @param $order_id
     */
    function scheduleCartSync($order_id)
    {
        $hook = 'retainful_sync_abandoned_cart_order';
        $meta_key = '_rnoc_order_id';
        if (self::$settings->hasAnyActiveScheduleExists($hook, $order_id, $meta_key) == false) {
            self::$settings->scheduleEvents($hook, current_time('timestamp') + 60, array($meta_key => $order_id));
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
                if ($this->needInstantOrderSync()) {
                    $order_obj = new Order();
                    $cart = $order_obj->getOrderData($order);
                    self::$settings->logMessage($cart);
                    $cart_hash = $this->encryptData($cart);
                    //Reduce the loading speed
                    if (!empty($cart_hash)) {
                        $this->syncCart($cart_hash);
                    }
                } else {
                    $this->scheduleCartSync($order_id);
                }
                //$this->unsetOrderTempData();
            }
        } catch (Exception $e) {
        }
    }

    /**
     * need the instant sync or not
     * @return mixed|void
     */
    function needInstantOrderSync()
    {
        return apply_filters('rnoc_sync_order_data_instantly_to_api', true);
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
        if ($this->needInstantOrderSync()) {
            $order_obj = new Order();
            $cart = $order_obj->getOrderData($order);
            self::$settings->logMessage($cart);
            $cart_hash = $this->encryptData($cart);
            if (!empty($cart_hash)) {
                $this->syncCart($cart_hash);
            }
        } else {
            $this->scheduleCartSync($order_id);
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
        $this->setSessionForCustomer('cart_token', '', $this->cart_token_key);
        $this->setSessionForCustomer('pending_recovery', '', $this->pending_recovery_key);
        $this->setSessionForCustomer('cart_created_date', '', $this->cart_tracking_started_key);
        $this->setSessionForCustomer('previous_cart_hash', '', $this->previous_cart_hash_key);
        $this->setSessionForCustomer('user_ip', '', $this->user_ip_key);
        //This was set in plugin since 2.0.4
        $this->setSessionForCustomer('recovered_at', '', 'rnoc_recovered_at');
        $this->setSessionForCustomer('recovered_by_retainful', '', 'rnoc_recovered_by_retainful');
        $this->setSessionForCustomer('recovered_cart_token', '', 'rnoc_recovered_cart_token');
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
}