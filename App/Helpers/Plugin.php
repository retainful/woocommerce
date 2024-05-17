<?php

namespace Rnoc\App\Helpers;

defined('ABSPATH') || exit; // Exit if accessed directly

class Plugin
{
    /**
     * Check the plugin are active or not.
     *
     * @param string $plugin_path Plugin path.
     * @return bool
     */
    public static function isActive($plugin_path)
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));

        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array($plugin_path, $active_plugins) || array_key_exists($plugin_path, $active_plugins);
    }

    /**
     * Before activate check.
     *
     * @param bool $allow_exit Allow exit.
     * @return bool
     */
    public static function checkDependencies($allow_exit = false)
    {

        if (!self::isPHPCompatible()) {
            $message = sprintf(__('%s requires minimum PHP version %s', 'retainful-next-order-coupon-for-woocommerce'), RNOC_PLUGIN_NAME, RNOC_MINIMUM_PHP_VERSION);
            $allow_exit ? die(esc_html($message)) : WC::adminNotice(esc_html($message), 'error');
            return false;
        }
        if (!self::isWordPressCompatible()) {
            $message = sprintf(__('%s requires minimum WordPress version %s', 'retainful-next-order-coupon-for-woocommerce'), RNOC_PLUGIN_NAME, RNOC_MINIMUM_WC_VERSION);
            $allow_exit ? exit($message) : WC::adminNotice(esc_html($message), 'error');
            return false;
        }

        if (!self::isActive('woocommerce/woocommerce.php')) {
            $message = sprintf(__('%s requires WooCommerce to be installed and activated in order to be used.', 'retainful-next-order-coupon-for-woocommerce'), RNOC_PLUGIN_NAME);
            $allow_exit ? exit($message) : WC::adminNotice(esc_html($message), 'error');
            return false;
        }
        if (!self::isWooCompatible()) {
            $message = sprintf(__('%s requires minimum Woocommerce version %s', 'retainful-next-order-coupon-for-woocommerce'), RNOC_PLUGIN_NAME, RNOC_MINIMUM_WC_VERSION);
            $allow_exit ? exit($message) : WC::adminNotice(esc_html($message), 'error');
            return false;
        }
        return true;
    }

    /**
     * Check php version is compatible.
     *
     * @return bool
     */
    protected static function isPHPCompatible()
    {
        return (int)version_compare(PHP_VERSION, RNOC_MINIMUM_PHP_VERSION, '>=') > 0;
    }

    /**
     * Check WordPress required version.
     *
     * @return bool
     */
    protected static function isWordPressCompatible()
    {
        return (int)version_compare(get_bloginfo('version'), RNOC_MINIMUM_WP_VERSION, '>=') > 0;
    }

    /**
     * Check woocommerce is compatible.
     *
     * @return bool
     */
    protected static function isWooCompatible()
    {
        $woo_version = self::getWooVersion();
        return (int)version_compare($woo_version, RNOC_MINIMUM_WC_VERSION, '>=') > 0;
    }

    /**
     * Get Woocommerce version.
     *
     * @return string
     */
    protected static function getWooVersion()
    {
        if (defined('WC_VERSION')) {
            return WC_VERSION;
        }
        if (!function_exists('get_plugins')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_folder = get_plugins('/woocommerce');
        return isset($plugin_folder['woocommerce.php']['Version']) ? $plugin_folder['woocommerce.php']['Version'] : '1.0.0';
    }

}