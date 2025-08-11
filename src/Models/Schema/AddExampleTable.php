<?php

namespace Wuunder\Shipping\Models\Schema;

use Wuunder\Shipping\Contracts\Schema;

/**
 * Class AddExampleTable
 *
 * Represents an example table. Doesn't do anything.
 */
class AddExampleTable extends Schema {

	/**
	 * Define the table name.
	 */
	protected string $table_name = 'wooping_examples';


	/**
	 * Run the query that will add our table
	 *
	 * @return void
	 */
	public function run(): void {
		global $wpdb;
		$charset_collate            = $wpdb->get_charset_collate();
		$table_name                 = \esc_sql( $this->get_table_name() );

		$query = "CREATE TABLE `$table_name` (
            id int NOT NULL AUTO_INCREMENT,
            customer_id int DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate";

		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';


		\dbDelta( $query );
	}
}
