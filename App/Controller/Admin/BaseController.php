<?php

namespace Rnoc\App\Controller\Admin;

if (!defined('ABSPATH')) exit;

use Rnoc\App\Helpers\Input;
use Rnoc\App\Modules\AbandonedCart\RestApi;
use Rnoc\App\Modules\Integrations\MultiLingual;
use Rnoc\App\library\RetainfulApi;
use Rnoc\App\Helpers\WcFunctions;
use Rnoc\App\Helpers\Settings as SettingHelper;

class BaseController
{
    public static $slug = 'retainful';


    /**
     * Check connection is active or not.
     *
     * @return bool
     */
    public static function isConnectionActive()
    {
        $secret_key = self::getSecretKey();
        $app_id = self::getSecretKey();
        if (self::isAppConnected() && !empty($secret_key) && !empty($app_id)) {
            return true;
        }
        return false;
    }

    /**
     * Check fo entered API key is valid or not
     * @return bool
     */
    public static function isAppConnected()
    {
        self::storeDetails('68b30adc-00fd-4196-a426-494384b50c63', 'd3177c054689e5df671893ec0369a28b');
        $is_connected = SettingHelper::get(RNOC_PLUGIN_PREFIX . 'is_retainful_connected', 'retainful_license');
        return !empty($is_connected);
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    public static function getApiKey()
    {
        $apikey = SettingHelper::get(RNOC_PLUGIN_PREFIX . 'retainful_app_id', 'retainful_license');
        return !empty($apikey) ? $apikey : NULL;
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    public static function getSecretKey()
    {
        $apikey = SettingHelper::get(RNOC_PLUGIN_PREFIX . 'retainful_app_id', 'retainful_license');
        return !empty($apikey) ? $apikey : NULL;
    }

    /**
     * License settings
     * @return mixed|void
     */
    public static function getLicenseDetails()
    {
        return get_option(self::$slug . '_license', array());
    }


    /**
     * webhook delivery url
     * @return string
     */
    public static function getDeliveryUrl()
    {
        return RetainfulApi::getDomain() . 'woocommerce/webhooks/checkout';
    }


    /**
     * update user as Free user
     */
    public static function updateUserAsFreeUser()
    {
        $details = RetainfulApi::getPlanDetails();
        self::updatePlanDetails($details);
    }

    /**
     * update the plan details
     * @param array $details
     */
    public static function updatePlanDetails($details = array())
    {
        update_option('rnoc_plan_details', $details);
        update_option('rnoc_last_plan_checked', current_time('timestamp'));
    }

    /**
     * Check fo entered API key is valid or not
     * @param string $api_key
     * @param string $secret_key
     * @param string $store_data
     * @return bool|array
     */
    public static function isApiEnabled($api_key = "", $secret_key = NULL, $store_data = NULL)
    {
        if (empty($api_key)) {
            $api_key = self::getApiKey();
        }
        if (empty($secret_key)) {
            $secret_key = self::getSecretKey();
        }
        if (empty($store_data)) {
            $store_data = self::storeDetails($api_key, $secret_key);
        }
        if (!empty($api_key)) {
            if ($details = RetainfulApi::validateApi($api_key, $store_data)) {
                if (empty($details) || is_string($details)) {
                    self::updateUserAsFreeUser();
                    return array('error' => $details);
                } else {
                    self::updatePlanDetails($details);
                    return array('success' => isset($details['message']) ? $details['message'] : NULL);
                }
            } else {
                self::updateUserAsFreeUser();
                return false;
            }
        } else {
            self::updateUserAsFreeUser();
            return false;
        }
    }

    /**
     * Get the store details
     * @param $api_key
     * @param $secret_key
     * @return array
     */
    public static function storeDetails($api_key, $secret_key)
    {
        $scheme = wc_site_is_https() ? 'https' : 'http';
        $country_code = WcFunctions::getStoreCountry();
        $state_code = WcFunctions::getStoreState();
        $default_language = MultiLingual::getDefaultLanguage();
        $details = array(
            'woocommerce_app_id' => $api_key,
            'secret_key' => RestApi::encryptData($api_key, $secret_key),
            'id' => NULL,
            'name' => SettingHelper::getData('blogname'),
            'email' => SettingHelper::getData('admin_email'),
            'domain' => get_home_url(null, null, $scheme),
            'address1' => SettingHelper::getData('woocommerce_store_address', NULL),
            'address2' => SettingHelper::getData('woocommerce_store_address_2', NULL),
            'currency' => self::getBaseCurrency(),
            'city' => SettingHelper::getData('woocommerce_store_city', NULL),
            'zip' => SettingHelper::getData('woocommerce_store_postcode', NULL),
            'country' => NULL,
            'timezone' => self::getSiteTimeZone(),
            'weight_unit' => SettingHelper::getData('woocommerce_weight_unit'),
            'country_code' => $country_code,
            'province_code' => $state_code,
            'force_ssl' => (SettingHelper::getData('woocommerce_force_ssl_checkout', 'no') == 'yes'),
            'enabled_presentment_currencies' => self::getAllAvailableCurrencies(),
            'primary_locale' => $default_language
        );
        return $details;
    }

    /**
     * Check the site has multi currency
     * @return bool
     */
    public static function getBaseCurrency()
    {
        $base_currency = WcFunctions::getDefaultCurrency();
        return apply_filters('rnoc_get_default_currency_code', $base_currency);
    }


    /**
     * Get the timezone of the site
     * @return mixed|void
     */
    public static function getSiteTimeZone()
    {
        $time_zone = get_option('timezone_string');
        if (empty($time_zone)) {
            $time_zone = get_option('gmt_offset');
        }
        return $time_zone;
    }


    /**
     * Check the site has multi currency
     * @return bool
     */
    public static function getAllAvailableCurrencies()
    {
        $base_currency = WcFunctions::getDefaultCurrency();
        $currencies = array($base_currency);
        return apply_filters('rnoc_get_available_currencies', $currencies);
    }

    /**
     * Create log file named retainful.log
     * @param $message
     * @param $log_in_as
     */
    public static function logMessage($message, $log_in_as = "checkout")
    {
        $admin_settings = Settings::getAdminSettings();
        if (isset($admin_settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log']) && !empty($admin_settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log']) && !empty($message)) {
            try {
                if (is_array($message) || is_object($message)) {
                    $message = json_encode($message);
                }
                $to_print = $log_in_as . ":\n";
                $to_print .= $message;
                $file = fopen(RNOC_LOG_FILE_PATH, 'a');
                $content = "\n\n Time :" . current_time('mysql', true) . ' | ' . $to_print;
                fwrite($file, $content);
                fclose($file);
            } catch (\Exception $e) {
                $e->getMessage();
            }
        }
    }

}