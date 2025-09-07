<?php

namespace Wuunder\Shipping\WooCommerce;

use Wuunder\Shipping\Contracts\Interfaces\Hookable;

/**
 * Register class for dynamically registering carrier-specific shipping methods.
 */
class Register implements Hookable {


	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_shipping_methods', [ $this, 'register_shipping_methods' ] );
	}

	/**
	 * Register all enabled Wuunder carriers as separate shipping methods.
	 *
	 * @param array<string, string> $methods Existing shipping methods.
	 * @return array<string, string>
	 */
	public function register_shipping_methods( array $methods ): array {
		// Register our single shipping method that can handle all carriers
		$methods['wuunder_shipping'] = Methods\Shipping::class;

		// Register the pick-up point shipping method
		$methods['wuunder_pickup'] = Methods\Pickup::class;

		return $methods;
	}
}
