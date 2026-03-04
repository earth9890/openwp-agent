<?php
/**
 * Approval repository.
 *
 * @package OpenWP\Inc\Logs
 */

namespace OpenWP\Inc\Logs;

use OpenWP\Inc\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for approvals queue.
 */
class Approval_Repository {
	/**
	 * Create pending approval record.
	 *
	 * @param array<string,mixed> $data Approval record.
	 * @return int
	 */
	public function create( $data ) {
		global $wpdb;
		if ( ! Tables::exists( 'approvals' ) ) {
			return 0;
		}

		$wpdb->insert(
			Tables::get( 'approvals' ),
			[
				'requester_user_id' => absint( $data['requester_user_id'] ?? 0 ),
				'approver_user_id'  => 0,
				'action_key'        => sanitize_key( $data['action_key'] ?? '' ),
				'risk_level'        => sanitize_key( $data['risk_level'] ?? 'medium' ),
				'prompt'            => wp_json_encode( $data['prompt'] ?? '' ),
				'model_output'      => wp_json_encode( $data['model_output'] ?? [] ),
				'params'            => wp_json_encode( $data['params'] ?? [] ),
				'status'            => 'pending',
				'decision_note'     => '',
				'typed_confirmation' => '',
				'backup_required'   => ! empty( $data['backup_required'] ) ? 1 : 0,
				'backup_id'         => 0,
				'execution_log_id'  => 0,
				'created_at'        => current_time( 'mysql', true ),
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
				'%d',
				'%d',
				'%d',
				'%s',
			]
		);

		return absint( $wpdb->insert_id );
	}

	/**
	 * Update decision state.
	 *
	 * @param int                 $id Approval ID.
	 * @param array<string,mixed> $data Decision data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;
		if ( ! Tables::exists( 'approvals' ) ) {
			return false;
		}

		$result = $wpdb->update(
			Tables::get( 'approvals' ),
			[
				'approver_user_id'  => absint( $data['approver_user_id'] ?? 0 ),
				'status'            => sanitize_key( $data['status'] ?? 'pending' ),
				'decision_note'     => sanitize_textarea_field( $data['decision_note'] ?? '' ),
				'typed_confirmation' => sanitize_text_field( $data['typed_confirmation'] ?? '' ),
				'backup_id'         => absint( $data['backup_id'] ?? 0 ),
				'execution_log_id'  => absint( $data['execution_log_id'] ?? 0 ),
				'decided_at'        => current_time( 'mysql', true ),
			],
			[ 'id' => absint( $id ) ],
			[
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
			],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Get approval row.
	 *
	 * @param int $id Approval ID.
	 * @return array<string,mixed>|null
	 */
	public function get( $id ) {
		global $wpdb;
		if ( ! Tables::exists( 'approvals' ) ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::get( 'approvals' ) . ' WHERE id = %d', absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->decode_row( $row );
	}

	/**
	 * List approvals.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array<string,mixed>
	 */
	public function list( $args = [] ) {
		global $wpdb;
		if ( ! Tables::exists( 'approvals' ) ) {
			return [
				'total'    => 0,
				'page'     => 1,
				'per_page' => 20,
				'items'    => [],
			];
		}

		$args = wp_parse_args(
			$args,
			[
				'page'     => 1,
				'per_page' => 20,
				'status'   => '',
			]
		);

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = '1=1';
		$values = [];

		if ( ! empty( $args['status'] ) ) {
			$where    .= ' AND status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		$table = Tables::get( 'approvals' );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";

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
	 * Decode JSON columns.
	 *
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	private function decode_row( $row ) {
		$fields = [ 'prompt', 'model_output', 'params' ];

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
