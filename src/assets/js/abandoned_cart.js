document.addEventListener("DOMContentLoaded", function () {
    let default_retainful_cart_data = {
        "ajax_url": "",
        "jquery_url": "https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js",
        "ip": "",
        "version": "",
        "public_key": "",
        "api_url": "",
        "tracking_element_selector": "retainful-abandoned-cart-data",
        "cart_tracking_engine": "js",
    };
    let rnoc_cart_js_data = {};
    if (typeof retainful_cart_data === "undefined") {
        rnoc_cart_js_data = {...default_retainful_cart_data, ...{}};
    } else {
        rnoc_cart_js_data = {...default_retainful_cart_data, ...retainful_cart_data}
    }
    if (typeof jQuery == "undefined") {
        function getScript(url, success) {
            let script = document.createElement('script');
            script.src = url;
            let head = document.getElementsByTagName('head')[0],
                done = false;
            // Attach handlers for all browsers
            script.onload = script.onreadystatechange = function () {
                if (!done && (!this.readyState || this.readyState === 'loaded' || this.readyState === 'complete')) {
                    done = true;
                    // callback function provided as param
                    success();
                    script.onload = script.onreadystatechange = null;
                    head.removeChild(script);
                }
            };
            head.appendChild(script);
        }

        getScript(rnoc_cart_js_data.jquery_url, function () {
            if (typeof jQuery == "undefined") {
                console.log("retainful unable to include jQuery");
            } else {
                jQuery.noConflict();
                initJqueryRetainfulAbandonedCartsTracking(rnoc_cart_js_data);
            }
        });
    } else {
        // alert('defined');
        initJqueryRetainfulAbandonedCartsTracking(rnoc_cart_js_data);
    }
});

