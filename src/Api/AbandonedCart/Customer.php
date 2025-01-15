<?php


namespace Rnoc\Retainful\Api\AbandonedCart;


use \Rnoc\Retainful\Admin\Settings;

class Customer {

	/**
	 * create coupons
	 *
	 * @param   \WP_REST_Request  $request
	 *
	 * @return \WP_REST_Response
	 */
	public static  function getCustomerOrders( \WP_REST_Request $request  ) {
		if(empty($request)) {
			 return;
		 }
		$settings = new Settings();
		$email = !empty( $request->get_param('email')) ? sanitize_email( $request->get_param('email') ): '';
		$after_date = !empty( $request->get_param('date_after')) ?  sanitize_text_field( $request->get_param('date_after') ) : '';
		$digest = !empty( $request->get_param('digest')) ?  $request->get_param('digest') : '';

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return new \WP_Error('invalid_email', 'The email address provided is invalid.', ['status' => 400]);
		}

		if ( ! self::hashVerification( array( 'email' => $email ,'date_after' => $after_date ), $digest ) ) {
			$settings->logMessage( $digest, 'API customer order request digest not matched' );
			$status   = 400;
			$response = array(
				'success'       => false,
				'RESPONSE_CODE' => 'SECURITY_BREACH',
				'message'       => 'Security validation failed!'
			);

			return new \WP_REST_Response( $response, $status );
		}


		$order_arg = [
			 'email' =>  $email,
			 'date_after' => $after_date,
			 'post_status' => apply_filters('rnoc_customer_order_sync_order_status',['wc-completed', 'wc-processing']),
		];

		$orders = function_exists('wc_get_orders' ) && !empty($order_arg) ?  wc_get_orders($order_arg) : '';

		if(!empty($orders) && is_array($orders) && count($orders) > 0) {
			$response = [
				'success'       => true,
			];
		}else {
			$response = [
				'success'       => false,
			];
		}
		return new \WP_REST_Response( $response );

	}

	/**
	 * Hash verification
	 *
	 * @param $data
	 * @param $hash_value
	 *
	 * @return bool
	 */
	protected static function hashVerification( $data, $hash_value ) {
		if ( ! is_array( $data ) || ! is_string( $hash_value ) ) {
			return false;
		}
		$settings = new Settings();
		$data         = json_encode( $data );
		$secret       = $settings->getSecretKey();
		$reverse_hmac = hash_hmac( 'sha256', $data, $secret );
		return hash_equals( $reverse_hmac, $hash_value );
	}
}