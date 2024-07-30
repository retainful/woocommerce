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
                rnoc_jquery('.rnoc-main #retainful-settings-form #' + button_id).attr('disabled', false);
                if (json.success) {
                    createToast(json.data.message, 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 800);
                } else {
                    if (json.data && json.data.error_fields) {
                        rnoc_jquery.each(json.data.error_fields, function (index, value) {
                            let error_field = rnoc_jquery('#' + index);
                            error_field.addClass('input-error');
                            if (rnoc_jquery('.rnoc-error').length <= 0) {
                                error_field.after('<div><p class="rnoc-error">' + value + '</p></div>');
                            }
                            createToast(value);
                        });
                    }
                }
            }
        });
    });
    rnoc_jquery(document).on('rnoc-app-connect', function (e, app_id, app_secret) {
        let button = rnoc_jquery('#validate-app-id-and-secret');
        let loading_icon = button.find('.loading-icon');

        button.attr('disabled', true).css('pointer-events', 'none');
        loading_icon.addClass('rnoc-loader');
        // Retrieve values
        let rnoc_app_id = rnoc_jquery('#' + app_id).val();
        let rnoc_app_secret = rnoc_jquery('#' + app_secret).val();
        let message = rnoc_jquery(".retainful_app_validation_message");

        // Validate inputs
        if (!rnoc_app_id || !rnoc_app_secret) {
            // Re-enable the button if validation fails
            button.attr('disabled', false).css('pointer-events', 'auto');
            loading_icon.removeClass('rnoc-loader');
            return false;
        }

        // Prepare data for AJAX request
        let data = {
            action: "rnoc_connection",
            rnoc_nonce: rnoc_localize_data.app_connect,
            app_id: rnoc_app_id,
            app_secret: rnoc_app_secret
        };
        // AJAX request
        rnoc_jquery.ajax({
            type: "POST",
            url: rnoc_localize_data.ajax_url,
            data: data,
            dataType: "json",
            success: function (response) {
                button.prop('disabled', false).css('pointer-events', 'auto'); // Re-enable the button
                loading_icon.removeClass('rnoc-loader');

                if (response.error && typeof response.error === "object") {
                    var result = response.error;
                    for (const [key, value] of Object.entries(result)) {
                        var field = rnoc_jquery('#error_' + key);
                        var res_html = '';
                        if (Array.isArray(value)) {
                            res_html = '<ul>';
                            for (let i = 0; i < value.length; i++) {
                                res_html += "<li>" + value[i] + "</li>";
                            }
                            res_html += '</ul>';
                        } else {
                            res_html = value;
                        }
                        field.html(res_html);
                    }
                    return false;
                }

                if (response.error) {
                    alertify.error(response.error);
                    return false;
                }

                if (response.success) {
                    var success_message = response.data.message ? response.data.message : response.success;
                    createToast(success_message, 'success');
                    message.html('<p style="color:green;">' + success_message + '</p>');
                    // Optional: Reload the page to reflect changes
                    window.location.reload();
                } else {
                    if (response.data && response.data.error_fields) {
                        rnoc_jquery.each(response.data.error_fields, function (index, value) {
                            createToast(response.data.error_fields, 'error');
                            message.html('<p style="color:red;">' + value + '</p>');
                        });
                    } else {
                        createToast(response.data.message);
                        message.html('<p style="color:red;">' + response.data.message + '</p>');
                    }
                }
            },
            error: function (response) {
                button.attr('disabled', false).css('pointer-events', 'auto'); // Re-enable the button
                loading_icon.removeClass('rnoc-loader');
                createToast(response.statusText);
            }
        });
    });

    rnoc_jquery(document).on('rnoc-app-disconnect', function (e, app_id, app_secret) {
        let button = rnoc_jquery('#disconnect-app-btn');
        let loading_icon = button.find('.loading-icon');

        button.attr('disabled', true).css('pointer-events', 'none');
        loading_icon.addClass('rnoc-loader');
        // Disable the button and set pointer-events to none
        loading_icon.addClass('rnoc-loader');
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
            success: function (response) {
                button.attr('disabled', false).css('pointer-events', 'auto'); // Re-enable the button
                loading_icon.removeClass('rnoc-loader');
                createToast(response.data.message, 'success');
                window.location.reload();
            },
            error: function (response) {
                button.attr('disabled', false).css('pointer-events', 'auto'); // Re-enable the button
                loading_icon.removeClass('rnoc-loader');
                createToast(response.statusText);
            }
        });
    });
})(rnoc_jquery);


function createToast(text, type = 'error') {
    if (type !== 'success' && type !== 'error') {
        return;
    }
    var icon = '';
    var title = '';
    if (type === 'success') {
        icon = 'tick-icon';
        title = rnoc_localize_data.rnoc_success;
    } else if (type === 'error') {
        icon = 'close-icon';
        title = rnoc_localize_data.rnoc_error;
    }

    let newToast = document.createElement('div');
    newToast.classList.add('rnoc_notification');
    newToast.innerHTML = `
    <div class="toast ${type}">
        <i class='${icon}'></i>
        <div class="content">
            <div class="title">${title}</div>
            <span class="toast-msg">${text}</span>
        </div>
        <i class='bx bx-x' onclick="(this.parentElement).remove()"></i>
    </div>
    `;
    document.querySelector('.rnoc-main').appendChild(newToast);

    //Auto remove after 5 seconds
    newToast.timeOut = setTimeout(function () {
        newToast.remove();
    }, 5000);
}