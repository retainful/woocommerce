<?php
/**
 * Plugin name: Retainful - Next order coupon for WooCommerce
 * Plugin URI: https://www.retainful.com
 * Description: Drive repeat purchases by sending single-use, unique coupon codes to customers for their next purchase
 * Author: Retainful
 * Author URI: https://www.retainful.com
 * Version: 1.0.2
 * Slug: retainful-woocommerce
 * Text Domain: retainful-woocommerce
 * Domain Path: /i18n/languages/
 * Plugin URI: https://www.retainful.com
 * Requires at least: 4.6.1
 * WC requires at least: 2.4
 * WC tested up to: 3.5
 */

namespace Rnoc;
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
    wp_die('Unable to find packages!');
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
    define('RNOC_VERSION', '1.0.2');

require __DIR__ . '/vendor/autoload.php';

use Rnoc\Retainful\Main;

Main::instance();