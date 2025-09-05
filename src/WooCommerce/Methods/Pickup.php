<?php

namespace Wuunder\Shipping\WooCommerce\Methods;

use WC_Shipping_Method;
use Wuunder\Shipping\Traits\ShippingMethodSanitization;

/**
 * Wuunder Pick-up shipping method for parcel shop locator.
 */
class Pickup extends WC_Shipping_Method {

	use ShippingMethodSanitization;

	/**
	 * Iframe base URL for pickup point locator.
	 */
	public const IFRAME_BASE_URL = 'https://my.wearewuunder.com/parcelshop_locator/iframe';

	/**
	 * Iframe origin for security verification.
	 */
	public const IFRAME_ORIGIN = 'https://my.wearewuunder.com';

	/**
	 * Get iframe configuration for JavaScript.
	 *
	 * @return array<string, string> Configuration array with base_url and origin.
	 */
	public static function get_iframe_config(): array {
		return [
			'base_url' => self::IFRAME_BASE_URL,
			'origin' => self::IFRAME_ORIGIN,
		];
	}

	/**
	 * Available carriers for pick-up points.
	 *
	 * @var array<string, string>
	 */
	private array $available_carriers = [
		'dhl' => 'DHL',
		'postnl' => 'PostNL',
		'ups' => 'UPS',
	];

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id           = 'wuunder_pickup';
		$this->instance_id  = absint( $instance_id );
		$this->method_title = __( 'Wuunder Pick-up', 'wuunder-shipping' );

