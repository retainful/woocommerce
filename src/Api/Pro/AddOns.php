<?php

namespace Rnoc\Retainful\Api\Pro;

use Rnoc\Retainful\Admin\Settings;

class AddOns
{
    protected $handle = 'retainful-popups-script';

    /**
     * pro addons script
     */
    function proAddonsScripts()
    {
        if (!wp_script_is($this->handle, 'enqueued')) {
            wp_enqueue_script($this->handle, $this->getProAddOnsUrl(), array(), RNOC_VERSION, true);
        }
    }

    /**
     * set some script attribute
     * @param $src
     * @param $current_handle
     * @return string
     */
    function addScriptAttr($src, $current_handle)
    {
        if ($current_handle == $this->handle) {
            return $src . " data-rtlattr='true";
        }
        return $src;
    }

    /**
     * un-Clean the url
     * @param $good_protocol_url
     * @param $original_url
     * @param $_context
     * @return string
     */
    function uncleanUrl($good_protocol_url, $original_url, $_context)
    {
        if (false !== strpos($original_url, 'data-rtlattr')) {
            remove_filter('clean_url', 'unclean_url', 10);
            $url_parts = parse_url($good_protocol_url);
            $admin = new Settings();
            $api_key = $admin->getApiKey();
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $email = strval($user->user_email);
                $first_name = strval($user->first_name);
                $user_id = strval($user->ID);
            } else {
                $email = '';
                $first_name = '';
                $user_id = '';
            }
            $attrs = "' data-customer-email='{$email}' data-customer-id='{$user_id}' data-customer-first-name='{$first_name}' data-app_id='{$api_key}' data-cfasync='false";
            return $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . $attrs;
        }
        return $good_protocol_url;
    }

    /**
     * get pro addons url
     * @return mixed|void
     */
    function getProAddOnsUrl()
    {
        return apply_filters('pro_addons_engine_url', "https://js.retainful.com/woocommerce/popup/production/retainful-popups.js");
    }
}