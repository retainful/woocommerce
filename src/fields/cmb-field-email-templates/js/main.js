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
})(jQuery);