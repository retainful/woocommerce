<?php
/**
 * Plugin name: Retainful - Abandoned Cart Recovery Emails and Next Order Coupons
 * Plugin URI: https://www.retainful.com
 * Description: Recover abandoned carts and drive repeat purchases by sending single-use, unique coupon codes to customers for their next purchase
 * Author: Retainful
 * Author URI: https://www.retainful.com
 * Version: 2.6.12
 * Slug: retainful-next-order-coupon-for-woocommerce
 * Text Domain: retainful-next-order-coupon-for-woocommerce
 * Domain Path: /i18n/languages/
 * Plugin URI: https://www.retainful.com
 * Requires at least: 4.6.1
 * Contributers: Sathyaseelan
 * WC requires at least: 3.0.9
 * WC tested up to: 6.3
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
    define('RNOC_VERSION', '2.6.12');
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
/**
 * Define plugin path
 */
if (!defined('RNOCPREMIUM_PLUGIN_PATH'))
    define('RNOCPREMIUM_PLUGIN_PATH', RNOC_PLUGIN_PATH . 'src/premium/');
/**
 * Define premium plugin URL
 */
if (!defined('RNOCPREMIUM_PLUGIN_URL'))
    define('RNOCPREMIUM_PLUGIN_URL', RNOC_PLUGIN_URL . 'src/premium/');
/**
 * Set Plugin log path
 */
if (!defined('RNOC_LOG_FILE_PATH')) {
    $path = ABSPATH . 'wp-content/retainful.log';
    define('RNOC_LOG_FILE_PATH', $path);
}
/**
 * Setup plugin compatable versions
 */
if (!defined('RNOC_MINIMUM_WC_VERSION')) {
    define('RNOC_MINIMUM_WC_VERSION', '3.0.9');
}
if (!defined('RNOC_MINIMUM_WP_VERSION')) {
    define('RNOC_MINIMUM_WP_VERSION', '4.4');
}
if (!defined('RNOC_MINIMUM_PHP_VERSION')) {
    define('RNOC_MINIMUM_PHP_VERSION', '5.6.0');
}
//Create and alter the tables for abandoned carts and also check for woocommerce installed
register_activation_hook(RNOC_FILE, 'rnocPluginActivation');
if (!function_exists('rnocPluginActivation')) {
    /**
     * Run on plugin activation
     */
    function rnocPluginActivation()
    {
        if (!rnocIsEnvironmentCompatible()) {
            wp_die(sprintf(__('This plugin can not be activated because it requires minimum PHP version of %1$s.', RNOC_TEXT_DOMAIN), RNOC_MINIMUM_PHP_VERSION));
        }
        if (!rnocIsWordPressCompatible()) {
            exit(__('Woocommerce Email Customizer + requires at least Wordpress', RNOC_TEXT_DOMAIN) . ' ' . RNOC_MINIMUM_WC_VERSION);
        }
        if (!rnocIsWoocommerceActive()) {
            exit(__('Woocommerce must be installed and activated in-order to use Retainful!', RNOC_TEXT_DOMAIN));
        }
        if (!rnocIsWooCompatible()) {
            exit(__('Woocommerce Email Customizer + requires at least Woocommerce', RNOC_TEXT_DOMAIN) . ' ' . RNOC_MINIMUM_WC_VERSION);
        }
        do_action('retainful_plugin_activated');
        return true;
    }
}
/**
 * Check the woocommerce is active or not
 * @return bool
 */
if (!function_exists('rnocIsWoocommerceActive')) {
    function rnocIsWoocommerceActive()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('woocommerce/woocommerce.php', $active_plugins, false) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
    }
}
/**
 * Check woocommerce version is compatibility
 * @return bool
 */
if (!function_exists('rnocIsWooCompatible')) {
    function rnocIsWooCompatible()
    {
        if (!RNOC_MINIMUM_WC_VERSION) {
            $is_compatible = true;
        } else {
            $is_compatible = defined('WC_VERSION') && version_compare(WC_VERSION, RNOC_MINIMUM_WC_VERSION, '>=');
        }
        return $is_compatible;
    }
}
/**
 * Determines if the WordPress compatible.
 *
 * @return bool
 * @since 1.0.0
 *
 */
if (!function_exists('rnocIsWordPressCompatible')) {
    function rnocIsWordPressCompatible()
    {
        if (!RNOC_MINIMUM_WP_VERSION) {
            $is_compatible = true;
        } else {
            $is_compatible = version_compare(get_bloginfo('version'), RNOC_MINIMUM_WP_VERSION, '>=');
        }
        return $is_compatible;
    }
}
/**
 * Determines if the server environment is compatible with this plugin.
 *
 * @return bool
 * @since 1.0.0
 *
 */
if (!function_exists('rnocIsEnvironmentCompatible')) {
    function rnocIsEnvironmentCompatible()
    {
        return version_compare(PHP_VERSION, RNOC_MINIMUM_PHP_VERSION, '>=');
    }
}
if (!function_exists('rnocGetInstalledWoocommerceVersion')) {
    /**
     * Get the installed woocommerce version
     */
    function rnocGetInstalledWoocommerceVersion()
    {
        $plugin_folder = get_plugins('/woocommerce');
        $plugin_file = 'woocommerce.php';
        $wc_installed_version = NULL;
        if (isset($plugin_folder[$plugin_file]['Version'])) {
            $wc_installed_version = $plugin_folder[$plugin_file]['Version'];
        }
        return $wc_installed_version;
    }
}
if (!function_exists('rnocEscAttr')) {
    function rnocEscAttr($txt)
    {
        return stripslashes(esc_attr__($txt));
    }
}
/**
 * check is woocommerce is active
 */
if (!rnocIsWoocommerceActive()) {
    return '';
}
require __DIR__ . '/vendor/autoload.php';

use Rnoc\Retainful\Main;

Main::instance();