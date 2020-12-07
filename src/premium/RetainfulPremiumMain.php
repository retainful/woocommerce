<?php

namespace Rnoc\Retainful\Premium;

use Rnoc\Retainful\Admin\Settings;

class RetainfulPremiumMain
{
    static $addons = array();
    public $admin;

    function __construct()
    {
        add_action('init', array($this, 'init'));
    }

    /**
     * init the plugin
     * @return bool
     */
    function init()
    {
        $this->initAddon();
        add_filter('rnoc_get_premium_addon_list', array($this, 'getAddonLists'));
        return true;
    }

    /**
     * get the available addon ,list
     * @return array
     */
    function getAddonLists()
    {
        $list = $this->getAvailableAddon();
        return $list;
    }

    /**
     * @return array
     * get the available addon
     */
    static function getAvailableAddon()
    {
        if (!empty(self::$addons)) {
            return self::$addons;
        }
        $path = RNOCPREMIUM_PLUGIN_PATH . 'addons/';
        if ($handle = opendir($path)) {
            $admin = new Settings();
            $plan_details = $admin->getPlanDetails();
            $plan = isset($plan_details['plan']) ? $plan_details['plan'] : 'free';
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $entry != "RetainfulPremiumAddonBase.php") {
                    include $path . $entry;
                    $file_name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $entry);
                    if (class_exists($file_name)) {
                        $class_obj = new $file_name();
                        $addon_plans = $class_obj->plan();
                        if (in_array($plan, $addon_plans)) {
                            self::$addons[] = $class_obj;
                        }
                    }
                }
            }
            closedir($handle);
        }
        return self::$addons;
    }

    /**
     * init the addon
     */
    function initAddon()
    {
        $available_addons = $this->getAvailableAddon();
        foreach ($available_addons as $addon) {
            $addon->init();
        }
    }
}
