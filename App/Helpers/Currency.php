<?php

namespace Rnoc\App\Helpers;

use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Helpers\Input;
use Rnoc\App\Helpers\WC;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
class Currency
{
    /**
     * Currency details for cart
     * @param $cart_total
     * @param $current_currency_code
     * @param $default_currency_code
     * @return array
     */
    public static function getCurrencyDetails($cart_total, $current_currency_code, $default_currency_code)
    {
        if ($current_currency_code != $default_currency_code) {
            $exchange_rate = apply_filters('rnoc_get_currency_rate', $cart_total, $current_currency_code);
            $shop_cart_total = self::convertToCurrency($cart_total, $exchange_rate);
        } else {
            $shop_cart_total = $cart_total;
        }
        $details = array(
            'shop_money' => array(
                'amount' => $shop_cart_total,
                'currency_code' => $default_currency_code
            ),
            'presentment_money' => array(
                'amount' => $cart_total,
                'currency_code' => $current_currency_code
            )
        );
        return apply_filters('rnoc_get_cart_currency_details', $details, $current_currency_code, $default_currency_code);
    }


    /**
     * Convert price to another price as per currency rate
     * @param $price
     * @param $rate
     * @return float|int
     */
    public static function convertToCurrency($price, $rate)
    {
        if (!empty($price) && !empty($rate)) {
            return $price / $rate;
        }
        return $price;
    }

    /**
     * get the active currency code
     * @return String|null
     */
    public static function getCurrentCurrencyCode()
    {
        $default_currency = Settings::getBaseCurrency();
        return apply_filters('rnoc_get_current_currency_code', $default_currency);
    }


}