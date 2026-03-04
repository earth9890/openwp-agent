/**
 * Settings screen i18n strings and palette presets.
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * Default brand palette (Royal Indigo).
 */
export const DEFAULT_PALETTE = [ '#4F46E5', '#7C3AED', '#F59E0B', '#1E1B4B', '#EEF2FF', '#C7D2FE' ];

/**
 * Curated prebuilt palettes.
 * Each entry has a `name` and a `colors` array of brand colors.
 * Order: primary, secondary, accent, then supporting (dark, light bg, border/muted).
 */
export const PRESET_PALETTES = [
	{
		name: 'Royal Indigo',
		colors: DEFAULT_PALETTE,
	},
	{
		name: 'Midnight SaaS',
		colors: [ '#0F172A', '#1E293B', '#3B82F6', '#22D3EE', '#A78BFA', '#F8FAFC', '#334155' ],
	},
	{
		name: 'Sunset Startup',
		colors: [ '#F97316', '#FB7185', '#FACC15', '#1F2937', '#FFF7ED', '#FED7AA' ],
	},
	{
		name: 'Emerald Tech',
		colors: [ '#059669', '#10B981', '#34D399', '#064E3B', '#ECFDF5', '#A7F3D0' ],
	},
	{
		name: 'Arctic Minimal',
		colors: [ '#111827', '#374151', '#2563EB', '#F9FAFB', '#E5E7EB', '#6B7280' ],
	},
	{
		name: 'Cyber Neon',
		colors: [ '#0F0F1B', '#1A1A2E', '#00F5FF', '#A855F7', '#FF2E63', '#EAEAEA' ],
	},
	{
		name: 'Earthy Modern',
		colors: [ '#3F6212', '#65A30D', '#D97706', '#F7FEE7', '#1A2E05', '#E7E5E4' ],
	},
];

