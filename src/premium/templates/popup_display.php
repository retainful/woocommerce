<div id="<?php echo esc_attr($add_on_slug); ?>" class="rnoc-popup-modal">
    <span class="close-rnoc-popup">&times;</span>
    <div class="rnoc-popup-modal-content">
        <?php
        echo wp_kses_post($template);
        ?>
    </div>
</div>
<script>
    var rnoc_ajax_url = '<?php echo esc_url(admin_url('admin-ajax.php'));?>';
    var no_thanks_action = <?php echo $no_thanks_action  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped	?>;
    var is_email_manditory = <?php echo $is_email_mandatory //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped	?>;
</script>
<style>
    <?php echo wp_kses_post($custom_style) ?>
</style>