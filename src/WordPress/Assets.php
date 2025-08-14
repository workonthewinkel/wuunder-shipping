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

		// Check if we're on the Wuunder tab
		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $tab !== 'wuunder' ) {
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
				]
			);
		}

		wp_enqueue_script( 'wuunder_shipping_main_js', $url . '/js/main.js', [ 'wp-api' ], \WUUNDER_PLUGIN_VERSION, [ 'in_footer' => true ] );
	}
}
