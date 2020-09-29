<?php

namespace Rnoc\Retainful\Api\NextOrderCoupon;
class CouponManagement
{
    function couponDiscountTypes($types)
    {
        $types['retainful'] = __('Retainful', 'woocommerce');
        return $types;
    }

    /**
     * save the data in post meta
     * @param $post_id
     * @param $post
     */
    function processShopCouponMeta($post_id, $post)
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permission to perform this operation', 'wt-smart-coupons-for-woocommerce'));
        }
        if (isset($_POST['_rnoc_discount_type']) && !empty($_POST['_rnoc_discount_type']) && in_array($_POST['_rnoc_discount_type'], array('rnoc_flat', 'rnoc_percent'))) {
            $rnoc_discount_type = sanitize_text_field($_POST['_rnoc_discount_type']);
        } else {
            $rnoc_discount_type = 'rnoc_percent';
        }
        update_post_meta($post_id, '_rnoc_discount_type', $rnoc_discount_type);
    }

    /**
     * @param $coupon \WC_Coupon
     */
    function couponLoaded($coupon)
    {
        if (method_exists($coupon, 'get_meta')) {
            $discount_type = $coupon->get_meta('_rnoc_discount_type', true);
            if ($discount_type == "rnoc_flat") {
                $to_discount_type = "fixed_cart";
            } else {
                $to_discount_type = "percent";
            }
            $coupon->set_discount_type($to_discount_type);
        }
    }

    /**
     * additional fields to show coupon
     * @param $id
     * @param $coupon
     */
    function couponOptions($id, $coupon)
    {
        ?>
        <div id="rnoc_coupon_field_template" style="display: none">
            <?php
            $val = get_post_meta($id, '_rnoc_discount_type', true);
            woocommerce_wp_select(
                array(
                    'id' => '_rnoc_discount_type',
                    'label' => __('Coupon type', RNOC_TEXT_DOMAIN),
                    'options' => array(
                        'rnoc_percent' => __("Percentage", RNOC_TEXT_DOMAIN),
                        'rnoc_flat' => __("Flat", RNOC_TEXT_DOMAIN),
                    ),
                    'value' => $val,
                )
            );
            ?>
        </div>
        <script type="application/javascript">
            jQuery(document).on('change', '[name="discount_type"]', function () {
                var val = jQuery(this).val();
                handleRetainfulCoupon(val);
            })
            jQuery(document).ready(function () {
                var val = jQuery('[name="discount_type"]').val();
                handleRetainfulCoupon(val);
            });

            function handleRetainfulCoupon(val) {
                var field = jQuery("#rnoc_coupon_field_template");
                var coupon_amount_field = jQuery(".coupon_amount_field");
                var discount_type_field = jQuery(".rnoc_discount_type_field");
                coupon_amount_field.before(field.html());
                field.html('');
                if (val === "retainful") {
                    discount_type_field.show();
                } else {
                    discount_type_field.hide();
                }
            }
        </script>
        <?php
    }
}