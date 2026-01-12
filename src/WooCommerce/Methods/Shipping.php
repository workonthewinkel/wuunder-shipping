<?php

namespace Wuunder\Shipping\WooCommerce\Methods;

use WC_Shipping_Method;
use Wuunder\Shipping\Models\Carrier;
use Wuunder\Shipping\Traits\NoCarriersNotice;
use Wuunder\Shipping\Traits\ShippingMethodSanitization;

/**
 * Simple Wuunder shipping method that uses instance settings.
 */
class Shipping extends WC_Shipping_Method {

	use NoCarriersNotice;
	use ShippingMethodSanitization;

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
		$this->method_title = __( 'Wuunder Delivery', 'wuunder-shipping' );

		$this->supports = [
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		];

		$this->init();

		// Set method description after init to check carrier status
		$this->set_method_description();
	}

	/**
	 * Initialize the shipping method.
	 */
	public function init(): void {
		$this->init_form_fields();

		// Get instance-specific title with carrier name
		$this->title = $this->get_option( 'title', __( 'Wuunder Shipping', 'wuunder-shipping' ) );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	/**
	 * Set method description based on carrier status.
	 */
	private function set_method_description(): void {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=wuunder&section=shipping_methods' );
		$carrier_id   = $this->get_option( 'wuunder_carrier', '' );

		$this->method_description = sprintf(
			/* translators: %s: URL to Wuunder settings page */
			__( 'Shipping method provided by <a href="%s"><strong>Wuunder</strong></a>', 'wuunder-shipping' ),
			$settings_url
		);

		if ( $this->is_carrier_disabled( $carrier_id ) ) {
			$this->method_description .= "\n <i>" . __( '(this shipping carrier is disabled)', 'wuunder-shipping' ) . '</i>';
		}
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void {
		$selected_carrier    = $this->get_option( 'wuunder_carrier', '' );
		$is_carrier_disabled = $this->is_carrier_disabled( $selected_carrier );

		// If carrier is disabled, prevent enabling the shipping method
		if ( $is_carrier_disabled ) {
			$enabled_field['description']       = __( 'Enable this shipping method (this shipping carrier is disabled)', 'wuunder-shipping' );
			$enabled_field['disabled']          = true;
			$enabled_field['custom_attributes'] = [ 'readonly' => 'readonly' ];
		}

		$available_carriers = $this->get_available_carriers();
		$has_carriers       = count( $available_carriers ) > 1; // More than just the "Select a carrier..." option

		// If no carriers available, only show message with link to manage carriers
		if ( ! $has_carriers ) {
			$this->instance_form_fields = $this->get_no_carriers_notice_fields( 'shipping_methods' );
			return;
		}

		$this->instance_form_fields = [
			'wuunder_carrier' => [
				'title' => __( 'Carrier', 'wuunder-shipping' ),
				'type' => 'select',
				'description' => __( 'Select which Wuunder carrier this method should use.', 'wuunder-shipping' ),
				'options' => $available_carriers,
				'desc_tip' => true,
				'sanitize_callback' => array( $this, 'sanitize_carrier' ),
			],
			'title' => [
				'title' => __( 'Method Title', 'wuunder-shipping' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wuunder-shipping' ),
				'default' => '',
				'desc_tip' => true,
				'sanitize_callback' => array( $this, 'sanitize_title' ),
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
		$carriers = Carrier::get_standard_carriers( true ); // Get only enabled carriers that do not accept parcelshop delivery
		$options  = [ '' => __( 'Select a carrier...', 'wuunder-shipping' ) ];

		foreach ( $carriers as $carrier ) {
			// Use product_name as primary label, fallback to carrier_name
			$label = ! empty( $carrier->product_name ) ? $carrier->product_name : $carrier->carrier_name;

			$options[ $carrier->get_method_id() ] = $label;
		}

		return $options;
	}

	/**
	 * Check if a carrier is disabled.
	 *
	 * @param string $carrier_id Carrier method ID.
	 * @return bool True if carrier is disabled, false otherwise.
	 */
	private function is_carrier_disabled( $carrier_id ) {
		if ( empty( $carrier_id ) ) {
			return false;
		}

		$carrier = Carrier::find_by_method_id( $carrier_id );
		return $carrier ? ! $carrier->enabled : true;
	}

	/**
	 * Process admin options and validate carrier status.
	 *
	 * @return bool
	 */
	public function process_admin_options(): bool {
		$result = parent::process_admin_options();

		// Check if trying to enable a method with disabled carrier
		$enabled    = $this->get_option( 'enabled', 'yes' );
		$carrier_id = $this->get_option( 'wuunder_carrier', '' );

		if ( $enabled === 'yes' && $this->is_carrier_disabled( $carrier_id ) ) {
			// Force disable the method
			$this->update_option( 'enabled', 'no' );

			// Add an admin notice
			\WC_Admin_Settings::add_error(
				__( 'The shipping method could not be enabled because the selected carrier is disabled in Wuunder settings.', 'wuunder-shipping' )
			);
		}

		return $result;
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

		// Get the carrier data to access service_level
		$carrier       = Carrier::find_by_method_id( $carrier_key );
		$service_level = $carrier ? $carrier->service_level : '';

		$rate = [
			'id' => $this->get_rate_id(),
			'label' => $label,
			'cost' => $cost,
			'calc_tax' => 'per_order',
			'meta_data' => [
				'service_code' => $carrier_key,
				'preferred_service_level' => $service_level,
			],
		];

		$this->add_rate( $rate );
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
}
