(function ($) {
    $(document).ready(function () {
        $(document).on("keypress keyup blur", "#app_coupon_value", function (event) {
            $(this).val($(this).val().replace(/[^0-9\.]/g, ''));
            if ((event.which !== 46 || $(this).val().indexOf('.') !== -1) && (event.which < 48 || event.which > 57)) {
                event.preventDefault();
            }
        });
        $(document).on("keypress keyup blur", "#app_coupon_expire_days,.number_only_field", function (event) {
            if ((event.which < 48 || event.which > 57)) {
                event.preventDefault();
            }
        });
    });

})(jQuery);