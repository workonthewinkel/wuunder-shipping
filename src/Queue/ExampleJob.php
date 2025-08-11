<?php

namespace Wuunder\Shipping\Queue;

use Wuunder\Shipping\Contracts\Queueable;

/**
 * Class ExampleJob
 *
 * This class is an example of a job
 */
class ExampleJob extends Queueable {

	/**
	 * Hook on which this job runs
	 */
	protected string $hook = 'wuunder/shipping/example-job';

	/**
	 * This job doesn't do anything yet.
	 *
	 * @return void
	 */
	public function run( $reservation_id ): void {		
		return;
	}
}
