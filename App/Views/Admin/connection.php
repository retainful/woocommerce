<?php
$settings         = ! empty( $settings ) ? $settings : [];
$app_url          = ! empty( $app_url ) ? $app_url : '';
$is_app_connected = ( $settings[ RNOC_PLUGIN_PREFIX . 'is_retainful_connected' ] == 1 );
?>
<form id="retainful-license-form" class="card">
    <table class="form-table" role="presentation">
        <tbody>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_id'; ?>"><?php
					esc_html_e( 'App ID', 'retainful-next-order-coupon-for-woocommerce' );
					?></label>
            </th>
            <td>
                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_id'; ?>" type="text"
                       id="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_id'; ?>"
                       value="<?php echo stripslashes( esc_attr__( $settings[ RNOC_PLUGIN_PREFIX . 'retainful_app_id' ] ) ); ?>"
                       class="regular-text">
                <p class="error" id="error_app_id" style="color: red;"></p>
                <p class="description">
					<?php
					echo sprintf( esc_html__( 'Get your App-id %s', 'retainful-next-order-coupon-for-woocommerce' ), '<a target="_blank" href="' . $app_url . 'app/settings/general">here</a>' );
					?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_secret'; ?>"><?php
					esc_html_e( 'App Secret', 'retainful-next-order-coupon-for-woocommerce' );
					?></label>
            </th>
            <td>
                <input name="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_secret'; ?>" type="password"
                       id="<?php echo RNOC_PLUGIN_PREFIX . 'retainful_app_secret'; ?>"
                       value="<?php echo stripslashes( esc_attr__( $settings[ RNOC_PLUGIN_PREFIX . 'retainful_app_secret' ] ) ); ?>"
                       class="regular-text">
                <p class="error" id="error_secret_key" style="color: red;"></p>
                <p class="description">
					<?php
					echo sprintf( esc_html__( 'Get your secret key %s', 'retainful-next-order-coupon-for-woocommerce' ), '<a target="_blank" href="' . $app_url . 'app/settings/general">here</a>' );
					?>
                </p>
            </td>
        </tr>
        <tr>
            <th>
            </th>
            <td>
                <button type="button" data-action="validate_app_key" id="validate-app-id-and-secret"
                        data-security="<?php echo wp_create_nonce( 'validate_app_key' ) ?>"
                        class="button button-primary button-green"><?php echo ( ! $is_app_connected ) ? __( 'Connect', 'retainful-next-order-coupon-for-woocommerce' ) : __( 'Re-Connect', 'retainful-next-order-coupon-for-woocommerce' ); ?></button>
				<?php
				if ( $is_app_connected ) {
					?>
                    <button type="button" id="disconnect-app-btn" data-action="rnoc_disconnect_license"
                            data-security="<?php echo wp_create_nonce( 'rnoc_disconnect_license' ) ?>"
                            class="button"><?= __( 'Dis-connect', 'retainful-next-order-coupon-for-woocommerce' ) ?></button>
                    <a href="<?php echo $app_url ?>" target="_blank" class="button"
                       style="text-decoration: none;color:#fff;background:#F27052;border-radius: 4px;font-weight: 600;border-color:#F27052;"><?php echo __( 'Visit Your Dashboard', 'retainful-next-order-coupon-for-woocommerce' ); ?></a>
                    <br>
					<?php
				}
				?>
                <div class="retainful_app_validation_message" style="display:flex;">
                    <p style="color:green;margin-top:10px;"><?php echo ( $is_app_connected ) ? __( 'Successfully connected to Retainful', 'retainful-next-order-coupon-for-woocommerce' ) : '' ?></p>
                </div>
            </td>
        </tr>
        </tbody>
    </table>
</form>