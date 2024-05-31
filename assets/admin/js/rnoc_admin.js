if (typeof (rnoc_jquery) == 'undefined') {
    rnoc_jquery = jQuery.noConflict();
}

rnoc = window.rnoc || {};

(function (rnoc) {
    rnoc_jquery(document).on('save_settings', function (e, button_id) {
        let data = {
            form_data: rnoc_jquery('.rnoc-main #retainful-settings-form').serializeArray(),
            rnoc_nonce: rnoc_localize_data.rnoc_save_settings,
            action: "rnoc_validate_connection",
        }
        rnoc_jquery('.rnoc-main #retainful-settings-form #' + button_id).attr('disabled', true);
        rnoc_jquery.ajax({
            data: data,
            type: 'post',
            url: rnoc_localize_data.ajax_url,
            error: function (request, error) {
            },
            success: function (json) {
                // alertify.set('notifier', 'position', 'top-right');
                rnoc_jquery('.rnoc-main #retainful-settings-form #' + button_id).attr('disabled', false);
                if (json.error) {
                    if (json.message) {
                        alert(json.message);
                    }
                    if (json.field_error) {
                        rnoc_jquery.each(json.field_error, function (index, value) {
                            alert(value);
                            //alertify.error(value);
                        });
                    }
                } else {
                    alert(json.message);
                    setTimeout(function () {
                        location.reload();
                    }, 800);
                }
                if (json.redirect) {
                    window.location.href = json.redirect;
                }
            }
        });
    });
    rnoc_jquery(document).on('validate_app_key', function (e, app_id, app_secret) {
        let rnoc_app_id = rnoc_jquery(app_id).val();
        let rnoc_app_secret = rnoc_jquery(app_secret).val();
        var message = rnoc_jquery(".retainful_app_validation_message");
        rnoc_jquery(this).attr('disabled', true);
        if (rnoc_app_id === "" && rnoc_app_secret === "") {
            return false;
        }
        rnoc_jquery('.error').html('');
        let data = {
            action: "rnoc_validate_connection",
            rnoc_nonce: rnoc_localize_data.validate_app_key,
            app_id: rnoc_app_id,
            app_secret: rnoc_app_secret
        }
        rnoc_jquery.ajax({
            type: "POST",
            url: rnoc_localize_data.ajax_url,
            data: data,
            async: false,
            dataType: "json",
            success: function (response) {
                console.log(response);
                if (response.error && typeof response.error === "object") {
                    var result = response.error;
                    for (const [key, value] of Object.entries(result)) {
                        var field = rnoc_jquery('#error_' + key);
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
                if (response.error && app_id !== "") {
                    app_id.focus();
                    message.html('<p style="color:red;">' + response.error + '</p>');
                }
                if (response.success) {
                    message.html('<p style="color:green;">' + response.success + '</p>');
                    window.location.reload();
                }

            },
            error: function () {
                alert('Please try again later.');
            }
        });
    });
    rnoc_jquery(document).on('rnoc-app-disconnect', function (e, app_id, app_secret) {
        let rnoc_app_id = rnoc_jquery(app_id).val();
        let rnoc_app_secret = rnoc_jquery(app_secret).val();
        let data = {
            action: "rnoc_disconnect_connection",
            rnoc_nonce: rnoc_localize_data.disconnect_license,
            app_id: rnoc_app_id,
            app_secret: rnoc_app_secret
        }
        rnoc_jquery.ajax({
            type: "POST",
            url: rnoc_localize_data.ajax_url,
            data: data,
            dataType: "json",
            success: function (json) {
                window.location.reload();
            },
            error: function () {
                alert('Please try again later.');
            }
        });
    });
})(rnoc_jquery);