<?php

namespace RNOC\App\Modules\AbandonedCart;

use RNOC\App\Helpers\Settings;
use Valitron\Validator;

class Coupon {

	/**
	 * create coupons.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	static function createRestCoupon( \WP_REST_Request $request ) {
		$requestParams        = $request->get_params();
		$defaultRequestParams = [
			'discount_rule' => [],
			'digest'        => ''
		];
		$params               = wp_parse_args( $requestParams, $defaultRequestParams );
		if ( is_array( $params['discount_rule'] ) && ! empty( $params['discount_rule'] ) && is_string( $params['digest'] ) && ! empty( $params['digest'] ) ) {
			$secret          = Settings::get( RNOC_PLUGIN_PREFIX . 'retainful_app_secret', '', 'licence' );
			$to_hash         = [
				'value_type'  => ( isset( $params['discount_rule']['value_type'] ) ) ? $params['discount_rule']['value_type'] : "",
				'value'       => ( isset( $params['discount_rule']['value'] ) ) ? $params['discount_rule']['value'] : "",
				'coupon_code' => ( isset( $params['discount_rule']['coupon_code'] ) ) ? $params['discount_rule']['coupon_code'] : "",
			];
			$cipher_text_raw = json_encode( $to_hash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$reverse_hmac    = hash_hmac( 'sha256', $cipher_text_raw, $secret );
			if ( hash_equals( $reverse_hmac, $params['digest'] ) ) {
				$defaultRuleParams = [
					'coupon_code'                 => null,
					'usage_limit'                 => 1,
					'usage_limit_per_user'        => 1,
					'value_type'                  => 'percentage',
					'value'                       => 0,
					'target_type'                 => 'line_item',
					'customer_email'              => null,
					'ends_at'                     => null,
					'prerequisite_subtotal_range' => [ 'greater_than_or_equal_to' => 0 ],
				];
				$ruleParams        = wp_parse_args( $params['discount_rule'], $defaultRuleParams );
				$is_valid_data     = true;
				$errors            = [];
				self::validateRestCoupon( $ruleParams, $is_valid_data, $errors );
				if ( $is_valid_data ) {
					$data = [
						'code'                        => $ruleParams['coupon_code'],
						'amount'                      => ( $ruleParams['value'] < 0 ) ? floatval( $ruleParams['value'] ) * - 1 : 0,
						'status'                      => 'publish',
						'date_created'                => current_time( 'timestamp', true ),
						'date_modified'               => current_time( 'timestamp', true ),
						'date_expires'                => ( ! empty( $ruleParams['ends_at'] ) ) ? strtotime( $ruleParams['ends_at'] ) : null,
						'discount_type'               => ( $ruleParams['value_type'] == "fixed_amount" ) ? 'fixed_cart' : 'percent',
						'description'                 => '',
						'usage_count'                 => 0,
						'individual_use'              => true,
						'product_ids'                 => [],
						'excluded_product_ids'        => [],
						'usage_limit'                 => $ruleParams['usage_limit'],
						'usage_limit_per_user'        => 0,
						'limit_usage_to_x_items'      => $ruleParams['usage_limit_per_user'],
						'free_shipping'               => ( $ruleParams['target_type'] == "shipping_line" ) ? 'yes' : 'no',
						'product_categories'          => [],
						'excluded_product_categories' => [],
						'exclude_sale_items'          => true,
						'minimum_amount'              => ( floatval( $ruleParams['prerequisite_subtotal_range']['greater_than_or_equal_to'] ) > 0 ) ? floatval( $ruleParams['prerequisite_subtotal_range']['greater_than_or_equal_to'] ) : null,
						'maximum_amount'              => 0,
						'email_restrictions'          => [],
						'used_by'                     => [],
						'virtual'                     => false,
					];
					if ( isset( $data['free_shipping'] ) && $data['free_shipping'] === 'yes' ) {
						$data['coupon_amount'] = 0;
					}
					$data = apply_filters( 'rnoc_before_create_rest_coupon', $data, $ruleParams );
					if ( class_exists( 'WC_Coupon' ) ) {
						$coupon        = new \WC_Coupon( $data['code'] );
						$old_coupon_id = $coupon->get_id();
						if ( empty( $old_coupon_id ) ) {
							$coupon = new \WC_Coupon();
							$coupon->set_code( $data['code'] ); // Set the coupon code
							$coupon->set_discount_type( $data['discount_type'] ); // Type: 'fixed_cart', 'percent', 'fixed_product'
							$coupon->set_status( 'publish' );
							$coupon->set_amount( $data['amount'] ); // Amount of discount
							$coupon->set_date_expires( $data['date_expires'] ); // Expiry date
							$coupon->set_usage_limit( $data['usage_limit'] ); // Usage limit
							$coupon->set_usage_limit_per_user( $data['usage_limit_per_user'] );
							$coupon->set_limit_usage_to_x_items( $data['limit_usage_to_x_items'] );
							$coupon->set_free_shipping( $data['free_shipping'] );
							$coupon->set_product_ids( $data['product_ids'] );
							$coupon->set_excluded_product_ids( $data['excluded_product_ids'] );
							$coupon->set_date_created( $data['date_created'] );
							$coupon->set_date_modified( $data['date_modified'] );
							$coupon->set_individual_use( $data['individual_use'] );
							$coupon->set_usage_count( $data['usage_count'] );
							$coupon->set_excluded_product_ids( $data['excluded_product_ids'] );
							$coupon->set_minimum_amount( $data['minimum_amount'] );
							$coupon->set_maximum_amount( $data['minimum_amount'] );
							$coupon->set_email_restrictions( $data['email_restrictions'] );
							$coupon->set_virtual( $data['virtual'] );
							$coupon->set_used_by( $data['used_by'] );
							$coupon->set_exclude_sale_items( $data['exclude_sale_items'] );
							$coupon_id = $coupon->save();
						}
					}
					$response = [
						'success'       => false,
						'RESPONSE_CODE' => 'UNABLE_TO_CREATE_OR_UPDATE',
						'message'       => 'Coupon code was not created!'
					];
					$status   = 200;
					if ( ! empty( $coupon_id ) ) {
						$response = [
							'success'                => true,
							'RESPONSE_CODE'          => 'COUPON_CODE_CREATED_OR_UPDATED',
							'external_price_rule_id' => $coupon_id,
							'code'                   => $data['coupon_code']
						];
					}
				} else {
					$status   = 400;
					$response = $errors;
				}
			} else {
				$status   = 400;
				$response = [
					'success'       => false,
					'RESPONSE_CODE' => 'SECURITY_BREACH',
					'message'       => 'Security validation failed!'
				];
			}
		} else {
			$status   = 400;
			$response = [ 'success' => false, 'RESPONSE_CODE' => 'DATA_MISSING', 'message' => 'Invalid data!' ];
		}

		return new \WP_REST_Response( $response, $status );
	}

	/**
	 * Validate the data.
	 *
	 * @param array $data data.
	 * @param bool $validate validate.
	 * @param array $errors errors.
	 */
	static function validateRestCoupon( $data, &$validate, &$errors ) {
		$validator = new Validator( $data );
		$validator->rule( 'slug', [
			'coupon_code',
		] );
		$validator->rule( 'dateFormat', 'expiry_date', 'Y-m-d' );
		$validator->rule( 'in', [
			'value_type',
		], [ 'percentage', 'fixed_amount' ] );
		$validator->rule( 'in', [
			'target_type',
		], [ 'shipping_line', 'line_item' ] );
		$validator->rule( 'numeric', [
			'value'
		] );
		$validator->rule( 'integer', [
			'usage_limit',
			'usage_limit_per_user',
		] );
		$validator->rule( 'email', 'customer_email' );
		$validate = $validator->validate();
		$errors   = $validator->errors();
	}

}