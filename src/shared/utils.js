import { OPENROUTER_MODELS } from './constants';

export function fmtInt( value ) {
	const n = Number( value || 0 );
	if ( ! Number.isFinite( n ) ) return '0';
	return n.toLocaleString();
}

export function defaultModelForProvider( provider, settings ) {
	if ( provider === 'anthropic' ) {
		return settings.default_model_anthropic || 'claude-3-5-sonnet-latest';
	}
	if ( provider === 'glm' ) return settings.default_model_glm || 'glm-5';
	if ( provider === 'openrouter' ) {
		return settings.default_model_openrouter || 'anthropic/claude-sonnet-4-5';
	}
	return settings.default_model_openai || 'gpt-5.2';
}

export function getOpenRouterModelOptions( selected ) {
	const options = OPENROUTER_MODELS.slice();
	if ( selected && ! options.some( ( item ) => item.value === selected ) ) {
		options.unshift( { value: selected, label: selected + ' (Custom)' } );
	}
	return options;
}

export function statusTone( status ) {
	if ( status === 'success' || status === 'approved' ) return 'success';
	if ( status === 'pending' || status === 'pending_approval' ) return 'warning';
	if ( status === 'failed' || status === 'rejected' || status === 'failed_backup_gate' ) return 'danger';
	return 'neutral';
}
