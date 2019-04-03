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

    function getAbandonedCartDetails(start, end) {
        var path = $("#retainful_ajax_path").val();
        if (no_ajax) {
            var duration = $('#duration').val();
            var cart_type = $('#cart_type').val();
            var url = '&start=' + start + '&end=' + end + '&page_number=' + page_number + '&cart_type=' + cart_type + '&duration=' + duration;
            window.location.href = page_url + url;
        } else {
            $.ajax({
                url: path,
                type: 'POST',
                dataType: "json",
                data: {action: 'get_ajax_details_for_dashboard', start: start, end: end},
                success: function (response) {
                    $("#rnoc_abandoned_carts").html(response.abandoned_carts);
                    $("#rnoc_abandoned_total").html(response.abandoned_total);
                    $("#rnoc_recovered_carts").html(response.recovered_carts);
                    $("#rnoc_recovered_total").html(response.recovered_total);
                }
            });
        }
    }
})(jQuery);