<div id="rnoc-applied-coupon-popup-main" class="modal"
     style="display: block;position: fixed;z-index: 9999;padding-top: 100px;left: 0;top: 0;width: 100%;height: 100%;overflow: auto;background-color: rgba(0,0,0,0.6);">
    <div class="modal-content"
         style="background-color: #fefefe;margin: auto;padding: 20px;border: 1px solid #888;">
                <span class="close" id="close-rnoc-applied-coupon-popup"
                      style="color: #aaaaaa;cursor: pointer;float: right;font-size: 28px;font-weight: bold;">&times;</span>
        <?php
        echo $popup_content
        ?>
    </div>
</div>
<script>
    jQuery(document).ready(function () {
        jQuery("#rnoc-applied-coupon-popup-main").show();
    });
    jQuery("#close-rnoc-applied-coupon-popup").click(function () {
        jQuery("#rnoc-applied-coupon-popup-main").hide();
    });
</script>

<style>
    #rnoc-applied-coupon-popup-main .modal-content{
        width: 28%;
    }
    @media (max-width: 768px) {
        #rnoc-applied-coupon-popup-main .modal-content {
            width: 85%;
        }
    }
</style>