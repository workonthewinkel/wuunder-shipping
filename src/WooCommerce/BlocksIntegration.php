<?php

namespace Wuunder\Shipping\WooCommerce;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Wuunder\Shipping\Contracts\Interfaces\Hookable;

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
		
		// Handle Store API extension data
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'update_order_from_request' ], 10, 2 );
		
		// Extend Store API with pickup point data
		add_action( 'woocommerce_blocks_loaded', [ $this, 'extend_store_api' ] );
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
		$script_path = 'build/index.js';
		$script_url  = plugin_dir_url( WUUNDER_PLUGIN_FILE ) . $script_path;
		$script_file = WUUNDER_PLUGIN_PATH . '/' . $script_path;
		$asset_file = WUUNDER_PLUGIN_PATH . '/build/index.asset.php';

		// Load asset file for dependencies and version
		$asset = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => $this->get_script_dependencies(),
			'version' => WUUNDER_PLUGIN_VERSION
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
					'i18n' => [
						'selectPickupLocation' => __( 'Select pick-up location', 'wuunder-shipping' ),
						'change' => __( 'Change', 'wuunder-shipping' ),
						'close' => __( 'Close', 'wuunder-shipping' ),
						'loading' => __( 'Loading...', 'wuunder-shipping' ),
						'errorLoading' => __( 'Error loading pickup points', 'wuunder-shipping' ),
					],
				]
			);
		}
	}

	/**
	 * Register editor scripts.
	 */
	private function register_editor_scripts(): void {
		// Use the same script for editor and frontend
		$script_path = 'build/index.js';
		$script_url  = plugin_dir_url( WUUNDER_PLUGIN_FILE ) . $script_path;
		$script_file = WUUNDER_PLUGIN_PATH . '/' . $script_path;
		$asset_file = WUUNDER_PLUGIN_PATH . '/build/index.asset.php';

		// Load asset file for dependencies and version
		$asset = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => $this->get_script_dependencies(),
			'version' => WUUNDER_PLUGIN_VERSION
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
		$style_path = 'assets/dist/css/blocks/checkout-pickup.css';
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
			add_action( 'wp_enqueue_scripts', function() {
				if ( wp_script_is( 'wuunder-pickup-block-frontend', 'enqueued' ) ) {
					wp_enqueue_style( 'wuunder-pickup-block-style' );
				}
			} );
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
	 * Update order with pickup point data from block checkout.
	 *
	 * @param \WC_Order $order Order object.
	 * @param \WP_REST_Request $request Request object.
	 */
	public function update_order_from_request( $order, $request ): void {
		// Check if this order uses pickup shipping
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

		// Get extension data from request
		$extensions = $request->get_param( 'extensions' );
		
		if ( isset( $extensions['wuunder-pickup']['pickup_point'] ) ) {
			$pickup_point = $extensions['wuunder-pickup']['pickup_point'];
			
			// Save pickup point data as order meta
			$order->update_meta_data( '_wuunder_pickup_point', $pickup_point );
			
			// Save individual fields for easy access
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
			
			$order->save();
		}
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
				'type' => ['object', 'null'],
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
}