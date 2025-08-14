<?php

namespace Wuunder\Shipping\Contracts;

/**
 * Base Model class for WordPress database operations.
 *
 * This abstract class provides basic database functionality without external dependencies.
 */
abstract class Model {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	protected static string $table = '';

	/**
	 * Get the full table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . static::$table;
	}

	/**
	 * Check if the table exists in the database.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table = static::get_table_name();
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_var( $query ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	}
}
