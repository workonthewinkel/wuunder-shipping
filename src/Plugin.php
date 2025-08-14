<?php

namespace Wuunder\Shipping;

use Wuunder\Shipping\WordPress\Assets;
use Wuunder\Shipping\Models\Database\Migrations;
use Wuunder\Shipping\Controllers\SettingsController;
use Wuunder\Shipping\WooCommerce\ShippingMethodRegistry;
use Wuunder\Shipping\WooCommerce\ShippingZoneDataFilter;
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
	 * Call all classes needed for the custom functionality.
	 */
	public function init(): void {

		// General WordPress hooks.
		( new Admin() )->register_hooks();

		// General WordPress hooks.
		( new Assets() )->register_hooks();

		// Dynamic shipping method registry
		( new ShippingMethodRegistry() )->register_hooks();

		// Shipping zone data filter for JavaScript
		( new ShippingZoneDataFilter() )->register_hooks();

		// Admin settings
		( new SettingsController() )->register_hooks();
	}
}
