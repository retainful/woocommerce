<?php

namespace Rnoc\Retainful\Api\Referral;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\RestApi;

class ReferralManagement
{
    function printReferralPopup()
    {
        $settings = new Settings();
        $api_key = $settings->getApiKey();
        $rest_api = new RestApi();
        ?>
        <div id="rtl-shopify-init" data-app-key="<?php echo $api_key; ?>"
            <?php
            if (is_user_logged_in()) {
                $secret_key = $settings->getSecretKey();
                $user = wp_get_current_user();
                $order_count = wc_get_customer_order_count($user->ID);
                $total_spent = wc_get_customer_total_spent($user->ID);
                ?>
                data-customer-accepts-marketing="yes"
                data-customer-email="<?php echo rawurlencode($user->user_email); ?>"
                data-customer-first-name="<?php echo rawurlencode($user->user_firstname); ?>"
                data-customer-id="<?php echo rawurlencode($user->ID); ?>"
                data-customer-last-name="<?php echo rawurlencode($user->user_lastname); ?>"
                data-customer-orders-count="<?php echo $order_count ?>>"
                data-customer-tags=""
                data-customer-total-spent="<?php echo $total_spent ?>"
                data-digest="<?php
                echo $rest_api->hashTheData($api_key . 'yes' . $user->user_email . $user->user_firstname . $user->ID . $user->user_lastname . $order_count . $total_spent);
                ?>"
                <?php
            }
            ?>
        >
        </div>
        <?php
    }
}