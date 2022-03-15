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
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'; ?>"><?php
                    esc_html_e('Cart tracking engine?', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'cart_tracking_engine_js'; ?>"
                           value="js" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'] == 'js') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('JavaScript (Default,Recommended)', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'cart_tracking_engine_php'; ?>"
                           value="php" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'cart_tracking_engine'] == 'php') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('PHP', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'; ?>"><?php
                    esc_html_e('Track Zero value carts / orders', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'track_zero_value_carts_yes'; ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'track_zero_value_carts_no'; ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'track_zero_value_carts'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'; ?>"><?php
                    esc_html_e('Consider On-Hold order status as abandoned cart?', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'; ?>"
                           type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status_1'; ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'; ?>"
                           type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status_0'; ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'; ?>"><?php
                    esc_html_e('Consider Canceled order status as abandoned cart?', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'; ?>"
                           type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status_1'; ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'; ?>"
                           type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status_0'; ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'consider_cancelled_as_abandoned_status'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'; ?>"><?php
                    esc_html_e('Consider failed order status as abandoned cart?', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'; ?>"
                           type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status_1'; ?>"
                           value="1" <?php if (isset($settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status']) && $settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'; ?>"
                           type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status_0'; ?>"
                           value="0" <?php if (isset($settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status']) && $settings[RNOC_PLUGIN_PREFIX . 'consider_failed_as_abandoned_status'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'; ?>"><?php
                    esc_html_e('Fix for Cart sync not working', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load_1'; ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load_0'; ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'refresh_fragments_on_page_load'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
                <p class="description">
                    <?php
                    esc_html_e('Enable this option only when you dont see your carts in Retainful dashboard ', RNOC_TEXT_DOMAIN);
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_referral_widget'; ?>"><?php
                    esc_html_e('Enable Referral program for your store?', RNOC_TEXT_DOMAIN);
                    ?> <span class="premium-label">Premium</span></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_referral_widget'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_referral_widget_yes'; ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_referral_widget'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_referral_widget'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_referral_widget_no'; ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_referral_widget'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
                <p class="description">
                    <?php
                    esc_html_e('You should also enable and configure the referral program in your Retainful dashboard.', RNOC_TEXT_DOMAIN);
                    ?>
                </p>
            </td>
        </tr>
        <tr style="display: none;">
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_popup_widget'; ?>"><?php
                    esc_html_e('Enable Popup for your store?', RNOC_TEXT_DOMAIN);
                    ?> <span class="premium-label">Premium</span></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_popup_widget'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_popup_widget_yes'; ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_popup_widget'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_popup_widget'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_popup_widget_no'; ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_popup_widget'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
                <p class="description">
                    <?php
                    esc_html_e('You should also enable and configure the popup widget in your Retainful dashboard.', RNOC_TEXT_DOMAIN);
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget'; ?>"><?php
                    esc_html_e('Show unique referral link in my account page for logged in customers?', RNOC_TEXT_DOMAIN);
                    ?> <span class="premium-label">Premium</span></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget_yes'; ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget_no'; ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_embeded_referral_widget'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'; ?>"><?php
                    esc_html_e('Enable GDPR Compliance?', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance_1'; ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance_0'; ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_gdpr_compliance'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'cart_capture_msg'; ?>"><?php
                    esc_html_e('Compliance Message', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <textarea name="<?php echo RNOC_PLUGIN_PREFIX . 'cart_capture_msg'; ?>"
                          id="<?php echo RNOC_PLUGIN_PREFIX . 'cart_capture_msg'; ?>" cols="60" rows="10"
                ><?php echo rnocEscAttr(trim($settings[RNOC_PLUGIN_PREFIX . 'cart_capture_msg'])); ?>
                </textarea>
                <p class="description">
                    <?php
                    esc_html_e('Under GDPR, it is mandatory to inform the users when we track their cart activity in real-time.', RNOC_TEXT_DOMAIN);
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_ip_filter'; ?>"><?php
                    esc_html_e('Enable IP filter?', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_ip_filter'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_ip_filter_1'; ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_ip_filter'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_ip_filter_0'; ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_ip_filter'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'; ?>"><?php
                    esc_html_e('Exclude capturing carts from these IP\'s', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <textarea name="<?php echo RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'; ?>"
                          id="<?php echo RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'; ?>" cols="60" rows="10"
                ><?php echo rnocEscAttr(trim($settings[RNOC_PLUGIN_PREFIX . 'ignored_ip_addresses'])); ?>
                </textarea>
                <p class="description">
                    <?php
                    esc_html_e('The plugin will not track carts from these IP\'s . Enter IP in comma seperated format . Example 192.168.1.10,192.168.1.11 . Alternatively you can also use 192.168 .* , 192.168.10 .*, 192.168.1.1 - 192.168.1.255', RNOC_TEXT_DOMAIN);
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'enable_debug_log'; ?>"><?php
                    esc_html_e('Enable debug log?', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_debug_log'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_debug_log_1'; ?>"
                           value="1" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log'] == '1') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_debug_log'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_debug_log_0'; ?>"
                           value="0" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'enable_debug_log'] == '0') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'handle_storage_using'; ?>"><?php
                    esc_html_e('Session handler', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'handle_storage_using'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'handle_storage_using_woocommerce'; ?>"
                           value="woocommerce" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'handle_storage_using'] == 'woocommerce') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('WooCommerce session (Default)', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'handle_storage_using'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'handle_storage_using_cookie'; ?>"
                           value="cookie" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'handle_storage_using'] == 'cookie') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Cookie', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'handle_storage_using'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'handle_storage_using_php'; ?>"
                           value="php" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'handle_storage_using'] == 'php') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('PHP Session', RNOC_TEXT_DOMAIN); ?>
                </label>
                <p class="description">
                    <?php
                    esc_html_e('DO NOT change this setting unless you are instructed by the Retainful Support team. WooCommerce session will work for 99% of the shops.', RNOC_TEXT_DOMAIN);
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'varnish_check'; ?>"><?php
                    esc_html_e('Varnish Cache Compatibility', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'varnish_check'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'varnish_check'; ?>"
                           value="yes" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'varnish_check'] == 'yes') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                </label>
                <label>
                    <input name="<?php echo RNOC_PLUGIN_PREFIX . 'varnish_check'; ?>" type="radio"
                           id="<?php echo RNOC_PLUGIN_PREFIX . 'varnish_check'; ?>"
                           value="no" <?php if ($settings[RNOC_PLUGIN_PREFIX . 'varnish_check'] == 'no') {
                        echo "checked";
                    } ?>>
                    <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                </label>

                <p class="description">
                    <?php
                    esc_html_e('DO NOT change this setting unless you are instructed by the Retainful Support team. Use this option only when you are using the server side caching with Varnish. Certain features may not work if enabled. So check with the Support team before enabling this option', RNOC_TEXT_DOMAIN);
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th>
            </th>
            <td>
                <button type="submit" data-action="rnoc_save_settings"
                        data-security="<?php echo wp_create_nonce('rnoc_save_settings') ?>"
                        class="button button-primary"><?php esc_html_e('save', RNOC_TEXT_DOMAIN); ?></button>
            </td>
        </tr>
        </tbody>
    </table>
    <button type="submit" data-action="rnoc_save_settings"
            data-security="<?php echo wp_create_nonce('rnoc_save_settings') ?>"
            class="button button-primary button-right-fixed"><i
                class="dashicons dashicons-yes"></i>&nbsp;&nbsp;<span><?php esc_html_e('save', RNOC_TEXT_DOMAIN); ?></span>
    </button>
</form>