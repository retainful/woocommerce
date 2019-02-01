<?php
/**
 * Plugin name: Retainful - Next order coupon for WooCommerce
 * Plugin URI: https://www.retainful.com
 * Description: Drive repeat purchases by sending single-use, unique coupon codes to customers for their next purchase
 * Author: Retainful
 * Author URI: https://www.retainful.com
 * Version: 1.0.4
 * Slug: retainful-next-order-coupon-for-woocommerce
 * Text Domain: retainful-woocommerce
 * Domain Path: /i18n/languages/
 * Plugin URI: https://www.retainful.com
 * Requires at least: 4.6.1
 * WC requires at least: 2.4
 * WC tested up to: 3.5
 */

if (!defined('ABSPATH')) exit;

/**
 * Check and abort if PHP version is is less them 5.6
 */
register_activation_hook(__FILE__, function () {
    if (version_compare(phpversion(), '5.6', '<')) {
        wp_die('Retainful-woocommerce requires minimum PHP version of 5.6');
    }
});

/**
 * Check for required packages
 */
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    return false;
}

/**
 * Define the text domain
 */
if (!defined('RNOC_TEXT_DOMAIN'))
    define('RNOC_TEXT_DOMAIN', 'retainful-woocommerce');

/**
 * Current version of our app
 */
if (!defined('RNOC_VERSION'))
    define('RNOC_VERSION', '1.0.4');

/**
 * Set base file URL
 */
if (!defined('RNOC_BASE_FILE'))
    define('RNOC_BASE_FILE', plugin_basename(__FILE__));

require __DIR__ . '/vendor/autoload.php';

use Rnoc\Retainful\Main;

Main::instance();