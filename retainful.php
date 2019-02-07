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
 * WC requires at least: 2.5
 * WC tested up to: 3.5
 */

if (!defined('ABSPATH')) exit;


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

/**
 * Check and abort if PHP version is is less them 5.6 and does not met the required woocommerce version
 */
register_activation_hook(__FILE__, function () {
    if (version_compare(phpversion(), '5.6', '<')) {
        exit(__('Retainful-woocommerce requires minimum PHP version of 5.6', RNOC_TEXT_DOMAIN));
    }
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        exit(__('Woocommerce must installed and activated in-order to use Retainful-Woocommerce!', RNOC_TEXT_DOMAIN));
    } else {
        if (!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $plugin_folder = get_plugins('/' . 'woocommerce');
        $plugin_file = 'woocommerce.php';
        $wc_installed_version = NULL;
        $wc_required_version = '2.5';
        if (isset($plugin_folder[$plugin_file]['Version'])) {
            $wc_installed_version = $plugin_folder[$plugin_file]['Version'];
        }
        if (version_compare($wc_required_version, $wc_installed_version, '>=')) {
            exit(__('Retainful-woocommerce requires minimum Woocommerce version of ', RNOC_TEXT_DOMAIN) . ' ' . $wc_required_version . '. ' . __('But your Woocommerce version is ', RNOC_TEXT_DOMAIN) . ' ' . $wc_installed_version);
        }
    }
});

/**
 * De-activate our plugin when Woocommerce gets deactivated
 */
add_action('deactivated_plugin', 'detect_plugin_deactivation', 10, 2);
function detect_plugin_deactivation($plugin, $network_activation)
{
    if (in_array($plugin, array('woocommerce/woocommerce.php'))) {
        //Todo - Deactivate this plugin
    }
}

/**
 * Check for required packages
 */
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    return false;
}

require __DIR__ . '/vendor/autoload.php';

use Rnoc\Retainful\Main;

Main::instance();