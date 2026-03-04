/**
 * Actions screen i18n strings.
 *
 * @package
 */

import { __, sprintf, _n } from '@wordpress/i18n';

export const strings = {
	actionPolicy: () => __( 'Action Policy', 'openwp' ),
	actionPolicySubtitle: () =>
		__(
			'Control which actions the AI agent can execute and which require human approval before running.',
			'openwp'
		),
	savePolicy: () => __( 'Save Policy', 'openwp' ),
	searchActionsPlaceholder: () => __( 'Search actions…', 'openwp' ),
	enabled: () => __( 'Enabled', 'openwp' ),
	approval: () => __( 'Approval', 'openwp' ),
	on: () => __( 'On', 'openwp' ),
	off: () => __( 'Off', 'openwp' ),
	required: () => __( 'Required', 'openwp' ),
	optional: () => __( 'Optional', 'openwp' ),
	risk: () => __( 'Risk', 'openwp' ),
	unsavedChanges: () => __( 'Policy changes are not saved yet.', 'openwp' ),
	allChangesSaved: () => __( 'Policy is saved.', 'openwp' ),
	noActionsRegistered: () => __( 'No actions registered', 'openwp' ),
	noActionsRegisteredMessage: () =>
		__(
			'Actions appear here once the plugin initialises its action registry.',
			'openwp'
		),
	noMatches: () => __( 'No matches', 'openwp' ),
	noActionMatches: ( filter ) =>
		sprintf(
			/* translators: %s: search filter text */
			__( 'No action matches "%s".', 'openwp' ),
			filter
		),
	riskLabel: ( risk ) =>
		sprintf(
			/* translators: %s: risk level (e.g., "high", "low") */
			__( '%s Risk', 'openwp' ),
			risk.charAt( 0 ).toUpperCase() + risk.slice( 1 )
		),
	actionsCount: ( count ) =>
		sprintf(
			/* translators: %d: number of actions */
			_n( '%d action', '%d actions', count, 'openwp' ),
			count
		),
};
