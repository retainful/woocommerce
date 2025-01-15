<?php


namespace Rnoc\Retainful\Api\AbandonedCart;


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
		 $order_arg = [
			 'email' => !empty( $request->get_param('email')) ? $request->get_param('email') : '',
			 'date_after' => !empty( $request->get_param('date_after')) ?  $request->get_param('date_after') : '',
			 'post_status' => ['wc-completed', 'wc-processing'],
		 ];
		$orders = function_exists('wc_get_orders' ) && !empty($order_arg) ?  wc_get_orders($order_arg) : '';
		if(!empty($orders)) {
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