<?php

namespace Wuunder\Shipping\Services;

use Wuunder\Shipping\API\WuunderClient;
use Wuunder\Shipping\Models\Carrier;

/**
 * Service for managing carrier operations.
 */
class CarrierService {

	/**
	 * Refresh carriers from the Wuunder API.
	 *
	 * @param bool $preserve_enabled Whether to preserve enabled state for existing carriers.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function refresh_from_api( bool $preserve_enabled = false ) {

		$api_key = get_option( 'wuunder_api_key', '' );

		// Return early if no API key is configured.
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'No API key configured.', 'wuunder-shipping' ) );
		}

		$client       = new WuunderClient( $api_key );
		$api_carriers = $client->get_carriers();

		if ( is_wp_error( $api_carriers ) ) {
			return $api_carriers;
		}

		// Get existing carriers to optionally preserve enabled state
		$existing_carriers = [];
		foreach ( Carrier::get_all() as $carrier ) {
			$existing_carriers[ $carrier->get_method_id() ] = $carrier->enabled;
		}

		// Save carriers from API
		foreach ( $api_carriers as $key => $carrier_data ) {
			$carrier = new Carrier();

			// Map all carrier data fields
			$carrier->carrier_code                = $carrier_data['carrier_code'] ?? '';
			$carrier->carrier_product_code        = $carrier_data['carrier_product_code'] ?? '';
			$carrier->service_code                = $carrier_data['service_code'] ?? '';
			$carrier->carrier_name                = $carrier_data['carrier_name'] ?? '';
			$carrier->product_name                = $carrier_data['product_name'] ?? '';
			$carrier->service_level               = $carrier_data['service_level'] ?? '';
			$carrier->carrier_product_description = $carrier_data['carrier_product_description'] ?? '';
			$carrier->price                       = $carrier_data['price'] ?? 0.0;
			$carrier->currency                    = $carrier_data['currency'] ?? 'EUR';
			$carrier->carrier_image_url           = $carrier_data['carrier_image_url'] ?? '';
			$carrier->pickup_date                 = $carrier_data['pickup_date'] ?? '';
			$carrier->pickup_before               = $carrier_data['pickup_before'] ?? '';
			$carrier->pickup_after                = $carrier_data['pickup_after'] ?? '';
			$carrier->pickup_address_type         = $carrier_data['pickup_address_type'] ?? '';
			$carrier->delivery_date               = $carrier_data['delivery_date'] ?? '';
			$carrier->delivery_before             = $carrier_data['delivery_before'] ?? '';
			$carrier->delivery_after              = $carrier_data['delivery_after'] ?? '';
			$carrier->delivery_address_type       = $carrier_data['delivery_address_type'] ?? '';
			$carrier->modality                    = $carrier_data['modality'] ?? '';
			$carrier->is_return                   = $carrier_data['is_return'] ?? false;
			$carrier->is_parcelshop_drop_off      = $carrier_data['is_parcelshop_drop_off'] ?? false;
			$carrier->accepts_parcelshop_delivery = $carrier_data['accepts_parcelshop_delivery'] ?? false;
			$carrier->includes_ad_hoc_pickup      = $carrier_data['includes_ad_hoc_pickup'] ?? false;
			$carrier->info                        = $carrier_data['info'] ?? '';
			$carrier->tags                        = is_array( $carrier_data['tags'] ?? '' ) ? wp_json_encode( $carrier_data['tags'] ) : ( $carrier_data['tags'] ?? '' );
			$carrier->surcharges                  = is_array( $carrier_data['surcharges'] ?? '' ) ? wp_json_encode( $carrier_data['surcharges'] ) : ( $carrier_data['surcharges'] ?? '' );
			$carrier->carrier_product_settings    = is_array( $carrier_data['carrier_product_settings'] ?? '' ) ? wp_json_encode( $carrier_data['carrier_product_settings'] ) : ( $carrier_data['carrier_product_settings'] ?? '' );

			// Preserve enabled state if requested and it existed
			if ( $preserve_enabled && isset( $existing_carriers[ $key ] ) ) {
				$carrier->enabled = $existing_carriers[ $key ];
			} else {
				$carrier->enabled = true; // Enable new methods by default
			}

			$carrier->save();
		}

		// After looping through all new carriers, loop through the old ones and see which we need to delete:
		$existing_carrier_ids = array_keys( $existing_carriers );
		foreach( $existing_carrier_ids as $carrier_id ){

			// if this existing carrier was not sent over by the api:
			if( !isset( $api_carriers[ $carrier_id ] ) ){
				static::handle_carrier_deletion( $carrier_id );
			}

		}

		return true;
	}

	/**
	 * Handle the deletion of a single carrier:
	 * - Delete the carrier from the database
	 * - Delete any WooCommerce shipping method instances that use this carrier
	 *
	 * @param string $carrier_id The carrier method ID (format: carrier_code:carrier_product_code).
	 * @return void
	 */
	public static function handle_carrier_deletion( string $carrier_id ): void {
		// Load the actual Carrier object to delete it
		$carrier = Carrier::find_by_method_id( $carrier_id );
		
		if ( ! $carrier ) {
			// Carrier doesn't exist, nothing to delete
			return;
		}

		// Disable associated WooCommerce shipping method instances
		static::disable_shipping_method_instances( [ $carrier_id ] );

		// Delete the carrier from the database
		$carrier->delete();
	}

