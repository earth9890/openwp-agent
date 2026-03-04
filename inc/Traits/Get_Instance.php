<?php
/**
 * Singleton trait.
 *
 * @package OpenWP\Inc\Traits
 */

namespace OpenWP\Inc\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared singleton helper.
 */
trait Get_Instance {
	/**
	 * Singleton instance.
	 *
	 * @var static|null
	 */
	private static $instance;

	/**
	 * Retrieve singleton.
	 *
	 * @return static
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
