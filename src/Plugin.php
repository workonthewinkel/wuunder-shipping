<?php

namespace Wuunder\Shipping;

use Wuunder\Shipping\WordPress\Assets;
use Wuunder\Shipping\Models\Database\Migrations;

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

	}

	/**
	 * Runs when the plugin gets deactivated.
	 *
	 * @return void
	 */
	public function uninstall(): void {

		// Roll back our migrations
		( new Migrations() )->roll_back();

	}

	/**
	 * Call all classes needed for the custom functionality.
	 */
	public function init(): void {

		// General WordPress hooks.
		( new Assets() )->register_hooks();
		
	}

}
