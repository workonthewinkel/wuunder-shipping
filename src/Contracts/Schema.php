<?php

namespace Wuunder\Shipping\Contracts;

/**
 * Class Schema
 *
 * Classes extending Schema manipulate the database with custom queries (like migrations)
 */
abstract class Schema {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	protected string $table_name = '';

	/**
	 * Get the complete table name.
	 */
	protected function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . $this->table_name;
	}

	/**
	 * Check if our table exists, if not, run the schema.
	 */
	public function exists(): bool {
		global $wpdb;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->get_table_name() ) ) ) === $this->get_table_name() ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			return true;
		}

		return false;
	}
}
