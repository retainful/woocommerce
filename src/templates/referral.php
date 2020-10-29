<?php
/**
 * @var $params array
 */
?>
<div id="rtl-shopify-init" data-app-key="<?php echo $params['api_key']; ?>"
     data-customer-accepts-marketing="<?php echo $params['accepts_marketing']; ?>"
     data-customer-email="<?php echo rawurlencode($params['email']); ?>"
     data-customer-first-name="<?php echo rawurlencode($params['first_name']); ?>"
     data-customer-id="<?php echo rawurlencode($params['id']); ?>"
     data-customer-last-name="<?php echo rawurlencode($params['last_name']); ?>"
     data-customer-orders-count="<?php echo $params['order_count'] ?>"
     data-customer-tags="<?php echo rawurlencode(implode(',', $params['tags'])); ?>"
     data-customer-total-spent="<?php echo $params['total_spent'] ?>"
     data-digest="<?php echo $params['digest']; ?>"
>
</div>