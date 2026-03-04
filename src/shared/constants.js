/**
 * Tab keys - used for validation and routing.
 * Labels are resolved in components via local constants.
 */
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

/**
 * Onboarding route keys (hash-based).
 */
export const ONBOARDING_ROUTE_KEYS = [
	'onboarding/welcome',
	'onboarding/provider',
	'onboarding/guardrails',
	'onboarding/test-command',
	'onboarding/done',
];

/**
 * All allowed hash routes in the admin app.
 */
export const APP_ROUTE_KEYS = [ ...TAB_KEYS, ...ONBOARDING_ROUTE_KEYS ];

/**
 * OpenAI model options.
 */
export const OPENAI_MODELS = [
	{ value: 'gpt-5.2', label: 'gpt-5.2' },
	{ value: 'gpt-5.1', label: 'gpt-5.1' },
	{ value: 'gpt-5', label: 'gpt-5' },
	{ value: 'gpt-5-mini', label: 'gpt-5-mini' },
	{ value: 'gpt-5-nano', label: 'gpt-5-nano' },
	{ value: 'gpt-4o', label: 'gpt-4o' },
	{ value: 'gpt-4o-mini', label: 'gpt-4o-mini' },
];

/**
 * OpenRouter model options.
 */
export const OPENROUTER_MODELS = [
	// --- Paid models ---
	{ value: 'anthropic/claude-sonnet-4-5', label: 'anthropic/claude-sonnet-4-5' },
	{ value: 'openai/gpt-5.2', label: 'openai/gpt-5.2' },
	{ value: 'openai/gpt-5.1', label: 'openai/gpt-5.1' },
	{ value: 'openai/gpt-5', label: 'openai/gpt-5' },
	{ value: 'openai/gpt-5-mini', label: 'openai/gpt-5-mini' },
	{ value: 'openai/gpt-5-nano', label: 'openai/gpt-5-nano' },
	{ value: 'openai/gpt-4o', label: 'openai/gpt-4o' },
	{ value: 'openai/gpt-4o-mini', label: 'openai/gpt-4o-mini' },
	{ value: 'google/gemini-2.0-flash-001', label: 'google/gemini-2.0-flash-001' },
	{ value: 'deepseek/deepseek-chat-v3-0324', label: 'deepseek/deepseek-chat-v3-0324' },
	// --- Free models ---
	{ value: 'nousresearch/hermes-3-llama-3.1-405b:free', label: 'Hermes 3 Llama 3.1 405B (Free)' },
	{ value: 'qwen/qwen3-235b-a22b-thinking-2507:free', label: 'Qwen3 235B A22B Thinking (Free)' },
	{ value: 'openai/gpt-oss-120b:free', label: 'OpenAI GPT-OSS 120B (Free)' },
	{ value: 'meta-llama/llama-3.3-70b-instruct:free', label: 'Llama 3.3 70B Instruct (Free)' },
	{ value: 'qwen/qwen3-coder:free', label: 'Qwen3 Coder (Free)' },
	{ value: 'nvidia/nemotron-3-nano-30b-a3b:free', label: 'Nemotron 3 Nano 30B (Free)' },
	{ value: 'google/gemma-3-27b-it:free', label: 'Gemma 3 27B (Free)' },
	{ value: 'mistralai/mistral-small-3.1-24b-instruct:free', label: 'Mistral Small 3.1 24B (Free)' },
	{ value: 'qwen/qwen3-next-80b-a3b-instruct:free', label: 'Qwen3 Next 80B (Free)' },
	{ value: 'stepfun/step-3.5-flash:free', label: 'Step 3.5 Flash (Free)' },
	{ value: 'z-ai/glm-4.5-air:free', label: 'GLM 4.5 Air (Free)' },
	{ value: 'cognitivecomputations/dolphin-mistral-24b-venice-edition:free', label: 'Dolphin Mistral 24B (Free)' },
	{ value: 'arcee-ai/trinity-large-preview:free', label: 'Trinity Large Preview (Free)' },
	{ value: 'upstage/solar-pro-3:free', label: 'Solar Pro 3 (Free)' },
];
