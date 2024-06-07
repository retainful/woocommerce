<?php

namespace RNOC\App\Modules\AbandonedCart\Traits;
trait SyncData {
	protected static $cart_token_key_for_db = '_rnoc_user_cart_token';
	protected static $cart_token_key = 'rnoc_user_cart_token';
	protected static $cart_tracking_started_key = 'rnoc_cart_created_at';
	protected static $cart_tracking_started_key_for_db = '_rnoc_cart_tracking_started_at';
	protected static $previous_cart_hash_key = 'rnoc_previous_cart_hash';
	protected static $order_cancelled_date_key_for_db = "_rnoc_order_cancelled_at";
}