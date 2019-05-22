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
            <div class="rnoc-grid-container">
                <?php
                foreach ($available_addon_list as $addon) {
                    ?>
                    <div class="rnoc-grid-cell">
                        <div class="header"><?php echo $addon->title(); ?></div>
                        <div class="description"><p><?php
                                echo $addon->description();
                                ?></p>
                        </div>
                        <div class="footer">
                            <button type="button" class="view-addon-btn button button-green"
                                    data-slug="<?php echo $addon->slug(); ?>"><?php echo __('Go to Configuration', RNOC_TEXT_DOMAIN); ?></button>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } else {
            $available_addon_list = array(
                array(
                    'title' => 'Add to Cart popup (Premium)',
                    'description' => 'Collect customer email at the time of adding to cart. This will help you recover the cart even if they abandon before checkout.',
                )
            );
            ?>
            <div class="rnoc-grid-container">
                <?php
                $library = new Rnoc\Retainful\library\RetainfulApi();
                $premium_url = $library->upgradePremiumUrl();
                foreach ($available_addon_list as $addon) {
                    ?>
                    <div class="rnoc-grid-cell">
                        <div class="header"><?php echo $addon['title']; ?></div>
                        <div class="description"><p><?php
                                echo $addon['description'];
                                ?></p>
                        </div>
                        <div class="footer">
                            <a href="<?php echo $premium_url; ?>"
                               target="_blank"
                               class="button button-green"><?php echo __('Upgrade to Premium', RNOC_TEXT_DOMAIN); ?></a>
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
                grid-template-columns: 24% 25% 25% 24%;
                grid-gap: 10px;
                padding: 10px;
            }

            .rnoc-grid-container .rnoc-grid-cell {
                box-shadow: 2px 2px 5px 3px #e2e2e2;
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
