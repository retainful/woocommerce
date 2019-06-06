<?php
/**
 * Plugin name: Retainful - Abandoned Cart Recovery Emails and Next Order Coupons
 * Plugin URI: https://www.retainful.com
 * Description: Recover abandoned carts and drive repeat purchases by sending single-use, unique coupon codes to customers for their next purchase
 * Author: Retainful
 * Author URI: https://www.retainful.com
 * Version: 1.1.9
 * Slug: retainful-next-order-coupon-for-woocommerce
 * Text Domain: retainful-next-order-coupon-for-woocommerce
 * Domain Path: /i18n/languages/
 * Plugin URI: https://www.retainful.com
 * Requires at least: 4.6.1
 * WC requires at least: 2.5
 * WC tested up to: 3.5
 */
if (!defined('ABSPATH')) exit;
/**
 * Define the text domain
 */
if (!defined('RNOC_TEXT_DOMAIN'))
    define('RNOC_TEXT_DOMAIN', 'retainful-next-order-coupon-for-woocommerce');
/**
 * Current version of our app
 */
if (!defined('RNOC_VERSION'))
    define('RNOC_VERSION', '1.1.9');
/**
 * Set base file URL
 */
if (!defined('RNOC_BASE_FILE'))
    define('RNOC_BASE_FILE', plugin_basename(__FILE__));
/**
 * Set base file URL
 */
if (!defined('RNOC_FILE'))
    define('RNOC_FILE', __FILE__);
/**
 * Set base file URL
 */
if (!defined('RNOC_PLUGIN_PREFIX'))
    define('RNOC_PLUGIN_PREFIX', 'rnoc_');
/**
 * Define plugin path
 */
if (!defined('RNOC_PLUGIN_PATH'))
    define('RNOC_PLUGIN_PATH', plugin_dir_path(__FILE__));
/**
 * Define plugin path
 */
if (!defined('RNOC_PLUGIN_URL'))
    define('RNOC_PLUGIN_URL', plugin_dir_url(__FILE__));
/**
 * Check for required packages
 */
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    return false;
}
require __DIR__ . '/vendor/autoload.php';

use Rnoc\Retainful\Main;

Main::instance();