<?php
$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : NULL; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
</h2>