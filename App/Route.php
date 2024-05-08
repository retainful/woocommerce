<?php

namespace Rnoc\App;
if (!defined('ABSPATH')) exit;

use Rnoc\App\Modules\AbandonedCart\Cart;
use Rnoc\App\Modules\AbandonedCart\Checkout;
use Rnoc\Retainful\Api\NextOrderCoupon\CouponManagement;
use Rnoc\Retainful\Api\Popup\Popup;
use Rnoc\Retainful\Api\Referral\ReferralManagement;
use Rnoc\Retainful\Integrations\AfterPay;
use Rnoc\Retainful\Integrations\Currency;
use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Controller\Admin\BaseController;
use Rnoc\Retainful\Controllers\Site\IpFiltering;

class Route
{
    private static $base_controller, $settings, $cart;

    public static function init()
    {
        self::$settings = empty(self::$settings) ? new Settings() : self::$settings;
        self::$base_controller = empty(self::$base_controller) ? new BaseController() : self::$base_controller;
        //ini plugin hooks
        do_action('rnoc_before_init');
        self::addCommonHooks();
        if (is_admin()) {
            self::addAdminHooks();
        } else {
            self::addSiteHooks();
        }
        do_action('rnoc_after_init');
    }


    public static function addAdminHooks()
    {
        add_action('admin_menu', array(self::$settings, 'registerMenu'));
        add_action('admin_enqueue_scripts', array(self::$settings, 'initAdminPageStyles'));
        add_action('wp_ajax_rnoc_save_settings', array(self::$settings, 'saveAcSettings'));
        add_action('wp_ajax_rnoc_save_settings', array(self::$settings, 'saveAcSettings'));
        add_action('wp_ajax_rnoc_disconnect_license', array(self::$settings, 'disconnectLicense'));

        //add_action('wp_ajax_rnoc_disconnect_license', array($this->admin, 'disconnectLicense'));
        //Validate key
        // add_action('wp_ajax_validate_app_key', array(self::$base_controller, 'validateAppKey'));
    }


    /**
     * @return void
     */
    public static function addSiteHooks()
    {
        /*
        * Retainful abandoned cart api
        */
        $cart = new Cart();
        $checkout = new Checkout();
        add_action('woocommerce_after_calculate_totals', array($cart, 'syncCartData'));
        add_action('woocommerce_payment_complete', array($checkout, 'paymentCompleted'));
        add_action('woocommerce_checkout_update_order_meta', array($checkout, 'checkoutOrderProcessed'));

    }


    /**
     * Register all the required end points
     */

    public static function addCommonHooks()
    {

    }


}