<?php

namespace Rnoc\Retainful\Admin;

class Survey
{
    public $plugin, $plugin_text_domain, $name;
    const END_POINT = "";

    /**
     * init the survey
     * @param $plugin
     * @param $text_domain
     * @param $plugin_name
     * @return null
     */
    function init($plugin, $plugin_name, $text_domain)
    {
        $this->plugin = $plugin;
        $this->name = $plugin_name;
        $this->plugin_text_domain = $text_domain;
        if ($this->isPluginPage() || !$this->isDevelopmentSite()) {
            add_action('admin_print_scripts', array($this, 'js'), 20);
            add_action('admin_print_scripts', array($this, 'css'));
            add_action('admin_footer', array($this, 'modal'));
        }
        return NULL;
    }

    /**
     * Print the required js
     */
    function js()
    {
        ?>
        <script type="text/javascript">
            jQuery(function ($) {
                var $deactivateLink = $('#the-list').find('[data-slug="<?php echo $this->plugin; ?>"] span.deactivate a'),
                    $overlay = $('#plugin-deactivate-survey-<?php echo $this->plugin; ?>'),
                    $form = $overlay.find('form'),
                    formOpen = false;
                // Plugin listing table deactivate link.
                $deactivateLink.on('click', function (event) {
                    event.preventDefault();
                    $overlay.css('display', 'table');
                    formOpen = true;
                    $form.find('.<?php echo $this->plugin; ?>-deactivate-survey-option:first-of-type input[type=radio]').focus();
                });
                // Survey radio option selected.
                $form.on('change', 'input[type=radio]', function (event) {
                    event.preventDefault();
                    $form.find('input[type=text], .error').hide();
                    $form.find('.<?php echo $this->plugin; ?>-deactivate-survey-option').removeClass('selected');
                    $(this).closest('.<?php echo $this->plugin; ?>-deactivate-survey-option').addClass('selected').find('input[type=text]').show();
                });
                // Survey Skip & Deactivate.
                $form.on('click', '.<?php echo $this->plugin; ?>-deactivate-survey-deactivate', function (event) {
                    event.preventDefault();
                    location.href = $deactivateLink.attr('href');
                });
                // close button
                $form.on('click', '.<?php echo $this->plugin; ?>-deactivate-survey-close', function (event) {
                    event.preventDefault();
                    $overlay.css('display', 'none');
                    formOpen = false;
                });
                // Survey submit.
                $form.submit(function (event) {
                    event.preventDefault();
                    if (!$form.find('input[type=radio]:checked').val()) {
                        $form.find('.<?php echo $this->plugin; ?>-deactivate-survey-footer').prepend('<span class="error"><?php echo esc_js(__('Please select an option', $this->plugin_text_domain)); ?></span>');
                        return;
                    }
                    $form.find('.<?php echo $this->plugin; ?>-deactivate-survey-submit').html('<?php echo esc_js(__('Sending Feedback', $this->plugin_text_domain)); ?>').attr("disabled", true).removeClass('button-primary');
                    var submitSurvey = $.ajax(
                        {
                            url: "<?php echo self::END_POINT; ?>",
                            type: "POST",
                            data: {
                                id: '<?php echo mailchimp_get_store_id()?>',
                                url: '<?php echo esc_url(home_url()); ?>',
                                data: {
                                    code: $form.find('.selected input[type=radio]').val(),
                                    reason: $form.find('.selected .<?php echo $this->plugin; ?>-deactivate-survey-option-reason').val(),
                                    details: $form.find('.selected input[type=text]').val(),
                                    plugin: '<?php echo sanitize_key($this->name); ?>'
                                }
                            },
                            dataType: 'json',
                            async: false,
                            success: function (msg) {
                                location.href = $deactivateLink.attr('href');
                            }
                        }
                    )
                });

                // Exit key closes survey when open.
                $(document).keyup(function (event) {
                    if (27 === event.keyCode && formOpen) {
                        $overlay.hide();
                        formOpen = false;
                        $deactivateLink.focus();
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * CSS required for survey form
     */
    function css()
    {
        ?>
        <style type="text/css">
            .<?php echo $this->plugin; ?>-deactivate-survey-modal {
                display: none;
                table-layout: fixed;
                position: fixed;
                z-index: 9999;
                width: 100%;
                height: 100%;
                text-align: center;
                font-size: 14px;
                top: 0;
                left: 0;
                background: rgba(0, 0, 0, 0.8);
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-wrap {
                display: table-cell;
                vertical-align: middle;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey {
                background-color: #fff;
                max-width: 550px;
                margin: 0 auto;
                padding: 30px;
                text-align: left;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey .error {
                display: block;
                color: red;
                margin: 0 0 10px 0;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-header {
                display: block;
                font-size: 18px;
                font-weight: 700;
                text-transform: uppercase;
                border-bottom: 1px solid #ddd;
                padding: 0 0 18px 0;
                margin: 0 0 18px 0;
                position: relative;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-title {
                text-align: left;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-close {
                text-align: right;
                position: absolute;
                right: 0px;
                font-size: 24px;
                cursor: pointer;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-title span {
                color: #999;
                margin-right: 10px;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-desc {
                display: block;
                font-weight: 600;
                margin: 0 0 18px 0;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-option {
                margin: 0 0 10px 0;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-option-input {
                margin-right: 10px !important;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-option-details {
                display: none;
                width: 90%;
                margin: 10px 0 0 30px;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-footer {
                margin-top: 18px;
            }

            .<?php echo $this->plugin; ?>-deactivate-survey-deactivate {
                float: right;
                font-size: 13px;
                color: #ccc;
                text-decoration: none;
                padding-top: 7px;
            }
        </style>
        <?php
    }

    /**
     * Modal window showing survey
     */
    function modal()
    {
        $options = array(
            1 => array(
                'title' => esc_html__('I want to change the audience associated with this integration.', $this->plugin_text_domain),
                'reason' => 'I want to change the audience associated with this integration.'
            ),
            2 => array(
                'title' => esc_html__('I want to change the site or store connected through this integration.', $this->plugin_text_domain),
                'reason' => 'I want to change the site or store connected through this integration.'
            ),
            3 => array(
                'title' => esc_html__('The order data isn\'t syncing.', $this->plugin_text_domain),
                'reason' => 'The order data isn\'t syncing.'
            ),
            4 => array(
                'title' => esc_html__('The promo codes aren\'t showing up.', $this->plugin_text_domain),
                'reason' => 'The promo codes aren\'t showing up.'
            ),
            5 => array(
                'title' => esc_html__('I\'m trying to troubleshoot the integration.', $this->plugin_text_domain),
                'reason' => 'I\'m trying to troubleshoot the integration.'
            ),
            6 => array(
                'title' => esc_html__('I was instructed to disconnect by Mailchimp Support.', $this->plugin_text_domain),
                'reason' => 'I was instructed to disconnect by Mailchimp Support.'
            ),
            7 => array(
                'title' => esc_html__('I no longer use this integration.', $this->plugin_text_domain),
                'reason' => 'I no longer use this integration.'
            ),
            8 => array(
                'title' => esc_html__('It\'s a temporary deactivation.', $this->plugin_text_domain),
                'reason' => 'It\'s a temporary deactivation.'
            ),
            9 => array(
                'title' => esc_html__('Other', $this->plugin_text_domain),
                'reason' => 'Other',
                'details' => esc_html__('Please share the reason', $this->plugin_text_domain),
            ),
        );
        ?>
        <div class="<?php echo $this->plugin; ?>-deactivate-survey-modal"
             id="plugin-deactivate-survey-<?php echo $this->plugin; ?>">
            <div class="<?php echo $this->plugin; ?>-deactivate-survey-wrap">
                <form class="<?php echo $this->plugin; ?>-deactivate-survey" method="post">
						<span class="<?php echo $this->plugin; ?>-deactivate-survey-header">
							<span class="dashicons dashicons-testimonial"></span>
							<?php echo ' ' . esc_html__('Quick Feedback', 'mailchimp-woocommerce'); ?>
							<span title="<?php esc_attr_e('Close', 'mailchimp-woocommerce'); ?> "
                                  class="<?php echo $this->plugin; ?>-deactivate-survey-close">✕</span>
						</span>

                    <span class="<?php echo $this->plugin; ?>-deactivate-survey-desc">
							<?php
                            printf(
                            /* translators: %s - plugin name. */
                                esc_html__('If you have a moment, please share why you are deactivating %s:', 'mailchimp-woocommerce'),
                                esc_html__('Mailchimp for Woocommerce', 'mc-woocommerce')
                            );
                            ?>
						</span>
                    <div class="<?php echo $this->plugin; ?>-deactivate-survey-options">
                        <?php foreach ($options as $id => $option) : ?>
                            <div class="<?php echo $this->plugin; ?>-deactivate-survey-option">
                                <label for="<?php echo $this->plugin; ?>-deactivate-survey-option-<?php echo $this->plugin; ?>-<?php echo $id; ?>"
                                       class="<?php echo $this->plugin; ?>-deactivate-survey-option-label">
                                    <input id="<?php echo $this->plugin; ?>-deactivate-survey-option-<?php echo $this->plugin; ?>-<?php echo $id; ?>"
                                           class="<?php echo $this->plugin; ?>-deactivate-survey-option-input"
                                           type="radio"
                                           name="code" value="<?php echo $id; ?>"/>
                                    <span class="<?php echo $this->plugin; ?>-deactivate-survey-option-title"><?php echo $option['title']; ?></span>
                                    <input class="<?php echo $this->plugin; ?>-deactivate-survey-option-reason"
                                           type="hidden"
                                           value="<?php echo $option['reason']; ?>"/>
                                </label>
                                <?php if (!empty($option['details'])) : ?>
                                    <input class="<?php echo $this->plugin; ?>-deactivate-survey-option-details"
                                           type="text"
                                           placeholder="<?php echo $option['details']; ?>"/>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="<?php echo $this->plugin; ?>-deactivate-survey-footer">
                        <button type="submit"
                                class="<?php echo $this->plugin; ?>-deactivate-survey-submit button button-primary button-large"><?php echo esc_html__('Submit & Deactivate', 'mailchimp-woocommerce'); ?></button>
                        <a href="#"
                           class="<?php echo $this->plugin; ?>-deactivate-survey-deactivate"><?php echo esc_html__('Skip & Deactivate', 'mailchimp-woocommerce'); ?></a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Check this page is plugin page or not
     * @return bool
     */
    function isPluginPage()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : false;
        if (empty($screen)) {
            return false;
        }
        return (!empty($screen->id) && in_array($screen->id, array('plugins', 'plugins-network'), true));
    }

    /**
     * Checks if current site is a development one.
     * @return bool
     */
    public function isDevelopmentSite()
    {
        // If it is an AM dev site, return false, so we can see them on our dev sites.
        if (defined('AWESOMEMOTIVE_DEV_MODE') && AWESOMEMOTIVE_DEV_MODE) {
            return false;
        }
        $url = network_site_url('/');
        $is_local_url = false;
        // Trim it up
        $url = strtolower(trim($url));
        // Need to get the host...so let's add the scheme so we can use parse_url
        if (false === strpos($url, 'http://') && false === strpos($url, 'https://')) {
            $url = 'http://' . $url;
        }
        $url_parts = parse_url($url);
        $host = !empty($url_parts['host']) ? $url_parts['host'] : false;
        if (!empty($url) && !empty($host)) {
            if (false !== ip2long($host)) {
                if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $is_local_url = true;
                }
            } else if ('localhost' === $host) {
                $is_local_url = true;
            }
            $tlds_to_check = array('.dev', '.local', ':8888');
            foreach ($tlds_to_check as $tld) {
                if (false !== strpos($host, $tld)) {
                    $is_local_url = true;
                    continue;
                }
            }
            if (substr_count($host, '.') > 1) {
                $subdomains_to_check = array('dev.', '*.staging.', 'beta.', 'test.');
                foreach ($subdomains_to_check as $subdomain) {
                    $subdomain = str_replace('.', '(.)', $subdomain);
                    $subdomain = str_replace(array('*', '(.)'), '(.*)', $subdomain);
                    if (preg_match('/^(' . $subdomain . ')/', $host)) {
                        $is_local_url = true;
                        continue;
                    }
                }
            }
        }
        return $is_local_url;
    }
}