<?php

namespace Wuunder\Shipping\Models;

use Wuunder\Shipping\Contracts\Model;

/**
 * Class Reservation
 *
 * This class represents an Reservation
 *
 */
class Example extends Model {

	/**
	 * Define the table name
	 *
	 * @var string
	 */
	protected $table = 'wooping_examples';

	/**
	 * Everything is fillable, except id:
	 *
	 * @var string[]
	 */
	protected $guarded = [ 'id' ];


}
