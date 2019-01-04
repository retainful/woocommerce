<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

class WcFunctions
{
    /**
     * Get order object
     * @param $order_id
     * @return bool|\WC_Order|null
     */
    function getOrder($order_id)
    {
        if (function_exists('wc_get_order')) {
            return wc_get_order($order_id);
        }
        return NULL;
    }

    /**
     * Get order Email form order object
     * @param $order
     * @return null
     */
    function getOrderEmail($order)
    {
        if (method_exists($order, 'get_billing_email')) {
            return $order->get_billing_email();
        }
        return NULL;
    }

    /**
     * Get used coupons of order
     * @param $order
     * @return null
     */
    function getUsedCoupons($order)
    {
        if (method_exists($order, 'get_used_coupons')) {
            return $order->get_used_coupons();
        }
        return NULL;
    }

    /**
     * Get order meta from order object
     * @param $order
     * @param $meta_key
     * @return null
     */
    function getOrderMeta($order, $meta_key)
    {
        if (method_exists($order, 'get_meta')) {
            return $order->get_meta($meta_key);
        }
        return NULL;
    }

    /**
     * Get Order Id
     * @param $order
     * @return String|null
     */
    function getOrderId($order)
    {
        if (method_exists($order, 'get_id')) {
            return $order->get_id();
        }
        return NULL;
    }

    /**
     * Get User Last name
     * @param $order
     * @return null
     */
    function getOrderFirstName($order)
    {
        if (method_exists($order, 'get_billing_first_name')) {
            return $order->get_billing_first_name();
        }
        return NULL;
    }

    /**
     * Get User Last name
     * @param $order
     * @return null
     */
    function getOrderLastName($order)
    {
        if (method_exists($order, 'get_billing_last_name')) {
            return $order->get_billing_last_name();
        }
        return NULL;
    }

    /**
     * Get Order Total
     * @param $order
     * @return null
     */
    function getOrderTotal($order)
    {
        if (method_exists($order, 'get_total')) {
            return $order->get_total();
        }
        return NULL;
    }

    /**
     * get Ordered Date
     * @param $order
     * @return null
     */
    function getOrderDate($order)
    {
        if (method_exists($order, 'get_date_created')) {
            return $order->get_date_created();
        }
        return NULL;
    }

    /**
     * get Order User Id
     * @param $order
     * @return null
     */
    function getOrderUserId($order)
    {
        if (method_exists($order, 'get_user_id')) {
            return $order->get_user_id();
        }
        return NULL;
    }

    /**
     * Format the price
     * @param $price
     * @return string
     */
    function formatPrice($price)
    {
        if (function_exists('wc_price'))
            return wc_price($price);
        else
            return $price;
    }
}