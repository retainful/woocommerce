if (typeof (rnoc_jquery) == 'undefined') {
    rnoc_jquery = jQuery.noConflict();
}

rnoc = window.rnoc || {};

(function (rnoc) {
    rnoc_jquery(document).on('saveSettings', function (e, button_id) {
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
    rnoc_jquery(document).on('validate_app_key', function (e, app_id_button, app_secret_button) {
        if (button_id !== '') {
            rnoc_jquery('#' + button_id).attr('disabled', 'disabled');
        }
        let app_id = rnoc_jquery(app_id_button).val();
        let app_secret_key = rnoc_jquery(app_secret_button).val();
        let data = {
            action: "rnoc_validate_connection",
            rnoc_nonce: rnoc_localize_data.validate_app_key,
            app_id: app_id,
            app_secret: app_secret_key
        }
        rnoc_jquery.ajax({
            type: "POST",
            url: rnoc_localize_data.ajax_url,
            data: data,
            dataType: "json",
            success: function (json) {

            }
        });
    });
    rnoc_jquery(document).on('rnoc-app-disconnect', function (e, app_id_button, app_secret_button) {
        let app_id = rnoc_jquery(app_id_button).val();
        let app_secret_key = rnoc_jquery(app_secret_button).val();
        let data = {
            action: "rnoc_validate_connection",
            rnoc_nonce: rnoc_localize_data.rnoc_disconnect_license,
            app_id: app_id,
            app_secret: app_secret_key
        }
        rnoc_jquery.ajax({
            type: "POST",
            url: rnoc_localize_data.ajax_url,
            data: data,
            dataType: "json",
            success: function (json) {

            }
        });
    });
})(rnoc_jquery);