function initJqueryRetainfulAbandonedCartsTracking(rnoc_cart_js_data) {
    jQuery(function ($) {
        class Retainful {
            /**
             * Constructor
             * @param end_point
             * @param public_key
             */
            constructor(end_point = null, public_key = null) {
                this.ajax_url = null;
                this.is_force_synced = false;
                this.end_point = end_point;
                this.public_key = public_key;
                this.abandoned_cart_data = null;
                this.cart_token = null;
                this.ip = null;
                this.version = null;
                this.cart_hash = null;
                this.force_refresh_carts = null;
                this.cart_tracking_element_id = "retainful-abandoned-cart-data";
                this.async_request = true;
                this.previous_cart_hash = null;
            }

            /**
             * Set IP for tracking
             * @param ip
             * @returns {Retainful}
             */
            setIp(ip) {
                if (ip === undefined) {
                    ip = null;
                }
                this.ip = ip;
                return this;
            }

            /**
             * Set version for tracking
             * @param version
             * @returns {Retainful}
             */
            setVersion(version) {
                if (version === undefined) {
                    version = null;
                }
                this.version = version;
                return this;
            }

            /**
             * Set endpoint for tracking
             * @param end_point
             * @returns {Retainful}
             */
            setEndPoint(end_point) {
                this.end_point = end_point;
                return this;
            }

            /**
             * Get endpoint for tracking
             * @return string
             */
            getEndPoint() {
                return this.end_point
            }

            /**
             * Set public key for the api
             * @param public_key
             * @returns {Retainful}
             */
            setPublicKey(public_key) {
                this.public_key = public_key;
                return this;
            }

            /**
             * Get public key for tracking
             * @returns string
             */
            getPublicKey() {
                return this.public_key
            }

            /**
             * Get public key for tracking
             * @returns string
             */
            getIp() {
                return this.ip;
            }

            /**
             * Get public key for tracking
             * @returns string
             */
            getVersion() {
                return this.version
            }

            /**
             * set cart tracking element ID
             * @param element_id
             * @return {Retainful}
             */
            setCartTrackingElementId(element_id) {
                this.cart_tracking_element_id = element_id;
                return this;
            }

            /**
             * set the ajax url
             * @param url
             * @returns {Retainful}
             */
            setAjaxUrl(url) {
                this.ajax_url = url;
                return this;
            }

            /**
             * cart tracking element ID
             * @return {string}
             */
            getCartTrackingElementId() {
                return this.cart_tracking_element_id;
            }

            /**
             * Set the abandoned cart data
             * @param cart_data
             * @return {Retainful}
             */
            setAbandonedCartData(cart_data = null) {
                if (cart_data !== null) {
                    this.abandoned_cart_data = cart_data;
                } else {
                    let element_id = this.getCartTrackingElementId();
                    let data = $("#" + element_id).html();
                    let cart_details = JSON.parse(data);
                    this.abandoned_cart_data = (cart_details.data !== undefined) ? cart_details.data : null;
                    this.cart_hash = (cart_details.cart_hash !== undefined) ? cart_details.cart_hash : null;
                    this.cart_token = (cart_details.cart_token !== undefined) ? cart_details.cart_token : null;
                    this.force_refresh_carts = (cart_details.force_refresh_carts !== undefined) ? cart_details.force_refresh_carts : null;
                    if (this.isLocalStorageSupports() && this.cart_token !== null) {
                        let old_cart_token_history = localStorage.getItem('retainful_ac_cart_token_history');
                        let old_cart_token = localStorage.getItem('retainful_ac_cart_token');
                        let timestamp_in_ms = window.performance && window.performance.now && window.performance.timing && window.performance.timing.navigationStart ? window.performance.now() + window.performance.timing.navigationStart : Date.now();
                        if (old_cart_token_history === null) {
                            let cart_token_history = [];
                            cart_token_history.push({"time": timestamp_in_ms, "token": this.cart_token});
                            localStorage.setItem('retainful_ac_cart_token_history', JSON.stringify(cart_token_history));
                        } else {
                            let cart_token_history = JSON.parse(old_cart_token_history);
                            if (old_cart_token !== this.cart_token) {
                                cart_token_history.push({"time": timestamp_in_ms, "token": this.cart_token});
                            }
                            localStorage.setItem('retainful_ac_cart_token_history', JSON.stringify(cart_token_history));
                        }
                        localStorage.setItem('retainful_ac_cart_token', this.cart_token);
                    }
                }
                return this;
            }

            /**
             * get abandoned cart data
             * @return {null}
             */
            getAbandonedCartData() {
                this.setAbandonedCartData();
                return this.abandoned_cart_data;
            }

            /**
             * get abandoned cart hash data
             * @return {null}
             */
            getCartHash() {
                return this.cart_hash;
            }

            /**
             * get cart token
             * @return {null}
             */
            getCartToken() {
                let cart_token = localStorage.getItem('retainful_ac_cart_token');
                if (cart_token !== null || cart_token !== '') {
                    this.setCartToken(cart_token);
                }
                return this.cart_token;
            }

            /**
             * set the cart token
             * @param cart_token
             */
            setCartToken(cart_token) {
                this.cart_token = cart_token;
            }

            /**
             * Init cart tracking the hooks
             * @return {Retainful}
             */
            initCartTracking() {
                let retainful = this;
                $(document.body).on("added_to_cart removed_from_cart updated_cart_totals updated_shipping_method applied_coupon removed_coupon updated_checkout", function () {
                    retainful.syncCart();
                }).on("wc_fragments_refreshed", function () {
                    retainful.syncCart();
                }).on("wc_fragments_loaded", function () {
                    retainful.syncCart();
                });
                return this;
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
             * sync cart to api
             */
            syncCart(cart_data = null, force_sync = false) {
                if (cart_data === null) {
                    cart_data = this.getAbandonedCartData();
                }
                let cart_hash = this.getCartHash();
                if ((cart_data !== undefined && cart_data !== null && cart_data !== "" && cart_hash !== this.previous_cart_hash) || (force_sync)) {
                    this.previous_cart_hash = cart_hash;
                    let headers = {
                        "app_id": this.getPublicKey(),
                        "Content-Type": "application/json",
                        "X-Client-Referrer-IP": this.getIp(),
                        "X-Retainful-Version": this.getVersion(),
                        "X-Cart-Token": this.getCartToken(),
                        "Cart-Token": this.getCartToken()
                    };
                    let body = {"data": cart_data};
                    this.request(this.getEndPoint(), JSON.stringify(body), headers, 'json', 'POST', this.async_request);
                }
                if (this.force_refresh_carts !== null && !this.is_force_synced) {
                    this.is_force_synced = true;
                    let response = retainful.request(this.ajax_url, {action: 'rnoc_track_user_data'}, {}, "json", "POST", false);
                    if (response.success && response.data) {
                        retainful.syncCart(response.data, true);
                    }
                }
            }

            /**
             * request api
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

            validateEmail(value) {
                var valid = true;
                if (value.indexOf('@') === -1) {
                    valid = false;
                } else {
                    var parts = value.split('@');
                    var domain = parts[1];
                    if (domain.indexOf('.') === -1) {
                        valid = false;
                    } else {
                        var domainParts = domain.split('.');
                        var ext = domainParts[1];
                        if (ext.length > 14 || ext.length < 2) {
                            valid = false;
                        }
                    }
                }
                return valid;
            }
        }

        let retainful = new Retainful(rnoc_cart_js_data.api_url, rnoc_cart_js_data.public_key).setCartTrackingElementId(rnoc_cart_js_data.tracking_element_selector);
        if (rnoc_cart_js_data.cart_tracking_engine === "js") {
            retainful.setAjaxUrl(rnoc_cart_js_data.ajax_url);
            retainful.setIp(rnoc_cart_js_data.ip);
            retainful.setVersion(rnoc_cart_js_data.version);
            retainful.initCartTracking();
            $(document).ready(function () {
                retainful.syncCart();
            });
        }
        if (rnoc_cart_js_data.cart !== undefined) {
            let tracking_content = '<div id="' + rnoc_cart_js_data.tracking_element_selector + '" style="display:none;">' + JSON.stringify(rnoc_cart_js_data.cart) + '</div>';
            $(tracking_content).appendTo('body');
        }
        $('input#billing_email,input#billing_first_name,input#billing_last_name,input#billing_phone').on('change', function () {
            var rnoc_phone = $("#billing_phone").val();
            var rnoc_email = $("#billing_email").val();
            var ship_to_bill = $("#ship-to-different-address-checkbox:checked").length;
            var guest_data = {
                billing_first_name: $('#billing_first_name').val(),
                billing_last_name: $('#billing_last_name').val(),
                billing_company: $('#billing_company').val(),
                billing_address_1: $('#billing_address_1').val(),
                billing_address_2: $('#billing_address_2').val(),
                billing_city: $('#billing_city').val(),
                billing_state: $('#billing_state').val(),
                billing_postcode: $('#billing_postcode').val(),
                billing_country: $('#billing_country').val(),
                billing_phone: $('#billing_phone').val(),
                billing_email: $('#billing_email').val(),
                ship_to_billing: ship_to_bill,
                order_notes: $('#order_comments').val(),
                shipping_first_name: $('#shipping_first_name').val(),
                shipping_last_name: $('#shipping_last_name').val(),
                shipping_company: $('#shipping_company').val(),
                shipping_address_1: $('#shipping_address_1').val(),
                shipping_address_2: $('#shipping_address_2').val(),
                shipping_city: $('#shipping_city').val(),
                shipping_state: $('#shipping_state').val(),
                shipping_postcode: $('#shipping_postcode').val(),
                shipping_country: $('#shipping_country').val(),
                action: 'rnoc_track_user_data'
            };
            updateCheckout(rnoc_email, rnoc_phone, guest_data);
        });
        $('.wp-block-woocommerce-checkout input#email,.wp-block-woocommerce-checkout input#phone').on('change', function () {
            var rnoc_email = $(".wp-block-woocommerce-checkout input#email").val();
            var rnoc_phone = $(".wp-block-woocommerce-checkout input#phone").val();
            var guest_data = {
                billing_first_name: $('.wp-block-woocommerce-checkout #billing-first_name').val(),
                billing_last_name: $('.wp-block-woocommerce-checkout #billing-last_name').val(),
                billing_address_1: $('.wp-block-woocommerce-checkout #billing-address_1').val(),
                billing_address_2: $('.wp-block-woocommerce-checkout #billing-address_2').val(),
                billing_city: $('.wp-block-woocommerce-checkout #billing-city').val(),
                billing_postcode: $('.wp-block-woocommerce-checkout #billing-postcode').val(),
                billing_phone: rnoc_phone,
                billing_email: rnoc_email,
                ship_to_billing: 1,
                action: 'rnoc_track_user_data'
            };
            updateCheckout(rnoc_email, rnoc_phone, guest_data);
        });

        function updateCheckout(rnoc_email, rnoc_phone, guest_data) {
            let msg = null;
            if (typeof rnoc_email === 'undefined') {
                return;
            }
            var atposition = rnoc_email.indexOf("@");
            var dotposition = rnoc_email.lastIndexOf(".");
            if (typeof rnoc_phone === 'undefined' || rnoc_phone === null) { //If phone number field does not exist on the Checkout form
                rnoc_phone = '';
            }
            /*$('input#billing_email').on('change', function () {*/
            if (!(atposition < 1 || dotposition < atposition + 2 || dotposition + 2 >= rnoc_email.length) || rnoc_phone.length >= 1) {
                Object.keys(guest_data).forEach(key => guest_data[key] === undefined && delete guest_data[key]);
                if (retainful.validateEmail(rnoc_email) || rnoc_phone.length >= 4) {
                    sessionStorage.setItem("rnocp_is_add_to_cart_popup_email_entered", "1");
                    $.ajax({
                        url: rnoc_cart_js_data.ajax_url,
                        headers: {},
                        method: 'POST',
                        dataType: 'json',
                        data: guest_data,
                        async: true,
                        success: function (response) {
                            if (response.success && response.data) {
                                retainful.syncCart(response.data, true);
                            }
                        },
                        error: function (response) {
                            msg = response;
                        }
                    });
                } else {
                    sessionStorage.setItem("rnocp_is_add_to_cart_popup_email_entered", "0");
                    //console.log('Email validation failed');
                }

            } else {
                //console.log('Not a valid email yet');
            }
        }
    });
}