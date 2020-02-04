<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Customer_Data_Retainful_Store_Session extends WC_Data_Store_WP implements WC_Customer_Data_Store_Interface, WC_Object_Data_Store_Interface
{
    /**
     * Keys which are also stored in a session (so we can make sure they get updated...)
     *
     * @var array
     */
    protected $retainful_session_keys = array(
        'cart_created_date',
        'cart_modified_date',
        'cart_token',
        'cart_hash',
        'ac_coupon',
        'user_ip',
        'pending_recovery',
        'buyer_accepts_marketing',
        'recovered_cart_token',
        'recovered_by_retainful',
        'recovered_at',
        'previous_cart_hash'
    );

    /**
     * Simply update the session.
     *
     * @param Retainful_Customer $customer Customer object.
     */
    public function create(&$customer)
    {
        $this->save_to_session($customer);
    }

    /**
     * Simply update the session.
     *
     * @param Retainful_Customer $customer Customer object.
     */
    public function update(&$customer)
    {
        $this->save_to_session($customer);
    }

    /**
     * Saves all customer data to the session.
     *
     * @param Retainful_Customer $customer Customer object.
     */
    public function save_to_session($customer)
    {
        $data = array();
        foreach ($this->retainful_session_keys as $session_key) {
            $data[$session_key] = (string)$customer->{"get_$session_key"}('edit');
        }
        WC()->session->set('retainful_customer', $data);
    }

    /**
     * Read customer data from the session unless the user has logged in, in
     * which case the stored ID will differ from the actual ID.
     *
     * @param Retainful_Customer $customer Customer object.
     * @since 3.0.0
     */
    public function read(&$customer)
    {
        $session = WC()->session;
        if (is_object($session) && $session instanceof WC_Session) {
            $data = (array)WC()->session->get('retainful_customer');
            foreach ($this->retainful_session_keys as $session_key) {
                if (isset($data[$session_key]) && is_callable(array($customer, "set_{$session_key}"))) {
                    $customer->{"set_{$session_key}"}(wp_unslash($data[$session_key]));
                }
            }
        }
        $this->set_defaults($customer);
        $customer->set_object_read(true);
    }

    /**
     * Load default values if props are unset.
     *
     * @param Retainful_Customer $customer Customer object.
     */
    protected function set_defaults(&$customer)
    {
        try {
            if (!$customer->get_cart_created_date()) {
                $customer->set_cart_created_date(null);
            }
            if (!$customer->get_cart_hash()) {
                $customer->set_cart_hash(null);
            }
            if (!$customer->get_cart_token()) {
                $customer->set_cart_token(null);
            }
            if (!$customer->get_cart_modified_date()) {
                $customer->set_cart_modified_date(null);
            }
            if (!$customer->get_previous_cart_hash()) {
                $customer->set_previous_cart_hash(null);
            }
            if (!$customer->get_pending_recovery()) {
                $customer->set_pending_recovery(false);
            }
            if (!$customer->get_buyer_accepts_marketing()) {
                $customer->set_buyer_accepts_marketing(1);
            }
        } catch (WC_Data_Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }
    }

    /**
     * Deletes a customer from the database.
     *
     * @param Retainful_Customer $customer Customer object.
     * @param array $args Array of args to pass to the delete method.
     * @since 3.0.0
     */
    public function delete(&$customer, $args = array())
    {
        WC()->session->set('retainful_customer', null);
    }

    /**
     * Gets the customers last order.
     *
     * @param WC_Customer $customer Customer object.
     * @return WC_Order|false
     * @since 3.0.0
     */
    public function get_last_order(&$customer)
    {
        return false;
    }

    /**
     * Return the number of orders this customer has.
     *
     * @param WC_Customer $customer Customer object.
     * @return integer
     * @since 3.0.0
     */
    public function get_order_count(&$customer)
    {
        return 0;
    }

    /**
     * Return how much money this customer has spent.
     *
     * @param WC_Customer $customer Customer object.
     * @return float
     * @since 3.0.0
     */
    public function get_total_spent(&$customer)
    {
        return 0;
    }
}
