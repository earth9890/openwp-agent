import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Alert, Skeleton } from '../../components/ui';
import { request } from '../../../shared/api';
import {
	defaultModelForProvider,
	getOpenRouterModelOptions,
} from '../../../shared/utils';
import OnboardingLayout from './layout';
import WelcomeStep from './steps/welcome';
import ProviderStep from './steps/provider';
import GuardrailsStep from './steps/guardrails';
import TestCommandStep from './steps/test-command';
import DoneStep from './steps/done';
import {
	DEFAULT_TEST_PROMPT,
	GUARDRAIL_PRESETS,
	ONBOARDING_STEPS,
	ONBOARDING_ROUTE_SET,
	providerModelKey,
} from './constants';

const STEPS_IN_FLOW = ONBOARDING_STEPS.filter(
	( step ) => step.key !== 'onboarding/done'
);

function firstIncompleteRoute( progress = {} ) {
	if ( ! progress.provider_saved || ! progress.connection_test_passed ) {
		return 'onboarding/provider';
	}

	if ( ! progress.guardrail_preset_saved ) {
		return 'onboarding/guardrails';
	}

	if ( ! progress.first_command_passed ) {
		return 'onboarding/test-command';
	}

	return 'onboarding/done';
}

function hasAnyProviderKey( providerStatus = {} ) {
	return !! (
		providerStatus.has_openai_key ||
		providerStatus.has_anthropic_key ||
		providerStatus.has_glm_key ||
		providerStatus.has_openrouter_key
	);
}