export const strings = {
	settingsTitle: () => __( 'Settings', 'openwp' ),
	settingsSubtitle: () =>
		__(
			'Configure providers, execution limits, and guardrails for reliable OpenWP runs.',
			'openwp'
		),
	saveSettings: () => __( 'Save Settings', 'openwp' ),

	// Section labels
	defaults: () => __( 'Global Defaults', 'openwp' ),
	connections: () => __( 'Provider Connections', 'openwp' ),
	rateLimits: () => __( 'Rate Limits', 'openwp' ),
	providerKeys: () => __( 'Provider Keys (BYOK)', 'openwp' ),

	// Shared labels
	defaultProvider: () => __( 'Default Provider', 'openwp' ),
	httpTimeout: () => __( 'HTTP Timeout (seconds)', 'openwp' ),
	status: () => __( 'Status', 'openwp' ),
	enabled: () => __( 'Enabled', 'openwp' ),
	disabled: () => __( 'Disabled', 'openwp' ),
	memoryFeature: () => __( 'Agent Memory', 'openwp' ),
	memoryFeatureHint: () =>
		__(
			'Allow explicit remember/forget actions and memory context retrieval.',
			'openwp'
		),
	userActionsPerDay: () => __( 'User Actions / Day', 'openwp' ),
	siteActionsPerDay: () => __( 'Site Actions / Day', 'openwp' ),
	providerModel: () => __( 'Default Model', 'openwp' ),

	// Provider labels
	openai: () => __( 'OpenAI', 'openwp' ),
	anthropic: () => __( 'Anthropic', 'openwp' ),
	glm: () => __( 'GLM (Z.AI)', 'openwp' ),
	openrouter: () => __( 'OpenRouter', 'openwp' ),
	openaiApiKey: () => __( 'OpenAI API Key', 'openwp' ),
	anthropicApiKey: () => __( 'Anthropic API Key', 'openwp' ),
	glmApiKey: () => __( 'GLM API Key', 'openwp' ),
	openrouterApiKey: () => __( 'OpenRouter API Key', 'openwp' ),

	// Field hints
	hintSecondsPerRequest: () =>
		__( 'Recommended: 30–90 seconds per request.', 'openwp' ),
	hintPerUser: () => __( 'Daily cap for each WordPress user.', 'openwp' ),
	hintAcrossUsers: () =>
		__( 'Daily cap across all users on this site.', 'openwp' ),
	storedKey: ( masked ) =>
		sprintf(
			/* translators: %s: masked API key */
			__( 'Stored: %s', 'openwp' ),
			masked
		),
	notSet: () => __( 'No key saved yet', 'openwp' ),

	// Validation
	requiredField: () => __( 'This field is required.', 'openwp' ),
	numberMin: ( min ) =>
		sprintf(
			/* translators: %d: minimum number */
			__( 'Must be at least %d.', 'openwp' ),
			min
		),
	numberRange: ( min, max ) =>
		sprintf(
			/* translators: 1: minimum number, 2: maximum number */
			__( 'Must be between %1$d and %2$d.', 'openwp' ),
			min,
			max
		),
	fixValidationErrors: () =>
		__( 'Fix validation errors before saving.', 'openwp' ),

	// Connection testing
	testConnection: () => __( 'Test Connection', 'openwp' ),
	testing: () => __( 'Testing…', 'openwp' ),
	connectionReady: () => __( 'Connection Ready', 'openwp' ),
	connectionMissing: () => __( 'Key Missing', 'openwp' ),
	connectionSuccess: () => __( 'Connection successful.', 'openwp' ),
	connectionFailed: () => __( 'Connection failed.', 'openwp' ),
	testedWithModel: ( model ) =>
		sprintf(
			/* translators: %s: model slug */
			__( 'Tested with model: %s', 'openwp' ),
			model
		),
	saveToTestNotice: () =>
		__( 'Save provider key first, then run a connection test.', 'openwp' ),

	// Content generation section
	contentGeneration: () => __( 'Content Generation', 'openwp' ),
	mcp: () => __( 'MCP Server', 'openwp' ),
	contentGenerationSubtitle: () =>
		__( 'Set default options and brand colors used when generating content in the block editor.', 'openwp' ),
	themePalette: () => __( 'Theme Color Palette', 'openwp' ),
	themePaletteHint: () =>
		__( 'Brand color palette passed to the AI when generating content. Pick a preset or build your own.', 'openwp' ),
	palettePresets: () => __( 'Presets', 'openwp' ),
	paletteCustom: () => __( 'Custom Colors', 'openwp' ),
	addColor: () => __( '+ Add Color', 'openwp' ),
	clearPalette: () => __( 'Reset to Default', 'openwp' ),
	defaultContentType: () => __( 'Default Content Type', 'openwp' ),
	defaultContentTypeHint: () =>
		__( 'Pre-selected type when opening the AI Content Generator block.', 'openwp' ),
	defaultTone: () => __( 'Default Tone', 'openwp' ),
	defaultToneHint: () =>
		__( 'Pre-selected writing tone for generated content.', 'openwp' ),

	// MCP section
	mcpTitle: () => __( 'MCP Server', 'openwp' ),
	mcpSubtitle: () =>
		__( 'Enable OpenWP MCP, manage bearer auth, toggle modules, and copy client setup snippets.', 'openwp' ),
	mcpEnabled: () => __( 'Enable MCP API', 'openwp' ),
	mcpEnabledHint: () =>
		__( 'Registers /wp-json/mcp/v1 endpoints for MCP clients.', 'openwp' ),
	mcpDebug: () => __( 'Debug Mode', 'openwp' ),
	mcpDebugHint: () =>
		__( 'Adds verbose diagnostics for MCP runtime behavior.', 'openwp' ),
	mcpBearerToken: () => __( 'Bearer Token', 'openwp' ),
	mcpBearerHint: () =>
		__( 'Required for all MCP requests. Stored encrypted at rest.', 'openwp' ),
	mcpTokenConfigured: () => __( 'Token configured', 'openwp' ),
	mcpTokenMissing: () => __( 'No token configured', 'openwp' ),
	mcpGenerateToken: () => __( 'Generate', 'openwp' ),
	mcpRevokeToken: () => __( 'Revoke', 'openwp' ),
	mcpCopyHttpEndpoint: () => __( 'Copy HTTP Endpoint', 'openwp' ),
	mcpCopySseEndpoint: () => __( 'Copy SSE Endpoint', 'openwp' ),
	mcpModuleToggles: () => __( 'Module Toggles', 'openwp' ),
	mcpModuleStatus: () => __( 'Availability and Tool Counts', 'openwp' ),
	mcpClientSteps: () => __( 'Quick Setup Steps', 'openwp' ),
	mcpSnippet: () => __( 'Client Snippet', 'openwp' ),
	mcpSnippetJson: () => __( 'MCP JSON config', 'openwp' ),
	mcpSnippetClaudeCode: () => __( 'Claude Code CLI', 'openwp' ),
	mcpSnippetCurlInit: () => __( 'cURL: initialize', 'openwp' ),
	mcpSnippetCurlTools: () => __( 'cURL: tools/list', 'openwp' ),
	mcpSnippetSse: () => __( 'SSE message transport', 'openwp' ),
	mcpCopySnippet: () => __( 'Copy Snippet', 'openwp' ),
	mcpStepOne: () => __( '1) Enable MCP API and save settings.', 'openwp' ),
	mcpStepTwo: () => __( '2) Generate or paste a bearer token, then save.', 'openwp' ),
	mcpStepThree: () => __( '3) Copy HTTP endpoint + snippet into Claude/ChatGPT MCP config.', 'openwp' ),
	mcpStepFour: () => __( '4) Test with initialize and tools/list.', 'openwp' ),
	mcpModuleCore: () => __( 'Core', 'openwp' ),
	mcpModuleWoo: () => __( 'WooCommerce', 'openwp' ),
	mcpModulePlugin: () => __( 'Plugins', 'openwp' ),
	mcpModuleTheme: () => __( 'Themes', 'openwp' ),
	mcpModuleDb: () => __( 'Database', 'openwp' ),
	mcpModulePolylang: () => __( 'Polylang', 'openwp' ),
	mcpAvailable: () => __( 'Available', 'openwp' ),
	mcpUnavailable: () => __( 'Unavailable', 'openwp' ),
	mcpTools: ( count ) =>
		sprintf(
			/* translators: %d: tool count */
			__( '%d tools', 'openwp' ),
			count
		),

	// Content type labels
	heroSection: () => __( 'Hero Section', 'openwp' ),
	featureSection: () => __( 'Feature Section', 'openwp' ),
	faqSection: () => __( 'FAQ Section', 'openwp' ),
	ctaSection: () => __( 'Call to Action', 'openwp' ),
	blogIntro: () => __( 'Blog Intro', 'openwp' ),
	testimonialsSection: () => __( 'Testimonials Section', 'openwp' ),
	pricingSection: () => __( 'Pricing Section', 'openwp' ),
	aboutSection: () => __( 'About / Team Section', 'openwp' ),
	newsletterSection: () => __( 'Newsletter Signup', 'openwp' ),
	contactSection: () => __( 'Contact Section', 'openwp' ),
	fullLandingPage: () => __( 'Full Landing Page', 'openwp' ),
	fullBlogPost: () => __( 'Full Blog Post', 'openwp' ),
	fullAboutPage: () => __( 'Full About Page', 'openwp' ),
	fullServicesPage: () => __( 'Full Services Page', 'openwp' ),
	fullContactPage: () => __( 'Full Contact Page', 'openwp' ),

	// Tone labels
	toneProfessional: () => __( 'Professional', 'openwp' ),
	toneFriendly: () => __( 'Friendly', 'openwp' ),
	tonePersuasive: () => __( 'Persuasive', 'openwp' ),
	toneCasual: () => __( 'Casual', 'openwp' ),
	toneTechnical: () => __( 'Technical', 'openwp' ),

	// Sticky bar
	unsavedChanges: () => __( 'You have unsaved changes.', 'openwp' ),
	allChangesSaved: () => __( 'All changes are saved.', 'openwp' ),
};

