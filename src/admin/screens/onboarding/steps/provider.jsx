import { __ } from '@wordpress/i18n';
import {
	CheckCircle2,
	XCircle,
	Loader2,
	Lock,
	Pencil,
	X,
} from 'lucide-react';
import { useState, useEffect, useRef } from '@wordpress/element';
import {
	Alert,
	AlertDescription,
	Badge,
	Button,
	Input,
	Label,
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
	cn,
} from '../../../components/ui';

export default function ProviderStep( {
	provider,
	model,
	apiKey,
	errors = {},
	modelOptions = [],
	maskedKey = '',
	hasSavedKey = false,
	connectionStatus = 'idle',
	connectionMessage = '',
	onProviderChange,
	onModelChange,
	onApiKeyChange,
	onRetryConnection,
} ) {
	const [ isEditingKey, setIsEditingKey ] = useState( false );
	const prevMaskedRef = useRef( maskedKey );
	const isTesting = connectionStatus === 'testing';
	const isSuccess = connectionStatus === 'success';
	const isFailed = connectionStatus === 'error';

	// Reset editing state only when the masked key changes (i.e. after a save or provider switch)
	useEffect( () => {
		if ( maskedKey !== prevMaskedRef.current ) {
			prevMaskedRef.current = maskedKey;
			setIsEditingKey( false );
		}
	}, [ maskedKey ] );

	const handleEditClick = () => {
		setIsEditingKey( true );
		onApiKeyChange( '' );
	};

	const handleCancelEdit = () => {
		setIsEditingKey( false );
		onApiKeyChange( '' );
	};

	return (
		<div className="grid gap-5">
			{ /* Provider selection */ }
			<div className="grid gap-1.5">
				<Label className="text-xs font-medium text-muted">
					{ __( 'Provider', 'openwp' ) }
				</Label>
				<Select
					value={ provider }
					onValueChange={ onProviderChange }
				>
					<SelectTrigger>
						<SelectValue />
					</SelectTrigger>
					<SelectContent>
						<SelectItem value="openrouter">OpenRouter</SelectItem>
						<SelectItem value="openai">OpenAI</SelectItem>
						<SelectItem value="anthropic">Anthropic</SelectItem>
						<SelectItem value="glm">GLM (Z.AI)</SelectItem>
					</SelectContent>
				</Select>
				{ errors.provider && (
					<p className="m-0 text-xs text-danger">
						{ errors.provider }
					</p>
				) }
			</div>

			{ /* Model selection */ }
			<div className="grid gap-1.5">
				<Label className="text-xs font-medium text-muted">
					{ __( 'Model', 'openwp' ) }
				</Label>
				{ provider === 'openrouter' ? (
					<Select value={ model } onValueChange={ onModelChange }>
						<SelectTrigger>
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							{ modelOptions.map( ( option ) => (
								<SelectItem
									key={ option.value }
									value={ option.value }
								>
									{ option.label }
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
						placeholder="gpt-5.2"
					/>
				) }
				{ errors.model && (
					<p className="m-0 text-xs text-danger">
						{ errors.model }
					</p>
				) }
			</div>

			{ /* API Key */ }
			<div className="grid gap-1.5">
				<Label className="text-xs font-medium text-muted">
					{ __( 'API Key', 'openwp' ) }
				</Label>
				{ hasSavedKey && ! isEditingKey ? (
					<div className="flex items-center gap-2">
						<Input
							type="text"
							value={ maskedKey }
							disabled
							className="flex-1 font-mono text-xs"
						/>
						<Button
							variant="outline"
							size="sm"
							type="button"
							onClick={ handleEditClick }
							className="shrink-0 gap-1.5"
						>
							<Pencil className="h-3.5 w-3.5" />
							{ __( 'Edit', 'openwp' ) }
						</Button>
					</div>
				) : (
					<div className="flex items-center gap-2">
						<Input
							type="password"
							value={ apiKey }
							onChange={ ( e ) => onApiKeyChange( e.target.value ) }
							placeholder={ __( 'Enter your API key', 'openwp' ) }
							autoComplete="new-password"
							className="flex-1"
						/>
						{ isEditingKey && (
							<Button
								variant="ghost"
								size="sm"
								type="button"
								onClick={ handleCancelEdit }
								className="shrink-0"
								aria-label={ __( 'Cancel editing', 'openwp' ) }
							>
								<X className="h-4 w-4" />
							</Button>
						) }
					</div>
				) }
				{ errors.key && (
					<p className="m-0 text-xs text-danger">{ errors.key }</p>
				) }
			</div>

			{ /* Connection status feedback */ }
			{ isTesting && (
				<div className="flex items-center gap-2 rounded-lg border border-primary/25 bg-primary/10 px-3 py-2.5">
					<Loader2 className="h-4 w-4 animate-spin text-primary" />
					<span className="text-sm text-primary">
						{ __( 'Saving settings and testing connection…', 'openwp' ) }
					</span>
				</div>
			) }

			{ isSuccess && (
				<div className="flex items-center gap-2 rounded-lg border border-success/25 bg-success/10 px-3 py-2.5">
					<CheckCircle2 className="h-4 w-4 text-success" />
					<span className="text-sm text-success">
						{ connectionMessage }
					</span>
				</div>
			) }

			{ isFailed && (
				<div className="grid gap-2">
					<div className="flex items-center gap-2 rounded-lg border border-danger/20 bg-danger/5 px-3 py-2.5">
						<XCircle className="h-4 w-4 text-danger" />
						<span className="text-sm text-danger">
							{ connectionMessage }
						</span>
					</div>
					<Button
						variant="outline"
						size="sm"
						onClick={ onRetryConnection }
						className="w-fit"
					>
						{ __( 'Retry Connection Test', 'openwp' ) }
					</Button>
				</div>
			) }

			<p className="m-0 flex items-center gap-1.5 text-xs text-muted">
				<Lock className="h-3 w-3" />
				{ __( 'Your API key is stored encrypted and never exposed.', 'openwp' ) }
			</p>
		</div>
	);
}
