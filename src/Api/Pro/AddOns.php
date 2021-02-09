<?php

namespace Rnoc\Retainful\Api\Pro;

use Rnoc\Retainful\Admin\Settings;

class AddOns
{
    protected $handle = 'retainful-popups-script';

    /**
     * get pro addons url
     * @return mixed|void
     */
    function getProAddOnsUrl()
    {
        return apply_filters('pro_addons_engine_url', "https://js.retainful.com/woocommerce/popup/production/retainful-popups.js?t=" . time());
    }

    /**
     * print the pro popup script in body bottom
     */
    function printProPopupScript()
    {
        $admin = new Settings();
        $api_key = $admin->getApiKey();
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $email = $admin->wc_functions->getCustomerBillingEmail();
            if (empty($email)) {
                $email = strval($user->user_email);
            }
            $first_name = strval($user->first_name);
            $user_id = strval($user->ID);
        } else {
            $email = $admin->wc_functions->getCustomerBillingEmail();
            $first_name = '';
            $user_id = '';
        }
        $params = array(
            'email' => $email,
            'first_name' => $first_name,
            'user_id' => $user_id,
            'api_key' => $api_key,
            'pro_popup_url' => $this->getProAddOnsUrl(),
        );
        include_once plugin_dir_path(RNOC_FILE) . 'src/templates/pro-addons.php';
    }
}