<?php

namespace Wuunder\Shipping\WooCommerce;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Wuunder\Shipping\Contracts\Interfaces\Hookable;
use Wuunder\Shipping\WooCommerce\CheckoutHandler;
use Wuunder\Shipping\WooCommerce\Methods\Pickup;
use Wuunder\Shipping\WordPress\View;

/**
 * Class for integrating with WooCommerce Blocks.
 */
class BlocksIntegration implements IntegrationInterface, Hookable {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wuunder-pickup';
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Register ourselves with WooCommerce Blocks using the correct hook
		add_action( 'woocommerce_blocks_checkout_block_registration', [ $this, 'register_with_integration_registry' ] );
		add_action( 'woocommerce_blocks_cart_block_registration', [ $this, 'register_with_integration_registry' ] );

		// Extend Store API with pickup point data
		add_action( 'woocommerce_blocks_loaded', [ $this, 'extend_store_api' ] );

		// AJAX handler for storing pickup point in session (for block checkout)
		add_action( 'wp_ajax_wuunder_store_pickup_point', [ $this, 'ajax_store_pickup_point' ] );
		add_action( 'wp_ajax_nopriv_wuunder_store_pickup_point', [ $this, 'ajax_store_pickup_point' ] );
	}

	/**
	 * Register the integration with WooCommerce Blocks.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $integration_registry Integration registry.
	 * @return void
	 */
	public function register_with_integration_registry( $integration_registry ): void {
		$integration_registry->register( $this );
	}

	/**
	 * Initialize the integration.
	 *
	 * This is fired by Automattic\WooCommerce\Blocks\Assets\Api::register_script()
	 * and should be used to initialize any PHP-side integration handling.
	 */
	public function initialize() {
		$this->register_scripts();
		$this->register_editor_scripts();
		$this->register_styles();
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return [ 'wuunder-pickup-block-frontend' ];
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return [ 'wuunder-pickup-block-editor' ];
	}

	/**
	 * Returns an array of script data to pass to the block scripts.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wuunder-pickup-block' ),
			'i18n' => [
				'selectPickupLocation' => __( 'Select pick-up location', 'wuunder-shipping' ),
				'change' => __( 'Change', 'wuunder-shipping' ),
				'close' => __( 'Close', 'wuunder-shipping' ),
				'loading' => __( 'Loading...', 'wuunder-shipping' ),
				'errorLoading' => __( 'Error loading pickup points', 'wuunder-shipping' ),
			],
		];
	}

	/**
	 * Register scripts for the block checkout integration.
	 */
	private function register_scripts(): void {
		$script_path = 'assets/dist/js/blocks.js';
		$script_url  = plugin_dir_url( WUUNDER_PLUGIN_FILE ) . $script_path;
		$script_file = WUUNDER_PLUGIN_PATH . '/' . $script_path;
		$asset_file  = WUUNDER_PLUGIN_PATH . '/assets/dist/js/blocks.asset.php';

		// Load asset file for dependencies and version
		$asset = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => $this->get_script_dependencies(),
			'version' => WUUNDER_PLUGIN_VERSION,
		);

		if ( file_exists( $script_file ) ) {
			wp_register_script(
				'wuunder-pickup-block-frontend',
				$script_url,
				$asset['dependencies'],
				$asset['version'],
				true
			);

			// Localize script with necessary data
			wp_localize_script(
				'wuunder-pickup-block-frontend',
				'wuunderPickupBlock',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'wuunder-pickup-block' ),
					'methodSettings' => $this->get_pickup_method_settings(),
					'iframeConfig' => [
						'baseUrl' => Pickup::IFRAME_BASE_URL,
						'origin' => Pickup::IFRAME_ORIGIN,
					],
					'i18n' => [
						'selectPickupLocation' => __( 'Select pick-up location', 'wuunder-shipping' ),
						'change' => __( 'Change', 'wuunder-shipping' ),
						'close' => __( 'Close', 'wuunder-shipping' ),
						'loading' => __( 'Loading...', 'wuunder-shipping' ),
						'errorLoading' => __( 'Error loading pickup points', 'wuunder-shipping' ),
					],
				]
			);

			// Render pickup point template in footer
			add_action( 'wp_footer', [ $this, 'render_pickup_template' ] );
		}
	}

	/**
	 * Render pickup point template in footer.
	 */
	public function render_pickup_template(): void {
		echo View::render( 'frontend/pickup-point-display' );
	}

	/**
	 * Register editor scripts.
	 */
	private function register_editor_scripts(): void {
		// Use the same script for editor and frontend
		$script_path = 'assets/dist/js/blocks.js';
		$script_url  = plugin_dir_url( WUUNDER_PLUGIN_FILE ) . $script_path;
		$script_file = WUUNDER_PLUGIN_PATH . '/' . $script_path;
		$asset_file  = WUUNDER_PLUGIN_PATH . '/assets/dist/js/blocks.asset.php';

		// Load asset file for dependencies and version
		$asset = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => $this->get_script_dependencies(),
			'version' => WUUNDER_PLUGIN_VERSION,
		);

		if ( file_exists( $script_file ) ) {
			wp_register_script(
				'wuunder-pickup-block-editor',
				$script_url,
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}
	}

	/**
	 * Register styles for the block checkout integration.
	 */
	private function register_styles(): void {
		$style_path = 'assets/dist/css/blocks.css';
		$style_url  = plugin_dir_url( WUUNDER_PLUGIN_FILE ) . $style_path;
		$style_file = WUUNDER_PLUGIN_PATH . '/' . $style_path;

		if ( file_exists( $style_file ) ) {
			wp_register_style(
				'wuunder-pickup-block-style',
				$style_url,
				[],
				WUUNDER_PLUGIN_VERSION
			);

			// Enqueue style when scripts are enqueued
			add_action(
				'wp_enqueue_scripts',
				function () {
					if ( wp_script_is( 'wuunder-pickup-block-frontend', 'enqueued' ) ) {
						wp_enqueue_style( 'wuunder-pickup-block-style' );
					}
				}
			);
		}
	}

	/**
	 * Get script dependencies.
	 *
	 * @return array
	 */
	private function get_script_dependencies(): array {
		return [
			'wp-element',
			'wp-i18n',
			'wp-components',
			'wp-data',
			'wp-plugins',
		];
	}

	/**
	 * Extend Store API with pickup point schema.
	 */
	public function extend_store_api(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint' => 'checkout',
				'namespace' => 'wuunder-pickup',
				'data_callback' => [ $this, 'get_pickup_data' ],
				'schema_callback' => [ $this, 'get_pickup_schema' ],
				'schema_type' => ARRAY_A,
			]
		);
	}

	/**
	 * Get pickup data for Store API.
	 *
	 * @return array
	 */
	public function get_pickup_data(): array {
		return [
			'pickup_point' => WC()->session->get( 'wuunder_selected_pickup_point', null ),
		];
	}

	/**
	 * Get pickup schema for Store API.
	 *
	 * @return array
	 */
	public function get_pickup_schema(): array {
		return [
			'pickup_point' => [
				'description' => __( 'Selected pickup point data', 'wuunder-shipping' ),
				'type' => [ 'object', 'null' ],
				'properties' => [
					'id' => [
						'type' => 'string',
					],
					'name' => [
						'type' => 'string',
					],
					'street' => [
						'type' => 'string',
					],
					'postcode' => [
						'type' => 'string',
					],
					'city' => [
						'type' => 'string',
					],
					'country' => [
						'type' => 'string',
					],
					'carrier' => [
						'type' => 'string',
					],
					'opening_hours' => [
						'type' => 'array',
					],
				],
			],
		];
	}

	/**
	 * Handle AJAX request to store pickup point in session.
	 *
	 * @return void
	 */
	public function ajax_store_pickup_point(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wuunder-pickup-block' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token', 'wuunder-shipping' ) ] );
			return;
		}

		// Get pickup point data
		if ( ! isset( $_POST['pickup_point'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No pickup point data provided', 'wuunder-shipping' ) ] );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string will be sanitized after decoding.
		$pickup_point = json_decode( wp_unslash( $_POST['pickup_point'] ), true );

		if ( ! $pickup_point || ! is_array( $pickup_point ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid pickup point data', 'wuunder-shipping' ) ] );
			return;
		}

		// Store in WooCommerce session
		if ( WC()->session ) {
			WC()->session->set( 'wuunder_selected_pickup_point', $pickup_point );

			// Trigger cart recalculation to update shipping method meta
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();

			wp_send_json_success(
				[
					'message' => __( 'Pickup point stored successfully', 'wuunder-shipping' ),
					'pickup_point' => $pickup_point,
				]
			);
		} else {
			wp_send_json_error( [ 'message' => __( 'WooCommerce session not available', 'wuunder-shipping' ) ] );
		}
	}

	/**
	 * Get pickup method settings for all instances.
	 *
	 * @return array
	 */
	private function get_pickup_method_settings(): array {
		$settings = [];

		// Get all shipping zones
		$zones    = \WC_Shipping_Zones::get_zones();
		$zones[0] = \WC_Shipping_Zones::get_zone( 0 ); // Add default zone

		foreach ( $zones as $zone ) {
			if ( is_array( $zone ) ) {
				$zone = \WC_Shipping_Zones::get_zone( $zone['zone_id'] );
			}

			$shipping_methods = $zone->get_shipping_methods();

			foreach ( $shipping_methods as $method ) {
				if ( $method->id === 'wuunder_pickup' ) {
					$settings[ $method->get_instance_id() ] = [
						'primary_color' => $method->get_option( 'primary_color', '#52ba69' ),
						'available_carriers' => $method->get_option( 'available_carriers', [ 'dhl', 'postnl', 'ups' ] ),
						'language' => $method->get_option( 'language', 'nl' ),
					];
				}
			}
		}

		return $settings;
	}
}
