<?php

namespace Wuunder\Shipping\Debug;

use Wuunder\Shipping\Contracts\Interfaces\Hookable;

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

		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) );
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
			<?php $this->render_rest_output(); ?>
		</table>
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
		$orders = wc_get_orders([
			'limit' => 10,
			'orderby' => 'date',
			'order' => 'DESC',
			'status' => 'any'
		]);

		error_log( 'Wuunder Debug: Found ' . count( $orders ) . ' orders' );

		$orders_data = [];

		foreach ( $orders as $order ) {
			// Convert order directly to array format similar to REST API
			$order_data = [
				'id' => $order->get_id(),
				'status' => $order->get_status(),
				'date_created' => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : '',
				'total' => $order->get_total(),
				'currency' => $order->get_currency(),
				'billing' => [
					'first_name' => $order->get_billing_first_name(),
					'last_name' => $order->get_billing_last_name(),
					'company' => $order->get_billing_company(),
					'address_1' => $order->get_billing_address_1(),
					'address_2' => $order->get_billing_address_2(),
					'city' => $order->get_billing_city(),
					'state' => $order->get_billing_state(),
					'postcode' => $order->get_billing_postcode(),
					'country' => $order->get_billing_country(),
					'email' => $order->get_billing_email(),
					'phone' => $order->get_billing_phone(),
				],
				'shipping' => [
					'first_name' => $order->get_shipping_first_name(),
					'last_name' => $order->get_shipping_last_name(),
					'company' => $order->get_shipping_company(),
					'address_1' => $order->get_shipping_address_1(),
					'address_2' => $order->get_shipping_address_2(),
					'city' => $order->get_shipping_city(),
					'state' => $order->get_shipping_state(),
					'postcode' => $order->get_shipping_postcode(),
					'country' => $order->get_shipping_country(),
				],
				'shipping_lines' => $this->format_shipping_lines( $order ),
				'meta_data' => $this->format_meta_data( $order ),
			];
			
			error_log( 'Wuunder Debug: Successfully processed order ' . $order->get_id() );
			$orders_data[] = $order_data;
		}

		return $orders_data;
	}

	/**
	 * Format shipping lines for display
	 */
	private function format_shipping_lines( \WC_Order $order ): array {
		$shipping_lines = [];
		
		foreach ( $order->get_shipping_methods() as $shipping_item ) {
			$shipping_lines[] = [
				'id' => $shipping_item->get_id(),
				'method_id' => $shipping_item->get_method_id(),
				'method_title' => $shipping_item->get_method_title(),
				'total' => $shipping_item->get_total(),
				'meta_data' => $this->format_item_meta_data( $shipping_item ),
			];
		}
		
		return $shipping_lines;
	}

	/**
	 * Format order meta data
	 */
	private function format_meta_data( \WC_Order $order ): array {
		$meta_data = [];
		
		foreach ( $order->get_meta_data() as $meta ) {
			$meta_data[] = [
				'key' => $meta->key,
				'value' => $meta->value,
			];
		}
		
		return $meta_data;
	}

	/**
	 * Format item meta data
	 */
	private function format_item_meta_data( \WC_Order_Item $item ): array {
		$meta_data = [];
		
		foreach ( $item->get_meta_data() as $meta ) {
			$meta_data[] = [
				'key' => $meta->key,
				'value' => $meta->value,
			];
		}
		
		return $meta_data;
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
			$order_id = $order_data['id'] ?? 'N/A';
			$has_pickup = $this->has_pickup_shipping( $order_data );
			
			?>
			<div class="debug-order">
				<h4 class="order-header">
					<?php printf( esc_html__( 'Order #%s', 'wuunder-shipping' ), esc_html( $order_id ) ); ?>
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