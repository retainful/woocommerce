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
        $(document).on("click", "#rnoc_retainful #submit-cmb", function (event) {
            var is_noc_explained = $('[name="rnoc_enable_next_order_coupon"]:checked').val();
            var noc_coupon_val = $('[name="rnoc_retainful_coupon_amount"]').val();
            if (parseInt(is_noc_explained) === 1 && (parseInt(noc_coupon_val) <= 0 || noc_coupon_val === "")) {
                alert("Please enter a coupon value. Example: 10 (to provide a 10% coupon for next order.)");
                return false;
            }
        })
        $(document).on("change", "#exit_intent_popup_show_option", function (event) {
            var val = $(this).val();
            eip_show_option(val);
        })
        $(document).on("change", "#rnoc_show_woo_coupon", function (event) {
            var val = $(this).val();
            atcp_show_woo_coupon(val);
        })
        $(document).ready(function () {
            var val = $("#exit_intent_popup_show_option").val();
            eip_show_option(val);
            var show_coupon_field = $("#rnoc_show_woo_coupon").val();
            atcp_show_woo_coupon(show_coupon_field)
        })

        function eip_show_option(val) {
            var input = $("#show_x_times_per_page_val");
            if (val === "show_x_times_per_page") {
                input.show();
            } else {
                input.hide();
            }
        }

        function atcp_show_woo_coupon(val) {
            var popup = $("#row_atcp_template");
            var email = $(".row_atcp_mail_template");
            if (val === "instantly" || val === "both" || val === "auto_apply_and_redirect" || val === "auto_apply_and_redirect_cart") {
                popup.show();
            } else {
                popup.hide();
            }
            if (val === "send_via_email" || val === "both" || val === "send_mail_auto_apply_and_redirect" || val === "send_mail_auto_apply_and_redirect_cart") {
                email.show();
            } else {
                email.hide();
            }
        }

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
        $(document).on('change', '#exit_intent_popup_show_option', function () {
            if ($(this).val() === 'show_x_times_per_page') {
                $('#show_x_times_per_page_val').show();
            } else {
                $('#show_x_times_per_page_val').hide();
            }
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
    $(document).ready(function () {


        var editor = $("#rnoc_exit_intent_popup_template");
        $(document).on('click', '.insert-exit-intent-popup-template', function () {
            var template_id = $(this).data('template');
            $.ajax({
                url: retainful_admin.ajax_endpoint.replace("{{action}}", "rnocp_get_exit_intent_popup_template"),
                type: 'POST',
                dataType: "json",
                data: {
                    id: template_id
                },
                success: function (response) {
                    if (response.success) {
                        var value = response.content;
                        value = value.replace(/\\/g, '');
                        editor.val('');
                        editor.val(value);
                        viewPriview();
                        /*console.log(tinyMCE.activeEditor);
                        if (tinyMCE.activeEditor != null) {
                            tinyMCE.activeEditor.setContent(value);
                        }*/
                    }
                }
            });
        });
        $(document).on('click', '#rnoc_exit_intent_popup_template_show_preview', function () {
            viewPriview();
        });

        function viewPriview() {
            let val = editor.val();
            let custom_css = $("#rnoc_exit_intent_modal_custom_style").val();
            $("#custom-style-container").html(custom_css);
            $('#exit-intent-popup-preview').html(val);
        }

        viewPriview();
    });
})(jQuery);