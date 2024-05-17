<?php

namespace Rnoc\App\Modules\AbandonedCart;

use Exception;
use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Helpers\WC;
use Rnoc\App\Helpers\Customer;
use Rnoc\App\Helpers\Currency;
use Rnoc\App\Helpers\Settings as SettingsHelper;
use Rnoc\App\Modules\Integrations\MultiLingual;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use stdClass;

class Cart extends RestApi
{
    function __construct()
    {
        parent::__construct();
    }


    /**
     * Sync cart with the retainful
     * @param bool $force_sync
     */
    public static function syncCartData($force_sync = false)
    {
        if (!self::isValidCartToTrack()) {
            return;
        }
        if ($force_sync || self::needToTrackCart()) {
            $cart = self::getUserCart();
            if (!empty($cart)) {
                Settings::logMessage($cart, 'cart');
                $client_ip = self::formatUserIP(self::getClientIp());
                $cart_hash = self::encryptData($cart);
                if (!empty($cart_hash)) {
                    $token = self::getCartToken();
                    $extra_headers = array(
                        "X-Client-Referrer-IP" => (!empty($client_ip)) ? $client_ip : null,
                        "X-Retainful-Version" => RNOC_VERSION,
                        "X-Cart-Token" => $token,
                        "Cart-Token" => $token,
                    );
                    self::syncCart($cart_hash, $extra_headers);
                }
            }
        }
    }


    /**
     * Get the line items details
     * @return array
     */
    public static function getCartLineItemsDetails()
    {
        $items = array();
        $cart = WC::getCart();
        if (!empty($cart)) {
            foreach ($cart as $item_key => $item_details) {
                //Deceleration
                $tax_details = array();
                $item_quantity = !empty($item_details['quantity']) ? $item_details['quantity'] : NULL;
                $variant_id = !empty($item_details['variation_id']) ? $item_details['variation_id'] : 0;
                $product_id = !empty($item_details['product_id']) ? $item_details['product_id'] : 0;
                $cat_ids = !empty($product_id) && $product_id > 0 ? WC::getProductCategoryIds($product_id) : array();
                $item = apply_filters('woocommerce_cart_item_product', $item_details['data'], $item_details, $item_key);
                if (empty($item)) {
                    if (!empty($variant_id)) {
                        $item = WC::getProduct($variant_id);
                    } elseif (!empty($product_id)) {
                        $item = WC::getProduct($product_id);
                    }
                }
                $line_tax = (!empty($item_details['line_tax'])) ? $item_details['line_tax'] : 0;
                if ($line_tax > 0) {
                    $tax_details[] = array(
                        'rate' => 0,
                        'zone' => 'province',
                        'price' => self::formatDecimalPriceRemoveTrailingZeros($line_tax),
                        'title' => 'tax',
                        'source' => 'WooCommerce',
                        'position' => 1,
                        'compare_at' => 0,
                    );
                }
                $image_url = WC::getProductImageSrc($item);
                if (!empty($item) && !empty($item_quantity)) {
                    $item_array = array(
                        'key' => $item_key,
                        'sku' => WC::getItemSku($item),
                        'price' => self::formatDecimalPriceRemoveTrailingZeros(WC::getCartItemPrice($item)),
                        'title' => WC::getItemName($item),
                        'taxable' => ($line_tax != 0),
                        'quantity' => $item_quantity,
                        'tax_lines' => $tax_details,
                        'line_price' => self::formatDecimalPriceRemoveTrailingZeros(self::getLineItemTotal($item_details)),
                        'product_id' => $product_id,
                        'cat_ids' => implode(',', $cat_ids),
                        'cat_names' => WC::getProductCategoryName($product_id),
                        'variant_id' => $variant_id,
                        'variant_price' => self::formatDecimalPriceRemoveTrailingZeros(!empty($variant_id) ? WC::getCartItemPrice($item) : 0),
                        'variant_title' => !empty($variant_id) ? WC::getItemName($item) : '',
                        'image_url' => $image_url,
                        'product_url' => WC::getProductUrl($item),
                        'properties' => array()
                    );
                    $items[] = apply_filters('rnoc_get_cart_line_item_details', $item_array, $cart, $item_key, $item, $item_details);
                }
            }
        }
        return apply_filters("rnoc_get_abandoned_cart_line_items", $items, $cart);
    }

