/**
 * Side nav component i18n strings.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

export const TAB_KEYS = [
	'dashboard',
	'console',
	'approvals',
	'settings',
	'actions',
	'memory',
	'logs',
	'backups',
];

export const getTabLabel = ( key ) => {
	const labels = {
		dashboard: __( 'Dashboard', 'openwp' ),
		console: __( 'Console', 'openwp' ),
		approvals: __( 'Approvals', 'openwp' ),
		settings: __( 'Settings', 'openwp' ),
		actions: __( 'Actions', 'openwp' ),
		memory: __( 'Memory', 'openwp' ),
		logs: __( 'Logs', 'openwp' ),
		backups: __( 'Backups', 'openwp' ),
	};
	return labels[ key ] || key;
};

export const getTabDescription = ( key ) => {
	const descriptions = {
		dashboard: __( 'Overview of controls, usage, and status.', 'openwp' ),
		console: __( 'Run commands and inspect agent output.', 'openwp' ),
		approvals: __( 'Review pending guarded actions.', 'openwp' ),
		settings: __(
			'Configure providers, limits, and guardrails.',
			'openwp'
		),
		actions: __( 'Control policies by action risk.', 'openwp' ),
		memory: __( 'Store and manage explicit agent memory.', 'openwp' ),
		logs: __( 'Audit every execution and rollback.', 'openwp' ),
		backups: __( 'Manage restore checkpoints.', 'openwp' ),
	};
	return descriptions[ key ] || '';
};

export const getTabs = ( metrics = {} ) =>
	TAB_KEYS.map( ( key ) => {
		let count = null;
		if ( key === 'approvals' ) {
			count = Number( metrics.pending || 0 );
		} else if ( key === 'memory' ) {
			count = Number( metrics.memory || 0 );
		} else if ( key === 'logs' ) {
			count = Number( metrics.logs || 0 );
		} else if ( key === 'backups' ) {
			count = Number( metrics.backups || 0 );
		}

		return {
			key,
			label: getTabLabel( key ),
			description: getTabDescription( key ),
			count,
		};
	} );
