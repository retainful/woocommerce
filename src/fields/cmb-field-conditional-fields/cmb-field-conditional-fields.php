<?php
/**
 * CMB2 Conditionals.
 */
if (!class_exists('CMB2_RNOC_Conditionals', false)) {
    /**
     * CMB2_Conditionals Plugin.
     */
    class CMB2_RNOC_Conditionals
    {
        /**
         * Constructor - Set up the actions for the plugin.
         */
        public function __construct()
        {
            add_action('admin_footer', array($this, 'setUpAdminScripts'));
        }

        /**
         * Setup conditional fields
         */
        function setUpAdminScripts()
        {
            $script_src = apply_filters('cmb2_conditionals_enqueue_script_src', plugins_url('/cmb-field-conditional-fields.js', __FILE__));
            wp_enqueue_script('cmb2-conditionals', $script_src, array('jquery', 'cmb2-scripts'), RNOC_VERSION, true);
        }
    }
} /* End of class-exists wrapper. */
new CMB2_RNOC_Conditionals();