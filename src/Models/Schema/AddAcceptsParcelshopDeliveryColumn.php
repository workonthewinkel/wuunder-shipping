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

		$table_name = \esc_sql( $this->get_table_name() );
		$sql        = "ALTER TABLE `{$table_name}` ADD COLUMN `accepts_parcelshop_delivery` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_parcelshop_drop_off`";

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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

		$table_name = \esc_sql( $this->get_table_name() );
		$query      = $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
			'accepts_parcelshop_delivery'
		);

		return (bool) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}


