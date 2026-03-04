/**
 * Console screen i18n strings.
 *
 * @package OpenWP
 */

import { __, sprintf } from '@wordpress/i18n';

export const strings = {
	commandConsole: () => __('Command Console', 'openwp'),
	commandConsoleSubtitle: () => __('Run one command and OpenWP executes the required ordered action plan with guardrails.', 'openwp'),
	refreshData: () => __('Refresh Data', 'openwp'),
	runCommand: () => __('Run Command', 'openwp'),
	running: () => __('Running...', 'openwp'),
	templates: () => __('Templates', 'openwp'),
	promptPlaceholder: () => __('Ask OpenWP to do one thing, clearly. Example: "Create a draft post named Weekly SEO Roundup with a summary and 3 bullet points."', 'openwp'),
	providerLabel: () => __('Provider', 'openwp'),
	modelLabel: () => __('Model', 'openwp'),
	execute: () => __('Execute', 'openwp'),
	modelPlaceholder: () => __('Model name', 'openwp'),
	tokens: () => __('Tokens:', 'openwp'),
	tokensIn: () => __('in', 'openwp'),
	tokensOut: () => __('out', 'openwp'),
	termination: () => __('Termination:', 'openwp'),
	steps: () => __('Steps:', 'openwp'),
	step: (n) => sprintf(
		/* translators: %d: step number */
		__('Step %d:', 'openwp'),
		n
	),
	openai: () => __('OpenAI', 'openwp'),
	anthropic: () => __('Anthropic', 'openwp'),
	glm: () => __('GLM (Z.AI)', 'openwp'),
	openrouter: () => __('OpenRouter', 'openwp'),

	// Inline approval
	pendingApproval: () => __('Pending Approval', 'openwp'),
	pendingApprovalMessage: () => __('This action requires approval before it can be executed.', 'openwp'),
	approve: () => __('Approve', 'openwp'),
	reject: () => __('Reject', 'openwp'),
	typeApprovePrompt: () => __('Type APPROVE to confirm this critical action', 'openwp'),
	actionKey: () => __('Action:', 'openwp'),
	riskLevel: () => __('Risk:', 'openwp'),
	conversationContext: () => __('Conversation Context', 'openwp'),
	usePreviousTurnContext: () => __('Use previous turn context', 'openwp'),
	clearConversation: () => __('Clear Conversation', 'openwp'),
	conversationEmpty: () => __('No conversation turns yet.', 'openwp'),
	user: () => __('User', 'openwp'),
	assistant: () => __('Assistant', 'openwp'),
	mcpToolCatalog: () => __('MCP Tool Catalog', 'openwp'),
	mcpToolsSubtitle: () => __('Available tools from connected MCP servers. Click "Use" to build a prompt.', 'openwp'),
	mcpSearch: () => __('Search tools…', 'openwp'),
	mcpUse: () => __('Use', 'openwp'),
	mcpNoTools: () => __('No MCP tools available.', 'openwp'),
	mcpTemplates: () => __('MCP Templates', 'openwp'),
};

export const getPromptTemplates = () => [
	__(
		'Create a detailed blog post draft about the banking sector, including current trends, risks, and opportunities.',
		'openwp'
	),
	__(
		'List all active plugins with version, author, and whether an update is available.',
		'openwp'
	),
	__(
		'List my recent WooCommerce orders with order number, status, total, and customer name.',
		'openwp'
	),
];

export const getMcpPromptTemplates = () => [
	{ label: __('WooCommerce', 'openwp'), prompt: __('List my 5 most recent WooCommerce orders with their status and total.', 'openwp') },
	{ label: __('Plugin Files', 'openwp'), prompt: __('Show me the main file of the Yoast SEO plugin.', 'openwp') },
	{ label: __('DB Query', 'openwp'), prompt: __('Run a database query to count posts by status (publish, draft, trash).', 'openwp') },
	{ label: __('Theme Info', 'openwp'), prompt: __('List all installed themes and show which one is active.', 'openwp') },
	{ label: __('Product Stock', 'openwp'), prompt: __('Show WooCommerce products that are low on stock (less than 5 units).', 'openwp') },
	{ label: __('Site Options', 'openwp'), prompt: __('Get the value of the blogname and blogdescription options.', 'openwp') },
];
