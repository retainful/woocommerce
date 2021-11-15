(function () {
    jQuery(document).on('updated_cart_totals',function () {
        var guest_data = {
            action: 'rnoc_update_cart_token',
            rnoc_nonce: retainful_cart_refresh_data.refresh_nonce
        };
        jQuery.post(retainful_cart_refresh_data.ajax_url, guest_data, function (response) {
            if(response.cart){
                jQuery(response.cart.id).html(response.cart.content);
            }
        });
    });
})(jQuery);
(function () {
    jQuery(document).on('updated_checkout',function () {
        var guest_data = {
            action: 'rnoc_update_cart_token',
            rnoc_nonce: retainful_cart_refresh_data.refresh_nonce
        };
        jQuery.post(retainful_cart_refresh_data.ajax_url, guest_data, function (response) {
            if(response.cart){
                jQuery(response.cart.id).html(response.cart.content);
            }
        });
    });
})(jQuery);