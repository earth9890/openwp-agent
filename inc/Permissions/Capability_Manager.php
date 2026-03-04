<?php
/**
 * Capability manager.
 *
 * @package OpenWP\Inc\Permissions
 */

namespace OpenWP\Inc\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assigns OpenWP capabilities.
 */
class Capability_Manager {
	/**
	 * OpenWP capabilities.
	 *
	 * @return string[]
	 */
	public static function capabilities() {
		return [
			'openwp_run_agent',
			'openwp_approve_actions',
			'openwp_manage_settings',
			'openwp_view_logs',
		];
	}

	/**
	 * Grant capabilities to admin role.
	 *
	 * @return void
	 */
	public static function grant_capabilities() {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::capabilities() as $capability ) {
			$role->add_cap( $capability );
		}
	}
}
