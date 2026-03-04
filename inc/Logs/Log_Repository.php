<?php
/**
 * Action log repository.
 *
 * @package OpenWP\Inc\Logs
 */

namespace OpenWP\Inc\Logs;

use OpenWP\Inc\Core\Settings;
use OpenWP\Inc\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for execution logs.
 */
class Log_Repository {
	/**
	 * Cleanup expired logs based on retention policy.
	 *
	 * @return void
	 */
	public static function cleanup_retention() {
		global $wpdb;
		if ( ! Tables::exists( 'logs' ) ) {
			return;
		}

		$retention = max( 1, absint( Settings::get()['log_retention_days'] ) );
		$cutoff    = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $retention ) );

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . Tables::get( 'logs' ) . ' WHERE created_at < %s',
				$cutoff
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Insert log row.
	 *
	 * @param array<string,mixed> $data Log row data.
	 * @return int
	 */
	public function insert( $data ) {
		global $wpdb;
		if ( ! Tables::exists( 'logs' ) ) {
			return 0;
		}

		$defaults = [
			'user_id'           => 0,
			'approver_id'       => 0,
			'action_key'        => '',
			'risk_level'        => 'low',
			'prompt'            => '',
			'model_output'      => '',
			'params'            => '',
			'result'            => '',
			'status'            => 'success',
			'error_message'     => '',
			'rollback_snapshot' => '',
			'backup_id'         => 0,
			'created_at'        => current_time( 'mysql', true ),
		];

		$row = wp_parse_args( $data, $defaults );

		$wpdb->insert(
			Tables::get( 'logs' ),
			[
				'user_id'           => absint( $row['user_id'] ),
				'approver_id'       => absint( $row['approver_id'] ),
				'action_key'        => sanitize_key( $row['action_key'] ),
				'risk_level'        => sanitize_key( $row['risk_level'] ),
				'prompt'            => wp_json_encode( $row['prompt'] ),
				'model_output'      => wp_json_encode( $row['model_output'] ),
				'params'            => wp_json_encode( $row['params'] ),
				'result'            => wp_json_encode( $row['result'] ),
				'status'            => sanitize_key( $row['status'] ),
				'error_message'     => is_string( $row['error_message'] ) ? $row['error_message'] : wp_json_encode( $row['error_message'] ),
				'rollback_snapshot' => wp_json_encode( $row['rollback_snapshot'] ),
				'backup_id'         => absint( $row['backup_id'] ),
				'created_at'        => sanitize_text_field( $row['created_at'] ),
			],
			[
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			]
		);

		return absint( $wpdb->insert_id );
	}

	/**
	 * Fetch paginated logs.
	 *
	 * @param array<string,mixed> $args Filter args.
	 * @return array<string,mixed>
	 */
	public function list( $args = [] ) {
		global $wpdb;
		if ( ! Tables::exists( 'logs' ) ) {
			return [
				'total'    => 0,
				'page'     => 1,
				'per_page' => 20,
				'items'    => [],
			];
		}

		$defaults = [
			'page'      => 1,
			'per_page'  => 20,
			'status'    => '',
			'action'    => '',
			'risk'      => '',
			'user_id'   => 0,
			'search'    => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$where  = [ '1=1' ];
		$values = [];

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action_key = %s';
			$values[] = sanitize_key( $args['action'] );
		}

		if ( ! empty( $args['risk'] ) ) {
			$where[]  = 'risk_level = %s';
			$values[] = sanitize_key( $args['risk'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(action_key LIKE %s OR error_message LIKE %s)';
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$values[] = $search;
			$values[] = $search;
		}

		$where_sql = implode( ' AND ', $where );

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table = Tables::get( 'logs' );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";

		$count_values = $values;
		$list_values  = $values;
		$list_values[] = $per_page;
		$list_values[] = $offset;

		if ( empty( $count_values ) ) {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_values ), ARRAY_A );

		return [
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => array_map( [ $this, 'decode_row' ], is_array( $rows ) ? $rows : [] ),
		];
	}

	/**
	 * Find a log row.
	 *
	 * @param int $id Log ID.
	 * @return array<string,mixed>|null
	 */
	public function get( $id ) {
		global $wpdb;
		if ( ! Tables::exists( 'logs' ) ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::get( 'logs' ) . ' WHERE id = %d', absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->decode_row( $row );
	}

	/**
	 * Decode JSON fields.
	 *
	 * @param array<string,mixed> $row Raw DB row.
	 * @return array<string,mixed>
	 */
	private function decode_row( $row ) {
		$fields = [ 'prompt', 'model_output', 'params', 'result', 'rollback_snapshot' ];

		foreach ( $fields as $field ) {
			if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) ) {
				$decoded = json_decode( $row[ $field ], true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					$row[ $field ] = $decoded;
				}
			}
		}

		return $row;
	}
}
