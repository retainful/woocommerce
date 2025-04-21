<div class="rnoc-popup"
     style="background-color: <?php echo esc_attr($rnoc_modal_bg_color); ?>;border-top: 5px solid <?php echo esc_attr($rnoc_modal_add_cart_border_top_color); ?>;">
    <div class="rnoc-popup-head">
        <div class="rnoc-lw-title"
             style="color: <?php echo esc_attr($rnoc_modal_heading_color); ?>;"><?php echo esc_attr__($rnoc_modal_heading,'retainful-next-order-coupon-for-woocommerce'); //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText	?></div>
        <p style="color: <?php echo esc_attr($rnoc_modal_sub_heading_color); ?>;"><?php echo esc_attr__($rnoc_modal_sub_heading,'retainful-next-order-coupon-for-woocommerce'); //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></p>
    </div>
    <?php echo wp_kses_post($rnoc_popup_form_open) ?>
    <div class="rnoc-lw-center">
        <div class="rnoc-lw-field rnoc-popup-form-block">
            <div class="rnoc-form-input-field" style="width:<?php echo floatval($rnoc_modal_email_field_width); ?>%;">
                <input class="rnoc-lw-input" id="rnoc-popup-email-field"
                       placeholder="<?php echo esc_attr__($rnoc_modal_email_placeholder,'retainful-next-order-coupon-for-woocommerce') //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?>" <?php echo esc_attr($rnoc_popup_email_field) ?>
                       type="email">
            </div>
            <div class="rnoc-form-button-field" style="width:<?php echo floatval($rnoc_modal_button_field_width); ?>%;">
                <button style="color: <?php echo esc_attr($rnoc_modal_add_cart_color) ?>;background: <?php echo esc_attr($rnoc_modal_add_cart_bg_color) ?>;"
                        class="rnoc-lw-btn rnoc-popup-btn"><?php echo esc_attr__($rnoc_modal_add_cart_text,'retainful-next-order-coupon-for-woocommerce') //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?>
                </button>
            </div>
        </div>
        <p style="margin: 0 0 5px 15px;color: red;text-align: left;display: none;"
           id="rnoc-invalid-mail-message"><?php echo esc_html__('Please enter the valid email.', 'retainful-next-order-coupon-for-woocommerce') ?></p>
        <?php
        if ($rnoc_gdpr_check_box_settings != "no_need_gdpr") {
            ?>
            <div class="rnoc-pp-label rnoc-accepts-marketing-form-field">
                <label>
                    <?php
                    if (in_array($rnoc_gdpr_check_box_settings, array('show_and_check_checkbox', 'show_checkbox'))) {
                        ?>
                        <input type="checkbox" name="add_to_cart_buyer_accepts_marketing"
                               id="rnoc-popup-buyer-accepts-marketing" <?php echo esc_attr($rnoc_gdpr_check_box_settings == 'show_and_check_checkbox') ? "checked" : "" ?>/>&nbsp;
                        <?php
                    }
                    echo wp_kses($rnoc_gdpr_check_box_message);
                    ?>
                </label>
            </div>
            <?php
        }
        if ($rnoc_no_thanks_action) {
            ?>
            <a class="no-thanks-close-popup" href="#"
               style="color: <?php echo esc_attr($rnoc_modal_add_cart_no_thanks_color); ?>;"><?php echo esc_attr__($rnoc_modal_not_mandatory_text,'retainful-next-order-coupon-for-woocommerce') //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></a>
            <?php
        }
        ?>
        <span><?php echo esc_attr__($rnoc_modal_terms_text,'retainful-next-order-coupon-for-woocommerce') //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></span>
    </div>
    <?php echo wp_kses_post($rnoc_popup_form_close) ?>
</div>