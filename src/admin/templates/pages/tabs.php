<?php
$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : NULL;
$can_hide_next_order_coupon = get_option('retainful_hide_next_order_coupon','no');
?>
<h2 class="nav-tab-wrapper">
    <a class="nav-tab <?php if($page=='retainful_license'){echo "nav-tab-active";} ?>" href="<?php echo admin_url('admin.php?page=retainful_license');?>"><?php esc_html_e('Connection',RNOC_TEXT_DOMAIN); ?></a>
    <a class="nav-tab <?php if($page=='retainful_settings'){echo "nav-tab-active";} ?>" href="<?php echo admin_url('admin.php?page=retainful_settings');?>"><?php esc_html_e('Settings',RNOC_TEXT_DOMAIN); ?></a>
    <?php if($can_hide_next_order_coupon !== 'yes'):?>
        <a class="nav-tab <?php if($page=='retainful'){echo "nav-tab-active";} ?>" href="<?php echo admin_url('admin.php?page=retainful');?>"><?php esc_html_e('Next order coupon',RNOC_TEXT_DOMAIN); ?></a>
    <?php endif; ?>
        <a class="nav-tab <?php if($page=='retainful_premium'){echo "nav-tab-active";} ?>" href="<?php echo admin_url('admin.php?page=retainful_premium');?>"><?php esc_html_e('Premium Features',RNOC_TEXT_DOMAIN); ?></a>
</h2>