import { useEffect, useMemo, useState } from '@wordpress/element';
import { useHashTab } from './hooks/use-hash-tab';
import NavBar from './components/nav-bar';
import DashboardTab from './screens/dashboard';
import ConsoleTab from './screens/console';
import ApprovalsTab from './screens/approvals';
import SettingsTab from './screens/settings';
import ActionsTab from './screens/actions';
import MemoryTab from './screens/memory';
import LogsTab from './screens/logs';
import BackupsTab from './screens/backups';
import OnboardingWizard from './screens/onboarding';
import { defaultModelForProvider } from '../shared/utils';
import { request, streamRequest } from '../shared/api';
import { toast } from 'sonner';
import {
	Alert,
	Badge,
	Button,
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
	Input,
	Toaster,
} from './components/ui';
import { messages, strings } from './constants';

const DEFAULT_ONBOARDING_PROGRESS = {
	provider_saved: false,
	connection_test_passed: false,
	guardrail_preset_saved: false,
	first_command_passed: false,
};

export default function App() {
	const [ tab, setTab ] = useHashTab();
	const [ loading, setLoading ] = useState( false );

	const [ prompt, setPrompt ] = useState( '' );
	const [ provider, setProvider ] = useState( 'openrouter' );
	const [ model, setModel ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const [ conversationTurns, setConversationTurns ] = useState( [] );
	const [ usePreviousContext, setUsePreviousContext ] = useState( false );

	const [ streaming, setStreaming ] = useState( false );
	const [ streamEvents, setStreamEvents ] = useState( [] );

	const [ bootstrap, setBootstrap ] = useState( {} );
	const [ actions, setActions ] = useState( [] );
	const [ approvals, setApprovals ] = useState( [] );
	const [ logs, setLogs ] = useState( [] );
	const [ backups, setBackups ] = useState( [] );
	const [ memory, setMemory ] = useState( [] );
	const [ memoryEnabled, setMemoryEnabled ] = useState( true );
	const [ mcpTools, setMcpTools ] = useState( [] );

	const [ settings, setSettings ] = useState( {} );
	const [ policy, setPolicy ] = useState( {} );
	const [ keys, setKeys ] = useState( {
		openai: '',
		anthropic: '',
		glm: '',
		openrouter: '',
	} );
	const [ maskedKeys, setMaskedKeys ] = useState( {
		openai: '',
		anthropic: '',
		glm: '',
		openrouter: '',
	} );
	const [ providerStatus, setProviderStatus ] = useState( {} );
	const [ settingsDirty, setSettingsDirty ] = useState( false );
	const [ policyDirty, setPolicyDirty ] = useState( false );

	const [ confirmDialog, setConfirmDialog ] = useState( null );
	const [ typedConfirmation, setTypedConfirmation ] = useState( '' );
	const [ dialogError, setDialogError ] = useState( '' );
	const [ , setOnboarding ] = useState( () => ( {
		completed: !! window?.openwpAdmin?.onboarding?.completed,
		progress: {
			...DEFAULT_ONBOARDING_PROGRESS,
			...( window?.openwpAdmin?.onboarding?.progress || {} ),
		},
	} ) );

	const isOnboardingRoute = tab.startsWith( 'onboarding/' );

	const loadDashboard = async () => {
		setLoading( true );
		const errors = [];
		let memoryResult = null;

		try {
			const b = await request( '/openwp/v1/bootstrap' );
			setBootstrap( b || {} );
		} catch ( err ) {
			errors.push( err?.message || messages.requestFailed() );
		}

		try {
			const a = await request( '/openwp/v1/actions' );
			setActions( a?.items || [] );
		} catch ( err ) {
			errors.push( err?.message || messages.requestFailed() );
		}

		try {
			const ap = await request( '/openwp/v1/approvals' );
			setApprovals( ( ap?.items || [] ).slice( 0, 25 ) );
		} catch ( err ) {
			setApprovals( [] );
			errors.push( err?.message || messages.requestFailed() );
		}

		try {
			const lg = await request( '/openwp/v1/logs' );
			setLogs( ( lg?.items || [] ).slice( 0, 25 ) );
		} catch ( err ) {
			setLogs( [] );
			errors.push( err?.message || messages.requestFailed() );
		}

		try {
			const bk = await request( '/openwp/v1/backups' );
			setBackups( ( bk?.items || [] ).slice( 0, 25 ) );
		} catch ( err ) {
			setBackups( [] );
			errors.push( err?.message || messages.requestFailed() );
		}

		try {
			memoryResult = await request( '/openwp/v1/memory?per_page=200' );
			setMemory( memoryResult?.items || [] );
			setMemoryEnabled( memoryResult?.enabled !== false );
		} catch ( err ) {
			setMemory( [] );
			errors.push( err?.message || messages.requestFailed() );
		}

		try {
			const mcp = await request( '/openwp/v1/mcp/tools' );
			setMcpTools( mcp?.modules || [] );
		} catch ( err ) {
			setMcpTools( [] );
			errors.push( err?.message || messages.requestFailed() );
		}

		try {
			const st = await request( '/openwp/v1/settings' );
			const payload = st || {};
			const nextSettings = payload.settings || {};
			setSettings( {
				...nextSettings,
				mcp: payload.mcp || {},
			} );
			setPolicy( payload.policy || {} );
			setProviderStatus( payload.provider || {} );
			setMaskedKeys(
				payload.provider?.masked_keys || {
					openai: '',
					anthropic: '',
					glm: '',
					openrouter: '',
				}
			);
			setProvider( nextSettings.default_provider || 'openrouter' );
			setModel(
				defaultModelForProvider(
					nextSettings.default_provider || 'openrouter',
					nextSettings
				)
			);
			setMemoryEnabled(
				nextSettings.openwp_memory_enabled !== false &&
					( memoryResult?.enabled !== false )
			);
		} catch ( err ) {
			errors.push( err?.message || messages.requestFailed() );
		}

		if ( errors.length ) {
			toast.error( messages.panelsFailedToLoad( errors.join( ' | ' ) ) );
		}

		setLoading( false );
	};

	const loadOnboarding = async () => {
		try {
			const response = await request( '/openwp/v1/onboarding' );
			setOnboarding( {
				completed: !! response?.completed,
				progress: {
					...DEFAULT_ONBOARDING_PROGRESS,
					...( response?.progress || {} ),
				},
			} );
		} catch ( err ) {
			// Keep localised bootstrap state as fallback; surface API errors only on active app actions.
		}
	};

	useEffect( () => {
		loadDashboard();
		loadOnboarding();
	}, [] );

	useEffect( () => {
		document.body.classList.toggle(
			'openwp-onboarding-page',
			isOnboardingRoute
		);
		return () => {
			document.body.classList.remove( 'openwp-onboarding-page' );
		};
	}, [ isOnboardingRoute ] );

	const withLoading =
		( fn ) =>
		async ( ...args ) => {
			setLoading( true );
			try {
				await fn( ...args );
			} catch ( err ) {
				toast.error( err?.message || messages.somethingWentWrong() );
			} finally {
				setLoading( false );
			}
		};

	const normalizeConversationText = ( value, maxLength ) => {
		const nextValue = String( value || '' )
			.replace( /\s+/g, ' ' )
			.trim();
		if ( ! nextValue ) {
			return '';
		}
		return nextValue.length > maxLength
			? `${ nextValue.slice( 0, maxLength - 1 ) }\u2026`
			: nextValue;
	};

	const buildAssistantConversationText = ( summary ) => {
		if ( ! summary || typeof summary !== 'object' ) {
			return '';
		}
		if ( summary.execution?.message ) {
			return normalizeConversationText( summary.execution.message, 700 );
		}
		if ( typeof summary.message === 'string' && summary.message.trim() ) {
			return normalizeConversationText( summary.message, 700 );
		}
		if (
			Array.isArray( summary.executions ) &&
			summary.executions.length > 0
		) {
			const lastExecution =
				summary.executions[ summary.executions.length - 1 ];
			if ( lastExecution?.execution?.message ) {
				return normalizeConversationText(
					lastExecution.execution.message,
					700
				);
			}
		}
		if ( summary.status === 'awaiting_approval' && summary.execution?.action ) {
			return normalizeConversationText(
				`Awaiting approval for ${ summary.execution.action }.`,
				700
			);
		}
		if ( typeof summary.status === 'string' && summary.status ) {
			return normalizeConversationText(
				`Completed with status ${ summary.status }.`,
				700
			);
		}
		return '';
	};

	const appendConversationTurn = ( userText, summary ) => {
		const nextUser = normalizeConversationText( userText, 500 );
		const nextAssistant = buildAssistantConversationText( summary );
		if ( ! nextUser || ! nextAssistant ) {
			return;
		}
		setConversationTurns( ( prev ) =>
			[
				...prev,
				{
					user: nextUser,
					assistant: nextAssistant,
					created_at: new Date().toISOString(),
				},
			].slice( -3 )
		);
	};

	const clearConversation = () => {
		setConversationTurns( [] );
		setUsePreviousContext( false );
	};

	const runCommand = async () => {
		if ( ! prompt.trim() ) {
			toast.error( messages.enterPrompt() );
			return;
		}

		setLoading( true );
		setStreaming( true );
		setStreamEvents( [] );
		setResult( null );
		const submittedPrompt = prompt.trim();
		const requestData = {
			prompt: submittedPrompt,
			provider,
			model,
		};
		if ( usePreviousContext && conversationTurns.length > 0 ) {
			requestData.conversation_context = {
				enabled: true,
				turns: conversationTurns.slice( -3 ).map( ( turn ) => ( {
					user: turn.user,
					assistant: turn.assistant,
				} ) ),
			};
		}

		try {
			let hasStreamError = false;
			let lastResult = null;

			await streamRequest(
				'/openwp/v1/agent/execute/stream',
				requestData,
				( event ) => {
					setStreamEvents( ( prev ) => [ ...prev, event ] );

					if ( event.type === 'done' && event.summary ) {
						lastResult = event.summary;
						setResult( event.summary );
						appendConversationTurn( submittedPrompt, event.summary );
					}
					if ( event.type === 'error' ) {
						hasStreamError = true;
						toast.error( event.message || messages.somethingWentWrong() );
					}
				}
			);

			if ( ! hasStreamError ) {
				const status = lastResult?.execution?.status || lastResult?.status || '';
				if ( status === 'pending_approval' || status === 'awaiting_approval' ) {
					toast.info( messages.approvalSubmitted ? messages.approvalSubmitted() : 'Awaiting approval.' );
				} else if ( status === 'failed' ) {
					toast.error( lastResult?.execution?.error_message || lastResult?.message || messages.somethingWentWrong() );
				} else {
					toast.success( messages.commandProcessed() );
				}
			}
			await loadDashboard();
		} catch ( err ) {
			toast.error( err?.message || messages.somethingWentWrong() );
		} finally {
			setLoading( false );
			setStreaming( false );
		}
	};

	const approveAction = withLoading( async ( id, typed ) => {
		const response = await request( `/openwp/v1/approvals/${ id }/approve`, {
			method: 'POST',
			data: { typed_confirmation: typed || '' },
		} );

		if ( response?.continuation_result ) {
			setResult( response.continuation_result );
			appendConversationTurn(
				`Approved action ${ id }`,
				response.continuation_result
			);
			toast.success(
				response.continuation_result.status === 'awaiting_approval'
					? messages.approvalSubmitted()
					: messages.commandProcessed()
			);
		} else {
			setResult( response || null );
			toast.success( messages.approvalSubmitted() );
		}

		await loadDashboard();
	} );

	const rejectAction = withLoading( async ( id ) => {
		await request( `/openwp/v1/approvals/${ id }/reject`, {
			method: 'POST',
			data: {},
		} );
		setResult( null );
		toast.success( messages.actionRejected() );
		await loadDashboard();
	} );

	const rollbackAction = withLoading( async ( id ) => {
		await request( `/openwp/v1/logs/${ id }/rollback`, { method: 'POST' } );
		toast.success( messages.rollbackExecuted() );
		await loadDashboard();
	} );

	const restoreBackup = withLoading( async ( id, typed ) => {
		await request( `/openwp/v1/backups/${ id }/restore`, {
			method: 'POST',
			data: { typed_confirmation: typed },
		} );
		toast.success( messages.backupRestoreSubmitted() );
		await loadDashboard();
	} );

	const saveSettings = withLoading( async () => {
		const mcp = settings?.mcp || {};
		const settingsPayload = { ...settings };
		delete settingsPayload.mcp;

		const response = await request( '/openwp/v1/settings', {
			method: 'POST',
			data: {
				settings: settingsPayload,
				provider_keys: {
					openai: keys.openai,
					anthropic: keys.anthropic,
					glm: keys.glm,
					openrouter: keys.openrouter,
				},
				mcp,
			},
		} );

		setKeys( {
			openai: '',
			anthropic: '',
			glm: '',
			openrouter: '',
		} );
		setMaskedKeys( response?.provider?.masked_keys || maskedKeys );
		setProviderStatus( response?.provider || providerStatus );
		setSettings( {
			...( response?.settings || settingsPayload ),
			mcp: response?.mcp || mcp,
		} );
		setSettingsDirty( false );
		toast.success( messages.settingsSaved() );
		await loadDashboard();
	} );

	const savePolicy = withLoading( async () => {
		const response = await request( '/openwp/v1/settings', {
			method: 'POST',
			data: { action_policy: policy },
		} );

		setPolicy( response?.policy || policy );
		setPolicyDirty( false );
		toast.success( messages.policySaved() );
		await loadDashboard();
	} );

	const createMemory = withLoading( async ( payload ) => {
		await request( '/openwp/v1/memory', {
			method: 'POST',
			data: payload,
		} );
		toast.success( messages.memorySaved() );
		await loadDashboard();
	} );

	const deleteMemory = withLoading( async ( payload ) => {
		await request( '/openwp/v1/memory', {
			method: 'DELETE',
			data: payload,
		} );
		toast.success( messages.memoryDeleted() );
		await loadDashboard();
	} );

	const metrics = useMemo(
		() => ( {
			actions: actions.length,
			pending: approvals.filter( ( item ) => item.status === 'pending' )
				.length,
			memory: memory.length,
			logs: logs.length,
			backups: backups.length,
		} ),
		[ actions, approvals, memory, logs, backups ]
	);

	const onOnboardingStateChange = ( nextState ) => {
		setOnboarding( ( prev ) => ( {
			...prev,
			...nextState,
			progress: {
				...prev.progress,
				...( nextState?.progress || {} ),
			},
		} ) );
	};

	const onOnboardingCompleted = async ( nextState ) => {
		onOnboardingStateChange( { ...nextState, completed: true } );
		setTab( 'console' );
		await loadDashboard();
	};

	const openConfirmDialog = ( config ) => {
		setConfirmDialog( config );
		setTypedConfirmation( '' );
		setDialogError( '' );
	};

	const closeConfirmDialog = () => {
		setConfirmDialog( null );
		setTypedConfirmation( '' );
		setDialogError( '' );
	};

	const onRequestApprove = ( item ) => {
		openConfirmDialog( {
			type: 'approve',
			id: item.id,
			requireTyped: item.risk_level === 'critical',
			title: messages.confirmApprovalTitle(),
			description:
				item.risk_level === 'critical'
					? messages.confirmCriticalApprovalDescription()
					: messages.confirmApprovalDescription(),
			confirmLabel: messages.confirm(),
			destructive: false,
		} );
	};

	const onRequestReject = ( item ) => {
		openConfirmDialog( {
			type: 'reject',
			id: item.id,
			requireTyped: false,
			title: messages.confirmRejectTitle(),
			description: messages.confirmRejectDescription(),
			confirmLabel: messages.confirm(),
			destructive: true,
		} );
	};

	const onRequestRollback = ( item ) => {
		openConfirmDialog( {
			type: 'rollback',
			id: item.id,
			requireTyped: false,
			title: messages.confirmRollbackTitle(),
			description: messages.confirmRollbackDescription(),
			confirmLabel: messages.confirm(),
			destructive: true,
		} );
	};

	const onRequestRestore = ( item ) => {
		openConfirmDialog( {
			type: 'restore',
			id: item.id,
			requireTyped: true,
			title: messages.confirmBackupRestoreTitle( item.id ),
			description: messages.confirmBackupRestoreDescription(),
			confirmLabel: messages.confirm(),
			destructive: true,
		} );
	};

	const submitConfirmDialog = async () => {
		if ( ! confirmDialog ) {
			return;
		}
		const typed = typedConfirmation.trim().toUpperCase();

		if ( confirmDialog.requireTyped && typed !== 'APPROVE' ) {
			setDialogError( messages.typeApproveToContinue() );
			return;
		}

		if ( confirmDialog.type === 'approve' ) {
			await approveAction( confirmDialog.id, typed );
		} else if ( confirmDialog.type === 'reject' ) {
			await rejectAction( confirmDialog.id );
		} else if ( confirmDialog.type === 'rollback' ) {
			await rollbackAction( confirmDialog.id );
		} else if ( confirmDialog.type === 'restore' ) {
			await restoreBackup( confirmDialog.id, typed );
		}

		closeConfirmDialog();
	};

	const updatePolicy = ( key, patch ) => {
		setPolicy( ( prev ) => ( {
			...prev,
			[ key ]: { ...( prev[ key ] || {} ), ...patch },
		} ) );
		setPolicyDirty( true );
	};

	const renderTab = () => {
		if ( isOnboardingRoute ) {
			return (
				<OnboardingWizard
					route={ tab }
					onNavigate={ setTab }
					onCompleted={ onOnboardingCompleted }
					onStateChange={ onOnboardingStateChange }
				/>
			);
		}

		switch ( tab ) {
			case 'dashboard':
				return (
					<DashboardTab
						metrics={ metrics }
						provider={ provider }
						model={ model }
						bootstrap={ bootstrap }
					/>
				);
			case 'console':
				return (
					<ConsoleTab
						prompt={ prompt }
						provider={ provider }
						model={ model }
						result={ result }
						streaming={ streaming }
						streamEvents={ streamEvents }
						approvals={ approvals }
						conversationTurns={ conversationTurns }
						usePreviousContext={ usePreviousContext }
						onPromptChange={ setPrompt }
						onProviderChange={ ( p ) => {
							setProvider( p );
							setModel( defaultModelForProvider( p, settings ) );
						} }
						onModelChange={ setModel }
						onRun={ runCommand }
						onTemplate={ ( tpl ) => {
							setPrompt( tpl );
							setTab( 'console' );
						} }
						mcpTools={ mcpTools }
						onUsePreviousContextChange={ setUsePreviousContext }
						onClearConversation={ clearConversation }
						onRefresh={ loadDashboard }
						onRequestApprove={ onRequestApprove }
						onRequestReject={ onRequestReject }
					/>
				);
			case 'approvals':
				return (
					<ApprovalsTab
						items={ approvals }
						onRequestApprove={ onRequestApprove }
						onRequestReject={ onRequestReject }
					/>
				);
			case 'settings':
				return (
					<SettingsTab
						settings={ settings }
						keys={ keys }
						maskedKeys={ maskedKeys }
						providerStatus={ providerStatus }
						isDirty={ settingsDirty }
						onSettingsChange={ ( patch ) => {
							setSettings( ( s ) => ( { ...s, ...patch } ) );
							setSettingsDirty( true );
						} }
						onKeyChange={ ( k, v ) => {
							setKeys( ( ks ) => ( { ...ks, [ k ]: v } ) );
							setSettingsDirty( true );
						} }
						onSave={ saveSettings }
					/>
				);
			case 'actions':
				return (
					<ActionsTab
						actions={ actions }
						policy={ policy }
						isDirty={ policyDirty }
						onPolicyChange={ updatePolicy }
						onSave={ savePolicy }
					/>
				);
			case 'memory':
				return (
					<MemoryTab
						items={ memory }
						enabled={ memoryEnabled }
						onCreate={ createMemory }
						onDelete={ deleteMemory }
					/>
				);
			case 'logs':
				return (
					<LogsTab
						items={ logs }
						onRequestRollback={ onRequestRollback }
					/>
				);
			case 'backups':
				return (
					<BackupsTab
						items={ backups }
						onRequestRestore={ onRequestRestore }
					/>
				);
			default:
				return null;
		}
	};

	return (
		<div className="min-h-screen font-archivo text-ink">
			{ isOnboardingRoute ? (
				renderTab()
			) : (
				<>
					<NavBar
						tab={ tab }
						metrics={ metrics }
						onChange={ setTab }
					/>

					<main className="min-w-0 pb-2">{ renderTab() }</main>

					<Dialog
						open={ !! confirmDialog }
						onOpenChange={ ( open ) =>
							! open && closeConfirmDialog()
						}
					>
						<DialogContent>
							<DialogHeader>
								<DialogTitle>
									{ confirmDialog?.title || '' }
								</DialogTitle>
								<DialogDescription>
									{ confirmDialog?.description || '' }
								</DialogDescription>
							</DialogHeader>

							{ confirmDialog?.requireTyped && (
								<div className="grid gap-1.5">
									<label
										htmlFor="openwp-typed-confirmation"
										className="text-xs font-semibold uppercase tracking-[0.06em] text-muted"
									>
										{ messages.typeApproveLabel() }
									</label>
									<Input
										id="openwp-typed-confirmation"
										value={ typedConfirmation }
										onChange={ ( e ) =>
											setTypedConfirmation(
												e.target.value
											)
										}
										placeholder="APPROVE"
									/>
								</div>
							) }

							{ dialogError && (
								<Alert variant="destructive">
									{ dialogError }
								</Alert>
							) }

							<DialogFooter>
								<Button
									variant="secondary"
									onClick={ closeConfirmDialog }
								>
									{ messages.cancel() }
								</Button>
								<Button
									variant={
										confirmDialog?.destructive
											? 'destructive'
											: 'default'
									}
									onClick={ submitConfirmDialog }
								>
									{ confirmDialog?.confirmLabel ||
										messages.confirm() }
								</Button>
							</DialogFooter>
						</DialogContent>
					</Dialog>
				</>
			) }
			<Toaster />
		</div>
	);
}
