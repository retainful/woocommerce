<div class="rnoc-popup"
     style="padding: 15px;background: <?php echo $rnoc_modal_bg_color; ?>;border-radius: 15px;position: relative;transition: all 3s ease-in-out;border-top: 5px solid <?php echo $rnoc_modal_add_cart_border_top_color; ?>;">

    <div class="rnoc-popup-head" style="display: block;text-align: center;">
        <div class="lw-title"
             style="padding: 0 12px;margin: 15px 0px 4px;font-size: 31px;font-weight: 600;line-height: 43px;color: <?php echo $rnoc_modal_heading_color; ?>;">
            <?php echo $rnoc_modal_heading; ?>
        </div>
        <p style="font-size: 22px;padding: 0 15px;line-height: 30px;margin: 18px 0px 10px;color: <?php echo $rnoc_modal_sub_heading_color; ?>;"><?php echo $rnoc_modal_sub_heading; ?></p>

        <?php echo $rnoc_popup_form_open ?>
        <div class="rnoc-lw-center" style="text-align: center;padding: 0 10px;">
            <div class="rnoc-popup-form-block" style="position: relative;margin-bottom: 4px;">
                <input id="rnoc-popup-email-field"
                       placeholder="<?php echo $rnoc_modal_email_placeholder ?>" <?php echo $rnoc_popup_email_field ?>
                       type="email"
                       style="display: inline-block;width: 48%;font-size: 16px;border-radius: 5px 0px 0px 5px;box-shadow: 0 3px 5px 0 rgba(37, 39, 44, 0.12);height: 47px;padding: 0 15px;border: 1px solid rgba(0, 0, 0, 0.12);margin-bottom: 7px;font-weight: 500;">
                <button class="rnoc-popup-btn"
                        style="color: <?php echo $rnoc_modal_add_cart_color ?>;background: <?php echo $rnoc_modal_add_cart_bg_color ?>;width: 40%;display: inline-block;padding: 14px 20px;height: 48px;border: none;border-radius: 0px 5px 5px 0px;font-size: 16px;line-height: 20px;box-shadow: 0 3px 5px 0 rgba(37, 39, 44, 0.17);font-weight: 600;margin: 15px -7px;text-align: center;cursor: pointer;">
                    <?php echo $rnoc_modal_add_cart_text ?>
                </button>
            </div>
            <p style="margin: 15px 0 5px 50px;color: red;text-align: left;display: none;" id="rnoc-invalid-mail-message"><?php echo __('Please enter the valid
            email.', RNOC_TEXT_DOMAIN) ?></p>
            <?php
            if ($rnoc_gdpr_check_box_settings != "no_need_gdpr") {
                ?>
                <div class="rnoc-pp-label"
                     style="text-align: left;margin: 15px 0 15px 50px;font-size: 15px;">
                    <label>
                        <?php
                        if (in_array($rnoc_gdpr_check_box_settings, array('show_and_check_checkbox', 'show_checkbox'))) {
                            ?>
                            <input type="checkbox" name="add_to_cart_buyer_accepts_marketing"
                                   id="rnoc-popup-buyer-accepts-marketing" <?php echo ($rnoc_gdpr_check_box_settings == 'show_and_check_checkbox') ? "checked" : "" ?>/>&nbsp;
                            <?php
                        }
                        echo $rnoc_gdpr_check_box_message;
                        ?>
                    </label>
                </div>
                <?php
            }
            ?>
            <?php
            if ($rnoc_no_thanks_action) {
                ?>
                <a class="no-thanks-close-popup" href="#"
                   style="text-decoration: none;font-size: 16px;color: <?php echo $rnoc_modal_add_cart_no_thanks_color; ?>;line-height: 24px;margin-bottom: 20px;font-weight: 500 !important;"><?php echo $rnoc_modal_not_mandatory_text ?></a>
                <?php
            }
            ?>
            <span style="font-size: 12px;padding: 0 85px;line-height: 22px;margin-top: 8px;color: #403f3f;display: block;"><?php echo $rnoc_modal_terms_text ?></span>
        </div>
        <?php echo $rnoc_popup_form_close ?>
    </div>
</div>