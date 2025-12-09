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
				$carrier->enabled = false; // Start with disabled by default
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

		// Extract carrier_code for pickup method checks
		$carrier_code = $carrier->carrier_code;

		// Delete associated WooCommerce shipping method instances
		static::delete_associated_shipping_methods( $carrier_id, $carrier_code );

		// Delete the carrier from the database
		$carrier->delete();
	}

	/**
	 * Delete WooCommerce shipping method instances associated with a carrier.
	 *
	 * @param string $carrier_id The full carrier method ID (carrier_code:carrier_product_code).
	 * @param string $carrier_code The carrier code (for pickup method checks).
	 * @return void
	 */
	private static function delete_associated_shipping_methods( string $carrier_id, string $carrier_code ): void {
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
			$methods_to_delete = [];

			foreach ( $shipping_methods as $instance_id => $shipping_method ) {
				// Check wuunder_shipping methods
				if ( $shipping_method->id === 'wuunder_shipping' ) {
					$carrier_option = $shipping_method->get_option( 'wuunder_carrier', '' );
					
					// If this method uses the deleted carrier, mark it for deletion
					if ( $carrier_option === $carrier_id ) {
						$methods_to_delete[] = $instance_id;
					}
				}

				// Check wuunder_pickup methods
				if ( $shipping_method->id === 'wuunder_pickup' ) {
					$available_carriers = $shipping_method->get_option( 'available_carriers', [] );
					
					// If the deleted carrier is in the available carriers list, delete the method
					if ( is_array( $available_carriers ) && in_array( $carrier_code, $available_carriers, true ) ) {
						$methods_to_delete[] = $instance_id;
					}
				}
			}

			// Delete the marked methods
			foreach ( $methods_to_delete as $instance_id ) {
				$zone_obj->delete_shipping_method( $instance_id );
			}
		}
	}
}
