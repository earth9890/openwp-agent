<?php
/**
 * Backup repository.
 *
 * @package OpenWP\Inc\Backup
 */

namespace OpenWP\Inc\Backup;

use OpenWP\Inc\Database\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles backup records.
 */
class Backup_Repository {
	/**
	 * Insert backup row.
	 *
	 * @param array<string,mixed> $data Backup data.
	 * @return int
	 */
	public function insert( $data ) {
		global $wpdb;
		if ( ! Tables::exists( 'backups' ) ) {
			return 0;
		}

		$wpdb->insert(
			Tables::get( 'backups' ),
			[
				'user_id'    => absint( $data['user_id'] ?? 0 ),
				'file_path'  => sanitize_text_field( $data['file_path'] ?? '' ),
				'file_size'  => absint( $data['file_size'] ?? 0 ),
				'checksum'   => sanitize_text_field( $data['checksum'] ?? '' ),
				'status'     => sanitize_key( $data['status'] ?? 'completed' ),
				'meta'       => wp_json_encode( $data['meta'] ?? [] ),
				'expires_at' => ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null,
				'created_at' => current_time( 'mysql', true ),
			],
			[
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);

		return absint( $wpdb->insert_id );
	}

	/**
	 * Get backup row.
	 *
	 * @param int $id Backup ID.
	 * @return array<string,mixed>|null
	 */
	public function get( $id ) {
		global $wpdb;
		if ( ! Tables::exists( 'backups' ) ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::get( 'backups' ) . ' WHERE id = %d', absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $row ) ) {
			return null;
		}

		$row['meta'] = json_decode( (string) $row['meta'], true );
		if ( ! is_array( $row['meta'] ) ) {
			$row['meta'] = [];
		}

		return $row;
	}

	/**
	 * List backups.
	 *
	 * @param int $page Current page.
	 * @param int $per_page Items per page.
	 * @return array<string,mixed>
	 */
	public function list( $page = 1, $per_page = 20 ) {
		global $wpdb;
		if ( ! Tables::exists( 'backups' ) ) {
			return [
				'total'    => 0,
				'page'     => 1,
				'per_page' => 20,
				'items'    => [],
			];
		}

		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 100, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table = Tables::get( 'backups' );
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$items = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$row['meta'] = json_decode( (string) $row['meta'], true );
			if ( ! is_array( $row['meta'] ) ) {
				$row['meta'] = [];
			}
			$items[] = $row;
		}

		return [
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => $items,
		];
	}

	/**
	 * Delete expired backup records.
	 *
	 * @return int|false
	 */
	public function delete_expired() {
		global $wpdb;
		if ( ! Tables::exists( 'backups' ) ) {
			return 0;
		}

		return $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Tables::get( 'backups' ) . ' WHERE expires_at IS NOT NULL AND expires_at < %s', current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
