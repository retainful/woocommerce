<?php
$page = isset( $page ) ? $page : 'retainful_license';
?>
<div class="rnoc-main">
    <h2 class="rnoc-main nav-tab-wrapper">
        <a class="nav-tab <?php echo esc_attr( $page == 'retainful_license' ? 'nav-tab-active' : '' ); ?>"
           href="<?php echo admin_url( 'admin.php?page=retainful_license' ); ?>"><?php esc_html_e( 'Connection', 'retainful-next-order-coupon-for-woocommerce' ); ?></a>
        <a class="nav-tab <?php echo esc_attr( $page == 'retainful_settings' ? 'nav-tab-active' : '' ); ?>"
           href="<?php echo admin_url( 'admin.php?page=retainful_settings' ); ?>"><?php esc_html_e( 'Settings', 'retainful-next-order-coupon-for-woocommerce' ); ?></a>
    </h2>
	<?php echo $sub_content; ?>
</div>
