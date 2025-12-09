<?php

namespace Wuunder\Shipping\Queue;

use Wuunder\Shipping\Contracts\Queueable;

/**
 * Class UpdateCarriers
 *
 * This class is responsible for updating carriers
 */
class UpdateCarriers extends Queueable {

	/**
	 * Hook on which this job runs
	 */
	protected string $hook = 'wuunder/update_carriers';

	/**
	 * This job triggers a call to the wuunder carriers api and updates the carriers in the database.   
	 *
	 * @return void
	 */
	public function handle(): void {
        // we want to refresh the carriers and preserve the enabled state.
		CarrierService::refresh_from_api( true );
	}
}