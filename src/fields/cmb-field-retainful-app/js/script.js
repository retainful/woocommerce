(function ($) {
    'use strict';
    $(document).ready(function () {
        var app_id = $("#rnoc_retainful_app_id").val();
        var app_secret = $("#rnoc_retainful_app_secret").val();
        var is_connected = $("#is_retainful_app_connected").val();
        if (app_id !== "" && app_secret !== "" && !is_connected) {
            $("#submit-cmb").attr('disabled', 'disabled');
        }
    });
    $(document).on('click', '#validate_retainful_app_id', function () {
        validateKey();
    });
    /*$(document).on('change', '#rnoc_retainful_app_id', function () {
        validateKey();
    });*/
    $(document).on('click', '#disconnect-app-btn', function () {
        $("#is_retainful_app_connected").val(0);
        $("#submit-cmb").trigger("click");
    });

    function validateKey() {
        var path = $("#retainful_ajax_path").val();
        var app_id = $("#rnoc_retainful_app_id");
        var app_secret = $("#rnoc_retainful_app_secret");
        var message = $(".retainful_app_validation_message");
        var submit_btn =  $("#submit-cmb");
        var validate_btn =  $("#validate_retainful_app_id");
        validate_btn.attr('disabled', 'disabled');
        $("#connect-to-retainful-loader").show();
        message.html('<p>&nbsp;</p>');
        $("#is_retainful_app_connected").val(0);
        if (app_id.val() !== ""|| app_secret.val() !== "") {
            submit_btn.attr('disabled', 'disabled');
        }
        $.ajax({
            url: path,
            type: 'POST',
            dataType: "json",
            data: {action: 'validate_app_key', app_id: app_id.val(),secret_key:app_secret.val()},
            success: function (response) {
                if (response.error && app_id.val() !== "") {
                    app_id.focus();
                    submit_btn.removeAttr('disabled');
                    $("#connect-to-retainful-loader").hide();
                    message.html('<p style="color:red;">' + response.error + '</p>');
                }
                if (response.error && app_id.val() === "") {
                    app_id.focus();
                    submit_btn.removeAttr('disabled');
                    $("#connect-to-retainful-loader").hide();
                    message.html('<p style="color:red;">' + response.error + '</p>');
                }
                if (response.success) {
                    $("#is_retainful_app_connected").val(1);
                    message.html('<p style="color:green;">' + response.success + '</p>');
                    submit_btn.removeAttr('disabled');
                    $("#connect-to-retainful-loader").hide();
                }
                validate_btn.removeAttr('disabled');
                submit_btn.trigger("click");
            }
        });
    }
})(jQuery);