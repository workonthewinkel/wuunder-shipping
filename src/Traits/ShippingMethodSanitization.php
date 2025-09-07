<?php

namespace Wuunder\Shipping\Traits;

/**
 * Trait for common shipping method sanitization methods.
 */
trait ShippingMethodSanitization {

	/**
	 * Sanitize the title.
	 *
	 * @param mixed $value Title value.
	 * @return string Sanitized title.
	 * @throws \Exception If the title is empty.
	 */
	public function sanitize_title( $value ) {
		$value = sanitize_text_field( $value );
		if ( empty( $value ) ) {
			throw new \Exception( esc_html__( 'The title for a shipping method is required.', 'wuunder-shipping' ) );
		}
		return $value;
	}

	/**
	 * Sanitize the cost, inspired by the WooCommerce shipping method sanitize_cost function.
	 *
	 * @param mixed $value Cost value.
	 * @return string Sanitized cost.
	 * @throws \Exception If the cost is invalid.
	 */
	public function sanitize_cost( $value ) {
		$value = is_null( $value ) ? '0' : $value;
		$value = empty( $value ) ? '0' : $value;
		$value = wp_kses_post( trim( wp_unslash( $value ) ) );
		$value = str_replace( array( get_woocommerce_currency_symbol(), html_entity_decode( get_woocommerce_currency_symbol() ) ), '', $value );

		// Get the current locale and all possible decimal separators.
		$locale   = localeconv();
		$decimals = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );

		// Remove whitespace, then decimals, and then invalid start/end characters.
		$value = preg_replace( '/\s+/', '', $value );
		$value = str_replace( $decimals, '.', $value );
		$value = rtrim( ltrim( $value, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

		// If the value is not numeric, then throw an exception.
		if ( ! is_numeric( $value ) ) {
			throw new \Exception( esc_html__( 'Invalid cost entered.', 'wuunder-shipping' ) );
		}
		return $value;
	}
}
