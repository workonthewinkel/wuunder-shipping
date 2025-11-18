<?php

namespace Wuunder\Shipping\Controllers;

use Wuunder\Shipping\Contracts\Controller;
use Wuunder\Shipping\Contracts\Interfaces\Hookable;
use Wuunder\Shipping\API\WuunderClient;
use Wuunder\Shipping\Models\Carrier;
use Wuunder\Shipping\Services\CarrierService;
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
		add_action( 'woocommerce_shipping_zone_method_status_toggled', [ $this, 'prevent_enabling_disabled_carriers' ], 10, 4 );
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
			$this->load_carriers_from_api();
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

		// Track which carriers are being disabled
		$disabled_carriers = [];

		// Update each carrier's enabled status
		foreach ( $carriers as $carrier ) {
			$was_enabled      = $carrier->enabled;
			$carrier->enabled = in_array( $carrier->get_method_id(), $enabled_carriers, true );

			// Track carriers that are being disabled
			if ( $was_enabled && ! $carrier->enabled ) {
				$disabled_carriers[] = $carrier->get_method_id();
			}

			$carrier->save();
		}

		// Disable corresponding shipping method instances for disabled carriers
		if ( ! empty( $disabled_carriers ) ) {
			$this->disable_shipping_method_instances( $disabled_carriers );
		}

		// Clear WooCommerce cache to refresh shipping methods
		if ( function_exists( 'wc_clear_template_cache' ) ) {
			wc_clear_template_cache();
		}
	}

	/**
	 * Disable shipping method instances for disabled carriers.
	 *
	 * @param array $disabled_carrier_ids Array of disabled carrier method IDs.
	 * @return void
	 */
	private function disable_shipping_method_instances( array $disabled_carrier_ids ): void {
		global $wpdb;

		// Get all shipping zones
		$zones    = \WC_Shipping_Zones::get_zones();
		$zones[0] = \WC_Shipping_Zones::get_zone_by( 'zone_id', 0 ); // Add 'Rest of the World' zone

		foreach ( $zones as $zone ) {
			if ( is_array( $zone ) ) {
				$zone_obj = \WC_Shipping_Zones::get_zone( $zone['id'] );
				$zone_id  = $zone['id'];
			} else {
				$zone_obj = $zone;
				$zone_id  = $zone_obj->get_id();
			}

			foreach ( $zone_obj->get_shipping_methods() as $instance_id => $shipping_method ) {
				// Check if this is a Wuunder shipping method
				if ( $shipping_method->id === 'wuunder_shipping' ) {
					$carrier_option = $shipping_method->get_option( 'wuunder_carrier', '' );

					// If this method uses a disabled carrier, disable the shipping method instance
					if ( in_array( $carrier_option, $disabled_carrier_ids, true ) ) {
						// Update the enabled status in the shipping method options
						$shipping_method->update_option( 'enabled', 'no' );

						// Follow WooCommerce core pattern: update database directly
						if ( $wpdb->update( "{$wpdb->prefix}woocommerce_shipping_zone_methods", array( 'is_enabled' => 0 ), array( 'instance_id' => absint( $instance_id ) ) ) ) {
							do_action( 'woocommerce_shipping_zone_method_status_toggled', $instance_id, $shipping_method->id, $zone_id, 0 );
						}
					}
				}
			}
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

		$result = $this->load_carriers_from_api( true );

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
	 * Prevent enabling shipping methods with disabled carriers.
	 *
	 * @param int    $instance_id Instance ID.
	 * @param string $method_id Method ID.
	 * @param int    $zone_id Zone ID.
	 * @param bool   $is_enabled Whether the method is being enabled.
	 * @return void
	 */
	public function prevent_enabling_disabled_carriers( $instance_id, $method_id, $zone_id, $is_enabled ): void {
		// Only care about enabling Wuunder shipping methods
		if ( ! $is_enabled || $method_id !== 'wuunder_shipping' ) {
			return;
		}

		// Get the shipping method instance
		$zone             = \WC_Shipping_Zones::get_zone( $zone_id );
		$shipping_methods = $zone->get_shipping_methods();

		if ( ! isset( $shipping_methods[ $instance_id ] ) ) {
			return;
		}

		$shipping_method = $shipping_methods[ $instance_id ];
		$carrier_option  = $shipping_method->get_option( 'wuunder_carrier', '' );

		// Check if the carrier is disabled
		if ( ! empty( $carrier_option ) ) {
			$carrier = Carrier::find_by_method_id( $carrier_option );

			if ( ! $carrier || ! $carrier->enabled ) {
				// Force disable it again following WooCommerce core pattern
				global $wpdb;
				$wpdb->update( "{$wpdb->prefix}woocommerce_shipping_zone_methods", array( 'is_enabled' => 0 ), array( 'instance_id' => absint( $instance_id ) ) );
			}
		}
	}

	/**
	 * Load carriers from API.
	 *
	 * @param bool $preserve_enabled Whether to preserve enabled state for existing carriers.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function load_carriers_from_api( $preserve_enabled = false ) {
		return CarrierService::refresh_from_api( $preserve_enabled );
	}
}
