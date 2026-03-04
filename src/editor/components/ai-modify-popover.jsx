import { useState, useRef, useEffect } from '@wordpress/element';
import { Popover } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { Loader2 } from 'lucide-react';
import { parseAndRecover } from '../utils/recover-blocks';
import { streamRequest } from '../../shared/api';
import GenerationPreview from './generation-preview';
import PromptBox from './prompt-box';
import openwpLogo from '../../assets/openwp.png';

const config = window.openwpAiContentGenerator || {};
const defaultProvider = config.defaultProvider || 'openrouter';
const providerDefaults = config.providers || {};
const defaultModel =
	config.defaultModel ||
	providerDefaults[ defaultProvider ] ||
	'anthropic/claude-sonnet-4-5';

const QUICK_ACTIONS = [
	{ label: __( 'Humanize', 'openwp' ), instruction: 'Rewrite the content to sound natural and human-written. Remove any em dashes, replace them with commas or periods. Avoid overly formal or corporate phrasing, filler words like "delve", "utilize", "leverage", "it\'s important to note", and "in conclusion". Use shorter sentences, contractions where natural, and a conversational tone. Keep the same meaning and structure.' },
	{ label: __( 'Fix Grammar', 'openwp' ), instruction: 'Fix any grammar, spelling, or punctuation errors.' },
	{ label: __( 'Improve Writing', 'openwp' ), instruction: 'Improve the writing quality, clarity, and flow.' },
	{ label: __( 'Simplify', 'openwp' ), instruction: 'Simplify the language to be easier to understand.' },
	{ label: __( 'Make Shorter', 'openwp' ), instruction: 'Make the content shorter and more concise while keeping the key message.' },
	{ label: __( 'Make Longer', 'openwp' ), instruction: 'Expand the content with more detail and supporting points.' },
];

