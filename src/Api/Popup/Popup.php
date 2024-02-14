<?php

namespace Rnoc\Retainful\Api\Popup;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\WcFunctions;

class Popup
{
    /**
     * Get popup url
     *
     * @return mixed|null
     */
    function getPopupJs()
    {
        //https://js.retainful.com/woocommerce/v2/popup/beta/poup-widget.beta.js
        return apply_filters('rnoc_popup_js','https://js.retainful.com/woocommerce/v2/popup/production/poup-widget.js?t='.time());
    }

    /**
     * add popup script.
     *
     * @return void
     */
    function addPopupScripts()
    {
        $settings = new Settings();
        if($settings->needPopupWidget()){
            wp_enqueue_script(RNOC_PLUGIN_PREFIX . 'popups', $this->getPopupJs(), array('jquery'), RNOC_VERSION, true);
        }
    }

    function userRegister($user_id)
    {
        if(!empty($user_id)){
            $user = get_user_by('id',$user_id);
            if(is_object($user) && !empty($user->user_email)){
                $settings = new Settings();
                $settings->setIdentity($user->user_email);
            }
        }
    }

    function userLogin($user_name, $user)
    {
        if(is_object($user) && !empty($user->user_email)){
            $settings = new Settings();
            $settings->setIdentity($user->user_email);
        }
    }

    function changeIdentityPath($option,$name,$value)
    {
        if(!is_admin() && !empty($option['path']) && $name == '_wc_rnoc_tk_session'){
            $settings = new Settings();
            $option['path'] = $settings->getIdentityPath();
        }
        return $option;
    }

    /**
     * Print popup.
     *
     * @return void
     */
    function printPopup()
    {
        $admin = new Settings();
        $wc = new WcFunctions();
        $api_key = $admin->getApiKey();
        $secret = $admin->getSecretKey();
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_arr = array(
                'api_key' => $api_key,
                'email' => strval($user->user_email),
            );
        } else {
            $user_arr = array(
                'api_key' => $api_key,
                'email' => '',
            );
        }
        $data = implode('', $user_arr);
        $digest = hash_hmac('sha256', $data, $secret);

        $default_params = array(
            'digest' => $digest,
            'email' => '',
            'api_key' => '',
            'path' => $admin->getIdentityPath(),
            'domain' => COOKIE_DOMAIN
        );
        $params = wp_parse_args($user_arr, $default_params);
        include_once plugin_dir_path(RNOC_FILE) . 'src/templates/popup.php';
    }
}