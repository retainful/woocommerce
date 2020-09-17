(function (factory) {
    if (typeof module === "object" && typeof module.exports === "object") {
        factory(require("jquery"), window, document);
    } else {
        factory(jQuery, window, document);
    }
}(function ($, window, document, undefined) {

    /**
     * premium
     * @constructor
     */
    function Retainful_premium() {
        this.settings = {};
        this.default_ei_popup_settings = {
            "enable": "no"
        };
        this.default_ct_settings = {
            "enable": "yes",
            "timer_started": "0",
            "time_in_minutes": "15",
            "code": "",
            "expiry_url": "",
            "reset_url": "",
            "expiry_message": "",
            "expired_text": "Expired",
            "timer_reset": "0",
            "top": {
                "enable": "yes",
                "checkout_url": "",
                "message": "Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}",
                "timer": "{{minutes}}M {{seconds}}S",
                "display_on": "bottom",
                "background": "#ffffff",
                "color": "#000000",
                "coupon_code_color": "#000000",
                "coupon_timer_color": "#000000",
                "enable_cta": "yes",
                "cta_text": "Checkout Now",
                "cta_color": "#ffffff",
                "cta_background": "#f27052"
            }, "above_cart": {
                "enable": "no"
            }, "below_discount": {
                "enable": "no"
            }
        };
        var default_settings = {
            "checkout_url": "",
            "cart_url": "",
            "ei_popup": {
                "enable": "yes",
                "show_for": "all",
                "is_user_logged_in": "no",
                "coupon_code": null,
                "show_once_its_coupon_applied": "no",
                "applied_coupons": ["no"],
                "show_popup": "every_time_on_customer_exists",
                "number_of_times_per_page": "1",
                "cookie_expired_at": "1",
                "redirect_url": "1",
                "mobile": {
                    "enable": "yes",
                    "time_delay": "yes",
                    "delay": "10",
                    "scroll_distance": "yes",
                    "distance": "10"
                }
            },
            "coupon_timer": {
                "enable": "yes",
                "timer_started": "0",
                "time_in_minutes": "15",
                "code": "",
                "expiry_url": "",
                "reset_url": "",
                "expiry_message": "",
                "expired_text": "Expired",
                "timer_reset": "0",
                "top": {
                    "enable": "yes",
                    "checkout_url": "",
                    "message": "Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}",
                    "timer": "{{minutes}}M {{seconds}}S",
                    "display_on": "bottom",
                    "background": "#ffffff",
                    "color": "#000000",
                    "coupon_code_color": "#000000",
                    "coupon_timer_color": "#000000",
                    "enable_cta": "yes",
                    "cta_text": "Checkout Now",
                    "cta_color": "#ffffff",
                    "cta_background": "#f27052"
                }, "above_cart": {
                    "enable": "no"
                }, "below_discount": {
                    "enable": "no"
                }
            }
        }
    }

    Retainful_premium.prototype.is_local_storage_supported = function () {
        try {
            sessionStorage.setItem('retainful', 'test');
            sessionStorage.removeItem('retainful');
            localStorage.setItem('retainful', 'test');
            localStorage.removeItem('retainful');
            return true;
        } catch (err) {
            return false;
        }
    }
    Retainful_premium.prototype.modal = function (content = "") {
        $(document).trigger('before_rnoc_show_modal');
        window.retainful.close_modal();
        var html_content = $('<div class="rnoc-modal"><div class="rnoc-modal-container"><a href="#0" class="rnoc-close-modal">Close</a> ' + content + '</div></div>');
        $('body').append(html_content);
        html_content.show();
        $(document).trigger('before_rnoc_show_modal');
        html_content.on('click', '.rnoc-close-modal', function () {
            $(document).trigger('before_rnoc_close_modal');
            html_content.remove();
        })
    }
    Retainful_premium.prototype.close_modal = function () {
        $(document).trigger('before_rnoc_close_modal');
        $('.rnoc-modal').remove();
    }

    /**
     * coupon timer
     * @param current_settings
     */
    Retainful_premium.prototype.init_coupon_timer = function (current_settings) {
        var settings = {...this.default_ct_settings, ...current_settings};
        var timer_message = function (timer_settings, position, code, days, hours, minutes, seconds) {
            var message = timer_settings.message;
            var timer = timer_settings.timer;
            var timer_html = timer.replace('{{seconds}}', seconds).replace('{{minutes}}', minutes).replace('{{hours}}', hours).replace('{{days}}', days);
            return message.replace('{{coupon_code}}', '<span class="timer-coupon-code-' + position + '" style="color: ' + position.coupon_code_color + '">' + code + '</span>').replace('{{coupon_timer}}', '<span id="rnoc-coupon-timer-' + position + '" style="color: ' + position.coupon_timer_color + '">' + timer_html + '</span>');
        }
        var display_on_top = function (message, settings) {
            var btn = '';
            if (settings.enable_cta === "yes") {
                btn = '<a href="' + settings.checkout_url + '" style="text-decoration:none;padding: 10px;color: ' + settings.cta_color + ';background-color: ' + settings.cta_background + '">' + settings.cta_text + '</a>';
            }
            var top_message = '<div class="rnoc-coupon-timer-container-top" style="' + settings.display_on + ':0;background-color:' + settings.background + ';color:' + settings.color + ';">' + message + btn + '</div>';
            $('.rnoc-coupon-timer-container-top').remove();
            var body = $("body");
            body.prepend(top_message);
            var container = $(".rnoc-coupon-timer-container-top")[0];
            body.css("margin-" + settings.display_on, container.offsetHeight);
        }
        var display_on_above_cart = function (message, settings) {
            var btn = '';
            if (settings.enable_cta === "yes") {
                btn = '<a href="' + settings.checkout_url + '" style="text-decoration:none;padding: 10px;color: ' + settings.cta_color + ';background-color: ' + settings.cta_background + '">' + settings.cta_text + '</a>';
            }
            var before_cart_message = '<div class="rnoc-coupon-timer-container-above_cart" style="background-color:' + settings.background + ';color:' + settings.color + ';">' + message + btn + '</div>';
            var container = $(".rnoc_before_cart_container");
            container.html(before_cart_message);
        }
        var display_on_below_discount = function (code, message, settings) {
            var before_cart_message = '<div class="rnoc-coupon-timer-container-below_discount" style="background-color:' + settings.background + ';color:' + settings.color + ';">' + message + '</div>';
            var container = $(".rnoc-below-discount_container-" + code);
            container.html(before_cart_message);
        }
        var run_timer = function () {
            var start_time = sessionStorage.getItem('rnoc_coupon_timer_started');
            if (parseInt(settings.timer_reset) === 1) {
                start_time = new Date().getTime();
                sessionStorage.setItem('rnoc_coupon_timer_started', start_time.toString());
                sessionStorage.removeItem('rnoc_timer_expired_message_shown');
                $.post(settings.reset_url, function (data, status) {
                });
            }
            if (start_time === null || start_time === undefined) {
                start_time = new Date().getTime();
                sessionStorage.setItem('rnoc_coupon_timer_started', start_time.toString());
            } else {
                start_time = parseInt(start_time);
            }
            var end_time_minutes = parseInt(settings.time_in_minutes) * 60000;
            var countdown_date_time = start_time + end_time_minutes;
            var code = settings.code;
            if (code !== "") {
                code = atob(code);
            }
            var timer = setInterval(function () {
                var now = new Date().getTime();
                var distance = countdown_date_time - now;
                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                if (distance > 0) {
                    if (settings.top.enable === "yes") {
                        var above_top = timer_message(settings.top, 'top', code, days, hours, minutes, seconds);
                        display_on_top(above_top, settings.top);
                    }
                    if (settings.above_cart.enable === "yes") {
                        var above_cart_message = timer_message(settings.above_cart, 'above_cart', code, days, hours, minutes, seconds);
                        display_on_above_cart(above_cart_message, settings.above_cart);
                    }
                    if (settings.below_discount.enable === "yes") {
                        var below_discount = timer_message(settings.below_discount, 'below_discount', code, days, hours, minutes, seconds);
                        display_on_below_discount(code, below_discount, settings.below_discount);
                    }
                } else {
                    clearInterval(timer);
                    var message_shown = localStorage.getItem('rnoc_timer_expired_message_shown')
                    if (message_shown === undefined || message_shown === null || parseInt(message_shown) !== 1) {
                        $.post(settings.expiry_url, function (data, status) {
                            if (data.success) {
                                $('.rnoc-coupon-timer-container-top').remove();
                                $('.rnoc-coupon-timer-container-above_cart').remove();
                                $('.rnoc-coupon-timer-container-below_discount').remove();
                                var coupon_det = $('.coupon-' + code.toLowerCase());
                                coupon_det.hide();
                                var wrapper = $(".woocommerce-notices-wrapper");
                                var html = '<ul class="woocommerce-error" role="alert"><li>' + settings.expiry_message + '</li></ul>';
                                wrapper.append(html);
                            }
                        });
                        localStorage.setItem('rnoc_timer_expired_message_shown', '1');
                    }
                }
            }, 1000);
        }
        //console.log(settings);
        if (settings.enable === 'yes') {
            if (parseInt(settings.timer_started) === 1) {
                run_timer();
            }
            $(document).on('added_to_cart', function (fragment, cart_hash, this_button) {
                run_timer();
            });
        }
    }
    /**
     * exit intent popup
     * @param current_settings
     */
    Retainful_premium.prototype.init_ei_popup = function (current_settings) {
        window.rnoc_ei_popup_showed_for = 0;
        window.is_rnoc_mobil_scroll_popup_showed = false;
        var settings = {...this.default_ei_popup_settings, ...current_settings};
        var is_mobile_device = function () {
            return (screen.width < 768);
        }
        var amount_scrolled = function () {
            var win_height = $(window).height();
            var doc_height = $(document).height();
            var scroll_top = $(window).scrollTop();
            var track_length = doc_height - win_height
            return Math.floor(scroll_top / track_length * 100) // gets percentage scrolled (ie: 80 or NaN if tracklength == 0)
        }
        var is_email = function (email) {
            var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
            return regex.test(email);
        }
        var show_popup = function () {
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
            console.log(cart_hash)
            if (cart_hash !== "" && cart_hash !== undefined && cart_hash !== null) {
                var show = false;
                switch (settings.show_for) {
                    default:
                    case "all":
                        show = true;
                        break;
                    case "guest":
                        show = (settings.is_user_logged_in === 'no');
                        break;
                    case "non_email_users":
                        var is_email_provided = sessionStorage.getItem('rnocp_is_add_to_cart_popup_email_entered');
                        show = (typeof is_email_provided !== "undefined" || parseInt(is_email_provided) === 0);
                        break;
                }
                if (!show) {
                    return false;
                }
                if (show) {
                    var html = $('.rnoc-ei-popup').html();
                    window.retainful.modal(html);
                    sessionStorage.setItem('rnoc_ei_popup_showed_count', '1')
                    window.rnoc_ei_popup_showed_for++;
                }
                switch (settings.show_popup) {
                    default:
                    case "every_time_on_customer_exists":
                        show = true;
                        break;
                    case "once_per_page":
                        show = (window.rnoc_ei_popup_showed_for <= 1);
                        break;
                    case "show_x_times_per_page":
                        show = (window.rnoc_ei_popup_showed_for <= parseInt(settings.number_of_times_per_page));
                        break;
                    case "once_per_session":
                        var rnoc_ei_popup_showed_count = sessionStorage.getItem('rnoc_ei_popup_showed_count');
                        show = (typeof rnoc_ei_popup_showed_count != "undefined");
                        break;
                }
            }
        }
        if (settings.enable === "yes") {
            $(document).on('submit', '#rnoc_exit_intent_popup_form', function (event) {
                event.preventDefault();
                var popup_submit_btn = $('.rnoc-exit-intent-popup-submit-button');
                var message = $("#rnoc-invalid-mail-message-exit-intent");
                var email = $('#rnoc-exit-intent-popup-email-field');
                var is_buyer_accepting_marketing = $('#rnoc-exit-intent-popup-buyer-accepts-marketing');
                message.hide();
                if (!is_email(email.val()) || email.val() === '') {
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
                $.post(settings.ajax_url, popup_data, function (response) {
                    if (response.error === false) {
                        sessionStorage.setItem('rnocp_is_add_to_cart_popup_email_entered', '1');
                        if (response.message !== '') {
                            alert(response.message);
                        }
                        window.retainful.close_modal();
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
            $(document).on('mouseleave', function (e) {
                if (e.clientY < 0) {
                    show_popup();
                }
            });
            if (is_mobile_device()) {
                if (settings.mobile.time_delay === "yes" && parseInt(settings.mobile.delay) > 0) {
                    setTimeout(function () {
                        show_popup();
                    }, parseInt(settings.mobile.delay) * 1000);
                }
                if (settings.mobile.scroll_distance === "yes" && parseInt(settings.mobile.distance) > 0) {
                    $(window).on("scroll", function () {
                        var scrolled_distance = amount_scrolled();
                        if ((scrolled_distance > settings.mobile.distance) && window.is_rnoc_mobil_scroll_popup_showed === false) {
                            window.is_rnoc_mobil_scroll_popup_showed = true;
                            show_popup();
                        }
                    });
                }
            }
        }
    }
    window.retainful = new Retainful_premium();
    if (window.retainful.is_local_storage_supported()) {
        window.retainful.init_ei_popup(rnoc_premium_ei_popup);
        window.retainful.init_coupon_timer(rnoc_premium_ct);
    }
}))
;