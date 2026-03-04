<?php
/**
 * Backup service.
 *
 * @package OpenWP\Inc\Backup
 */

namespace OpenWP\Inc\Backup;

use OpenWP\Inc\Core\Settings;
use OpenWP\Inc\Database\Tables;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and restores SQL backups.
 */
class Backup_Service implements BackupServiceInterface {
	/**
	 * Backup repository.
	 *
	 * @var Backup_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new Backup_Repository();
	}

	/**
	 * Create a full SQL backup.
	 *
	 * @param int    $user_id User creating backup.
	 * @param string $reason Backup reason.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create( $user_id, $reason = '' ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'openwp_backup_upload_error', (string) $upload['error'] );
		}

		$backup_dir = trailingslashit( $upload['basedir'] ) . 'openwp-backups';
		if ( ! wp_mkdir_p( $backup_dir ) ) {
			return new WP_Error( 'openwp_backup_dir_error', __( 'Unable to create backup directory.', 'openwp' ) );
		}

		$timestamp  = gmdate( 'Ymd-His' );
		$random     = wp_generate_password( 8, false, false );
		$base_name  = "openwp-{$timestamp}-{$random}";
		$sql_path   = trailingslashit( $backup_dir ) . $base_name . '.sql';
		$gzip_path  = $sql_path . '.gz';

		$handle = fopen( $sql_path, 'wb' );
		if ( false === $handle ) {
			return new WP_Error( 'openwp_backup_file_error', __( 'Unable to create backup file.', 'openwp' ) );
		}

		$written = $this->write_dump( $handle );
		fclose( $handle );

		if ( is_wp_error( $written ) ) {
			@unlink( $sql_path );
			return $written;
		}

		$compressed = $this->gzip_file( $sql_path, $gzip_path );
		if ( is_wp_error( $compressed ) ) {
			@unlink( $sql_path );
			@unlink( $gzip_path );
			return $compressed;
		}

		@unlink( $sql_path );

		$checksum = hash_file( 'sha256', $gzip_path );
		$size     = filesize( $gzip_path );

		$retention_days = max( 1, absint( Settings::get()['log_retention_days'] ) );
		$expires_at     = gmdate( 'Y-m-d H:i:s', time() + ( DAY_IN_SECONDS * $retention_days ) );

		$backup_id = $this->repository->insert(
			[
				'user_id'    => $user_id,
				'file_path'  => $gzip_path,
				'file_size'  => (int) $size,
				'checksum'   => is_string( $checksum ) ? $checksum : '',
				'status'     => 'completed',
				'expires_at' => $expires_at,
				'meta'       => [
					'reason'  => $reason,
					'engine'  => 'wp-native-sql-export',
					'version' => OPENWP_VERSION,
				],
			]
		);

		if ( $backup_id <= 0 ) {
			return new WP_Error( 'openwp_backup_db_error', __( 'Failed to persist backup record.', 'openwp' ) );
		}

		return [
			'id'       => $backup_id,
			'path'     => $gzip_path,
			'size'     => (int) $size,
			'checksum' => $checksum,
		];
	}

	/**
	 * Restore backup by ID.
	 *
	 * @param int $backup_id Backup ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function restore( $backup_id ) {
		global $wpdb;

		$backup = $this->repository->get( $backup_id );
		if ( ! $backup ) {
			return new WP_Error( 'openwp_backup_not_found', __( 'Backup not found.', 'openwp' ) );
		}

		$file = (string) $backup['file_path'];
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'openwp_backup_missing_file', __( 'Backup file no longer exists on disk.', 'openwp' ) );
		}

		$checksum = hash_file( 'sha256', $file );
		if ( ! is_string( $checksum ) || $checksum !== (string) $backup['checksum'] ) {
			return new WP_Error( 'openwp_backup_checksum', __( 'Backup checksum mismatch.', 'openwp' ) );
		}

		$contents = gzdecode( (string) file_get_contents( $file ) );
		if ( false === $contents || '' === $contents ) {
			return new WP_Error( 'openwp_backup_read_error', __( 'Unable to read backup file.', 'openwp' ) );
		}

		$queries = $this->split_sql_statements( $contents );
		if ( empty( $queries ) ) {
			return new WP_Error( 'openwp_backup_empty_sql', __( 'No SQL statements found in backup.', 'openwp' ) );
		}

		$executed = 0;
		foreach ( $queries as $query ) {
			$query = trim( $query );
			if ( '' === $query ) {
				continue;
			}

			$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $result ) {
				return new WP_Error( 'openwp_backup_restore_failed', __( 'Backup restore failed while executing SQL.', 'openwp' ) );
			}

			++$executed;
		}

		return [
			'backup_id' => absint( $backup_id ),
			'executed'  => $executed,
		];
	}

	/**
	 * Cleanup expired backups on cron.
	 *
	 * @return void
	 */
	public static function cleanup_expired_backups() {
		global $wpdb;
		if ( ! \OpenWP\Inc\Database\Tables::exists( 'backups' ) ) {
			return;
		}

		$table = Tables::get( 'backups' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, file_path FROM {$table} WHERE expires_at IS NOT NULL AND expires_at < %s", current_time( 'mysql', true ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$file = isset( $row['file_path'] ) ? (string) $row['file_path'] : '';
				if ( '' !== $file && file_exists( $file ) ) {
					@unlink( $file );
				}
			}
		}

