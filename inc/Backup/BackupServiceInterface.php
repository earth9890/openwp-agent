<?php
/**
 * Backup service interface.
 *
 * @package OpenWP\Inc\Backup
 */

namespace OpenWP\Inc\Backup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for backup engines.
 */
interface BackupServiceInterface {
	/**
	 * Create backup.
	 *
	 * @param int    $user_id User id.
	 * @param string $reason Reason string.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function create( $user_id, $reason = '' );
}
