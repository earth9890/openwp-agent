/**
 * Backups screen i18n strings.
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';

export const strings = {
	backupsTitle: () => __( 'Backups', 'openwp' ),
	backupsSubtitle: () =>
		__(
			'System-generated backup snapshots for guarded write actions.',
			'openwp'
		),
	restoreCritical: () => __( 'Restore (critical)', 'openwp' ),
	backupId: () => __( 'Backup ID', 'openwp' ),
	status: () => __( 'Status', 'openwp' ),
	size: () => __( 'Size', 'openwp' ),
	created: () => __( 'Created', 'openwp' ),
	checksumLabel: () => __( 'Checksum', 'openwp' ),
	action: () => __( 'Action', 'openwp' ),
	noBackupsYet: () => __( 'No backups yet', 'openwp' ),
	noBackupsYetMessage: () =>
		__(
			'Backups are created before high/critical write actions.',
			'openwp'
		),
	backupNumber: ( id ) =>
		sprintf(
			/* translators: %d: backup ID */
			__( 'Backup #%d', 'openwp' ),
			id
		),
	sizeBytes: ( size ) =>
		sprintf(
			/* translators: %s: file size */
			__( 'size: %s bytes', 'openwp' ),
			size
		),
	checksum: () => __( 'checksum:', 'openwp' ),
};
