<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Abandoned_Cart_Dashboard
{

    /**
     * Current version number
     */
    const VERSION = '1.0.0';

    /**
     * Initialize the plugin by hooking into CMB2
     */
    public function __construct()
    {
        add_filter('cmb2_render_abandoned_cart_dashboard', array($this, 'render_abandoned_cart_dashboard'), 10, 5);
    }

    /**
     *
     */
    public function render_abandoned_cart_dashboard($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $this->setupAdminScripts();
        $abandoned_cart_obj = new \Rnoc\Retainful\AbandonedCart();
        $start_end_dates = $abandoned_cart_obj->start_end_dates;
        $cart_details = $abandoned_cart_obj->getStaticsForDashboard($start_end_dates['last_seven']['start_date'], $start_end_dates['last_seven']['end_date']);
        ?>
        <div class="rnoc_counter_container">
            <div class="rnoc_counter_widget widget_violet">
                <div class="rnoc_counter_container">
                    <div class="rnoc_widget_title_container">
                        <span class="rnoc_counter_title"><?php echo __('Abandoned Carts', RNOC_TEXT_DOMAIN); ?></span>
                    </div>
                    <div class="rnoc_counter_body" id="rnoc_abandoned_carts">
                        <?php echo $cart_details['abandoned_carts']; ?>
                    </div>
                </div>
            </div>
            <div class="rnoc_counter_widget widget_orange">
                <div class="rnoc_counter_container">
                    <div class="rnoc_widget_title_container">
                        <span class="rnoc_counter_title"><?php echo __('Abandoned Amount', RNOC_TEXT_DOMAIN); ?></span>
                    </div>
                    <div class="rnoc_counter_body" id="rnoc_abandoned_total">
                        <?php echo $cart_details['abandoned_total']; ?>
                    </div>
                </div>
            </div>
            <div class="rnoc_counter_widget widget_blue">
                <div class="rnoc_counter_container">
                    <div class="rnoc_widget_title_container">
                        <span class="rnoc_counter_title"><?php echo __('Recovered Carts', RNOC_TEXT_DOMAIN); ?></span>
                    </div>
                    <div class="rnoc_counter_body" id="rnoc_recovered_carts">
                        <?php echo $cart_details['recovered_carts']; ?>
                    </div>
                </div>
            </div>
            <div class="rnoc_counter_widget widget_green">
                <div class="rnoc_counter_container">
                    <div class="rnoc_widget_title_container">
                        <span class="rnoc_counter_title"><?php echo __('Recovered Amount', RNOC_TEXT_DOMAIN); ?></span>
                    </div>
                    <div class="rnoc_counter_body" id="rnoc_recovered_total">
                        <?php echo $cart_details['recovered_total']; ?>
                    </div>
                </div>
            </div>
        </div>
        <style>
            #submit-cmb {
                display: none;
            }
        </style>
        <?php
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_abandoned_cart_dashboard_asset_path', plugins_url('', __FILE__));
        wp_enqueue_style('abandoned-cart-dashboard', $asset_path . '/css/main.css');
        /*wp_enqueue_script('abandoned-cart-dashboard-chart-js', $asset_path . '/js/chart.min.js');
        wp_enqueue_script('abandoned-cart-dashboard-init-chart-js', $asset_path . '/js/main.js');*/
    }
}

$cmb2_field_unlock_usage_restriction = new CMB2_Field_Abandoned_Cart_Dashboard();
