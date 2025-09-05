<?php

namespace Wuunder\Shipping\Controllers;

use Wuunder\Shipping\Contracts\Controller;
use Wuunder\Shipping\Contracts\Interfaces\Hookable;
use Wuunder\Shipping\API\WuunderClient;
use Wuunder\Shipping\Models\Carrier;
use Wuunder\Shipping\WordPress\View;

/**
 * Settings Controller for managing Wuunder settings in WooCommerce.
 */
class SettingsController extends Controller implements Hookable {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_wuunder', [ $this, 'settings_tab' ] );
		add_action( 'woocommerce_update_options_wuunder', [ $this, 'update_settings' ] );
		add_action( 'wp_ajax_wuunder_test_connection', [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_wuunder_refresh_carriers', [ $this, 'ajax_refresh_carriers' ] );
		add_action( 'wp_ajax_wuunder_disconnect', [ $this, 'ajax_disconnect' ] );
	}

	/**
	 * Add Wuunder tab to WooCommerce settings.
	 *
	 * @param array $settings_tabs Existing settings tabs.
	 * @return array
	 */
	public function add_settings_tab( array $settings_tabs ): array {
		$settings_tabs['wuunder'] = __( 'Wuunder', 'wuunder-shipping' );
		return $settings_tabs;
	}

	/**
	 * Display settings tab content.
	 *
	 * @return void
	 */
	public function settings_tab(): void {
		$api_key     = get_option( 'wuunder_api_key', '' );
		$has_api_key = ! empty( $api_key );

		// Default section depends on whether API key is set
		$default_section = $has_api_key ? 'carriers' : 'settings';
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : $default_section; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Get available sections from filter
		$available_sections = apply_filters( 'wuunder_settings_sections', [ 'carriers', 'settings' ] );

		// Validate current section exists
		if ( ! in_array( $current_section, $available_sections, true ) ) {
			$current_section = $default_section;
		}

		View::display(
			'admin/settings-tabs',
			[
				'current_section' => $current_section,
				'has_api_key' => $has_api_key,
				'available_sections' => $available_sections,
			]
		);

		// Allow sections to render their own content
		do_action( 'wuunder_settings_section_' . $current_section, $current_section );

		// Fallback to built-in sections
		if ( $current_section === 'carriers' ) {
			$this->display_carriers_section();
		} elseif ( $current_section === 'settings' ) {
			$this->display_settings_section();
		}
	}

	/**
	 * Display settings section.
	 *
	 * @return void
	 */
	private function display_settings_section(): void {
		$api_key = get_option( 'wuunder_api_key', '' );
		View::display(
			'admin/settings-section',
			[
				'settings' => $this->get_settings(),
				'api_key' => $api_key,
			]
		);
	}

	/**
	 * Display carriers section.
	 *
	 * @return void
	 */
	private function display_carriers_section(): void {
		$api_key     = get_option( 'wuunder_api_key', '' );
		$has_api_key = ! empty( $api_key );

		// Auto-load carriers if we have API key but no carriers
		$carrier_methods = Carrier::get_all();
		if ( $has_api_key && empty( $carrier_methods ) ) {
			$this->load_carriers_from_api( $api_key );
			$carrier_methods = Carrier::get_all(); // Re-fetch after loading
		}

		$carrier_names = [];
		foreach ( $carrier_methods as $carrier ) {
			if ( ! isset( $carrier_names[ $carrier->carrier_code ] ) ) {
				$carrier_names[ $carrier->carrier_code ] = $carrier->carrier_name;
			}
		}

		View::display(
			'admin/carriers-section',
			[
				'carrier_methods' => $carrier_methods,
				'carrier_names' => $carrier_names,
				'has_api_key' => $has_api_key,
			]
		);
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	private function get_settings(): array {
		return [
			[
				'title' => __( 'Wuunder Settings', 'wuunder-shipping' ),
				'type'  => 'title',
				'desc'  => __( 'Configure your Wuunder API integration.', 'wuunder-shipping' ),
				'id'    => 'wuunder_settings',
			],
			[
				'title'    => __( 'API Key', 'wuunder-shipping' ),
				'desc'     => __( 'Enter your Wuunder API key', 'wuunder-shipping' ),
				'id'       => 'wuunder_api_key',
				'type'     => 'password',
				'default'  => '',
				'desc_tip' => true,
			],
			[
				'type' => 'sectionend',
				'id'   => 'wuunder_settings',
			],
		];
	}

	/**
	 * Update settings.
	 *
	 * @return void
	 */
	public function update_settings(): void {
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Detect carriers section by POST data presence since URL section param gets lost
		$is_carriers_section = isset( $_POST['wuunder_enabled_carriers'] ) || $current_section === 'carriers'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $is_carriers_section ) {
			$this->update_carriers();
		} else {
			woocommerce_update_options( $this->get_settings() );
		}
	}

	/**
	 * Update carriers.
	 *
	 * @return void
	 */
	private function update_carriers(): void {
		// Check nonce for security
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}

		$carriers         = Carrier::get_all();
		$enabled_carriers = [];

		// Get enabled carriers from POST data
		if ( isset( $_POST['wuunder_enabled_carriers'] ) && is_array( $_POST['wuunder_enabled_carriers'] ) ) {
			$raw_carriers     = wp_unslash( $_POST['wuunder_enabled_carriers'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$enabled_carriers = array_map( 'sanitize_text_field', array_keys( $raw_carriers ) );
		}

		// Update each carrier's enabled status
		foreach ( $carriers as $carrier ) {
			$carrier->enabled = in_array( $carrier->get_method_id(), $enabled_carriers, true );
			$carrier->save();
		}

		// Clear WooCommerce cache to refresh shipping methods
		if ( function_exists( 'wc_clear_template_cache' ) ) {
			wc_clear_template_cache();
		}
	}


	/**
	 * AJAX handler for testing connection.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'wuunder-admin', 'nonce' );

		// Check if API key is provided in the request (for testing before saving)
		$api_key = '';
		if ( isset( $_POST['api_key'] ) && ! empty( $_POST['api_key'] ) ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
		} else {
			// Fall back to saved API key
			$api_key = get_option( 'wuunder_api_key', '' );
		}

		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'Please provide an API key to test.', 'wuunder-shipping' ) );
		}

		$client = new WuunderClient( $api_key );

		if ( $client->test_connection() ) {
			wp_send_json_success( __( 'Connection successful!', 'wuunder-shipping' ) );
		} else {
			wp_send_json_error( __( 'Connection failed. Please check your API key.', 'wuunder-shipping' ) );
		}
	}

	/**
	 * AJAX handler for refreshing carriers.
	 *
	 * @return void
	 */
	public function ajax_refresh_carriers(): void {
		check_ajax_referer( 'wuunder-admin', 'nonce' );

		$api_key = get_option( 'wuunder_api_key', '' );

		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'Please configure your API key first.', 'wuunder-shipping' ) );
		}

