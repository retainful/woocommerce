<?php

namespace RNOC\App\Models\WC;

use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

class Order {
	/**
	 * Get order object.
	 *
	 * @param int|WC_Order $order_or_id Order object or id.
	 *
	 * @return WC_Order|WC_Order_Refund|bool
	 */
	public static function get( $order_or_id ) {
		return wc_get_order( $order_or_id );
	}
}