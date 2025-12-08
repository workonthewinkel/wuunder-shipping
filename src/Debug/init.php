<?php
/**
 * Debug initialization
 * This file is automatically loaded via autoload-dev
 *
 * @package Wuunder\Shipping
 */

if ( ! function_exists( 'add_action' ) ) {
	return;
}

// Initialize debug controller when WordPress loads
add_action(
	'init',
	function () {
		if ( is_admin() && class_exists( '\Wuunder\Shipping\Debug\DebugController' ) ) {
			$debug_controller = new \Wuunder\Shipping\Debug\DebugController();
			$debug_controller->register_hooks();
		}
	}
);

// Register WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\Wuunder\Shipping\Debug\CLI' ) ) {
	\Wuunder\Shipping\Debug\CLI::register();
}
