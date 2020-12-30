<?php
/**
 * @var $params array
 */
?>
<script>
    !function (e, c) {
        !function (e) {
            const o = c.createElement("script");
            o.type = "application/javascript", o.src = e,o.setAttribute("data-app_id", "<?php echo $params['api_key'] ?>"),o.setAttribute("data-cfasync", "false"),o.setAttribute("data-customer-first-name", "<?php echo wp_json_encode($params['first_name']); ?>"),o.setAttribute("data-customer-id", "<?php echo $params['user_id'] ?>"),o.setAttribute("data-customer-email", "<?php echo wp_json_encode($params['email']) ?>"), c.body.appendChild(o)
        }("<?php echo $params['pro_popup_url'] ?>")
    }(window, document);
</script>