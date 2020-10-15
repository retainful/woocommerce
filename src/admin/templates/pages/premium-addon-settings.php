<?php
/**
 * @var $settings array
 * @var $available_addon_list array
 * @var $base_url string
 * @var $add_on_slug string
 */
require_once "tabs.php";
?>
<form id="retainful-settings-form">
    <div class="card" style="width: 25%;float: left;padding: 0;">
        <div class="common-errors">

        </div>
        <?php
        if (!empty($available_addon_list)) {
            ?>
            <ul class="rnoc-premium-addon-nav">
                <li>
                    <a class="" href="<?php echo $base_url; ?>"><i class="dashicons dashicons-admin-plugins"></i>&nbsp;Add-ons
                        list</a>
                </li>
                <?php
                foreach ($available_addon_list as $addon) {
                    $title = $addon->title();
                    if (empty($title)) {
                        continue;
                    }
                    $slug = $addon->slug();
                    ?>
                    <li class="<?php if ($slug == $add_on_slug) {
                        echo "active";
                    } ?>">
                        <a class=""
                           href="<?php echo add_query_arg(array('add-on' => $slug), $base_url) ?>"
                        ><i class="dashicons <?php echo $addon->icon(); ?>"></i>&nbsp;<?php echo $title; ?>
                        </a>
                    </li>
                    <?php
                }
                ?>
            </ul>
            <?php
        }
        ?>
    </div>
    <div class="card" style="width: 74%;float: left;margin-left: 5px;">
        <button type="submit" data-action="rnoc_save_premium_addon_settings"
                data-security="<?php echo wp_create_nonce('rnoc_save_premium_addon_settings') ?>"
                class="button button-primary button-right-fixed"><i
                    class="dashicons dashicons-yes"></i>&nbsp;&nbsp;<span><?php esc_html_e('save', RNOC_TEXT_DOMAIN); ?></span>
        </button>
        <?php
        do_action('rnoc_premium_addon_settings_page_' . $add_on_slug, $settings, $base_url, $add_on_slug);
        ?>
        <table class="form-table">
            <tbody>
            <tr>
                <th>
                </th>
                <td>
                    <button type="submit" data-action="rnoc_save_premium_addon_settings"
                            data-security="<?php echo wp_create_nonce('rnoc_save_premium_addon_settings') ?>"
                            class="button button-primary"><?php esc_html_e('save', RNOC_TEXT_DOMAIN); ?></button>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</form>