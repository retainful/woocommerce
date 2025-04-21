<form id="rnoc_exit_intent_popup_form" class="lw-wrap" style="display: block; padding: 0; color: #2f2e35;">
    <input id="rnoc-exit-intent-popup-email-field"
           style="display: inline-block; width: <?php echo esc_attr($input_width); ?>; font-size: 16px; border: 1px solid rgba(0, 0, 0, 0.06); border-radius: 4px; box-shadow: 0 3px 5px 0 rgba(37, 39, 44, 0.05), 0 1px 3px 0 rgba(37, 39, 44, 0.06); height: <?php echo esc_attr($input_height); ?>; padding: 0 15px; margin-bottom: 12px;"
           required="" type="email"
           placeholder="<?php echo esc_attr($place_holder); ?>"/>
    <button type="submit" class="rnoc-exit-intent-popup-submit-button"
            style="width: <?php echo esc_attr($button_width); ?>; display: inline-block; padding: 12px 20px; background: <?php echo esc_attr($button_bg_color); ?>; border: none; border-radius: 4px; font-size: 16px; font-weight: 600; color: <?php echo esc_attr($button_color); ?>; text-align: center; line-height: 1.33333; transition: background .2s, opacity .2s; margin-top: 0; margin-bottom: 12px;height: <?php echo esc_attr($input_height); ?>;">
        <?php echo esc_attr($button_text); ?>
    </button>
    <div style="text-align: left;">
        <?php
        if ($rnoc_gdpr_check_box_settings != "no_need_gdpr") {
            ?>
            <label>
                <?php
                if (in_array($rnoc_gdpr_check_box_settings, array('show_and_check_checkbox', 'show_checkbox'))) {
                    ?>
                    <input type="checkbox" name="add_to_cart_buyer_accepts_marketing"
                           id="rnoc-exit-intent-popup-buyer-accepts-marketing" <?php echo ($rnoc_gdpr_check_box_settings == 'show_and_check_checkbox') ? "checked" : "" ?>/>&nbsp;
                    <?php
                }
                echo wp_kses_post($rnoc_gdpr_check_box_message);
                ?>
            </label>
            <?php
        }
        ?>
    </div>
    <div class="lw-field" style="position: relative; margin-bottom: 12px;"></div>
    <p style="color: red;text-align: left;display: none;"
       id="rnoc-invalid-mail-message-exit-intent"><?php echo esc_html__('Please enter the valid
            email.', 'retainful-next-order-coupon-for-woocommerce') ?></p>
</form>