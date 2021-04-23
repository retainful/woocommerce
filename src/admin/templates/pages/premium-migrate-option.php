<?php
/**
 * @var $is_migrated_to_cloud bool
 */
$migrate = (!$is_migrated_to_cloud) ? "yes" : "no";
if($is_fresh_installation == 0) {
    if (!$is_migrated_to_cloud) {
        ?>
        <div class="card">
            Would you like to migrate premium features to cloud <a
                    href="<?php echo admin_url('admin.php?page=retainful_premium&migrate-to-cloud=' . $migrate); ?>"
                    class="button-green button">yes migrate to cloud</a>
        </div>
        <?php
    } else {
        ?>
        <div class="card">
            Would you like to migrate premium features back to your site <a
                    href="<?php echo admin_url('admin.php?page=retainful_premium&migrate-to-cloud=' . $migrate); ?>"
                    class="button-green button">yes migrate back</a>
        </div>
        <?php
    }
}
if ($is_fresh_installation == 1 || $is_migrated_to_cloud) {
    ?>
    <form id="retainful-settings-form" class="card">
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="rnoc_enable_next_order_coupon">Enable JS for cloud?</label>
                </th>
                <td>
                    <label>
                        <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_pro_js_for_cloud'; ?>" type="radio"
                               id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_pro_js_for_cloud_yes'; ?>"
                               value="yes" <?php if ($is_pro_enabled_for_cloud) {
                            echo "checked";
                        } ?>>
                        <?php esc_html_e('Yes', RNOC_TEXT_DOMAIN); ?>
                    </label>
                    <label>
                        <input name="<?php echo RNOC_PLUGIN_PREFIX . 'enable_pro_js_for_cloud'; ?>" type="radio"
                               id="<?php echo RNOC_PLUGIN_PREFIX . 'enable_pro_js_for_cloud_no'; ?>"
                               value="no" <?php if (!$is_pro_enabled_for_cloud) {
                            echo "checked";
                        } ?>>
                        <?php esc_html_e('No', RNOC_TEXT_DOMAIN); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th>
                </th>
                <td>
                    <button type="submit" data-action="rnoc_save_enable_pro_js"
                            data-security="<?php echo wp_create_nonce('rnoc_save_enable_pro_js') ?>"
                            class="button button-primary"><?php esc_html_e('save', RNOC_TEXT_DOMAIN); ?></button>
                </td>
            </tr>
            </tbody>
        </table>
    </form>
    <?php
}
