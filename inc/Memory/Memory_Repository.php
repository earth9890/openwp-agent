<?php
/**
 * Memory repository.
 *
 * @package OpenWP\Inc\Memory
 */

namespace OpenWP\Inc\Memory;

use OpenWP\Inc\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD access for OpenWP memory records.
 */
class Memory_Repository {
	/**
	 * List memory records with optional filters.
	 *
	 * @param array<string,mixed> $args List arguments.
	 * @return array<string,mixed>
	 */
	public function list( $args = [] ) {
		global $wpdb;

		if ( ! Tables::exists( 'memory' ) ) {
			return [
				'total'    => 0,
				'page'     => 1,
				'per_page' => 50,
				'items'    => [],
			];
		}

		$defaults = [
			'page'     => 1,
			'per_page' => 50,
			'type'     => '',
			'search'   => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$where  = [ '1=1' ];
		$values = [];

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'memory_type = %s';
			$values[] = sanitize_key( (string) $args['type'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
			$where[]  = '(memory_key LIKE %s OR memory_value LIKE %s)';
			$values[] = $search;
			$values[] = $search;
		}

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, min( 200, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table     = Tables::get( 'memory' );
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d";

		$list_values   = $values;
		$list_values[] = $per_page;
		$list_values[] = $offset;

		if ( empty( $values ) ) {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return [
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => array_map( [ $this, 'decode_row' ], is_array( $rows ) ? $rows : [] ),
		];
	}

	/**
	 * Fetch one record by type+key.
	 *
	 * @param string $type Memory type.
	 * @param string $key Memory key.
	 * @return array<string,mixed>|null
	 */
	public function get( $type, $key ) {
		global $wpdb;

		if ( ! Tables::exists( 'memory' ) ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Tables::get( 'memory' ) . ' WHERE memory_type = %s AND memory_key = %s LIMIT 1',
				sanitize_key( $type ),
				sanitize_title( $key )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->decode_row( $row );
	}

	/**
	 * Insert or update a memory record.
	 *
	 * @param string $type Memory type.
	 * @param string $key Memory key.
	 * @param string $value_json Encoded payload.
	 * @return bool
	 */
	public function upsert( $type, $key, $value_json ) {
		global $wpdb;

		if ( ! Tables::exists( 'memory' ) ) {
			return false;
		}

		$table = Tables::get( 'memory' );
		$now   = current_time( 'mysql', true );

		$query = $wpdb->prepare(
			"INSERT INTO {$table} (memory_type, memory_key, memory_value, updated_at)
			VALUES (%s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				memory_value = VALUES(memory_value),
				updated_at = VALUES(updated_at)",
			sanitize_key( $type ),
			sanitize_title( $key ),
			(string) $value_json,
			$now
		);

		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return false !== $result;
	}

	/**
	 * Delete a memory record by type+key.
	 *
	 * @param string $type Memory type.
	 * @param string $key Memory key.
	 * @return bool
	 */
	public function delete( $type, $key ) {
		global $wpdb;

		if ( ! Tables::exists( 'memory' ) ) {
			return false;
		}

		$deleted = $wpdb->delete(
			Tables::get( 'memory' ),
			[
				'memory_type' => sanitize_key( $type ),
				'memory_key'  => sanitize_title( $key ),
			],
			[ '%s', '%s' ]
		);

		return false !== $deleted;
	}

	/**
	 * Delete all memory records.
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_all() {
		global $wpdb;

		if ( ! Tables::exists( 'memory' ) ) {
			return 0;
		}

		$table = Tables::get( 'memory' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( "DELETE FROM {$table}" );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Fetch recent records for relevance scoring.
	 *
	 * @param int $limit Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function fetch_recent( $limit = 200 ) {
		global $wpdb;

		if ( ! Tables::exists( 'memory' ) ) {
			return [];
		}

		$limit = max( 1, min( 400, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Tables::get( 'memory' ) . ' ORDER BY updated_at DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( [ $this, 'decode_row' ], is_array( $rows ) ? $rows : [] );
	}

	/**
	 * Decode a row payload.
	 *
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	private function decode_row( $row ) {
		$value = [];
		if ( isset( $row['memory_value'] ) && is_string( $row['memory_value'] ) ) {
			$decoded = json_decode( $row['memory_value'], true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			}
		}

		$text = isset( $value['text'] ) && is_string( $value['text'] ) ? $value['text'] : '';
		$tags = [];
		if ( isset( $value['tags'] ) && is_array( $value['tags'] ) ) {
			foreach ( $value['tags'] as $tag ) {
				$clean_tag = sanitize_key( (string) $tag );
				if ( '' !== $clean_tag ) {
					$tags[] = $clean_tag;
				}
			}
		}

		return [
			'id'         => absint( $row['id'] ?? 0 ),
			'type'       => sanitize_key( (string) ( $row['memory_type'] ?? '' ) ),
			'key'        => sanitize_title( (string) ( $row['memory_key'] ?? '' ) ),
			'text'       => sanitize_textarea_field( $text ),
			'tags'       => array_values( array_unique( $tags ) ),
			'value'      => $value,
			'updated_at' => sanitize_text_field( (string) ( $row['updated_at'] ?? '' ) ),
		];
	}
}
