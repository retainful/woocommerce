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
            ei_popup: {
                enable_exit_intent_popup: 'yes',
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
        if (settings.enable_exit_intent_popup === "yes") {
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
                enable_exit_intent_popup: 'no'
            }
        }
    );
    if (window.retainful.is_local_storage_supported()) {
        window.retainful.init_ei_popup();
    }
}))
;