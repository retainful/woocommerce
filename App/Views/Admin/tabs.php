<?php
$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : NULL;
?>
<h2 class="nav-tab-wrapper">
    <a class="nav-tab <?php if ($page == 'retainful_license') {
        echo "nav-tab-active";
    } ?>"
       href="<?php echo admin_url('admin.php?page=retainful_license'); ?>"><?php esc_html_e('Connection', 'retainful-next-order-coupon-for-woocommerce'); ?></a>
    <a class="nav-tab <?php if ($page == 'retainful_settings') {
        echo "nav-tab-active";
    } ?>"
       href="<?php echo admin_url('admin.php?page=retainful_settings'); ?>"><?php esc_html_e('Settings', 'retainful-next-order-coupon-for-woocommerce'); ?></a>
</h2>