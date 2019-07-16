<?php

namespace Rnoc\Retainful\Integrations;

class MultiLingual
{
    /**
     * Get all available languages
     * @return mixed|void
     */
    function getAvailableLanguages()
    {
        $languages = apply_filters('wpml_active_languages', NULL, 'orderby=id&order=desc');
        if (empty($languages) && function_exists('icl_get_languages')) {
            $languages = icl_get_languages();
        }
        return $languages;
    }

    /**
     * Get the default language of the site
     * @return String|null
     */
    function getDefaultLanguage()
    {
        $current_lang = NULL;
        $wpml_options = get_option('icl_sitepress_settings');
        if (!empty($wpml_options)) {
            return (isset($wpml_options['default_language'])) ? $wpml_options['default_language'] : NULL;
        }
        if (function_exists('get_locale')) {
            $current_lang = get_locale();
            if (empty($current_lang)) {
                $current_lang = 'en';
            }
        }
        return $current_lang;
    }

    /**
     * Get the default language of the site
     * @return String|null
     */
    function getCurrentLanguage()
    {
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }
        if ($default_lang = $this->getDefaultLanguage()) {
            return $default_lang;
        }
        return NULL;
    }
}