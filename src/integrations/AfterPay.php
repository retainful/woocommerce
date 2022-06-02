<?php

namespace Rnoc\Retainful\Integrations;

use Rnoc\Retainful\Api\AbandonedCart\Checkout;

class AfterPay
{
    /** @var int the Afterpay quote id */
    private $quote_id;

    /** @var array key-value pairs of jilt meta */
    private $retainful_meta = array();

    function __construct()
    {
        if ($this->isPluginActive()) {
            add_action('save_post_afterpay_quote', array($this, 'saveAfterPayData'), 10, 3);
            add_action('before_delete_post', array($this, 'captureRetainfulDataFromQuote'));
            add_action('woocommerce_new_order', array($this, 'saveRetainfulDataToOrder'));
        }
    }

    function saveAfterPayData($post_id, $post, $update)
    {
        if ($update) {
            return;
        }
        $api = new Checkout();
        if ($api->isPendingRecovery()) {
            $api->markOrderAsPendingRecovery();
        }
        $cart_token = $api->getCartToken();
        if ($cart_token) {
            $api->setOrderCartToken($cart_token, $post_id);
        }
    }

    function captureRetainfulDataFromQuote($post_id)
    {
        $post = get_post($post_id);
        if ('afterpay_quote' === $post->post_type) {
            $this->quote_id = (int)$post_id;
            $post_meta = get_post_meta($post_id, '', true);
            foreach ($post_meta as $key => $value) {
                if (0 === strpos($key, '_rnoc_user_cart_token')) {
                    $this->retainful_meta[$key] = isset($value[0]) && !empty($value[0]) ? $value[0]: '';
                }
            }
        }
    }

    function saveRetainfulDataToOrder($order_id){
        if ( $order_id > 0 &&  (int)$order_id === $this->quote_id ) {
            $api = new Checkout();
            foreach ( $this->retainful_meta as $key => $value ) {
                $api->setOrderCartToken($value, $order_id);
            }
        }
    }


    function isPluginActive()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('afterpay-gateway-for-woocommerce/afterpay-gateway-for-woocommerce.php', $active_plugins, false) || array_key_exists('afterpay-gateway-for-woocommerce/afterpay-gateway-for-woocommerce.php', $active_plugins);
    }

}