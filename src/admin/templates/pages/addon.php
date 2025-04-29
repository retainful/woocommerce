<?php
/**
 * @var $available_addon_list array
 * @var $activated_addons array
 * @var $base_url string
 */
require_once "tabs.php";
?>

<div class="card">
    <div class="rnoc-tabs-container">
        <ul class="rnoc-tabs-menu">
            <li class="rnoc-tab-item active" data-tab="active-addons">Active Addons</li>
            <li class="rnoc-tab-item" data-tab="available-addons">Available Addons</li>
        </ul>
    </div>
    <div class="rnoc-tabs-content">
        <!-- Active Addons Tab -->
        <div class="rnoc-tab-content active" id="active-addons">
			<?php
			if (!empty($activated_addons)) {
				?>
                <div class="rnoc-grid-container retainful_premium_card_box">
					<?php
					foreach ($activated_addons as $addon_name => $addon) {
						$title = $addon['name'];
						$deactivation_url = wp_nonce_url(
							admin_url('plugins.php?action=deactivate&plugin=' . urlencode($addon['plugin_file'])),
							'deactivate-plugin_' . $addon['plugin_file']
						);
						if (!empty($title)) {
							?>
                            <div class="rnoc-grid-cell retainful_premium_grid">
                                <div class="avatar-lg-bg">
                                    <img class="retain-icon-premium" src="<?php echo $addon['icon_url']; ?>">
                                </div>
                                <div class="header retainful_premium_heading">
									<?php echo $title; ?>
                                </div>
                                <div class="retainful_premium_para">
                                    <p><?php echo $addon['description']; ?></p>
                                </div>
                                <div class="action-buttons">
                                    <button type="button" data-action="rnoc_deactivate_plugin" id="deactivate_plugin_button"
                                            data-security="<?php echo wp_create_nonce('deactivate_plugin_button') ?>"
                                            data-plugin-file="<?php echo esc_attr($addon['plugin_file']); ?>"
                                            class="view-addon-btn button button-premium"><?php echo __('DeActivate', RNOC_TEXT_DOMAIN); ?></button>
                                    <a  class="view-addon-btn button button-premium"
                                       href="<?php echo admin_url('admin.php?page='.$addon['page']) ?? '#'; ?>">
										<?php echo __('Open', RNOC_TEXT_DOMAIN); ?>
                                    </a>
                                </div>
                            </div>
							<?php
						}
					}
					?>
                </div>
				<?php
			} else {
				echo '<p>' . __('No active addons found.', RNOC_TEXT_DOMAIN) . '</p>';
			}
			?>
        </div>
        <!-- Available Addons Tab -->
        <div class="rnoc-tab-content" id="available-addons">
			<?php if (!empty($available_addon_list)) { ?>
                <div class="rnoc-grid-container retainful_premium_card_box">
					<?php
					foreach ($available_addon_list as $addon) {
						$title = $addon['name'];
						$activation_url = wp_nonce_url(
							admin_url('plugins.php?action=activate&plugin=' . urlencode($addon['plugin_file'])),
							'activate-plugin_' . $addon['plugin_file']
						);
						if (!empty($title)) {
							?>
                            <div class="rnoc-grid-cell retainful_premium_grid">
                                <div class="avatar-lg-bg">
                                    <img class="retain-icon-premium" src="<?php echo $addon['icon_url']; ?>">
                                </div>
                                <div class="header retainful_premium_heading">
									<?php echo $title; ?>
                                </div>
                                <div class="retainful_premium_para">
                                    <p><?php echo $addon['description']; ?></p>
                                </div>
                                <div class="footer">
                                    <?php if($addon['show_activate']) { ?>
                                        <button type="button" data-action="rnoc_activate_plugin" id="activate_plugin_button"
                                                data-security="<?php echo wp_create_nonce('rnoc_activate_plugin') ?>"
                                                data-plugin-file="<?php echo esc_attr($addon['plugin_file']); ?>"
                                                class="view-addon-btn button button-premium"><?php echo __('Activate', RNOC_TEXT_DOMAIN); ?></button>
                                    <?php } else { ?>
                                    <a class="view-addon-btn button button-premium"
                                       href="<?php echo $addon['download_url']; ?>">
										<?php echo __('Download', RNOC_TEXT_DOMAIN); ?>
                                    </a>
                                <?php }?>
                                </div>
                            </div>
							<?php
						}
					}
					?>
                </div>
				<?php
			} else {
				echo '<p>' . __('No available addons found.', RNOC_TEXT_DOMAIN) . '</p>';
			}
			?>
        </div>
    </div>
</div>

