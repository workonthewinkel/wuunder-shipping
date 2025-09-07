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

		// Add pickup point meta to shipping item when it's created
		add_action( 'woocommerce_checkout_create_order_shipping_item', [ $this, 'add_pickup_point_to_shipping_item' ], 10, 4 );

		// Update shipping address after order is created (both classic and block checkout)
		add_action( 'woocommerce_checkout_order_created', [ $this, 'update_pickup_order_shipping_address' ], 10 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'update_pickup_order_shipping_address' ], 10 );

		// Validate pickup point selection (classic and block checkout)
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_pickup_selection' ], 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'validate_pickup_selection_block' ], 10, 2 );

		// Replace shipping address with pickup point address for pickup orders
		add_filter( 'woocommerce_order_formatted_shipping_address', [ $this, 'replace_shipping_address_with_pickup' ], 10, 2 );
		add_filter( 'woocommerce_formatted_address_replacements', [ $this, 'format_pickup_address_replacements' ], 10, 2 );

		// Add pickup point information to emails only (address replacement handles confirmation page)
		add_action( 'woocommerce_email_customer_details', [ $this, 'display_pickup_point_in_email' ], 15, 4 );

		// Override shipping method display in admin to show pickup point details
		add_filter( 'woocommerce_order_shipping_to_display', [ $this, 'customize_shipping_display_for_pickup' ], 10, 2 );

		// Hide pickup point metadata in admin except pickup_point_id
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hide_pickup_point_metadata_in_admin' ], 10, 1 );

		// Customize pickup point ID display in admin
		add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'customize_pickup_point_meta_display' ], 10, 3 );

		// AJAX handler for classic checkout pickup point storage
		add_action( 'wp_ajax_wuunder_store_pickup_point_classic', [ $this, 'ajax_store_pickup_point_classic' ] );
		add_action( 'wp_ajax_nopriv_wuunder_store_pickup_point_classic', [ $this, 'ajax_store_pickup_point_classic' ] );
	}

	/**
	 * Add pickup point meta to shipping item when it's created.
	 *
	 * @param \WC_Order_Item_Shipping $item Shipping item.
	 * @param string                  $package_key Package key.
	 * @param array                   $package Package data.
	 * @param \WC_Order               $order Order object.
	 * @return void
	 */
	public function add_pickup_point_to_shipping_item( $item, $package_key, $package, $order ): void {
		// Check if this is a pickup shipping method
		if ( strpos( $item->get_method_id(), 'wuunder_pickup' ) === false ) {
			return;
		}

		// Get pickup point from session
		$pickup_point = WC()->session ? WC()->session->get( 'wuunder_selected_pickup_point' ) : null;

		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			return;
		}

		// Add all pickup point data as meta
		if ( ! empty( $pickup_point['id'] ) ) {
			$item->add_meta_data( 'pickup_point_id', $pickup_point['id'], true );
		}
		if ( ! empty( $pickup_point['name'] ) ) {
			$item->add_meta_data( 'pickup_point_name', $pickup_point['name'], true );
		}
		if ( ! empty( $pickup_point['street'] ) ) {
			$item->add_meta_data( 'pickup_point_street', $pickup_point['street'], true );
		}
		if ( ! empty( $pickup_point['street_name'] ) ) {
			$item->add_meta_data( 'pickup_point_street_name', $pickup_point['street_name'], true );
		}
		if ( ! empty( $pickup_point['house_number'] ) ) {
			$item->add_meta_data( 'pickup_point_house_number', $pickup_point['house_number'], true );
		}
		if ( ! empty( $pickup_point['postcode'] ) ) {
			$item->add_meta_data( 'pickup_point_postcode', $pickup_point['postcode'], true );
		}
		if ( ! empty( $pickup_point['city'] ) ) {
			$item->add_meta_data( 'pickup_point_city', $pickup_point['city'], true );
		}
		if ( ! empty( $pickup_point['country'] ) ) {
			$item->add_meta_data( 'pickup_point_country', $pickup_point['country'], true );
		}
		if ( ! empty( $pickup_point['carrier'] ) ) {
			$item->add_meta_data( 'pickup_point_carrier', $pickup_point['carrier'], true );
		}
		if ( ! empty( $pickup_point['opening_hours'] ) ) {
			$item->add_meta_data( 'pickup_point_opening_hours', wp_json_encode( $pickup_point['opening_hours'] ), true );
		}
	}

	/**
	 * Validate that a pickup point was selected when using pickup shipping (classic checkout).
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

		// Use unified validation logic
		$pickup_point = $this->get_pickup_point_for_validation();
		
		if ( ! $this->is_valid_pickup_point( $pickup_point ) ) {
			$errors->add(
				'pickup_point_required',
				__( 'Please select a pickup location for your delivery.', 'wuunder-shipping' )
			);
		}
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

		// Get pickup point data from shipping method meta
		$pickup_point = $this->get_pickup_point_from_order( $order );

		if ( ! $pickup_point ) {
			return $address;
		}

		// Build address_2 field with carrier and optionally ID for admin
		$address_2_parts = [];
		if ( ! empty( $pickup_point['carrier'] ) ) {
			/* translators: %s: Carrier name */
			$address_2_parts[] = sprintf( __( 'Carrier: %s', 'wuunder-shipping' ), strtoupper( $pickup_point['carrier'] ) );
		}
		if ( is_admin() && ! empty( $pickup_point['id'] ) ) {
			/* translators: %s: Pickup point ID */
			$address_2_parts[] = sprintf( __( 'ID: %s', 'wuunder-shipping' ), $pickup_point['id'] );
		}

		// Replace with pickup point address
		$pickup_address = [
			'company'    => $pickup_point['name'] ?? '',
			'address_1'  => $pickup_point['street'] ?? '',
			'address_2'  => implode( "\n", $address_2_parts ),
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
			$replacements['{name}']       = $args['first_name'];
			$replacements['{company}']    = $args['company'];
			$replacements['{first_name}'] = '';
			$replacements['{last_name}']  = '';
		}

		return $replacements;
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
		$pickup_point = $this->get_pickup_point_from_order( $order );

		if ( ! $pickup_point ) {
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
	 * @param string   $shipping_display The shipping display string.
	 * @param WC_Order $order The order object.
	 * @return string Modified shipping display.
	 */
	public function customize_shipping_display_for_pickup( $shipping_display, $order ): string {
		$pickup_point = $this->get_pickup_point_from_order( $order );

		if ( ! $pickup_point ) {
			return $shipping_display;
		}

		// Simple display for order totals - just "Pick up at {name}"
		if ( ! empty( $pickup_point['name'] ) ) {
			/* translators: %s: Pickup point name */
			return sprintf( __( 'Pick up at %s', 'wuunder-shipping' ), esc_html( $pickup_point['name'] ) );
		}

		return $shipping_display;
	}

	/**
	 * Update pickup order shipping address after order creation.
	 * This method gets pickup point data from shipping meta and updates the order's shipping address.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function update_pickup_order_shipping_address( $order ): void {
		// Get pickup point data from shipping method meta
		$pickup_point = $this->get_pickup_point_from_order( $order );

		if ( ! $pickup_point ) {
			return;
		}

		// Update shipping address with pickup point data
		// Keep customer's first and last name (required fields)

		// Set shipping company to pickup point name
		$order->set_shipping_company( $pickup_point['name'] ?? '' );

		// Set address fields
		$order->set_shipping_address_1( $pickup_point['street'] ?? '' );
		/* translators: %s: Carrier name */
		$order->set_shipping_address_2( ! empty( $pickup_point['carrier'] ) ? sprintf( __( 'Carrier: %s', 'wuunder-shipping' ), strtoupper( $pickup_point['carrier'] ) ) : '' );
		$order->set_shipping_city( $pickup_point['city'] ?? '' );
		$order->set_shipping_postcode( $pickup_point['postcode'] ?? '' );
		$order->set_shipping_country( $pickup_point['country'] ?? '' );
		$order->set_shipping_state( '' ); // Clear state for pickup points

		// Save the changes
		$order->save();
	}

	/**
	 * Get pickup point data from order shipping method meta.
	 *
	 * @param \WC_Order $order Order object.
	 * @return array|null Pickup point data or null if not found.
	 */
	private function get_pickup_point_from_order( $order ): ?array {
		$shipping_methods = $order->get_shipping_methods();

		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				// Build pickup point data from individual meta fields
				$pickup_point = [];

				// Get all meta data from the shipping method
				$meta_data = $shipping_method->get_meta_data();

				// Extract pickup point data from meta
				foreach ( $meta_data as $meta ) {
					$key   = $meta->key;
					$value = $meta->value;

					switch ( $key ) {
						case 'pickup_point_id':
							$pickup_point['id'] = $value;
							break;
						case 'pickup_point_name':
							$pickup_point['name'] = $value;
							break;
						case 'pickup_point_street':
							$pickup_point['street'] = $value;
							break;
						case 'pickup_point_street_name':
							$pickup_point['street_name'] = $value;
							break;
						case 'pickup_point_house_number':
							$pickup_point['house_number'] = $value;
							break;
						case 'pickup_point_postcode':
							$pickup_point['postcode'] = $value;
							break;
						case 'pickup_point_city':
							$pickup_point['city'] = $value;
							break;
						case 'pickup_point_country':
							$pickup_point['country'] = $value;
							break;
						case 'pickup_point_carrier':
							$pickup_point['carrier'] = $value;
							break;
						case 'pickup_point_opening_hours':
							$pickup_point['opening_hours'] = json_decode( $value, true );
							break;
					}
				}

				// Return pickup point data if we found any
				if ( ! empty( $pickup_point ) ) {
					return $pickup_point;
				}
			}
		}

		return null;
	}

	/**
	 * Handle AJAX request to store pickup point in session for classic checkout.
	 *
	 * @return void
	 */
	public function ajax_store_pickup_point_classic(): void {
		// Verify nonce for classic checkout
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wuunder-checkout' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token', 'wuunder-shipping' ) ] );
			return;
		}

		// Get pickup point data
		if ( ! isset( $_POST['pickup_point'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No pickup point data provided', 'wuunder-shipping' ) ] );
			return;
		}

		// Decode pickup point data
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string will be sanitized after decoding.
		$pickup_point = json_decode( wp_unslash( $_POST['pickup_point'] ), true );

		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid pickup point data', 'wuunder-shipping' ) ] );
			return;
		}

		// Store in WooCommerce session
		if ( WC()->session ) {
			WC()->session->set( 'wuunder_selected_pickup_point', $pickup_point );

			wp_send_json_success(
				[
					'message' => __( 'Pickup point stored successfully in classic checkout', 'wuunder-shipping' ),
					'pickup_point' => $pickup_point,
				]
			);
		} else {
			wp_send_json_error( [ 'message' => __( 'WooCommerce session not available', 'wuunder-shipping' ) ] );
		}
	}

	/**
	 * Validate pickup point selection for block checkout using Store API.
	 *
	 * @param \WC_Order        $order Order object.
	 * @param \WP_REST_Request $request REST request object.
	 * @return void
	 * @throws \WC_Data_Exception When validation fails.
	 */
	public function validate_pickup_selection_block( $order, $request ): void {
		// Get shipping methods from order
		$shipping_methods = $order->get_shipping_methods();
		
		if ( empty( $shipping_methods ) ) {
			return;
		}

		// Check if any shipping method is a pickup method
		$has_pickup_method = false;
		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				$has_pickup_method = true;
				break;
			}
		}

		if ( ! $has_pickup_method ) {
			return;
		}

		// Use unified validation logic
		$pickup_point = $this->get_pickup_point_for_validation();
		
		if ( ! $this->is_valid_pickup_point( $pickup_point ) ) {
			throw new \WC_Data_Exception(
				'pickup_point_required',
				__( 'Please select a pickup location for your delivery.', 'wuunder-shipping' )
			);
		}
	}

	/**
	 * Get pickup point data for validation (unified method for both checkout types).
	 *
	 * @return array|null Pickup point data or null if not found.
	 */
	private function get_pickup_point_for_validation(): ?array {
		// First try to get from session (works for both checkout types)
		if ( WC()->session ) {
			$pickup_point = WC()->session->get( 'wuunder_selected_pickup_point' );
			if ( $pickup_point && is_array( $pickup_point ) ) {
				return $pickup_point;
			}
		}

		// Fallback for classic checkout: check POST data
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WooCommerce.
		if ( isset( $_POST['wuunder_selected_pickup_point'] ) && ! empty( $_POST['wuunder_selected_pickup_point'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified by WooCommerce, sanitization happens after JSON decode.
			$raw_data = $_POST['wuunder_selected_pickup_point'];
			
			// Try to decode the JSON data
			$pickup_point = json_decode( $raw_data, true );
			if ( ! $pickup_point ) {
				$pickup_point = json_decode( wp_unslash( $raw_data ), true );
			}
			if ( ! $pickup_point ) {
				$pickup_point = json_decode( stripslashes( $raw_data ), true );
			}

			if ( $pickup_point && is_array( $pickup_point ) ) {
				return $pickup_point;
			}
		}

		return null;
	}

	/**
	 * Validate if pickup point data is valid (unified validation logic).
	 *
	 * @param array|null $pickup_point Pickup point data to validate.
	 * @return bool True if pickup point is valid, false otherwise.
	 */
	private function is_valid_pickup_point( $pickup_point ): bool {
		return $pickup_point && is_array( $pickup_point ) && ! empty( $pickup_point['id'] );
	}

	/**
	 * Hide pickup point metadata in admin except pickup_point_id.
	 *
	 * @param array $hidden_meta Array of metadata keys to hide.
	 * @return array
	 */
	public function hide_pickup_point_metadata_in_admin( $hidden_meta ): array {
		// Add all pickup point metadata except pickup_point_id to hidden list
		$pickup_point_meta_to_hide = [
			'pickup_point_name',
			'pickup_point_street',
			'pickup_point_street_name',
			'pickup_point_house_number',
			'pickup_point_postcode',
			'pickup_point_city',
			'pickup_point_country',
			'pickup_point_carrier',
			'pickup_point_opening_hours',
		];

		return array_merge( $hidden_meta, $pickup_point_meta_to_hide );
	}

	/**
	 * Customize pickup point meta key display in admin.
	 *
	 * @param string                $display_key The display key.
	 * @param \WC_Meta_Data         $meta Meta data object.
	 * @param \WC_Order_Item_Shipping $item Order item object.
	 * @return string
	 */
	public function customize_pickup_point_meta_display( $display_key, $meta, $item ): string {
		// Only customize for pickup point ID on shipping items
		if ( $meta->key === 'pickup_point_id' && $item instanceof \WC_Order_Item_Shipping ) {
			// Check if this is a pickup shipping method
			if ( strpos( $item->get_method_id(), 'wuunder_pickup' ) !== false ) {
				// Return simple 'ID' label
				return __( 'Method ID', 'wuunder-shipping' );
			}
		}

		return $display_key;
	}
}