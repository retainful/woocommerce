if (typeof (rnoc_jquery) == 'undefined') {
    rnoc_jquery = jQuery.noConflict();
}

rnoc = window.rnoc || {};

(function (rnoc) {
    rnoc_jquery(document).on('rnoc_save_settings', function (e, button_id) {
        let data = rnoc_jquery('.rnoc-main #retainful-settings-form').serializeArray()
        rnoc_jquery('.rnoc-main #retainful-settings-form #' + button_id).attr('disabled', true);
        rnoc_jquery.ajax({
            data: data,
            type: 'post',
            url: rnoc_localize_data.ajax_url,
            success: function (json) {
                alertify.set('notifier', 'position', 'top-right');
                rnoc_jquery('.rnoc-main #retainful-settings-form #' + button_id).attr('disabled', false);
                if (!json.success) {
                    if (typeof json.data === 'object') {
                        rnoc_jquery.each(json.data, function (index, value) {
                            // Check if the property is an array
                            if (Array.isArray(value)) {
                                // If it's an array, iterate over the array and display each item
                                value.forEach(function (item) {
                                    console.log(item);
                                    alertify.error(item);
                                });
                            } else {
                                // If it's not an array, display the value directly
                                console.log(value);
                                alertify.error(value);
                            }
                        });
                    } else {
                        alertify.error(json.data);
                    }
                } else {
                    alertify.success(json.data);
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
    rnoc_jquery(document).on('rnoc-app-connect', function (e, app_id, app_secret) {
        let rnoc_app_id = rnoc_jquery(app_id).val();
        let rnoc_app_secret = rnoc_jquery(app_secret).val();
        var message = rnoc_jquery(".retainful_app_validation_message");
        alertify.set('notifier', 'position', 'top-right');

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
                    alertify.error(response.error);
                    app_id.focus();
                    message.html('<p style="color:red;">' + response.error + '</p>');
                }
                if (response.success) {
                    alertify.success('Successfully connected to Retainful');
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