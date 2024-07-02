<?php

namespace RNOC\App\Helpers;

use Google\Rpc\Context\AttributeContext\Request;
use RNOC\App\Modules\AbandonedCart\AbandonedCart;

defined( 'ABSPATH' ) || exit;

class Webhook {

	/**
	 * Get webhook status.
	 * @return array
	 */
	public static function getWebHookStatus() {
		$topics = [
			'order.updated'   => false,
			'order.created'   => false,
			'product.created' => false,
			'product.updated' => false,
			'product.deleted' => false
		];
		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return $topics;
		}
		try {
			$data_store = \WC_Data_Store::load( 'webhook' );
			$args       = [
				'limit'  => - 1,
				'offset' => 0,
			];
			$webhooks   = $data_store->search_webhooks( $args );
			if ( empty( $webhooks ) ) {
				return $topics;
			}
			foreach ( $webhooks as $webhook_id ) {
				$webhook = wc_get_webhook( $webhook_id );
				if ( empty( $webhook ) ) {
					continue;
				}
				$topic             = $webhook->get_topic();
				$delivery_url      = $webhook->get_delivery_url();
				$site_delivery_url = self::getDeliveryUrl( $topic );
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
	 * Get retainful webhook delivery url.
	 *
	 * @return mixed|null
	 */
	public static function getDeliveryUrl( $topic ) {
		if ( empty( $topic ) ) {
			return;
		}
		$url = '';
		if ( in_array( $topic, array( 'order.created', 'order.updated' ) ) ) {
			$url = \RNOC\App\Modules\AbandonedCart\Request::getAbandonedCartApiUrl() . 'webhooks/checkout';
		} elseif ( in_array( $topic, array( 'product.created', 'product.updated', 'product.deleted' ) ) ) {
			$url = 'https://5tzcs7zuy3.execute-api.us-east-2.amazonaws.com/development/v3/event/woocommerce/products';
		}

		return apply_filters( 'retainful_change_delivery_url', $url );
	}

	/**
	 * Create the webhook.
	 * @return void
	 */
	public static function createWebhook() {
		if ( is_admin() ) {
			$is_app_connected = Settings::get( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0, 'license' );
			if ( $is_app_connected ) {
				$hook_status = self::getWebHookStatus();

				if ( isset( $hook_status['order.updated'] ) && ! $hook_status['order.updated'] ) {
					self::addNewWebhook();
				}
				if ( isset( $hook_status['order.created'] ) && ! $hook_status['order.created'] ) {
					self::addNewWebHook( 'order.created' );
				}
				if ( isset( $hook_status['product.updated'] ) && ! $hook_status['product.updated'] ) {
					self::addNewWebHook( 'product.updated' );
				}
				if ( isset( $hook_status['product.created'] ) && ! $hook_status['product.created'] ) {
					self::addNewWebHook( 'product.created' );
				}
				if ( isset( $hook_status['product.deleted'] ) && ! $hook_status['product.deleted'] ) {
					self::addNewWebHook( 'product.deleted' );
				}
			} else {
				self::removeWebhook();
			}
		}
	}

	/**
	 * Add new webhook.
	 *
	 * @param string $topic Webhook topic.
	 *
	 * @return bool
	 */
	protected static function addNewWebHook( $topic = 'order.updated' ) {
		$order_topic         = [
			'order.updated',
			'order.created',
		];
		$product_topic       = [
			'product.updated',
			'product.created',
			'product.deleted'
		];
		$allow_product_topic = apply_filters( 'retainful_allow_product_webhooks', false, $product_topic );
		$allowed_topic       = ! empty( $allow_product_topic ) ? array_merge( $order_topic, $product_topic ) : $order_topic;
		if ( ! in_array( $topic, $allowed_topic ) ) {
			return false;
		}
		try {
			$name = '';
			switch ( $topic ) {
				case 'order.updated':
					$name = 'Retainful Order Update';
					break;
				case 'order.created':
					$name = 'Retainful Order created';
					break;
				case 'product.updated':
					$name = 'Retainful product Update';
					break;
				case 'product.created':
					$name = 'Retainful product created';
					break;
				case 'product.deleted':
					$name = 'Retainful product deleted';
					break;
			}
			$webhook = new \WC_Webhook();
			$webhook->set_name( $name );
			if ( ! $webhook->get_user_id() ) {
				$webhook->set_user_id( get_current_user_id() );
			}
			//
			$webhook->set_status( 'active' );
			$delivery_url = self::getDeliveryUrl( $topic );
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
	 */
	public static function removeWebhook() {
		if ( ! class_exists( 'WC_Data_Store' ) || ! function_exists( 'wc_get_webhook' ) ) {
			return;
		}
		try {
			$data_store = \WC_Data_Store::load( 'webhook' );
			$args       = [
				'limit'  => - 1,
				'offset' => 0,
			];
			$webhooks   = $data_store->search_webhooks( $args );
			foreach ( $webhooks as $webhook_id ) {
				$webhook = wc_get_webhook( $webhook_id );
				if ( empty( $webhook ) ) {
					continue;
				}
				$topic = $webhook->get_topic();

				$delivery_url      = $webhook->get_delivery_url();
				$site_delivery_url = self::getDeliveryUrl( $topic );
				if ( $delivery_url != $site_delivery_url ) {
					continue;
				}
				$webhook->delete();
			}
		} catch ( \Exception $e ) {

		}
	}

	/**
	 * Is need to show webhook notice.
	 *
	 * @return bool
	 */
	public static function isWebhookNoticeShow() {

		if ( ! settings::get( RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 0, 'license' ) ) {
			return false;
		}

		if ( ! class_exists( 'WC_Data_Store' ) || ! function_exists( 'wc_get_webhook' ) ) {
			return false;
		}
		$order_status        = [
			'order_created' => false,
			'order_updated' => false,
		];
		$product_status      = [
			'product_created' => false,
			'product_updated' => false,
			'product_deleted' => false
		];
		$allow_product_topic = apply_filters( 'retainful_allow_product_webhooks', false, $product_status );
		$webhook_status      = ! empty( $allow_product_topic ) ? array_merge( $order_status, $product_status ) : $order_status;

		try {
			$data_store = \WC_Data_Store::load( 'webhook' );
			$args       = [
				'limit'  => - 1,
				'offset' => 0,
			];

			$webhooks = $data_store->search_webhooks( $args );

			foreach ( $webhooks as $webhook_id ) {
				$webhook = wc_get_webhook( $webhook_id );
				if ( empty( $webhook ) ) {
					continue;
				}
				$topic             = $webhook->get_topic();
				$delivery_url      = $webhook->get_delivery_url();
				$site_delivery_url = self::getDeliveryUrl( $topic );
				if ( $delivery_url != $site_delivery_url ) {
					continue;
				}
				$topic  = $webhook->get_topic();
				$status = $webhook->get_status();
				if ( $status == 'active' && $topic == 'order.created' ) {
					$webhook_status['order_created'] = true;
				}
				if ( $status == 'active' && $topic == 'order.updated' ) {
					$webhook_status['order_updated'] = true;
				}
				if ( $status == 'active' && $topic == 'product.created' ) {
					$webhook_status['product_created'] = true;
				}
				if ( $status == 'active' && $topic == 'product.updated' ) {
					$webhook_status['product_updated'] = true;
				}
				if ( $status == 'active' && $topic == 'product.deleted' ) {
					$webhook_status['product_deleted'] = true;
				}
			}
		} catch ( \Exception $e ) {

		}

		return in_array( false, $webhook_status );
	}
}