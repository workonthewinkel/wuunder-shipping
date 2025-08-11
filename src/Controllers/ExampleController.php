<?php

namespace Wuunder\Shipping\Controllers;

use Wuunder\Shipping\Contracts\Controller;
use Wuunder\Shipping\Models\Example;

class ExampleController extends Controller{


	public function view( $example_id ): Example {

		return Example::findOrFail( $example_id );
	
	}

}
