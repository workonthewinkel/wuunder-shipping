<?php

namespace Wuunder\Shipping\Debug;

use Wuunder\Shipping\Models\Carrier;


/**
 * WP-CLI commands for Wuunder Shipping
 */
class CLI {

	/**
	 * Register CLI commands
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'wuunder', self::class );
	}

	/**
	 * Clear all Wuunder settings and data.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear all Wuunder data
	 *     $ wp wuunder clear
	 *
	 *     # Clear without confirmation
	 *     $ wp wuunder clear --yes
	 *
	 * @when after_wp_load
	 */
	public function clear( $args, $assoc_args ): void {
		$yes = \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		if ( ! $yes ) {
			\WP_CLI::confirm( 'This will delete all Wuunder settings, carriers, and related data. Are you sure?' );
		}

		\WP_CLI::log( 'Clearing Wuunder data...' );

		// Options to delete.
		$options = [
			'wuunder_api_key',
			'wuunder_carriers_last_update',
			'wuunder_plugin_version',
		];

		foreach ( $options as $option ) {
			if ( delete_option( $option ) ) {
				\WP_CLI::log( "  Deleted option: {$option}" );
			}
		}

		// Transients to delete.
		delete_transient( 'wuunder_activation_redirect' );
		\WP_CLI::log( '  Deleted transient: wuunder_activation_redirect' );

		// Clear carriers table.
		global $wpdb;
		$table = Carrier::get_table_name();
		$count = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'TRUNCATE TABLE %i', $table )
		);
		\WP_CLI::log( "  Truncated table: {$table}" );

		\WP_CLI::success( 'All Wuunder data has been cleared.' );
	}

	/**
	 * Delete all shop orders.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete all shop orders
	 *     $ wp wuunder delete_orders
	 *
	 *     # Delete without confirmation
	 *     $ wp wuunder delete_orders --yes
	 *
	 * @when after_wp_load
	 */
	public function delete_orders( $args, $assoc_args ): void {
		$yes = \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		$orders = wc_get_orders(
			[
				'limit'  => -1,
				'return' => 'ids',
			]
		);

		$count = count( $orders );

		if ( 0 === $count ) {
			\WP_CLI::success( 'No orders to delete.' );
			return;
		}

		if ( ! $yes ) {
			\WP_CLI::confirm( "This will permanently delete {$count} order(s). Are you sure?" );
		}

		\WP_CLI::log( "Deleting {$count} order(s)..." );

		$deleted = 0;
		$failed  = 0;

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order && $order->delete( true ) ) {
				++$deleted;
				\WP_CLI::log( "  Deleted order #{$order_id}" );
			} else {
				++$failed;
				\WP_CLI::warning( "  Failed to delete order #{$order_id}" );
			}
		}

		if ( $failed > 0 ) {
			\WP_CLI::warning( "Deleted {$deleted} order(s), {$failed} failed." );
		} else {
			\WP_CLI::success( "Deleted {$deleted} order(s)." );
		}
	}
}
