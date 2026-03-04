<?php
/**
 * Database actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use OpenWP\Inc\Security\Sql_Guard;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database maintenance and SQL handlers.
 */
class Database_Actions {
	/**
	 * Optimize DB tables.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function db_optimize_tables( $params, ActionContext $context ) {
		return self::run_table_maintenance( 'OPTIMIZE TABLE', $params, __( 'Tables optimized.', 'openwp' ) );
	}

	/**
	 * Repair DB tables.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function db_repair_tables( $params, ActionContext $context ) {
		return self::run_table_maintenance( 'REPAIR TABLE', $params, __( 'Tables repaired.', 'openwp' ) );
	}

	/**
	 * Analyze DB tables.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function db_analyze_tables( $params, ActionContext $context ) {
		return self::run_table_maintenance( 'ANALYZE TABLE', $params, __( 'Tables analyzed.', 'openwp' ) );
	}

	/**
	 * Execute arbitrary SQL query.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function db_query( $params, ActionContext $context ) {
		global $wpdb;

		$query = isset( $params['query'] ) ? (string) $params['query'] : '';
		$guard = new Sql_Guard();
		$meta  = $guard->inspect( $query );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		$statement_type = (string) $meta['statement_type'];
		$is_read        = ! empty( $meta['is_read'] );
		$is_write       = ! empty( $meta['is_write'] );
		$run_query      = rtrim( trim( (string) $meta['query'] ), ';' );

		if ( ! $is_read && ! $is_write ) {
			return new WP_Error( 'openwp_db_query_unsupported', __( 'Unsupported SQL statement type.', 'openwp' ) );
		}

		// Cap read query size if no explicit limit.
		if ( $is_read && ! $is_write && null === $meta['row_limit'] && in_array( $statement_type, [ 'select', 'show', 'describe', 'explain' ], true ) ) {
			$run_query .= ' LIMIT 200';
		}

		// Best-effort execution timeout (MySQL 5.7+).
		$wpdb->query( 'SET SESSION MAX_EXECUTION_TIME=5000' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $is_write ) {
			$result = $wpdb->query( $run_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $result ) {
				return new WP_Error( 'openwp_db_query_failed', __( 'SQL write query failed.', 'openwp' ) );
			}

			return new ActionResult(
				[
					'success' => true,
					'message' => __( 'SQL write query executed.', 'openwp' ),
					'data'    => [
						'statement_type' => $statement_type,
						'affected_rows'  => (int) $result,
					],
				]
			);
		}

		$rows = $wpdb->get_results( $run_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( null === $rows ) {
			return new WP_Error( 'openwp_db_query_failed', __( 'SQL read query failed.', 'openwp' ) );
		}

		$rows = self::redact_sensitive_values( is_array( $rows ) ? $rows : [] );

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'SQL read query executed.', 'openwp' ),
				'data'    => [
					'statement_type' => $statement_type,
					'rows'           => $rows,
					'count'          => count( $rows ),
				],
			]
		);
	}

	/**
	 * Run table maintenance queries.
	 *
	 * @param string               $command SQL command.
	 * @param array<string,mixed>  $params Params.
	 * @param string               $message Success message.
	 * @return ActionResult|WP_Error
	 */
	private static function run_table_maintenance( $command, $params, $message ) {
		global $wpdb;

		$tables = [];
		if ( ! empty( $params['tables'] ) && is_array( $params['tables'] ) ) {
			foreach ( $params['tables'] as $table ) {
				$table = sanitize_text_field( (string) $table );
				if ( '' !== $table ) {
					$tables[] = $table;
				}
			}
		}

		if ( empty( $tables ) ) {
			$tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		if ( empty( $tables ) || ! is_array( $tables ) ) {
			return new WP_Error( 'openwp_no_tables', __( 'No tables found for maintenance.', 'openwp' ) );
		}

		$results = [];
		foreach ( $tables as $table ) {
			$table = (string) $table;
			if ( '' === $table ) {
				continue;
			}

			$query  = $command . ' `' . str_replace( '`', '``', $table ) . '`';
			$result = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results[] = [
				'table'  => $table,
				'result' => is_array( $result ) ? $result : [],
			];
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => $message,
				'data'    => [ 'results' => $results ],
			]
		);
	}

	/**
	 * Redact sensitive fields in SQL outputs.
	 *
	 * @param array<int,array<string,mixed>> $rows SQL rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function redact_sensitive_values( $rows ) {
		$sensitive_tokens = [ 'password', 'pass', 'token', 'secret', 'key', 'auth' ];

		foreach ( $rows as $index => $row ) {
			foreach ( $row as $column => $value ) {
				$column_lower = strtolower( (string) $column );
				foreach ( $sensitive_tokens as $token ) {
					if ( false !== strpos( $column_lower, $token ) ) {
						$rows[ $index ][ $column ] = '[REDACTED]';
						break;
					}
				}
			}
		}

		return $rows;
	}
}
