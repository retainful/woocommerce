<div class="rnoc-coupon-timer-container-<?php echo $coupon_timer_position ?>"><?php echo $rnoc_coupon_timer_message; ?></div>
<script>
    var timerStarted = parseInt(<?php echo $is_timer_started; ?>);
    <?php
    if ($is_timer_reset == 1) {
    ?>
    startedTime = new Date().getTime();
    sessionStorage.setItem('rnoc_coupon_timer_started', startedTime.toString());
    <?php
    $woocommerce->removeSession('rnoc_is_coupon_timer_reset');
    }
    ?>
    var timerStartTime = sessionStorage.getItem('rnoc_coupon_timer_started');
    var startedTime;
    if (timerStarted === 1 && timerStartTime === null) {
        startedTime = new Date().getTime();
        sessionStorage.setItem('rnoc_coupon_timer_started', startedTime.toString());
    } else {
        startedTime = parseInt(timerStartTime);
    }
    var endedTimeInMin = parseInt("<?php echo $expired_in_min; ?>") * 60000;
    var countDownDate = startedTime + endedTimeInMin;
    window.rnoc_timer_expired_message_shown = false;
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
            if($auto_fix_page_reload == 0){
            ?>
            jQuery.post("<?php echo admin_url('admin-ajax.php?action=rnoc_coupon_timer_expired') ?>", function (data, status) {
                if (data.success) {
                    window.location.reload();
                }
            });
            <?php
            }else {
            ?>
            (function ($) {
                $.post("<?php echo admin_url('admin-ajax.php?action=rnoc_coupon_timer_expired') ?>", function (data, status) {
                    if (data.success) {
                        if (window.rnoc_timer_expired_message_shown === false) {
                            var coupon_det = $('.coupon-<?php echo strtolower($coupon_code); ?>');
                            coupon_det.hide();
                            var wrapper = $(".woocommerce-notices-wrapper");
                            var html = '<ul class="woocommerce-error" role="alert"><li><?php echo $coupon_timer_expire_message; ?></li></ul>';
                            wrapper.append(html);
                            window.rnoc_timer_expired_message_shown = true;
                        }
                    }
                });
            })(jQuery);
            <?php
            }
            }
            ?>
        }
    }, 1000);
</script>
<style>
    .rnoc-coupon-timer-container-<?php echo $coupon_timer_position ?> {
        padding: 20px 5px;
        text-align: center;
        background: <?php echo $rnoc_coupon_timer_background; ?> !important;;
        width: 100%;
        right: 0;
        margin: 10px 0;
        font-size: 16px;
        color: <?php echo $rnoc_coupon_timer_color ?>;
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