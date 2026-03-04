<?php
/**
 * Table name helper.
 *
 * @package OpenWP\Inc\Database
 */

namespace OpenWP\Inc\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves OpenWP table names.
 */
class Tables {
	/**
	 * Runtime flag to avoid repeated self-heal attempts in same request.
	 *
	 * @var bool
	 */
	private static $self_heal_attempted = false;

	/**
	 * Get full table name by suffix.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public static function get( $suffix ) {
		global $wpdb;

		return $wpdb->prefix . 'openwp_' . $suffix;
	}

	/**
	 * Check whether a custom OpenWP table exists.
	 *
	 * @param string $suffix Table suffix.
	 * @return bool
	 */
	public static function exists( $suffix ) {
		global $wpdb;

		$table = self::get( $suffix );
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( $exists === $table ) {
			return true;
		}

		if ( ! self::$self_heal_attempted ) {
			self::$self_heal_attempted = true;
			Migrations::maybe_upgrade();

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return $exists === $table;
	}
}
