<div class="rnoc-popup-holder"
     style="border-top: 5px solid <?php echo $rnoc_exit_intent_modal_add_cart_border_top_color; ?>;padding: 20px;background: #f5f5f5;">
    <div class="rnoc-header">
        <div style="color: <?php echo $rnoc_exit_intent_modal_heading_color; ?>;font-size: 34px;font-weight: 600;margin-top: 20px;">
            <?php echo rnocEscAttr($rnoc_exit_intent_modal_heading); ?>
        </div>
        <div style="color: <?php echo $rnoc_exit_intent_modal_sub_heading_color; ?>;margin: 10px 0;line-height: 1.7;font-size: 20px;">
            <?php echo rnocEscAttr($rnoc_exit_intent_modal_sub_heading); ?>
        </div>
    </div>
    <div class="rnoc-content">
        <?php echo $rnoc_popup_form_open ?>
        <div class="rnoc-popup-form-block"
             style="display: flex;align-items: center;flex-wrap: wrap;justify-content: space-between;">
            <input type="email" id="rnoc-popup-email-field"
                   placeholder="<?php echo rnocEscAttr($rnoc_exit_intent_modal_email_placeholder) ?>"
                   class="rnoc-popup-input" <?php echo rnocEscAttr($rnoc_popup_email_field) ?>
                   style="width: 100%;max-width: 100%;padding: 15px;box-shadow: 0 0 4px -3px #000000;border-radius: 5px;background: #ffffff;margin: 10px 0;font-weight: 400;font-size: 18px;border: none !important;flex: 0 0 58%;">
            <button class="rnoc-popup-btn"
                    style="color: <?php echo $rnoc_exit_intent_modal_add_cart_color ?>;background: <?php echo $rnoc_exit_intent_modal_add_cart_bg_color ?>;width: 100%;padding: 15px;border-radius: 5px;margin: 10px 0;font-weight: 400;font-size: 18px;border: none !important;flex: 0 0 40%;cursor: pointer;">
                <?php echo rnocEscAttr($rnoc_exit_intent_modal_add_cart_text) ?>
            </button>
        </div>
        <p style="color: red;text-align: left;display: none;" id="rnoc-invalid-mail-message"><?php echo __('Please enter the valid
            email.', RNOC_TEXT_DOMAIN) ?></p>
        <p class="small-text"
           style="color: #6d6d6d;text-align: center;font-size: 14px;"><?php echo rnocEscAttr($rnoc_coupon_message) ?></p>
        <?php echo $rnoc_popup_form_close ?>
    </div>
    <div style="margin: 20px auto;color: #5f5f5f;text-align: center;"><?php echo rnocEscAttr($rnoc_exit_intent_modal_terms_text) ?></div>
</div>