<?php
/**
 * Database migrations.
 *
 * @package OpenWP\Inc\Database
 */

namespace OpenWP\Inc\Database;

use OpenWP\Inc\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and migrates OpenWP tables.
 */
class Migrations {
	/**
	 * Runtime flag to avoid repeated migration checks in same request.
	 *
	 * @var bool
	 */
	private static $checked = false;

	/**
	 * Activation entrypoint.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::seed_options();
		update_option( OPENWP_OPTION_DB_VERSION, OPENWP_DB_VERSION );
	}

	/**
	 * Ensure schema exists and is up to date.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::$checked ) {
			return;
		}
		self::$checked = true;

		$needs_upgrade = get_option( OPENWP_OPTION_DB_VERSION ) !== OPENWP_DB_VERSION;

		if ( ! $needs_upgrade && self::all_tables_exist() ) {
			return;
		}

		self::activate();
	}

	/**
	 * Create tables using dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$logs_table = Tables::get( 'logs' );
		$approvals  = Tables::get( 'approvals' );
		$memory     = Tables::get( 'memory' );
		$backups    = Tables::get( 'backups' );
		$usage      = Tables::get( 'usage_daily' );

		$sql = [];

		$sql[] = "CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			approver_id bigint(20) unsigned DEFAULT 0,
			action_key varchar(191) NOT NULL,
			risk_level varchar(20) NOT NULL,
			prompt longtext NULL,
			model_output longtext NULL,
			params longtext NULL,
			result longtext NULL,
			status varchar(30) NOT NULL,
			error_message longtext NULL,
			rollback_snapshot longtext NULL,
			backup_id bigint(20) unsigned DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action_key (action_key),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$approvals} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			requester_user_id bigint(20) unsigned NOT NULL,
			approver_user_id bigint(20) unsigned DEFAULT 0,
			action_key varchar(191) NOT NULL,
			risk_level varchar(20) NOT NULL,
			prompt longtext NULL,
			model_output longtext NULL,
			params longtext NULL,
			status varchar(30) NOT NULL,
			decision_note text NULL,
			typed_confirmation varchar(255) NULL,
			backup_required tinyint(1) NOT NULL DEFAULT 0,
			backup_id bigint(20) unsigned DEFAULT 0,
			execution_log_id bigint(20) unsigned DEFAULT 0,
			created_at datetime NOT NULL,
			decided_at datetime NULL,
			PRIMARY KEY  (id),
			KEY action_key (action_key),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$memory} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			memory_type varchar(40) NOT NULL,
			memory_key varchar(191) NOT NULL,
			memory_value longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY memory_unique (memory_type,memory_key)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$backups} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			file_path text NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			checksum varchar(128) NOT NULL,
			status varchar(30) NOT NULL,
			meta longtext NULL,
			expires_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$usage} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			usage_day date NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			actions_count int(11) NOT NULL DEFAULT 0,
			tokens_in int(11) NOT NULL DEFAULT 0,
			tokens_out int(11) NOT NULL DEFAULT 0,
			last_action_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY usage_day_user (usage_day,user_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Seed defaults for options.
	 *
	 * @return void
	 */
	private static function seed_options() {
		if ( false === get_option( OPENWP_OPTION_SETTINGS, false ) ) {
			add_option( OPENWP_OPTION_SETTINGS, Settings::defaults() );
		}

		if ( false === get_option( OPENWP_OPTION_ACTION_POLICIES, false ) ) {
			add_option( OPENWP_OPTION_ACTION_POLICIES, [] );
		}

		if ( false === get_option( OPENWP_OPTION_RATE_LIMITS, false ) ) {
			add_option(
				OPENWP_OPTION_RATE_LIMITS,
				[
					'max_actions_user_day' => 50,
					'max_actions_site_day' => 500,
					'max_tokens_user_day'  => 120000,
					'max_tokens_site_day'  => 1200000,
				]
			);
		}

		if ( false === get_option( OPENWP_OPTION_MCP_ENABLED, false ) ) {
			add_option( OPENWP_OPTION_MCP_ENABLED, false );
		}

		if ( false === get_option( OPENWP_OPTION_MCP_DEBUG_MODE, false ) ) {
			add_option( OPENWP_OPTION_MCP_DEBUG_MODE, false );
		}

		if ( false === get_option( OPENWP_OPTION_MCP_MODULES, false ) ) {
			add_option( OPENWP_OPTION_MCP_MODULES, Settings::mcp_module_defaults() );
		}

		if ( false === get_option( OPENWP_OPTION_MCP_BEARER_TOKEN, false ) ) {
			add_option( OPENWP_OPTION_MCP_BEARER_TOKEN, '' );
		}
	}

	/**
	 * Return required custom tables.
	 *
	 * @return array<int,string>
	 */
	private static function required_tables() {
		return [
			Tables::get( 'logs' ),
			Tables::get( 'approvals' ),
			Tables::get( 'memory' ),
			Tables::get( 'backups' ),
			Tables::get( 'usage_daily' ),
		];
	}

	/**
	 * Check if all required custom tables exist.
	 *
	 * @return bool
	 */
	private static function all_tables_exist() {
		global $wpdb;

		foreach ( self::required_tables() as $table ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( $exists !== $table ) {
				return false;
			}
		}

		return true;
	}
}