/**
 * Curated color names for closest-match lookup.
 * Each entry is [name, hex]. Covers the full spectrum with recognisable labels.
 */
export const COLOR_NAMES = [
	[ 'Black', '#000000' ],
	[ 'Charcoal', '#36454f' ],
	[ 'Dark Gray', '#444444' ],
	[ 'Dim Gray', '#696969' ],
	[ 'Gray', '#808080' ],
	[ 'Dark Silver', '#999999' ],
	[ 'Silver', '#c0c0c0' ],
	[ 'Light Gray', '#d3d3d3' ],
	[ 'Gainsboro', '#dcdcdc' ],
	[ 'White Smoke', '#f5f5f5' ],
	[ 'White', '#ffffff' ],
	[ 'Snow', '#fffafa' ],
	[ 'Ivory', '#fffff0' ],
	[ 'Linen', '#faf0e6' ],
	[ 'Beige', '#f5f5dc' ],
	[ 'Cream', '#fffdd0' ],
	[ 'Cornsilk', '#fff8dc' ],
	[ 'Lemon', '#fff44f' ],
	[ 'Yellow', '#ffff00' ],
	[ 'Gold', '#ffd700' ],
	[ 'Amber', '#ffbf00' ],
	[ 'Orange', '#ff8c00' ],
	[ 'Dark Orange', '#ff6600' ],
	[ 'Tangerine', '#ff9966' ],
	[ 'Peach', '#ffcba4' ],
	[ 'Apricot', '#fbceb1' ],
	[ 'Salmon', '#fa8072' ],
	[ 'Coral', '#ff7f50' ],
	[ 'Tomato', '#ff6347' ],
	[ 'Red', '#ff0000' ],
	[ 'Crimson', '#dc143c' ],
	[ 'Dark Red', '#8b0000' ],
	[ 'Maroon', '#800000' ],
	[ 'Berry', '#8e4585' ],
	[ 'Rose', '#ff007f' ],
	[ 'Hot Pink', '#ff69b4' ],
	[ 'Pink', '#ffc0cb' ],
	[ 'Blush', '#de5d83' ],
	[ 'Magenta', '#ff00ff' ],
	[ 'Fuchsia', '#c154c1' ],
	[ 'Purple', '#800080' ],
	[ 'Dark Purple', '#301934' ],
	[ 'Plum', '#dda0dd' ],
	[ 'Violet', '#8b00ff' ],
	[ 'Lavender', '#e6e6fa' ],
	[ 'Orchid', '#da70d6' ],
	[ 'Mauve', '#e0b0ff' ],
	[ 'Indigo', '#4b0082' ],
	[ 'Navy', '#000080' ],
	[ 'Dark Blue', '#00008b' ],
	[ 'Blue', '#0000ff' ],
	[ 'Royal Blue', '#4169e1' ],
	[ 'Cobalt', '#0047ab' ],
	[ 'Dodger Blue', '#1e90ff' ],
	[ 'Cornflower', '#6495ed' ],
	[ 'Steel Blue', '#4682b4' ],
	[ 'Sky Blue', '#87ceeb' ],
	[ 'Light Blue', '#add8e6' ],
	[ 'Powder Blue', '#b0e0e6' ],
	[ 'Alice Blue', '#f0f8ff' ],
	[ 'Cyan', '#00ffff' ],
	[ 'Aqua', '#00e5ff' ],
	[ 'Turquoise', '#40e0d0' ],
	[ 'Teal', '#008080' ],
	[ 'Dark Teal', '#003f3f' ],
	[ 'Mint', '#98ff98' ],
	[ 'Seafoam', '#93e9be' ],
	[ 'Emerald', '#50c878' ],
	[ 'Green', '#008000' ],
	[ 'Lime', '#00ff00' ],
	[ 'Chartreuse', '#7fff00' ],
	[ 'Spring Green', '#00ff7f' ],
	[ 'Dark Green', '#006400' ],
	[ 'Forest Green', '#228b22' ],
	[ 'Olive', '#808000' ],
	[ 'Dark Olive', '#556b2f' ],
	[ 'Sage', '#b2ac88' ],
	[ 'Khaki', '#c3b091' ],
	[ 'Tan', '#d2b48c' ],
	[ 'Sand', '#c2b280' ],
	[ 'Wheat', '#f5deb3' ],
	[ 'Buff', '#f0dc82' ],
	[ 'Brown', '#8b4513' ],
	[ 'Chocolate', '#7b3f00' ],
	[ 'Sienna', '#a0522d' ],
	[ 'Coffee', '#6f4e37' ],
	[ 'Walnut', '#5c4033' ],
	[ 'Mahogany', '#c04000' ],
	[ 'Rust', '#b7410e' ],
	[ 'Copper', '#b87333' ],
	[ 'Bronze', '#cd7f32' ],
];

export const getProviderMeta = () => [
	{
		key: 'openrouter',
		label: strings.openrouter(),
		keyLabel: strings.openrouterApiKey(),
		placeholder: 'sk-or-v1-…',
		modelKey: 'default_model_openrouter',
		modelPlaceholder: 'anthropic/claude-sonnet-4-5',
	},
	{
		key: 'openai',
		label: strings.openai(),
		keyLabel: strings.openaiApiKey(),
		placeholder: 'sk-...',
		modelKey: 'default_model_openai',
		modelPlaceholder: 'gpt-5.2',
	},
	{
		key: 'anthropic',
		label: strings.anthropic(),
		keyLabel: strings.anthropicApiKey(),
		placeholder: 'sk-ant-...',
		modelKey: 'default_model_anthropic',
		modelPlaceholder: 'claude-3-5-sonnet-latest',
	},
	{
		key: 'glm',
		label: strings.glm(),
		keyLabel: strings.glmApiKey(),
		placeholder: 'Bearer token…',
		modelKey: 'default_model_glm',
		modelPlaceholder: 'glm-5',
	},
];
