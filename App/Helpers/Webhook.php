<?php

namespace RNOC\App\Helpers;

use RNOC\App\Modules\AbandonedCart\AbandonedCart;

defined( 'ABSPATH' ) || exit;

class Webhook {

	public static $domain = "https://api.retainful.com/v1/";

	public static function getDomain() {
		return apply_filters( 'retainful_domain_url', self::$domain );
	}

	/**
	 * Get webhook status.
	 *
	 * @return array
	 */
	public static function getWebHookStatus() {
		$topics = [
			'order.updated' => false,
			'order.created' => false
		];
		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return $topics;
		}
		try {
			$data_store = \WC_Data_Store::load( 'webhook' );
			$args       = array(
				'limit'  => - 1,
				'offset' => 0,
			);
			$webhooks   = $data_store->search_webhooks( $args );
			if ( empty( $webhooks ) ) {
				return $topics;
			}
			foreach ( $webhooks as $webhook_id ) {
				$webhook = wc_get_webhook( $webhook_id );
				if ( empty( $webhook ) ) {
					continue;
				}
				$delivery_url      = $webhook->get_delivery_url();
				$site_delivery_url = self::getDeliveryUrl();
				if ( $delivery_url != $site_delivery_url ) {
					continue;
				}
				if ( isset( $topics[ $webhook->get_topic() ] ) ) {
					$topics[ $webhook->get_topic() ] = true;
				}
			}

		} catch ( \Exception $e ) {

		}

		return $topics;
	}


	/**
	 * get retainful webhook delivery url.
	 *
	 * @return mixed|null
	 */
	public static function getDeliveryUrl() {
		$url = self::getDomain() . 'woocommerce/webhooks/checkout';

		return apply_filters( 'change_delivery_url', $url );
	}

	/**
	 * create the webhook
	 *
	 * @return void
	 */
	public static function createWebhook() {
		if ( is_admin() ) {
			$is_app_connected = Settings::get( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0 );
			if ( $is_app_connected ) {
				$hook_status = self::getWebHookStatus();
				if ( isset( $hook_status['order.updated'] ) && ! $hook_status['order.updated'] ) {
					self::addNewWebhook();
				}
				if ( isset( $hook_status['order.created'] ) && ! $hook_status['order.created'] ) {
					self::addNewWebHook( 'order.created' );
				}
			} else {
				self::removeWebhook();
			}
		}
	}

	/**
	 * Add new webhook.
	 *
	 * @param string $topic webhook topic
	 *
	 * @return bool
	 */
	protected static function addNewWebHook( $topic = 'order.updated' ) {
		if ( ! in_array( $topic, [ 'order.updated', 'order.created' ] ) || ! class_exists( 'WC_Webhook' ) ) {
			return false;
		}
		try {
			$webhook = new \WC_Webhook();
			$name    = $topic == 'order.updated' ? sanitize_text_field( wp_unslash( 'Retainful Order Update' ) ) : sanitize_text_field( wp_unslash( 'Retainful Order Create' ) );
			$webhook->set_name( $name );
			if ( ! $webhook->get_user_id() ) {
				$webhook->set_user_id( get_current_user_id() );
			}
			//
			$webhook->set_status( 'active' );
			$delivery_url = self::getDeliveryUrl();
			$webhook->set_delivery_url( $delivery_url );
			$secret = wp_generate_password( 50, true, true );
			$webhook->set_secret( $secret );
			if ( wc_is_webhook_valid_topic( $topic ) ) {
				$webhook->set_topic( $topic );
			}
			// API version.
			$rest_api_versions = wc_get_webhook_rest_api_versions();
			$webhook->set_api_version( end( $rest_api_versions ) ); // WPCS: input var okay, CSRF ok.
			$webhook_id = $webhook->save();
			if ( $webhook_id > 0 ) {
				return true;
			}
		} catch ( \Exception $e ) {
			return false;
		}

		return false;
	}

	/**
	 * Remove retainful webhook.
	 *
	 */
	public static function removeWebhook() {
		if ( ! class_exists( 'WC_Data_Store' ) || ! class_exists( '\Rnoc\Retainful\library\RetainfulApi' ) || ! function_exists( 'wc_get_webhook' ) ) {
			return;
		}
		try {
			$data_store = \WC_Data_Store::load( 'webhook' );
			$args       = array(
				'limit'  => - 1,
				'offset' => 0,
			);
			$webhooks   = $data_store->search_webhooks( $args );
			foreach ( $webhooks as $webhook_id ) {
				$webhook = wc_get_webhook( $webhook_id );
				if ( empty( $webhook ) ) {
					continue;
				}
				$delivery_url      = $webhook->get_delivery_url();
				$site_delivery_url = self::getDeliveryUrl();
				if ( $delivery_url != $site_delivery_url ) {
					continue;
				}
				$webhook->delete();
			}
		} catch ( \Exception $e ) {

		}
	}

}