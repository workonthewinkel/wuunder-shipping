<?php
/**
 * Settings section view
 *
 * @package Wuunder\Shipping
 * @var array $settings Settings fields array
 * @var string $api_key Current API key value
 */

defined( 'ABSPATH' ) || exit;

woocommerce_admin_fields( $settings );
?>
<p>
	<button type="button" id="wuunder-test-connection" class="button button-secondary">
		<?php esc_html_e( 'Test Connection', 'wuunder-shipping' ); ?>
	</button>
	<?php if ( ! empty( $api_key ) ) : ?>
		<button type="button" id="wuunder-disconnect" class="button button-secondary wuunder-disconnect-btn">
			<?php esc_html_e( 'Disconnect', 'wuunder-shipping' ); ?>
		</button>
	<?php endif; ?>
	<div>
		<span id="wuunder-test-result"></span>
	</div>
	<div class="wuunder-shipping-settings-notice">
	<?php
	echo wp_kses_post(
		sprintf(
			/* translators: %s: Contact us link */
			__( "Don't have an API key yet? <a href=\"%s\" target=\"_blank\">Contact us</a> to get one.", 'wuunder-shipping' ),
			'https://wearewuunder.com/contact/'
		)
	);
	?>
	</div>
</p>