import { useMemo, useRef, useEffect, useState } from '@wordpress/element';
import Panel from '../../components/panel';
import Pill from '../../components/pill';
import JsonBox from '../../components/json-box';
import { Loader2, ChevronDown, ChevronRight, Rocket, Search, Zap } from 'lucide-react';
import SmartResult from '../../components/smart-result';
import {
	Alert,
	Button,
	Input,
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
	Switch,
} from '../../components/ui';
import {
	fmtInt,
	getOpenRouterModelOptions,
} from '../../../shared/utils';
import {
	strings,
	getPromptTemplates,
	getMcpPromptTemplates,
} from './constants';

const PROMPT_TEMPLATES = getPromptTemplates();

/**
 * Build aggregated streaming state from an array of SSE events.
 */
function useStreamState( events ) {
	return useMemo( () => {
		let thought = '';
		const actions = [];
		const steps = [];
		const results = [];
		let approval = null;
		let tokens = null;
		let error = '';

		for ( const event of events ) {
			switch ( event.type ) {
				case 'thinking':
					thought += event.content || '';
					break;
				case 'action':
					actions.push( {
						action: event.action,
						params: event.params,
					} );
					break;
				case 'step':
					steps.push( {
						step: event.step,
						total: event.total,
						status: event.status,
					} );
					break;
				case 'result':
					results.push( {
						status: event.status,
						message: event.message,
					} );
					break;
				case 'approval':
					approval = event;
					break;
				case 'tokens':
					tokens = {
						input: event.input,
						output: event.output,
					};
					break;
				case 'error':
					error = event.message || '';
					break;
			}
		}

		return { thought, actions, steps, results, approval, tokens, error };
	}, [ events ] );
}

// ---------------------------------------------------------------------------
// Shared helper components
// ---------------------------------------------------------------------------

function Collapsible( { label, defaultOpen = false, children, className = '' } ) {
	const [ open, setOpen ] = useState( defaultOpen );

	return (
		<div className={ className }>
			<button
				type="button"
				onClick={ () => setOpen( ! open ) }
				className="!border-0 !shadow-none !bg-transparent outline-none focus:outline-none flex w-full items-center gap-1.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.07em] text-muted transition-colors hover:text-ink"
			>
				{ open ? (
					<ChevronDown size={ 12 } />
				) : (
					<ChevronRight size={ 12 } />
				) }
				{ label }
			</button>
			{ open && <div className="mt-1.5">{ children }</div> }
		</div>
	);
}

function ParamDisplay( { params } ) {
	if (
		! params ||
		typeof params !== 'object' ||
		Object.keys( params ).length === 0
	) {
		return null;
	}

	return (
		<div className="grid gap-1 p-2.5">
			{ Object.entries( params ).map( ( [ key, val ] ) => {
				const display =
					typeof val === 'object'
						? JSON.stringify( val )
						: String( val );
				const isLong = display.length > 150;

				return (
					<div key={ key } className="flex gap-2 text-xs">
						<span className="shrink-0 font-mono font-semibold text-muted">
							{ key }:
						</span>
						<span className="break-all text-ink">
							{ isLong
								? display.slice( 0, 150 ) + '\u2026'
								: display }
						</span>
					</div>
				);
			} ) }
		</div>
	);
}

// ---------------------------------------------------------------------------
// Streaming display components
// ---------------------------------------------------------------------------

function StreamingThought( { text } ) {
	const ref = useRef( null );

	useEffect( () => {
		if ( ref.current ) {
			ref.current.scrollTop = ref.current.scrollHeight;
		}
	}, [ text ] );

	if ( ! text ) {
		return null;
	}

	return (
		<div className="mb-2.5 rounded-[10px] border-[0.5px] border-solid border-line bg-surface-2 p-2.5">
			<div className="mb-1 text-[11px] font-bold uppercase tracking-[0.07em] text-muted">
				Thinking
			</div>
			<pre
				ref={ ref }
				className="max-h-32 overflow-y-auto whitespace-pre-wrap break-words font-mono text-xs leading-relaxed text-ink"
			>
				{ text }
				<span className="animate-pulse">|</span>
			</pre>
		</div>
	);
}

