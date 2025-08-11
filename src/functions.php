<?php
/**
 * Simple helper functions for our SWW plugin.
 */

if( !function_exists( 'dd' ) ){

	/**
	 * Simple dump & die function
	 *
	 * @param mixed $whatever
	 * @return void
	 */
	function dd( $whatever ): void {
		echo '<pre>';
		print_r( $whatever );
		echo '</pre>';
		die();
	}
}
