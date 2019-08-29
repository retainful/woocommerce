<?php
if (!defined('ABSPATH')) exit;

class CMB2_Field_Abandoned_Cart_Sent_Emails
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
        add_filter('cmb2_render_abandoned_cart_sent_emails', array($this, 'render_abandoned_cart_sent_emails'), 10, 5);
    }

    /**
     * sent emails list
     */
    public function render_abandoned_cart_sent_emails($field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object)
    {
        $abandoned_cart_obj = new \Rnoc\Retainful\AbandonedCart();
        $settings = new \Rnoc\Retainful\Admin\Settings();
        $limit = 20;
        $page_number = (isset($_GET['page_number'])) ? ($_GET['page_number']) : 1;
        $order_by = (isset($_GET['order_by'])) ? ($_GET['order_by']) : 'sent_time';
        $order_by_value = (isset($_GET['order_by_value'])) ? ($_GET['order_by_value']) : 'desc';
        $start = ($page_number - 1) * $limit;
        $sent_emails = $abandoned_cart_obj->sentEmailsHistory($start, $limit, $order_by, $order_by_value);
        $url_arr = array(
            'page' => $settings->slug . '_abandoned_cart_sent_emails',
            'order_by' => $order_by,
            'order_by_value' => $order_by_value
        );
        $url = admin_url('admin.php?' . http_build_query($url_arr));
        $pagConfig = array(
            'baseURL' => $url,
            'totalRows' => $abandoned_cart_obj->getTotalEmailsSent(),
            'perPage' => $limit
        );
        $pagination = new \Rnoc\Retainful\Library\Pagination($pagConfig);
        ?>
        <table class="retainful_abandoned_table">
            <thead class="bg-light">
            <tr class="border-0">
                <th class="border-0"><?php echo __('Abandoned cart ID', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0"><?php echo __('Date', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0"><?php echo __('Email', RNOC_TEXT_DOMAIN); ?></th>
                <th class="border-0"><?php echo __('Subject', RNOC_TEXT_DOMAIN); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            if (!empty($sent_emails)) {
                foreach ($sent_emails as $email) {
                    ?>
                    <tr>
                        <td>
                            <?php echo $email->abandoned_order_id; ?>
                        </td>
                        <td>
                            <?php echo date('Y-m-d H:i A', strtotime($email->sent_time)); ?>
                        </td>
                        <td class="email-section">
                            <a href="mailto:<?php echo $email->sent_email_id ?>"><?php echo $email->sent_email_id ?></a>
                        </td>
                        <td class="email-section">
                            <?php echo $email->subject ?>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="8">
                        <p style="text-align: center;color: red"><?php echo __('No E-Mails sent!', RNOC_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
        <?php
        if (!empty($sent_emails)) {
            ?>
            <div class="table_data_dp">
                <?php
                echo $pagination->createLinks();
                ?>
            </div>
            <?php
        }
        ?>
        <style>
            #submit-cmb {
                display: none;
            }
        </style>
        <?php
    }
}

$cmb2_field_abandon_cart_lists = new CMB2_Field_Abandoned_Cart_Sent_Emails();
