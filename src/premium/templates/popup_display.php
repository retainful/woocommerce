<div id="<?php echo $add_on_slug; ?>" class="rnoc-popup-modal">
    <span class="close-rnoc-popup">&times;</span>
    <div class="rnoc-popup-modal-content">
        <?php
        echo $template;
        ?>
    </div>
</div>
<script>
    var rnoc_ajax_url = '<?php echo admin_url('admin-ajax.php');?>';
    var no_thanks_action = <?php echo $no_thanks_action ?>;
    var is_email_manditory = <?php echo $is_email_mandatory ?>;
</script>
<style>
    <?php echo $custom_style ?>
</style>