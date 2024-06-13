<?php
/**
 * Plugin name: Retainful - Abandoned Cart Recovery Emails and Next Order Coupons
 * Plugin URI: https://www.retainful.com
 * Description: Recover abandoned carts and drive repeat purchases by sending single-use, unique coupon codes to customers for their next purchase
 * Author: Retainful
 * Author URI: https://www.retainful.com
 * Version: 2.6.34
 * Slug: retainful-next-order-coupon-for-woocommerce
 * Text Domain: retainful-next-order-coupon-for-woocommerce
 * Domain Path: /i18n/languages/
 * Plugin URI: https://www.retainful.com
 * Requires at least: 4.7.0
 * WC requires at least: 6.0.0
 * WC tested up to: 8.9
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use RNOC\App\Router;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'isWoocommerceActive' ) ) {
	function isWoocommerceActive() {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}
}
// Check woocommerce active or not
if ( ! isWoocommerceActive() ) {
	return;
}

// HPOS support
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( FeaturesUtil::class ) ) {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
	}
} );

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return false;
}

// Constants
defined( 'RNOC_PLUGIN_SLUG' ) or define( 'RNOC_PLUGIN_SLUG', 'retainful-next-order-coupon-for-woocommerce' );
defined( 'RNOC_PLUGIN_PREFIX' ) or define( 'RNOC_PLUGIN_PREFIX', 'rnoc_' );
defined( 'RNOC_PLUGIN_PATH' ) or define( 'RNOC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
defined( 'RNOC_PLUGIN_URL' ) or define( 'RNOC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'RNOC_VERSION' ) or define( 'RNOC_VERSION', '2.6.32' );
require __DIR__ . '/vendor/autoload.php';

if ( class_exists( Router::class ) ) {
	Router::init();
}