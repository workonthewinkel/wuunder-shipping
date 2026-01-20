<?php
/**
 * Plugin Name: Wuunder Shipping
 * Plugin URI: https://wuunder.com
 * Description: WooCommerce integration for Wuunder parcel delivery platform
 * Version: 1.0.0
 * Author: Work on The Winkel
 * Author URI: https://workonthewinkel.nl/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wuunder-shipping
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 */

use Wuunder\Shipping\Plugin;

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WUUNDER_PLUGIN_FILE', __FILE__ );
define( 'WUUNDER_PLUGIN_PATH', dirname( WUUNDER_PLUGIN_FILE ) );
define( 'WUUNDER_PLUGIN_URL', plugin_dir_url( WUUNDER_PLUGIN_FILE ) );
define( 'WUUNDER_PLUGIN_SLUG', 'wuunder-shipping' );
define( 'WUUNDER_PLUGIN_VERSION', '1.0.0' );

if ( ! file_exists( WUUNDER_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
	return;
}

require WUUNDER_PLUGIN_PATH . '/vendor/autoload.php';

/**
 * Handle plugin activation.
 */
function wuunder_shipping_activate() {
	( new Plugin() )->install();
}
register_activation_hook( WUUNDER_PLUGIN_FILE, 'wuunder_shipping_activate' );

/**
 * Handle plugin deactivation.
 */
function wuunder_shipping_deactivate() {
	( new Plugin() )->uninstall();
}
register_deactivation_hook( WUUNDER_PLUGIN_FILE, 'wuunder_shipping_deactivate' );

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
