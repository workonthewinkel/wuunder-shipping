<?php

namespace Wuunder\Shipping\WooCommerce;

use Wuunder\Shipping\Contracts\Interfaces\Hookable;

/**
 * Handles checkout processing for Wuunder shipping methods.
 */
class CheckoutHandler implements Hookable {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Add pickup point data to shipping method label
		add_filter( 'woocommerce_cart_shipping_method_full_label', [ $this, 'add_pickup_data_to_label' ], 10, 2 );
		
		// Process checkout and save pickup point data
		add_action( 'woocommerce_checkout_create_order', [ $this, 'save_pickup_point_to_order' ], 10, 2 );
		
		// Validate pickup point selection
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_pickup_selection' ], 10, 2 );
		
		// Add data attributes to shipping methods on checkout
		add_filter( 'woocommerce_shipping_rate_html', [ $this, 'add_shipping_rate_data_attributes' ], 10, 2 );
		
		// Pickup point info now shown in shipping address, no need for separate display
		// add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $this, 'display_pickup_point_in_admin' ] );
		
		// Replace shipping address with pickup point address for pickup orders
		add_filter( 'woocommerce_order_formatted_shipping_address', [ $this, 'replace_shipping_address_with_pickup' ], 10, 2 );
		add_filter( 'woocommerce_formatted_address_replacements', [ $this, 'format_pickup_address_replacements' ], 10, 2 );
		add_filter( 'woocommerce_localisation_address_formats', [ $this, 'set_pickup_address_format' ] );
		
		// Hide shipping address on order confirmation and emails for pickup orders
		// Disabled because we want to show pickup point in shipping address column instead
		// add_filter( 'woocommerce_order_hide_shipping_address', [ $this, 'hide_shipping_address_for_pickup' ], 10, 2 );
		
		// Add pickup point information to emails only (address replacement handles confirmation page)
		add_action( 'woocommerce_email_customer_details', [ $this, 'display_pickup_point_in_email' ], 15, 4 );
		
