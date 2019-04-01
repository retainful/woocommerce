(function ($) {
    /*$('input#billing_email,input#billing_last_name,input#billing_first_name,input#billing_postcode,select#billing_country,select#billing_state').on('change', function () {*/
    $('input#billing_email').on('change', function () {
        var data = {
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
            ship_to_billing: $('#shiptobilling-checkbox').val(),
            action: 'save_retainful_guest_data'
        };
        $.post(wcal_guest_capture_params.ajax_url, data, function (response) {
        });
    });
})(jQuery);