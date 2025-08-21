<?php
/**
 * Carriers section view
 *
 * @package Wuunder\Shipping
 * @var array $carriers    Array of Carrier objects
 * @var bool  $has_api_key Whether API key is configured
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wuunder-carriers-section" id="wuunder-carriers-section">
	<?php if ( empty( $carrier_methods ) ) : ?>
		<?php if ( $has_api_key ) : ?>
			<p><?php esc_html_e( 'No carriers found. Click "Refresh Carriers" to retrieve available shipping methods.', 'wuunder-shipping' ); ?></p>
		<?php else : ?>
			<p>
				<?php
				printf(
					/* translators: %s: Link to Settings tab */
					esc_html__( 'Please configure your API key in the %s first.', 'wuunder-shipping' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=wc-settings&tab=wuunder&section=settings' ) ),
						esc_html__( 'Settings tab', 'wuunder-shipping' )
					)
				);
				?>
			</p>
		<?php endif; ?>
	<?php else : ?>
		<h2><?php esc_html_e( 'Available Carriers', 'wuunder-shipping' ); ?></h2>
		
		<?php if ( ! empty( $carrier_methods ) ) : ?>
			<p class="wuunder-shipping-settings-notice">
				<?php
				/* translators: %s: Link to WooCommerce Shipping Settings page */
				printf(
					/* translators: %s: Link to WooCommerce Shipping Settings page */
					esc_html__( 'Enable carriers below, then configure shipping methods in %s to display them at checkout.', 'wuunder-shipping' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ),
						esc_html__( 'WooCommerce Shipping Settings', 'wuunder-shipping' )
					)
				);
				?>
			</p>
		<?php endif; ?>
		<div class="wuunder-carriers-section__actions">
			<div class="wuunder-carriers-section__actions-filter">
				<label for="wuunder_carrier_filter"><?php esc_html_e( 'Filter by: ', 'wuunder-shipping' ); ?></label>
				<select name="wuunder_carrier_filter" id="wuunder_carrier_filter">
					<option value=""><?php esc_html_e( 'Select a carrier...', 'wuunder-shipping' ); ?></option>
					<?php foreach ( $carrier_names as $carrier_code => $carrier_name ) : ?>
						<option value="<?php echo esc_attr( $carrier_code ); ?>"><?php echo esc_html( $carrier_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php if ( $has_api_key ) : ?>
				<button type="button" id="wuunder-refresh-carriers" class="button button-secondary">
					<?php esc_html_e( 'Refresh Carriers', 'wuunder-shipping' ); ?>
					<span class="spinner"></span>
				</button>
			<?php endif; ?>
		</div>
		<table class="widefat wuunder-carriers-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Enabled', 'wuunder-shipping' ); ?></th>
					<th><?php esc_html_e( 'Carrier', 'wuunder-shipping' ); ?></th>
					<th><?php esc_html_e( 'Service', 'wuunder-shipping' ); ?></th>
					<th><?php esc_html_e( 'Method ID', 'wuunder-shipping' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $carrier_methods as $carrier ) : ?>
					<tr data-carrier-code="<?php echo esc_attr( $carrier->carrier_code ); ?>">
						<td class="wuunder-carriers-table__checkbox-container">
							<div class="wuunder-carriers-table__checkbox-container--inner">

								<div class="wuunder-toggle">
									<input type="checkbox" 
									name="wuunder_enabled_carriers[<?php echo esc_attr( $carrier->get_method_id() ); ?>]" 
									value="1" 
									<?php checked( $carrier->enabled ); ?> />
									<label for="wuunder_enabled_carriers[<?php echo esc_attr( $carrier->get_method_id() ); ?>]">
										<span></span>
									</label>
								</div>
								<?php if ( ! empty( $carrier->carrier_image_url ) ) : ?>
									<img src="<?php echo esc_url( $carrier->carrier_image_url ); ?>" 
										alt="<?php echo esc_attr( $carrier->carrier_name ); ?>" 
										class="wuunder-carrier-logo" />
								<?php endif; ?>
							</div>
						</td>
						<td>
							<?php echo esc_html( $carrier->carrier_name ); ?>
						</td>
						<td>
							<div class="wuunder-service-name"><?php echo esc_html( $carrier->product_name ); ?></div>
							<?php if ( ! empty( $carrier->carrier_product_description ) ) : ?>
								<div class="wuunder-service-description"><?php echo esc_html( $carrier->carrier_product_description ); ?></div>
							<?php endif; ?>
						</td>
						<td>
							<code><?php echo esc_html( $carrier->get_method_id() ); ?></code>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>