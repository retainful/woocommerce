(function ($) {
    $(document).on('click', '.reload-button', function () {
        window.location.reload();
    });

    $(document).on('change', '.is-template-active', function () {
        var is_active = 0;
        var id = $(this).data('template');
        if ($(this).prop("checked") === true) {
            is_active = 1;
        }
        $.ajax({
            url: email_template.path,
            type: 'POST',
            dataType: "json",
            data: {action: 'rnoc_activate_or_deactivate_template', id: id, is_active: is_active},
            success: function (response) {
                if (response.error) {
                    alert(response.message);
                }
            }
        });
    });

    $(document).on('click', '.send-test-email', function () {
        var editorContent = "";
        var email_to = $("#test_mail_to").val();
        var subject = $("#field_subject").val();
        if (tinyMCE.activeEditor != null) {
            editorContent = tinyMCE.activeEditor.getContent();
        }
        var loader = $("#sending_email_loader");
        loader.show();
        if (email_to !== "") {
            $.ajax({
                url: email_template.path,
                type: 'POST',
                dataType: "json",
                data: {action: 'rnoc_send_sample_email', email_to: email_to, body: editorContent, subject: subject},
                success: function (response) {
                    if (response.error) {
                        alert(response.message);
                    }
                    loader.hide();
                }
            });
        } else {
            alert(email_template.email_field_empty);
            loader.hide();
        }
    });

    $(document).on('click', '.remove-email-template', function () {
        if (confirm(email_template.sure_msg)) {
            var id = $(this).data('template');
            $.ajax({
                url: email_template.path,
                type: 'POST',
                dataType: "json",
                data: {action: 'rnoc_remove_template', id: id},
                success: function (response) {
                    if (response.error) {
                        alert(response.message);
                    } else {
                        $("#template-no-" + id).remove();
                    }
                }
            });
        }
    });

    $(document).on('click', '.edit-email-template', function () {
        var id = $(this).data('template');
        $.ajax({
            url: email_template.path,
            type: 'POST',
            dataType: "json",
            data: {action: 'rnoc_edit_template', id: id},
            success: function (response) {
                if (response.error) {
                    alert(response.message);
                } else {
                    console.log(response.template);
                    $.each(response.template, function (key, value) {
                        if (key !== "body") {
                            $("#field_" + key).val(value);
                        } else {
                            if (key === "body") {
                                value = value.replace(/\\/g, '');
                                if (tinyMCE.activeEditor != null) {
                                    tinyMCE.activeEditor.setContent(value);
                                }
                            }
                        }
                        toggleForm();
                    });
                }
            }
        });
    });
    $(document).on('click', '.create-or-add-template', function () {
        toggleForm();
    });

    function toggleForm() {
        $(".email-templates-list").hide();
        $(".create-or-edit-template-form").show();
    }

    $(document).on('click', '.save-close-email-template', function () {
        var path = $(this).data('path');
        saveTemplate(true, path);
    });
    $(document).on('click', '.save-email-template', function () {
        var path = $(this).data('path');
        saveTemplate(false, path);
    });

    function saveTemplate(reload, path) {
        var template = $('.create-or-edit-template-form').find('select, textarea, input');
        var formdata = template.serializeArray();
        var data = {};
        $(formdata).each(function (index, obj) {
            if (obj.name !== "email_template_body") {
                data[obj.name] = obj.value;
            } else {
                var editorContent = "";
                if (tinyMCE.activeEditor != null) {
                    editorContent = tinyMCE.activeEditor.getContent();
                }
                data['body'] = editorContent;
            }
        });

        $.ajax({
            url: path,
            type: 'POST',
            dataType: "json",
            data: {action: 'rnoc_save_email_template', data: data},
            success: function (response) {
                if (response.success) {
                    $("#field_id").val(response.id);
                    alert(response.message);
                    if (reload) {
                        window.location.reload();
                    }
                }
            }
        });
    }
})(jQuery);