		// Build dynamic link to Wuunder Settings - carriers tab
		$settings_url             = admin_url( 'admin.php?page=wc-settings&tab=wuunder&section=carriers' );
		$this->method_description = sprintf(
			/* translators: %s: URL to Wuunder settings page */
			__( 'Pick-up point shipping method provided by <a href="%s"><strong>Wuunder</strong></a>', 'wuunder-shipping' ),
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

		// Get instance-specific title
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
				'default' => __( 'Pick-up at parcel shop', 'wuunder-shipping' ),
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
			'available_carriers' => [
				'title' => __( 'Available Carriers', 'wuunder-shipping' ),
				'type' => 'carrier_checkboxes',
				'description' => __( 'Select which carriers should be available for pick-up points.', 'wuunder-shipping' ),
				'options' => $this->get_carrier_options(),
				'default' => array_keys( $this->available_carriers ),
				'desc_tip' => true,
				'sanitize_callback' => array( $this, 'sanitize_carriers' ),
			],
			'primary_color' => [
				'title' => __( 'Primary Color', 'wuunder-shipping' ),
				'type' => 'wuunder_color',
				'description' => __( 'Primary color for the shop locator.', 'wuunder-shipping' ),
				'default' => '#52ba69',
				'desc_tip' => true,
				'sanitize_callback' => array( $this, 'sanitize_color' ),
			],
			'language' => [
				'title' => __( 'Language', 'wuunder-shipping' ),
				'type' => 'select',
				'description' => __( 'Language for the shop locator interface.', 'wuunder-shipping' ),
				'options' => [
					'nl' => __( 'Dutch', 'wuunder-shipping' ),
					'en' => __( 'English', 'wuunder-shipping' ),
					'de' => __( 'German', 'wuunder-shipping' ),
					'fr' => __( 'French', 'wuunder-shipping' ),
				],
				'default' => 'nl',
				'desc_tip' => true,
			],
		];
	}

	/**
	 * Generate carrier checkboxes HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_carrier_checkboxes_html( $key, $data ): string {
		$field_key = $this->get_field_key( $key );
		$defaults  = $this->get_option( $key, $data['default'] ?? [] );
		$options   = $data['options'] ?? [];

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<?php foreach ( $options as $option_key => $option_value ) : ?>
						<label for="<?php echo esc_attr( $field_key . '_' . $option_key ); ?>">
							<input 
								type="checkbox" 
								name="<?php echo esc_attr( $field_key ); ?>[]" 
								id="<?php echo esc_attr( $field_key . '_' . $option_key ); ?>" 
								value="<?php echo esc_attr( $option_key ); ?>"
								<?php checked( in_array( $option_key, $defaults, true ) ); ?>
							/>
							<?php echo esc_html( $option_value ); ?>
						</label><br/>
					<?php endforeach; ?>
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get carrier options for multiselect.
	 *
	 * @return array<string, string>
	 */
	private function get_carrier_options(): array {
		return $this->get_available_carriers();
	}

	/**
	 * Get available carriers.
	 * This method can be extended in the future to load carriers dynamically.
	 *
	 * @return array<string, string>
	 */
	public function get_available_carriers(): array {
		return apply_filters( 'wuunder_pickup_available_carriers', $this->available_carriers );
	}

	/**
	 * Build iframe URL for shop locator.
	 *
	 * @param string $address Address to center the map on.
	 * @param array  $carriers Selected carriers.
	 * @param string $color Primary color (hex).
	 * @param string $language Language code.
	 * @return string
	 */
	public function build_iframe_url( $address, array $carriers, $color, $language ): string {
		$base_url = self::IFRAME_BASE_URL;

		// Remove # from color if present
		$color = ltrim( $color, '#' );

		$params = [
			'address' => $address,
			'availableCarriers' => implode( ',', $carriers ),
			'primary_color' => $color,
			'language' => $language,
		];

		return add_query_arg( $params, $base_url );
	}

	/**
	 * Calculate shipping rate.
	 *
	 * @param array<string, mixed> $package Package data.
	 */
	public function calculate_shipping( $package = [] ): void {
		$label = $this->get_option( 'title' );
		$cost  = $this->get_option( 'cost' );

		$meta_data = [];

		// Add pickup point info if available in session
		$pickup_point = WC()->session ? WC()->session->get( 'wuunder_selected_pickup_point' ) : null;
		if ( $pickup_point && is_array( $pickup_point ) ) {
			// Always save the ID and name if available
			if ( ! empty( $pickup_point['id'] ) ) {
				$meta_data['pickup_point_id'] = $pickup_point['id'];
			}
			if ( ! empty( $pickup_point['name'] ) ) {
				$meta_data['pickup_point_name'] = $pickup_point['name'];
			}

			// Save address components
			if ( ! empty( $pickup_point['street'] ) ) {
				$meta_data['pickup_point_street'] = $pickup_point['street'];
			}
			if ( ! empty( $pickup_point['street_name'] ) ) {
				$meta_data['pickup_point_street_name'] = $pickup_point['street_name'];
			}
			if ( ! empty( $pickup_point['house_number'] ) ) {
				$meta_data['pickup_point_house_number'] = $pickup_point['house_number'];
			}
			if ( ! empty( $pickup_point['postcode'] ) ) {
				$meta_data['pickup_point_postcode'] = $pickup_point['postcode'];
			}
			if ( ! empty( $pickup_point['city'] ) ) {
				$meta_data['pickup_point_city'] = $pickup_point['city'];
			}
			if ( ! empty( $pickup_point['country'] ) ) {
				$meta_data['pickup_point_country'] = $pickup_point['country'];
			}

			// Save carrier information
			if ( ! empty( $pickup_point['carrier'] ) ) {
				$meta_data['pickup_point_carrier'] = $pickup_point['carrier'];
			}

			// Save opening hours
			if ( ! empty( $pickup_point['opening_hours'] ) ) {
				$meta_data['pickup_point_opening_hours'] = wp_json_encode( $pickup_point['opening_hours'] );
			}
		}

		$rate = [
			'id' => $this->get_rate_id(),
			'label' => $label,
			'cost' => $cost,
			'calc_tax' => 'per_order',
			'meta_data' => $meta_data,
		];

		$this->add_rate( $rate );
	}


	/**
	 * Sanitize selected carriers.
	 *
	 * @param mixed $value Carriers value.
	 * @return array Sanitized carriers.
	 * @throws \Exception If no carriers are selected.
	 */
	public function sanitize_carriers( $value ) {
		if ( ! is_array( $value ) ) {
			$value = [];
		}

		// Filter out invalid carriers
		$valid_carriers = array_keys( $this->available_carriers );
		$value          = array_intersect( $value, $valid_carriers );

		if ( empty( $value ) ) {
			throw new \Exception( esc_html__( 'At least one carrier must be selected.', 'wuunder-shipping' ) );
		}

		return $value;
	}

	/**
	 * Generate custom color field HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_wuunder_color_html( $key, $data ): string {
		$field_key = $this->get_field_key( $key );
		$defaults  = [
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => [],
		];

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="wuunder-color-picker <?php echo esc_attr( $data['class'] ); ?>" type="color" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}


	/**
	 * Sanitize color value.
	 *
	 * @param mixed $value Color value.
	 * @return string Sanitized color.
	 */
	public function sanitize_color( $value ) {
		$value = sanitize_text_field( $value );

		// Ensure it's a valid hex color
		if ( ! preg_match( '/^#?[a-fA-F0-9]{6}$/', $value ) ) {
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Not commented out code, just a return value with comment.
			return '#52ba69'; // Return default if invalid
		}

		// Ensure it starts with #
		if ( strpos( $value, '#' ) !== 0 ) {
			$value = '#' . $value;
		}

		return $value;
	}
}