/**
 * Header component i18n strings.
 *
 * @package OpenWP
 */

import { __ } from '@wordpress/i18n';

export const strings = {
	brandKicker: () => __('WordPress Agent Control Plane', 'openwp'),
	brandTitle: () => __('OpenWP', 'openwp'),
	brandSubtitle: () => __('Manage your WordPress site through guarded AI actions with full auditability.', 'openwp'),
	registeredActions: () => __('Registered Actions', 'openwp'),
	pendingApprovals: () => __('Pending Approvals', 'openwp'),
	recentLogs: () => __('Recent Logs', 'openwp'),
	backups: () => __('Backups', 'openwp'),
	actionRegistry: () => __('Action registry', 'openwp'),
	needsAdminDecision: () => __('Needs admin decision', 'openwp'),
	auditVisibility: () => __('Audit visibility', 'openwp'),
	recoveryCheckpoints: () => __('Recovery checkpoints', 'openwp'),
};
