jQuery(function ($) {
    var editor = $("#rnoc_exit_intent_popup_template");
    $(document).on('click', '.insert-exit-intent-popup-template', function () {
        var template_id = $(this).data('template');
        $.ajax({
            url: rnocp_admin_params.ajax_url,
            type: 'POST',
            dataType: "json",
            data: {
                action: 'rnocp_get_exit_intent_popup_template',
                id: template_id
            },
            success: function (response) {
                if (response.success) {
                    var value = response.content;
                    value = value.replace(/\\/g, '');
                    editor.val('');
                    editor.val(value);
                    viewPriview();
                    /*console.log(tinyMCE.activeEditor);
                    if (tinyMCE.activeEditor != null) {
                        tinyMCE.activeEditor.setContent(value);
                    }*/
                }
            }
        });
    });
    $(document).on('click','#rnoc_exit_intent_popup_template_show_preview',function(){
        viewPriview();
    });
    function viewPriview(){
        let val = editor.val();
        let custom_css = $("#rnoc_exit_intent_modal_custom_style").val();
        $("#custom-style-container").html(custom_css);
        $('#exit-intent-popup-preview').html(val);
    }
    viewPriview();
});