    /**
     * get the cart tax details
     * @return array
     */
    public static function getCartTaxDetails()
    {
        $tax_details = WC::getCartTaxes();
        $taxes = array();
        if (!empty($tax_details)) {
            foreach ($tax_details as $key => $tax_detail) {
                $taxes[] = array(
                    'rate' => 0,
                    'price' => self::formatDecimalPrice((isset($tax_detail->amount)) ? $tax_detail->amount : 0),
                    'title' => (isset($tax_detail->label)) ? $tax_detail->label : 'Tax'
                );
            }
        }
        return $taxes;
    }


    /**
     * get user IP details
     * @return array|mixed|string|null
     */
    public static function getUserIPDetails()
    {
        return empty($user_ip) ? self::formatUserIP(self::getClientIp()) : self::retrieveUserIp();
    }

    /**
     * Preprocess cart required for API call
     * @return array
     */
    public static function getUserCart()
    {
        Customer::getAddressDetails();
        $current_language = MultiLingual::getCurrentLanguage();
        $customer_details = Customer::getCustomerDetails();
        $cart_token = self::getCartToken();
        $current_currency_code = Currency::getCurrentCurrencyCode();
        $default_currency_code = Settings::getBaseCurrency();
        $cart_created_at = self::userCartCreatedAt();
        $cart_total = self::formatDecimalPrice(WC::getCartTotalPrice());
        $cart_hash = self::generateCartHash();
        $consider_on_hold_order_as_ac = SettingsHelper::get(RNOC_PLUGIN_PREFIX . 'consider_on_hold_as_abandoned_status', 'retainful_settings', 0);
        $cart = array(
            'cart_type' => 'cart',
            'treat_on_hold_as_complete' => ($consider_on_hold_order_as_ac == 0),
            'cart_hash' => $cart_hash,
            'ip' => self::getUserIPDetails(),
            'id' => $cart_token,
            'email' => (isset($customer_details['email'])) ? $customer_details['email'] : NULL,
            'token' => $cart_token,
            'currency' => $default_currency_code,
            'customer' => $customer_details,
            'tax_lines' => self::getCartTaxDetails(),
            'total_tax' => WC::getCartTotalTax(),
            'cart_token' => $cart_token,
            'created_at' => self::formatToIso8601($cart_created_at),
            'line_items' => self::getCartLineItemsDetails(),
            'updated_at' => self::formatToIso8601(''),
            'total_price' => $cart_total,
            'completed_at' => NULL,
            'discount_codes' => WC::getAppliedDiscounts(),
            'shipping_lines' => array(),
            'subtotal_price' => self::formatDecimalPrice(WC::getCartSubTotal()),
            'total_price_set' => Currency::getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code),
            'taxes_included' => (!WC::isPriceExcludingTax()),
            'customer_locale' => $current_language,
            'order_status' => NULL,
            'total_discounts' => self::formatDecimalPrice(WC::getCartTotalDiscount()),
            'shipping_address' => Customer::getAddressDetails('shipping'),
            'billing_address' => Customer::getAddressDetails('billing'),
            'presentment_currency' => $current_currency_code,
            'abandoned_checkout_url' => self::getRecoveryLink($cart_token),
            'total_line_items_price' => self::formatDecimalPrice(WC::getCartTotal()),
            'buyer_accepts_marketing' => self::isBuyerAcceptsMarketing(),
            'client_session' => WC::getClientSession(),
            'woocommerce_totals' => self::getCartTotals(),
            'recovered_at' => (!empty($recovered_at)) ? self::formatToIso8601($recovered_at) : NULL,
            'recovered_by_retainful' => (self::$storage->getValue('rnoc_recovered_by_retainful')) ? true : false,
            'recovered_cart_token' => self::$storage->getValue('rnoc_recovered_cart_token'),
            'client_details' => self::getClientDetails()
        );
        if (!empty($cart_token)) {
            $referrer_automation_id = WC::getSession($cart_token . '_referrer_automation_id');
            if (!empty($referrer_automation_id)) {
                $cart['referrer_automation_id'] = $referrer_automation_id;
            }
        }
        return apply_filters('rnoc_get_user_cart', $cart);
    }

    /**
     * get cart totals
     * @return array
     */
    public static function getCartTotals()
    {
        return array(
            'total_price' => self::formatDecimalPrice(WC::getCartTotalPrice()),
            'subtotal_price' => self::formatDecimalPrice(WC::getCartSubTotal()),
            'total_tax' => self::formatDecimalPrice(WC::getCartTaxTotal() + WC::getCartShippingTaxTotal()),
            'total_discounts' => self::formatDecimalPrice(WC::getCartDiscountTotal()),
            'total_shipping' => self::formatDecimalPrice(WC::getCartShippingTotal()),
            'fee_items' => self::getCartFeeDetails(),
        );
    }

    /**
     * get cart fee details
     * @return array
     */
    public static function getCartFeeDetails()
    {
        $fee_items = array();
        if ($fees = WC::getCartFees()) {
            foreach ($fees as $fee) {
                $fee_items[] = array(
                    'title' => html_entity_decode($fee->name),
                    'key' => $fee->id,
                    'amount' => self::formatDecimalPrice($fee->amount)
                );
            }
        }
        return $fee_items;
    }


    /**
     * Hash the data
     * @param $data
     * @return false|string
     */
    public static function hashTheData($data)
    {
        $secret = Settings::getSecretKey();
        return hash_hmac(self::HMAC_ALGORITHM, $data, $secret);
    }


    /**
     * need to track user cart
     * @return bool
     */
    public static function isValidCartToTrack()
    {
        $crawler_detect = new CrawlerDetect();
        if ($crawler_detect->isCrawler()) {
            return false;
        }
        if (self::canTrackAbandonedCarts() == false) {
            return false;
        }
        return true;
    }

    /**
     * need to track carts or not
     * @param string $ip_address
     * @param $order null | \WC_Order | \WC_Cart
     * @return bool
     */
    public static function canTrackAbandonedCarts($ip_address = NULL, $order = null)
    {
        if (apply_filters('rnoc_is_cart_has_valid_ip', true, $ip_address) && apply_filters('rnoc_can_track_abandoned_carts', true, $order)) {
            return true;
        }
        return false;
    }

    /**
     * Check weather Retainful needs to track the cart or not
     * @return bool
     */
    public static function needToTrackCart()
    {
        $cart_hash = self::generateCartHash();
        $cart_created_at = self::userCartCreatedAt();
        if (empty($cart_hash) && empty($cart_created_at)) {
            return false;
        } elseif (empty($cart_hash) && !empty($cart_created_at)) {
            return self::comparePreviousCartHash($cart_hash);
        } elseif (!empty($cart_hash) && empty($cart_created_at)) {
            //TODO What if it fails to create cart created time
            $time = current_time('timestamp', true);
            self::$storage->setValue(self::$cart_tracking_started_key, $time);
            if ($user_id = get_current_user_id()) {
                self::setCartCreatedDate($user_id, $time);
            }
            return self::comparePreviousCartHash($cart_hash);
        } else {
            return self::comparePreviousCartHash($cart_hash);
        }
    }

    /**
     * compare old and current cart hash to sync the cart;
     * This will help from tracking same cart multiple times
     * This will also reduce the number of API requests
     * @param $current_cart_hash
     * @return bool
     */
    public static function comparePreviousCartHash($current_cart_hash)
    {
        $old_cart_hash = self::$storage->getValue(self::$previous_cart_hash_key);
        $is_not_similar = ($old_cart_hash != $current_cart_hash);
        if ($is_not_similar) {
            self::$storage->setValue(self::$previous_cart_hash_key, $current_cart_hash);
        }
        self::$storage->setValue('rnoc_current_cart_hash', $current_cart_hash);
        return $is_not_similar;
    }
}
