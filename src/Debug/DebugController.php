<?php

namespace Wuunder\Shipping\Debug;

use Wuunder\Shipping\Contracts\Interfaces\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Controller - Development only
 * Adds debug tab to WooCommerce settings showing REST API output
 */
class DebugController implements Hookable {

	/**
	 * Register WordPress hooks
	 * Note: This class is only loaded when composer install includes dev dependencies
	 */
	public function register_hooks(): void {
		add_filter( 'wuunder_settings_sections', [ $this, 'add_debug_section' ] );
		add_filter( 'wuunder_settings_section_labels', [ $this, 'add_debug_section_label' ] );
		add_action( 'wuunder_settings_section_debug', [ $this, 'render_debug_section' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_debug_assets' ] );
	}

	/**
	 * Enqueue debug assets on Wuunder settings pages
	 */
	public function enqueue_debug_assets( string $hook ): void {
		// Only load on WooCommerce settings pages
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only operation for loading CSS.
		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only operation for loading CSS.
		$section = sanitize_text_field( wp_unslash( $_GET['section'] ?? '' ) );

		if ( $tab === 'wuunder' && $section === 'debug' ) {
			$url = plugin_dir_url( WUUNDER_PLUGIN_FILE ) . 'assets/dist/css/debug.css';

			if ( file_exists( WUUNDER_PLUGIN_PATH . '/assets/dist/css/debug.css' ) ) {
				wp_enqueue_style(
					'wuunder-debug-css',
					$url,
					[],
					WUUNDER_PLUGIN_VERSION
				);
			}
		}
	}

	/**
	 * Add debug section to available sections
	 */
	public function add_debug_section( array $sections ): array {
		$sections[] = 'debug';
		return $sections;
	}

	/**
	 * Add debug section label
	 */
	public function add_debug_section_label( array $labels ): array {
		$labels['debug'] = '🔧 ' . __( 'Debug', 'wuunder-shipping' );
		return $labels;
	}

	/**
	 * Render debug section content
	 */
	public function render_debug_section( string $current_section ): void {
		if ( $current_section !== 'debug' ) {
			return;
		}

		$this->enqueue_debug_assets( 'woocommerce_page_wc-settings' );
		?>
		<h2><?php esc_html_e( 'Debug Information', 'wuunder-shipping' ); ?></h2>
		<p><?php esc_html_e( 'Debug tools for development. This section is only available in development mode.', 'wuunder-shipping' ); ?></p>
		
		<table class="form-table">
			<?php $this->render_wuunder_api_output(); ?>
			<?php $this->render_shipping_methods_output(); ?>
			<?php $this->render_rest_output(); ?>
		</table>
		<?php
	}

	/**
	 * Render Wuunder API output debug section
	 */
	private function render_wuunder_api_output(): void {
		$api_key = get_option( 'wuunder_api_key', '' );

		if ( empty( $api_key ) ) {
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label><?php esc_html_e( 'Wuunder API', 'wuunder-shipping' ); ?></label>
				</th>
				<td class="forminp">
					<p><em><?php esc_html_e( 'No API key configured', 'wuunder-shipping' ); ?></em></p>
				</td>
			</tr>
			<?php
			return;
		}

		$client   = new \Wuunder\Shipping\API\WuunderClient( $api_key );
		$carriers = $client->get_carriers();

		$shipping_carriers = [];
		$pickup_carriers   = [];

		if ( ! is_wp_error( $carriers ) ) {
			foreach ( $carriers as $carrier_id => $carrier ) {
				if ( ! empty( $carrier['accepts_parcelshop_delivery'] ) ) {
					$pickup_carriers[ $carrier_id ] = $carrier;
				} else {
					$shipping_carriers[ $carrier_id ] = $carrier;
				}
			}
		}

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'Wuunder API - Shipping Carriers', 'wuunder-shipping' ); ?></label>
			</th>
			<td class="forminp">
				<div class="wuunder-debug-container">
					<div class="debug-header">
						<?php esc_html_e( 'Shipping carriers from API (after filters)', 'wuunder-shipping' ); ?>
						<span style="margin-left: 10px; font-weight: normal;">
							<?php
							if ( is_wp_error( $carriers ) ) {
								echo esc_html( 'Error: ' . $carriers->get_error_message() );
							} else {
								/* translators: %d: Number of shipping carriers */
								printf( esc_html__( '%d carriers', 'wuunder-shipping' ), count( $shipping_carriers ) );
							}
							?>
						</span>
					</div>
					<div class="debug-output" style="max-height: 400px; overflow-y: auto;">
						<pre><?php echo esc_html( wp_json_encode( $shipping_carriers, JSON_PRETTY_PRINT ) ); ?></pre>
					</div>
				</div>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'Wuunder API - Pickup Carriers', 'wuunder-shipping' ); ?></label>
			</th>
			<td class="forminp">
				<div class="wuunder-debug-container">
					<div class="debug-header">
						<?php esc_html_e( 'Pickup carriers from API (accepts_parcelshop_delivery = true)', 'wuunder-shipping' ); ?>
						<span style="margin-left: 10px; font-weight: normal;">
							<?php
							if ( is_wp_error( $carriers ) ) {
								echo esc_html( 'Error: ' . $carriers->get_error_message() );
							} else {
								/* translators: %d: Number of pickup carriers */
								printf( esc_html__( '%d carriers', 'wuunder-shipping' ), count( $pickup_carriers ) );
							}
							?>
						</span>
					</div>
					<div class="debug-output" style="max-height: 400px; overflow-y: auto;">
						<pre><?php echo esc_html( wp_json_encode( $pickup_carriers, JSON_PRETTY_PRINT ) ); ?></pre>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render shipping methods debug section
	 */
	private function render_shipping_methods_output(): void {
		$zones    = \WC_Shipping_Zones::get_zones();
		$zones[0] = \WC_Shipping_Zones::get_zone_by( 'zone_id', 0 );

		$shipping_methods = [];
		$pickup_methods   = [];

		foreach ( $zones as $zone ) {
			if ( is_array( $zone ) ) {
				$zone_obj  = \WC_Shipping_Zones::get_zone( $zone['id'] );
				$zone_name = $zone['zone_name'];
			} else {
				$zone_obj  = $zone;
				$zone_name = $zone_obj->get_zone_name();
			}

			if ( ! $zone_obj ) {
				continue;
			}

			foreach ( $zone_obj->get_shipping_methods() as $instance_id => $method ) {
				if ( $method->id === 'wuunder_shipping' ) {
					$shipping_methods[] = [
						'zone'        => $zone_name,
						'instance_id' => $instance_id,
						'title'       => $method->get_option( 'title', '' ),
						'carrier'     => $method->get_option( 'wuunder_carrier', '' ),
						'enabled'     => $method->is_enabled() ? 'yes' : 'no',
					];
				}

				if ( $method->id === 'wuunder_pickup' ) {
					$pickup_methods[] = [
						'zone'               => $zone_name,
						'instance_id'        => $instance_id,
						'title'              => $method->get_option( 'title', '' ),
						'available_carriers' => $method->get_option( 'available_carriers', [] ),
						'enabled'            => $method->is_enabled() ? 'yes' : 'no',
					];
				}
			}
		}

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'Wuunder Shipping Methods', 'wuunder-shipping' ); ?></label>
			</th>
			<td class="forminp">
				<div class="wuunder-debug-container">
					<div class="debug-header">
						<?php
						/* translators: %d: Number of shipping methods configured */
						printf( esc_html__( '%d shipping methods configured', 'wuunder-shipping' ), count( $shipping_methods ) );
						?>
					</div>
					<div class="debug-output" style="max-height: 300px; overflow-y: auto;">
						<pre><?php echo esc_html( wp_json_encode( $shipping_methods, JSON_PRETTY_PRINT ) ); ?></pre>
					</div>
				</div>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'Wuunder Pickup Methods', 'wuunder-shipping' ); ?></label>
			</th>
			<td class="forminp">
				<div class="wuunder-debug-container">
					<div class="debug-header">
						<?php
						/* translators: %d: Number of pickup methods configured */
						printf( esc_html__( '%d pickup methods configured', 'wuunder-shipping' ), count( $pickup_methods ) );
						?>
					</div>
					<div class="debug-output" style="max-height: 300px; overflow-y: auto;">
						<pre><?php echo esc_html( wp_json_encode( $pickup_methods, JSON_PRETTY_PRINT ) ); ?></pre>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render REST output debug section
	 */
	public function render_rest_output(): void {
		$orders_data = $this->get_orders_rest_data();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php esc_html_e( 'REST API Output', 'wuunder-shipping' ); ?></label>
			</th>
			<td class="forminp">
				<div class="wuunder-debug-container">
					<div class="debug-header"><?php esc_html_e( 'Last 10 Orders (as seen by external services via WC REST API)', 'wuunder-shipping' ); ?></div>
					
					<div class="debug-output">
						<?php $this->render_orders_data( $orders_data ); ?>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get orders data as it appears in REST API
	 */
	private function get_orders_rest_data(): array {
		// Make actual REST API request to get orders list
		$request = new \WP_REST_Request( 'GET', '/wc/v2/orders' );
		$request->set_param( 'per_page', 10 );
		$request->set_param( 'orderby', 'date' );
		$request->set_param( 'order', 'desc' );

		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			error_log( 'Wuunder Debug: REST API error: ' . $response->get_error_message() );
			return [];
		}

		$orders_data = $response->get_data();

		if ( ! is_array( $orders_data ) ) {
			error_log( 'Wuunder Debug: Invalid REST API response format' );
			return [];
		}

		error_log( 'Wuunder Debug: Found ' . count( $orders_data ) . ' orders via REST API' );

		return $orders_data;
	}


