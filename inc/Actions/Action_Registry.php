<?php
/**
 * Action registry.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores registered actions.
 */
class Action_Registry {
	/**
	 * Registry map.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private static $actions = [];

	/**
	 * Register action definition.
	 *
	 * @param string               $key Action key.
	 * @param array<string,mixed>  $definition Action metadata.
	 * @return void
	 */
	public static function register( $key, $definition ) {
		$definition['key'] = sanitize_key( $key );
		self::$actions[ $definition['key'] ] = $definition;
	}

	/**
	 * Get action definition.
	 *
	 * @param string $key Action key.
	 * @return array<string,mixed>|null
	 */
	public static function get( $key ) {
		$key = sanitize_key( $key );
		return isset( self::$actions[ $key ] ) ? self::$actions[ $key ] : null;
	}

	/**
	 * Return all registered actions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		return self::$actions;
	}
}
