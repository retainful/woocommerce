<?php
$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : NULL; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div style="background-color: #d63638; color: white; padding: 10px; margin-bottom: 20px;border-radius: 8px;">
    <h1 style="color: white;">Retainful V3 — Migration Required by April 15th</h1>
    <p style="font-size: 17px;">We've rebuilt Retainful from the ground up with a new secure WooCommerce REST API. Your store needs to be reconnected on the new platform to continue sending emails. All your contacts, lists, and flows carry over. Migrate now — it takes a few minutes.<a href="https://app.retainful.com/" class="button button-primary align-center" target="_blank">Migrate My Account</a></p>
</div>
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