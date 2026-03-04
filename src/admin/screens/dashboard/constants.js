/**
 * Dashboard screen i18n strings.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';

export const strings = {
	overviewTitle: () => __( 'System Overview', 'openwp' ),
	overviewSubtitle: () =>
		__(
			'Current runtime defaults and capabilities for this site.',
			'openwp'
	),
	defaultProvider: () => __( 'Default Provider', 'openwp' ),
	defaultModel: () => __( 'Default Model', 'openwp' ),
	agentCapability: () => __( 'Can Run Agent', 'openwp' ),
	yes: () => __( 'Yes', 'openwp' ),
	no: () => __( 'No', 'openwp' ),
};
