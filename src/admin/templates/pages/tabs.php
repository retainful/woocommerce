<?php
$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : NULL; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<?php /* Plugin deprecated: replaced by full page popup below
<div class="rnoc-notice-container">
    <h1>Retainful V3 — Migration Required by April 15th</h1>
    <p>We've rebuilt Retainful from the ground up with a new secure WooCommerce REST API. Your store needs to be reconnected on the new platform to continue sending emails. All your contacts, lists, and flows carry over. Migrate now — it takes a few minutes.<a href="https://app.retainful.com/" target="_blank">Migrate My Account</a></p>
</div>
*/ ?>
<div class="rnoc-deprecated-overlay">
    <div class="rnoc-deprecated-popup" role="dialog" aria-modal="true" aria-labelledby="rnoc-deprecated-title">
        <div class="rnoc-deprecated-icon"><span class="dashicons dashicons-warning" aria-hidden="true"></span></div>
        <h2 id="rnoc-deprecated-title"><?php esc_html_e('Action required: Update Retainful', 'retainful-next-order-coupon-for-woocommerce'); ?></h2>
        <p><?php echo wp_kses_post(__('This version of Retainful is <strong>no longer supported</strong>. Install the latest plugin to continue managing your Retainful account.', 'retainful-next-order-coupon-for-woocommerce')); ?></p>
        <a class="rnoc-deprecated-btn" href="https://downloads.wordpress.org/plugin/retainful.zip"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e('Install Latest Plugin', 'retainful-next-order-coupon-for-woocommerce'); ?></a>
        <p class="rnoc-deprecated-note"><?php echo wp_kses_post(__('After installing, <strong>uninstall this old plugin</strong>. You won\'t be able to make changes here until the latest version is installed.', 'retainful-next-order-coupon-for-woocommerce')); ?></p>
    </div>
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