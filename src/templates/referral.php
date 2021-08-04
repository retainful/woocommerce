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
        }("<?php echo $params['referral_url'] ?>")
    }(window, document);
</script>
<div id="rtl-shopify-init" data-app-key="<?php echo $params['api_key']; ?>"
     data-customer-accepts-marketing="<?php echo $params['accepts_marketing']; ?>"
     data-customer-email="<?php echo $params['email']; ?>"
     data-customer-first-name="<?php echo $params['first_name']; ?>"
     data-customer-id="<?php echo $params['id']; ?>"
     data-customer-last-name="<?php echo $params['last_name']; ?>"
     data-customer-orders-count="<?php echo $params['order_count'] ?>"
     data-customer-tags="<?php echo $params['tags']; ?>"
     data-customer-total-spent="<?php echo $params['total_spent'] ?>"
     data-digest="<?php echo $params['digest']; ?>"
>
</div>
<script type="application/javascript">
    window.retainful_referral = <?php echo wp_json_encode($params['window']) ?>;
</script>