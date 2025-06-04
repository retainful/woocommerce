<?php
/**
 * @var $settings array
 */
require_once "tabs.php";
?>
<form id="retainful-settings-form" class="card">
    <table class="form-table" role="presentation">
        <tbody>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'); ?>"><?php
                    esc_html_e('Cart tracking engine?', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_tracking_engine_js'); ?>"
                           value="js" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'] == 'js') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('JavaScript (Default,Recommended)', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_tracking_engine_php'); ?>"
                           value="php" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'] == 'php') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('PHP', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'); ?>"><?php
                    esc_html_e('Use only webhooks for tracking the order events in the background', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_background_order_sync_yes'); ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_background_order_sync_no'); ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_background_order_sync'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <p class="description">
                    <?php
                    $scheduled_action = admin_url('tools.php?page=action-scheduler');
                    /* translators: %s: Real time tracking url */
                    echo wp_kses_post(sprintf(__("Turning on this option will stop real-time tracking of order activities like order placement. Instead, the Retainful API will rely on WooCommerce's Webhooks to send the order information as a background task.<br>Remember: WooCommerce uses the %s to execute webhooks in the background for this. So, make sure your cron setup is correct. If cron isn't working, order details won't reach Retainful API, and your workflow triggers won't work right.", 'retainful-next-order-coupon-for-woocommerce'),"<a target='_blank' href='".esc_url($scheduled_action)."'>".esc_html__("Scheduled Actions","retainful-next-order-coupon-for-woocommerce")."</a>"));                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'); ?>"><?php
                    esc_html_e('Track Zero value carts / orders', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'track_zero_value_carts_yes'); ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'track_zero_value_carts_no'); ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'); ?>"><?php
                    esc_html_e('Consider On-Hold order status as abandoned cart?', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'); ?>"
                           type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status_1'); ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'); ?>"
                           type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status_0'); ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'); ?>"><?php
                    esc_html_e('Consider Canceled order status as abandoned cart?', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'); ?>"
                           type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status_1'); ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'); ?>"
                           type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status_0'); ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'); ?>"><?php
                    esc_html_e('Consider failed order status as abandoned cart?', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'); ?>"
                           type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status_1'); ?>"
                           value="1" <?php if (isset($settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status']) && $settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'); ?>"
                           type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status_0'); ?>"
                           value="0" <?php if (isset($settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status']) && $settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'); ?>"><?php
                    esc_html_e('Fix for Cart sync not working', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load_1'); ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load_0'); ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <p class="description">
                    <?php
                    esc_html_e('Enable this option only when you dont see your carts in Retainful dashboard ', 'retainful-next-order-coupon-for-woocommerce');
                    ?>
                </p>
            </td>
        </tr>
        <tr >
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup'); ?>"><?php
                    esc_html_e('Enable Signup Forms / Popups', 'retainful-next-order-coupon-for-woocommerce');
                    ?>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup_no'); ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup_yes'); ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_dynamic_popup'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <p class="description">
                    <?php
                    esc_html_e('Enable this option if you are using the signup forms / popups in Retainful to build your audience list.', 'retainful-next-order-coupon-for-woocommerce');
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'); ?>"><?php
                    esc_html_e('Marketing Consent', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance_0'); ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Implicit', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance_1'); ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Explicit', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <p class="description">
                    <?php
                    esc_html_e('If set to Explicit, it will add an opt-in checkbox at the checkout to capture the consent', 'retainful-next-order-coupon-for-woocommerce');
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'gdpr_display_position'); ?>"><?php
                    esc_html_e('Consent field position:', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <select name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'gdpr_display_position'); ?>">
                    <option value="after_billing_email" <?php echo  ($settings[RNOC_PLUGIN_PREFIX . 'gdpr_display_position'] == 'after_billing_email') ? "selected='selected'":''; ?>>
                        <?php esc_html_e('Below Email Address field', 'retainful-next-order-coupon-for-woocommerce'); ?></option>
                    <option value="after_term_and_condition" <?php echo  ($settings[RNOC_PLUGIN_PREFIX . 'gdpr_display_position'] == 'after_term_and_condition') ? "selected='selected'":''; ?>>
                        <?php esc_html_e('Below Terms and Conditions section', 'retainful-next-order-coupon-for-woocommerce'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_capture_msg'); ?>"><?php
                    esc_html_e('Text for the opt-in checkbox', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <textarea name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_capture_msg'); ?>"
                          id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_capture_msg'); ?>" cols="60" rows="10"
                ><?php echo esc_attr(trim($settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'])); ?>
                </textarea>
                <p class="description">
                    <?php
                    esc_html_e('Under GDPR, it is mandatory to inform the users when we track their cart activity in real-time.', 'retainful-next-order-coupon-for-woocommerce');
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_sms_consent'); ?>"><?php
			        esc_html_e('Phone Consent', 'retainful-next-order-coupon-for-woocommerce');
			        ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_sms_consent'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_sms_consent_0'); ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_sms_consent'] == '0') {
				        echo "checked";
			        } ?>>
			        <?php esc_html_e('Implicit', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_sms_consent'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_sms_consent_1'); ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_sms_consent'] == '1') {
				        echo "checked";
			        } ?>>
			        <?php esc_html_e('Explicit', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <p class="description">
			        <?php
			        esc_html_e('If set to Explicit, it will add an opt-in checkbox at the checkout to capture the consent', 'retainful-next-order-coupon-for-woocommerce');
			        ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'sms_consent_display_position'); ?>"><?php esc_html_e('Phone Consent field position:', 'retainful-next-order-coupon-for-woocommerce');
			        ?></label>
            </th>
            <td>
                <select name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'sms_consent_display_position'); ?>">
                    <option value="after_billing_email" <?php echo  ($settings[RNOC_PLUGIN_PREFIX . 'sms_consent_display_position'] == 'after_billing_email') ? "selected='selected'":''; ?>>
				        <?php esc_html_e('Below Email Address field', 'retainful-next-order-coupon-for-woocommerce'); ?></option>
                    <option value="after_term_and_condition" <?php echo  ($settings[RNOC_PLUGIN_PREFIX . 'sms_consent_display_position'] == 'after_term_and_condition') ? "selected='selected'":''; ?>>
				        <?php esc_html_e('Below Terms and Conditions section', 'retainful-next-order-coupon-for-woocommerce'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'cart_capture_msg'); ?>"><?php
			        esc_html_e('Phone Text for the opt-in checkbox', 'retainful-next-order-coupon-for-woocommerce');
			        ?></label>
            </th>
            <td>
              <textarea name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'sms_capture_msg'); ?>"
                        id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'sms_capture_msg'); ?>" cols="60" rows="10"
              ><?php echo esc_attr(trim(isset($settings[RNOC_PLUGIN_PREFIX . 'sms_capture_msg'])) ? $settings[RNOC_PLUGIN_PREFIX . 'sms_capture_msg'] : '' ); ?>
                </textarea>
                <p class="description">
			        <?php
			        esc_html_e('Under GDPR, it is mandatory to inform the users when we track their cart activity in real-time.', 'retainful-next-order-coupon-for-woocommerce');
			        ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_ip_filter'); ?>"><?php
                    esc_html_e('Enable IP filter?', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_ip_filter'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_ip_filter_1'); ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_ip_filter'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_ip_filter_0'); ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'); ?>"><?php
                    esc_html_e('Exclude capturing carts from these IP\'s', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <textarea name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'); ?>"
                          id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'); ?>" cols="60" rows="10"
                ><?php echo esc_attr(trim($settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'])); ?>
                </textarea>
                <p class="description">
                    <?php
                    esc_html_e('The plugin will not track carts from these IP\'s . Enter IP in comma seperated format . Example 192.168.1.10,192.168.1.11 . Alternatively you can also use 192.168 .* , 192.168.10 .*, 192.168.1.1 - 192.168.1.255', 'retainful-next-order-coupon-for-woocommerce');
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_debug_log'); ?>"><?php
                    esc_html_e('Enable debug log?', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_debug_log'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_debug_log_1'); ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_debug_log'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_debug_log_0'); ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'handle_storage_using'); ?>"><?php
                    esc_html_e('Session handler', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'handle_storage_using'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'handle_storage_using_woocommerce'); ?>"
                           value="woocommerce" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'handle_storage_using'] == 'woocommerce') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('WooCommerce session (Default)', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'handle_storage_using'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'handle_storage_using_cookie'); ?>"
                           value="cookie" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'handle_storage_using'] == 'cookie') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Cookie', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <p class="description">
                    <?php
                    esc_html_e('DO NOT change this setting unless you are instructed by the Retainful Support team. WooCommerce session will work for 99% of the shops.', 'retainful-next-order-coupon-for-woocommerce');
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_afterpay_action'); ?>"><?php
                    esc_html_e('Enable AfterPay payment gateway support (only enable this option if you installed AfterPay plugin) ?', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_afterpay_action'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_afterpay_action_yes'); ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_afterpay_action'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_afterpay_action'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'enable_afterpay_action_no'); ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_afterpay_action'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'varnish_check'); ?>"><?php
                    esc_html_e('Varnish Cache Compatibility', 'retainful-next-order-coupon-for-woocommerce');
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'varnish_check'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'varnish_check'); ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'varnish_check'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>
                <label>
                    <input name="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'varnish_check'); ?>" type="radio"
                           id="<?php echo esc_attr(RNOC_PLUGIN_PREFIX . 'varnish_check'); ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'varnish_check'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', 'retainful-next-order-coupon-for-woocommerce'); ?>
                </label>

                <p class="description">
                    <?php
                    esc_html_e('DO NOT change this setting unless you are instructed by the Retainful Support team. Use this option only when you are using the server side caching with Varnish. Certain features may not work if enabled. So check with the Support team before enabling this option', 'retainful-next-order-coupon-for-woocommerce');
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th>
            </th>
            <td>
                <button type="submit" data-action="rnoc_save_settings"
                        data-security="<?php echo esc_attr(wp_create_nonce('rnoc_save_settings')) ?>"
                        class="button button-primary"><?php esc_html_e('save', 'retainful-next-order-coupon-for-woocommerce'); ?></button>
            </td>
        </tr>
        </tbody>
    </table>
    <button type="submit" data-action="rnoc_save_settings"
            data-security="<?php echo esc_attr(wp_create_nonce('rnoc_save_settings')) ?>"
            class="button button-primary button-right-fixed"><i
                class="dashicons dashicons-yes"></i>&nbsp;&nbsp;<span><?php esc_html_e('save', 'retainful-next-order-coupon-for-woocommerce'); ?></span>
    </button>
</form>
