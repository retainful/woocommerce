(function ($) {
    'use strict';
    $(document).ready(function () {
        let app_id = $("#retainful_app_id").val();
        let is_connected = $("#is_retainful_app_connected").val();
        if (app_id !== "" && !is_connected) {
            $("#submit-cmb").hide();
        }
    });
    $(document).on('click', '#validate_retainful_app_id', function () {
        validateKey();
    });
    $(document).on('change', '#retainful_app_id', function () {
        validateKey();
    });

    function validateKey() {
        let path = $("#retainful_ajax_path").val();
        let app_id = $("#retainful_app_id");
        let message = $(".retainful_app_validation_message");
        message.html('<p>&nbsp;</p>');
        $("#is_retainful_app_connected").val(0);
        if(app_id.val() !== ""){
            $("#submit-cmb").hide();
        }
        $.ajax({
            url: path,
            type: 'POST',
            dataType: "json",
            data: {action: 'validateAppKey', app_id: app_id.val()},
            success: function (response) {
                console.log(response);
                if (response.error && app_id.val() !== "") {
                    app_id.val("");
                    app_id.focus();
                    $("#submit-cmb").show();
                    message.html('<p style="color:red;">' + response.error + '</p>');
                }
                if (response.success) {
                    $("#is_retainful_app_connected").val(1);
                    message.html('<p style="color:green;">' + response.success + '</p>');
                    $("#submit-cmb").trigger("click");
                }
            }
        });
        $("#submit-cmb").show();
    }
})(jQuery);