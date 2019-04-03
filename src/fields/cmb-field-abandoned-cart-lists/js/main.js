(function ($) {
    $(document).ready(function () {
        $(".view-cart").fancybox({
            type: 'ajax',
            minWidth: 800
        });
    });
    $(document).on('click', '.remove-cart-btn', function () {
        var path = $(this).data('ajax');
        var cart_id = $(this).data('cart');
        $.ajax({
            url: path,
            type: 'POST',
            dataType: "json",
            data: {action: 'remove_abandoned_cart', cart_id: cart_id},
            success: function (response) {
                if (response.success) {
                    window.location.reload();
                }
            }
        });
    })
})(jQuery);
