<?php

namespace Rnoc\Retainful\Helpers;
defined('ABSPATH') || exit; // Exit if accessed directly

class plugin
{
    /**
     * Check the plugin are active or not.
     *
     * @param string $plugin_path Plugin path.
     * @return bool
     */
    public static function isActive(string $plugin_path): bool
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
    public static function checkDependencies(bool $allow_exit = false): bool
    {

        if (!self::isPHPCompatible()) {
            wp_die(sprintf(__('This plugin can not be activated because it requires minimum PHP version of %1$s.', RNOC_TEXT_DOMAIN), RNOC_MINIMUM_PHP_VERSION));
        }
        if (!self::isWordPressCompatible()) {
            exit(__('Woocommerce Email Customizer + requires at least Wordpress', RNOC_TEXT_DOMAIN) . ' ' . RNOC_MINIMUM_WC_VERSION);
        }

        if (!self::isActive('woocommerce/woocommerce.php')) {
            exit(__('Woocommerce must be installed and activated in-order to use Retainful!', RNOC_TEXT_DOMAIN));
        }
        if (!self::isWooCompatible()) {
            exit(__('Woocommerce Email Customizer + requires at least Woocommerce', RNOC_TEXT_DOMAIN) . ' ' . RNOC_MINIMUM_WC_VERSION);
        }
        return true;
    }

    /**
     * Check php version is compatible.
     *
     * @return bool
     */
    protected static function isPHPCompatible(): bool
    {
        return (int)version_compare(PHP_VERSION, RNOC_MINIMUM_PHP_VERSION, '>=') > 0;
    }

    /**
     * Check WordPress required version.
     *
     * @return bool
     */
    protected static function isWordPressCompatible(): bool
    {
        return (int)version_compare(get_bloginfo('version'), RNOC_MINIMUM_WP_VERSION, '>=') > 0;
    }

    /**
     * Check woocommerce is compatible.
     *
     * @return bool
     */
    protected static function isWooCompatible(): bool
    {
        $woo_version = self::getWooVersion();
        return (int)version_compare($woo_version, RNOC_MINIMUM_WC_VERSION, '>=') > 0;
    }

    /**
     * Get Woocommerce version.
     *
     * @return string
     */
    protected static function getWooVersion(): string
    {
        if (defined('WC_VERSION')) {
            return WC_VERSION;
        }
        if (!function_exists('get_plugins')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_folder = get_plugins('/woocommerce');
        return $plugin_folder['woocommerce.php']['Version'] ?? '1.0.0';
    }

}