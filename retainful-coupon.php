<?php
/**
 * Plugin name: Retainful next order
 * Plugin URI: http://www.flycart.org
 * Description: Simple plugin to give coupon codes and track coupons for woocommerce
 * Author: Flycart Technologies LLP
 * Author URI: https://www.flycart.org
 * Version: 1.0.0
 * Slug: retainful-coupon
 * Text Domain: retainful-coupon
 * Domain Path: /i18n/languages/
 * Plugin URI: http://www.flycart.org
 * Requires at least: 4.6.1
 * WC requires at least: 2.4
 * WC tested up to: 3.5
 */

namespace Rnoc;
if (!defined('ABSPATH')) exit;

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    wp_die('Unable to find packages!');
}

require __DIR__ . '/vendor/autoload.php';

use Rnoc\Retainful\Main;

Main::instance();