<?php


namespace RNOC\App\Modules\Integrations;

use RNOC\App\Modules\AbandonedCart\Cart;
use RNOC\App\Modules\AbandonedCart\Order;
use RNOC\App\Modules\AbandonedCart\Traits\SyncData;

class AfterPay {
	use SyncData;

	/** @var int the Afterpay quote id */
	private static $quote_id;

	/** @var array key-value pairs of jilt meta */
	private static $retainful_meta = [];

	/**
	 * Initialise hooks
	 */
	function __construct() {
		add_action( 'save_post_afterpay_quote', array( $this, 'saveAfterPayData' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'captureRetainfulDataFromQuote' ) );
		add_action( 'woocommerce_new_order', array( $this, 'saveRetainfulDataToOrder' ) );
	}

	/**
	 * Save Afterpay data.
	 *
	 * @param int $post_id Post id.
	 * @param \WP_Post $post Post object.
	 * @param $update
	 *
	 * @return void
	 */
	function saveAfterPayData( $post_id, $post, $update ) {
		if ( $update || ! $this->isPluginActive() ) {
			return;
		}
		if ( Order::isPendingRecovery() ) {
			\RNOC\App\Helpers\Order::setOrderMeta( $post_id, '_rnoc_is_pending_recovery', true );
		}
		$cart_token = self::getCartToken();
		if ( $cart_token ) {
			$cart = new Cart();
			$cart->setCartToken( $cart_token, $post_id );
		}
	}

	/**
	 * Capture retainful data.
	 *
	 * @param int $post_id Post id
	 *
	 * @return void
	 */
	function captureRetainfulDataFromQuote( $post_id ) {
		if ( ! $this->isPluginActive() ) {
			return;
		}
		$post = get_post( $post_id );
		if ( isset( $post->post_type ) && 'afterpay_quote' === $post->post_type ) {
			self::$quote_id = (int) $post_id;
			$post_meta      = get_post_meta( $post_id, '', true );
			foreach ( $post_meta as $key => $value ) {
				if ( 0 === strpos( $key, '_rnoc' ) ) {
					self::$retainful_meta[ $key ] = ! empty( $value[0] ) ? $value[0] : '';
				}
			}
		}
	}


	/**
	 * Save retainful data.
	 *
	 * @param int $order_id order id.
	 *
	 * @return void
	 */
	function saveRetainfulDataToOrder( $order_id ) {
		if ( ! self::isPluginActive() ) {
			return;
		}
		if ( $order_id > 0 && (int) $order_id === self::$quote_id ) {
			$order_object = \RNOC\App\Helpers\Order::getOrder( $order_id );
			if ( is_object( $order_object ) && ! empty( $order_object ) ) {
				foreach ( self::$retainful_meta as $key => $value ) {
					\RNOC\App\Helpers\Order::setOrderMeta( $order_id, $key, $value );
				}
			}
		}
	}


	/**
	 * Check AfterPay payment is active
	 *
	 * @return bool
	 */
	public static function isPluginActive() {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
		}

		return in_array( 'afterpay-gateway-for-woocommerce/afterpay-gateway-for-woocommerce.php', $active_plugins, false ) || array_key_exists( 'afterpay-gateway-for-woocommerce/afterpay-gateway-for-woocommerce.php', $active_plugins );
	}

}