<?php

namespace Rnoc\Retainful\Admin;
class Settings
{
    /**
     * options key
     * @var string
     */
    public $slug = 'retainful';
    /**
     * settings
     * @var array[]
     */
    public static $settings;

    function __construct()
    {
        $connection = get_option($this->slug . '_license', array());
        $default_connection = $this->getDefaultConnectionSettings();
        self::$settings = array(
            'connection' => wp_parse_args($connection, $default_connection),
            'general_settings' => array(),
            'next_order_coupon' => array(),
            'premium' => array(),
        );
    }

    function get($type, $key = "", $all = false, $depth = false)
    {

    }

    /**
     * default connection settings
     * @return array
     */
    function getDefaultConnectionSettings()
    {
        return array(
            RNOC_PLUGIN_PREFIX . 'is_retainful_connected' => 0,
            RNOC_PLUGIN_PREFIX . 'retainful_app_id' => '',
            RNOC_PLUGIN_PREFIX . 'retainful_app_secret' => '',
        );
    }
}