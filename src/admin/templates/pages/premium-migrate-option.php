<?php
/**
 * @var $is_migrated_to_cloud bool
 */
$migrate = (!$is_migrated_to_cloud) ? "yes" : "no";
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