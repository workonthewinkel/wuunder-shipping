<?php

namespace Wuunder\Shipping\Contracts;

use Wuunder\Shipping\Contracts\Interfaces\Hookable;

/**
 * Class Queueable
 *
 * This class represents a queueable job
 */
abstract class Queueable implements Hookable {

	/**
	 * Hook on which this queueable gets triggered.
	 */
	protected string $hook;

	/**
	 * All Action Scheduler async actions should be assigned to a group.
	 * With this we are able to check whether a group of async actions is still running.
	 */
	protected static string $group;

	/**
	 * Register this job
	 */
	public function register_hooks(): void {
		// only hook into the hook, if we have a handle action
		// we can't give a default for this function because of
		// how action scheduler deals with arguments. It's got priority 100
		// and allows for a max of 10 arguments to be passed along.
		if ( \method_exists( $this, 'handle' ) ) {
			\add_action( $this->hook, [ $this, 'handle' ], 100, 10 );
		}
	}
    
    /**
     * Handle the job
     */
    public function handle(): void {
        // implement this in the child class
    }

	/**
	 * Get the group name used for scheduling async actions in the Action Scheduler.
	 */
	public static function get_group(): string {
		return self::$group;
	}

	/**
	 * Return this queueables hook
	 */
	public function get_hook(): string {
		return $this->hook;
	}
}