<?php
/**
 * Plugin name: Retainful - Next order coupon for WooCommerce
 * Plugin URI: https://www.retainful.com
 * Description: Drive repeat purchases by sending single-use, unique coupon codes to customers for their next purchase
 * Author: Retainful
 * Author URI: https://www.retainful.com
 * Version: 1.0.1
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

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    wp_die('Unable to find packages!');
}

/**
 * Define the text domain
 */
if(!defined('RNOC_TEXT_DOMAIN'))
    define('RNOC_TEXT_DOMAIN','retainful-woocommerce');

require __DIR__ . '/vendor/autoload.php';

use Rnoc\Retainful\Main;

Main::instance();