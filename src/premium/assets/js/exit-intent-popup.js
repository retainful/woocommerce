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

/* Bounceback.js v1.0.0 | Copyright 2014 Avi Kohn | Distributable under the MIT license */
!function (a, b) {
    "function" == typeof define && define.amd ? define(function () {
        return b(a, document, {})
    }) : "undefined" != typeof exports ? global && global.testEnv ? b(global.testEnv, global.testEnv.document, exports) : b(a, document, exports) : a.Bounceback = b(a, document, {})
}(window, function (a, b, c) {
    var d = function (a, b, c) {
        a.attachEvent ? a.attachEvent("on" + b, c) : a.addEventListener(b, c, !1)
    }, e = a.Bounceback;
    c.noConflict = function () {
        return a.Bounceback = e, this
    }, c.version = "1.0.0", c.options = {
        distance: 100,
        maxDisplay: 1,
        method: "auto",
        sensitivity: 10,
        cookieLife: 365,
        scrollDelay: 500,
        aggressive: !1,
        checkReferrer: !0,
        storeName: "bounceback-visited",
        onBounce: function () {
            return c
        }
    }, c.data = {
        get: function (c) {
            if (a.localStorage) return a.localStorage.getItem(c) || "";
            for (var d = b.cookie.split(";"), e = -1, f = [], g = d.length; ++e < g;) if (f = d[e].split("="), f[0] == c) return f.shift(), f.join("=");
            return ""
        }, set: function (d, e) {
            if (a.localStorage) a.localStorage.setItem(d, e); else {
                var f = new Date;
                f.setDate(f.getDate() + c.options.cookieLife), b.cookie = d + "=" + e + "; expires=" + f.toUTCString() + ";path=/;"
            }
            return this
        }
    };
    var f = 0;
    return c.onBounce = function () {
        f++, (!this.options.maxDisplay || f <= this.options.maxDisplay) && this.options.onBounce()
    }, c.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(a.navigator.userAgent), c.disabled = !1, c.activated = !1, c.disable = function () {
        return this.disabled = !0, this
    }, c.enable = function () {
        return this.disabled = !1, this
    }, c.activate = function (e) {
        if ("history" == e) "replaceState" in a.history ? (a.history.replaceState({isBouncing: !0}, a.title), a.history.pushState(null, a.title), d(a, "popstate", function () {
            a.history.state && a.history.state.isBouncing && c.onBounce()
        })) : "onhashchange" in a && (a.location.replace("#bht"), a.location.hash = "", d(a, "hashchange", function () {
            "bht" === a.location.hash.substr(-3) && c.onBounce()
        })); else {
            var f = null, g = [];
            d(b, "mousemove", function (a) {
                g.unshift({x: a.clientX, y: a.clientY}), g = g.slice(0, 10)
            }), d(b, "mouseout", function (a) {
                if (!c.disabled) {
                    var b = a.relatedTarget || a.toElement;
                    (!b || "HTML" == b.nodeName) && a.clientY <= c.options.distance && 10 == g.length && g[0].y < g[9].y && g[9].y - g[0].y > c.options.sensitivity && c.onBounce()
                }
            }), this.options.scrollDelay && d(a, "scroll", function () {
                c.disabled || (c.disabled = !0, clearTimeout(f), f = setTimeout(function () {
                    c.disabled = !1
                }, c.options.scrollDelay))
            })
        }
    }, c.init = function (c) {
        c = c || {};
        var d;
        for (d in this.options) this.options.hasOwnProperty(d) && !c.hasOwnProperty(d) && (c[d] = this.options[d]);
        if (this.options = c, c.checkReferrer && b.referrer) {
            var e = b.createElement("a");
            e.href = b.referrer, e.host == a.location.host && this.data.set(c.storeName, "1")
        }
        return this.activated || !c.aggressive && this.data.get(c.storeName) || (this.activated = !0, this.activate("history" === c.method || "auto" === c.method && this.isMobile ? "history" : "mouse"), this.data.set(c.storeName, "1")), this
    }, c
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
                if (parseInt(retainful_premium_exit_intent_popup.show_when_its_coupon_applied) === 1) {
                    var code = retainful_premium_exit_intent_popup.coupon_code;
                    if (code !== "") {
                        var find_class = 'tr.cart-discount.coupon-' + code.toLowerCase();
                        var is_coupon_applied = $(document).find(find_class);//;$().('.coupon-' + code);
                        if (is_coupon_applied.length > 0) {
                            return false;
                        }
                    }
                }
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