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
                initJqueryRetainfulAddToCartCouponPopupJs();
            }
        });
    }
} else {
    initJqueryRetainfulAddToCartCouponPopupJs();
}

function initJqueryRetainfulAddToCartCouponPopupJs() {
    jQuery(function ($) {
        class addToCartCouponPopup {
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
                    /*let popup_data_encoded = sessionStorage.getItem("rnoc_add_to_cart_popup_data");
                    if(popup_data_encoded) {
                        let popup_data = JSON.parse(popup_data_encoded);
                        let response = this.request(rnoc_ajax_url, popup_data);
                        if(!response.error){
                            sessionStorage.removeItem("rnoc_add_to_cart_popup_data");
                        }
                    }*/
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

        let add_to_cart_coupon_popup = new addToCartCouponPopup();
        $(document).on('added_to_cart', (fragment, cart_hash, this_button) => {
            add_to_cart_coupon_popup.showInstantPopup();
        });
        $(document).ready(() => {
            add_to_cart_coupon_popup.showInstantPopup()
        });
        $(document).on('click', '#rnoc-add-to-cart-add-on-instant-coupon .close-rnoc-popup', () => {
            add_to_cart_coupon_popup.closeInstantPopup();
        });
    });
}
