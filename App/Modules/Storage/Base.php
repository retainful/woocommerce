<?php

namespace RNOC\App\Modules\Storage;
defined( 'ABSPATH' ) || exit;

abstract class Base {
	/**
	 * Set storage value.
	 *
	 * @param string $key Storage key.
	 * @param mixed $value Storage value.
	 *
	 * @return void
	 */
	abstract function set( $key, $value );

	/**
	 * Get storage value.
	 *
	 * @param string $key Storage key.
	 * @param mixed $default Storage default value.
	 *
	 * @return mixed
	 */
	abstract function get( $key, $default = '' );

	/**
	 * Remove storage by key.
	 *
	 * @param string $key Storage key.
	 *
	 * @return void
	 */
	abstract function remove( $key );

	/**
	 * Has storage key.
	 *
	 * @param string $key Storage key.
	 *
	 * @return mixed
	 */
	abstract function has( $key );
}