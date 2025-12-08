<?php
/**
 * Carriers section view
 *
 * @package Wuunder\Shipping
 * @var array  $carrier_methods Array of Carrier objects
 * @var array  $carrier_names   Array of carrier names keyed by carrier code
 * @var bool   $has_api_key     Whether API key is configured
 * @var string $carrier_type    Type of carriers: 'standard' or 'parcelshop'
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wuunder-carriers-section" id="wuunder-carriers-section">
	<input type="hidden" name="wuunder_carrier_type" value="<?php echo esc_attr( $carrier_type ); ?>" />
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
		<div class="wuunder-carriers-section__container">
		<div class="wuunder-carriers-section__controls">
			<div class="wuunder-carriers-section__controls-inner">
				<input type="text"
					id="wuunder_carrier_search"
					placeholder="<?php esc_attr_e( 'Search...', 'wuunder-shipping' ); ?>"
					class="wuunder-search-input" />

				<?php $carrier_filter = isset( $_GET['carrier_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['carrier_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<select name="wuunder_carrier_filter" id="wuunder_carrier_filter" class="wuunder-filter-select">
					<option value=""><?php esc_html_e( 'All carriers', 'wuunder-shipping' ); ?></option>
					<option value="enabled" <?php selected( $carrier_filter, 'enabled' ); ?>><?php esc_html_e( 'Enabled only', 'wuunder-shipping' ); ?></option>
					<?php foreach ( $carrier_names as $carrier_code => $carrier_name ) : ?>
						<?php if ( 'any' === $carrier_code ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<option value="<?php echo esc_attr( $carrier_code ); ?>" <?php selected( $carrier_filter, $carrier_code ); ?>><?php echo esc_html( $carrier_name ); ?></option>
					<?php endforeach; ?>
				</select>

				<?php if ( $has_api_key ) : ?>
					<button type="button" id="wuunder-refresh-carriers" class="button button-secondary">
						<?php esc_html_e( 'Refresh', 'wuunder-shipping' ); ?>
						<span class="spinner"></span>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<div class="wuunder-carriers-table-wrapper">
		<table class="widefat wuunder-carriers-table">
			<thead>
				<tr>
					<th class="wuunder-carriers-table__checkbox-header">
						<input type="checkbox" id="wuunder-select-all-carriers" />
					</th>
					<th><?php esc_html_e( 'Service', 'wuunder-shipping' ); ?></th>
					<th><?php esc_html_e( 'Carrier', 'wuunder-shipping' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $carrier_methods as $carrier ) : ?>
					<?php
					// Build data tags for filtering
					$data_tags = [];

					// Add type tags based on accepts_parcelshop_delivery
					if ( $carrier->accepts_parcelshop_delivery ) {
						$data_tags[] = 'pickup';
						$data_tags[] = 'parcelshop';
					} else {
						$data_tags[] = 'delivery';
					}

					// Add additional tags
					if ( $carrier->includes_ad_hoc_pickup ) {
						$data_tags[] = 'adhoc-pickup';
					}

					$data_tags_string = implode( ' ', $data_tags );

					// Determine if checkbox should be checked
					$is_checked = $carrier->enabled;
					?>
					<?php
					$row_hidden = false;
					if ( $carrier_filter === 'enabled' && ! $is_checked ) {
						$row_hidden = true;
					} elseif ( $carrier_filter && $carrier_filter !== 'enabled' && $carrier->carrier_code !== $carrier_filter ) {
						$row_hidden = true;
					}
					?>
					<tr data-carrier-code="<?php echo esc_attr( $carrier->carrier_code ); ?>"
						data-tags="<?php echo esc_attr( $data_tags_string ); ?>"
						data-carrier-name="<?php echo esc_attr( strtolower( $carrier->carrier_name ) ); ?>"
						data-product-name="<?php echo esc_attr( strtolower( $carrier->product_name ) ); ?>"
						data-description="<?php echo esc_attr( strtolower( $carrier->carrier_product_description ) ); ?>"
						<?php
						if ( $row_hidden ) :
							?>
							style="display: none;"<?php endif; ?>>
						<td class="wuunder-carriers-table__checkbox-container">
							<div class="wuunder-toggle">
								<input type="checkbox"
								class="wuunder-carrier-checkbox"
								name="wuunder_enabled_carriers[<?php echo esc_attr( $carrier->get_method_id() ); ?>]"
								value="1"
								<?php checked( $is_checked ); ?> />
								<label for="wuunder_enabled_carriers[<?php echo esc_attr( $carrier->get_method_id() ); ?>]">
									<span></span>
								</label>
							</div>
						</td>
						<td>
							<div class="wuunder-service-name"><?php echo esc_html( $carrier->product_name ); ?></div>
							<?php if ( ! empty( $carrier->carrier_product_description ) && $carrier->carrier_product_description !== $carrier->product_name ) : ?>
								<div class="wuunder-service-description"><?php echo esc_html( $carrier->carrier_product_description ); ?></div>
							<?php endif; ?>
						</td>
						<td class="wuunder-carriers-table__carrier-cell">
							<?php if ( ! empty( $carrier->carrier_image_url ) ) : ?>
								<img src="<?php echo esc_url( $carrier->carrier_image_url ); ?>"
									alt="<?php echo esc_attr( $carrier->carrier_name ); ?>"
									class="wuunder-carrier-logo" />
							<?php endif; ?>
							<span><?php echo esc_html( $carrier->carrier_name ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		</div>
	<?php endif; ?>
</div>