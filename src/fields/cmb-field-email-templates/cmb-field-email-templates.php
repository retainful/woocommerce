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
        wp_enqueue_style('abandoned-cart-email-template', $asset_path . '/css/style.css',array(),RNOC_VERSION);
        wp_enqueue_script('abandoned-cart-email-template-js', $asset_path . '/js/main.js',array(),RNOC_VERSION);
        wp_localize_script('abandoned-cart-email-template-js', 'email_template', array('email_field_empty' => __('Please enter email Id!', RNOC_TEXT_DOMAIN), 'sure_msg' => __('Are you sure?', RNOC_TEXT_DOMAIN), 'path' => admin_url('admin-ajax.php')));
    }

    /**
     * Sort array by key
     * @param $array
     * @param $on
     * @param int $order
     * @return array
     */
    function sortArray($array, $on, $order = SORT_ASC)
    {
        $new_array = array();
        $sortable_array = array();
        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        if ($k2 == $on) {
                            $sortable_array[$k] = $v2;
                        }
                    }
                } else {
                    $sortable_array[$k] = $v;
                }
            }
            switch ($order) {
                case SORT_ASC:
                    asort($sortable_array);
                    break;
                case SORT_DESC:
                    arsort($sortable_array);
                    break;
            }
            foreach ($sortable_array as $k => $v) {
                $new_array[$k] = $array[$k];
            }
        }
        return $new_array;
    }

    /**
     * Render select box field
     */
    public function render_email_templates($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $language_helper = new \Rnoc\Retainful\Integrations\MultiLingual();
        $available_languages = $language_helper->getAvailableLanguages();
        $default_language = $language_helper->getDefaultLanguage();
        $current_language = $language_helper->getCurrentLanguage();
        $active_language = (isset($_GET['language_code'])) ? $_GET['language_code'] : $default_language;
        $this->setupAdminScripts();
        ?>
        <div>
            <input type="submit" name="submit-cmb" id="submit-cmb" class="button button-primary no-hide" value="Save">
        </div>
        <div class="main_email_tt">
            <div class="email-template">
                <h3><?php echo __('Email Templates', RNOC_TEXT_DOMAIN) ?></h3>
                <p style="text-align: center;"><?php echo __("Add email templates at different intervals to maximize the possibility of recovering your abandoned carts.", RNOC_TEXT_DOMAIN) ?></p>
            </div>
        </div>
        <?php
        if (!empty($available_languages)) {
            ?>
            <div class="nav-tab-wrapper">
                <?php
                foreach ($available_languages as $language) {
                    $lang_code = $language['code'];
                    ?>
                    <a class="nav-tab <?php echo ($lang_code == $active_language) ? 'nav-tab-active' : '' ?>"
                       href="<?php echo admin_url('admin.php?' . http_build_query(array('page' => $_GET['page'], 'language_code' => $lang_code))) ?>"><?php echo $language['native_name']; ?></a>
                    <?php
                }
                ?>
            </div>
            <?php
        }
        ?>
        <div class="retainful_abandoned_email_main">
            <ul class="retainful_abandoned_email">
                <?php
                $abandoned_cart = new \Rnoc\Retainful\AbandonedCart();
                $settings = new \Rnoc\Retainful\Admin\Settings();
                $templates = $abandoned_cart->getEmailTemplates($active_language);
                $arranged_templates = array();
                if (!empty($templates)) {
                    foreach ($templates as $template) {
                        $in_sec = ($template->day_or_hour == "Days") ? 86400 : 3600;
                        $choosen_time = intval($template->frequency) * $in_sec;
                        $arranged_templates[] = array('time' => $choosen_time, 'template' => $template);
                    }
                    $arranged_templates = $this->sortArray($arranged_templates, 'time');
                }
                if (!empty($arranged_templates)) {
                    foreach ($arranged_templates as $arranged_template) {
                        $template = $arranged_template['template'];
                        ?>
                        <li class="retainful_abandoned_email-event" id="template-no-<?php echo $template->id ?>">
                            <label class="retainful_abandoned_email-event-icon <?php echo ($template->is_active == 1) ? ' edit-brd' : ' delete-brd'; ?>"><span
                                        class="dashicons <?php echo ($template->is_active == 1) ? ' dashicons-yes icon-edit' : ' dashicons-no-alt icon-delete'; ?>"></span></label>
                            <div class="retainful_abandoned_email-event-copy">
                                <p class="retainful_abandoned_email-event-thumbnail"><?php echo __('Send ', RNOC_TEXT_DOMAIN) . $template->frequency . ' ' . $template->day_or_hour . ' ' . __('After Abandonment', RNOC_TEXT_DOMAIN) ?></p>
                                <div class="retainful_abandoned_email-inner">
                                    <div class="message-head clearfix">
                                        <div class="user-detail">
                                            <h5 class="handle"><?php echo $template->template_name; ?></h5>
                                            <span class="qa-message-when-data">
                                                <span class="handle-label"><?php echo __('Subject: ', RNOC_TEXT_DOMAIN) ?></span>
                                                    <b><?php
                                                        echo $template->subject; ?></b></span>
                                        </div>
                                        <div class="user-option">
                                            <h5 class="handle-label"><?php echo __('Sent Emails', RNOC_TEXT_DOMAIN); ?></h5>
                                            <span class="qa-message-when-data number-data"><?php echo $template->emails_sent ?></span>
                                        </div>
                                        <div class="user-option">
                                            <h5 class="handle-label"><?php echo ($template->is_active == 1) ? __('Enabled', RNOC_TEXT_DOMAIN) : __('Disabled', RNOC_TEXT_DOMAIN); ?></h5>
                                            <label class="switch">
                                                <input type="checkbox"
                                                       value="1" <?php echo ($template->is_active == 1) ? ' checked' : ''; ?>
                                                       class="is-template-active"
                                                       data-template="<?php echo $template->id ?>">
                                                <span class="slider round"></span>
                                            </label>
                                        </div>
                                        <div class="user-option">
                                            <a href="<?php echo admin_url('admin.php?page=' . $settings->slug . '_abandoned_cart_email_templates&task=edit-template&template=' . $template->id) ?>&language_code=<?php echo $active_language ?>"
                                               class="email-button email-btn-edit"><?php echo __('Edit', RNOC_TEXT_DOMAIN); ?></a>
                                            <button type="button"
                                                    class="email-button email-btn-delete remove-email-template"
                                                    data-template="<?php echo $template->id ?>"><?php echo __('Delete', RNOC_TEXT_DOMAIN) ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <p class="force-center text-danger"><?php echo __('No email templates found! So, No emails were sent!', RNOC_TEXT_DOMAIN); ?></p>
                    </tr>
                    <?php
                }
                ?>
            </ul>
        </div>

        <div class="force-center1">
            <a href="<?php echo admin_url('admin.php?page=' . $settings->slug . '_abandoned_cart_email_templates&task=create-email-template'); ?>&language_code=<?php echo $active_language ?>"
               class="create-new-template"><?php echo __('Create New Email Template', RNOC_TEXT_DOMAIN) ?></a>
        </div>
        <?php
    }
}

$cmb2_field_email_after = new CMB2_Field_Email_After();