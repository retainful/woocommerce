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
window.is_rnoc_mobil_scroll_popup_showed = false;
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

function isMobileDevice() {
    let isMobile = false;
    let a = navigator.userAgent || navigator.vendor || window.opera;
    if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0, 4))) {
        isMobile = true;
    } else if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|android|ipad|playbook|silk/i.test(a) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0, 4))) {
        isMobile = true
    }
    return (screen.width < 768 || window.screen.width < 768 || isMobile);
}

function isTabletDevice() {
    return (screen.width < 480);
}

function amountScrolled($) {
    var win_height = $(window).height();
    var doc_height = $(document).height();
    var scroll_top = $(window).scrollTop();
    var track_length = doc_height - win_height
    return Math.floor(scroll_top / track_length * 100) // gets percentage scrolled (ie: 80 or NaN if tracklength == 0)
}

function initJqueryRetainfulExitIntentPopupJs() {
    jQuery(function ($) {
        window.retainful_exit_intent_popup_status = true;
        $(document).on('rnoc_show_exit_intent_popup', function () {
            showRetainfulExitIntentPopup();
        });
        if (isMobileDevice()) {
            var delay = parseInt(retainful_premium_exit_intent_popup.delay);
            if (delay > 0) {
                setTimeout(function () {
                    showRetainfulExitIntentPopup();
                }, delay * 1000);
            }
            var rnoc_distance = parseInt(retainful_premium_exit_intent_popup.distance);
            if (rnoc_distance > 0) {
                $(window).on("scroll", function () {
                    var scrolled_distance = amountScrolled($);
                    if ((scrolled_distance > rnoc_distance) && window.is_rnoc_mobil_scroll_popup_showed === false) {
                        window.is_rnoc_mobil_scroll_popup_showed = true;
                        showRetainfulExitIntentPopup();
                    }
                });
            }
        }
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