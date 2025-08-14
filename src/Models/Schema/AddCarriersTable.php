<?php

namespace Wuunder\Shipping\Models\Schema;

use Wuunder\Shipping\Contracts\Schema;

/**
 * Add carriers table schema.
 */
class AddCarriersTable extends Schema {

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
		global $wpdb;

		$table_name      = \esc_sql( $this->get_table_name() );
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `{$table_name}` (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			carrier_code varchar(100) NOT NULL,
			carrier_product_code varchar(100) NOT NULL,
			service_code varchar(100) NOT NULL,
			carrier_name varchar(255) NOT NULL,
			product_name varchar(255) NOT NULL,
			service_level varchar(255) DEFAULT '',
			carrier_product_description text DEFAULT '',
			price decimal(10,2) DEFAULT 0.00,
			currency varchar(10) DEFAULT 'EUR',
			carrier_image_url varchar(500) DEFAULT '',
			pickup_date date NULL,
			pickup_before time NULL,
			pickup_after time NULL,
			pickup_address_type varchar(50) DEFAULT '',
			delivery_date date NULL,
			delivery_before time NULL,
			delivery_after time NULL,
			delivery_address_type varchar(50) DEFAULT '',
			modality varchar(50) DEFAULT '',
			is_return tinyint(1) DEFAULT 0,
			is_parcelshop_drop_off tinyint(1) DEFAULT 0,
			includes_ad_hoc_pickup tinyint(1) DEFAULT 0,
			info text DEFAULT '',
			tags text DEFAULT '',
			surcharges text DEFAULT '',
			carrier_product_settings text DEFAULT '',
			enabled tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY carrier_method (carrier_code, carrier_product_code),
			KEY enabled (enabled),
			KEY service_code (service_code)
		) {$charset_collate}";

		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql );
	}
}
