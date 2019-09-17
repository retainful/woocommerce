<?php
/**
 * Plugin name: Retainful - Abandoned Cart Recovery Emails and Next Order Coupons
 * Plugin URI: https://www.retainful.com
 * Description: Recover abandoned carts and drive repeat purchases by sending single-use, unique coupon codes to customers for their next purchase
 * Author: Retainful
 * Author URI: https://www.retainful.com
 * Version: 1.3.0
 * Slug: retainful-next-order-coupon-for-woocommerce
 * Text Domain: retainful-next-order-coupon-for-woocommerce
 * Domain Path: /i18n/languages/
 * Plugin URI: https://www.retainful.com
 * Requires at least: 4.6.1
 * WC requires at least: 2.5
 * WC tested up to: 3.7.0
 */
if (!defined('ABSPATH')) exit;
/**
 * Define the text domain
 */
if (!defined('RNOC_TEXT_DOMAIN'))
    define('RNOC_TEXT_DOMAIN', 'retainful-next-order-coupon-for-woocommerce');

/**
 * Define the plugin slug
 */
if (!defined('RNOC_PLUGIN_SLUG'))
    define('RNOC_PLUGIN_SLUG', 'retainful-next-order-coupon-for-woocommerce');
/**
 * Current version of our app
 */
if (!defined('RNOC_VERSION'))
    define('RNOC_VERSION', '1.3.0');
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
//Create and alter the tables for abandoned carts and also check for woocommerce installed
register_activation_hook(RNOC_FILE, 'RnocValidatePluginActivation');
if (!function_exists('RnocValidatePluginActivation')) {
    function RnocValidatePluginActivation()
    {
        if (version_compare(phpversion(), '5.6', '<')) {
            exit(__('Retainful-woocommerce requires minimum PHP version of 5.6', RNOC_TEXT_DOMAIN));
        }
        $path = 'woocommerce/woocommerce.php';
        if (!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $plugins = get_plugins();
        $is_installed =  isset($plugins[$path]);
        if(!$is_installed){
            exit(__('Woocommerce must installed and activated in-order to use Retainful-Woocommerce!', RNOC_TEXT_DOMAIN));
        }else {
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
        do_action('retainful_plugin_activated');
    }
}


require __DIR__ . '/vendor/autoload.php';

use Rnoc\Retainful\Main;

Main::instance();