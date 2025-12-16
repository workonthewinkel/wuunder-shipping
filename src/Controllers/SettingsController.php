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
	 * Whether API key is configured.
	 *
	 * @var bool
	 */
	private bool $has_api_key = false;

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
		$this->has_api_key = ! empty( get_option( 'wuunder_api_key', '' ) );

		// Default section depends on whether API key is set
		$default_section = $this->has_api_key ? 'shipping_methods' : 'settings';
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : $default_section; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Get available sections from filter
		$available_sections = apply_filters( 'wuunder_settings_sections', [ 'shipping_methods', 'pickup_methods', 'settings' ] );

		// Validate current section exists
		if ( ! in_array( $current_section, $available_sections, true ) ) {
			$current_section = $default_section;
		}

		View::display(
			'admin/settings-tabs',
			[
				'current_section'    => $current_section,
				'has_api_key'        => $this->has_api_key,
				'available_sections' => $available_sections,
			]
		);

		// Allow sections to render their own content
		do_action( 'wuunder_settings_section_' . $current_section, $current_section );

		// Fallback to built-in sections
		if ( $current_section === 'shipping_methods' ) {
			$this->display_shipping_methods_section();
		} elseif ( $current_section === 'pickup_methods' ) {
			$this->display_pickup_methods_section();
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
	 * Display shipping methods section.
	 *
	 * @return void
	 */
	private function display_shipping_methods_section(): void {
		// Auto-load carriers if none exist (service handles API key check)
		if ( empty( Carrier::get_all() ) ) {
			$this->load_carriers_from_api();
		}

		// Get standard carriers (non-parcelshop)
		$carrier_methods = Carrier::get_standard_carriers();

		$carrier_names = [];
		foreach ( $carrier_methods as $carrier ) {
			if ( ! isset( $carrier_names[ $carrier->carrier_code ] ) ) {
				$carrier_names[ $carrier->carrier_code ] = $carrier->carrier_name;
			}
		}

		View::display(
			'admin/methods-section',
			[
				'carrier_methods' => $carrier_methods,
				'carrier_names'   => $carrier_names,
				'has_api_key'     => $this->has_api_key,
				'carrier_type'    => 'standard',
			]
		);
	}

	/**
	 * Display pickup methods section.
	 *
	 * @return void
	 */
	private function display_pickup_methods_section(): void {
		// Auto-load carriers if none exist (service handles API key check)
		if ( empty( Carrier::get_all() ) ) {
			$this->load_carriers_from_api();
		}

		// Get parcelshop carriers
		$carrier_methods = Carrier::get_parcelshop_carriers();

		$carrier_names = [];
		foreach ( $carrier_methods as $carrier ) {
			if ( ! isset( $carrier_names[ $carrier->carrier_code ] ) ) {
				$carrier_names[ $carrier->carrier_code ] = $carrier->carrier_name;
			}
		}

		View::display(
			'admin/methods-section',
			[
				'carrier_methods' => $carrier_methods,
				'carrier_names'   => $carrier_names,
				'has_api_key'     => $this->has_api_key,
				'carrier_type'    => 'parcelshop',
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

		// Detect methods section by POST data presence since URL section param gets lost
		$is_methods_section = isset( $_POST['wuunder_enabled_carriers'] ) || in_array( $current_section, [ 'shipping_methods', 'pickup_methods' ], true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $is_methods_section ) {
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

		// Determine which carrier type we're updating
		$carrier_type = isset( $_POST['wuunder_carrier_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wuunder_carrier_type'] ) ) : 'standard';

		// Get only carriers of the type being edited
		if ( $carrier_type === 'parcelshop' ) {
			$carriers = Carrier::get_parcelshop_carriers();
		} else {
			$carriers = Carrier::get_standard_carriers();
		}

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
			CarrierService::disable_shipping_method_instances( $disabled_carriers );
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
		// Only care about enabling Wuunder methods
		if ( ! $is_enabled || ! in_array( $method_id, [ 'wuunder_shipping', 'wuunder_pickup' ], true ) ) {
			return;
		}

		// Get the shipping method instance
		$zone             = \WC_Shipping_Zones::get_zone( $zone_id );
		$shipping_methods = $zone->get_shipping_methods();

		if ( ! isset( $shipping_methods[ $instance_id ] ) ) {
			return;
		}

		$shipping_method = $shipping_methods[ $instance_id ];

		// Check wuunder_shipping: carrier must exist and be enabled
		if ( $method_id === 'wuunder_shipping' ) {
			$carrier_option = $shipping_method->get_option( 'wuunder_carrier', '' );

			if ( ! empty( $carrier_option ) ) {
				$carrier = Carrier::find_by_method_id( $carrier_option );

				if ( ! $carrier || ! $carrier->enabled ) {
					CarrierService::disable_shipping_method( $shipping_method, $instance_id, $zone_id );
				}
			}
		}

		// Check wuunder_pickup: must have at least one enabled carrier
		if ( $method_id === 'wuunder_pickup' ) {
			$available_carriers = $shipping_method->get_option( 'available_carriers', [] );

			if ( ! is_array( $available_carriers ) || empty( $available_carriers ) ) {
				CarrierService::disable_shipping_method( $shipping_method, $instance_id, $zone_id );
				return;
			}

			// Check if at least one carrier is enabled
			$enabled_carriers    = Carrier::get_parcelshop_carriers( true );
			$has_enabled_carrier = false;
			foreach ( $enabled_carriers as $carrier ) {
				if ( in_array( $carrier->get_method_id(), $available_carriers, true ) ) {
					$has_enabled_carrier = true;
					break;
				}
			}

			if ( ! $has_enabled_carrier ) {
				CarrierService::disable_shipping_method( $shipping_method, $instance_id, $zone_id );
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
