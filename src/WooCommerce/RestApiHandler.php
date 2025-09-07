<?php

namespace Wuunder\Shipping\WooCommerce;

use Wuunder\Shipping\Contracts\Interfaces\Hookable;

/**
 * Handles REST API customizations for WooCommerce orders.
 */
class RestApiHandler implements Hookable {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Add custom field to WooCommerce REST API order response
		// This filter works for both HPOS and classic order storage
		add_filter( 'woocommerce_rest_prepare_shop_order_object', [ $this, 'add_wuunder_parcelshop_to_response' ], 10, 3 );
	}

	/**
	 * Add wuunder_parcelshop_id to the order REST API response.
	 * Works with both HPOS (High-Performance Order Storage) and classic order storage.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WC_Order         $order    The order object (not WP_Post, works with HPOS).
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response
	 */
	public function add_wuunder_parcelshop_to_response( $response, $order, $request ) {
		if ( empty( $response->data ) ) {
			return $response;
		}

		// Get the parcelshop ID from the order
		$parcelshop_id = $this->get_wuunder_parcelshop_id_from_order( $order );

		// Add it as a top-level property in the response
		$response->data['wuunder_parcelshop_id'] = $parcelshop_id;

		return $response;
	}

	/**
	 * Get the Wuunder parcelshop ID from order shipping metadata.
	 *
	 * @param \WC_Order $order Order object (works with both HPOS and classic storage).
	 * @return string|null Parcelshop ID or null if not found.
	 */
	private function get_wuunder_parcelshop_id_from_order( $order ): ?string {
		// Get shipping methods
		$shipping_methods = $order->get_shipping_methods();

		foreach ( $shipping_methods as $shipping_method ) {
			// Check if this is a Wuunder pickup method
			if ( strpos( $shipping_method->get_method_id(), 'wuunder_pickup' ) !== false ) {
				// Get the parcelshop ID from internal meta key (consistent with other pickup point fields)
				$parcelshop_id = $shipping_method->get_meta( 'pickup_point_id' );
				
				if ( $parcelshop_id ) {
					return $parcelshop_id;
				}
			}
		}

		return null;
	}
}