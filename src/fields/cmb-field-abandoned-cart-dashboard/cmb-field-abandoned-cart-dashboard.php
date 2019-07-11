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
        $admin = new \Rnoc\Retainful\Admin\Settings();
        $start_end_dates = $abandoned_cart_obj->start_end_dates;
        $duration = (isset($_GET['duration'])) ? $_GET['duration'] : 'last_seven';
        if ($duration != "custom") {
            $start_date = $start_end_dates[$duration]['start_date'];
            $end_date = $start_end_dates[$duration]['end_date'];
        } else if (isset($_GET['start']) && isset($_GET['end'])) {
            $start_date = $_GET['start'];
            $end_date = $_GET['start'];
        } else {
            $start_date = $start_end_dates['last_seven']['start_date'];
            $end_date = $start_end_dates['last_seven']['end_date'];
        }
        $cart_details = $abandoned_cart_obj->getStaticsForDashboard($start_date, $end_date);
        $plan = $admin->getUserActivePlan();
        $width_class = 'rnoc_width_23';
        $is_free_user = false;
        if (!in_array($plan, array('pro', 'business'))) {
            $width_class = 'rnoc_width_18';
            $is_free_user = true;
        }
        ?>
        <div class="rnoc_counter_container retainful_abandoned_container">
            <div class="rnoc_counter_widget widget_violet card_main_box <?php echo $width_class; ?>">
                <div class="rnoc_counter_container">
                    <div class="rnoc_widget_title_container">
                        <span class="rnoc_counter_title card-box-name "><?php echo __('Abandoned Carts', RNOC_TEXT_DOMAIN); ?></span>
                    </div>
					<div class="avatar-lg avatar-lg-1">
						<img src="<?php echo RNOC_PLUGIN_URL; ?>src/assets/images/icon-1.png" class="img_icon" alt="Abandoned Carts">
					</div>
                    <div class="rnoc_counter_body card-box-value " id="rnoc_abandoned_carts">
                        <?php echo $cart_details['abandoned_carts']; ?> 
                    </div>
                </div>
            </div>
            <div class="rnoc_counter_widget widget_orange card_main_box <?php echo $width_class; ?>">
                <div class="rnoc_countcmb-type-abandoned-cart-dashboarder_container">
                    <div class="rnoc_widget_title_container">
                        <span class="rnoc_counter_title card-box-name"><?php echo __('Abandoned Amount', RNOC_TEXT_DOMAIN); ?></span>
                    </div>
					<div class="avatar-lg avatar-lg-2">
						<img src="<?php echo RNOC_PLUGIN_URL; ?>src/assets/images/icon-2.png" class="img_icon" alt="Abandoned Carts">
					</div>					
					<div class="rnoc_counter_body card-box-value" id="rnoc_abandoned_total">
                        <?php echo $cart_details['abandoned_total']; ?> 
                    </div>
                </div>
            </div>
            <div class="rnoc_counter_widget widget_blue card_main_box <?php echo $width_class; ?>">
                <div class="rnoc_counter_container">
                    <div class="rnoc_widget_title_container">
                        <span class="rnoc_counter_title card-box-name"><?php echo __('Recovered Carts', RNOC_TEXT_DOMAIN); ?></span>
                    </div>
                    <div class="avatar-lg avatar-lg-3">
						<img src="<?php echo RNOC_PLUGIN_URL; ?>src/assets/images/icon-3.png" class="img_icon" alt="Abandoned Carts">
					</div>
					<div class="rnoc_counter_body card-box-value" id="rnoc_recovered_carts">
                        <?php echo $cart_details['recovered_carts']; ?> 
                    </div>
                </div>
            </div>
            <div class="rnoc_counter_widget widget_green card_main_box <?php echo $width_class; ?>">
                <div class="rnoc_counter_container">
                    <div class="rnoc_widget_title_container">
                        <span class="rnoc_counter_title card-box-name"><?php echo __('Recovered Amount', RNOC_TEXT_DOMAIN); ?></span>
                    </div>
					<div class="avatar-lg avatar-lg-4">
						<img src="<?php echo RNOC_PLUGIN_URL; ?>src/assets/images/icon-4.png" class="img_icon" alt="Abandoned Carts">
					</div>
					<div class="rnoc_counter_body card-box-value" id="rnoc_recovered_total">
                        <?php echo $cart_details['recovered_total']; ?> 
                    </div>
                </div>
            </div>
            <?php
            if ($is_free_user) {
                ?>
                <div class="rnoc_counter_widget widget_red card_main_box card_premium_box  <?php echo $width_class; ?>">
                    <div class="rnoc_counter_container">
                        <div class="rnoc_counter_box upgrade-premium_box">
                            <?php
                            echo __('Get more features like Email collection popup, Countdown timer & coupons', RNOC_TEXT_DOMAIN);
                            $api = new \Rnoc\Retainful\library\RetainfulApi();
                            ?>
                            <br>
                            <a href="<?php echo $api->upgradePremiumUrl(); ?>"
                               target="_blank"><?php echo __('Upgrade to Premium', RNOC_TEXT_DOMAIN) ?></a>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_abandoned_cart_dashboard_asset_path', plugins_url('', __FILE__));
        wp_enqueue_style('abandoned-cart-dashboard', $asset_path . '/css/main.css');
    }
}

$cmb2_field_abandon_cart_dashboard = new CMB2_Field_Abandoned_Cart_Dashboard();
