<div class="rnoc-coupon-timer-container-<?php echo $coupon_timer_position ?>"><?php
    echo $rnoc_coupon_timer_message;
    if ($rnoc_enable_checkout_button && !empty($rnoc_enable_checkout_button)) {
        ?>
        <a href="<?php echo $rnoc_checkout_url; ?>"
           style="text-decoration:none;padding: 10px;color: <?php echo $rnoc_checkout_button_color ?>;background-color: <?php echo $rnoc_checkout_button_bg_color ?>"><?php echo $rnoc_checkout_button_text; ?></a>
        <?php
    }
    ?></div>
<script>
    var countDownDate = new Date("<?php echo date('M d, Y H:i:s', $coupon_expire_time) ?> UTC").getTime();
    var x = setInterval(function () {
        var now = new Date().getTime();
        var distance = countDownDate - now;
        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);
        document.getElementById("rnoc-coupon-timer-<?php echo $coupon_timer_position ?>").innerHTML = <?php echo $rnoc_coupon_timer_display_format ?>;
        if (distance < 0) {
            clearInterval(x);
            document.getElementById("rnoc-coupon-timer-<?php echo $coupon_timer_position ?>").innerHTML = "<?php echo __('EXPIRED', RNOC_TEXT_DOMAIN) ?>";
            <?php
            if(apply_filters('rnoc_coupon_timer_top_position_reload', true)){
            ?>
            window.location.reload();
            <?php
            }
            ?>
        }
    }, 1000);
    jQuery(document).ready(function () {
        (function ($) {
            var container = $(".rnoc-coupon-timer-container-<?php echo $coupon_timer_position ?>")[0];
            $("body").css("margin-<?php echo $rnoc_top_bottom_position ?>", container.offsetHeight);
        })(jQuery);
    })
</script>
<style>
    .rnoc-coupon-timer-container-<?php echo $coupon_timer_position ?> {
        text-align: center;
        background: <?php echo $rnoc_coupon_timer_background; ?> !important;;
        width: 100%;
        right: 0;
        <?php echo $rnoc_top_bottom_position ?>: 0;
        color: <?php echo $rnoc_coupon_timer_color ?>;
        position: fixed;
        z-index: 999;
        padding: 20px 5px;
    }

    .rnoc-coupon-timer-container-<?php echo $coupon_timer_position ?> .timer-coupon-code-<?php echo $coupon_timer_position ?> {
        font-weight: 800;
        color: <?php echo $rnoc_coupon_timer_coupon_code_color; ?> !important;
    }

    .rnoc-coupon-timer-container-<?php echo $coupon_timer_position ?> #rnoc-coupon-timer-<?php echo $coupon_timer_position ?> {
        font-weight: 800;
        color: <?php echo $rnoc_coupon_timer_coupon_timer_color; ?> !important;
    }
</style>