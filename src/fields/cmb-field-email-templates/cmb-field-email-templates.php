<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Email_After
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
        add_filter('cmb2_render_email_templates', array($this, 'render_email_templates'), 10, 5);
    }

    /**
     * Enqueue scripts and styles
     */
    public function setupAdminScripts()
    {
        $asset_path = apply_filters('cmb2_field_abandoned_cart_dashboard_asset_path', plugins_url('', __FILE__));
        wp_enqueue_style('abandoned-cart-email-template', $asset_path . '/css/style.css');
        wp_enqueue_script('abandoned-cart-email-template-js', $asset_path . '/js/main.js');
        wp_localize_script('abandoned-cart-email-template-js', 'email_template', array('email_field_empty' => __('Please enter email Id!', RNOC_TEXT_DOMAIN), 'sure_msg' => __('Are you sure?', RNOC_TEXT_DOMAIN), 'path' => admin_url('admin-ajax.php')));
    }

    /**
     * Render select box field
     */
    public function render_email_templates($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $this->setupAdminScripts();
        ?>

        <div>
            <input type="submit" name="submit-cmb" id="submit-cmb" class="button button-primary no-hide" value="Save">
        </div>
        <div class="main_email_tt">
            <div class="email-template">
                <h3><?php echo __('Email Templates', RNOC_TEXT_DOMAIN) ?></h3>
                <?php
                $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
                $settings = new \Rnoc\Retainful\Admin\Settings();
                $templates = $abandoned_cart->getEmailTemplates();
                ?>
                <p style="text-align: center;"><?php echo __("Add email templates at different intervals to maximize the possibility of recovering your abandoned carts.", RNOC_TEXT_DOMAIN) ?></p>
            </div>

        </div>

        <ul class="timeline">
            <li class="timeline-event">
                <label class="timeline-event-icon edit-brd"><span class="dashicons dashicons-yes icon-edit"></span></label>
                <div class="timeline-event-copy">
                    <p class="timeline-event-thumbnail">1 Hours After Abandonment</p>
                    <div class="time-inner">
                        <div class="message-head clearfix">
                            <div class="avatar"><a href="./index.php?qa=user&amp;qa_1=Oleg+Kolesnichenko"><img src="https://ssl.gstatic.com/accounts/ui/avatar_2x.png"></a></div>
                            <div class="user-detail">
                                <h5 class="handle">Favorite Items</h5>
                                <span class="qa-message-when-data">Shop</span>
                            </div>
                            <div class="user-detail">
                                <h5 class="handle">Number of Email Send</h5>
                                <span class="qa-message-when-data"><span class="dashicons dashicons-email-alt send-email"></span> 145</span>
                            </div>
                            <div class="user-detail user_active">
                                <label class="switch">
                                    <input type="checkbox" value="1" checked="" class="is-template-active" data-template="1">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="user-option">
                                <button type="button" class="email-button email-btn-edit">Edit</button>
                                <button type="button" class="email-button email-btn-delete">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
            <li class="timeline-event">
                <label class="timeline-event-icon delete-brd"><span class="dashicons dashicons-no-alt icon-delete"></span></label>
                <div class="timeline-event-copy">
                    <p class="timeline-event-thumbnail">1 Hours After Abandonment</p>
                    <div class="time-inner">
                        <div class="message-head clearfix">
                            <div class="avatar"><a href="./index.php?qa=user&amp;qa_1=Oleg+Kolesnichenko"><img src="https://ssl.gstatic.com/accounts/ui/avatar_2x.png"></a></div>
                            <div class="user-detail">
                                <h5 class="handle">Favorite Items</h5>
                                <span class="qa-message-when-data">Shop</span>
                            </div>
                            <div class="user-detail">
                                <h5 class="handle">Number of Email Send</h5>
                                <span class="qa-message-when-data"><span class="dashicons dashicons-email-alt send-email"></span> 25</span>
                            </div>
                            <div class="user-detail user_active">
                                <label class="switch">
                                    <input type="checkbox" value="1" checked="" class="is-template-active" data-template="1">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="user-option">
                                <button type="button" class="email-button email-btn-edit">Edit</button>
                                <button type="button" class="email-button email-btn-delete">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
            <li class="timeline-event">
                <label class="timeline-event-icon edit-brd"><span class="dashicons dashicons-yes icon-edit"></span></label>
                <div class="timeline-event-copy">
                    <p class="timeline-event-thumbnail">1 Hours After Abandonment</p>
                    <div class="time-inner">
                        <div class="message-head clearfix">
                            <div class="avatar"><a href="./index.php?qa=user&amp;qa_1=Oleg+Kolesnichenko"><img src="https://ssl.gstatic.com/accounts/ui/avatar_2x.png"></a></div>
                            <div class="user-detail">
                                <h5 class="handle">Favorite Items</h5>
                                <span class="qa-message-when-data">Shop</span>
                            </div>
                            <div class="user-detail">
                                <h5 class="handle">Number of Email Send</h5>
                                <span class="qa-message-when-data"><span class="dashicons dashicons-email-alt send-email"></span> 200</span>
                            </div>
                            <div class="user-detail user_active">
                                <label class="switch">
                                    <input type="checkbox" value="1" checked="" class="is-template-active" data-template="1">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="user-option">
                                <button type="button" class="email-button email-btn-edit">Edit</button>
                                <button type="button" class="email-button email-btn-delete">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <div class="force-center1">
            <a href="<?php echo admin_url('admin.php?page=' . $settings->slug . '_abandoned_cart_email_templates&task=create-email-template'); ?>"
               class="create-new-template"><?php echo __('Create New Template', RNOC_TEXT_DOMAIN) ?></a>
        </div>



        <?php
    }
}

$cmb2_field_email_after = new CMB2_Field_Email_After();