export default function AiModifyPopover( { clientId, blockContent, onClose } ) {
	const [ instruction, setInstruction ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ previewContent, setPreviewContent ] = useState( '' );
	const [ lastInstruction, setLastInstruction ] = useState( '' );
	const abortRef = useRef( null );

	const { replaceBlock } = useDispatch( 'core/block-editor' );

	const hasPreview = ! isLoading && !! previewContent;

	useEffect( () => {
		return () => {
			if ( abortRef.current ) {
				abortRef.current.abort();
			}
		};
	}, [] );

	const generateModification = async ( text ) => {
		if ( ! text.trim() ) {
			setError( __( 'Please enter an instruction.', 'openwp' ) );
			return;
		}

		setIsLoading( true );
		setError( '' );
		setPreviewContent( '' );
		setLastInstruction( text );

		const controller = new AbortController();
		abortRef.current = controller;

		let finalContent = '';

		try {
			await streamRequest(
				'/openwp/v1/editor/modify/stream',
				{
					block_content: blockContent,
					instruction: text,
					provider: defaultProvider,
					model: defaultModel,
				},
				( event ) => {
					if ( event.type === 'done' && event.content ) {
						finalContent = event.content;
					}
					if ( event.type === 'error' ) {
						setError(
							event.message ||
								__( 'Failed to modify block.', 'openwp' )
						);
					}
				},
				{ signal: controller.signal }
			);

			if ( finalContent ) {
				setPreviewContent( finalContent );
			}
		} catch ( err ) {
			if ( err?.name !== 'AbortError' ) {
				setError(
					err?.message ||
						__( 'Failed to modify block.', 'openwp' )
				);
			}
		} finally {
			setIsLoading( false );
			abortRef.current = null;
		}
	};

	const handleApply = () => {
		const blocks = parseAndRecover( previewContent );
		if ( blocks && blocks.length ) {
			replaceBlock( clientId, blocks );
			onClose();
		} else {
			setError(
				__(
					'Could not parse the modified content. Try again.',
					'openwp'
				)
			);
		}
	};

	const handleDiscard = () => {
		setPreviewContent( '' );
		setError( '' );
	};

	const handleRetry = () => {
		generateModification( lastInstruction );
	};

	return (
		<Popover
			className="openwp-ai-modify-popover"
			placement="bottom-start"
			shift
			onClose={ () => {
				if ( abortRef.current ) {
					abortRef.current.abort();
				}
				onClose();
			} }
		>
			<div className="p-3" style={ { width: 380 } }>
				<div className="mb-2 flex items-center gap-1.5">
					<img src={ openwpLogo } alt="" className="h-6 w-6" />
					<span className="text-xs font-semibold text-[#132038]">
						{ __( 'OpenWP AI', 'openwp' ) }
					</span>
				</div>

				{ /* ---------- Loading state ---------- */ }
				{ isLoading && (
					<div className="flex items-center gap-2 rounded-md !border !border-solid !border-[#d9e2f2] bg-[#f4f9ff] px-3 py-2.5 !shadow-none">
						<Loader2 size={ 14 } className="animate-spin text-[#0057ff]" />
						<span className="text-xs font-medium text-[#17304f]">
							{ __( 'Generating modification\u2026', 'openwp' ) }
						</span>
					</div>
				) }

				{ /* ---------- Preview state ---------- */ }
				{ hasPreview && (
					<>
						<GenerationPreview content={ previewContent } isStreaming={ false } />

						{ error && (
							<p className="mt-1.5 text-[11px] text-[#dc2626]">{ error }</p>
						) }

						<div className="mt-2 flex items-center gap-1.5">
							<button
								type="button"
								onClick={ handleApply }
								className="!border-0 !shadow-none outline-none focus:outline-none inline-flex items-center gap-1.5 rounded-md bg-[#0057ff] px-3 py-1.5 text-[11px] font-semibold text-white transition-colors hover:bg-[#0043c4]"
							>
								{ __( 'Apply Content', 'openwp' ) }
							</button>
							<button
								type="button"
								onClick={ handleRetry }
								className="!border !border-solid !border-[#d9e2f2] !shadow-none outline-none focus:outline-none inline-flex items-center rounded-md bg-white px-3 py-1.5 text-[11px] font-semibold text-[#17304f] transition-colors hover:!border-[#0057ff] hover:text-[#0057ff]"
							>
								{ __( 'Retry', 'openwp' ) }
							</button>
							<button
								type="button"
								onClick={ handleDiscard }
								className="!border-0 !shadow-none outline-none focus:outline-none inline-flex items-center rounded-md px-3 py-1.5 text-[11px] font-semibold text-[#7a8daa] transition-colors hover:text-[#dc2626]"
							>
								{ __( 'Discard', 'openwp' ) }
							</button>
						</div>
					</>
				) }

				{ /* ---------- Form state ---------- */ }
				{ ! isLoading && ! hasPreview && (
					<>
						<div className="mb-2 flex flex-wrap gap-1">
							{ QUICK_ACTIONS.map( ( action ) => (
								<button
									key={ action.label }
									type="button"
									onClick={ () => generateModification( action.instruction ) }
									className="!border !border-solid !border-[#d9e2f2] !shadow-none outline-none focus:outline-none rounded-full bg-[#f4f9ff] px-2.5 py-1 text-[11px] font-medium text-[#17304f] transition-colors hover:!border-[#0057ff] hover:bg-[#eef3ff] hover:text-[#0057ff]"
								>
									{ action.label }
								</button>
							) ) }
						</div>

						<PromptBox
							value={ instruction }
							onChange={ ( e ) => setInstruction( e.target.value ) }
							placeholder={ __( 'e.g. "Change heading color to blue"', 'openwp' ) }
							onSubmit={ () => generateModification( instruction ) }
							rows={ 2 }
							autoFocus
						/>

						{ error && (
							<p className="mt-1.5 text-[11px] text-[#dc2626]">{ error }</p>
						) }
					</>
				) }
			</div>
		</Popover>
	);
}
