<?php
/**
 * Created by PhpStorm.
 * User: cartrabbit
 * Date: 2019-05-15
 * Time: 12:36
 */
if (!class_exists('RetainfulPremiumAddonBase')) {
    abstract class RetainfulPremiumAddonBase
    {
        public $title = NULL, $icon = NULL, $description = NULL, $slug = '', $version = '1.0.0', $path, $url, $admin, $wc_functions, $plan = array(), $premium_addon_settings = array();

        function __construct()
        {
            $this->admin = new Rnoc\Retainful\Admin\Settings();
            $this->plan = array('pro', 'business');
            $this->wc_functions = new \Rnoc\Retainful\WcFunctions();
            $this->premium_addon_settings = $this->admin->getPremiumAddonSettings();
        }

        /**
         * Title of addon
         * @return string
         */
        function title()
        {
            return $this->title;
        }

        /**
         * Icon of addon
         * @return string
         */
        function icon()
        {
            return $this->icon;
        }

        /**
         * Description of addon
         * @return string
         */
        function description()
        {
            return $this->description;
        }

        /**
         * Version of addon
         * @return string
         */
        function version()
        {
            return $this->version;
        }

        /**
         * Slug of addon
         * @return string
         */
        function slug()
        {
            return $this->slug;
        }

        /**
         * Plan of addon
         * @return array
         */
        function plan()
        {
            return $this->plan;
        }

        /**
         * get the template content
         * @param $path
         * @param array $params
         * @param null $addon_name
         * @return mixed|null
         */
        function getTemplateContent($path, $params = array(), $addon_name = NULL)
        {
            $path = apply_filters('rnocp_modify_addon_template_path', $path, $addon_name);
            if (file_exists($path)) {
                ob_start();
                extract($params);
                include $path;
                $content = ob_get_clean();
                $final_content = apply_filters('rnocp_modify_addon_template', $content);
                return $final_content;
            }
            return NULL;
        }

        /**
         * is valid page to display
         * @param $to_display_pages
         * @return bool
         */
        function isValidPagesToDisplay($to_display_pages)
        {
            if (empty($to_display_pages)) {
                return true;
            }
            if (is_array($to_display_pages)) {
                $to_display_pages = array_map('intval', $to_display_pages);
            }
            $to_display_pages = apply_filters('rnocp_before_page_check',$to_display_pages);
            if (is_page($to_display_pages)) {
                return true;
            }
            return false;
        }

        /**
         * get the value from array
         * @param $array
         * @param $key
         * @param $default
         * @return mixed
         */
        function getKeyFromArray($array, $key, $default = NULL)
        {
            if (isset($array[$key])) {
                return $array[$key];
            }
            return $default;
        }

        /**
         * select coupon
         * @return array
         */
        function getWooCouponCodes()
        {
            $posts = get_posts(array('post_type' => 'shop_coupon', 'posts_per_page' => -1, 'post_status' => 'publish'));
            return wp_list_pluck($posts, 'post_title', 'post_title');
        }

        /**
         * Check the user is capable for doing task
         * @return bool
         */
        function isValidUserToShow()
        {
            if (current_user_can('administrator')) {
                return true;
            } elseif (!is_user_logged_in()) {
                return true;
            }
            return false;
        }

        /**
         * get pages
         * @return array
         */
        function getPageLists()
        {
            $posts = get_posts(array('post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1));
            $remaining_pages = wp_list_pluck($posts, 'post_title', 'ID');
            /*if (is_array($remaining_pages)) {
                $remaining_pages['landing_page'] = __('Landing page', RNOC_TEXT_DOMAIN);
            }*/
            return $remaining_pages;
        }

        /**
         * get the cart url
         * @return string|null
         */
        function getCartUrl()
        {
            $cart_url = "";
            if (function_exists('wc_get_cart_url')) {
                $cart_url = wc_get_cart_url();
            }
            return apply_filters("rnoc_get_cart_page_url", $cart_url);
        }

        /**
         * get the checkout url
         * @return string|null
         */
        function getCheckoutUrl()
        {
            $checkout_url = "";
            if (function_exists('wc_get_checkout_url')) {
                $checkout_url = wc_get_checkout_url();
            }
            return apply_filters('rnoc_get_checkout_url', $checkout_url);
        }

        function complianceMessageOptions()
        {
            return array(
                'no_need_gdpr' => __('Disabled', RNOC_TEXT_DOMAIN),
                'dont_show_checkbox' => __('Don\'t show checkbox, but show GDPR compliance message', RNOC_TEXT_DOMAIN),
                'show_checkbox' => __('Show checkbox - default un checked', RNOC_TEXT_DOMAIN),
                'show_and_check_checkbox' => __('Show checkbox - default checked', RNOC_TEXT_DOMAIN),
            );
        }

        abstract function init();
    }
}