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

    if (typeof retainful_premium_add_to_cart_collection_popup_condition.jquery_url !== "undefined") {
        getScript(retainful_premium_add_to_cart_collection_popup_condition.jquery_url, function () {
            if (typeof jQuery == 'undefined') {
                // Super failsafe - still somehow failed...
            } else {
                jQuery.noConflict();
                initJqueryRetainfulPopupJs();
            }
        });
    }
} else {
    initJqueryRetainfulPopupJs();
}

function initJqueryRetainfulPopupJs() {
    jQuery(function ($) {
        class addToCartPopup {
            constructor() {
                this.add_to_cart_popup_options = {};
            }

            /**
             * Check the browser supports local storage or not
             * @return {boolean}
             */
            isLocalStorageSupports() {
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

            /**
             * set the options needed for popup
             * @param options
             * @return {addToCartPopup}
             */
            setOptions(options = {}) {
                let default_options = {
                    "enable_add_to_cart_popup": "no",
                    "is_email_mandatory": "yes",
                    "no_thanks_action": "1",
                    "show_popup_until": "1",
                    "add_to_cart_popup_base_id": "#rnoc-add-to-cart-add-on"
                };
                this.add_to_cart_popup_default_options = {...default_options, ...options};
                return this;
            }

            /**
             * get options required by the user
             * @return {({no_thanks_action, enable_add_to_cart_popup, is_email_mandatory, show_popup_until}&{})|*}
             */
            getOptions() {
                return this.add_to_cart_popup_default_options;
            }

            /**
             * Display popup
             * @param thisButton
             */
            displayPopup(thisButton) {
                //Trigger event about showing popup
                $(document).trigger('retainful_showing_add_to_cart_popup', [thisButton]);
                thisButton.removeClass('loading');
                console.log(thisButton);
                thisButton.addClass('rnoc-popup-opener');
                let modal = this.getAddToCartPopupWindow();
                modal.css('display', 'block');
                localStorage.setItem('retainful_add_to_cart_opened', 'yes');
                $(document).trigger('retainful_showed_add_to_cart_popup', [modal, thisButton]);
            }

            /**
             * Closing popup
             */
            closePopup(event = "1") {
                if (this.isLocalStorageSupports()) {
                    localStorage.setItem('retainful_add_to_cart_popup_closed_by', event);
                    let modal = this.getAddToCartPopupWindow();
                    //Trigger event about hiding popup
                    $(document).trigger('retainful_closing_add_to_cart_popup', [modal]);
                    let popup_btn = $('.rnoc-popup-opener');
                    if (event !== "3") {
                        if (event === "2") {
                            localStorage.setItem('retainful_add_to_cart_popup_temporary_silent', "1");
                        }
                        //Allow only when no thanks button clicked
                        popup_btn.click();
                    }
                    popup_btn.removeClass('rnoc-popup-opener');
                    modal.css('display', 'none');
                    $(document).trigger('retainful_closed_add_to_cart_popup', [modal]);
                }
            }

            /**
             * getting popup object window
             * @return {*|jQuery|HTMLElement}
             */
            getAddToCartPopupWindow() {
                let options = this.getOptions();
                return $(options.add_to_cart_popup_base_id);
            }

            /**
             * Check is popup is enabled
             * @return {boolean}
             */
            isPopupEnabled() {
                let options = this.getOptions();
                return (options.enable_add_to_cart_popup === "yes")
            }

            /**
             * Validate is the entered content is email
             * @param email
             * @return {boolean}
             */
            isEmail(email) {
                var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
                return regex.test(email);
            }

            /**
             * check the popup needs to display or not
             * @return {boolean}
             */
            needPopup() {
                let options = this.getOptions();
                //Popup will not shown to non local storage supported browsers
                if (this.isLocalStorageSupports() && this.isPopupEnabled()) {
                    if (localStorage.getItem('retainful_add_to_cart_popup_temporary_silent') === "1") {
                        localStorage.removeItem('retainful_add_to_cart_popup_temporary_silent');
                        return false;
                    }
                    let popup_closed_by = localStorage.getItem("retainful_add_to_cart_popup_closed_by");
                    if (popup_closed_by === null) {
                        return true;
                    }
                    let return_val = true;
                    switch (options.show_popup_until) {
                        default:
                        case "1":/*Until provide email */
                            return_val = (popup_closed_by !== "1");
                            break;
                        case "2":/*Until No thanks button clicked */
                            return_val = (popup_closed_by !== "2");
                            //All adding item to cart when no thanks button is clicked
                            if (options.no_thanks_action === "1") {
                                return_val = false;
                            }
                            break;
                        case "3":/*Until close button clicked */
                            return_val = (popup_closed_by !== "3");
                            break;
                    }
                    return return_val;
                }
                return false;
            }

            /**
             * validate and sync the email
             * @param email
             * @param marketing_data
             * @param submit_button
             * @param event
             * @param error_container
             * @return {{error: boolean}}
             */
            syncEmail(email, marketing_data, submit_button, event, error_container) {
                event.preventDefault();
                error_container.hide();
                let options = this.getOptions();
                if (options.is_email_mandatory !== "yes") {
                    this.closePopup("1");
                    return {"error": false}
                }
                if (options.is_email_mandatory === "yes" && email === "") {
                    error_container.show();
                    return {"error": true}
                }
                if (options.is_email_mandatory === "yes" && !this.isEmail(email)) {
                    error_container.show();
                    return {"error": true}
                }
                if (options.is_email_mandatory === "yes" && this.isEmail(email)) {
                    submit_button.addClass('loading');
                    submit_button.attr('disabled', true);
                    let popup_data = {
                        email: email,
                        is_buyer_accepting_marketing: (marketing_data.is(':checked')) ? 1 : 0,
                        action: 'set_rnoc_guest_session'
                    };
                    let response = this.request(rnoc_ajax_url, popup_data);
                    if (!response.error) {
                        if (response.message !== '') {
                            //return {"error": true}
                        }
                        //instant popup support
                        if (response.show_coupon_instant_popup) {
                            sessionStorage.setItem("rnoc_instant_coupon_popup_showed", "no");
                            sessionStorage.setItem("rnoc_instant_coupon_popup_html", response.coupon_instant_popup_content);
                        }
                        this.closePopup("1");
                    } else {
                        if (response.message !== '') {
                            //return err
                        }
                    }
                    submit_button.removeClass('loading');
                    submit_button.attr('disabled', false);
                    return {"error": false};
                }
            }

            /**
             * request js
             * @param url
             * @param body
             * @param headers
             * @param data_type
             * @param method
             * @param async
             */
            request(url, body = {}, headers = {}, data_type = "json", method = "POST", async = false) {
                let msg = null;
                $.ajax({
                    url: url,
                    headers: headers,
                    method: method,
                    dataType: data_type,
                    data: body,
                    async: async,
                    success: function (response) {
                        msg = response;
                    },
                    error: function (response) {
                        msg = response;
                    }
                });
                return msg;
            }

            /**
             * Show the instant popup
             */
            showInstantPopup() {
                if (this.isLocalStorageSupports()) {
                    let is_popup_showed = sessionStorage.getItem("rnoc_instant_coupon_popup_showed");
                    if (is_popup_showed && is_popup_showed === "no") {
                        let popup_html = sessionStorage.getItem("rnoc_instant_coupon_popup_html");
                        $(popup_html).appendTo("body");
                        let instant_popup = this.getInstantPopup();
                        instant_popup.show();
                        sessionStorage.setItem("rnoc_instant_coupon_popup_showed", "yes");
                    }
                }
            }

            /**
             * Hide the instant popup
             */
            closeInstantPopup() {
                let instant_popup = this.getInstantPopup();
                instant_popup.hide();
            }

            /**
             * get the instant popup
             * @return {jQuery}
             */
            getInstantPopup() {
                return $("#rnoc-add-to-cart-add-on-instant-coupon").show();
            }
        }

        let add_to_cart_popup = new addToCartPopup().setOptions(retainful_premium_add_to_cart_collection_popup_condition);
        $(document).on('adding_to_cart', (eventData, thisButton) => {
            if (add_to_cart_popup.needPopup()) {
                add_to_cart_popup.displayPopup(thisButton);
                throw new Error('Retainful intercepts to show popup!');
            }
        });
        $(document).on('click', '#rnoc-add-to-cart-add-on .close-rnoc-popup', () => {
            add_to_cart_popup.closePopup("3");
        });
        $(document).on('click', '#rnoc-add-to-cart-add-on .no-thanks-close-popup', () => {
            add_to_cart_popup.closePopup("2");
        });
        $(document).on('click', '#rnoc-add-to-cart-add-on .rnoc-popup-btn', (event) => {
            let email = $('#rnoc-add-to-cart-add-on #rnoc-popup-email-field').val();
            let error_handler = $("#rnoc-add-to-cart-add-on  #rnoc-invalid-mail-message");
            var is_buyer_accepting_marketing = $('#rnoc-add-to-cart-add-on #rnoc-popup-buyer-accepts-marketing');
            add_to_cart_popup.syncEmail(email, is_buyer_accepting_marketing, $(this), event, error_handler);
        });
        $(document).on('added_to_cart', (fragment, cart_hash, this_button) => {
            add_to_cart_popup.showInstantPopup();
        });
        $(document).on('click', '#rnoc-add-to-cart-add-on-instant-coupon .close-rnoc-popup', () => {
            add_to_cart_popup.closeInstantPopup();
        });
        $(document).ready(() => {
            add_to_cart_popup.showInstantPopup()
        });
        $(document).on("click", ".single_add_to_cart_button,[name=add-to-cart]", function (event) {
            if (add_to_cart_popup.needPopup()) {
                event.preventDefault();
                add_to_cart_popup.displayPopup($(this));
            }
        });
        if (typeof retainful_premium_add_to_cart_collection !== "undefined" && typeof retainful_premium_add_to_cart_collection.add_to_cart_button_classes !== 'undefined') {
            var retainful_email_collection_support_class = retainful_premium_add_to_cart_collection.add_to_cart_button_classes;
            /*$(document).on('retainful_closing_add_to_cart_popup', function () {
            var button = $('.rnoc-popup-opener');
            var link = button.attr('href');
            if (typeof link !== "undefined") {
                window.location.href = link;
            } else {
                button.trigger('click');
                button.removeClass('rnoc-popup-opener');
            }
            });*/
            $(document).on('click', retainful_email_collection_support_class, function (event) {
                let this_button = $(this);
                let url = this_button.attr('href');
                let button_name = this_button.attr('name');
                if (url !== undefined && add_to_cart_popup.needPopup() && url.indexOf('add-to-cart') !== -1) {
                    event.preventDefault();
                    add_to_cart_popup.displayPopup(this_button);
                } else if (button_name !== undefined && button_name === "add-to-cart") {
                    event.preventDefault();
                    add_to_cart_popup.displayPopup(this_button);
                }
            });
        }
    });
}
