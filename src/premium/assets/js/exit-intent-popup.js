document.addEventListener("DOMContentLoaded", function () {
// Only do anything if jQuery isn't defined
    if (typeof jQuery == 'undefined') {
        function getScript(url, success) {
            var script = document.createElement('script');
            script.src = url;
            var head = document.getElementsByTagName('head')[0],
                done = false;
            // Attach handlers for all browsers
            script.onload = script.onreadystatechange = function () {
                if (!done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete')) {
                    done = true;
                    // callback function provided as param
                    success();
                    script.onload = script.onreadystatechange = null;
                    head.removeChild(script);
                }
            };
            head.appendChild(script);
        }

        if (typeof retainful_premium_exit_intent_popup.jquery_url !== "undefined") {
            getScript(retainful_premium_exit_intent_popup.jquery_url, function () {
                if (typeof jQuery == 'undefined') {
                    // Super failsafe - still somehow failed...
                } else {
                    jQuery.noConflict();
                    initJqueryRetainfulExitIntentPopupJs();
                }
            });
        }
    } else {
        initJqueryRetainfulExitIntentPopupJs();
    }
});

function initJqueryRetainfulExitIntentPopupJs() {
    jQuery(function ($) {
        window.retainful_exit_intent_popup_status = true;
        $(document).on('rnoc_show_exit_intent_popup', function () {
            showRetainfulExitIntentPopup();
        });
        var modal = $('#rnoc-exit-intent-popup-add-on');
        $(document).on('click', '#rnoc-exit-intent-popup-add-on .close-rnoc-popup', function () {
            hideRetainfulExitIntentPopup();
        });

        function setPopupLastShowedTime() {
            let current_time = Date.now();
            localStorage.setItem('rnocp_exit_intent_popup_last_showed_on', current_time);
        }

        function getPopupLastShowedTime() {
            return localStorage.getItem('rnocp_exit_intent_popup_last_showed_on')
        }

        function hideRetainfulExitIntentPopup() {
            modal.css("display", "none");
            var message = $("#rnoc-add-to-cart-add-on #rnoc-invalid-mail-message");
            message.hide();
            $(document.body).trigger('rnoc_hiding_exit_intent_popup');
        }

        Bounceback.init({
            maxDisplay: 0,
            distance: parseInt(retainful_premium_exit_intent_popup.distance),
            cookieLife: parseInt(retainful_premium_exit_intent_popup.cookieLife),
            storeName: retainful_premium_exit_intent_popup.storeName,
            aggressive: true,
            scrollDelay: 500,
            onBounce: function () {
                showRetainfulExitIntentPopup();
            }
        });

        function showRetainfulExitIntentPopup() {
            let cart_hash;
            for (let key in sessionStorage) {
                if (sessionStorage.hasOwnProperty(key)) {
                    if (key.indexOf("wc_cart_hash_") === 0) {
                        cart_hash = sessionStorage.getItem(key);
                        break;
                    }
                }
            }
            let checkout_form = $('form.checkout');
            if (checkout_form.is('.processing')) {
                return false;
            }
            if (cart_hash !== "" && cart_hash !== undefined && cart_hash !== null) {
                let number_of_times_showed = (typeof window.rnocp_exit_intent_popup_showed_count !== "undefined") ? window.rnocp_exit_intent_popup_showed_count : 0;
                let show_popup = true;
                if (retainful_premium_exit_intent_popup.show_only_for === "non_email_users") {
                    let atcp_email_entered = sessionStorage.getItem('rnocp_is_add_to_cart_popup_email_entered');
                    if (atcp_email_entered && parseInt(atcp_email_entered) === 1) {
                        return false;
                    }
                }
                if (retainful_premium_exit_intent_popup.show_option === "once_per_session") {
                    let current_time = Date.now();
                    let last_showed_time = getPopupLastShowedTime();
                    let next_show_time = getNextTimeToShowPopup(retainful_premium_exit_intent_popup.cookieLife, last_showed_time)
                    if (current_time < next_show_time) {
                        show_popup = false;
                    }
                } else if (retainful_premium_exit_intent_popup.show_option === "every_time_on_customer_exists") {
                    show_popup = true;
                } else if (retainful_premium_exit_intent_popup.show_option === "once_per_page") {
                    show_popup = (number_of_times_showed === 0);
                } else if (retainful_premium_exit_intent_popup.show_option === "show_x_times_per_page") {
                    show_popup = (number_of_times_showed < parseInt(retainful_premium_exit_intent_popup.maxDisplay));
                }
                var is_email_entered = sessionStorage.getItem('rnocp_is_add_to_cart_popup_email_entered');
                if (parseInt(is_email_entered) === 1) {
                    $("#rnoc_exit_intent_popup_form").hide();
                }
                if (show_popup && window.retainful_exit_intent_popup_status) {
                    modal.css("display", "block");
                    window.rnocp_exit_intent_popup_showed_count = number_of_times_showed + 1;
                    setPopupLastShowedTime();
                }
            }
        }

        function getNextTimeToShowPopup(days, append_to) {
            let do_not_show_for = parseInt(days) * 24 * 60 * 60 * 1000;
            return parseInt(append_to) + do_not_show_for;
        }

        $(document).on('submit', '#rnoc_exit_intent_popup_form', function (event) {
            event.preventDefault();
            var popup_submit_btn = $('.rnoc-exit-intent-popup-submit-button');
            var message = $("#rnoc-invalid-mail-message-exit-intent");
            var email = $('#rnoc-exit-intent-popup-email-field');
            var is_buyer_accepting_marketing = $('#rnoc-exit-intent-popup-buyer-accepts-marketing');
            message.hide();
            if (!isEmail(email.val()) || email.val() === '') {
                message.show();
                return false;
            }
            popup_submit_btn.addClass('loading');
            popup_submit_btn.attr('disabled', true);
            var popup_data = {
                email: email.val(),
                is_buyer_accepting_marketing: (is_buyer_accepting_marketing.is(':checked')) ? 1 : 0,
                action: 'set_rnoc_exit_intent_popup_guest_session'
            };
            $.post(rnoc_ajax_url, popup_data, function (response) {
                if (!response.error) {
                    sessionStorage.setItem('rnocp_is_add_to_cart_popup_email_entered', '1');
                    if (response.message !== '') {
                        alert(response.message);
                    }
                    window.retainful_exit_intent_popup_status = false;
                    if (email.val() === '') {
                        window.retainful_exit_intent_popup_status = true;
                    }
                    hideRetainfulExitIntentPopup();
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    }
                } else {
                    if (response.message !== '') {
                        alert(response.message);
                    }
                }
                popup_submit_btn.removeClass('loading');
                popup_submit_btn.attr('disabled', false);
            });
        });

        $(document).on("change", "#billing_email", function () {
            let email = $(this).val();
            if (isEmail(email)) {
                sessionStorage.setItem('rnocp_is_add_to_cart_popup_email_entered', '1');
            }
        });

        function isEmail(email) {
            var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
            return regex.test(email);
        }
    });
}