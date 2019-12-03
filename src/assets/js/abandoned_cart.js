(function ($) {
    class Retainful {
        /**
         * Constructor
         * @param end_point
         * @param public_key
         */
        constructor(end_point = null, public_key = null) {
            this.end_point = end_point;
            this.public_key = public_key;
            this.abandoned_cart_data = null;
            this.cart_hash = null;
            this.cart_tracking_element_id = "retainful-abandoned-cart-data";
            this.async_request = true;
            this.previous_cart_hash = null;
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
         * set cart tracking element ID
         * @param element_id
         * @return {Retainful}
         */
        setCartTrackingElementId(element_id) {
            this.cart_tracking_element_id = element_id;
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
                this.abandoned_cart_data = cart_details.data;
                this.cart_hash = cart_details.cart_hash;
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
         * sync cart to api
         */
        syncCart() {
            let cart_data = this.getAbandonedCartData();
            let cart_hash = this.getCartHash();
            if (cart_data !== undefined && cart_data !== null && cart_data !== "" && cart_hash !== this.previous_cart_hash) {
                this.previous_cart_hash = cart_hash;
                let headers = {"app_id": this.getPublicKey(), "Content-Type": "application/json"};
                let body = {"data": cart_data};
                this.request(this.getEndPoint(), JSON.stringify(body), headers, 'json', 'POST', this.async_request);
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
    }

    let retainful = new Retainful(retainful_cart_data.api_url, retainful_cart_data.public_key).setCartTrackingElementId(retainful_cart_data.tracking_element_selector);
    if (retainful_cart_data.cart_tracking_engine === "js") {
        retainful.initCartTracking();
    }

    $('input#billing_email,input#billing_last_name,input#billing_first_name,input#billing_postcode,select#billing_country,select#billing_state').on('change', function () {
        /*$('input#billing_email').on('change', function () {*/
        if ($('#billing_email').val() !== "") {
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
            retainful.request(retainful_cart_data.ajax_url, guest_data, {}, "json", "POST", true);
        }
    });


})(jQuery);