<?php

namespace Wuunder\Shipping\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Register
 *
 * This class handles the registration of hooks and recurring jobs for this plugin.
 */
class Register {

	/**
	 * Register hooks for this plugin
	 *
	 * This method registers hooks for the plugin. It first gets all the queueable classes by calling the
	 * `queueables()` method. Then it loops through each queueable class instance and registers their hooks
	 * by calling the `register_hooks()` method on each instance. Finally, it registers any recurring jobs
	 * by adding an action hook to the 'init' hook, which calls the `register_recurring()` method on the
	 * current instance.
	 */
	public function register_hooks(): void {
		// get all classes in this folder.
		$queueables = $this->queueables();
		foreach ( $queueables as $queueable ) {

			// loop through each instance and listen for their events.
			$instance = '\\Wuunder\\Shipping\\Queue\\' . $queueable;
			( new $instance() )->register_hooks();
		}

		// register any recurring jobs.
		\add_action( 'init', [ $this, 'register_recurring' ] );
	}

	/**
	 * Register any recurring jobs for this plugin.
	 */
	public function register_recurring(): void {

		if ( \function_exists( 'as_has_scheduled_action' ) && \function_exists( 'as_schedule_recurring_action' ) ) {

			// daily check if our carriers are still available on the wuunder api.
			if ( \as_has_scheduled_action( 'wuunder/update_carriers' ) === false ) {
				\as_schedule_recurring_action(
					\time() + \DAY_IN_SECONDS,
					\DAY_IN_SECONDS,
					'wuunder/update_carriers'
				);
			}
		}
	}

	/**
	 * Return all files in this folder to queue them
	 *
	 * @return string[] Array of class names
	 */
	public function queueables(): array {
		$response = [];
		$dir      = \WUUNDER_PLUGIN_PATH . '/src/Queue';
		$files    = \scandir( $dir );

		$not_allowed = [ '.', '..', '.DS_Store', 'Register.php' ];

		foreach ( $files as $file ) {
			if ( ! \in_array( $file, $not_allowed, true ) ) {
				$response[] = \str_replace( '.php', '', $file );
			}
		}

		return $response;
	}

	/**
	 * Remove all scheduled actions
	 *
	 * @return void
	 */
	public function clean(): void {

		if ( ! \function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		// get all classes in this folder.
		$queueables = $this->queueables();
		foreach ( $queueables as $queueable ) {

			// loop through each instance and unschedule their hooks.
			$instance = '\\Wuunder\\Shipping\\Queue\\' . $queueable;
			\as_unschedule_all_actions( ( new $instance() )->get_hook() );
		}
	}
}
