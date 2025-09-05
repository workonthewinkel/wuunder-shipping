<?php

namespace Wuunder\Shipping\WooCommerce\Methods;

use WC_Shipping_Method;
use Wuunder\Shipping\Models\Carrier;

/**
 * Simple Wuunder shipping method that uses instance settings.
 */
class Shipping extends WC_Shipping_Method {

	/**
	 * Wuunder carrier data.
	 *
	 * @var array<string, mixed>
	 */
	public array $wuunder_carrier_data = [];

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->id           = 'wuunder_shipping';
		$this->instance_id  = absint( $instance_id );
		$this->method_title = __( 'Wuunder Shipping', 'wuunder-shipping' );

		// Build dynamic link to Wuunder Settings - carriers tab
		$settings_url             = admin_url( 'admin.php?page=wc-settings&tab=wuunder&section=carriers' );
		$this->method_description = sprintf(
			/* translators: %s: URL to Wuunder settings page */
			__( 'Shipping method provided by <a href="%s"><strong>Wuunder</strong></a>', 'wuunder-shipping' ),
			$settings_url
		);

		$this->supports = [
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		];

		$this->init();
	}

	/**
	 * Initialize the shipping method.
	 */
	public function init(): void {
		$this->init_form_fields();

		// Get instance-specific title with carrier name
		$this->title = $this->get_option( 'title' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void {
		$this->instance_form_fields = [
			'title' => [
				'title' => __( 'Method Title', 'wuunder-shipping' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wuunder-shipping' ),
				'default' => '',
				'desc_tip' => true,
				'sanitize_callback' => array( $this, 'sanitize_title' ),
			],
			'wuunder_carrier' => [
				'title' => __( 'Carrier', 'wuunder-shipping' ),
				'type' => 'select',
				'description' => __( 'Select which Wuunder carrier this method should use.', 'wuunder-shipping' ),
				'options' => $this->get_available_carriers(),
				'desc_tip' => true,
				'sanitize_callback' => array( $this, 'sanitize_carrier' ),
			],
			'cost' => [
				'title' => __( 'Cost', 'wuunder-shipping' ),
				'type' => 'text',
				'placeholder' => '0',
				'description' => __( 'Enter a cost (excluding tax).', 'wuunder-shipping' ),
				'default' => '',
				'desc_tip' => true,
				'sanitize_callback' => array( $this, 'sanitize_cost' ),
			],
		];
	}

	/**
	 * Get available carriers for the dropdown.
	 *
	 * @return array<string, string>
	 */
	private function get_available_carriers(): array {
		$carriers = Carrier::get_all( true ); // Get only enabled carriers
		$options  = [ '' => __( 'Select a carrier...', 'wuunder-shipping' ) ];

		foreach ( $carriers as $carrier ) {
			// Use product_name as primary label, fallback to carrier_name
			$label = ! empty( $carrier->product_name ) ? $carrier->product_name : $carrier->carrier_name;

			$options[ $carrier->get_method_id() ] = $label;
		}

		return $options;
	}

	/**
	 * Calculate shipping rate.
	 *
	 * @param array<string, mixed> $package Package data.
	 */
	public function calculate_shipping( $package = [] ): void {
		$carrier_key = $this->get_option( 'wuunder_carrier' );

		$label = $this->get_option( 'title' );
		$cost  = $this->get_option( 'cost' );

		$rate = [
			'id' => $this->get_rate_id(),
			'label' => $label,
			'cost' => $cost,
			'calc_tax' => 'per_order',
			'meta_data' => [
				'wuunder_method_id' => $carrier_key,
			],
		];

		$this->add_rate( $rate );
	}

	/**
	 * Sanitize the title by checking if the title is empty.
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
	 * Sanitize the carrier by checking if the carrier value exists in the database.
	 *
	 * @param mixed $value Carrier value.
	 * @return string Sanitized carrier.
	 * @throws \Exception If the carrier is not valid.
	 */
	public function sanitize_carrier( $value ) {
		$carrier = Carrier::find_by_method_id( $value );
		if ( ! $carrier ) {
			throw new \Exception( esc_html__( 'The selected carrier is not valid.', 'wuunder-shipping' ) );
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
