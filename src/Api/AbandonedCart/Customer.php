<?php


namespace Rnoc\Retainful\Api\AbandonedCart;


use function WPML\FP\apply;

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
		$email = !empty( $request->get_param('email')) ? sanitize_email( $request->get_param('email') ): '';
		$after_date = !empty( $request->get_param('date_after')) ?  sanitize_text_field( $request->get_param('date_after') ) : '';

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return new \WP_Error('invalid_email', 'The email address provided is invalid.', ['status' => 400]);
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
}