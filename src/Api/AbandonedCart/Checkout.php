<?php

namespace Rnoc\Retainful\Api\AbandonedCart;

use Exception;

class Checkout extends RestApi
{
    function recoverHeldOrders()
    {
        $recover_held_orders = apply_filters('rnoc_recover_held_orders', 'no');
        return ($recover_held_orders == "no");
    }

    /**
     * set retainful related data to order
     */
    function setRetainfulOrderData()
    {
        global $retainful;
        $draft_order = $retainful::$woocommerce->getSession('store_api_draft_order');
        if (!empty($draft_order) && intval($draft_order) > 0) {
            $this->purchaseComplete(intval($draft_order));
        }
    }

    /**
     * @param $checkout_fields
     * @return mixed
     */
    function moveEmailFieldToTop($checkout_fields)
    {
        global $retainful;
        if ($retainful::$plugin_admin->moveEmailFieldToTop()) {
            $checkout_fields['billing']['billing_email']['priority'] = 4;
        }
        return $checkout_fields;
    }

    /**
     * purchase complete
     * @param $order_id
     * @return null
     */
    function purchaseComplete($order_id)
    {
        global $retainful;
        if (empty($order_id)) {
            return NULL;
        }
        //TODO remove carthash from session after success place order
        $cart_token = $this->retrieveCartToken();
        if (!empty($cart_token)) {
            $cart_created_at = $this->userCartCreatedAt();
            $user_ip = $this->retrieveUserIp();
            $is_buyer_accepts_marketing = ($this->isBuyerAcceptsMarketing()) ? 1 : 0;
            $cart_hash = $retainful::$storage->getValue('rnoc_current_cart_hash');
            $recovered_at = $retainful::$storage->getValue('rnoc_recovered_at');
            $recovered_by = $retainful::$storage->getValue('rnoc_recovered_by_retainful');
            $recovered_cart_token = $retainful::$storage->getValue('rnoc_recovered_cart_token');
            $user_agent = $this->getUserAgent();
            $user_accept_language = $this->getUserAcceptLanguage();
            $retainful::$woocommerce->setOrderMeta($order_id, $this->cart_token_key_for_db, $cart_token);
            $retainful::$woocommerce->setOrderMeta($order_id, $this->cart_hash_key_for_db, $cart_hash);
            $retainful::$woocommerce->setOrderMeta($order_id, $this->cart_tracking_started_key_for_db, $cart_created_at);
            $retainful::$woocommerce->setOrderMeta($order_id, $this->user_ip_key_for_db, $user_ip);
            $retainful::$woocommerce->setOrderMeta($order_id, $this->accepts_marketing_key_for_db, $is_buyer_accepts_marketing);
            $retainful::$woocommerce->setOrderMeta($order_id, '_rnoc_recovered_at', $recovered_at);
            $retainful::$woocommerce->setOrderMeta($order_id, '_rnoc_recovered_by', $recovered_by);
            $retainful::$woocommerce->setOrderMeta($order_id, '_rnoc_recovered_cart_token', $recovered_cart_token);
            $retainful::$woocommerce->setOrderMeta($order_id, '_rnoc_get_http_user_agent', $user_agent);
            $retainful::$woocommerce->setOrderMeta($order_id, '_rnoc_get_http_accept_language', $user_accept_language);
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
     * @return mixed|void
     */
    function generateNocCouponForManualOrders()
    {
        global $retainful;
        $has_backorder_coupon = $retainful::$plugin_admin->autoGenerateCouponsForOldOrders();
        $need_noc_coupon = ($has_backorder_coupon && is_admin());
        return apply_filters('rnoc_generate_noc_coupon_for_manual_orders', $need_noc_coupon, $this);
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
        global $retainful;
        $order = $retainful::$woocommerce->getOrder($order_id);
        $order_obj = new Order();
        $cart_token = $retainful::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db);
        if (empty($cart_token)) {
            if ($this->generateNocCouponForManualOrders()) {
                $noc_details = $order_obj->getNextOrderCouponDetails($order);
                if (is_array($noc_details) && !empty($noc_details) && isset($noc_details[0]['code']) && !empty($noc_details[0]['code'])) {
                    $cart_token = $this->generateCartToken();
                    $retainful::$woocommerce->setOrderMeta($order_id, $this->cart_token_key_for_db, $cart_token);
                } else {
                    return;
                }
            } else {
                return;
            }
        }
        if (empty($cart_token)) {
            return;
        }
        $order_status = $retainful::$woocommerce->getStatus($order);
        $order_cancelled_at = $retainful::$woocommerce->getOrderMeta($order, $this->order_cancelled_date_key_for_db);
        // handle order cancellation
        if (!$order_cancelled_at && 'cancelled' === $order_status) {
            $order_cancelled_at = current_time('timestamp', true);
            $retainful::$woocommerce->setOrderMeta($order_id, $this->order_cancelled_date_key_for_db, $order_cancelled_at);
            $this->unsetOrderTempData();
        }
        $order_data = $order_obj->getOrderData($order);
        if (empty($order_data)) {
            return null;
        }
        $order_data['cancelled_at'] = (!empty($order_cancelled_at)) ? $this->formatToIso8601($order_cancelled_at) : NULL;
        $retainful::$plugin_admin->logMessage($order_data);
        $cart_hash = $this->encryptData($order_data);
        $client_ip = $retainful::$woocommerce->getOrderMeta($order, $this->user_ip_key_for_db);
        if (!empty($cart_hash)) {
            $token = $retainful::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db);
            $extra_headers = array(
                "X-Client-Referrer-IP" => (!empty($client_ip)) ? $client_ip : null,
                "X-Retainful-Version" => RNOC_VERSION,
                "X-Cart-Token" => $token,
                "Cart-Token" => $token
            );
            $this->syncCart($cart_hash, $extra_headers);
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
        global $retainful;
        $hook = 'retainful_sync_abandoned_cart_order';
        $meta_key = '_rnoc_order_id';
        if ($retainful::$plugin_admin->hasAnyActiveScheduleExists($hook, $order_id, $meta_key) == false) {
            $retainful::$plugin_admin->scheduleEvents($hook, current_time('timestamp') + 60, array($meta_key => $order_id));
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
        global $wp, $retainful;
        try {
            // PayPal IPN request
            if (!empty($wp->query_vars['wc-api']) && 'WC_Gateway_Paypal' === $wp->query_vars['wc-api']) {
                $order = $retainful::$woocommerce->getOrder($order_id);
                // PayPal order is completed or authorized: clear any user session
                // data so that we don't have to rely on the thank-you page rendering
                if (($retainful::$woocommerce->isOrderPaid($order) || $new_status == 'on-hold') && ($user_id = $retainful::$woocommerce->getOrderUserId($order))) {
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
        global $retainful;
        $placed = $retainful::$woocommerce->isOrderPaid($order) || ($new_status === 'on-hold' && !$this->recoverHeldOrders());
        return apply_filters('rnoc_abandoned_cart_is_order_get_placed', $placed, $order, $old_status, $new_status, $this);
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
        global $retainful;
        if ($this->isPendingRecovery()) {
            $this->markOrderAsPendingRecovery($order_id);
        }
        try {
            $cart_token = $this->retrieveCartToken();
            if (!empty($cart_token)) {
                $order = $retainful::$woocommerce->getOrder($order_id);
                $this->purchaseComplete($order_id);
                $this->syncOrderToAPI($order, $order_id);
                //$this->unsetOrderTempData();
            }
        } catch (Exception $e) {
        }
    }

    /**
     * sync order to api
     * @param $order
     * @param $order_id
     */
    function syncOrderToAPI($order, $order_id)
    {
        if ($this->needInstantOrderSync()) {
            global $retainful;
            $order_obj = new Order();
            $cart = $order_obj->getOrderData($order);
            if (!empty($cart)) {
                $retainful::$plugin_admin->logMessage($cart);
                $cart_hash = $this->encryptData($cart);
                //Reduce the loading speed
                $client_ip = $retainful::$woocommerce->getOrderMeta($order, $this->user_ip_key_for_db);
                if (!empty($cart_hash)) {
                    $token = $retainful::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db);
                    $extra_headers = array(
                        "X-Client-Referrer-IP" => (!empty($client_ip)) ? $client_ip : null,
                        "X-Retainful-Version" => RNOC_VERSION,
                        "X-Cart-Token" => $token,
                        "Cart-Token" => $token
                    );
                    $this->syncCart($cart_hash, $extra_headers);
                }
            }
        } else {
            $this->scheduleCartSync($order_id);
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
        global $retainful;
        if (empty($cart_token)) {
            $cart_token = $this->generateCartToken();
        }
        $retainful::$woocommerce->setOrderMeta($order_id, $this->cart_token_key_for_db, $cart_token);
    }

    /**
     * Update order on successful payment
     * @param $result
     * @param $order_id
     * @return mixed
     */
    function maybeUpdateOrderOnSuccessfulPayment($result, $order_id)
    {
        global $retainful;
        $order = $retainful::$woocommerce->getOrder($order_id);
        if (!$cart_token = $retainful::$woocommerce->getOrderMeta($order, $this->cart_token_key_for_db)) {
            return $result;
        }
        $this->syncOrderToAPI($order, $order_id);
        //$this->unsetOrderTempData();
        return $result;
    }

    /**
     * Payment completed
     * @param $order_id
     */
    function paymentCompleted($order_id)
    {
        global $retainful;
        if ($retainful::$woocommerce->getOrder($order_id)) {
            $retainful::$woocommerce->setCustomerPayingForOrder($order_id);
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
        global $retainful;
        $retainful::$storage->removeValue($this->cart_token_key);
        $retainful::$storage->removeValue($this->pending_recovery_key);
        $retainful::$storage->removeValue($this->cart_tracking_started_key);
        $retainful::$storage->removeValue($this->previous_cart_hash_key);
        //This was set in plugin since 2.0.4
        $retainful::$storage->removeValue('rnoc_force_refresh_cart');
        $retainful::$storage->removeValue('rnoc_recovered_at');
        $retainful::$storage->removeValue('rnoc_recovered_by_retainful');
        $retainful::$storage->removeValue('rnoc_recovered_cart_token');
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
        global $retainful;
        $retainful::$woocommerce->setOrderMeta($order_id, $this->pending_recovery_key_for_db, true);
    }
}