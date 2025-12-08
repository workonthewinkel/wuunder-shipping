<?php

namespace Wuunder\Shipping\Traits;

/**
 * Trait for displaying "no carriers available" notice in shipping method settings.
 */
trait NoCarriersNotice {

	/**
	 * Get form fields for "no carriers available" notice.
	 *
	 * @param string $settings_section The settings section to link to (e.g., 'shipping_methods' or 'pickup_methods').
	 * @return array Form fields array with no carriers notice.
	 */
	protected function get_no_carriers_notice_fields( string $settings_section ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=wuunder&section=' . $settings_section );

		return [
			'no_carriers_notice' => [
				'title' => __( 'No carriers available', 'wuunder-shipping' ),
				'type' => 'title',
				'description' => sprintf(
					/* translators: %s: Link to Wuunder carriers settings page */
					__( 'You don\'t have enabled carriers yet. %s', 'wuunder-shipping' ),
					sprintf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( $settings_url ),
						__( 'Click here to manage carriers', 'wuunder-shipping' )
					)
				),
			],
		];
	}
}