	/**
	 * Disable WooCommerce shipping method instances associated with unavailable carriers.
	 *
	 * For wuunder_shipping: disables the method.
	 * For wuunder_pickup: removes the carrier from available_carriers, disables if none remain.
	 *
	 * @param array $carrier_ids Array of carrier method IDs (carrier_code:carrier_product_code).
	 * @return void
	 */
	public static function disable_shipping_method_instances( array $carrier_ids ): void {
		// Extract carrier codes for pickup method checks
		$carrier_codes = [];
		foreach ( $carrier_ids as $carrier_id ) {
			$carrier = Carrier::find_by_method_id( $carrier_id );
			if ( $carrier ) {
				$carrier_codes[] = $carrier->carrier_code;
			}
		}
		$carrier_codes = array_unique( $carrier_codes );

		// Get all shipping zones
		$zones = \WC_Shipping_Zones::get_zones();
		$zones[0] = \WC_Shipping_Zones::get_zone_by( 'zone_id', 0 ); // Add 'Rest of the World' zone

		foreach ( $zones as $zone ) {
			if ( is_array( $zone ) ) {
				$zone_obj = \WC_Shipping_Zones::get_zone( $zone['id'] );
				$zone_id = $zone['id'];
			} else {
				$zone_obj = $zone;
				$zone_id = $zone_obj->get_id();
			}

			if ( ! $zone_obj ) {
				continue;
			}

			$shipping_methods = $zone_obj->get_shipping_methods();

			foreach ( $shipping_methods as $instance_id => $shipping_method ) {
				// Check wuunder_shipping methods
				if ( $shipping_method->id === 'wuunder_shipping' ) {
					$carrier_option = $shipping_method->get_option( 'wuunder_carrier', '' );

					// Disable if no carrier is configured or if the carrier is disabled
					if ( empty( $carrier_option ) || in_array( $carrier_option, $carrier_ids, true ) ) {
						static::disable_shipping_method( $shipping_method, $instance_id, $zone_id );
					}
				}

				// Check wuunder_pickup methods
				if ( $shipping_method->id === 'wuunder_pickup' ) {
					$available_carriers = $shipping_method->get_option( 'available_carriers', [] );

					if ( ! is_array( $available_carriers ) || empty( $available_carriers ) ) {
						static::disable_shipping_method( $shipping_method, $instance_id, $zone_id );
						continue;
					}

					// Filter out unavailable carriers
					$filtered_carriers = array_values( array_diff( $available_carriers, $carrier_codes ) );

					// If carriers were removed, update the option
					if ( count( $filtered_carriers ) !== count( $available_carriers ) ) {
						$shipping_method->update_option( 'available_carriers', $filtered_carriers );

						// If no carriers remain, disable the method
						if ( empty( $filtered_carriers ) ) {
							static::disable_shipping_method( $shipping_method, $instance_id, $zone_id );
						}
					}
				}
			}
		}
	}

	/**
	 * Disable a single shipping method instance.
	 *
	 * @param \WC_Shipping_Method $shipping_method The shipping method instance.
	 * @param int                 $instance_id     The instance ID.
	 * @param int                 $zone_id         The zone ID.
	 * @return void
	 */
	public static function disable_shipping_method( \WC_Shipping_Method $shipping_method, int $instance_id, int $zone_id ): void {
		global $wpdb;

		$shipping_method->update_option( 'enabled', 'no' );

		// Follow WooCommerce core pattern: update database directly
		if ( $wpdb->update( "{$wpdb->prefix}woocommerce_shipping_zone_methods", array( 'is_enabled' => 0 ), array( 'instance_id' => absint( $instance_id ) ) ) ) {
			do_action( 'woocommerce_shipping_zone_method_status_toggled', $instance_id, $shipping_method->id, $zone_id, 0 );
		}
	}

	/**
	 * Get settings for all pickup method instances across all zones.
	 *
	 * @return array Array of settings keyed by instance ID.
	 */
	public static function get_pickup_method_settings(): array {
		$settings = [];

		// Get all shipping zones
		$zones    = \WC_Shipping_Zones::get_zones();
		$zones[0] = \WC_Shipping_Zones::get_zone( 0 ); // Add default zone

		foreach ( $zones as $zone ) {
			if ( is_array( $zone ) ) {
				$zone = \WC_Shipping_Zones::get_zone( $zone['zone_id'] );
			}

			$shipping_methods = $zone->get_shipping_methods();

			foreach ( $shipping_methods as $method ) {
				if ( $method->id === 'wuunder_pickup' ) {
					$settings[ $method->get_instance_id() ] = [
						'primary_color'      => $method->get_option( 'primary_color', '#52ba69' ),
						'available_carriers' => $method->get_option( 'available_carriers', [] ),
						'language'           => $method->get_option( 'language', 'nl' ),
					];
				}
			}
		}

		return $settings;
	}
}
