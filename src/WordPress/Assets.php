<?php

namespace Wuunder\Shipping\WordPress;

use Wooping\ShopHealth\Contracts\Interfaces\Hookable;

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
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Add our custom css and js
	 */
	public function enqueue( string $hook ): void {
		$url = \plugin_dir_url( \EXAMPLE_PLUGIN_FILE ) . 'assets/dist';

		\wp_enqueue_script( 'wuunder_shipping_admin_js', $url . '/js/main.js', [ 'wp-api' ], \EXAMPLE_PLUGIN_VERSION, [ 'in_footer' => true ] );
		\wp_enqueue_style( 'wuunder_shipping_admin_css', $url . '/css/admin.css', [], \EXAMPLE_PLUGIN_VERSION );
	}
}