export default function OnboardingWizard( {
	route,
	onNavigate,
	onCompleted,
	onStateChange,
} ) {
	const [ loading, setLoading ] = useState( true );
	const [ working, setWorking ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ settings, setSettings ] = useState( {} );
	const [ providerStatus, setProviderStatus ] = useState( {} );
	const [ onboarding, setOnboarding ] = useState( {
		completed: false,
		progress: {
			provider_saved: false,
			connection_test_passed: false,
			guardrail_preset_saved: false,
			first_command_passed: false,
		},
		derived: {},
	} );
	const [ provider, setProvider ] = useState( 'openrouter' );
	const [ model, setModel ] = useState( 'anthropic/claude-sonnet-4-5' );
	const [ apiKey, setApiKey ] = useState( '' );
	const [ providerErrors, setProviderErrors ] = useState( {} );
	const [ connectionState, setConnectionState ] = useState( {
		status: 'idle',
		message: '',
	} );
	const [ preset, setPreset ] = useState( 'balanced' );
	const [ commandPrompt, setCommandPrompt ] = useState( DEFAULT_TEST_PROMPT );
	const [ commandState, setCommandState ] = useState( {
		status: 'idle',
		message: '',
		result: null,
	} );

	// Use refs for callbacks to avoid re-creating loadWizardData on every render
	const onStateChangeRef = useRef( onStateChange );
	onStateChangeRef.current = onStateChange;

	const onNavigateRef = useRef( onNavigate );
	onNavigateRef.current = onNavigate;

	const stepMap = useMemo( () => {
		const map = {};
		ONBOARDING_STEPS.forEach( ( step ) => {
			map[ step.key ] = step;
		} );
		return map;
	}, [] );

	const currentStep = stepMap[ route ] || stepMap[ 'onboarding/welcome' ];
	const flowIndex = STEPS_IN_FLOW.findIndex( ( step ) => step.key === route );
	const currentIndex =
		route === 'onboarding/done'
			? STEPS_IN_FLOW.length
			: Math.max( 0, flowIndex );

	const loadWizardData = useCallback( async () => {
		setLoading( true );
		setError( '' );
		try {
			const [ onboardingResponse, settingsResponse ] = await Promise.all(
				[
					request( '/openwp/v1/onboarding' ),
					request( '/openwp/v1/settings' ),
				]
			);
			const nextOnboarding = {
				completed: !! onboardingResponse?.completed,
				progress: {
					provider_saved:
						!! onboardingResponse?.progress?.provider_saved,
					connection_test_passed:
						!! onboardingResponse?.progress?.connection_test_passed,
					guardrail_preset_saved:
						!! onboardingResponse?.progress?.guardrail_preset_saved,
					first_command_passed:
						!! onboardingResponse?.progress?.first_command_passed,
				},
				derived: onboardingResponse?.derived || {},
			};
			setOnboarding( nextOnboarding );
			onStateChangeRef.current?.( nextOnboarding );

			setSettings( settingsResponse?.settings || {} );
			setProviderStatus( settingsResponse?.provider || {} );

			const nextProvider =
				settingsResponse?.settings?.default_provider || 'openrouter';
			setProvider( nextProvider );
			setModel(
				defaultModelForProvider(
					nextProvider,
					settingsResponse?.settings || {}
				)
			);

			// If connection test already passed, reflect that
			if ( nextOnboarding.progress.connection_test_passed ) {
				setConnectionState( {
					status: 'success',
					message: __( 'Connection verified.', 'openwp' ),
				} );
			}
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to load onboarding data.', 'openwp' )
			);
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadWizardData();
	}, [ loadWizardData ] );

	useEffect( () => {
		if ( ! ONBOARDING_ROUTE_SET.has( route ) ) {
			onNavigateRef.current( 'onboarding/welcome' );
			return;
		}

		if ( loading || working ) {
			return;
		}

		if ( route === 'onboarding/welcome' || route === 'onboarding/done' ) {
			return;
		}

		// Only prevent skipping ahead past the first incomplete step.
		// Navigating back to completed steps is always allowed.
		const target = firstIncompleteRoute( onboarding.progress );
		const targetIndex = ONBOARDING_STEPS.findIndex(
			( step ) => step.key === target
		);
		const routeIndex = ONBOARDING_STEPS.findIndex(
			( step ) => step.key === route
		);
		if ( routeIndex > targetIndex ) {
			onNavigateRef.current( target );
		}
	}, [ route, loading, working, onboarding ] );

	const onboardingRef = useRef( onboarding );
	onboardingRef.current = onboarding;

	const updateProgress = async ( patch ) => {
		const response = await request( '/openwp/v1/onboarding/progress', {
			method: 'POST',
			data: { progress: patch },
		} );

		// Only take the patched keys from the response to avoid
		// the server response overwriting unrelated progress flags.
		const progressUpdate = {};
		for ( const key of Object.keys( patch ) ) {
			progressUpdate[ key ] =
				response?.progress?.[ key ] ?? patch[ key ];
		}

		const prev = onboardingRef.current;
		const next = {
			...prev,
			progress: {
				...prev.progress,
				...progressUpdate,
			},
		};
		setOnboarding( next );
		onStateChangeRef.current?.( next );
		return next.progress;
	};

	const handleExit = () => {
		onNavigate( 'console' );
	};

	const handleBack = () => {
		if ( route === 'onboarding/provider' ) {
			onNavigate( 'onboarding/welcome' );
			return;
		}
		if ( route === 'onboarding/guardrails' ) {
			onNavigate( 'onboarding/provider' );
			return;
		}
		if ( route === 'onboarding/test-command' ) {
			onNavigate( 'onboarding/guardrails' );
		}
	};

	const handleProviderSaveAndTest = async () => {
		const hasSavedKeyForProvider =
			!! providerStatus?.masked_keys?.[ provider ];
		const nextErrors = {};
		if ( ! provider ) {
			nextErrors.provider = __( 'Provider is required.', 'openwp' );
		}
		if ( ! model.trim() ) {
			nextErrors.model = __( 'Model is required.', 'openwp' );
		}
		if ( ! apiKey.trim() && ! hasSavedKeyForProvider ) {
			nextErrors.key = __( 'API key is required.', 'openwp' );
		}

		setProviderErrors( nextErrors );
		if ( Object.keys( nextErrors ).length > 0 ) {
			return;
		}

		setWorking( true );
		setError( '' );
		setConnectionState( { status: 'testing', message: '' } );
		try {
			// 1. Save provider settings
			const modelKey = providerModelKey( provider );
			const settingsPatch = {
				default_provider: provider,
				[ modelKey ]: model.trim(),
			};
			const providerPatch = {
				openai: '',
				anthropic: '',
				glm: '',
				openrouter: '',
			};
			providerPatch[ provider ] = apiKey.trim();

			const response = await request( '/openwp/v1/settings', {
				method: 'POST',
				data: {
					settings: settingsPatch,
					provider_keys: providerPatch,
				},
			} );

			const nextSettings = response?.settings || settings;
			const nextProviderStatus = response?.provider || providerStatus;
			setSettings( nextSettings );
			setProviderStatus( nextProviderStatus );
			setApiKey( '' );
			setProviderErrors( {} );

			const providerSaved = hasAnyProviderKey( nextProviderStatus );
			await updateProgress( {
				provider_saved: providerSaved,
				connection_test_passed: false,
				first_command_passed: false,
			} );

			// 2. Run connection test inline
			await request( '/openwp/v1/editor/generate', {
				method: 'POST',
				data: {
					prompt: 'Return one short sentence confirming API connectivity.',
					content_type: 'paragraph',
					tone: 'professional',
					provider,
					model,
				},
			} );

			setConnectionState( {
				status: 'success',
				message: __( 'Connection successful! Your API key is working.', 'openwp' ),
			} );
			await updateProgress( { connection_test_passed: true } );
		} catch ( err ) {
			const msg = err?.message || '';
			// If settings saved but connection failed
			if ( connectionState.status === 'testing' ) {
				setConnectionState( {
					status: 'error',
					message: msg || __( 'Connection test failed. Check your API key and try again.', 'openwp' ),
				} );
			} else {
				setError( msg || __( 'Failed to save provider settings.', 'openwp' ) );
			}
		} finally {
			setWorking( false );
		}
	};

	const handleConnectionRetry = async () => {
		setWorking( true );
		setError( '' );
		setConnectionState( { status: 'testing', message: '' } );
		try {
			await request( '/openwp/v1/editor/generate', {
				method: 'POST',
				data: {
					prompt: 'Return one short sentence confirming API connectivity.',
					content_type: 'paragraph',
					tone: 'professional',
					provider,
					model,
				},
			} );
			setConnectionState( {
				status: 'success',
				message: __( 'Connection successful! Your API key is working.', 'openwp' ),
			} );
			await updateProgress( { connection_test_passed: true } );
		} catch ( err ) {
			setConnectionState( {
				status: 'error',
				message: err?.message || __( 'Connection test failed.', 'openwp' ),
			} );
		} finally {
			setWorking( false );
		}
	};

	const handleGuardrailsContinue = async () => {
		const chosen =
			GUARDRAIL_PRESETS[ preset ] || GUARDRAIL_PRESETS.balanced;
		setWorking( true );
		setError( '' );
		try {
			const response = await request( '/openwp/v1/settings', {
				method: 'POST',
				data: {
					settings: chosen.settings,
				},
			} );
			setSettings( response?.settings || settings );
			await updateProgress( { guardrail_preset_saved: true } );
			onNavigate( 'onboarding/test-command' );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to apply guardrail preset.', 'openwp' )
			);
		} finally {
			setWorking( false );
		}
	};

	const handleRunCommand = async () => {
		setWorking( true );
		setError( '' );
		setCommandState( { status: 'running', message: '', result: null } );
		try {
				const response = await request( '/openwp/v1/agent/execute', {
					method: 'POST',
					data: {
						prompt: commandPrompt.trim(),
						provider,
						model,
					},
				} );
			const status = response?.execution?.status || response?.status;
			if ( status !== 'success' ) {
				throw new Error(
					__(
						'Command did not complete successfully.',
						'openwp'
					)
				);
			}
			await updateProgress( { first_command_passed: true } );
			setCommandState( {
				status: 'success',
				message: __( 'Command executed successfully!', 'openwp' ),
				result: response,
			} );
		} catch ( err ) {
			setCommandState( {
				status: 'error',
				message: err?.message || __( 'Command test failed.', 'openwp' ),
				result: null,
			} );
		} finally {
			setWorking( false );
		}
	};

	const handleDoneContinue = async () => {
		setWorking( true );
		setError( '' );
		try {
			// Ensure all progress flags are true before completing.
			// The user reached Done, so all steps were completed or skipped.
			await updateProgress( {
				provider_saved: true,
				connection_test_passed: true,
				guardrail_preset_saved: true,
				first_command_passed: true,
			} );

			await request( '/openwp/v1/onboarding/complete', {
				method: 'POST',
				data: {},
			} );

			const prev = onboardingRef.current;
			const nextOnboarding = { ...prev, completed: true };
			setOnboarding( nextOnboarding );
			onStateChangeRef.current?.( nextOnboarding );
			onCompleted?.( nextOnboarding );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Unable to complete onboarding.', 'openwp' )
			);
		} finally {
			setWorking( false );
		}
	};

	const handleSkip = async () => {
		if ( working ) {
			return;
		}

		setWorking( true );
		setError( '' );
		try {
			if ( route === 'onboarding/welcome' ) {
				onNavigate( 'onboarding/provider' );
			} else if ( route === 'onboarding/provider' ) {
				await updateProgress( {
					provider_saved: true,
					connection_test_passed: true,
				} );
				onNavigate( 'onboarding/guardrails' );
			} else if ( route === 'onboarding/guardrails' ) {
				await updateProgress( { guardrail_preset_saved: true } );
				onNavigate( 'onboarding/test-command' );
			} else if ( route === 'onboarding/test-command' ) {
				await updateProgress( { first_command_passed: true } );
				onNavigate( 'onboarding/done' );
			}
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Unable to skip this step right now.', 'openwp' )
			);
		} finally {
			setWorking( false );
		}
	};

	const handleContinue = async () => {
		if ( route === 'onboarding/welcome' ) {
			onNavigate( 'onboarding/provider' );
			return;
		}
		if ( route === 'onboarding/provider' ) {
			if ( onboarding.progress.connection_test_passed ) {
				onNavigate( 'onboarding/guardrails' );
			} else {
				await handleProviderSaveAndTest();
			}
			return;
		}
		if ( route === 'onboarding/guardrails' ) {
			await handleGuardrailsContinue();
			return;
		}
		if ( route === 'onboarding/test-command' ) {
			if ( onboarding.progress.first_command_passed ) {
				onNavigate( 'onboarding/done' );
			}
			return;
		}
		if ( route === 'onboarding/done' ) {
			await handleDoneContinue();
		}
	};

	const handleStepNavigate = ( stepKey ) => {
		if ( stepKey === route ) {
			return;
		}
		onNavigate( stepKey );
	};

	const continueLabel = ( () => {
		if ( route === 'onboarding/welcome' ) {
			return __( 'Get Started', 'openwp' );
		}
		if ( route === 'onboarding/provider' ) {
			if ( onboarding.progress.connection_test_passed ) {
				return __( 'Continue', 'openwp' );
			}
			return __( 'Save & Test Connection', 'openwp' );
		}
		if ( route === 'onboarding/guardrails' ) {
			return __( 'Apply & Continue', 'openwp' );
		}
		if ( route === 'onboarding/test-command' ) {
			return __( 'Continue', 'openwp' );
		}
		return __( 'Go to OpenWP Console', 'openwp' );
	} )();

	const continueDisabled =
		working ||
		( route === 'onboarding/test-command' &&
			! onboarding.progress.first_command_passed );

	const isWideStep =
		route === 'onboarding/provider' ||
		route === 'onboarding/guardrails' ||
		route === 'onboarding/test-command';

	const renderContent = () => {
		if ( loading ) {
			return (
				<div className="space-y-4">
					<Skeleton className="h-5 w-3/4" />
					<Skeleton className="h-4 w-full" />
					<Skeleton className="h-4 w-2/3" />
					<div className="mt-6 space-y-3">
						<Skeleton className="h-10 w-full" />
						<Skeleton className="h-10 w-full" />
					</div>
				</div>
			);
		}

		if ( route === 'onboarding/welcome' ) {
			return <WelcomeStep />;
		}
		if ( route === 'onboarding/provider' ) {
			return (
				<ProviderStep
					provider={ provider }
					model={ model }
					apiKey={ apiKey }
					errors={ providerErrors }
					modelOptions={ getOpenRouterModelOptions( model ) }
					maskedKey={
						providerStatus?.masked_keys?.[ provider ] || ''
					}
					hasSavedKey={ !! providerStatus?.masked_keys?.[ provider ] }
					connectionStatus={ connectionState.status }
					connectionMessage={ connectionState.message }
					onProviderChange={ ( nextProvider ) => {
						setProvider( nextProvider );
						setModel(
							defaultModelForProvider(
								nextProvider,
								settings || {}
							)
						);
						setProviderErrors( {} );
						setConnectionState( { status: 'idle', message: '' } );
					} }
					onModelChange={ ( nextModel ) => setModel( nextModel ) }
					onApiKeyChange={ ( value ) => setApiKey( value ) }
					onRetryConnection={ handleConnectionRetry }
				/>
			);
		}
		if ( route === 'onboarding/guardrails' ) {
			return (
				<GuardrailsStep
					selectedPreset={ preset }
					onSelectPreset={ setPreset }
				/>
			);
		}
		if ( route === 'onboarding/test-command' ) {
			return (
				<TestCommandStep
					prompt={ commandPrompt }
					status={ commandState.status }
					message={ commandState.message }
					result={ commandState.result }
					onPromptChange={ setCommandPrompt }
					onRunCommand={ handleRunCommand }
				/>
			);
		}

		return (
			<DoneStep
				progress={ onboarding.progress }
				provider={ provider }
				model={ model }
				preset={ preset }
			/>
		);
	};

	return (
		<OnboardingLayout
			steps={ ONBOARDING_STEPS.map( ( step ) => ( {
				key: step.key,
				label: step.label,
			} ) ) }
			currentIndex={ currentIndex }
			title={ currentStep?.title || '' }
			subtitle={ currentStep?.subtitle || '' }
			onBack={ handleBack }
			onSkip={ handleSkip }
			onContinue={ handleContinue }
			onExit={ handleExit }
			onNavigate={ handleStepNavigate }
			showBack={
				route !== 'onboarding/welcome' && route !== 'onboarding/done'
			}
			showSkip={
				route !== 'onboarding/welcome' && route !== 'onboarding/done'
			}
			continueLoading={ working }
			skipDisabled={ working }
			continueDisabled={ continueDisabled }
			continueLabel={ continueLabel }
			isWide={ isWideStep }
		>
			{ error && (
				<Alert variant="destructive" className="mb-4">
					{ error }
				</Alert>
			) }
			{ renderContent() }
		</OnboardingLayout>
	);
}