		// Override shipping method display in admin to show pickup point details
		add_filter( 'woocommerce_order_shipping_to_display', [ $this, 'customize_shipping_display_for_pickup' ], 10, 2 );
	}

	/**
	 * Add data attributes to shipping rate HTML for JavaScript access.
	 *
	 * @param string $label HTML label.
	 * @param object $rate Shipping rate object.
	 * @return string
	 */
	public function add_shipping_rate_data_attributes( $label, $rate ): string {
		// Check if this is a pickup method
		if ( strpos( $rate->id, 'wuunder_pickup' ) === false ) {
			return $label;
		}

		// Get shipping method instance to access configuration
		$shipping_methods = WC()->shipping->get_shipping_methods();
		$pickup_method = isset( $shipping_methods['wuunder_pickup'] ) ? $shipping_methods['wuunder_pickup'] : null;
		
		// Build data attributes with defaults
		$carriers = 'dhl,postnl,ups';
		$color = '#52ba69';
		$language = 'nl';
		
		if ( $pickup_method ) {
			$available_carriers = $pickup_method->get_option( 'available_carriers', ['dhl', 'postnl', 'ups'] );
			$carriers = is_array( $available_carriers ) ? implode( ',', $available_carriers ) : $carriers;
			$color = $pickup_method->get_option( 'primary_color', '#52ba69' );
			$language = $pickup_method->get_option( 'language', 'nl' );
		}

		// Add script to inject data attributes
		$script = sprintf(
			"<script>
			jQuery(document).ready(function($) {
				var input = $('input[value=\"%s\"]');
				if (input.length) {
					var li = input.closest('li');
					li.attr('data-carriers', '%s');
					li.attr('data-primary-color', '%s');
					li.attr('data-language', '%s');
				}
			});
			</script>",
			esc_js( $rate->id ),
			esc_js( $carriers ),
			esc_js( $color ),
			esc_js( $language )
		);

		return $label . $script;
	}

	/**
	 * Add pickup point information to the shipping method label if selected.
	 *
	 * @param string $label Shipping method label.
	 * @param object $method Shipping method object.
	 * @return string
	 */
	public function add_pickup_data_to_label( $label, $method ): string {
		// Check if this is a pickup method
		if ( strpos( $method->id, 'wuunder_pickup' ) === false ) {
			return $label;
		}

		// Check if we have a selected pickup point in session
		$pickup_point = WC()->session->get( 'wuunder_selected_pickup_point' );
		
		if ( $pickup_point && is_array( $pickup_point ) ) {
			$pickup_info = sprintf(
				'<div class="wuunder-selected-pickup-info"><small>%s: %s, %s %s</small></div>',
				__( 'Pickup at', 'wuunder-shipping' ),
				esc_html( $pickup_point['name'] ?? '' ),
				esc_html( $pickup_point['street'] ?? '' ),
				esc_html( $pickup_point['city'] ?? '' )
			);
			
			$label .= $pickup_info;
		}

		return $label;
	}

	/**
	 * Save pickup point data to the order.
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $data Posted data.
	 * @return void
	 */
	public function save_pickup_point_to_order( $order, $data ): void {
		// Check if pickup point was selected
		if ( ! isset( $_POST['wuunder_selected_pickup_point'] ) ) {
			return;
		}

		// Get the selected shipping method
		$shipping_methods = $order->get_shipping_methods();
		$is_pickup_method = false;

		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				$is_pickup_method = true;
				break;
			}
		}

		if ( ! $is_pickup_method ) {
			return;
		}

		// Decode pickup point data - try different unslashing approaches
		$raw_data = $_POST['wuunder_selected_pickup_point'];
		
		// Try to decode without unslashing first
		$pickup_point = json_decode( $raw_data, true );
		
		// If that fails, try with wp_unslash
		if ( ! $pickup_point ) {
			$pickup_point = json_decode( wp_unslash( $raw_data ), true );
		}
		
		// If still fails, try with stripslashes
		if ( ! $pickup_point ) {
			$pickup_point = json_decode( stripslashes( $raw_data ), true );
		}

		if ( ! $pickup_point ) {
			return;
		}

		// Save pickup point data as order meta
		$order->update_meta_data( '_wuunder_pickup_point', $pickup_point );
		
		// Also save individual fields for easy access
		if ( isset( $pickup_point['id'] ) ) {
			$order->update_meta_data( '_wuunder_pickup_point_id', $pickup_point['id'] );
		}
		if ( isset( $pickup_point['name'] ) ) {
			$order->update_meta_data( '_wuunder_pickup_point_name', $pickup_point['name'] );
		}
		if ( isset( $pickup_point['street'] ) ) {
			$order->update_meta_data( '_wuunder_pickup_point_street', $pickup_point['street'] );
		}
		if ( isset( $pickup_point['postcode'] ) ) {
			$order->update_meta_data( '_wuunder_pickup_point_postcode', $pickup_point['postcode'] );
		}
		if ( isset( $pickup_point['city'] ) ) {
			$order->update_meta_data( '_wuunder_pickup_point_city', $pickup_point['city'] );
		}
		if ( isset( $pickup_point['country'] ) ) {
			$order->update_meta_data( '_wuunder_pickup_point_country', $pickup_point['country'] );
		}
		if ( isset( $pickup_point['carrier'] ) ) {
			$order->update_meta_data( '_wuunder_pickup_point_carrier', $pickup_point['carrier'] );
		}
		if ( isset( $pickup_point['opening_hours'] ) ) {
			$order->update_meta_data( '_wuunder_pickup_point_opening_hours', $pickup_point['opening_hours'] );
		}

		// Store in session for display purposes
		WC()->session->set( 'wuunder_selected_pickup_point', $pickup_point );
		
		// Add order note
		$note = sprintf(
			/* translators: %1$s: pickup point name, %2$s: street, %3$s: postcode, %4$s: city */
			__( 'Pickup point selected: %1$s, %2$s, %3$s %4$s', 'wuunder-shipping' ),
			$pickup_point['name'] ?? '',
			$pickup_point['street'] ?? '',
			$pickup_point['postcode'] ?? '',
			$pickup_point['city'] ?? ''
		);
		$order->add_order_note( $note );
	}

	/**
	 * Validate that a pickup point was selected when using pickup shipping.
	 *
	 * @param array     $data Posted checkout data.
	 * @param \WP_Error $errors Validation errors.
	 * @return void
	 */
	public function validate_pickup_selection( $data, $errors ): void {
		// Check if a pickup shipping method is selected
		if ( ! isset( $data['shipping_method'] ) || empty( $data['shipping_method'][0] ) ) {
			return;
		}

		$selected_method = $data['shipping_method'][0];

		// Check if it's a pickup method
		if ( strpos( $selected_method, 'wuunder_pickup' ) === false ) {
			return;
		}

		// Check if pickup point was selected
		if ( ! isset( $_POST['wuunder_selected_pickup_point'] ) || empty( $_POST['wuunder_selected_pickup_point'] ) ) {
			$errors->add(
				'pickup_point_required',
				__( 'Please select a pickup location for your delivery.', 'wuunder-shipping' )
			);
			return;
		}

		// Get raw data and try different unslashing approaches
		$raw_data = $_POST['wuunder_selected_pickup_point'];
		
		// Debug logging
		error_log( 'Wuunder Debug - Raw POST data: ' . var_export( $raw_data, true ) );
		
		// Try to decode without unslashing first
		$pickup_point = json_decode( $raw_data, true );
		error_log( 'Wuunder Debug - Decode attempt 1 (raw): ' . var_export( $pickup_point, true ) );
		
		// If that fails, try with wp_unslash
		if ( ! $pickup_point ) {
			$pickup_point = json_decode( wp_unslash( $raw_data ), true );
			error_log( 'Wuunder Debug - Decode attempt 2 (wp_unslash): ' . var_export( $pickup_point, true ) );
		}
		
		// If still fails, try with stripslashes
		if ( ! $pickup_point ) {
			$pickup_point = json_decode( stripslashes( $raw_data ), true );
			error_log( 'Wuunder Debug - Decode attempt 3 (stripslashes): ' . var_export( $pickup_point, true ) );
		}
		
		$debug_info = sprintf(
			'Raw data length: %d, JSON decode result: %s, Is array: %s, Has ID: %s',
			strlen( $raw_data ),
			$pickup_point ? 'success' : 'failed',
			is_array( $pickup_point ) ? 'yes' : 'no',
			( is_array( $pickup_point ) && isset( $pickup_point['id'] ) ) ? 'yes' : 'no'
		);
		
		if ( ! $pickup_point || ! is_array( $pickup_point ) || empty( $pickup_point['id'] ) ) {
			$errors->add(
				'pickup_point_invalid',
				sprintf(
					__( 'Invalid pickup location data. Please select a pickup location again. Debug: %s', 'wuunder-shipping' ),
					$debug_info
				)
			);
		} else {
			error_log( 'Wuunder Debug - Validation successful: ' . var_export( $pickup_point, true ) );
		}
	}

	/**
	 * Display pickup point information in admin order details.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function display_pickup_point_in_admin( $order ): void {
		$pickup_point = $order->get_meta( '_wuunder_pickup_point' );
		
		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			return;
		}
		
		// Check if this order used pickup shipping
		$shipping_methods = $order->get_shipping_methods();
		$is_pickup_method = false;
		
		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				$is_pickup_method = true;
				break;
			}
		}
		
		if ( ! $is_pickup_method ) {
			return;
		}
		
		?>
		<div class="address">
			<h3><?php esc_html_e( 'Pickup Location', 'wuunder-shipping' ); ?></h3>
			<p>
				<strong><?php echo esc_html( $pickup_point['name'] ?? '' ); ?></strong><br>
				<?php echo esc_html( $pickup_point['street'] ?? '' ); ?><br>
				<?php echo esc_html( $pickup_point['postcode'] ?? '' ); ?> <?php echo esc_html( $pickup_point['city'] ?? '' ); ?><br>
				<?php echo esc_html( $pickup_point['country'] ?? '' ); ?>
				<?php if ( ! empty( $pickup_point['carrier'] ) ) : ?>
					<br><em><?php esc_html_e( 'Carrier:', 'wuunder-shipping' ); ?> <?php echo esc_html( strtoupper( $pickup_point['carrier'] ) ); ?></em>
				<?php endif; ?>
				<?php if ( ! empty( $pickup_point['id'] ) ) : ?>
					<br><small><?php esc_html_e( 'ID:', 'wuunder-shipping' ); ?> <?php echo esc_html( $pickup_point['id'] ); ?></small>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Replace shipping address with pickup point address for pickup orders.
	 *
	 * @param array     $address Formatted shipping address.
	 * @param \WC_Order $order Order object.
	 * @return array
	 */
	public function replace_shipping_address_with_pickup( $address, $order ): array {
		// Ensure we have an array to work with
		if ( ! is_array( $address ) ) {
			$address = [];
		}
		
		// Check if this order used pickup shipping
		$pickup_point = $order->get_meta( '_wuunder_pickup_point' );
		
		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			return $address;
		}
		
		$shipping_methods = $order->get_shipping_methods();
		$is_pickup_method = false;
		
		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				$is_pickup_method = true;
				break;
			}
		}
		
		if ( ! $is_pickup_method ) {
			return $address;
		}
		
		// Build address_2 field with carrier and optionally ID for admin
		$address_2_parts = [];
		if ( ! empty( $pickup_point['carrier'] ) ) {
			$address_2_parts[] = sprintf( __( 'Carrier: %s', 'wuunder-shipping' ), strtoupper( $pickup_point['carrier'] ) );
		}
		if ( is_admin() && ! empty( $pickup_point['id'] ) ) {
			$address_2_parts[] = sprintf( __( 'ID: %s', 'wuunder-shipping' ), $pickup_point['id'] );
		}
		
		// Replace with pickup point address
		$pickup_address = [
			'company'    => $pickup_point['name'] ?? '',
			'address_1'  => $pickup_point['street'] ?? '',
			'address_2'  => implode( ' • ', $address_2_parts ),
			'city'       => $pickup_point['city'] ?? '',
			'state'      => '',
			'postcode'   => $pickup_point['postcode'] ?? '',
			'country'    => $pickup_point['country'] ?? '',
			'first_name' => __( 'Pickup at', 'wuunder-shipping' ),
			'last_name'  => '',
		];
		
		return $pickup_address;
	}

	/**
	 * Format address replacements for pickup points.
	 *
	 * @param array $replacements Address replacements.
	 * @param array $args Address arguments.
	 * @return array
	 */
	public function format_pickup_address_replacements( $replacements, $args ): array {
		// Ensure we have arrays to work with
		if ( ! is_array( $replacements ) ) {
			$replacements = [];
		}
		if ( ! is_array( $args ) ) {
			$args = [];
		}
		
		// Check if this is a pickup address replacement
		if ( isset( $args['first_name'] ) && $args['first_name'] === __( 'Pickup at', 'wuunder-shipping' ) ) {
			// Customize the formatting for pickup addresses
			$replacements['{name}'] = $args['first_name'];
			$replacements['{company}'] = $args['company'];
			$replacements['{first_name}'] = '';
			$replacements['{last_name}'] = '';
		}
		
		return $replacements;
	}

	/**
	 * Set pickup address format for better display.
	 *
	 * @param array $formats Address formats.
	 * @return array
	 */
	public function set_pickup_address_format( $formats ): array {
		// Ensure we have an array to work with
		if ( ! is_array( $formats ) ) {
			$formats = [];
		}
		
		// Add a specific format for pickup addresses (we'll use the default format)
		// This could be customized further if needed
		return $formats;
	}

	/**
	 * Hide shipping address for pickup orders by adding pickup method IDs to the hide list.
	 *
	 * @param array     $hide_methods Array of shipping method IDs to hide address for.
	 * @param \WC_Order $order Order object.
	 * @return array
	 */
	public function hide_shipping_address_for_pickup( $hide_methods, $order ): array {
		// Ensure we have an array to work with
		if ( ! is_array( $hide_methods ) ) {
			$hide_methods = [];
		}
		
		// Check if this order used pickup shipping
		$pickup_point = $order->get_meta( '_wuunder_pickup_point' );
		
		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			return $hide_methods;
		}
		
		$shipping_methods = $order->get_shipping_methods();
		
		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				// Add our pickup method ID to the hide list
				$hide_methods[] = $shipping_method->get_method_id();
				break;
			}
		}
		
		return $hide_methods;
	}

	/**
	 * Display pickup point information on order confirmation page.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function display_pickup_point_on_confirmation( $order ): void {
		$pickup_point = $order->get_meta( '_wuunder_pickup_point' );
		
		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			return;
		}
		
		// Check if this order used pickup shipping
		$shipping_methods = $order->get_shipping_methods();
		$is_pickup_method = false;
		
		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				$is_pickup_method = true;
				break;
			}
		}
		
		if ( ! $is_pickup_method ) {
			return;
		}
		
		?>
		<section class="woocommerce-pickup-details">
			<h2 class="woocommerce-column__title"><?php esc_html_e( 'Pickup Location', 'wuunder-shipping' ); ?></h2>
			<address>
				<strong><?php echo esc_html( $pickup_point['name'] ?? '' ); ?></strong><br>
				<?php echo esc_html( $pickup_point['street'] ?? '' ); ?><br>
				<?php echo esc_html( $pickup_point['postcode'] ?? '' ); ?> <?php echo esc_html( $pickup_point['city'] ?? '' ); ?><br>
				<?php echo esc_html( $pickup_point['country'] ?? '' ); ?>
				<?php if ( ! empty( $pickup_point['carrier'] ) ) : ?>
					<br><em><?php esc_html_e( 'Carrier:', 'wuunder-shipping' ); ?> <?php echo esc_html( strtoupper( $pickup_point['carrier'] ) ); ?></em>
				<?php endif; ?>
			</address>
		</section>
		<?php
	}

	/**
	 * Display pickup point information in order emails.
	 *
	 * @param \WC_Order $order Order object.
	 * @param bool      $sent_to_admin Whether sent to admin.
	 * @param bool      $plain_text Whether plain text email.
	 * @param object    $email Email object.
	 * @return void
	 */
	public function display_pickup_point_in_email( $order, $sent_to_admin, $plain_text, $email ): void {
		$pickup_point = $order->get_meta( '_wuunder_pickup_point' );
		
		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			return;
		}
		
		// Check if this order used pickup shipping
		$shipping_methods = $order->get_shipping_methods();
		$is_pickup_method = false;
		
		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				$is_pickup_method = true;
				break;
			}
		}
		
		if ( ! $is_pickup_method ) {
			return;
		}
		
		if ( $plain_text ) {
			echo "\n" . esc_html__( 'PICKUP LOCATION:', 'wuunder-shipping' ) . "\n";
			echo esc_html( $pickup_point['name'] ?? '' ) . "\n";
			echo esc_html( $pickup_point['street'] ?? '' ) . "\n";
			echo esc_html( $pickup_point['postcode'] ?? '' ) . ' ' . esc_html( $pickup_point['city'] ?? '' ) . "\n";
			echo esc_html( $pickup_point['country'] ?? '' );
			if ( ! empty( $pickup_point['carrier'] ) ) {
				echo "\n" . esc_html__( 'Carrier:', 'wuunder-shipping' ) . ' ' . esc_html( strtoupper( $pickup_point['carrier'] ) );
			}
			echo "\n\n";
		} else {
			?>
			<div style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Pickup Location', 'wuunder-shipping' ); ?></h3>
				<address style="font-style: normal;">
					<strong><?php echo esc_html( $pickup_point['name'] ?? '' ); ?></strong><br>
					<?php echo esc_html( $pickup_point['street'] ?? '' ); ?><br>
					<?php echo esc_html( $pickup_point['postcode'] ?? '' ); ?> <?php echo esc_html( $pickup_point['city'] ?? '' ); ?><br>
					<?php echo esc_html( $pickup_point['country'] ?? '' ); ?>
					<?php if ( ! empty( $pickup_point['carrier'] ) ) : ?>
						<br><em><?php esc_html_e( 'Carrier:', 'wuunder-shipping' ); ?> <?php echo esc_html( strtoupper( $pickup_point['carrier'] ) ); ?></em>
					<?php endif; ?>
				</address>
			</div>
			<?php
		}
	}

	/**
	 * Customize shipping method display to show simple pickup point name in totals.
	 *
	 * @param string $shipping_display The shipping display string.
	 * @param WC_Order $order The order object.
	 * @return string Modified shipping display.
	 */
	public function customize_shipping_display_for_pickup( $shipping_display, $order ): string {
		$pickup_point = $order->get_meta( '_wuunder_pickup_point' );
		
		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			return $shipping_display;
		}
		
		// Check if this order used pickup shipping
		$shipping_methods = $order->get_shipping_methods();
		$is_pickup_method = false;
		
		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				$is_pickup_method = true;
				break;
			}
		}
		
		if ( ! $is_pickup_method ) {
			return $shipping_display;
		}
		
		// Simple display for order totals - just "Pick up at {name}"
		if ( ! empty( $pickup_point['name'] ) ) {
			return sprintf( __( 'Pick up at %s', 'wuunder-shipping' ), esc_html( $pickup_point['name'] ) );
		}
		
		return $shipping_display;
	}
}