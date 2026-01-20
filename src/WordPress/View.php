<?php

namespace Wuunder\Shipping\WordPress;

/**
 * Simple view renderer for template files.
 */
class View {

	/**
	 * Render a view template.
	 *
	 * @param string $template Template name (without .php extension).
	 * @param array  $data     Data to pass to template.
	 * @return string Rendered content.
	 */
	public static function render( $template, array $data = [] ) {
		$template_path = WUUNDER_PLUGIN_PATH . '/src/Views/' . $template . '.php';

		if ( ! file_exists( $template_path ) ) {
			return '<!-- Template not found: ' . $template . ' -->';
		}

		// Extract variables for use in template
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data );

		// Start output buffering
		ob_start();

		// Include template
		include $template_path;

		// Return captured output
		return ob_get_clean();
	}

	/**
	 * Render and echo a view template.
	 *
	 * @param string $template Template name (without .php extension).
	 * @param array  $data     Data to pass to template.
	 * @return void
	 */
	public static function display( $template, array $data = [] ): void {
		echo self::render( $template, $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