function StreamingSteps( { steps, actions, results } ) {
	if ( ! steps.length ) {
		return null;
	}

	const stepMap = new Map();
	for ( const s of steps ) {
		stepMap.set( s.step, s );
	}

	return (
		<div className="mb-2.5 grid gap-2">
			{ Array.from( stepMap.values() ).map( ( s ) => {
				const action = actions[ s.step - 1 ];
				const result = results[ s.step - 1 ];
				const statusLabel = result?.status || s.status;

				return (
					<div
						key={ `stream-step-${ s.step }` }
						className="rounded-[10px] border-[0.5px] border-solid border-line bg-surface-2 p-2.5"
					>
						<div className="flex items-center justify-between gap-2">
							<strong className="text-[13px] text-ink">
								{ strings.step( s.step ) }{ ' ' }
								{ action?.action || '' }
								{ s.status === 'thinking' && (
									<span className="ml-1.5 animate-pulse text-xs font-normal text-muted">
										thinking...
									</span>
								) }
								{ s.status === 'executing' && (
									<span className="ml-1.5 animate-pulse text-xs font-normal text-muted">
										executing...
									</span>
								) }
							</strong>
							<Pill
								status={ statusLabel }
								label={ statusLabel }
							/>
						</div>
						{ result?.message && (
							<div className="mt-1.5 text-xs text-muted">
								{ result.message }
							</div>
						) }
						{ action?.params &&
							Object.keys( action.params ).length > 0 && (
								<Collapsible
									label="Parameters"
									className="mt-2"
								>
									<ParamDisplay
										params={ action.params }
									/>
								</Collapsible>
							) }
					</div>
				);
			} ) }
		</div>
	);
}