	/**
	 * Render orders data in a readable format
	 */
	private function render_orders_data( array $orders_data ): void {
		if ( empty( $orders_data ) ) {
			echo '<p><em>' . esc_html__( 'No orders found', 'wuunder-shipping' ) . '</em></p>';
			return;
		}

		foreach ( $orders_data as $order_data ) {
			$order_id   = $order_data['id'] ?? 'N/A';
			$has_pickup = $this->has_pickup_shipping( $order_data );

			?>
			<div class="debug-order">
				<h4 class="order-header">
					<?php
					/* translators: %s: Order ID */
					printf( esc_html__( 'Order #%s', 'wuunder-shipping' ), esc_html( $order_id ) );
					?>
					<?php if ( $has_pickup ) : ?>
						<span class="pickup-badge">PICKUP</span>
					<?php endif; ?>
				</h4>
				
				<pre><?php echo esc_html( wp_json_encode( $order_data, JSON_PRETTY_PRINT ) ); ?></pre>
			</div>
			<?php
		}
	}

	/**
	 * Check if order has pickup shipping
	 */
	private function has_pickup_shipping( array $order_data ): bool {
		if ( empty( $order_data['shipping_lines'] ) ) {
			return false;
		}

		foreach ( $order_data['shipping_lines'] as $line ) {
			if ( strpos( $line['method_id'] ?? '', 'wuunder_pickup' ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
