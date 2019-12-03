jQuery(function ($) {
    window.retainful_email_collection_popup_status = true;
    $(document).on('adding_to_cart', function (this_button, data) {
        let need_popup = needToShowAddToCartPopup();
        if (need_popup) {
            data.removeClass('loading');
            setTimeout(function () {
                console.clear();
            }, 1);
            data.addClass('rnoc-popup-opener');
            showRetainfulEmailCollectionPopup();
            throw new Error('this is not error.just terminating the process!');
        }
    });

    function needToShowAddToCartPopup() {
        var is_email_entered = sessionStorage.getItem('rnocp_is_add_to_cart_popup_email_entered');
        if (parseInt(is_email_entered) === 1) {
            window.retainful_email_collection_popup_status = false;
        }
        var hide_modal_after = parseInt(retainful_premium_add_to_cart_collection_popup_condition.hide_modal_after_show);
        if (window.retainful_email_collection_popup_status) {
            switch (hide_modal_after) {
                case 2:
                    var is_popup_no_thanks_clicked = sessionStorage.getItem('rnocp_is_add_to_cart_popup_no_thanks_clicked');
                    if (parseInt(is_popup_no_thanks_clicked) === 1) {
                        window.retainful_email_collection_popup_status = false;
                    }
                    break;
                case 3:
                    var is_popup_closed = sessionStorage.getItem('rnocp_is_add_to_cart_popup_closed');
                    if (parseInt(is_popup_closed) === 1) {
                        window.retainful_email_collection_popup_status = false;
                    }
                    break;
            }
        }
        return window.retainful_email_collection_popup_status;
    }

    $(document).on('click', '.single_add_to_cart_button,[name=add-to-cart]', function (event) {
        let need_popup = needToShowAddToCartPopup();
        if (need_popup) {
            event.preventDefault();
            $(this).addClass('rnoc-popup-opener');
            showRetainfulEmailCollectionPopup();
        }
    });
    $('.single_add_to_cart_button,[name=add-to-cart]').on('click', function (event) {
        let need_popup = needToShowAddToCartPopup();
        if (need_popup) {
            event.preventDefault();
            $(this).addClass('rnoc-popup-opener');
            showRetainfulEmailCollectionPopup();
        }
    });
    if (typeof retainful_premium_add_to_cart_collection !== "undefined" && typeof retainful_premium_add_to_cart_collection.add_to_cart_button_classes !== 'undefined') {
        var retainful_email_collection_support_class = retainful_premium_add_to_cart_collection.add_to_cart_button_classes;
        $(document).on('rnoc_closing_email_collection_popup', function () {
            var button = $('.rnoc-popup-opener');
            var link = button.attr('href');
            if (typeof link !== "undefined") {
                window.location.href = link;
            } else {
                button.trigger('click');
                button.removeClass('rnoc-popup-opener');
            }
        });

        $(document).on('click', retainful_email_collection_support_class, function (event) {
            let url = $(this).attr('href');
            let button_name = $(this).attr('name');
            let need_popup = needToShowAddToCartPopup();
            if (url !== undefined && need_popup && url.indexOf('add-to-cart') !== -1) {
                event.preventDefault();
                $(this).addClass('rnoc-popup-opener');
                $(document.body).trigger('rnoc_show_email_collection_popup');
            } else if (button_name !== undefined && button_name === "add-to-cart") {
                event.preventDefault();
                $(this).addClass('rnoc-popup-opener');
                $(document.body).trigger('rnoc_show_email_collection_popup');
            }
        });
    }
    $(document).on('rnoc_show_email_collection_popup', function () {
        showRetainfulEmailCollectionPopup();
    });
    var modal = $('#rnoc-add-to-cart-add-on');
    $(document).on('click', '#rnoc-add-to-cart-add-on .close-rnoc-popup', function () {
        sessionStorage.setItem('rnocp_is_add_to_cart_popup_closed', '1');
        popupClosed(3);
        hideRetainfulEmailCollectionPopup();
    });
    $(document).on('click', '#rnoc-add-to-cart-add-on .no-thanks-close-popup', function () {
        sessionStorage.setItem('rnocp_is_add_to_cart_popup_no_thanks_clicked', '1');
        popupClosed(2);
        if (no_thanks_action === 1) {
            window.retainful_email_collection_popup_status = false;
            retainfulEmailCollectionPopupAddToCart();
        }
        hideRetainfulEmailCollectionPopup();
        window.retainful_email_collection_popup_status = true;
    });

    function retainfulEmailCollectionPopupAddToCart() {
        $('.rnoc-popup-opener').trigger('click');
        $(document.body).trigger('rnoc_closing_email_collection_popup');
    }

    function showRetainfulEmailCollectionPopup() {
        modal.css("display", "block");
    }

    function hideRetainfulEmailCollectionPopup() {
        modal.css("display", "none");
        var message = $("#rnoc-add-to-cart-add-on  #rnoc-invalid-mail-message");
        message.hide();
        $(document.body).trigger('rnoc_hiding_email_collection_popup');
        $('.rnoc-popup-opener').removeClass('rnoc-popup-opener');
    }

    function popupClosed(action_triggred) {
        var show_one_time = retainful_premium_add_to_cart_collection_popup_condition.hide_modal_after_show;
        if (typeof show_one_time !== "undefined") {
            if (parseInt(show_one_time) === parseInt(action_triggred)) {
                window.retainful_email_collection_popup_status = false;
                var popup_data = {
                    action: 'rnoc_popup_closed',
                    popup_action: action_triggred
                };
                $.post(rnoc_ajax_url, popup_data, function (response) {
                });
            }
        }
    }

    $(document).on('submit', '#rnoc_popup_form', function (event) {
        event.preventDefault();
        var popup_submit_btn = $('#rnoc-add-to-cart-add-on  .rnoc-popup-btn');
        var message = $("#rnoc-add-to-cart-add-on  #rnoc-invalid-mail-message");
        var email = $('#rnoc-add-to-cart-add-on #rnoc-popup-email-field');
        var is_buyer_accepting_marketing = $('#rnoc-add-to-cart-add-on #rnoc-popup-buyer-accepts-marketing');
        message.hide();
        if (is_email_manditory === 1) {
            if (!isEmail(email.val())) {
                message.show();
                return false;
            }
        } else {
            if (!isEmail(email.val()) && email.val() !== '') {
                message.show();
                return false;
            }
        }
        popup_submit_btn.addClass('loading');
        popup_submit_btn.attr('disabled', true);
        var popup_data = {
            email: email.val(),
            is_buyer_accepting_marketing: (is_buyer_accepting_marketing.is(':checked')) ? 1 : 0,
            action: 'set_rnoc_guest_session'
        };
        $.post(rnoc_ajax_url, popup_data, function (response) {
            if (!response.error) {
                if (response.message !== '') {
                    alert(response.message);
                }
                sessionStorage.setItem('rnocp_is_add_to_cart_popup_email_entered', '1');
                window.retainful_email_collection_popup_status = false;
                retainfulEmailCollectionPopupAddToCart();
                if (email.val() === '') {
                    window.retainful_email_collection_popup_status = true;
                }
                hideRetainfulEmailCollectionPopup();
                popupClosed(1);
            } else {
                if (response.message !== '') {
                    alert(response.message);
                }
            }
            popup_submit_btn.removeClass('loading');
            popup_submit_btn.attr('disabled', false);
        });
    });

    function isEmail(email) {
        var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        return regex.test(email);
    }
});
