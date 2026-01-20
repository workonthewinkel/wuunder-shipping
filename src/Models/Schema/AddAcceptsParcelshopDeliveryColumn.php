<?php

namespace Wuunder\Shipping\Models\Schema;

use Wuunder\Shipping\Contracts\Schema;

/**
 * Add accepts_parcelshop_delivery column to carriers table.
 */
class AddAcceptsParcelshopDeliveryColumn extends Schema {

	/**
	 * Define the table name.
	 */
	protected string $table_name = 'wuunder_carriers';

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( parent::exists() === false ) {
			return;
		}

		global $wpdb;

		$table_name = $this->get_table_name();

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'ALTER TABLE %i ADD COLUMN `accepts_parcelshop_delivery` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_parcelshop_drop_off`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$table_name
			)
		);
	}

	/**
	 * Check if the column already exists.
	 *
	 * @return bool
	 */
	public function exists(): bool {
		if ( parent::exists() === false ) {
			return false;
		}

		global $wpdb;

		$table_name = $this->get_table_name();

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'accepts_parcelshop_delivery'
			)
		);
	}
}
