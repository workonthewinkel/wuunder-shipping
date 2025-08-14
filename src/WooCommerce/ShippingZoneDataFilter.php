<?php

namespace Wuunder\Shipping\WooCommerce;

use Wuunder\Shipping\Contracts\Interfaces\Hookable;
use Wuunder\Shipping\Models\Carrier;

/**
 * Filters shipping zone data to include Wuunder-specific information for JavaScript.
 */
class ShippingZoneDataFilter implements Hookable {


	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		// \add_filter( 'woocommerce_shipping_zone_shipping_methods', [ $this, 'add_carrier_data_to_methods' ], 10, 4 );
	}

	/**
	 * Modify Wuunder shipping method titles and add carrier data.
	 *
	 * @param array $methods Array of shipping method instances.
	 * @return array Modified methods array.
	 */
	public function add_carrier_data_to_methods( array $methods ): array {
		foreach ( $methods as $method ) {
			if ( $method->id === 'wuunder_shipping' ) {
				$carrier_key = $method->instance_settings['wuunder_carrier'] ?? '';

				if ( ! empty( $carrier_key ) ) {
					$carrier = Carrier::find_by_method_id( $carrier_key );

					if ( $carrier ) {
						// Prioritize product_name as the user-friendly delivery method title
						$product_name = $carrier->product_name ?? '';
						$carrier_name = $carrier->carrier_name ?? '';

						// Set the title (product_name is preferred)
						if ( ! empty( $carrier_name ) && ! empty( $product_name ) ) {
							$title = $carrier_name . ' - ' . $product_name;
						} elseif ( ! empty( $product_name ) ) {
							$title = $product_name;
						} else {
							$title = $carrier_name;
						}

						if ( ! empty( $title ) ) {
							$method->method_title = $title;
							$method->title        = $title;
						}
					}
				}
			}
		}

		return $methods;
	}
}
