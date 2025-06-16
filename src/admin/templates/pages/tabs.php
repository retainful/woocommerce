<?php
$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : NULL; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
$can_hide_next_order_coupon = get_option('retainful_hide_next_order_coupon', 'no');
$can_hide_premium_feature = get_option('retainful_hide_premium_feature', 'no');

?>
<h2 class="nav-tab-wrapper">
    <a class="nav-tab <?php if ($page == 'retainful_license') {
        echo "nav-tab-active";
    } ?>"
       href="<?php echo esc_url(admin_url('admin.php?page=retainful_license')); ?>"><?php esc_html_e('Connection', 'retainful-next-order-coupon-for-woocommerce'); ?></a>
    <a class="nav-tab <?php if ($page == 'retainful_settings') {
        echo "nav-tab-active";
    } ?>"
       href="<?php echo esc_url(admin_url('admin.php?page=retainful_settings')); ?>"><?php esc_html_e('Settings', 'retainful-next-order-coupon-for-woocommerce'); ?></a>
    <?php if ($can_hide_next_order_coupon !== 'yes'): ?>
        <a class="nav-tab <?php if ($page == 'retainful') {
            echo "nav-tab-active";
        } ?>"
           href="<?php echo esc_url(admin_url('admin.php?page=retainful')); ?>"><?php esc_html_e('Next order coupon', 'retainful-next-order-coupon-for-woocommerce'); ?></a>
    <?php endif; ?>
<!--    --><?php //if ($can_hide_premium_feature !== 'yes'): ?>
<!--        <a class="nav-tab --><?php //if ($page == 'retainful_premium') {
//            echo "nav-tab-active";
//        } ?><!--"-->
<!--           href="--><?php //echo esc_url(admin_url('admin.php?page=retainful_premium')); ?><!--">--><?php //esc_html_e('Premium Features', 'retainful-next-order-coupon-for-woocommerce'); ?><!--</a>-->
<!--    --><?php //endif; ?>
</h2>