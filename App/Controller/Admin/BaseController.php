<?php

namespace Rnoc\App\Controller\Admin;

if (!defined('ABSPATH')) exit;

use Rnoc\App\Helpers\Input;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;
use Rnoc\Retainful\Integrations\MultiLingual;
use Rnoc\Retainful\library\RetainfulApi;
use Rnoc\App\Helpers\WcFunctions;

class BaseController
{
    public $slug = 'retainful', $api, $wc_functions;
    public static $input = null;

    /**
     * Settings constructor.
     */
    function __construct()
    {
        $this->api = new RetainfulApi();
        $this->wc_functions = new WcFunctions();
        if (is_null(self::$input)) {
            self::$input = new Input();
        }
    }

    /**
     * Check connection is active or not.
     *
     * @return bool
     */
    function isConnectionActive()
    {
        $secret_key = $this->getSecretKey();
        $app_id = $this->getApiKey();
        if ($this->isAppConnected() && !empty($secret_key) && !empty($app_id)) {
            return true;
        }
        return false;
    }

    /**
     * Check fo entered API key is valid or not
     * @return bool
     */
    function isAppConnected()
    {
        $settings = $this->getLicenseDetails();
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'is_retainful_connected']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'])) {
            return true;
        }
        return false;
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    function getApiKey()
    {
        $settings = $this->getLicenseDetails();
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id'])) {
            return $settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id'];
        }
        return NULL;
    }

    /**
     * Get Admin API key
     * @return String|null
     */
    function getSecretKey()
    {
        $settings = $this->getLicenseDetails();
        if (!empty($settings) && isset($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_secret']) && !empty($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_secret'])) {
            return $settings[RNOC_PLUGIN_PREFIX . 'retainful_app_secret'];
        }
        return NULL;
    }

    /**
     * License settings
     * @return mixed|void
     */
    function getLicenseDetails()
    {
        return get_option($this->slug . '_license', array());
    }

    /**
     * get woocommerce plugin url
     * @return string|null
     */
    function getWooPluginUrl()
    {
        if (function_exists('WC')) {
            return WC()->plugin_url();
        }
        return NULL;
    }

    /**
     * webhook delivery url
     * @return string
     */
    function getDeliveryUrl()
    {
        return $this->api->getDomain() . 'woocommerce/webhooks/checkout';
    }


    /**
     * update user as Free user
     */
    function updateUserAsFreeUser()
    {
        $details = $this->api->getPlanDetails();
        $this->updatePlanDetails($details);
    }

    /**
     * update the plan details
     * @param array $details
     */
    function updatePlanDetails($details = array())
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
    function isApiEnabled($api_key = "", $secret_key = NULL, $store_data = NULL)
    {
        if (empty($api_key)) {
            $api_key = $this->getApiKey();
        }
        if (empty($secret_key)) {
            $secret_key = $this->getSecretKey();
        }
        if (empty($store_data)) {
            $store_data = $this->storeDetails($api_key, $secret_key);
        }
        if (!empty($api_key)) {
            if ($details = $this->api->validateApi($api_key, $store_data)) {
                if (empty($details) || is_string($details)) {
                    $this->updateUserAsFreeUser();
                    return array('error' => $details);
                } else {
                    $this->updatePlanDetails($details);
                    return array('success' => isset($details['message']) ? $details['message'] : NULL);
                }
            } else {
                $this->updateUserAsFreeUser();
                return false;
            }
        } else {
            $this->updateUserAsFreeUser();
            return false;
        }
    }

    /**
     * Get the store details
     * @param $api_key
     * @param $secret_key
     * @return array
     */
    function storeDetails($api_key, $secret_key)
    {
        $scheme = wc_site_is_https() ? 'https' : 'http';
        $country_details = get_option('woocommerce_default_country');
        list($country_code, $state_code) = explode(':', $country_details);
        $lang_helper = new MultiLingual();
        $default_language = $lang_helper->getDefaultLanguage();
        $api_obj = new RestApi();
        $details = array(
            'woocommerce_app_id' => $api_key,
            'secret_key' => $api_obj->encryptData($api_key, $secret_key),
            'id' => NULL,
            'name' => get_option('blogname'),
            'email' => get_option('admin_email'),
            'domain' => get_home_url(null, null, $scheme),
            'address1' => get_option('woocommerce_store_address', NULL),
            'address2' => get_option('woocommerce_store_address_2', NULL),
            'currency' => $this->getBaseCurrency(),
            'city' => get_option('woocommerce_store_city', NULL),
            'zip' => get_option('woocommerce_store_postcode', NULL),
            'country' => NULL,
            'timezone' => $this->getSiteTimeZone(),
            'weight_unit' => get_option('woocommerce_weight_unit'),
            'country_code' => $country_code,
            'province_code' => $state_code,
            'force_ssl' => (get_option('woocommerce_force_ssl_checkout', 'no') == 'yes'),
            'enabled_presentment_currencies' => $this->getAllAvailableCurrencies(),
            'primary_locale' => $default_language
        );
        return $details;
    }

    /**
     * Check the site has multi currency
     * @return bool
     */
    function getBaseCurrency()
    {
        $base_currency = $this->wc_functions->getDefaultCurrency();
        return apply_filters('rnoc_get_default_currency_code', $base_currency);
    }


    /**
     * Get the timezone of the site
     * @return mixed|void
     */
    function getSiteTimeZone()
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
    function getAllAvailableCurrencies()
    {
        $base_currency = $this->wc_functions->getDefaultCurrency();
        $currencies = array($base_currency);
        return apply_filters('rnoc_get_available_currencies', $currencies);
    }

    /**
     * Create log file named retainful.log
     * @param $message
     * @param $log_in_as
     */
    function logMessage($message, $log_in_as = "checkout")
    {
        $admin_settings = $this->getAdminSettings();
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