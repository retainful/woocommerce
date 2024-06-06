if (typeof (rnoc_jquery) == 'undefined') {
    rnoc_jquery = jQuery.noConflict();
}

rnoc = window.rnoc || {};

(function (rnoc) {
    rnoc_jquery(document).on('rnoc_save_settings', function (e, button_id) {
        let data = rnoc_jquery('.rnoc-main #retainful-settings-form').serializeArray()
        data.push({name: 'rnoc_nonce', value: rnoc_localize_data.save_settings});
        data.push({name: 'action', value: 'rnoc_save_settings'});
        rnoc_jquery('.rnoc-main #retainful-settings-form #' + button_id).attr('disabled', true);
        rnoc_jquery.ajax({
            data: data,
            type: 'post',
            url: rnoc_localize_data.ajax_url,
            success: function (json) {
                alertify.set('notifier', 'position', 'top-right');
                rnoc_jquery('.rnoc-main #retainful-settings-form #' + button_id).attr('disabled', false);
                if (json.success) {
                    alertify.success(json.data.message);
                    setTimeout(function () {
                        location.reload();
                    }, 800);
                } else {
                    if (json.data && json.data.error_fields) {
                        rnoc_jquery.each(json.data.error_fields, function (index, value) {
                            alertify.error(value);
                        });
                    }
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
            action: "rnoc_connection",
            rnoc_nonce: rnoc_localize_data.app_connect,
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
                    if (response.error) {
                        alertify.error(response.error);
                    }

                }
                if (response.success) {
                    alertify.success('Successfully connected to Retainful');
                    message.html('<p style="color:green;">' + response.success + '</p>');
                    window.location.reload();
                } else {
                    if (response.data && response.data.error_fields) {
                        rnoc_jquery.each(response.data.error_fields, function (index, value) {
                            alertify.error(value);
                            message.html('<p style="color:red;">' + value + '</p>');
                        });
                    }
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
            action: "rnoc_disconnect",
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