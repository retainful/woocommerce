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
     * Render select box field
     */
    function render_premium_addon_list($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
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
                            <div class="header retainful_premium_heading"><?php echo $title; ?></div>
                            <div class="retainful_premium_para"><p><?php
                                    echo $addon->description();
                                    ?></p>
                            </div>
                            <div class="footer">
                                <button type="button" class="view-addon-btn button button-green button-premium"   data-slug="<?php echo $addon->slug(); ?>"><?php echo __('Go to Configuration', RNOC_TEXT_DOMAIN); ?></button>
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
                ),
                array(
                    'title' => __('Coupon For Email Collection (Premium)', RNOC_TEXT_DOMAIN),
                    'description' => __('Encourage customers to enter the email to get a coupon code. This way you will come to know the customer email and can recover cart even if they abandon before checkout', RNOC_TEXT_DOMAIN),
                ),
                array(
                    'title' => __('Countdown Timer (Premium)', RNOC_TEXT_DOMAIN),
                    'description' => __('Give a clear deadline to grab the offer and add urgency using Countdown timer', RNOC_TEXT_DOMAIN),
                )
            );
            ?>
            <style>
                #submit-cmb {
                    display: none;
                }
            </style>
            <div class="rnoc-grid-container retainful_premium_card_box">
                <?php
                $library = new Rnoc\Retainful\library\RetainfulApi();
                $premium_url = $library->upgradePremiumUrl();
                foreach ($available_addon_list as $addon) {
                    ?>
                    <div class="rnoc-grid-cell retainful_premium_grid">
                        <div class="header retainful_premium_heading"><?php echo $addon['title']; ?></div>
                        <div class="retainful_premium_para"><p><?php
                                echo $addon['description'];
                                ?></p>
                        </div>
                        <div class="footer">
                            <a href="<?php echo $premium_url; ?>"
                               target="_blank"
                               class="button button-green button-premium"><?php echo __('Upgrade to Premium', RNOC_TEXT_DOMAIN); ?></a>
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
        </script>
        <style>
            .rnoc-grid-container {
                display: grid;
                grid-template-columns: 32% 32% 32%;
                grid-gap: 30px;
                padding: 10px;
            }

            .rnoc-grid-container .rnoc-grid-cell {
                padding: 10px;
                border-radius: 2px;
            }

            .rnoc-grid-cell .header {
                font-weight: 800;
                font-size: large;
                text-transform: capitalize;
            }

            .rnoc-grid-cell .description {
                text-align: justify;

            }
            .retainful_premium_card_box .retainful_premium_grid{
                background: #f4f7fd;
                border-radius: 4px;
                box-shadow: 0px 4px 3px rgba(126, 142, 177, 0.04);
                margin-bottom: 20px;
                padding: 20px 20px;
            }
            .retainful_premium_card_box .retainful_premium_grid .retainful_premium_heading
            {
                padding: 0px 5px 0px 5px;
                text-align: center;
                font-size: 25px;
                color: #2d2f3a;
                font-weight: 800;
                line-height: 36px;
            }
            .retainful_premium_card_box .retainful_premium_grid .retainful_premium_para p
            {
                padding: 5px 20px;
                text-align: center;
                font-size: 14px;
                color: #6c747a;
                line-height: 22px;
                font-weight: 500;
            }
            .retainful_premium_card_box .retainful_premium_grid .footer
            {
                text-align: center;
            }
            .retainful_premium_card_box .retainful_premium_grid .button-premium {
                color: #fff;
                background-color: #2dcb73 !important;
                display: inline-block;
                text-decoration: none;
                font-size: 15px;
                font-weight: 500;
                line-height: 45px;
                height: 45px;
                margin: 0;
                padding: 0px 45px 0px;
                cursor: pointer;
                border-width: 0px;
                border-style: solid;
                -webkit-appearance: none;
                border-radius: 5px;
                white-space: nowrap;
                box-sizing: border-box;
                margin-right: 28px;
                box-shadow: 0px 4px 3px rgba(126, 142, 177, 0.40);
            }
            .retainful_premium_card_box .retainful_premium_grid .button-premium:hover
            {
                color: #fff;
                box-shadow: 0px 7px 6px rgba(126, 142, 177, 0.40);
            }
            @media only screen and (max-width: 1350px) {

                .retainful_premium_card_box .retainful_premium_grid .button-premium {
                    line-height: 35px;
                    height: 40px;
                    margin: 0;
                    padding: 0px 14px 0px;
                }
                .retainful_premium_card_box .retainful_premium_grid {
                    background: #f4f7fd;
                    border-radius: 4px;
                    box-shadow: 0px 4px 3px rgba(126, 142, 177, 0.04);
                    margin-bottom: 20px;
                    padding: 14px 10px;
                }
                .retainful_premium_card_box .retainful_premium_grid .retainful_premium_heading {
                    padding: 5px 5px 0px 5px;
                    text-align: center;
                    font-size: 19px;
                    color: #2d2f3a;
                    font-weight: 800;
                    line-height: 26px;
                    margin-right: 0px;
                }
                .retainful_premium_card_box .retainful_premium_grid .retainful_premium_para p {
                    padding: 5px 3px;
                    text-align: center;
                    font-size: 14px;
                    color: #6c747a;
                    line-height: 22px;
                    font-weight: 500;
                }
                @media only screen and (max-width: 1024px) {
                    .retainful_premium_card_box {
                        display: block;
                        grid-template-columns: 24% 25% 25% 24%;
                        grid-gap: 10px;
                        padding: 10px;
                    }
                }
        </style>
        <?php
    }

    /**
     * get all the available addon list
     * @return array|mixed|object
     */
    function getAddonsList()
    {
        $addon_list = get_option('rnoc_available_addon', '{}');
        $addon_list_updated_on = get_option('rnoc_addon_list_updated_at', NULL);
        $update_addon_list = true;
        if (!empty($addon_list_updated_on)) {
            if ($addon_list_updated_on < current_time('timestamp')) {
                $update_addon_list = false;
            }
        }
        if ($update_addon_list) {
            if (function_exists('file_get_contents')) {
                //TODO: Change the json CDN api
                $remote_addon_list = file_get_contents('https://api.jsonbin.io/b/5cdab7bb14c2b53c0914a41b/6');
                if (empty($remote_addon_list)) {
                    $addon_list = '{}';
                } else {
                    $addon_list = $remote_addon_list;
                }
                update_option('rnoc_available_addon', $addon_list);
                update_option('rnoc_addon_list_updated_at', current_time('timestamp'));
            }
        }
        $list = json_decode($addon_list);
        return $list;
    }
}

new CMB2_Field_Premium_Addon_List();
