/**
 * Approvals screen i18n strings.
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';

export const strings = {
	approvalQueue: () => __( 'Approval Queue', 'openwp' ),
	approvalQueueSubtitle: () =>
		__(
			'Approve or reject queued actions. Critical actions require typed confirmation.',
			'openwp'
		),
	approve: () => __( 'Approve', 'openwp' ),
	reject: () => __( 'Reject', 'openwp' ),
	id: () => __( 'ID', 'openwp' ),
	action: () => __( 'Action', 'openwp' ),
	status: () => __( 'Status', 'openwp' ),
	created: () => __( 'Created', 'openwp' ),
	decision: () => __( 'Decision', 'openwp' ),
	typeApprovePrompt: () =>
		__( 'Type APPROVE to confirm this critical action', 'openwp' ),
	requiresTypedApproval: () =>
		__( 'Requires typed APPROVE confirmation.', 'openwp' ),
	openDetails: () => __( 'Open Details', 'openwp' ),
	hideDetails: () => __( 'Hide Details', 'openwp' ),
	noPendingApprovals: () => __( 'No pending approvals', 'openwp' ),
	noPendingApprovalsMessage: () =>
		__(
			'When guarded actions are queued, they will appear here.',
			'openwp'
		),
	requestNumber: ( id ) =>
		sprintf(
			/* translators: %d: request ID */
			__( 'Request #%d', 'openwp' ),
			id
		),
};
