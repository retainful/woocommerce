(function ($) {
    $(document).ready(function () {
        $(document).on("keypress keyup blur", "#app_coupon_value", function (event) {
            $(this).val($(this).val().replace(/[^0-9\.]/g, ''));
            if ((event.which !== 46 || $(this).val().indexOf('.') !== -1) && (event.which < 48 || event.which > 57)) {
                e.preventDefault();
            }
        });
        $(document).on("keypress keyup blur", "#app_coupon_expire_days", function (event) {
            $(this).val($(this).val().replace(/[^0-9\.]/g, ''));
            if ((event.which < 48 || event.which > 57)) {
                e.preventDefault();
            }
        });
        let is_usage_restriction = $("#unlock_usage_restriction").val();
        if(is_usage_restriction === "1"){
            $("#submit-cmb").hide();
        }
    });
    $(document).on("submit", "#rnoc_retainful", function () {
        let coupon = $("#app_coupon_value");
        let error_msg = $("#coupon_amount_error");
        let coupon_type = $("input[name$=retainful_coupon_type]:checked").val();
        error_msg.html('');
        if(coupon.val() !== "") {
            let ex = /^[0-9]+\.?[0-9]*$/;
            if (ex.test(coupon.val()) === false) {
                error_msg.html('Invalid coupon value.');
                coupon.focus();
                return false;
            } else {
                if (coupon_type === "0") {
                    if (parseFloat(coupon.val()) < 0 || parseFloat(coupon.val()) > 100) {
                        error_msg.html('For percentage coupon, coupon value must less then or equal to 100.');
                        coupon.focus();
                        return false;
                    }
                }
            }
            let val = coupon.val();
            if (val.indexOf('.') > -1) {
                val = parseFloat(val).toFixed(2);
            }
            coupon.val(val);
        }
    });
})(jQuery);