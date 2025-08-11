<?php
/**
 * Plugin Name: Wuunder Shipping
 * Plugin URI: wearewuunder.com
 * Description: Starting template 
 * Version: 0.0.1
 * Author: Marinus Klasen, Luc Princen
 * Author URI: mklasen.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wuunder-shipping
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 */

use Wuunder\Shipping\Plugin;

define( 'EXAMPLE_PLUGIN_FILE', __FILE__ );
define( 'EXAMPLE_PLUGIN_PATH', dirname( EXAMPLE_PLUGIN_FILE ) );
define( 'EXAMPLE_PLUGIN_SLUG', 'wuunder-shipping' );
define( 'EXAMPLE_PLUGIN_VERSION', '0.0.1' );

if ( ! file_exists( EXAMPLE_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
	return;
}

require EXAMPLE_PLUGIN_PATH . '/vendor/autoload.php';

// Upon activation check if the data model is in order.
register_activation_hook( EXAMPLE_PLUGIN_FILE, function() {
	( new Plugin() )->install();
} );

// Upon deactivation, uninstall the plugin
register_deactivation_hook( EXAMPLE_PLUGIN_FILE, function() {
	( new Plugin() )->uninstall();
} );

/**
 * Bootstrap the plugin.
 */
function wuunder_shipping_init(): Plugin {

	static $plugin;

	if ( is_object( $plugin ) ) {
		return $plugin;
	}

	$plugin = new Plugin();
	$plugin->init();

	return $plugin;
}

add_action( 'plugins_loaded', 'wuunder_shipping_init' );
