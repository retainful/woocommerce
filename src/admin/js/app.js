(function ($) {
    $(document).ready(function () {
        var search_coupon_field = $(".search-and-select-coupon");
        search_coupon_field.wrap('<div class="rnoc-autocomplete"></div>');
        var auto_complete_holder = $("<ul class='rnoc-auto-complete-results'></ul>");
        search_coupon_field.after(auto_complete_holder);
        search_coupon_field.on("keyup", function () {
            var code = $(this).val();
            if (code.length >= 3) {
                var url = retainful_admin.ajax_endpoint.replace("{{action}}", "rnoc_get_search_coupon").replace("{{security}}", retainful_admin.security.get_search_coupon);
                url = url + "&coupon=" + code;
                $.get(url, function (response) {
                    if (response.success) {
                        auto_complete_holder.html('');
                        var items = response.data;
                        for (const [key, value] of Object.entries(items)) {
                            var li = $("<li class='rnoc-coupon-sugg' data-code='" + key + "'>" + value + "</li>");
                            li.on('click', function () {
                                var code = $(this).data("code");
                                search_coupon_field.val(code);
                                auto_complete_holder.html('');
                            })
                            auto_complete_holder.append(li);
                        }
                    } else {
                        auto_complete_holder.html('');
                    }
                })
            } else if (code.length > 0) {
                auto_complete_holder.html('<li>Type 3 or more characters to search</li>');
            } else {
                auto_complete_holder.html('');
            }
        })
    });
    $(document).ready(function () {

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
        $(document).on('click', '#generate-webhook-btn', function (event){
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
                    if(response.success) {
                        alert(response.message);
                    }else {
                        alert(response.message);
                    }
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
            $(".error").html('');
            $('.switch-tmce').trigger('click');
            $.ajax({
                url: url,
                type: 'POST',
                async: false,
                dataType: "json",
                data: $(this).serialize(),
                success: function (response) {
                    if (!response.success) {
                        for (const [key, value] of Object.entries(response.data)) {
                            var field = $('[name="' + key + '"]');
                            if (field.length === 0) {
                                var field_name = key.replace('.*.', '[0][');
                                field = $('[name="' + field_name + ']"]');
                            }
                            var res_html = '';
                            var td = field.closest('td');
                            if (Array.isArray(value)) {
                                res_html = '<ul style="color: red;" class="error">';
                                var i;
                                for (i = 0; i < value.length; i++) {
                                    res_html += "<li>" + value[i] + "<li>";
                                }
                                res_html += '<ul>';
                            } else {
                                res_html = '<p style="color: red;" class="error">' + value + '</p>';
                            }
                            td.append(res_html);
                        }
                        alert('Settings not saved! Please fix all errors and click save!')
                    }
                    if (response.success) {
                        alert(response.data);
                    }
                },
                error: function () {
                    alert('Please try again later.');
                }
            });
            submit.attr('disabled', false);
        });

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