<?php

namespace Wuunder\Shipping\Models\Database;

use Wuunder\Shipping\Models\Schema\AddExampleTable;

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
	 * Revert the database migrations for this plugin
	 */
	public function roll_back(): void {

		// Get the migrations and loop through them.
		$migrations = $this->get_migrations();

		// Reverse the migrations array, because roll-backs happen in the reverse order.
		foreach ( \array_reverse( $migrations ) as $migration ) {

			// Only delete the tables if they exist.
			if ( $migration->exists() === true ) {
				$migration->roll_back();
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
			new AddExampleTable()
		];
	}
}
