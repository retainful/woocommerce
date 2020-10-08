<?php
/**
 * @var $available_addon_list array
 * @var $base_url string
 */
require_once "tabs.php";
?>
<div class="card">
    <?php
    if (!empty($available_addon_list)) {
        ?>
        <div class="rnoc-grid-container retainful_premium_card_box">
            <?php
            foreach ($available_addon_list as $addon) {
                $title = $addon->title();
                $slug = $addon->slug();
                if (!empty($title) && $slug != "do-not-track-ip") {
                    ?>
                    <div class="rnoc-grid-cell retainful_premium_grid">
                        <div class="avatar-lg-bg">
                            <i class="dashicons <?php echo $addon->icon(); ?> retain-icon-premium"></i>
                        </div>
                        <div class="header retainful_premium_heading"><?php echo $title; ?></div>
                        <div class="retainful_premium_para">
                            <p><?php
                                echo $addon->description();
                                ?>
                            </p>
                        </div>
                        <div class="footer">
                            <a class="view-addon-btn button button-premium"
                               href="<?php echo add_query_arg(array('add-on' => $slug), $base_url) ?>"
                            ><?php echo __('Go to Configuration', RNOC_TEXT_DOMAIN); ?></a>
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
                'title' => __('Add-to-Cart Email Collection Popup (Premium)', RNOC_TEXT_DOMAIN),
                'description' => __('Collect customer email at the time of adding to cart. This will help you recover the cart even if they abandon before checkout.', RNOC_TEXT_DOMAIN),
                'icon' => 'dashicons-cart'
            ),
            array(
                'title' => __('Countdown Timer (Premium)', RNOC_TEXT_DOMAIN),
                'description' => __('Give a clear deadline to grab the offer and create a sense of urgency using Countdown Timer', RNOC_TEXT_DOMAIN),
                'icon' => 'dashicons-clock'
            ),
            array(
                'title' => __('Exit Intent Popup (Premium)', RNOC_TEXT_DOMAIN),
                'description' => __('When customers try to leave your store, stop them by showing a coupon code or just collect their email and catch them later.', RNOC_TEXT_DOMAIN),
                'icon' => 'dashicons-external'
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
                           class="button button-premium"><?php echo __('Upgrade to a Paid Plan', RNOC_TEXT_DOMAIN); ?></a>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
    ?>
</div>