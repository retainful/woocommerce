(function ($) {
    $(document).ready(function () {
        $(document).on("click", "#rnoc_retainful #submit-cmb", function (event) {
            var is_noc_explained = $('[name="rnoc_enable_next_order_coupon"]:checked').val();
            var noc_coupon_val = $('[name="rnoc_retainful_coupon_amount"]').val();
            if (parseInt(is_noc_explained) === 1 && (parseInt(noc_coupon_val) <= 0 || noc_coupon_val === "")) {
                alert("Please enter a coupon value. Example: 10 (to provide a 10% coupon for next order.)");
                return false;
            }
        })
        /*$(document).on("keypress keyup blur", "#app_coupon_value", function (event) {
            $(this).val($(this).val().replace(/[^0-9\.]/g, ''));
            if ((event.which !== 46 || $(this).val().indexOf('.') !== -1) && (event.which < 48 || event.which > 57)) {
                event.preventDefault();
            }
        });*/
        $(document).on("keypress keyup blur", "#app_coupon_expire_days,.number_only_field", function (event) {
            if ((event.which < 48 || event.which > 57)) {
                event.preventDefault();
            }
        });
        $(document).on("change", "#rnoc_cart_abandoned_time", function (event) {
            let value = $(this).val();
            let consider_time = parseInt(value);
            if (consider_time < 15) {
                $(this).val(15);
            }
        });
        $(document).on('click', '#validate-app-id-and-secret', function (event) {
            event.preventDefault();
            var app_id = $("#rnoc_retainful_app_id");
            var app_secret = $("#rnoc_retainful_app_secret");
            var action = $(this).data('action');
            var security = $(this).data('security');
            var message = $(".retainful_app_validation_message");
            var url = retainful_admin.ajax_endpoint.replace("{{action}}", action).replace("{{security}}", security);
            $(this).attr('disabled', true);
            if (app_id.val() === "" && app_secret.val() === "") {
                return false;
            }
            $('.error').html('');
            $.ajax({
                url: url,
                type: 'POST',
                async: false,
                dataType: "json",
                data: {app_id: app_id.val(), secret_key: app_secret.val()},
                success: function (response) {
                    if (response.error && typeof response.error === "object") {
                        var result = response.error;
                        for (const [key, value] of Object.entries(result)) {
                            var field = $('#error_' + key);
                            var res_html = '';
                            if (Array.isArray(value)) {
                                res_html = '<ul>';
                                var i;
                                for (i = 0; i < value.length; i++) {
                                    res_html += "<li>" + value[i] + "<li>";
                                }
                                res_html += '<ul>';
                            } else {
                                res_html = value;
                            }
                            field.html(res_html);
                        }
                        return false;
                    }
                    if (response.error && app_id.val() !== "") {
                        app_id.focus();
                        message.html('<p style="color:red;">' + response.error + '</p>');
                    }
                    if (response.success) {
                        message.html('<p style="color:green;">' + response.success + '</p>');
                    }
                    window.location.reload();
                },
                error: function () {
                    alert('Please try again later.');
                }
            });
            $(this).attr('disabled', false);
        });
        $(document).on('click', '#disconnect-app-btn', function (event) {
            event.preventDefault();
            var action = $(this).data('action');
            var security = $(this).data('security');
            var url = retainful_admin.ajax_endpoint.replace("{{action}}", action).replace("{{security}}", security);
            $(this).attr('disabled', true);
            $.ajax({
                url: url,
                type: 'POST',
                async: false,
                dataType: "json",
                data: {},
                success: function (response) {
                    window.location.reload();
                },
                error: function () {
                    alert('Please try again later.');
                }
            });
            $(this).attr('disabled', false);
        });
        $(document).on('submit', '#retainful-settings-form', function (event) {
            event.preventDefault();
            let submit = $(this).find(':submit');
            var action = submit.data('action');
            var security = submit.data('security');
            var url = retainful_admin.ajax_endpoint.replace("{{action}}", action).replace("{{security}}", security);
            submit.attr('disabled', true);
            $.ajax({
                url: url,
                type: 'POST',
                async: false,
                dataType: "json",
                data: $(this).serialize(),
                success: function (response) {
                    if (response.success) {
                        alert(response.data);
                    }
                },
                error: function () {
                    alert('Please try again later.');
                }
            });
            submit.attr('disabled', false);
        })
        $('.rnoc-multi-select').select2({width: '100%', placeholder: 'Select values'});
        $('.rnoc-select2-select').select2({width: '100%', placeholder: 'Select value'});
        $('.wc-product-search').each(function () {
            var select2_args = {
                width: '100%',
                allowClear: ($(this).data('allow_clear')),
                placeholder: $(this).data('placeholder'),
                minimumInputLength: $(this).data('minimum_input_length') ? $(this).data('minimum_input_length') : '3',
                escapeMarkup: function (m) {
                    return m;
                },
                ajax: {
                    url: retainful_admin.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            term: params.term,
                            action: $(this).data('action') || 'woocommerce_json_search_products_and_variations',
                            security: retainful_admin.search_products_nonce,
                            exclude: $(this).data('exclude'),
                            exclude_type: $(this).data('exclude_type'),
                            include: $(this).data('include'),
                            limit: $(this).data('limit'),
                            display_stock: $(this).data('display_stock')
                        };
                    },
                    processResults: function (data) {
                        var terms = [];
                        if (data) {
                            $.each(data, function (id, text) {
                                terms.push({id: id, text: text});
                            });
                        }
                        return {
                            results: terms
                        };
                    },
                    cache: true
                }
            };
            $(this).select2(select2_args);
        });
        $('.rnoc-color-field').wpColorPicker();
    });

})(jQuery);