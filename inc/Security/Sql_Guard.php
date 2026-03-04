<?php
/**
 * SQL guardrails.
 *
 * @package OpenWP\Inc\Security
 */

namespace OpenWP\Inc\Security;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates arbitrary SQL before execution.
 */
class Sql_Guard {
	/**
	 * Allowed read-only SQL statements.
	 *
	 * @var string[]
	 */
	private $read_statements = [
		'select',
		'show',
		'describe',
		'explain',
	];

	/**
	 * Allowed mutating SQL statements.
	 *
	 * @var string[]
	 */
	private $write_statements = [
		'insert',
		'update',
		'delete',
		'replace',
		'alter',
		'drop',
		'truncate',
		'create',
		'rename',
		'optimize',
		'repair',
		'analyze',
	];

	/**
	 * Blocked SQL tokens.
	 *
	 * @var string[]
	 */
	private $blocked_tokens = [
		'INTO OUTFILE',
		'LOAD_FILE',
		'INTO DUMPFILE',
		'BENCHMARK(',
		'SLEEP(',
		'INFORMATION_SCHEMA.FILES',
	];

	/**
	 * Inspect query and classify risk.
	 *
	 * @param string $query Query string.
	 * @return array<string,mixed>|WP_Error
	 */
	public function inspect( $query ) {
		$query = trim( $query );
		if ( '' === $query ) {
			return new WP_Error( 'openwp_sql_empty', __( 'SQL query is empty.', 'openwp' ) );
		}

		$query_without_trailing_semicolon = rtrim( rtrim( $query ), ';' );
		if ( false !== strpos( $query_without_trailing_semicolon, ';' ) ) {
			return new WP_Error( 'openwp_sql_multi_statement', __( 'Multiple SQL statements are not allowed.', 'openwp' ) );
		}

		$normalized = strtoupper( preg_replace( '/\s+/', ' ', $query ) );
		if ( ! is_string( $normalized ) ) {
			return new WP_Error( 'openwp_sql_invalid', __( 'Unable to inspect SQL query.', 'openwp' ) );
		}

		foreach ( $this->blocked_tokens as $token ) {
			if ( false !== strpos( $normalized, $token ) ) {
				return new WP_Error( 'openwp_sql_blocked', sprintf( __( 'Blocked SQL primitive detected: %s', 'openwp' ), $token ) );
			}
		}

		$statement_type = '';
		if ( preg_match( '/^([A-Z]+)/i', $query, $matches ) ) {
			$statement_type = strtolower( (string) $matches[1] );
		}

		$is_read  = in_array( $statement_type, $this->read_statements, true );
		$is_write = in_array( $statement_type, $this->write_statements, true );
		if ( ! $is_read && ! $is_write ) {
			return new WP_Error( 'openwp_sql_unsupported_statement', __( 'Unsupported SQL statement type.', 'openwp' ) );
		}

		return [
			'query'          => $query,
			'normalized'     => $normalized,
			'statement_type' => $statement_type,
			'is_read'        => $is_read,
			'is_write'       => $is_write,
			'row_limit'      => $this->extract_row_limit( $normalized ),
		];
	}

	/**
	 * Parse LIMIT value for read safety.
	 *
	 * @param string $query Normalized query.
	 * @return int|null
	 */
	private function extract_row_limit( $query ) {
		if ( preg_match( '/LIMIT\s+(\d+)/i', $query, $matches ) ) {
			return absint( $matches[1] );
		}

		return null;
	}
}
