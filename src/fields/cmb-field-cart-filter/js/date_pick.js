(function ($) {
    function initDataRangePicker() {
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
        var yyyy = today.getFullYear();
        $('input[name="daterange"]').daterangepicker({
            "opens": 'right',
            "locale": {
                "format": "YYYY/MM/DD"
            },
            "maxDate": yyyy + '/' + mm + '/' + dd
        }, function (start, end, label) {
            getAbandonedCartDetails(start.format('YYYY/MM/DD'), end.format('YYYY/MM/DD'));
        });
    }

    initDataRangePicker();
    $(document).on('change', '#duration', function () {
        var days = $(this).val();
        if (days !== "custom") {
            $(".show_none").hide();
            var start_date = start_end_dates[days].start_date;
            var end_date = start_end_dates[days].end_date;
            $('input[name="daterange"]').val(start_date + ' - ' + end_date);
            getAbandonedCartDetails(start_date, end_date);
            initDataRangePicker();
        } else {
            $(".show_none").show();
        }
    });
    $(document).on('change', '#cart-type-selection', function () {
        let range = $('input[name="daterange"]').val();
        let dates = range.split('-');
        getAbandonedCartDetails(dates[0].trim(), dates[1].trim());
    });

    $(document).on('click', '#do-bulk-action', function () {
        let action = $('#bulk-action-select').val();
        if (action !== "") {
            if (confirm("Are you sure?")) {
                let path = $(this).data('ajax');
                if (action === "delete_selected") {
                    let checked_carts = $(".abandon-cart-list:checked");
                    if (checked_carts.length > 0) {
                        let cart = [];
                        $.each($(checked_carts), function () {
                            cart.push($(this).val());
                        });
                        $.ajax({
                            url: path,
                            type: 'POST',
                            dataType: "json",
                            data: {action: 'remove_abandoned_cart_multiple', cart_list: cart},
                            success: function (response) {
                                if (response.success) {
                                    window.location.reload();
                                }
                            }
                        });
                    } else {
                        alert("select at-least one to delete!")
                    }
                } else {
                    $.ajax({
                        url: path,
                        type: 'POST',
                        dataType: "json",
                        data: {action: action},
                        success: function (response) {
                            if (response.success) {
                                window.location.reload();
                            }
                        }
                    });
                }
            }
        }
    });

    function getAbandonedCartDetails(start, end) {
        var duration = $('#duration').val();
        var cart_type = $('#cart-type-selection').val();
        var url = '&start=' + start + '&end=' + end + '&page_number=' + page_number + '&cart_type=' + cart_type + '&duration=' + duration;
        window.location.href = page_url + url;
    }
})(jQuery);