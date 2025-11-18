<?php

namespace Wuunder\Shipping;

use Wuunder\Shipping\WordPress\Assets;
use Wuunder\Shipping\Models\Database\Migrations;
use Wuunder\Shipping\Controllers\SettingsController;
use Wuunder\Shipping\WooCommerce\Register;
use Wuunder\Shipping\WooCommerce\CheckoutHandler;
use Wuunder\Shipping\WooCommerce\BlocksIntegration;
use Wuunder\Shipping\WooCommerce\RestApiHandler;
use Wuunder\Shipping\WordPress\Admin;

/**
 * Plugin God class.
 */
class Plugin {

	/**
	 * Runs when the plugin is first activated.
	 *
	 * @return void
	 */
	public function install(): void {

		// Run migrations.
		( new Migrations() )->run();

		// Save the current version to the database.
		update_option( 'wuunder_plugin_version', WUUNDER_PLUGIN_VERSION );

		// Set transient to redirect to settings after activation
		set_transient( 'wuunder_activation_redirect', true, 30 );
	}

	/**
	 * Runs when the plugin gets deactivated.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// For now, don't do anything on uninstall. In many cases the plugin is temporarily deactivated.
	}

	/**
	 * Checks for version changes and runs migrations if needed.
	 *
	 * @return void
	 */
	public function update(): void {

		// Get the stored version from the database.
		$stored_version = get_option( 'wuunder_plugin_version', '' );

		// Get the current version from the constant.
		$current_version = WUUNDER_PLUGIN_VERSION;

		// If there's no stored version or if the current version is higher, run migrations.
		if ( empty( $stored_version ) || version_compare( $current_version, $stored_version, '>' ) ) {

			// Run migrations.
			( new Migrations() )->run();

			// Update the stored version.
			update_option( 'wuunder_plugin_version', $current_version );
		}
	}

	/**
	 * Call all classes needed for the custom functionality.
	 */
	public function init(): void {

		// Check for version changes and run migrations if needed.
		$this->update();

		// General WordPress hooks.
		( new Admin() )->register_hooks();

		// General WordPress hooks.
		( new Assets() )->register_hooks();

		// Dynamic shipping method registry
		( new Register() )->register_hooks();

		// Checkout handler for pickup points
		( new CheckoutHandler() )->register_hooks();

		// Block checkout integration
		( new BlocksIntegration() )->register_hooks();

		// REST API customizations
		( new RestApiHandler() )->register_hooks();

		// Admin settings
		( new SettingsController() )->register_hooks();
	}
}
