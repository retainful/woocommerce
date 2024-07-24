jQuery(document).on('wc-rtl-popup-coupon',function (event){
    if(event.coupon_code){
        jQuery.ajax({
            url: retainful_popup_data.ajax_url,
            headers: {},
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'rnoc_apply_popup_coupon',
                coupon_code : event.coupon_code,
            },
            success: function (response) {

            },
            error: function (response) {
            }
        });
    }
});
jQuery(document).on('wc-rtl-popup-redirect',function (event){
    if(event.redirect_url.url){
        sessionStorage.setItem("rnocp_popup_redirect", event.redirect_url.url);
        sessionStorage.setItem("rnocp_popup_redirect_type", event.redirect_url.type);
        let single_page = jQuery('body').hasClass('single-product')
        if (event.redirect_url.url !== null && !single_page ) {
            setTimeout(function() {
                sessionStorage.removeItem('rnocp_popup_redirect');
                sessionStorage.removeItem('rnocp_popup_redirect_type');
                window.open( event.redirect_url.url, event.redirect_url.type);
            }, retainful_popup_data.popup_redirect_timeout);
        }
    }
});

jQuery(document).ready(function(){
    setTimeout(function() {
        let redirect_url = sessionStorage.getItem('rnocp_popup_redirect');
        let redirect_type = sessionStorage.getItem('rnocp_popup_redirect_type');
        if(redirect_url !== null) {
            var currentUrl = window.location.href;
            if (redirect_url !== currentUrl) {
                sessionStorage.removeItem('rnocp_popup_redirect');
                sessionStorage.removeItem('rnocp_popup_redirect_type');
                window.open( redirect_url, redirect_type);
            }
        }
    }, retainful_popup_data.popup_redirect_timeout);
});