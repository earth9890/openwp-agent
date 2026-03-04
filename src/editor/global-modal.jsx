import { useState, useCallback, useRef, useEffect, createPortal } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, select } from '@wordpress/data';
import { useShortcut } from '@wordpress/keyboard-shortcuts';
import { Settings2, ChevronDown, ChevronUp } from 'lucide-react';
import openwpLogo from '../assets/openwp.png';
import { request } from '../shared/api';
import { parseAndRecover } from './utils/recover-blocks';
import useStreamingGeneration from './hooks/use-streaming-generation';
import GenerationProgress from './components/generation-progress';
import GenerationPreview from './components/generation-preview';
import BadgeSelector from './components/badge-selector';
import PromptBox from './components/prompt-box';
import {
	SECTION_TYPES,
	FULL_PAGE_TYPES,
	TONES,
	PROVIDERS,
	OPENROUTER_MODELS,
	defaultProvider,
	defaultModel,
	adminPalette,
	adminContentType,
	adminTone,
	getModelByProvider,
	getOpenRouterModelOptions,
	optionLabel,
} from './constants';
import {
	Alert,
	Button,
	Input,
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '../admin/components/ui';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function ModeBtn({ active, onClick, children }) {
	return (
		<button
			type="button"
			onClick={onClick}
			className={`!border-0 !shadow-none outline-none focus:outline-none rounded-md px-4 py-1.5 text-xs font-semibold transition-colors ${active ? 'bg-[#0057ff] text-white' : 'bg-transparent text-[#3f5578] hover:bg-[#f0f4ff]'}`}
		>
			{children}
		</button>
	);
}

function Field({ label, children }) {
	return (
		<label className="grid gap-1.5">
			<span className="text-xs font-bold text-[#17304f]">{label}</span>
			{children}
		</label>
	);
}

// ---------------------------------------------------------------------------
// Inject CSS once - makes the overlay click-through so users can edit behind
// the modal. Uses !important to survive React re-renders.
// ---------------------------------------------------------------------------

(function injectOverlayCSS() {
	if (document.getElementById('openwp-ai-modal-overlay-css')) return;
	const style = document.createElement('style');
	style.id = 'openwp-ai-modal-overlay-css';
	style.textContent = `
		.openwp-ai-overlay.components-modal__screen-overlay {
			pointer-events: none !important;
			background: none !important;
		}
		.openwp-ai-overlay .components-modal__frame {
			pointer-events: auto !important;
			box-shadow: 0 2px 10px rgba(19, 32, 56, 0.12) !important;
			border: 1px solid #d9e2f2 !important;
			border-radius: 18px !important;
			background: linear-gradient(140deg, #ffffff 0%, #f8fcff 65%, #f5f8ff 100%) !important;
		}
	`;
	document.head.appendChild(style);
})();

const OVERLAY_CLASS = 'openwp-ai-overlay';

// ---------------------------------------------------------------------------
// Staggered fade-in for inserted blocks
// ---------------------------------------------------------------------------

function applyBlockFadeIn(blocks) {
	requestAnimationFrame(() => {
		blocks.forEach((block, index) => {
			const el = document.querySelector(
				`[data-block="${block.clientId}"]`
			);
			if (el) {
				el.style.animation = `openwp-block-fade-in 0.35s ease-out ${index * 50}ms both`;
			}
		});
	});
}

// ---------------------------------------------------------------------------
// Floating trigger button - portaled to document.body
// ---------------------------------------------------------------------------

function FloatingButton({ onClick }) {
	return createPortal(
		<button
			type="button"
			onClick={onClick}
			title={__('OpenWP AI Agent (\u2318\u21e7Space)', 'openwp')}
			style={{
				position: 'fixed',
				bottom: 28,
				right: 28,
				zIndex: 99999,
				width: 48,
				height: 48,
				borderRadius: '50%',
				border: 'none',
				background: 'linear-gradient(140deg, #ffffff 0%, #f4f9ff 58%, #f0f4ff 100%)',
				cursor: 'pointer',
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
				boxShadow: '0 2px 10px rgba(19, 32, 56, 0.16)',
				transition: 'transform 0.2s, box-shadow 0.2s',
			}}
			onMouseEnter={(e) => {
				e.currentTarget.style.transform = 'scale(1.1)';
				e.currentTarget.style.boxShadow = '0 4px 12px rgba(0, 87, 255, 0.18)';
			}}
			onMouseLeave={(e) => {
				e.currentTarget.style.transform = 'scale(1)';
				e.currentTarget.style.boxShadow = '0 2px 10px rgba(19, 32, 56, 0.16)';
			}}
		>
			<img src={openwpLogo} alt="" style={{ width: 28, height: 28 }} />
		</button>,
		document.body
	);
}

// ---------------------------------------------------------------------------
// localStorage helpers for position / size persistence
// ---------------------------------------------------------------------------

const STORAGE_KEY = 'openwp_ai_modal_state';

function loadSavedState() {
	try {
		const raw = localStorage.getItem(STORAGE_KEY);
		return raw ? JSON.parse(raw) : null;
	} catch {
		return null;
	}
}

function saveState(data) {
	try {
		localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
	} catch { }
}

// ---------------------------------------------------------------------------
// Hook: useDraggableModal
// ---------------------------------------------------------------------------

function useDraggableModal() {
	const contentRef = useRef(null);
	const attachedRef = useRef(false);
	const frameRef = useRef(null);
	const lastRectRef = useRef(null);

	const setContentRef = useCallback((node) => {
		contentRef.current = node;
		attachedRef.current = false;
	}, []);

	const persistRect = useCallback(() => {
		const frame = frameRef.current;
		if (frame && frame.isConnected) {
			const rect = frame.getBoundingClientRect();
			lastRectRef.current = {
				x: Math.round(rect.left),
				y: Math.round(rect.top),
				w: Math.round(rect.width),
				h: Math.round(rect.height),
				positioned: frame.style.position === 'fixed',
			};
		}
		if (lastRectRef.current) {
			saveState(lastRectRef.current);
		}
	}, []);

	useEffect(() => {
		const node = contentRef.current;
		if (!node || attachedRef.current) return;

		let frame = node.closest('.components-modal__frame');
		if (!frame) return;

		attachedRef.current = true;
		frameRef.current = frame;

		frame.style.resize = 'both';
		frame.style.overflow = 'hidden';
		frame.style.minWidth = '480px';
		frame.style.minHeight = '320px';
		frame.style.maxWidth = 'none';
		frame.style.maxHeight = 'calc(100vh - 40px)';

		const saved = loadSavedState();
		if (saved) {
			frame.style.width = (saved.w || 660) + 'px';
			frame.style.height = (saved.h || 600) + 'px';

			if (saved.positioned) {
				const vw = window.innerWidth;
				const vh = window.innerHeight;
				const w = saved.w || 660;
				const h = saved.h || 600;
				const x = Math.max(0, Math.min(saved.x || 0, vw - Math.min(w, vw)));
				const y = Math.max(0, Math.min(saved.y || 0, vh - Math.min(h, vh)));

				frame.style.position = 'fixed';
				frame.style.top = y + 'px';
				frame.style.left = x + 'px';
				frame.style.margin = '0';
			}
		} else {
			frame.style.width = '660px';
			frame.style.height = '600px';
		}

		const initRect = frame.getBoundingClientRect();
		lastRectRef.current = {
			x: Math.round(initRect.left),
			y: Math.round(initRect.top),
			w: Math.round(initRect.width),
			h: Math.round(initRect.height),
			positioned: frame.style.position === 'fixed',
		};

		const header = frame.querySelector('.components-modal__header');
		if (!header) return;

		header.style.cursor = 'grab';
		header.style.userSelect = 'none';

		const onDown = (e) => {
			if (e.button !== 0 || e.target.closest('button')) return;
			e.preventDefault();

			const r = frame.getBoundingClientRect();
			frame.style.position = 'fixed';
			frame.style.top = r.top + 'px';
			frame.style.left = r.left + 'px';
			frame.style.margin = '0';

			const offX = e.clientX - r.left;
			const offY = e.clientY - r.top;
			const doc = frame.ownerDocument;

			doc.body.style.userSelect = 'none';
			doc.body.style.cursor = 'grabbing';
			header.style.cursor = 'grabbing';

			const onMove = (me) => {
				frame.style.top = Math.max(0, me.clientY - offY) + 'px';
				frame.style.left = Math.max(0, me.clientX - offX) + 'px';
			};

			const onUp = () => {
				doc.removeEventListener('mousemove', onMove);
				doc.removeEventListener('mouseup', onUp);
				doc.body.style.userSelect = '';
				doc.body.style.cursor = '';
				header.style.cursor = 'grab';
				persistRect();
			};

			doc.addEventListener('mousemove', onMove);
			doc.addEventListener('mouseup', onUp);
		};

		header.addEventListener('mousedown', onDown);

		const observer = new ResizeObserver(() => {
			persistRect();
		});
		observer.observe(frame);

		return () => {
			header.removeEventListener('mousedown', onDown);
			observer.disconnect();
			if (lastRectRef.current) {
				saveState(lastRectRef.current);
			}
		};
	});

	return { setContentRef, persistRect };
}

// ---------------------------------------------------------------------------
// Main plugin component
// ---------------------------------------------------------------------------

export default function GlobalAIModal() {
	const [isOpen, setIsOpen] = useState(false);
	const [prompt, setPrompt] = useState('');
	const [generationMode, setMode] = useState('section');
	const [contentType, setContentType] = useState(adminContentType);
	const [tone, setTone] = useState(adminTone);
	const [provider, setProvider] = useState(defaultProvider);
	const [model, setModel] = useState(defaultModel);
	const [showAdvanced, setShowAdvanced] = useState(false);

	const {
		isGenerating,
		generatedContent,
		errorMessage,
		setErrorMessage,
		generate,
		cancel,
		reset,
	} = useStreamingGeneration();

	const { insertBlocks } = useDispatch('core/block-editor');
	const { setContentRef: modalContentRef, persistRect } = useDraggableModal();

	const closeModal = useCallback(() => {
		if (isGenerating) {
			cancel();
		}
		reset();
		persistRect();
		setIsOpen(false);
	}, [persistRect, isGenerating, cancel, reset]);

	useShortcut(
		'openwp/ai-generator',
		useCallback(() => setIsOpen((prev) => {
			if (prev) {
				if (isGenerating) cancel();
				reset();
				persistRect();
			}
			return !prev;
		}), [persistRect, isGenerating, cancel, reset]),
		{ bindGlobal: true }
	);

	const handleModeSwitch = (newMode) => {
		setMode(newMode);
		const firstType = newMode === 'page' ? FULL_PAGE_TYPES[0].value : SECTION_TYPES[0].value;
		setContentType(firstType);
	};

	const typeOptions = generationMode === 'page' ? FULL_PAGE_TYPES : SECTION_TYPES;

	// --- State logic ---
	const hasPreview = !isGenerating && !!generatedContent;

	// --- Handlers ---

	const handleGenerate = () => {
		if (!prompt.trim()) {
			setErrorMessage(__('Please enter a prompt first.', 'openwp'));
			return;
		}
		generate({ prompt, content_type: contentType, tone, provider, model, palette: adminPalette });
	};

	const handleInsert = () => {
		const blocks = parseAndRecover(generatedContent);
		if (!blocks.length) {
			setErrorMessage(__('No valid blocks could be parsed from the generated content.', 'openwp'));
			return;
		}
		const insertIndex = select('core/block-editor').getBlockCount();
		insertBlocks(blocks, insertIndex);
		applyBlockFadeIn(blocks);
		reset();
		persistRect();
		setIsOpen(false);
		setPrompt('');
	};

	const handleDiscard = () => {
		reset();
	};

	const [isEnhancing, setIsEnhancing] = useState(false);

	const handleEnhance = useCallback(async () => {
		if (!prompt.trim() || isEnhancing) return;
		setIsEnhancing(true);
		try {
			const res = await request('/openwp/v1/editor/enhance-prompt', {
				method: 'POST',
				data: { prompt, content_type: contentType, provider, model },
			});
			if (res?.enhanced_prompt) {
				setPrompt(res.enhanced_prompt);
			} else {
				setErrorMessage(__('Could not enhance prompt — try rephrasing or use it as-is.', 'openwp'));
			}
		} catch ( err ) {
			setErrorMessage( err?.message || __('Failed to enhance prompt. Please try again.', 'openwp') );
		} finally {
			setIsEnhancing(false);
		}
	}, [prompt, contentType, provider, model, isEnhancing, setErrorMessage]);

	const isPage = generationMode === 'page';
	const selectZ = 'z-[160200]';

	return (
		<>
			{!isOpen && <FloatingButton onClick={() => setIsOpen(true)} />}

			{isOpen && (
				<Modal
					title={
						<span style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
							<img src={openwpLogo} alt="" style={{ width: 30, height: 30 }} />
							{__('OpenWP AI Agent', 'openwp')}
						</span>
					}
					onRequestClose={closeModal}
					shouldCloseOnClickOutside={false}
					overlayClassName={OVERLAY_CLASS}
				>
					<div ref={modalContentRef} className="grid gap-4">

						{ /* Mode toggle */}
						<div className="flex w-fit rounded-lg border border-[#d9e2f2] bg-[#f4f9ff] p-0.5">
							<ModeBtn active={generationMode === 'section'} onClick={() => handleModeSwitch('section')}>
								{__('Section', 'openwp')}
							</ModeBtn>
							<ModeBtn active={generationMode === 'page'} onClick={() => handleModeSwitch('page')}>
								{__('Full Page', 'openwp')}
							</ModeBtn>
						</div>

						{ /* Content-type badges */}
						<BadgeSelector
							label={isPage ? __('PAGE TYPE', 'openwp') : __('SECTION TYPE', 'openwp')}
							options={typeOptions}
							value={contentType}
							onChange={setContentType}
							disabled={isGenerating}
						/>

						{ /* Tone badges */}
						<BadgeSelector
							label={__('TONE', 'openwp')}
							options={TONES}
							value={tone}
							onChange={setTone}
							disabled={isGenerating}
						/>

						{ /* Prompt box with rocket */}
						<PromptBox
							value={prompt}
							onChange={(e) => setPrompt(e.target.value)}
							rows={4}
							placeholder={
								isPage
									? __('Build a complete landing page for a fitness coaching business\u2026', 'openwp')
									: __('Write a hero section for a SaaS product that helps restaurants manage online orders.', 'openwp')
							}
							onSubmit={handleGenerate}
							onEnhance={handleEnhance}
							isEnhancing={isEnhancing}
							autoFocus
							disabled={isGenerating}
						/>

						{ /* Advanced options - Provider, Model, Palette */}
						<button
							type="button"
							onClick={() => setShowAdvanced((v) => !v)}
							className="group flex w-full items-center justify-between rounded-lg border border-[#d9e2f2] bg-[#f4f9ff] px-3.5 py-2.5 text-left transition-colors hover:border-[#b8cff5] hover:bg-[#eef3ff] !shadow-none !border-solid outline-none focus:outline-none"
							disabled={isGenerating}
						>
							<span className="flex items-center gap-2 text-sm font-semibold text-[#17304f]">
								<Settings2 className="h-4 w-4 text-[#4a6fa5] group-hover:text-[#0057ff] transition-colors" />
								{__('Advanced Options', 'openwp')}
							</span>
							{showAdvanced
								? <ChevronUp className="h-4 w-4 text-[#667995] group-hover:text-[#0057ff] transition-colors" />
								: <ChevronDown className="h-4 w-4 text-[#667995] group-hover:text-[#0057ff] transition-colors" />
							}
						</button>

						{showAdvanced && (
							<div className="grid gap-4 rounded-lg border border-[#d9e2f2] bg-[linear-gradient(130deg,#f9fbff,#f5f8ff)] p-4">
								<div className="grid grid-cols-2 gap-3">
									<Field label={__('Provider', 'openwp')}>
										<Select
											value={provider}
											onValueChange={(value) => {
												setProvider(value);
												setModel(getModelByProvider(value));
											}}
											disabled={isGenerating}
										>
											<SelectTrigger><SelectValue>{optionLabel(PROVIDERS, provider)}</SelectValue></SelectTrigger>
											<SelectContent className={selectZ}>
												{PROVIDERS.map((item) => (
													<SelectItem key={item.value} value={item.value}>
														{item.label}
													</SelectItem>
												))}
											</SelectContent>
										</Select>
									</Field>

									<Field label={__('Model', 'openwp')}>
										{provider === 'openrouter' ? (
											<Select
												value={model || 'anthropic/claude-sonnet-4-5'}
												onValueChange={setModel}
												disabled={isGenerating}
											>
												<SelectTrigger><SelectValue>{optionLabel(OPENROUTER_MODELS, model || 'anthropic/claude-sonnet-4-5')}</SelectValue></SelectTrigger>
												<SelectContent className={selectZ}>
													{getOpenRouterModelOptions(model || 'anthropic/claude-sonnet-4-5').map((item) => (
														<SelectItem key={item.value} value={item.value}>
															{item.label}
														</SelectItem>
													))}
												</SelectContent>
											</Select>
										) : (
											<Input
												value={model}
												onChange={(e) => setModel(e.target.value)}
												placeholder={__('Model name', 'openwp')}
												disabled={isGenerating}
											/>
										)}
									</Field>
								</div>

								{ /* Palette preview */}
								{adminPalette.length > 0 && (
									<div>
										<span className="mb-1.5 block text-xs font-bold text-[#17304f]">
											{__('Brand Colors (from Settings)', 'openwp')}
										</span>
										<div className="flex flex-wrap gap-1.5">
											{adminPalette.map((color, i) => (
												<span
													key={i}
													title={color}
													className="h-5 w-5 rounded-md border border-white shadow-[0_0_0_1px_rgba(0,0,0,0.1)]"
													style={{ backgroundColor: color }}
												/>
											))}
										</div>
									</div>
								)}
							</div>
						)}

						{ /* Error */}
						{errorMessage && (
							<Alert variant="destructive">{errorMessage}</Alert>
						)}

						{ /* Live preview during generation */}
						{isGenerating && generatedContent && (
							<GenerationPreview content={generatedContent} isStreaming />
						)}

						{ /* Final preview after generation */}
						{hasPreview && (
							<GenerationPreview content={generatedContent} isStreaming={false} />
						)}

						{ /* Actions / Progress - only show during generation or preview */}
						{(isGenerating || hasPreview) && (
							<div className="border-t border-[#dce7f4] pt-4">
								{isGenerating ? (
									<div className="flex items-center justify-between gap-3">
										<GenerationProgress contentType={contentType} />
										<Button variant="secondary" onClick={cancel}>
											{__('Cancel', 'openwp')}
										</Button>
									</div>
								) : (
									<div className="flex items-center gap-2">
										<Button onClick={handleInsert}>
											{__('Insert Content', 'openwp')}
										</Button>
										<Button variant="secondary" onClick={handleGenerate}>
											{__('Regenerate', 'openwp')}
										</Button>
										<Button variant="ghost" onClick={handleDiscard}>
											{__('Discard', 'openwp')}
										</Button>
									</div>
								)}
							</div>
						)}

					</div>
				</Modal>
			)}
		</>
	);
}
