<?php
namespace Wuunder\Shipping\Contracts\Interfaces;

/**
 * The Hookable class
 */
interface Hookable {

	/**
	 * A Hookable always has a register_hooks function.
	 */
	public function register_hooks(): void;
}
