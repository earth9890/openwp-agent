import { __ } from '@wordpress/i18n';
import {
	CheckCircle2,
	XCircle,
	Loader2,
	Play,
	RotateCw,
	Terminal,
} from 'lucide-react';
import {
	Alert,
	Button,
	Label,
	Textarea,
	cn,
} from '../../../components/ui';

export default function TestCommandStep( {
	prompt,
	status = 'idle',
	message = '',
	result = null,
	onPromptChange,
	onRunCommand,
} ) {
	const running = status === 'running';
	const success = status === 'success';
	const failed = status === 'error';

	// Extract a friendly summary from the result
	const getSummary = () => {
		if ( ! result ) {
			return null;
		}

		const execution = result?.execution || result;
		const output = execution?.result || execution?.output || execution?.summary || '';

		if ( typeof output === 'string' && output.trim() ) {
			return output;
		}

		if ( typeof output === 'object' ) {
			return JSON.stringify( output, null, 2 );
		}

		return null;
	};

	const summary = getSummary();

	return (
		<div className="grid gap-4">
			<div className="grid gap-1.5">
				<Label className="text-xs font-medium text-muted">
					{ __( 'Command Prompt', 'openwp' ) }
				</Label>
				<Textarea
					value={ prompt }
					onChange={ ( e ) => onPromptChange( e.target.value ) }
					className="min-h-[100px] resize-y font-mono text-sm"
					disabled={ running }
				/>
			</div>

			<div>
				<Button
					variant={ success ? 'outline' : 'secondary' }
					size="sm"
					onClick={ onRunCommand }
					disabled={ running || ! prompt.trim() }
				>
					{ running && (
						<Loader2 className="h-3.5 w-3.5 animate-spin" />
					) }
					{ ! running && ! success && ! failed && (
						<Play className="h-3.5 w-3.5" />
					) }
					{ ! running && failed && (
						<RotateCw className="h-3.5 w-3.5" />
					) }
					{ ! running && success && (
						<RotateCw className="h-3.5 w-3.5" />
					) }
					{ running
						? __( 'Running…', 'openwp' )
						: success || failed
							? __( 'Run Again', 'openwp' )
							: __( 'Run Command', 'openwp' ) }
				</Button>
			</div>

			{ success && (
				<div className="grid gap-2">
					<div className="flex items-center gap-2 rounded-lg border border-success/25 bg-success/10 px-3 py-2.5">
						<CheckCircle2 className="h-4 w-4 shrink-0 text-success" />
						<span className="text-sm text-success">
							{ message }
						</span>
					</div>
					{ summary && (
						<div className="rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-3">
							<div className="mb-1.5 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted">
								<Terminal className="h-3 w-3" />
								{ __( 'Output', 'openwp' ) }
							</div>
							<pre className="m-0 max-h-[200px] overflow-auto whitespace-pre-wrap text-xs leading-relaxed text-ink">
								{ summary }
							</pre>
						</div>
					) }
				</div>
			) }

			{ failed && (
				<div className="flex items-center gap-2 rounded-lg border border-danger/20 bg-danger/5 px-3 py-2.5">
					<XCircle className="h-4 w-4 shrink-0 text-danger" />
					<span className="text-sm text-danger">{ message }</span>
				</div>
			) }
		</div>
	);
}
