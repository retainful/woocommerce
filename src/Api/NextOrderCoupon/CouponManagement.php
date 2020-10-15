<?php

namespace Rnoc\Retainful\Api\NextOrderCoupon;
class CouponManagement
{
    /**
     * link to view retainful coupon
     * @param $types
     * @return mixed
     */
    function viewsEditShopCoupon($types)
    {
        // Add NOC link.
        if (current_user_can('manage_woocommerce')) {
            $class = (isset($_GET['filter-by']) && 'retainful-next-order-coupon' == $_GET['filter-by']) ? 'current' : '';
            $admin_url = admin_url('edit.php?post_type=shop_coupon');
            $query_string = add_query_arg(array('filter-by' => rawurlencode('retainful-next-order-coupon')), $admin_url);
            $query = new \WP_Query(array('post_type' => 'shop_coupon', 'meta_key' => '_rnoc_shop_coupon_type', 'meta_value' => 'retainful'));
            $types['retainful'] = '<a href="' . esc_url($query_string) . '" class="' . esc_attr($class) . '">' . __('Retainful - Next order coupons', 'woocommerce') . ' (' . $query->found_posts . ')</a>';
        }
        return $types;
    }

    /**
     * query to filter
     * @param $query_vars
     * @return mixed
     */
    function requestQuery($query_vars)
    {
        global $typenow;
        if ($typenow == "shop_coupon") {
            if (isset($_GET['filter-by']) && 'retainful-next-order-coupon' == $_GET['filter-by']) {
                $query_vars['meta_key'] = "_rnoc_shop_coupon_type";
                $query_vars['meta_value'] = "retainful";
            }
        }
        return $query_vars;
    }
}