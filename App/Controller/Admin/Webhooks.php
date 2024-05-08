<?php

namespace Rnoc\App\Controller\Admin;

if (!defined('ABSPATH')) exit;

class Webhooks extends BaseController
{
    /**
     * Create order sync webhook.
     *
     * @return void
     */
    function createWebhook()
    {
        if (is_admin()) {
            if ($this->isConnectionActive()) {
                $hook_status = $this->getWebHookStatus();
                if (isset($hook_status['order.updated']) && !$hook_status['order.updated']) {
                    $this->addNewWebhook();
                }
                if (isset($hook_status['order.created']) && !$hook_status['order.created']) {
                    $this->addNewWebHook('order.created');
                }
            } else {
                $this->removeWebhook();
            }
        }
    }


    /**
     * Get Webhooks status.
     * @return array
     */
    function getWebHookStatus()
    {
        $topics = [
            'order.updated' => false,
            'order.created' => false
        ];
        try {
            $data_store = \WC_Data_Store::load('webhook');
            $args = array(
                'limit' => -1,
                'offset' => 0,
            );
            $webhooks = $data_store->search_webhooks($args);

            foreach ($webhooks as $webhook_id) {
                $webhook = wc_get_webhook($webhook_id);
                if (empty($webhook)) {
                    continue;
                }
                $delivery_url = $webhook->get_delivery_url();
                $site_delivery_url = $this->getDeliveryUrl();
                if ($delivery_url != $site_delivery_url) {
                    continue;
                }
                if (isset($topics[$webhook->get_topic()])) {
                    $topics[$webhook->get_topic()] = true;
                }
            }
        } catch (\Exception $e) {

        }
        return $topics;
    }

    /**
     * Add new webhook.
     *
     * @param $topic
     * @return bool
     */
    protected function addNewWebHook($topic = 'order.updated')
    {
        if (!in_array($topic, array('order.updated', 'order.created'))) {
            return false;
        }
        try {
            $webhook = new \WC_Webhook();
            $name = $topic == 'order.updated' ? sanitize_text_field(wp_unslash('Retainful Order Update')) : sanitize_text_field(wp_unslash('Retainful Order Create'));
            $webhook->set_name($name);
            if (!$webhook->get_user_id()) {
                $webhook->set_user_id(get_current_user_id());
            }
            //
            $webhook->set_status('active');
            $delivery_url = $this->getDeliveryUrl();
            $webhook->set_delivery_url($delivery_url);
            $secret = wp_generate_password(50, true, true);
            $webhook->set_secret($secret);
            if (wc_is_webhook_valid_topic($topic)) {
                $webhook->set_topic($topic);
            }
            // API version.
            $rest_api_versions = wc_get_webhook_rest_api_versions();
            $webhook->set_api_version(end($rest_api_versions)); // WPCS: input var okay, CSRF ok.
            $webhook_id = $webhook->save();
            if ($webhook_id > 0) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Remove retainful webhook.
     *
     * @return void
     */
    function removeWebhook()
    {
        if (!class_exists('WC_Data_Store') || !class_exists('\Rnoc\Retainful\library\RetainfulApi') || !function_exists('wc_get_webhook')) {
            return;
        }
        try {
            $data_store = \WC_Data_Store::load('webhook');
            $args = array(
                'limit' => -1,
                'offset' => 0,
            );
            $webhooks = $data_store->search_webhooks($args);
            foreach ($webhooks as $webhook_id) {
                $webhook = wc_get_webhook($webhook_id);
                if (empty($webhook)) {
                    continue;
                }
                $delivery_url = $webhook->get_delivery_url();
                $site_delivery_url = $this->getDeliveryUrl();
                if ($delivery_url != $site_delivery_url) {
                    continue;
                }
                $webhook->delete();
            }
        } catch (\Exception $e) {

        }
    }

    /**
     * Is need to show webhook notice.
     *
     * @return bool
     */
    function isWebhookNoticeShow()
    {

        if (!$this->isConnectionActive()) {
            return false;
        }
        if (!class_exists('WC_Data_Store') || !function_exists('wc_get_webhook')) {
            return false;
        }
        $webhook_status = array(
            'order_created' => false,
            'order_updated' => false
        );
        try {
            $data_store = \WC_Data_Store::load('webhook');
            $args = array(
                'limit' => -1,
                'offset' => 0,
            );

            $webhooks = $data_store->search_webhooks($args);
            foreach ($webhooks as $webhook_id) {
                $webhook = wc_get_webhook($webhook_id);
                if (empty($webhook)) {
                    continue;
                }
                $delivery_url = $webhook->get_delivery_url();
                $site_delivery_url = $this->api->getDomain() . 'woocommerce/webhooks/checkout';
                if ($delivery_url != $site_delivery_url) {
                    continue;
                }
                $topic = $webhook->get_topic();
                $status = $webhook->get_status();
                if ($status == 'active' && $topic == 'order.created') {
                    $webhook_status['order_created'] = true;
                }
                if ($status == 'active' && $topic == 'order.updated') {
                    $webhook_status['order_updated'] = true;
                }
            }
        } catch (\Exception $e) {

        }

        return in_array(false, $webhook_status);
    }
}