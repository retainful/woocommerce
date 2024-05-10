<?php
/**
 * Plugin name: Retainful - Abandoned Cart Recovery Emails and Next Order Coupons
 * Plugin URI: https://www.retainful.com
 * Description: Recover abandoned carts and drive repeat purchases by sending single-use, unique coupon codes to customers for their next purchase
 * Author: Retainful
 * Author URI: https://www.retainful.com
 * Version: 2.6.32
 * Slug: retainful-next-order-coupon-for-woocommerce
 * Text Domain: retainful-next-order-coupon-for-woocommerce
 * Domain Path: /i18n/languages/
 * Plugin URI: https://www.retainful.com
 * Requires at least: 4.7.0
 * Contributers: Sathyaseelan
 * WC requires at least: 6.0.0
 * WC tested up to: 8.7
 */
defined('ABSPATH') || die;
// Define the text domain
defined('RNOC_TEXT_DOMAIN') || define('RNOC_TEXT_DOMAIN', 'retainful-next-order-coupon-for-woocommerce');
// Define the plugin slug
defined('RNOC_PLUGIN_SLUG') || define('RNOC_PLUGIN_SLUG', 'retainful-next-order-coupon-for-woocommerce');
// Current version of our app
defined('RNOC_VERSION') || define('RNOC_VERSION', '2.6.32');
//Set base file URL
defined('RNOC_BASE_FILE') || define('RNOC_BASE_FILE', plugin_basename(__FILE__));
//Set base file URL
defined('RNOC_FILE') || define('RNOC_FILE', __FILE__);
// Set base file URL
defined('RNOC_PLUGIN_PREFIX') || define('RNOC_PLUGIN_PREFIX', 'rnoc_');
//Define plugin path
defined('RNOC_PLUGIN_PATH') || define('RNOC_PLUGIN_PATH', plugin_dir_path(__FILE__));
//Define plugin path
defined('RNOC_PLUGIN_URL') || define('RNOC_PLUGIN_URL', plugin_dir_url(__FILE__));
//Check for required packages
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    return false;
}
//Define plugin path
defined('RNOCPREMIUM_PLUGIN_PATH') || define('RNOCPREMIUM_PLUGIN_PATH', RNOC_PLUGIN_PATH . 'src/premium/');
//Define premium plugin URL
defined('RNOCPREMIUM_PLUGIN_URL') || define('RNOCPREMIUM_PLUGIN_URL', RNOC_PLUGIN_URL . 'src/premium/');
//Set Plugin log path
$path = ABSPATH . 'wp-content/retainful.log';
defined('RNOC_LOG_FILE_PATH') || define('RNOC_LOG_FILE_PATH', $path);

defined('RNOC_PLUGIN_NAME') || define('RNOC_PLUGIN_NAME', "Retainful - Abandoned Cart Recovery Emails and Next Order Coupons");
defined('RNOC_MINIMUM_WC_VERSION') || define('RNOC_MINIMUM_WC_VERSION', '6.0.0');
defined('RNOC_MINIMUM_WP_VERSION') || define('RNOC_MINIMUM_WP_VERSION', '4.7.0');
defined('RNOC_MINIMUM_PHP_VERSION') || define('RNOC_MINIMUM_PHP_VERSION', '5.6.0');
defined('REQUESTS_SILENCE_PSR0_DEPRECATIONS') || define('REQUESTS_SILENCE_PSR0_DEPRECATIONS', true);

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

if (!function_exists('rnocEscAttr')) {
    function rnocEscAttr($txt)
    {
        return stripslashes(esc_attr__($txt));
    }
}

if (!file_exists(RNOC_PLUGIN_PATH . '/vendor/autoload.php')) {
    return;
}


require __DIR__ . '/vendor/autoload.php';
if (class_exists('Rnoc\App\Route')) {
    if (\Rnoc\App\Helpers\PluginCompatiable::checkDependencies()) {
        \Rnoc\App\Route::init(); // init plugin hooks
    }
}
