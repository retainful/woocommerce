<?php

namespace Rnoc\Retainful;
if (!defined('ABSPATH')) exit;

use Rnoc\Retainful\Admin\Settings;

class Main
{
    public static $init;
    public $rnoc, $admin;

    /**
     * Initiate the plugin
     * @return Main
     */
    public static function instance()
    {
        return self::$init = (self::$init == NULL) ? new self() : self::$init;
    }

    /**
     * Main constructor.
     */
    function __construct()
    {
        $this->rnoc = ($this->rnoc == NULL) ? new OrderCoupon() : $this->rnoc;
        $this->admin = ($this->admin == NULL) ? new Settings() : $this->admin;
        $this->activateEvents();
    }

    /**
     * Activate the required events
     */
    function activateEvents()
    {
        //Check for dependencies
        add_action('plugins_loaded', array($this, 'checkDependencies'));
        //Activate CMB2 functions
        add_action('init', function () {
            $this->rnoc->init();
        });
        //Get events
        add_action('woocommerce_checkout_update_order_meta', array($this->rnoc, 'createNewCoupon'), 10, 2);
        add_action('woocommerce_payment_complete', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this->rnoc, 'onAfterPayment'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array($this->rnoc, 'onAfterPayment'), 10, 1);

        add_action('woocommerce_get_shop_coupon_data', array($this->rnoc, 'addVirtualCoupon'), 10, 2);
        add_action('wp_head', array($this->rnoc, 'setCouponToSession'));
        add_action('woocommerce_cart_loaded_from_session', array($this->rnoc, 'addCouponToCheckout'), 10);

        //Attach coupon to email
        $hook = $this->admin->couponMessageHook();
        if (!empty($hook))
            add_action($hook, array($this->rnoc, 'attachOrderCoupon'), 10, 4);

        //Validate key
        add_action('wp_ajax_validateAppKey', array($this->rnoc, 'validateAppKey'));
        //Settings link
        add_filter('plugin_action_links_' . RNOC_BASE_FILE, array($this->rnoc, 'pluginActionLinks'));
    }


    /**
     * Dependency check for our plugin
     */
    function checkDependencies()
    {
        if (!defined('WC_VERSION')) {
            $this->showAdminNotice(__('Woocommerce must be activated for Woocommerce category discount to work', 'retainful-next-order-coupon'));
        } else {
            if (version_compare(WC_VERSION, '2.0', '<')) {
                $this->showAdminNotice('Your woocommerce version is ' . WC_VERSION . '. Some of the features of Woocommerce category discount will not work properly on this woocommerce version.');
            }
        }
    }

    /**
     * Show notices for user..if anything unusually happen in our plugin
     * @param string $message - message to notice users
     */
    function showAdminNotice($message = "")
    {
        if (!empty($message)) {
            add_action('admin_notices', function () use ($message) {
                echo '<div class="error notice"><p>' . $message . '</p></div>';
            });
        }
    }
}