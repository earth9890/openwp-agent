/**
 * Logs screen i18n strings.
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';

export const strings = {
	executionLogs: () => __( 'Execution Logs', 'openwp' ),
	executionLogsSubtitle: () =>
		__(
			'Audit trail of all OpenWP action attempts and outcomes.',
			'openwp'
		),
	rollback: () => __( 'Rollback', 'openwp' ),
	noLogsYet: () => __( 'No logs yet', 'openwp' ),
	noLogsYetMessage: () =>
		__( 'Run a command to generate execution logs.', 'openwp' ),
	logNumber: ( id ) =>
		sprintf(
			/* translators: %d: log ID */
			__( 'Log #%d', 'openwp' ),
			id
		),
	filterByAction: () => __( 'Filter by action…', 'openwp' ),
	status: () => __( 'Status', 'openwp' ),
	allStatuses: () => __( 'All statuses', 'openwp' ),
	allRisks: () => __( 'All risks', 'openwp' ),
	risk: () => __( 'Risk', 'openwp' ),
	id: () => __( 'ID', 'openwp' ),
	action: () => __( 'Action', 'openwp' ),
	created: () => __( 'Created', 'openwp' ),
	details: () => __( 'Details', 'openwp' ),
	outcome: () => __( 'Outcome', 'openwp' ),
	rollbackAvailable: () => __( 'Rollback available', 'openwp' ),
	rollbackUnavailable: () => __( 'No snapshot', 'openwp' ),
	expand: () => __( 'Expand', 'openwp' ),
	collapse: () => __( 'Collapse', 'openwp' ),
	noMatchingLogs: () => __( 'No logs match your filter.', 'openwp' ),
};
