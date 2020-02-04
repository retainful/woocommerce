<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Retainful_Customer')) {
    class Retainful_Customer extends WC_Data
    {
        /**
         * Stores customer data.
         *
         * @var array
         */
        protected $data = array(
            'cart_created_date' => null,
            'cart_modified_date' => null,
            'session_created_at' => null,
            'recovered_cart_token' => null,
            'cart_token' => null,
            'cart_hash' => null,
            'user_ip' => null,
            'ac_coupon' => null,
            'pending_recovery' => false,
            'buyer_accepts_marketing' => 1,
            'recovered_by_retainful' => false,
            'previous_cart_hash' => null,
            'recovered_at' => null,
        );

        /**
         * Retainful_Customer constructor.
         * @param $data
         * @param $is_session
         * @throws Exception
         */
        function __construct($data = 0, $is_session = false)
        {
            parent::__construct($data);
            $this->data_store = WC_Data_Store::load('customer-retainful-abandoned-carts');
            $this->data_store->read($this);
            $this->set_object_read(true);
        }

        function set_recovered_at($recovered_at)
        {
            $this->set_prop('recovered_at', $recovered_at);
        }

        function get_recovered_at($context = "view")
        {
            return $this->get_prop('recovered_at', $context);
        }

        function set_recovered_by_retainful($recovered_by_retainful)
        {
            $this->set_prop('recovered_by_retainful', $recovered_by_retainful);
        }

        function get_recovered_by_retainful($context = "view")
        {
            return $this->get_prop('recovered_by_retainful', $context);
        }

        function set_recovered_cart_token($recovered_cart_token)
        {
            $this->set_prop('recovered_cart_token', $recovered_cart_token);
        }

        function get_recovered_cart_token($context = "view")
        {
            return $this->get_prop('recovered_cart_token', $context);
        }

        /**
         * set customer cart created date
         * @param $created_date
         */
        function set_cart_created_date($created_date)
        {
            $this->set_prop('cart_created_date', $created_date);
        }

        /**
         * Set cart modified date
         * @param $cart_modified_date
         */
        function set_cart_modified_date($cart_modified_date)
        {
            $this->set_prop('cart_cart_modified_date', $cart_modified_date);
        }

        /**
         * Set cart token
         * @param $cart_token
         */
        function set_cart_token($cart_token)
        {
            $this->set_prop('cart_token', $cart_token);
        }

        /**
         * set cart hash
         * @param $cart_hash
         */
        function set_cart_hash($cart_hash)
        {
            $this->set_prop('cart_hash', $cart_hash);
        }

        /**
         * Set user's previous cart hash
         * @param $created_date
         */
        function set_previous_cart_hash($created_date)
        {
            $this->set_prop('previous_cart_hash', $created_date);
        }

        /**
         * Set user's IP details
         * @param $user_ip
         */
        function set_user_ip($user_ip)
        {
            $this->set_prop('user_ip', $user_ip);
        }

        /**
         * Set user's pending recovery details
         * @param $pending_recovery
         */
        function set_pending_recovery($pending_recovery)
        {
            $this->set_prop('pending_recovery', $pending_recovery);
        }

        /**
         * Set is user's buyer accepts marketing
         * @param $buyer_accepts_marketing
         */
        function set_buyer_accepts_marketing($buyer_accepts_marketing)
        {
            $this->set_prop('buyer_accepts_marketing', $buyer_accepts_marketing);
        }

        /**
         * get cart created date
         * @param string $context
         * @return mixed
         */
        function get_cart_created_date($context = "view")
        {
            return $this->get_prop('cart_created_date', $context);
        }

        /**
         * Set abandoned cart
         * @param $ac_coupon
         */
        function set_ac_coupon($ac_coupon)
        {
            $this->set_prop('buyer_accepts_marketing', $ac_coupon);
        }

        /**
         * get AC coupon
         * @param string $context
         * @return mixed
         */
        function get_ac_coupon($context = "view")
        {
            return $this->get_prop('ac_coupon', $context);
        }

        /**
         * Set session created date
         * @param $session_created_at
         */
        function set_session_created_at($session_created_at)
        {
            $this->set_prop('session_created_at', $session_created_at);
        }

        /**
         * get session created date
         * @param string $context
         * @return mixed
         */
        function get_session_created_at($context = "view")
        {
            return $this->get_prop('session_created_at', $context);
        }

        /**
         * get is buyer accepts marketing
         * @param string $context
         * @return mixed
         */
        function get_buyer_accepts_marketing($context = "view")
        {
            return $this->get_prop('buyer_accepts_marketing', $context);
        }

        /**
         * get cart modified date
         * @param string $context
         * @return mixed
         */
        function get_cart_modified_date($context = "view")
        {
            return $this->get_prop('cart_modified_date', $context);
        }

        /**
         * get the cart token
         * @param string $context
         * @return mixed
         */
        function get_cart_token($context = "view")
        {
            return $this->get_prop('cart_token', $context);
        }

        /**
         * get the user's cart hash
         * @param string $context
         * @return mixed
         */
        function get_cart_hash($context = "view")
        {
            return $this->get_prop('cart_hash', $context);
        }

        /**
         * get the user's previous cart hash
         * @param string $context
         * @return mixed
         */
        function get_previous_cart_hash($context = "view")
        {
            return $this->get_prop('previous_cart_hash', $context);
        }

        /**
         * get the user's IP address
         * @param string $context
         * @return mixed
         */
        function get_user_ip($context = "view")
        {
            return $this->get_prop('user_ip', $context);
        }

        /**
         * get the user's IP address
         * @param string $context
         * @return mixed
         */
        function get_pending_recovery($context = "view")
        {
            return $this->get_prop('pending_recovery', $context);
        }
    }
}