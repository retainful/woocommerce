<?php

namespace Rnoc\App\Helpers;

use Rnoc\App\Controller\Admin\Settings;
use Rnoc\App\Helpers\Input;
use Rnoc\App\Helpers\WC;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
class Customer
{
    public static function getCustomerDetails()
    {
        $user_id = WC::getCurrentUserId();
        $billing_email = WC::getCustomerEmail();
        $billing_phone = !empty($billing_details['billing_phone']) ? $billing_details['billing_phone'] : NULL;
        $billing_state = !empty($billing_details['billing_state']) ? $billing_details['billing_state'] : NULL;
        $billing_last_name = !empty($billing_details['billing_last_name']) ? $billing_details['billing_last_name'] : NULL;
        $billing_first_name = !empty($billing_details['billing_first_name']) ? $billing_details['billing_first_name'] : NULL;
        $created_at = self::$storage->getValue('rnoc_session_created_at');  //add the storage settings
        $updated_at = current_time('timestamp', true);
        if (!empty($user_id)) {
            $user_data = WC::getCurrentUser();
            $billing_email = !empty($billing_email) ? $billing_email : $user_data->user_email;
            $billing_phone = empty($user_data->billing_phone) ? $billing_phone : $user_data->billing_phone;
            $billing_state = empty($user_data->billing_state) ? $billing_state : $user_data->billing_state;
            $billing_last_name = empty($user_data->billing_last_name) ? $billing_last_name : $user_data->billing_last_name;
            $billing_first_name = empty($user_data->billing_first_name) ? $billing_first_name : $user_data->billing_first_name;
        }
        return array(
            'id' => $user_id,
            'email' => $billing_email,
            'phone' => $billing_phone,
            'state' => $billing_state,
            'last_name' => $billing_last_name,
            'first_name' => $billing_first_name,
            'currency' => Settings::getBaseCurrency(),
            'created_at' => Input::formatToIso8601($created_at),
            'updated_at' => Input::formatToIso8601($updated_at),
            'verified_email' => true,
            'last_order_name' => NULL,
            'accepts_marketing' => true,
            'user_roles' => WC::getUserRoles($billing_email)
        );
    }

    /**
     * Customer address mapping fields
     * @return array
     */
    public static function getAddressMapFields()
    {
        $fields = array(
            'first_name',
            'last_name',
            'state',
            'phone',
            'postcode',
            'city',
            'country',
            'address_1',
            'address_2',
            'company'
        );
        return apply_filters('rnoc_get_checkout_mapping_fields', $fields);
    }


    public static function getAddressDetails($type = 'billing')
    {
        $address_details = self::getCustomerCheckoutDetails($type);
        $first_name = empty($address_details[$type . '_first_name']) ? $address_details[$type . 'first_name'] : NULL;
        $last_name = empty($address_details[$type . '_last_name']) ? $address_details[$type . 'last_name'] : NULL;
        $user_id = WC::getCurrentUserId();
        if (!empty($user_id)) {
            $user_data = WC::getCurrentUser();
            $first_name = !empty($user_data->first_name) ? $user_data->first_name : $address_details[$type . '_first_name'];
            $last_name = !empty($user_data->last_name) ? $user_data->last_name : $address_details[$type . '_last_name'];
        }
        $fields = array(
            $type . '_address_1' => '',
            $type . '_city' => '',
            $type . '_state' => '',
            $type . '_postcode' => '',
            $type . '_country' => '',
            $type . '_phone' => '',
            $type . '_address_2' => '',
            $type . '_company' => ''
        );

        foreach ($fields as $key => $value) {
            if (isset($user_id) && $user_id > 0) {
                $address_value = get_user_meta($user_id, $key, true);
            }
            if (empty($address_value)) {
                $address_value = isset($billing_details[$key]) ? $billing_details[$key] : $value;
            }
            $fields[$key] = $address_value;
        }
        return array(
            'zip' => $fields[$type . '_postcode'],
            'city' => $fields[$type . '_city'],
            'name' => $first_name . ' ' . $last_name,
            'phone' => $fields[$type . '_phone'],
            'company' => $fields[$type . '_company'],
            'country' => $fields[$type . '_country'],
            'address1' => $fields[$type . '_address_1'],
            'address2' => $fields[$type . '_address_2'],
            'province' => $fields[$type . '_state'],
            'last_name' => $last_name,
            'first_name' => $first_name,
            'country_code' => $fields[$type . '_country'],
            'province_code' => $fields[$type . '_state'],
        );
    }

    /**
     * Get the customer billing details
     * @param $type
     * @return array
     */
    public static function getCustomerCheckoutDetails($type = "billing")
    {
        $fields = self::getAddressMapFields();
        $checkout_field_values = array();
        if (!empty($fields)) {
            foreach ($fields as $key) {
                $method = 'get_' . $type . '_' . $key;
                if (is_callable(array(WC()->customer, $method))) {
                    $checkout_field_values[$type . '_' . $key] = WC()->customer->$method();
                }
            }
        }
        return $checkout_field_values;
    }

}