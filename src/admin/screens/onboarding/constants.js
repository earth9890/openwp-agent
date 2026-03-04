import { __ } from '@wordpress/i18n';

export const ONBOARDING_STEPS = [
	{
		key: 'onboarding/welcome',
		label: __( 'Welcome', 'openwp' ),
		title: __( 'Welcome to OpenWP', 'openwp' ),
		subtitle: __(
			'Your AI-powered WordPress operating system',
			'openwp'
		),
	},
	{
		key: 'onboarding/provider',
		label: __( 'Provider', 'openwp' ),
		title: __( 'Connect Your AI Provider', 'openwp' ),
		subtitle: __(
			'Select a provider, choose a model, and enter your API key.',
			'openwp'
		),
	},
	{
		key: 'onboarding/guardrails',
		label: __( 'Guardrails', 'openwp' ),
		title: __( 'Set Safety Guardrails', 'openwp' ),
		subtitle: __(
			'Choose how much control you want over AI actions.',
			'openwp'
		),
	},
	{
		key: 'onboarding/test-command',
		label: __( 'Test', 'openwp' ),
		title: __( 'Run a Test Command', 'openwp' ),
		subtitle: __(
			'Verify everything works with a safe read-only command.',
			'openwp'
		),
	},
	{
		key: 'onboarding/done',
		label: __( 'Done', 'openwp' ),
		title: __( "You're All Set!", 'openwp' ),
		subtitle: __(
			'OpenWP is configured and ready to use.',
			'openwp'
		),
	},
];

export const ONBOARDING_ROUTE_SET = new Set(
	ONBOARDING_STEPS.map( ( step ) => step.key )
);

export const ONBOARDING_REQUIREMENTS = {
	'onboarding/provider': [],
	'onboarding/guardrails': [ 'provider_saved', 'connection_test_passed' ],
	'onboarding/test-command': [ 'guardrail_preset_saved' ],
	'onboarding/done': [ 'first_command_passed' ],
};

export const GUARDRAIL_PRESETS = {
	balanced: {
		label: __( 'Balanced', 'openwp' ),
		description: __( 'Safe defaults for most teams.', 'openwp' ),
		note: __(
			'Approvals for medium/high/critical risk.',
			'openwp'
		),
		icon: 'scale',
		settings: {
			medium_requires_approval: true,
			high_requires_approval: true,
			critical_requires_approval: true,
			max_actions_user_day: 50,
			max_actions_site_day: 500,
		},
	},
	strict: {
		label: __( 'Strict', 'openwp' ),
		description: __(
			'Maximum control for sensitive sites.',
			'openwp'
		),
		note: __(
			'All risk levels require approval.',
			'openwp'
		),
		icon: 'shield',
		settings: {
			medium_requires_approval: true,
			high_requires_approval: true,
			critical_requires_approval: true,
			max_actions_user_day: 25,
			max_actions_site_day: 250,
		},
	},
	fast: {
		label: __( 'Fast', 'openwp' ),
		description: __(
			'Move faster with fewer approval gates.',
			'openwp'
		),
		note: __(
			'Only high/critical need approval.',
			'openwp'
		),
		icon: 'zap',
		settings: {
			medium_requires_approval: false,
			high_requires_approval: true,
			critical_requires_approval: true,
			max_actions_user_day: 100,
			max_actions_site_day: 1000,
		},
	},
};

export const DEFAULT_TEST_PROMPT =
	'List the latest 5 posts with ID, title, status, and date.';

export function providerModelKey( provider ) {
	if ( provider === 'anthropic' ) {
		return 'default_model_anthropic';
	}

	if ( provider === 'glm' ) {
		return 'default_model_glm';
	}

	if ( provider === 'openrouter' ) {
		return 'default_model_openrouter';
	}

	return 'default_model_openai';
}
