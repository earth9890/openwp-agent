/**
 * App-level i18n strings (app.jsx).
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * Notification and error messages.
 */
export const messages = {
	requestFailed: () => __( 'Request failed', 'openwp' ),
	panelsFailedToLoad: ( errors ) =>
		sprintf(
			/* translators: %s: error messages */
			__( 'Some panels failed to load: %s', 'openwp' ),
			errors
		),
	somethingWentWrong: () => __( 'Something went wrong.', 'openwp' ),
	enterPrompt: () => __( 'Please enter a prompt.', 'openwp' ),
	commandProcessed: () => __( 'Command processed.', 'openwp' ),
	approvalSubmitted: () => __( 'Approval submitted.', 'openwp' ),
	actionRejected: () => __( 'Action rejected.', 'openwp' ),
	rollbackExecuted: () => __( 'Rollback executed.', 'openwp' ),
	backupRestorePrompt: ( id ) =>
		sprintf(
			/* translators: %d: backup ID */
			__( 'Type APPROVE to restore backup #%d', 'openwp' ),
			id
		),
	backupRestoreSubmitted: () => __( 'Backup restore submitted.', 'openwp' ),
	memorySaved: () => __( 'Memory saved.', 'openwp' ),
	memoryDeleted: () => __( 'Memory deleted.', 'openwp' ),
	settingsSaved: () => __( 'Settings saved.', 'openwp' ),
	policySaved: () => __( 'Policy saved.', 'openwp' ),
	typeApproveToContinue: () => __( 'Type APPROVE to continue.', 'openwp' ),
	confirmApprovalTitle: () => __( 'Confirm Approval', 'openwp' ),
	confirmApprovalDescription: () =>
		__( 'Approve this action and allow execution to continue.', 'openwp' ),
	confirmCriticalApprovalDescription: () =>
		__(
			'This is a critical action. Typed confirmation is required.',
			'openwp'
		),
	confirmRejectTitle: () => __( 'Reject Action', 'openwp' ),
	confirmRejectDescription: () =>
		__( 'Reject this queued action. This cannot be undone.', 'openwp' ),
	confirmRollbackTitle: () => __( 'Confirm Rollback', 'openwp' ),
	confirmRollbackDescription: () =>
		__( 'Rollback this execution log using its snapshot.', 'openwp' ),
	confirmBackupRestoreTitle: ( id ) =>
		sprintf(
			/* translators: %d: backup ID */
			__( 'Restore Backup #%d', 'openwp' ),
			id
		),
	confirmBackupRestoreDescription: () =>
		__(
			'Restoring a backup can overwrite current site state. Typed confirmation is required.',
			'openwp'
		),
	cancel: () => __( 'Cancel', 'openwp' ),
	confirm: () => __( 'Confirm', 'openwp' ),
	typeApproveLabel: () => __( 'Type APPROVE', 'openwp' ),
};

/**
 * UI labels for footer and app-level display.
 */
export const strings = {
	working: () => __( 'Working…', 'openwp' ),
	provider: () => __( 'Provider:', 'openwp' ),
	model: () => __( 'Model:', 'openwp' ),
	canRunAgent: () => __( 'Can run agent:', 'openwp' ),
	yes: () => __( 'yes', 'openwp' ),
	no: () => __( 'no', 'openwp' ),
};

/**
 * Footer link labels.
 */
export const FOOTER_LINKS = {
	docs: __( 'Docs', 'openwp' ),
	github: __( 'GitHub', 'openwp' ),
};
