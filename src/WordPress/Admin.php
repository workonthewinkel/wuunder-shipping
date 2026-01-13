<?php

namespace Wuunder\Shipping\WordPress;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin God class.
 */
class Admin {

	/**
	 * Runs when the plugin is first activated.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
			return;
		}

		// Add settings link to plugin actions
		add_filter( 'plugin_action_links_' . plugin_basename( WUUNDER_PLUGIN_FILE ), [ $this, 'add_settings_link' ] );

		// Handle activation redirect
		add_action( 'admin_init', [ $this, 'handle_activation_redirect' ] );
	}

	/**
	 * Display notice if WooCommerce is not active.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Wuunder Shipping requires WooCommerce to be installed and active.', 'wuunder-shipping' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add settings link to plugin actions.
	 *
	 * @param array<string> $links Existing plugin action links.
	 * @return array<string>
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=wuunder' ) ),
			esc_html__( 'Settings', 'wuunder-shipping' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Handle redirect to settings page after activation.
	 *
	 * @return void
	 */
	public function handle_activation_redirect(): void {
		// Only redirect if transient exists and we're not doing bulk activation
		if ( get_transient( 'wuunder_activation_redirect' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Delete the transient
			delete_transient( 'wuunder_activation_redirect' );

			// Don't redirect if we're already on the settings page
			$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab  = sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $page === 'wc-settings' && $tab === 'wuunder' ) {
				return;
			}

			// Don't redirect during AJAX requests
			if ( wp_doing_ajax() ) {
				return;
			}

			// Redirect to settings page
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=wuunder' ) );
			exit;
		}
	}
}
