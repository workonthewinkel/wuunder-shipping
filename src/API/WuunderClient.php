<?php

namespace Wuunder\Shipping\API;

/**
 * Wuunder API Client for handling all API communications.
 */
class WuunderClient {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private string $api_url = 'https://api.wearewuunder.com';

	/**
	 * API key for authentication.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key API key for authentication.
	 */
	public function __construct( $api_key = '' ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$option_value  = get_option( 'wuunder_api_key', '' );
		$this->api_key = $api_key ? $api_key : ( is_string( $option_value ) ? $option_value : '' );
	}

	/**
	 * Make a request to the Wuunder API.
	 *
	 * @param string               $endpoint API endpoint.
	 * @param string               $method HTTP method.
	 * @param array<string, mixed> $data Request data.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function request( $endpoint, $method = 'GET', array $data = [] ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$url = $this->api_url . $endpoint;

		// Check if API key is set
		if ( empty( $this->api_key ) ) {
			return new \WP_Error(
				'wuunder_no_api_key',
				__( 'API key is not configured', 'wuunder-shipping' )
			);
		}

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
		];

		if ( ! empty( $data ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$body = wp_json_encode( $data );
			if ( $body !== false ) {
				$args['body'] = $body;
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			$decoded = json_decode( $body, true );
			return is_array( $decoded ) ? $decoded : [];
		}

		$error_data    = json_decode( $body, true );
		$error_message = ( is_array( $error_data ) && isset( $error_data['message'] ) ) ? $error_data['message'] : sprintf( 'API request failed with status %d', $code );

		if ( is_array( $error_data ) && isset( $error_data['errors'] ) && is_array( $error_data['errors'] ) ) {
			$error_details = [];
			foreach ( $error_data['errors'] as $field => $messages ) {
				if ( is_array( $messages ) ) {
					$error_details[] = $field . ': ' . implode( ', ', $messages );
				} else {
					$error_details[] = $field . ': ' . $messages;
				}
			}
			if ( ! empty( $error_details ) ) {
				$error_message .= ' - ' . implode( '; ', $error_details );
			}
		}

		return new \WP_Error(
			'wuunder_api_error',
			$error_message,
			[
				'body' => $body,
				'status' => $code,
			]
		);
	}

	/**
	 * Test API connection.
	 *
	 * @return bool
	 */
	public function test_connection(): bool {
		// Test with the new carriers endpoint
		$response = $this->get_carriers();
		return ! is_wp_error( $response );
	}

	/**
	 * Get available carriers.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_carriers() {
		// Use the new GET /api/v2/carriers endpoint
		$response = $this->request( '/api/v2/carriers', 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$carriers = [];

		// Process the new response structure
		if ( isset( $response['carriers'] ) && is_array( $response['carriers'] ) ) {
			foreach ( $response['carriers'] as $carrier ) {
				$carrier_code      = $carrier['code'] ?? '';
				$carrier_name      = $carrier['name'] ?? '';
				$carrier_image_url = $carrier['image_url'] ?? '';

				// Process each carrier product
				if ( isset( $carrier['carrier_products'] ) && is_array( $carrier['carrier_products'] ) ) {
					foreach ( $carrier['carrier_products'] as $product ) {
						$product_code = $product['code'] ?? '';
						$carrier_key  = $carrier_code . ':' . $product_code;
						$parcelshop_delivery = $product['accepts_parcelshop_delivery'] ?? false;

						// Map to our expected structure
						$carriers[ $carrier_key ] = [
							'carrier_code'                 => $carrier_code,
							'carrier_product_code'         => $product_code,
							'accepts_parcelshop_delivery'  => $parcelshop_delivery,
							'service_code'                 => $product['preferred_service_level'] ?? '',
							'carrier_name'                 => $carrier_name,
							'product_name'                 => $product['name'] ?? '',
							'service_level'                => $product['preferred_service_level'] ?? '',
							'carrier_product_description'  => $product['description'] ?? '',
							'price'                        => 0, // Not provided in new endpoint
							'currency'                     => 'EUR',
							'carrier_image_url'            => $carrier_image_url,
							'pickup_date'                  => '',
							'pickup_before'                => '',
							'pickup_after'                 => '',
							'pickup_address_type'          => '',
							'delivery_date'                => '',
							'delivery_before'              => '',
							'delivery_after'               => '',
							'delivery_address_type'        => '',
							'modality'                     => '',
							'is_return'                    => false,
							'is_parcelshop_drop_off'       => false,
							'includes_ad_hoc_pickup'       => false,
							'info'                         => '',
							'tags'                         => '',
							'surcharges'                   => '',
							'carrier_product_settings'     => '',
						];
					}
				}
			}
		}

		return $carriers;
	}
}