function ConversationContextPanel( {
	conversationTurns = [],
	usePreviousContext = false,
	onUsePreviousContextChange,
	onClearConversation,
} ) {
	const turns = Array.isArray( conversationTurns )
		? conversationTurns.slice( -3 )
		: [];

	return (
		<div className="mb-3 rounded-[10px] border-[0.5px] border-solid border-line bg-surface-2 p-2.5">
			<div className="mb-2 flex flex-wrap items-center justify-between gap-2">
				<strong className="text-[11px] font-bold uppercase tracking-[0.07em] text-muted">
					{ strings.conversationContext() }
				</strong>
				<div className="flex flex-wrap items-center gap-2">
					<label className="flex items-center gap-1.5 text-xs text-muted">
						<Switch
							checked={ !! usePreviousContext }
							onCheckedChange={ ( checked ) =>
								onUsePreviousContextChange?.( !! checked )
							}
						/>
						<span>{ strings.usePreviousTurnContext() }</span>
					</label>
					<Button
						variant="secondary"
						size="xs"
						onClick={ onClearConversation }
						disabled={ turns.length === 0 }
					>
						{ strings.clearConversation() }
					</Button>
				</div>
			</div>
			{ turns.length === 0 ? (
				<div className="text-xs text-muted">
					{ strings.conversationEmpty() }
				</div>
			) : (
				<div className="grid gap-2">
					{ turns.map( ( turn, index ) => (
						<div
							key={ `conversation-turn-${ index }` }
							className="rounded-md border-[0.5px] border-solid border-line bg-bg-soft p-2"
						>
							<div className="mb-1 text-[11px] font-semibold text-muted">
								{ strings.user() }:
								<span className="ml-1 font-normal text-ink">
									{ turn.user }
								</span>
							</div>
							<div className="text-[11px] font-semibold text-muted">
								{ strings.assistant() }:
								<span className="ml-1 font-normal text-ink">
									{ turn.assistant }
								</span>
							</div>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}

// ---------------------------------------------------------------------------
// Result step card
// ---------------------------------------------------------------------------

function ResultStepCard( { stepItem, stepKey } ) {
	const stepStatus = stepItem.status || 'unknown';
	const stepAction =
		stepItem.agent?.action || stepItem.execution?.action || 'none';
	const stepMessage =
		stepItem.execution?.message || stepItem.error || '';
	const agentThought = stepItem.agent?.thought || '';
	const agentParams = stepItem.agent?.params || {};
	const confidence = stepItem.agent?.confidence;
	const executionData = stepItem.execution?.data;
	const hasData =
		executionData &&
		typeof executionData === 'object' &&
		Object.keys( executionData ).length > 0;

	return (
		<div
			className="rounded-[10px] border-[0.5px] border-solid border-line bg-surface-2 p-2.5"
			key={ stepKey }
		>
			<div className="flex items-center justify-between gap-2">
				<strong className="text-[13px] text-ink">
					{ strings.step( stepItem.step ) } { stepAction }
				</strong>
				<div className="flex items-center gap-1.5">
					{ typeof confidence === 'number' && (
						<span className="text-[10px] font-semibold text-muted">
							{ Math.round( confidence * 100 ) }%
						</span>
					) }
					<Pill status={ stepStatus } label={ stepStatus } />
				</div>
			</div>

			{ stepMessage && (
				<div className="mt-1.5 text-xs text-muted">
					{ stepMessage }
				</div>
			) }

			{ /* Smart result data */ }
			{ hasData && (
				<div className="mt-2">
					<SmartResult data={ executionData } />
				</div>
			) }

			{ agentThought && (
				<Collapsible label="Reasoning" className="mt-2">
					<pre className="max-h-28 overflow-y-auto whitespace-pre-wrap break-words p-2.5 font-mono text-xs leading-relaxed text-muted">
						{ agentThought }
					</pre>
				</Collapsible>
			) }

			{ Object.keys( agentParams ).length > 0 && (
				<Collapsible label="Parameters" className="mt-1">
					<ParamDisplay params={ agentParams } />
				</Collapsible>
			) }
		</div>
	);
}

// ---------------------------------------------------------------------------
// MCP Tool Catalog panel
// ---------------------------------------------------------------------------

const ACCESS_LEVEL_COLORS = {
	read: 'bg-emerald-50 text-emerald-700 border-emerald-200',
	write: 'bg-amber-50 text-amber-700 border-amber-200',
	admin: 'bg-red-50 text-red-700 border-red-200',
};

function McpToolCatalog( { mcpTools = [], onUse } ) {
	const [ open, setOpen ] = useState( false );
	const [ search, setSearch ] = useState( '' );

	const MCP_TEMPLATES = useMemo( () => getMcpPromptTemplates(), [] );

	const modules = Array.isArray( mcpTools ) ? mcpTools : [];
	const totalTools = modules.reduce(
		( sum, mod ) => sum + ( mod.tools?.length || 0 ),
		0
	);

	const filtered = useMemo( () => {
		if ( ! search.trim() ) {
			return modules;
		}
		const query = search.toLowerCase();
		return modules
			.map( ( mod ) => ( {
				...mod,
				tools: ( mod.tools || [] ).filter(
					( tool ) =>
						tool.name.toLowerCase().includes( query ) ||
						( tool.description || '' ).toLowerCase().includes( query )
				),
			} ) )
			.filter( ( mod ) => mod.tools.length > 0 );
	}, [ modules, search ] );

	if ( totalTools === 0 ) {
		return null;
	}

	return (
		<div className="mb-3 rounded-[10px] border-[0.5px] border-solid border-line bg-surface-2">
			<button
				type="button"
				onClick={ () => setOpen( ! open ) }
				className="!border-0 !shadow-none !bg-transparent outline-none focus:outline-none flex w-full items-center gap-2 p-2.5 text-left"
			>
				{ open ? <ChevronDown size={ 14 } /> : <ChevronRight size={ 14 } /> }
				<Zap size={ 14 } className="text-primary" />
				<span className="text-[11px] font-bold uppercase tracking-[0.07em] text-muted">
					{ strings.mcpToolCatalog() }
				</span>
				<span className="ml-auto text-[10px] font-semibold text-muted/60">
					{ totalTools } tools
				</span>
			</button>

			{ open && (
				<div className="border-t border-line px-2.5 pb-2.5">
					<p className="mt-2 mb-2.5 text-[11px] text-muted">
						{ strings.mcpToolsSubtitle() }
					</p>

					{ /* MCP prompt templates */ }
					<div className="mb-2.5">
						<div className="mb-1.5 text-[10px] font-bold uppercase tracking-[0.07em] text-muted/60">
							{ strings.mcpTemplates() }
						</div>
						<div className="flex flex-wrap gap-1.5">
							{ MCP_TEMPLATES.map( ( tpl, i ) => (
								<Button
									key={ `mcp-tpl-${ i }` }
									variant="outline"
									size="xs"
									className="!h-auto !rounded-full !px-2 !py-1 !text-[11px] !font-medium !normal-case"
									onClick={ () => onUse?.( tpl.prompt ) }
								>
									{ tpl.label }
								</Button>
							) ) }
						</div>
					</div>

					{ /* Search */ }
					<div className="relative mb-2.5">
						<Search
							size={ 13 }
							className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-muted/50"
						/>
						<input
							type="text"
							value={ search }
							onChange={ ( e ) => setSearch( e.target.value ) }
							placeholder={ strings.mcpSearch() }
							className="w-full rounded-md border border-line bg-bg-soft py-1.5 pl-9 pr-2.5 text-xs text-ink placeholder:text-muted/40 focus:border-primary focus:outline-none"
						/>
					</div>

					{ /* Tool groups */ }
					{ filtered.map( ( mod ) => (
						<Collapsible
							key={ mod.module }
							label={ `${ mod.module } (${ mod.tools.length })` }
							defaultOpen={ filtered.length === 1 }
							className="mb-1"
						>
							<div className="grid gap-1">
								{ mod.tools.map( ( tool ) => (
									<div
										key={ tool.name }
										className="flex items-start gap-2 rounded-md border-[0.5px] border-line bg-bg-soft p-2"
									>
										<div className="min-w-0 flex-1">
											<div className="flex items-center gap-1.5">
												<code className="text-[11px] font-semibold text-ink">
													{ tool.name }
												</code>
												<span
													className={ `inline-block rounded-full border px-1.5 py-px text-[9px] font-bold uppercase ${ ACCESS_LEVEL_COLORS[ tool.accessLevel ] || ACCESS_LEVEL_COLORS.admin }` }
												>
													{ tool.accessLevel }
												</span>
											</div>
											{ tool.description && (
												<p className="mt-0.5 text-[11px] leading-snug text-muted">
													{ tool.description.length > 120
														? tool.description.slice( 0, 117 ) + '…'
														: tool.description }
												</p>
											) }
										</div>
										<Button
											variant="secondary"
											size="xs"
											className="!h-6 shrink-0 !text-[10px]"
											onClick={ () =>
												onUse?.( tool.description
													? `${ tool.description.split( '.' )[ 0 ] }.`
													: `Use MCP tool: ${ tool.name }` )
											}
										>
											{ strings.mcpUse() }
										</Button>
									</div>
								) ) }
							</div>
						</Collapsible>
					) ) }
				</div>
			) }
		</div>
	);
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

export default function ConsoleTab( {
	prompt,
	provider,
	model,
	result,
	streaming = false,
	streamEvents = [],
	approvals = [],
	conversationTurns = [],
	usePreviousContext = false,
	mcpTools = [],
	onPromptChange,
	onProviderChange,
	onModelChange,
	onRun,
	onTemplate,
	onUsePreviousContextChange,
	onClearConversation,
	onRefresh,
	onRequestApprove,
	onRequestReject,
} ) {
	const resultStatus = result?.execution?.status || result?.status || '';
	const resultExecutions = Array.isArray( result?.executions )
		? result.executions
		: [];

	const needsApproval =
		resultStatus === 'pending_approval' ||
		resultStatus === 'awaiting_approval' ||
		resultStatus === 'paused';

	// Try to find the pending approval from the refreshed list first.
	// Fallback: construct from the execution result if the API list hasn't refreshed yet.
	const pendingApproval = approvals.find(
		( item ) => item.status === 'pending'
	) || ( needsApproval && result?.execution?.approval_id ? {
		id: result.execution.approval_id,
		action_key: result.execution.action || result?.agent?.action || '',
		risk_level: result.execution.risk || 'high',
		status: 'pending',
	} : null );

	const stream = useStreamState( streamEvents );

	const promptRef = useRef( null );
	const [ isLaunching, setIsLaunching ] = useState( false );

	const handleLaunch = () => {
		if ( streaming || ! prompt.trim() || isLaunching ) return;
		setIsLaunching( true );
		setTimeout( () => {
			setIsLaunching( false );
			onRun();
		}, 500 );
	};

	const handlePromptKeyDown = ( e ) => {
		if ( e.key === 'Enter' && ( e.metaKey || e.ctrlKey ) ) {
			e.preventDefault();
			if ( ! streaming ) {
				handleLaunch();
			}
		}
	};

	return (
		<Panel
			title={ strings.commandConsole() }
			subtitle={ strings.commandConsoleSubtitle() }
			actions={
				<Button variant="secondary" onClick={ onRefresh }>
					{ strings.refreshData() }
				</Button>
			}
		>
			<div>
				<div className="mb-2 text-[11px] font-bold uppercase tracking-[0.1em] text-muted">
					{ strings.templates() }
				</div>
				<div className="mb-3 flex flex-wrap gap-2">
					{ PROMPT_TEMPLATES.map( ( template, index ) => (
						<Button
							key={ `tpl-${ index }` }
							variant="outline"
							size="sm"
							className="!h-auto !justify-start !rounded-full !px-2.5 !py-[7px] !text-xs !font-medium !normal-case !tracking-normal"
							onClick={ () => onTemplate( template ) }
						>
							{ template }
						</Button>
					) ) }
				</div>
			</div>

			{ /* ── Enhanced prompt box ── */ }
			<div className="openwp-prompt-container mb-3">
				<textarea
					ref={ promptRef }
					className="w-full px-3.5 pb-1 pt-3 font-mono text-sm leading-[1.55] text-ink placeholder:text-muted/50"
					style={ { minHeight: '120px' } }
					value={ prompt }
					onChange={ ( e ) => onPromptChange( e.target.value ) }
					onKeyDown={ handlePromptKeyDown }
					placeholder={ strings.promptPlaceholder() }
				/>
				<div className="flex items-center justify-end gap-2 px-3 pb-2.5">
					<kbd className="rounded border border-line bg-bg-soft px-1.5 py-0.5 text-[10px] font-semibold text-muted">
						{ navigator?.platform?.includes( 'Mac' )
							? '\u2318\u21B5'
							: 'Ctrl+\u21B5' }
					</kbd>
					<button
						type="button"
						onClick={ handleLaunch }
						disabled={ streaming || ! prompt.trim() }
						className="openwp-rocket-btn !border-0 !shadow-none outline-none focus:outline-none flex h-8 w-8 items-center justify-center rounded-full bg-[#0057ff] text-white transition-all duration-200 hover:bg-[#0043c4] hover:shadow-sm active:scale-95 disabled:bg-[#e8eef5] disabled:text-[#a8b8cc] disabled:cursor-not-allowed disabled:hover:shadow-none"
						title={ streaming ? strings.running() : strings.runCommand() }
					>
						{ streaming ? (
							<Loader2 className="h-4 w-4 animate-spin" />
						) : (
							<span className={ isLaunching ? 'openwp-rocket-launch inline-flex' : 'inline-flex transition-transform' }>
								<Rocket size={ 15 } />
							</span>
						) }
					</button>
				</div>
			</div>

			{ /* ── Inline approval (above catalog for visibility) ── */ }
			{ ! streaming && needsApproval && pendingApproval && (
				<div className="mb-3 rounded-lg border-[0.5px] border-solid border-line bg-bg-soft/50 p-3">
					<div className="flex flex-wrap items-center justify-between gap-3">
						<div className="flex items-center gap-2.5 text-[13px] text-muted">
							<Pill
								status={ pendingApproval.risk_level }
								label={ pendingApproval.risk_level }
							/>
							<span>
								{ strings.actionKey() }{ ' ' }
								<code className="rounded bg-surface px-1.5 py-0.5 font-mono text-xs">
									{ pendingApproval.action_key }
								</code>
							</span>
						</div>
						<div className="flex justify-end gap-2">
							<Button
								size="xs"
								onClick={ () =>
									onRequestApprove( pendingApproval )
								}
							>
								{ strings.approve() }
							</Button>
							<Button
								variant="secondary"
								size="xs"
								onClick={ () =>
									onRequestReject( pendingApproval )
								}
							>
								{ strings.reject() }
							</Button>
						</div>
					</div>
				</div>
			) }

			<ConversationContextPanel
				conversationTurns={ conversationTurns }
				usePreviousContext={ usePreviousContext }
				onUsePreviousContextChange={ onUsePreviousContextChange }
				onClearConversation={ onClearConversation }
			/>

			<McpToolCatalog
				mcpTools={ mcpTools }
				onUse={ ( text ) => onPromptChange( text ) }
			/>

			{ /* ── Settings row ── */ }
			<div className="mb-3 grid gap-2.5 [grid-template-columns:repeat(auto-fit,minmax(160px,1fr))] max-[920px]:grid-cols-1">
				<div className="grid gap-1.5">
					<span className="text-[11px] font-bold uppercase tracking-[0.07em] text-muted">
						{ strings.providerLabel() }
					</span>
					<Select
						value={ provider }
						onValueChange={ onProviderChange }
					>
						<SelectTrigger>
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value="openai">
								{ strings.openai() }
							</SelectItem>
							<SelectItem value="anthropic">
								{ strings.anthropic() }
							</SelectItem>
							<SelectItem value="glm">
								{ strings.glm() }
							</SelectItem>
							<SelectItem value="openrouter">
								{ strings.openrouter() }
							</SelectItem>
						</SelectContent>
					</Select>
				</div>

				<div className="grid gap-1.5">
					<span className="text-[11px] font-bold uppercase tracking-[0.07em] text-muted">
						{ strings.modelLabel() }
					</span>
					{ provider === 'openrouter' ? (
						<Select
							value={ model || 'anthropic/claude-sonnet-4-5' }
							onValueChange={ onModelChange }
						>
							<SelectTrigger>
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								{ getOpenRouterModelOptions(
									model || 'anthropic/claude-sonnet-4-5'
								).map( ( item ) => (
									<SelectItem
										key={ item.value }
										value={ item.value }
									>
										{ item.label }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
					) : (
						<Input
							value={ model }
							onChange={ ( e ) =>
								onModelChange( e.target.value )
							}
							placeholder={ strings.modelPlaceholder() }
						/>
					) }
				</div>
			</div>

			{ /* ── Live streaming display ── */ }
			{ streaming && streamEvents.length > 0 && (
				<div className="mt-2.5 border-t border-line pt-3">
					<StreamingThought text={ stream.thought } />
					<StreamingSteps
						steps={ stream.steps }
						actions={ stream.actions }
						results={ stream.results }
					/>

					{ stream.approval && (
						<Alert className="mb-2.5" variant="warning">
							Approval required for{ ' ' }
							<code className="rounded bg-bg-soft px-1.5 py-0.5 font-mono text-xs">
								{ stream.approval.action }
							</code>{ ' ' }
							(risk: { stream.approval.risk })
						</Alert>
					) }

					{ stream.tokens && (
						<div className="text-[11px] font-semibold uppercase tracking-[0.04em] text-muted">
							{ strings.tokens() }{ ' ' }
							{ fmtInt( stream.tokens.input ) }{ ' ' }
							{ strings.tokensIn() } /{ ' ' }
							{ fmtInt( stream.tokens.output ) }{ ' ' }
							{ strings.tokensOut() }
						</div>
					) }
				</div>
			) }

			{ /* ── Final result display ── */ }
			{ ! streaming && result && (
				<div className="mt-2.5 border-t border-line pt-3">
					{ /* Status + metadata */ }
					<div className="mb-2.5 flex flex-wrap items-center gap-2">
						<Pill status={ resultStatus } label={ resultStatus } />
						<span className="text-[11px] font-semibold uppercase tracking-[0.04em] text-muted">
							{ strings.providerLabel() }{ ' ' }
							{ result.provider || '-' }
						</span>
						<span className="text-[11px] font-semibold uppercase tracking-[0.04em] text-muted">
							{ strings.modelLabel() } { result.model || '-' }
						</span>
						<span className="text-[11px] font-semibold uppercase tracking-[0.04em] text-muted">
							{ strings.tokens() }{ ' ' }
							{ fmtInt( result.token_usage?.input_tokens ) }{ ' ' }
							{ strings.tokensIn() } /{ ' ' }
							{ fmtInt( result.token_usage?.output_tokens ) }{ ' ' }
							{ strings.tokensOut() }
						</span>
					</div>

					{ /* Result message (shown for approval executions) */ }
					{ ! result.execution && result.message && (
						<div className="mb-2 text-[13px] text-ink">
							{ result.message }
						</div>
					) }

					{ /* Primary result data */ }
					{ ( () => {
						const resultData = result.execution?.data || result.data;
						return resultData &&
							typeof resultData === 'object' &&
							Object.keys( resultData ).length > 0 && (
								<div className="mb-3">
									<SmartResult data={ resultData } />
								</div>
							);
					} )() }

					{ /* Single result execution cards */ }
					{ resultExecutions.length > 0 && (
						<div className="mb-2.5 grid gap-2">
							{ resultExecutions.map( ( stepItem ) => (
								<ResultStepCard
									key={ `exec-${ stepItem.step }` }
									stepKey={ `exec-${ stepItem.step }` }
									stepItem={ stepItem }
								/>
							) ) }
						</div>
					) }

					{ /* Collapsible raw response */ }
					<Collapsible
						label="Raw Response"
						defaultOpen={ false }
						className="mt-2"
					>
						<JsonBox value={ result } />
					</Collapsible>
				</div>
			) }
		</Panel>
	);
}
