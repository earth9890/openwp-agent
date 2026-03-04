import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Rocket, Wand2, Loader2 } from 'lucide-react';
import * as TooltipPrimitive from '@radix-ui/react-tooltip';

/**
 * Prompt textarea with an embedded rocket launch button and optional enhance (wand) button.
 *
 * The rocket animates on click before firing the onSubmit callback,
 * giving a delightful "launch" feel.
 *
 * @param {Object}   props
 * @param {string}   props.value        Textarea value.
 * @param {Function} props.onChange      onChange handler (receives native event).
 * @param {string}   [props.placeholder] Placeholder text.
 * @param {Function} props.onSubmit      Called (no args) after the rocket animation.
 * @param {Function} [props.onEnhance]   Called (no args) to enhance the prompt via AI.
 * @param {boolean}  [props.isEnhancing] Whether the prompt is currently being enhanced.
 * @param {boolean}  [props.disabled]    Disable textarea and button.
 * @param {number}   [props.rows=3]      Number of textarea rows.
 * @param {boolean}  [props.autoFocus]   Auto-focus the textarea on mount.
 */
export default function PromptBox( {
	value,
	onChange,
	placeholder,
	onSubmit,
	onEnhance,
	isEnhancing = false,
	disabled,
	rows = 3,
	autoFocus = false,
} ) {
	const [ isLaunching, setIsLaunching ] = useState( false );
	const textareaRef = useRef( null );
	const onSubmitRef = useRef( onSubmit );
	onSubmitRef.current = onSubmit;

	useEffect( () => {
		if ( autoFocus && textareaRef.current ) {
			textareaRef.current.focus();
		}
	}, [ autoFocus ] );

	const handleLaunch = () => {
		if ( ! value?.trim() || disabled || isLaunching ) return;
		setIsLaunching( true );
		setTimeout( () => {
			setIsLaunching( false );
			onSubmitRef.current();
		}, 500 );
	};

	const handleKeyDown = ( e ) => {
		if ( e.key === 'Enter' && ( e.metaKey || e.ctrlKey ) ) {
			e.preventDefault();
			handleLaunch();
		}
	};

	const isMac = typeof navigator !== 'undefined' && /Mac/.test( navigator.platform );

	return (
		<TooltipPrimitive.Provider delayDuration={ 200 }>
			<div className="openwp-prompt-box relative rounded-xl !border !border-solid !border-[#d9e2f2] bg-[linear-gradient(140deg,#ffffff,#f7fbff)] transition-all duration-200 focus-within:!border-[#0057ff] focus-within:shadow-[0_0_0_3px_rgba(0, 87, 255, 0.12)]">
				<textarea
					ref={ textareaRef }
					value={ value }
					onChange={ onChange }
					onKeyDown={ handleKeyDown }
					placeholder={ placeholder }
					rows={ rows }
					disabled={ disabled || isEnhancing }
					className="!border-0 !shadow-none !outline-none focus:!outline-none focus:!ring-0 focus:!shadow-none w-full resize-none bg-transparent px-3.5 pt-3 pb-11 text-[13px] leading-relaxed text-[#132038] placeholder-[#9babc3]"
				/>
				<div className="absolute bottom-2.5 right-2.5 flex items-center gap-2">
					<span className="select-none text-[10px] font-medium text-[#aab8cd]">
						{ isMac ? '\u2318\u21b5' : 'Ctrl+\u21b5' }
					</span>
					{ onEnhance && (
						<TooltipPrimitive.Root>
							<TooltipPrimitive.Trigger asChild>
								<button
									type="button"
									onClick={ onEnhance }
									disabled={ ! value?.trim() || disabled || isEnhancing }
									className="!border-0 !shadow-none outline-none focus:outline-none flex h-8 w-8 items-center justify-center rounded-full bg-[#f0f4ff] text-[#0057ff] transition-all duration-200 hover:bg-[#e0eaff] hover:shadow-sm hover:shadow-[#0057ff]/15 active:scale-95 disabled:bg-[#f5f5f5] disabled:text-[#a8b8cc] disabled:cursor-not-allowed disabled:hover:shadow-none"
								>
									{ isEnhancing ? (
										<Loader2 size={ 15 } className="animate-spin" />
									) : (
										<Wand2 size={ 15 } />
									) }
								</button>
							</TooltipPrimitive.Trigger>
							<TooltipPrimitive.Content
								side="top"
								sideOffset={ 6 }
								className="z-50 rounded-md bg-ink px-3 py-1.5 text-xs text-white animate-in fade-in-0 zoom-in-95"
							>
								{ __( 'Enhance prompt', 'openwp' ) }
							</TooltipPrimitive.Content>
						</TooltipPrimitive.Root>
					) }
					<TooltipPrimitive.Root>
						<TooltipPrimitive.Trigger asChild>
							<button
								type="button"
								onClick={ handleLaunch }
								disabled={ ! value?.trim() || disabled || isEnhancing }
								className="openwp-rocket-btn !border-0 !shadow-none outline-none focus:outline-none flex h-8 w-8 items-center justify-center rounded-full bg-[#0057ff] text-white transition-all duration-200 hover:bg-[#0043c4] hover:shadow-sm active:scale-95 disabled:bg-[#e8eef5] disabled:text-[#a8b8cc] disabled:cursor-not-allowed disabled:hover:shadow-none"
							>
								<span className={ isLaunching ? 'openwp-rocket-launch inline-flex' : 'inline-flex transition-transform' }>
									<Rocket size={ 15 } />
								</span>
							</button>
						</TooltipPrimitive.Trigger>
						<TooltipPrimitive.Content
							side="top"
							sideOffset={ 6 }
							className="z-50 rounded-md bg-ink px-3 py-1.5 text-xs text-white animate-in fade-in-0 zoom-in-95"
						>
							{ __( 'Generate', 'openwp' ) }
						</TooltipPrimitive.Content>
					</TooltipPrimitive.Root>
				</div>
			</div>
		</TooltipPrimitive.Provider>
	);
}
