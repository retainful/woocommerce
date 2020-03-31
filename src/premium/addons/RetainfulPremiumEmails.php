<?php
/**
 * Created by PhpStorm.
 * User: cartrabbit
 * Date: 2019-05-15
 * Time: 13:54
 */
if (!class_exists('RetainfulPremiumEmails')) {
    include __DIR__ . '/RetainfulPremiumAddonBase.php';

    class RetainfulPremiumEmails extends RetainfulPremiumAddonBase
    {
        function __construct()
        {
            parent::__construct();
        }

        function init()
        {
            add_filter('rnoc_premium_templates_list', array($this, 'getPremiumTemplates'));
            add_filter('rnoc_get_email_template_by_id', array($this, 'getTemplate'));
        }

        function getTemplate($template_id)
        {
            if (!empty($template_id)) {
                if (file_exists(RNOCPREMIUM_PLUGIN_PATH . 'src/templates/premium-' . $template_id . '.html')) {
                    ob_start();
                    include RNOCPREMIUM_PLUGIN_PATH . 'src/templates/premium-' . $template_id . '.html';
                    return ob_get_clean();
                }
            }
            return NULL;
        }

        /**
         * return the list of id of premium templates
         * @return array
         */
        function getPremiumTemplates()
        {
            $templates = array(
                1, 2, 3
            );
            return $templates;
        }
    }
}