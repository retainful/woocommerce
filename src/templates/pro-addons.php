<?php
/**
 * @var $params array
 */
?>
<script>
    !function (e, c) {
        !function (e) {
            const o = c.createElement("script");
            o.type = "application/javascript", o.src = e,o.setAttribute("data-app_id", "<?php echo $params['api_key'] ?>"),o.setAttribute("id", "retainful-popups-script-js"),o.setAttribute("data-cfasync", "false"),o.setAttribute("data-customer-first-name", "<?php echo esc_attr__($params['first_name']); ?>"),o.setAttribute("data-customer-id", "<?php echo $params['user_id'] ?>"),o.setAttribute("data-customer-email", "<?php echo esc_attr__($params['email']) ?>"), c.body.appendChild(o)
        }("<?php echo $params['pro_popup_url'] ?>")
    }(window, document);
</script>