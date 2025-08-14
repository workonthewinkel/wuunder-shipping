<?php

namespace Wuunder\Shipping\Models\Database;

use Wuunder\Shipping\Models\Schema\AddCarriersTable;

/**
 * Migrations class
 *
 * Deals with all database migrations that our plugin requires
 */
class Migrations {

	/**
	 * Run the database migrations for this plugin
	 */
	public function run(): void {

		// Get the migrations and loop through them.
		$migrations = $this->get_migrations();
		foreach ( $migrations as $migration ) {

			// Only create the tables that don't exist.
			if ( $migration->exists() === false ) {
				$migration->run();
			}
		}
	}

	/**
	 * Returns the available migrations as instances
	 *
	 * @return array<Schema>
	 */
	public function get_migrations(): array {

		return [
			new AddCarriersTable(),
		];
	}
}
