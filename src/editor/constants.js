import { __ } from '@wordpress/i18n';

// ---------------------------------------------------------------------------
// Static option lists (shared between global-modal and ai-content-generator)
// ---------------------------------------------------------------------------

export const SECTION_TYPES = [
	{ label: __( 'Hero Section', 'openwp' ), value: 'hero_section' },
	{ label: __( 'Feature Section', 'openwp' ), value: 'feature_section' },
	{ label: __( 'FAQ Section', 'openwp' ), value: 'faq_section' },
	{ label: __( 'Call to Action', 'openwp' ), value: 'cta_section' },
	{ label: __( 'Blog Intro', 'openwp' ), value: 'blog_intro' },
	{ label: __( 'Testimonials Section', 'openwp' ), value: 'testimonials_section' },
	{ label: __( 'Pricing Section', 'openwp' ), value: 'pricing_section' },
	{ label: __( 'About / Team Section', 'openwp' ), value: 'about_section' },
	{ label: __( 'Newsletter Signup', 'openwp' ), value: 'newsletter_section' },
	{ label: __( 'Contact Section', 'openwp' ), value: 'contact_section' },
];

export const FULL_PAGE_TYPES = [
	{ label: __( 'Full Landing Page', 'openwp' ), value: 'full_landing_page' },
	{ label: __( 'Full Blog Post', 'openwp' ), value: 'full_blog_post' },
	{ label: __( 'Full About Page', 'openwp' ), value: 'full_about_page' },
	{ label: __( 'Full Services Page', 'openwp' ), value: 'full_services_page' },
	{ label: __( 'Full Contact Page', 'openwp' ), value: 'full_contact_page' },
];

export const TONES = [
	{ label: __( 'Professional', 'openwp' ), value: 'professional' },
	{ label: __( 'Friendly', 'openwp' ), value: 'friendly' },
	{ label: __( 'Persuasive', 'openwp' ), value: 'persuasive' },
	{ label: __( 'Casual', 'openwp' ), value: 'casual' },
	{ label: __( 'Technical', 'openwp' ), value: 'technical' },
];

export const PROVIDERS = [
	{ label: __( 'OpenAI', 'openwp' ), value: 'openai' },
	{ label: __( 'Anthropic', 'openwp' ), value: 'anthropic' },
	{ label: __( 'GLM (Z.AI)', 'openwp' ), value: 'glm' },
	{ label: __( 'OpenRouter', 'openwp' ), value: 'openrouter' },
];

export const OPENROUTER_MODELS = [
	// --- Paid models ---
	{ label: 'anthropic/claude-sonnet-4-5', value: 'anthropic/claude-sonnet-4-5' },
	{ label: 'openai/gpt-5.2', value: 'openai/gpt-5.2' },
	{ label: 'openai/gpt-5.1', value: 'openai/gpt-5.1' },
	{ label: 'openai/gpt-5', value: 'openai/gpt-5' },
	{ label: 'openai/gpt-5-mini', value: 'openai/gpt-5-mini' },
	{ label: 'openai/gpt-5-nano', value: 'openai/gpt-5-nano' },
	{ label: 'openai/gpt-4o', value: 'openai/gpt-4o' },
	{ label: 'openai/gpt-4o-mini', value: 'openai/gpt-4o-mini' },
	{ label: 'google/gemini-2.0-flash-001', value: 'google/gemini-2.0-flash-001' },
	{ label: 'deepseek/deepseek-chat-v3-0324', value: 'deepseek/deepseek-chat-v3-0324' },
	// --- Free models ---
	{ label: 'Hermes 3 Llama 3.1 405B (Free)', value: 'nousresearch/hermes-3-llama-3.1-405b:free' },
	{ label: 'Qwen3 235B A22B Thinking (Free)', value: 'qwen/qwen3-235b-a22b-thinking-2507:free' },
	{ label: 'OpenAI GPT-OSS 120B (Free)', value: 'openai/gpt-oss-120b:free' },
	{ label: 'Llama 3.3 70B Instruct (Free)', value: 'meta-llama/llama-3.3-70b-instruct:free' },
	{ label: 'Qwen3 Coder (Free)', value: 'qwen/qwen3-coder:free' },
	{ label: 'Nemotron 3 Nano 30B (Free)', value: 'nvidia/nemotron-3-nano-30b-a3b:free' },
	{ label: 'Gemma 3 27B (Free)', value: 'google/gemma-3-27b-it:free' },
	{ label: 'Mistral Small 3.1 24B (Free)', value: 'mistralai/mistral-small-3.1-24b-instruct:free' },
	{ label: 'Qwen3 Next 80B (Free)', value: 'qwen/qwen3-next-80b-a3b-instruct:free' },
	{ label: 'Step 3.5 Flash (Free)', value: 'stepfun/step-3.5-flash:free' },
	{ label: 'GLM 4.5 Air (Free)', value: 'z-ai/glm-4.5-air:free' },
	{ label: 'Dolphin Mistral 24B (Free)', value: 'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' },
	{ label: 'Trinity Large Preview (Free)', value: 'arcee-ai/trinity-large-preview:free' },
	{ label: 'Solar Pro 3 (Free)', value: 'upstage/solar-pro-3:free' },
];

// ---------------------------------------------------------------------------
// Admin config (passed via wp_localize_script)
// ---------------------------------------------------------------------------

const config = window.openwpAiContentGenerator || {};
export const providerDefaults = config.providers || {};
export const defaultProvider = config.defaultProvider || 'openrouter';
export const defaultModel = config.defaultModel || providerDefaults[ defaultProvider ] || 'anthropic/claude-sonnet-4-5';
export const adminPalette = Array.isArray( config.themePalette ) ? config.themePalette : [];
export const adminContentType = config.defaultContentType || 'hero_section';
export const adminTone = config.defaultTone || 'professional';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

export function getModelByProvider( provider ) {
	if ( providerDefaults[ provider ] ) return providerDefaults[ provider ];
	if ( provider === 'anthropic' ) return 'claude-3-5-sonnet-latest';
	if ( provider === 'glm' ) return 'glm-5';
	if ( provider === 'openrouter' ) return 'anthropic/claude-sonnet-4-5';
	return 'gpt-5.2';
}

export function getOpenRouterModelOptions( selected ) {
	const options = OPENROUTER_MODELS.slice();
	if ( selected && ! options.some( ( o ) => o.value === selected ) ) {
		options.unshift( { label: selected + ' (Custom)', value: selected } );
	}
	return options;
}

export function optionLabel( options, value ) {
	return ( options.find( ( o ) => o.value === value ) || {} ).label || value;
}
