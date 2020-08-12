(function (factory) {
    if (typeof module === "object" && typeof module.exports === "object") {
        factory(require("jquery"), window, document);
    } else {
        factory(jQuery, window, document);
    }
}(function ($, window, document, undefined) {
    function Retainful_premium(current_settings = {}) {
        this.settings = {};
        var default_settings = {
            checkout_url: '',
            cart_url: '',
            ei_popup: {
                enable: 'yes',
                show_for: 'everyone',
                is_user_logged_in: 'no',
                coupon_code: null,
                show_once_its_coupon_applied: 'no',
                applied_coupons: ['no'],
                show_popup: 'always',
                number_of_times_per_page: '1',
                cookie_expired_at: '1',
                redirect_url: '1',
                mobile: {
                    enable: 'yes',
                    time_delay: 'yes',
                    delay: '10',
                    scroll_distance: 'yes',
                    distance: '10'
                }
            },
            coupon_timer: {
                enable: 'yes',
                time_in_minutes: 15,
                code: 'YmJ4a3ljejI=',
                expiry_url: '',
                expired_text: 'Expired',
                top: {
                    enable: 'yes',
                    message: 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}',
                    timer: '{{minutes}}M {{seconds}}S',
                    display_on: 'top',
                    background: '#ffffff',
                    color: '#000000',
                    coupon_code_color: '#000000',
                    coupon_timer_color: '#000000',
                    enable_cta: 'yes',
                    cta_text: 'Checkout Now',
                    cta_color: '#ffffff',
                    cta_background: '#f27052',

                }, above_cart: {
                    enable: 'yes',
                    message: 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}',
                    timer: '{{minutes}}M {{seconds}}S',
                    background: '#ffffff',
                    color: '#000000',
                    coupon_code_color: '#000000',
                    coupon_timer_color: '#000000',
                    enable_cta: 'yes',
                    cta_text: 'Checkout Now',
                    cta_color: '#ffffff',
                    cta_background: '#f27052'
                }, below_discount: {
                    enable: 'yes',
                    message: 'Make purchase quickly, your {{coupon_code}} will expire within {{coupon_timer}}',
                    timer: '{{minutes}}M {{seconds}}S',
                    background: '#ffffff',
                    color: '#000000',
                    coupon_code_color: '#000000',
                    coupon_timer_color: '#000000'
                }
            }
        }
        this.settings = {...default_settings, ...current_settings};
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

    Retainful_premium.prototype.init_coupon_timer = function () {
        var settings = this.settings.coupon_timer;
        var timer_message = function (timer_settings, position, code, days, hours, minutes, seconds) {
            var message = timer_settings.message;
            var timer = timer_settings.timer;
            var timer_html = timer.replace('{{seconds}}', seconds).replace('{{minutes}}', minutes).replace('{{hours}}', hours).replace('{{days}}', days);
            return message.replace('{{coupon_code}}', '<span class="timer-coupon-code-' + position + '" style="color: ' + position.coupon_code_color + '">' + code + '</span>').replace('{{coupon_timer}}', '<span id="rnoc-coupon-timer-' + position + '" style="color: ' + position.coupon_timer_color + '">' + timer_html + '</span>');
        }
        var display_on_top = function (message, settings) {
            var btn = '';
            if (settings.enable_cta === "yes") {
                btn = '<a href="" style="text-decoration:none;padding: 10px;color: ' + settings.cta_color + ';background-color: ' + settings.cta_background + '">' + settings.cta_text + '</a>';
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
                btn = '<a href="" style="text-decoration:none;padding: 10px;color: ' + settings.cta_color + ';background-color: ' + settings.cta_background + '">' + settings.cta_text + '</a>';
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
        var run_timer = function (started = 1) {
            var start_time = sessionStorage.getItem('rnoc_coupon_timer_started');
            if (started === 1 && start_time === null) {
                start_time = new Date().getTime();
                sessionStorage.setItem('rnoc_coupon_timer_started', start_time.toString());
            } else {
                start_time = parseInt(start_time);
            }
            var end_time_minutes = parseInt(settings.time_in_minutes) * 60000;
            var countdown_date_time = start_time + end_time_minutes;
            window.rnoc_timer_expired_message_shown = false;
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
                    if (window.rnoc_timer_expired_message_shown === false) {
                        $.post(settings.expiry_url, function (data, status) {
                            if (data.success) {
                                $('.rnoc-coupon-timer-container-top').remove();
                                $('.rnoc-coupon-timer-container-above_cart').remove();
                                $('.rnoc-coupon-timer-container-below_discount').remove();
                                var coupon_det = $('.coupon-' + code.toLowerCase());
                                coupon_det.hide();
                                var wrapper = $(".woocommerce-notices-wrapper");
                                var html = '<ul class="woocommerce-error" role="alert"><li><?php echo $coupon_timer_expire_message; ?></li></ul>';
                                wrapper.append(html);
                            }
                        });
                        window.rnoc_timer_expired_message_shown = true;
                    }
                }
            });
        }
        console.log(settings);
        if (settings.enable === 'yes') {
            run_timer();
        }
    }

    Retainful_premium.prototype.init_ei_popup = function () {
        window.rnoc_ei_popup_showed_for = 0;
        window.is_rnoc_mobil_scroll_popup_showed = false;
        var settings = this.settings.ei_popup;
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
        var show_popup = function () {
            var show = false;
            switch (settings.show_for) {
                default:
                case "everyone":
                    show = true;
                    break;
                case "guest":
                    show = (settings.is_user_logged_in === 'no');
                    break;
                case "non_email_provided_users":
                    var is_email_provided = localStorage.getItem('rnoc_is_email_provided');
                    show = (typeof is_email_provided !== "undefined" || parseInt(is_email_provided) === 0);
                    break;
            }
            if (!show) {
                return false;
            }
            switch (settings.show_popup) {
                default:
                case "always":
                    show = true;
                    break;
                case "once_per_page":
                    show = (window.rnoc_ei_popup_showed_for <= 1);
                    break;
                case "x_times_per_page":
                    show = (window.rnoc_ei_popup_showed_for <= parseInt(settings.number_of_times_per_page));
                    break;
                case "once_per_session":
                    var rnoc_ei_popup_showed_count = sessionStorage.getItem('rnoc_ei_popup_showed_count');
                    show = (typeof rnoc_ei_popup_showed_count != "undefined");
                    break;
            }
            if (show) {
                alert();
                sessionStorage.setItem('rnoc_ei_popup_showed_count', '1')
                window.rnoc_ei_popup_showed_for++;
            }
        }
        if (settings.enable === "yes") {
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
    window.retainful = new Retainful_premium(
        {
            ei_popup: {
                enable_exit_intent_popup: 'yes',
                show_for: 'guest',
                is_user_logged_in: 'no',
                coupon_code: null,
                show_once_its_coupon_applied: 'no',
                applied_coupons: ['no'],
                show_popup: 'always',
                number_of_times_per_page: '1',
                cookie_expired_at: '1',
                redirect_url: '1',
                mobile: {
                    enable: 'yes',
                    time_delay: 'yes',
                    delay: '10',
                    scroll_distance: 'yes',
                    distance: '10'
                }
            }
        }
    );
    if (window.retainful.is_local_storage_supported()) {
        window.retainful.init_ei_popup();
        window.retainful.init_coupon_timer();
    }
}))
;