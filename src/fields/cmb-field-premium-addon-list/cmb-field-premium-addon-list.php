<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Premium_Addon_List
{
    /**
     * Initialize the plugin by hooking into CMB2
     */
    function __construct()
    {
        add_filter('cmb2_render_premium_addon_list', array($this, 'render_premium_addon_list'), 10, 5);
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_abandoned_cart_dashboard_asset_path', plugins_url('', __FILE__));
        wp_enqueue_style('abandoned-cart-email-template', $asset_path . '/css/main.css', array(), RNOC_VERSION);
    }

    /**
     * Render select box field
     */
    function render_premium_addon_list($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $this->setupAdminScripts();
        $available_addon_list = apply_filters('rnoc_get_premium_addon_list', array());
        if (!empty($available_addon_list)) {
            ?>
            <div class="rnoc-grid-container retainful_premium_card_box">
                <?php
                foreach ($available_addon_list as $addon) {
                    $title = $addon->title();
                    if (!empty($title)) {
                        ?>
                        <div class="rnoc-grid-cell retainful_premium_grid">
                            <div class="avatar-lg-bg">
                                <i class="dashicons <?php echo $addon->icon(); ?> retain-icon-premium"></i>
                            </div>
                            <div class="header retainful_premium_heading"><?php echo $title; ?></div>
                            <div class="retainful_premium_para"><p><?php
                                    echo $addon->description();
                                    ?></p>
                            </div>
                            <div class="footer">
                                <button type="button" class="view-addon-btn button button-premium"
                                        data-slug="<?php echo $addon->slug(); ?>"><?php echo __('Go to Configuration', RNOC_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <?php
        } else {
            $available_addon_list = array(
                array(
                    'title' => __('Add to Cart Popup for Email collection (Premium)', RNOC_TEXT_DOMAIN),
                    'description' => __('Collect customer email at the time of adding to cart. This will help you recover the cart even if they abandon before checkout.', RNOC_TEXT_DOMAIN),
                    'icon' => 'dashicons-cart'
                ),
                array(
                    'title' => __('Coupon For Email Collection (Premium)', RNOC_TEXT_DOMAIN),
                    'description' => __('Encourage customers to enter the email to get a coupon code. This way you will come to know the customer email and can recover cart even if they abandon before checkout', RNOC_TEXT_DOMAIN),
                    'icon' => 'dashicons-tag'
                ),
                array(
                    'title' => __('Countdown Timer (Premium)', RNOC_TEXT_DOMAIN),
                    'description' => __('Give a clear deadline to grab the offer and add urgency using Countdown timer', RNOC_TEXT_DOMAIN),
                    'icon' => 'dashicons-clock'
                ),
                array(
                    'title' => __('IP Filter (Premium)', RNOC_TEXT_DOMAIN),
                    'description' => __('Exclude capturing carts from selected IPs or a range of IPs.', RNOC_TEXT_DOMAIN),
                    'icon' => 'dashicons-clock'
                )
            );
            ?>
            <div class="rnoc-grid-container retainful_premium_card_box">
                <?php
                $library = new Rnoc\Retainful\library\RetainfulApi();
                $premium_url = $library->upgradePremiumUrl();
                foreach ($available_addon_list as $addon) {
                    ?>
                    <div class="rnoc-grid-cell retainful_premium_grid">
                        <div class="avatar-lg-bg">
                            <i class="dashicons <?php echo $addon['icon']; ?> retain-icon-premium"></i>
                        </div>
                        <div class="header retainful_premium_heading"><?php echo $addon['title']; ?></div>
                        <div class="retainful_premium_para"><p><?php
                                echo $addon['description'];
                                ?></p>
                        </div>
                        <div class="footer">
                            <a href="<?php echo $premium_url; ?>"
                               target="_blank"
                               class="button button-premium"><?php echo __('Upgrade to Premium', RNOC_TEXT_DOMAIN); ?></a>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        }
        ?>
        <script>
            jQuery('.view-addon-btn').click(function () {
                var slug = jQuery(this).data('slug');
                jQuery('#<?php echo RNOC_PLUGIN_PREFIX; ?>retainful_premium_addon-tab-' + slug).trigger('click');
            });
            jQuery('.cmb-tabs div').click(function () {
                var save_btn = jQuery('#submit-cmb');
                var id = jQuery(this).attr('id');
                console.log(save_btn);
                if (id === "rnoc_retainful_premium_addon-tab-general-settings") {
                    save_btn.hide();
                } else {
                    save_btn.show();
                }
            });
        </script>
        <?php
    }
}

new CMB2_Field_Premium_Addon_List();