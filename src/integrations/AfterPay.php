<?php

namespace Rnoc\Retainful\Integrations;

use Rnoc\Retainful\Api\AbandonedCart\Checkout;
use Rnoc\Retainful\WcFunctions;

class AfterPay
{
    /** @var int the Afterpay quote id */
    private $quote_id;

    /** @var array key-value pairs of jilt meta */
    private $retainful_meta = array();

    function __construct()
    {
        add_action('save_post_afterpay_quote', array($this, 'saveAfterPayData'), 10, 3);
        add_action('before_delete_post', array($this, 'captureRetainfulDataFromQuote'));
        add_action('woocommerce_new_order', array($this, 'saveRetainfulDataToOrder'));
    }

    function saveAfterPayData($post_id, $post, $update)
    {
        if ($update || !$this->isPluginActive()) {
            return;
        }
        $checkout = new Checkout();
        if ($checkout->isPendingRecovery()) {
            $checkout->markOrderAsPendingRecovery();
        }
        $cart_token = $checkout->getCartToken();
        if ($cart_token) {
            $checkout->setOrderCartToken($cart_token, $post_id);
        }
    }

    function captureRetainfulDataFromQuote($post_id)
    {
        if(!$this->isPluginActive()){
            return;
        }
        $post = get_post($post_id);
        if (isset($post->post_type) && 'afterpay_quote' === $post->post_type) {
            $this->quote_id = (int)$post_id;
            $post_meta = get_post_meta($post_id, '', true);
            foreach ($post_meta as $key => $value) {
                if (0 === strpos($key, '_rnoc')) {
                    $this->retainful_meta[$key] = isset($value[0]) && !empty($value[0]) ? $value[0]: '';
                }
            }
        }
    }

    function saveRetainfulDataToOrder($order_id){
        if(!$this->isPluginActive()){
            return;
        }
        if ( $order_id > 0 &&  (int)$order_id === $this->quote_id ) {
            $wc_function = new WcFunctions();
            foreach ( $this->retainful_meta as $key => $value ) {
                $wc_function->setOrderMeta($order_id,$key,$value);
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