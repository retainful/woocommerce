<?php

namespace Rnoc\Retainful\Api\AbandonedCart;
class Referral extends RestApi
{
    /**
     * verify the app id
     * @param $data \WP_REST_Request
     * @return \WP_REST_Response
     */
    function createCoupon($data)
    {
        $coupon_data = sanitize_text_field($data->get_param('coupon_data'));
        $response = array(
            'success' => true,
            'message' => 'this is success message'
        );
        $response_object = new \WP_REST_Response($response);
        $response_object->set_status(200);
        return $response_object;
    }
}