		$result = $this->load_carriers_from_api( $api_key, true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Carriers refreshed successfully!', 'wuunder-shipping' ) );
	}

	/**
	 * AJAX handler for disconnecting Wuunder.
	 *
	 * @return void
	 */
	public function ajax_disconnect(): void {
		check_ajax_referer( 'wuunder-admin', 'nonce' );

		// Clear the API key
		delete_option( 'wuunder_api_key' );

		// Clear carrier hash
		delete_option( 'wuunder_carriers_last_update' );

		wp_send_json_success( __( 'Disconnected successfully. API key and carrier data have been cleared.', 'wuunder-shipping' ) );
	}

	/**
	 * Load carriers from API.
	 *
	 * @param string $api_key The API key to use.
	 * @param bool   $preserve_enabled Whether to preserve enabled state for existing carriers.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private function load_carriers_from_api( $api_key, $preserve_enabled = false ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$client       = new WuunderClient( $api_key );
		$api_carriers = $client->get_carriers();

		if ( is_wp_error( $api_carriers ) ) {
			return $api_carriers;
		}

		// Get existing carriers to optionally preserve enabled state
		$existing_carriers = [];
		if ( $preserve_enabled ) {
			foreach ( Carrier::get_all() as $carrier ) {
				$existing_carriers[ $carrier->get_method_id() ] = $carrier->enabled;
			}
		}

		// Save carriers from API
		foreach ( $api_carriers as $key => $carrier_data ) {
			$carrier = new Carrier();

			// Map all carrier data fields
			$carrier->carrier_code                = $carrier_data['carrier_code'] ?? '';
			$carrier->carrier_product_code        = $carrier_data['carrier_product_code'] ?? '';
			$carrier->service_code                = $carrier_data['service_code'] ?? '';
			$carrier->carrier_name                = $carrier_data['carrier_name'] ?? '';
			$carrier->product_name                = $carrier_data['product_name'] ?? '';
			$carrier->service_level               = $carrier_data['service_level'] ?? '';
			$carrier->carrier_product_description = $carrier_data['carrier_product_description'] ?? '';
			$carrier->price                       = $carrier_data['price'] ?? 0.0;
			$carrier->currency                    = $carrier_data['currency'] ?? 'EUR';
			$carrier->carrier_image_url           = $carrier_data['carrier_image_url'] ?? '';
			$carrier->pickup_date                 = $carrier_data['pickup_date'] ?? '';
			$carrier->pickup_before               = $carrier_data['pickup_before'] ?? '';
			$carrier->pickup_after                = $carrier_data['pickup_after'] ?? '';
			$carrier->pickup_address_type         = $carrier_data['pickup_address_type'] ?? '';
			$carrier->delivery_date               = $carrier_data['delivery_date'] ?? '';
			$carrier->delivery_before             = $carrier_data['delivery_before'] ?? '';
			$carrier->delivery_after              = $carrier_data['delivery_after'] ?? '';
			$carrier->delivery_address_type       = $carrier_data['delivery_address_type'] ?? '';
			$carrier->modality                    = $carrier_data['modality'] ?? '';
			$carrier->is_return                   = $carrier_data['is_return'] ?? false;
			$carrier->is_parcelshop_drop_off      = $carrier_data['is_parcelshop_drop_off'] ?? false;
			$carrier->includes_ad_hoc_pickup      = $carrier_data['includes_ad_hoc_pickup'] ?? false;
			$carrier->info                        = $carrier_data['info'] ?? '';
			$carrier->tags                        = is_array( $carrier_data['tags'] ?? '' ) ? wp_json_encode( $carrier_data['tags'] ) : ( $carrier_data['tags'] ?? '' );
			$carrier->surcharges                  = is_array( $carrier_data['surcharges'] ?? '' ) ? wp_json_encode( $carrier_data['surcharges'] ) : ( $carrier_data['surcharges'] ?? '' );
			$carrier->carrier_product_settings    = is_array( $carrier_data['carrier_product_settings'] ?? '' ) ? wp_json_encode( $carrier_data['carrier_product_settings'] ) : ( $carrier_data['carrier_product_settings'] ?? '' );

			// Preserve enabled state if requested and it existed
			if ( $preserve_enabled && isset( $existing_carriers[ $key ] ) ) {
				$carrier->enabled = $existing_carriers[ $key ];
			} else {
				$carrier->enabled = false; // Start with disabled by default
			}

			$carrier->save();
		}

		return true;
	}
}
