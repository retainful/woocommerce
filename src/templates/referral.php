<?php
/**
 * @var $params array
 */
?>
<script>
    !function (e, c) {
        !function (e) {
            const o = c.createElement("script");
            o.type = "application/javascript", o.src = e, c.body.appendChild(o)
        }("<?php echo esc_attr($params['referral_url']) ?>")
    }(window, document);
</script>
<div id="rtl-shopify-init" data-app-key="<?php echo esc_attr($params['api_key']); ?>"
     data-customer-accepts-marketing="<?php echo esc_attr($params['accepts_marketing']); ?>"
     data-customer-email="<?php echo esc_attr($params['email']); ?>"
     data-customer-first-name="<?php echo esc_attr($params['first_name']); ?>"
     data-customer-id="<?php echo esc_attr($params['id']); ?>"
     data-customer-last-name="<?php echo esc_attr($params['last_name']); ?>"
     data-customer-tags="<?php echo esc_attr($params['tags']); ?>"
     data-digest="<?php echo esc_attr($params['digest']); ?>"
>
</div>
<script type="application/javascript">
    window.retainful_referral = <?php echo wp_json_encode($params['window']) ?>;
</script>