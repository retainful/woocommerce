<?php
/**
 * @var $settings array
 */
require_once "tabs.php";
$is_app_connected = ($settings[RNOC_PLUGIN_PREFIX . 'is_retainful_connected'] == 1);
$api = new \Rnoc\Retainful\library\RetainfulApi();
$admin_settings = new Rnoc\Retainful\Admin\Settings();
?>
<form id="retainful-license-form" class="card">
    <table class="form-table" role="presentation">
        <tbody>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_id'; ?>"><?php
                    esc_html_e('App ID', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_id'; ?>" type="text"
                       id="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_id'; ?>"
                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_id']); ?>" class="regular-text">
                <p class="error" id="error_app_id" style="color: red;"></p>
                <p class="description">
                    <?php
                    echo sprintf(esc_html__('Get your App-id %s', RNOC_TEXT_DOMAIN), '<a target="_blank" href="' . $this->api->app_url . 'settings">here</a>');
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_secret'; ?>"><?php
                    esc_html_e('App Secret', RNOC_TEXT_DOMAIN);
                    ?></label>
            </th>
            <td>
                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_secret'; ?>" type="password"
                       id="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_secret'; ?>"
                       value="<?php echo rnocEscAttr($settings[RNOC_PLUGIN_PREFIX . 'retainful_app_secret']); ?>"
                       class="regular-text">
                <p class="error" id="error_secret_key" style="color: red;"></p>
                <p class="description">
                    <?php
                    echo sprintf(esc_html__('Get your secret key %s', RNOC_TEXT_DOMAIN), '<a target="_blank" href="' . $this->api->app_url . 'settings">here</a>');
                    ?>
                </p>
            </td>
        </tr>
        <tr>
            <th>
            </th>
            <td>
                <button type="button" data-action="validate_app_key" id="validate-app-id-and-secret"
                        data-security="<?php echo wp_create_nonce('validate_app_key') ?>"
                        class="button button-primary button-green"><?php echo (!$is_app_connected) ? __('Connect', RNOC_TEXT_DOMAIN) : __('Re-Connect', RNOC_TEXT_DOMAIN); ?></button>
                <?php
                if ($is_app_connected) {
                    ?>
                    <button type="button" id="disconnect-app-btn" data-action="rnoc_disconnect_license"
                            data-security="<?php echo wp_create_nonce('rnoc_disconnect_license') ?>"
                            class="button"><?= __('Dis-connect', RNOC_TEXT_DOMAIN) ?></button>
                    <a href="<?php echo $api->app_url ?>" target="_blank" class="button"
                       style="text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 600;border-color:#F27052;"><?php echo __('Visit Your Dashboard', RNOC_TEXT_DOMAIN); ?></a>
                    <br>
                    <?php
                }
                ?>
                <div class="retainful_app_validation_message" style="display:flex;">
                    <p style="color:green;margin-top:10px;"><?php echo ($is_app_connected) ? __('Successfully connected to Retainful', RNOC_TEXT_DOMAIN) : '' ?></p>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <?php
                if ($is_app_connected) {
                    $plan = $admin_settings->getUserActivePlan();
                    ?>
                    <div style="display:block;background: #fff;border: 1px solid #eee;color:#333;padding: 20px;max-width: 600px;width: 100%;text-align:center;border-radius: 4px;box-shadow: 0 0 5px 0 #ddd;margin: 20px auto;">
                        <p style="margin: 20px 0 10px;">
                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAASUAAABFCAYAAAAM5PCZAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAC2VJREFUeNrsXb1u5MgRbi0MO/SsH8DLe4KdBS7fUeRQUriBoZknkCbdZDTJpSM9gUa44CJDo/AicQNnBsSNDAMGlneRndyNAQf2nc9yl7boo7gku/qHFDn8PoCnPanZ7K6u+rr6r1opAAAAAAAAAAAAAAAAAAAAAOgx9iACIAQ+/93vD/WPce5XiX7iP3395RbSAUBKQNtkdKmfUcmfiZDmmpjWkFSp7Ca5/53kZJZomcU7VMeDXIf1Tj8bXb8EpAQ0RUjXgqSzoROTlhWRNsnrNRNQZHgl1c9RnfH2oM4r/eO04s/num7zsj88g2kBHlgFTreTZKSfM/3PD+xRTgWEpDjNqsf1ntYQEuGU04CUgGBKNxYaF2FUGKoMyZMkMlpUDG9N6LPMjl3TgJQAV4wgAuPQ5XrAcpJ0WKWk+wuoj7fyRYUGSLDiNHidyIZpvkh3nJQUPKXwcwWX7J7f5p4PPIew0+DVIRvyTQaiF9NAhESYD9G2QEruuK1QPnLXF0MgJo0LYbrzIXiPvMLmOzlN3tFGP/taZpshGhaGb27KV9woWIYT/ew0MWmjOdOyeGHwDMijWg5ENVbKbg5prZ8bHvKnsCyQkg/GgjQPK067sgmuhphmup5XTMJkkBMeqpGR3QxlfxJ7SdJhG8nnCEQEUgKaI6aYPaIh49BieLaPxZBqYE4JAMLgQJhuCUICKQFAV4b0W5wDBCkBQFuIBGkSiAmkBACNw+IIDUgJpAQAncI/IAIzRKtvfJSCVheyPRiJdGMXvzsuGXOvfZZEOd8Ju80vCu5zwgowiEBj3FNH/Py6RNZ5eTSyJ4YP6OZXoBLXzX8+ebEsJiVyoDp/06QM2sD92zdFW6R6bPa++Grbp2/UYU/QyFUxUahxS5c2ec8GvXNsGGtbxdlhIjphgUUW9aSyXlh+K9t3clAxfyCdQ9gKypVaGm0+Ls/Yod0flMzl2xVyulblhysTbuPEIq9LVb68Xqdv1BaLgiE1oROZPhS/MRK2Q6rqz7NR3Sr3dmmyqLJFem+mSWMTgJDOWJZl35jrb6yFsrqXpNN13bMiJT4qsahJQl7Ifsk7JxbK8dzkyeSUbuopc1KIpUkR2ThuHQ3eBUcmT4C9hxNLw5OAZDF39SZ1uW5VfYgN8kpeCfMyHWR9pG+5Yx0+eiEizoCHbEVtQptSC2RxquqPsFD7vdKkkXoQ0pQ7hTp8JvmGDymZ5pRMMVEmbCzZAdU7ZR87ZmyoHDXGXSCFIHK71Hles0JXYdoiIak6ZSNCZsPPZBA6FAbleZe1o+MQurZ92bOTeqZSfRsH0gvK57au/vy3aYv6MC0pz4nhnZEgjQmS9xdNV/6ZwIhNGDXlWXDvtGrAEA9ZEavyfd3yVEFUM1y4U80H+4pMhumhH0qoF9Jvj7ict8ojPEaJQd8yyVb9vW2MHGTta3/jgG3eGClJcdkQITXZO40NxPSkYANZtWgQI/Yiux6ULOsAR03UXwFPjhBn3w6U/NxP1Zj+qcbvY1bEo8Lv33nWyVsGDQ3VJPKg4fJZh3X2skG5TCoOUadPUE/7b/5qb3T/l5m7V331r040cAhP6dTj3VlxgtUhSBYZ9JKJZZ9/LpV8o9phMYC5LtO5am+j28PKSQMKHTsa00nHvaWmy/bJnAmvUJ63WMdzp1XR53vZsNb12RlPyRYxG/xVccXDMkgWvTuvCA1CK1lnPPcgGVqudNpNniBpxYjJ6mXJ+5Hy3xJASvdefbwDy6cn3nJ9ybuLy/LiieZjofeXTTifq/4gk8FNQd4j9uRtOjnylqKiHOk6IP17yp88kdclMguxJSBrwxjDt+aGJFf8MxUannQOZa0Ey9hMekQu1waDHJUNW6q2Dgi2SmSYeyhYbPjGhonduDeF02yYZCXzJgc9IqWlqo9sSfW+UHbzUBPWsaIcY1USooU3bEo8DWqvMwW0TkoxD8usen+e2JX0aImy31cz456szrs57tJcChmAlsl5YXicMtE7hZclktV5Hivzat6kJ/or2nxLnZOuNw3t74T5vgQ19HtO6VGvRZvbHIcjUhfbeqMfpzeFZI1c9uo0TEwUOJ42HpJBkVw/o57W89iMKK52D+5ps7oOnL1mafoxqGE3SGnt6ZpKLq9zHm+zApuM+bBrDUTGRHUONc9gcR4t6rDexrwYYYsrkNJwSOnhXIzryxa3rV75KrPh768H0u6SlcUuk9KFy0sWxI6LNneAlC48hxTSoYLvgcP36CH/34n0tuyeVw+lMPthkNLa833JxGIaIASJqadED7kbXh5IqccIsfq2DRCbRjRUCHDB4wvJUFIaaqNrKExO1+2diXqss4jeCFJqRUkiYZpFCzLpvLeUC4L2Uv1819pQgOiNIKVWEKEpjESUkXLoeEoAAFLqONIuFSZQIDMAACn1lZC6FLs5FzcInhEAUhogaJl52ZXC5ALnhSCk4sHgMYgOACmZjca0R+iTeOA7DpsAb9kp+fdKcABaEFcbAAZPSpL9R4MxIovDyQQ6boH76QE//NCdonTlMsrUwliHACkBU7QAl5tIMHQDHuO7/4KUCngvTHc4EBWRhs5wnQPDgVPgMf4OUioiFqY7HoiKiKIYugzZJNcdAT3F3zyI5a8/gZTy4GMdkiHcuAdxfrrukRzAenuJVJTqu3v7nL/VZPbP+85U9FmHhC4NS7IKFdiewsPSeTp+IotXpUdrmoxgGNnK4QkuVQTaJqU//8cuV5rg/uOPnapol0hpbeF9ON/Xxjf5Egl9rz7Gq17wY3NLbCpMN3WcnJfmf2pTb4V7zfqMd+JhmNRbIkL6Wv/nh/tOVbQzpGR5jU1GTBMLo6ShH+39+aDKrxYfKeGBXx5uSudzrh2ISTrxvxBeiU3fD36DMdAq5AffiWhM80tEXn/4d6dW3TJ0bUf3UskvYcyIiRqLrr6J1ccwKgkbYkZY9PNAhb06mrARDoXG7IWtuZxbQWgUylt61RSRXumNHuwdkTd1orANoNfY++Krzf3bN1tRO5LnQ8T0G+1z/FY/z/XzS/Vx3uh7/Xz7U6fmkDpNSmRU2pCOlN3FeGN+FmyIPkWwMdylks/PZORwWihjymQ6L9w5l+o0sZLvV1qw1xRznpGS30UG9AcbZTMnSF5QBz2h3gzfcgZJhjV7os/HlsPNtef3IlayMhJ2iXk+4fwmIKSdRNNnMztxKuBZFyXPN4/MetDocxUm1MknWx14iNeEEsY25At0agiXNkhMbV5V3z9SKhBTG+xN3ziyDYPLQ66jpsrIV1atA9dzBvPuNTGdNdCpxDrfeeA8E6E+WpOSJOObholpv+Genb7xyvWGjOxq8KZ6GZ3/LFDvSAqQXRQqWV5OAykeQXK1eBxQ2X311bZzioUdUygdOQpoE2vOL3SbS9LFLqS0FCj6ukHCyC5j3OcePlSjpuyu0o2zM9/AbvS+fl5xGV3ySuqMkj2mfY/6x0y8Sc5V3xrKsxbUe6vM2zhiCw90KZDTJoARbj3LUYaLFsqeeUtb/exzOV299Ie7GnU+M8pPWIethWwuDGWrzGvPlDPtelblsX1I0edt3/zBGxzpDBzNwdhM5iZc5ptQt80KyjhW5hU0UtSZ9Bwb70uivCVn2Cjvi7L6chlXJeWLeSi7tagv5XNaMU+xDJSXdbkM7XNZoj9bZXkdeCFfKnfZHri1crhuXoL7t2/oW9OcvknsgE5PrHNklM/vkGVTau/6ncRSztfq0xj8ad10yZ7FB/LKu+3KNURcrqrlb6p82jQJOcgv33tuPfOMSho9lubNipMpoHM4YN4TlW+DTuRlUXcVSk8KbZ20FeuKCSrbIjMqIaOEJ8sleT2ydxsyMsh529crzAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA8ML/BBgAeafBh1J4V2MAAAAASUVORK5CYII="
                                 style="max-width: 150px;height: auto;"
                                 alt=""></p>
                        <div class="list-app-msg">
                            <div class="dashboard-msg">
                                <p style="margin: 15px 0;color:#777;font-size: 17px;line-height:1.6;">
                                    <?php echo __('Manage abandoned carts, emails & next order coupons', RNOC_TEXT_DOMAIN); ?>
                                </p>
                                <a href="<?php echo $api->app_url ?>" target="_blank"
                                   style="display: inline-block;font-size: 16px;padding: 10px 20px;text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 600;line-height:1.8;margin-bottom: 20px;"><?php echo __('Visit Your Dashboard', RNOC_TEXT_DOMAIN); ?></a>
                            </div>
                            <?php
                            if (!$admin_settings->isProPlan()) {
                                ?>
                                <div class="premium-msg">
                                    <p style="margin: 15px 0;color:#777;font-size: 17px;line-height:1.6;">
                                        <?php echo __('Upgrade to Premium and get more features like Email Collection during add to cart and Coupon for email entry.', RNOC_TEXT_DOMAIN); ?>
                                    </p>
                                    <p style="margin: 20px 0 0;">
                                        <a href="<?php echo $api->upgradePremiumUrl(); ?>" target="_blank"
                                           style="display: inline-block;font-size: 16px;padding: 10px 20px;text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 600;line-height:1.8;margin-bottom: 20px;"><?php echo __('Upgrade to Premium', RNOC_TEXT_DOMAIN); ?></a>
                                    </p>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    <?php
                } else {
                    ?>
                    <div style="display:block;background: #fff;border: 1px solid #eee;color:#333;padding: 20px;max-width: 100%;text-align:center;border-radius: 4px;box-shadow: 0 0 5px 0 #ddd;margin: auto;">
                        <p style="margin: 0 0 20px;">
                            <img src="data:image/png;base64,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"
                                 style="max-width: 150px;height: auto;"
                                 alt=""></p>
                        <h3 style="flex: 1;margin: 0;font-weight: 600;font-size:25px;color: #333;line-height:1.3;"><?php echo __('Recover abandoned carts and drive repeat purchases.', RNOC_TEXT_DOMAIN); ?></h3>
                        <p style="margin: 15px 0;color:#777;font-size: 17px;line-height:1.6;">
                            <?php echo __('Stop cart abandonment and recover the lost revenue with Retainful. Increase sales by 10x. Capture emails and automatically send a series of recovery emails.', RNOC_TEXT_DOMAIN); ?>
                        </p>
                        <p style="margin: 15px 0;color:#777;font-size: 17px;line-height:1.6;">
                            <?php echo __('Get unlimited emails, drag and drop email editor, exit popups and more', RNOC_TEXT_DOMAIN); ?>
                        </p>
                        <p style="margin: 20px 0 0;">
                            <a href="https://app.retainful.com" target="_blank"
                               style="display: inline-block;font-size: 16px;padding: 10px 20px;text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 500;line-height:1.6;"><?php echo __('Get started for FREE', RNOC_TEXT_DOMAIN); ?></a>
                        </p>
                    </div>
                    <?php
                }
                ?>
            </td>
        </tr>
        </tbody>
    </table>
</form>