<?php
/**
 * @var $need_premium_features_link bool
 */
$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : NULL;
?>
<h2 class="nav-tab-wrapper">
    <a class="nav-tab <?php if ($page == 'retainful_license') {
        echo "nav-tab-active";
    } ?>"
       href="<?php echo admin_url('admin.php?page=retainful_license'); ?>"><?php esc_html_e('Connection', RNOC_TEXT_DOMAIN); ?></a>
    <a class="nav-tab <?php if ($page == 'retainful_settings') {
        echo "nav-tab-active";
    } ?>"
       href="<?php echo admin_url('admin.php?page=retainful_settings'); ?>"><?php esc_html_e('Settings', RNOC_TEXT_DOMAIN); ?></a>
    <a class="nav-tab <?php if ($page == 'retainful') {
        echo "nav-tab-active";
    } ?>"
       href="<?php echo admin_url('admin.php?page=retainful'); ?>"><?php esc_html_e('Next order coupon', RNOC_TEXT_DOMAIN); ?></a>
    <?php
    if ($need_premium_features_link) {
        ?>
        <a class="nav-tab <?php if ($page == 'retainful_premium') {
            echo "nav-tab-active";
        } ?>"
           href="<?php echo admin_url('admin.php?page=retainful_premium'); ?>"><?php esc_html_e('Premium', RNOC_TEXT_DOMAIN); ?>
            Features</a>
        <?php
    }
    ?>
</h2>