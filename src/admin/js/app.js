(function ($) {
    $(document).ready(function () {
        $(document).on("click", "#rnoc_retainful #submit-cmb", function (event) {
            var is_noc_explained = $('[name="rnoc_enable_next_order_coupon"]:checked').val();
            var noc_coupon_val = $('[name="rnoc_retainful_coupon_amount"]').val();
            if (parseInt(is_noc_explained) === 1 && (parseInt(noc_coupon_val) <= 0 || noc_coupon_val === "")) {
                alert("Coupon value must not empty and greater than 0!");
                return false;
            }
        })
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
        $(document).on("change", "#rnoc_cart_abandoned_time", function (event) {
            let value = $(this).val();
            let consider_time = parseInt(value);
            if (consider_time < 15) {
                $(this).val(15);
            }
        });
    });

})(jQuery);