		$repository = new Backup_Repository();
		$repository->delete_expired();
	}

	/**
	 * List backup records.
	 *
	 * @param int $page Page number.
	 * @param int $per_page Items per page.
	 * @return array<string,mixed>
	 */
	public function list( $page = 1, $per_page = 20 ) {
		return $this->repository->list( $page, $per_page );
	}

	/**
	 * Write SQL dump into file.
	 *
	 * @param resource $handle Output handle.
	 * @return true|WP_Error
	 */
	private function write_dump( $handle ) {
		global $wpdb;

		$tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( ! is_array( $tables ) ) {
			return new WP_Error( 'openwp_backup_tables_error', __( 'Unable to enumerate database tables.', 'openwp' ) );
		}

		fwrite( $handle, "-- OpenWP backup generated at " . gmdate( 'c' ) . "\n" );
		fwrite( $handle, 'SET FOREIGN_KEY_CHECKS=0;' . "\n" );

		foreach ( $tables as $table_name ) {
			$table_name = (string) $table_name;
			fwrite( $handle, "\n-- Table: {$table_name}\n" );

			$create_row = $wpdb->get_row( 'SHOW CREATE TABLE `' . esc_sql( $table_name ) . '`', ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			if ( ! is_array( $create_row ) || empty( $create_row[1] ) ) {
				continue;
			}

			fwrite( $handle, 'DROP TABLE IF EXISTS `' . $table_name . '`;' . "\n" );
			fwrite( $handle, $create_row[1] . ';' . "\n" );

			$rows = $wpdb->get_results( 'SELECT * FROM `' . esc_sql( $table_name ) . '`', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			if ( empty( $rows ) || ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$columns = array_map(
					static function ( $col ) {
						return '`' . str_replace( '`', '``', (string) $col ) . '`';
					},
					array_keys( $row )
				);

				$values = array_map( [ $this, 'format_sql_value' ], array_values( $row ) );

				$line = 'INSERT INTO `' . $table_name . '` (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ');' . "\n";
				fwrite( $handle, $line );
			}
		}

		fwrite( $handle, 'SET FOREIGN_KEY_CHECKS=1;' . "\n" );
		return true;
	}

	/**
	 * Convert a value to SQL literal.
	 *
	 * @param mixed $value DB value.
	 * @return string
	 */
	private function format_sql_value( $value ) {
		if ( null === $value ) {
			return 'NULL';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		$text = (string) $value;
		$text = str_replace( [ "\\", "\0", "\n", "\r", "\x1a", "'", '"' ], [ "\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'", '\\"' ], $text );
		return "'{$text}'";
	}

	/**
	 * Compress file using gzip.
	 *
	 * @param string $source Source file.
	 * @param string $target Target gz file.
	 * @return true|WP_Error
	 */
	private function gzip_file( $source, $target ) {
		$contents = file_get_contents( $source );
		if ( false === $contents ) {
			return new WP_Error( 'openwp_backup_read_source', __( 'Unable to read SQL backup source file.', 'openwp' ) );
		}

		$compressed = gzencode( $contents, 6 );
		if ( false === $compressed ) {
			return new WP_Error( 'openwp_backup_compress_error', __( 'Failed to compress SQL backup.', 'openwp' ) );
		}

		$result = file_put_contents( $target, $compressed );
		if ( false === $result ) {
			return new WP_Error( 'openwp_backup_write_error', __( 'Failed to write compressed backup file.', 'openwp' ) );
		}

		return true;
	}

	/**
	 * Split SQL by semicolon while respecting line comments.
	 *
	 * @param string $sql SQL script.
	 * @return string[]
	 */
	private function split_sql_statements( $sql ) {
		$lines      = explode( "\n", $sql );
		$statements = [];
		$current    = '';

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed || 0 === strpos( $trimmed, '--' ) ) {
				continue;
			}

			$current .= $line . "\n";
			if ( preg_match( '/;\s*$/', $trimmed ) ) {
				$statements[] = $current;
				$current      = '';
			}
		}

		if ( '' !== trim( $current ) ) {
			$statements[] = $current;
		}

		return $statements;
	}
}
