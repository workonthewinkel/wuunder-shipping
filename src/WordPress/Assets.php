<?php

namespace Wuunder\Shipping\WordPress;

use Wuunder\Shipping\Contracts\Interfaces\Hookable;

/**
 * Class Assets
 *
 * This class handles the enqueueing of custom CSS and JS assets for the plugin.
 */
class Assets implements Hookable {

	/**
	 * Register hooks for enqueueing scripts and styles
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Add our custom css and js
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load on WooCommerce settings pages
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// We load our assets on the Wuunder and shipping pages.
		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $tab, [ 'wuunder', 'shipping' ], true ) ) {
			return;
		}

		$url = \plugin_dir_url( \WUUNDER_PLUGIN_FILE ) . 'assets/dist';

		// Enqueue admin CSS
		if ( file_exists( WUUNDER_PLUGIN_PATH . '/assets/dist/css/admin.css' ) ) {
			\wp_enqueue_style(
				'wuunder_shipping_admin_css',
				$url . '/css/admin.css',
				[],
				\WUUNDER_PLUGIN_VERSION
			);
		}

		// Enqueue admin JavaScript
		if ( file_exists( WUUNDER_PLUGIN_PATH . '/assets/dist/js/admin.js' ) ) {
			\wp_enqueue_script(
				'wuunder_shipping_admin_js',
				$url . '/js/admin.js',
				[ 'jquery' ],
				\WUUNDER_PLUGIN_VERSION,
				[ 'in_footer' => true ]
			);

			// Localize script
			\wp_localize_script(
				'wuunder_shipping_admin_js',
				'wuunder_admin',
				[
					'ajax_url' => \admin_url( 'admin-ajax.php' ),
					'nonce'    => \wp_create_nonce( 'wuunder-admin' ),
					'i18n'     => [
						// ConnectionTester component strings
						'please_enter_api_key' => __( 'Please enter an API key first', 'wuunder-shipping' ),
						'testing_connection'   => __( 'Testing connection...', 'wuunder-shipping' ),
						'connection_test_failed' => __( 'Connection test failed', 'wuunder-shipping' ),
						
						// CarrierList component strings
						'refreshing_carriers' => __( 'Refreshing carriers...', 'wuunder-shipping' ),
						'failed_refresh_carriers' => __( 'Failed to refresh carriers', 'wuunder-shipping' ),
						
						// DisconnectManager component strings
						'confirm_disconnect' => __( 'Are you sure you want to disconnect? This will clear your API key and all carrier data.', 'wuunder-shipping' ),
						'disconnecting' => __( 'Disconnecting...', 'wuunder-shipping' ),
						'failed_disconnect' => __( 'Failed to disconnect', 'wuunder-shipping' ),
						
						// Validation component strings
						'title_required' => __( 'Title is required', 'wuunder-shipping' ),
						'carrier_required' => __( 'Carrier selection is required', 'wuunder-shipping' ),
						
						// Common UI strings
						'success_prefix' => __( '✓', 'wuunder-shipping' ),
						'error_prefix'   => __( '✗', 'wuunder-shipping' ),
					],
				]
			);
		}

	}
}
