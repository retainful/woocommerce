document.addEventListener("DOMContentLoaded", function () {
// Only do anything if jQuery isn't defined
    if (typeof jQuery == 'undefined') {
        function getScript(url, success) {
            var script = document.createElement('script');
            script.src = url;
            var head = document.getElementsByTagName('head')[0],
                done = false;
            // Attach handlers for all browsers
            script.onload = script.onreadystatechange = function () {
                if (!done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete')) {
                    done = true;
                    // callback function provided as param
                    success();
                    script.onload = script.onreadystatechange = null;
                    head.removeChild(script);
                }
            };
            head.appendChild(script);
        }
        if (typeof rnocp_admin_params.jquery_url !== "undefined") {
            getScript(rnocp_admin_params.jquery_url, function () {
                if (typeof jQuery == 'undefined') {
                    // Super failsafe - still somehow failed...
                } else {
                    jQuery.noConflict();
                    initJqueryExitIntentPopupAdmin();
                }
            });
        }
    } else {
        initJqueryExitIntentPopupAdmin();
    }
});

function initJqueryExitIntentPopupAdmin() {
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
        $(document).on('click', '#rnoc_exit_intent_popup_template_show_preview', function () {
            viewPriview();
        });

        function viewPriview() {
            let val = editor.val();
            let custom_css = $("#rnoc_exit_intent_modal_custom_style").val();
            $("#custom-style-container").html(custom_css);
            $('#exit-intent-popup-preview').html(val);
        }

        viewPriview();
    });
}