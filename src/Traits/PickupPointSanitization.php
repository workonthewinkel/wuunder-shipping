<?php

namespace Wuunder\Shipping\Traits;

/**
 * Trait for pickup point data sanitization methods.
 */
trait PickupPointSanitization {

	/**
	 * Sanitize pickup point data array.
	 *
	 * @param array $pickup_point Pickup point data to sanitize.
	 * @return array Sanitized pickup point data.
	 */
	protected function sanitize_pickup_point_data( $pickup_point ): array {
		if ( ! is_array( $pickup_point ) ) {
			return [];
		}

		$sanitized = [];

		// Sanitize string fields
		$string_fields = [
			'id',
			'name',
			'street',
			'street_name',
			'house_number',
			'postcode',
			'city',
			'country',
			'carrier',
		];

		foreach ( $string_fields as $field ) {
			if ( isset( $pickup_point[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $pickup_point[ $field ] );
			}
		}

		// Sanitize opening_hours array
		if ( isset( $pickup_point['opening_hours'] ) && is_array( $pickup_point['opening_hours'] ) ) {
			$sanitized['opening_hours'] = $this->sanitize_opening_hours( $pickup_point['opening_hours'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize opening hours array recursively.
	 *
	 * @param array $opening_hours Opening hours data to sanitize.
	 * @return array Sanitized opening hours data.
	 */
	protected function sanitize_opening_hours( $opening_hours ): array {
		if ( ! is_array( $opening_hours ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $opening_hours as $key => $value ) {
			$sanitized_key = sanitize_text_field( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = $this->sanitize_opening_hours( $value );
			} else {
				$sanitized[ $sanitized_key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}
}
