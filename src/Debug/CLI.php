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
		$count = $wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		\WP_CLI::log( "  Truncated table: {$table}" );

		// Remove Wuunder shipping methods from zones.
		$this->remove_shipping_methods();

		\WP_CLI::success( 'All Wuunder data has been cleared.' );
	}

	/**
	 * Remove Wuunder shipping methods from all zones.
	 */
	private function remove_shipping_methods(): void {
		$zones   = \WC_Shipping_Zones::get_zones();
		$zones[] = \WC_Shipping_Zones::get_zone_by( 'zone_id', 0 ); // Rest of World.
		$removed = 0;

		foreach ( $zones as $zone_data ) {
			if ( is_array( $zone_data ) ) {
				$zone = \WC_Shipping_Zones::get_zone( $zone_data['id'] );
			} else {
				$zone = $zone_data;
			}

			if ( ! $zone ) {
				continue;
			}

			foreach ( $zone->get_shipping_methods() as $instance_id => $method ) {
				if ( in_array( $method->id, [ 'wuunder_shipping', 'wuunder_pickup' ], true ) ) {
					$zone->delete_shipping_method( $instance_id );
					++$removed;
				}
			}
		}

		if ( $removed > 0 ) {
			\WP_CLI::log( "  Removed {$removed} Wuunder shipping method(s) from zones" );
		}
	}
}
