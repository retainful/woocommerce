(function ($) {
    var path = email_template.path;
    $(document).on('click', '.send-test-email', function () {
        var editorContent = "";
        var email_to = $("#test_mail_to").val();
        var subject = $("#subject").val();
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
    $(document).on('click', '.save-close-email-template', function () {
        var redirect_to = $(this).data('redirectto');
        saveTemplate(true, path, redirect_to);
    });
    $(document).on('click', '.save-email-template', function () {
        saveTemplate(false, path);
    });

    function saveTemplate(reload, path, redirect_to = "") {
        $('#email_template_body-tmce').trigger('click');
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
                        window.location.href = redirect_to;
                    }
                }
            }
        });
    }

    $(document).on('click', '.insert-template', function () {
        var template_id = $(this).data('template');
        var template_type = $(this).data('type');
        $.ajax({
            url: path,
            type: 'POST',
            dataType: "json",
            data: {action: 'rnoc_get_template_by_id', id: template_id, type: template_type},
            success: function (response) {
                if (response.success) {
                    var value = response.content;
                    value = value.replace(/\\/g, '');
                    if (tinyMCE.activeEditor != null) {
                        tinyMCE.activeEditor.setContent(value);
                    }
                }
            }
        });
    });
})(jQuery);