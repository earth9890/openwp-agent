import { useState, useCallback } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { dispatch, select } from '@wordpress/data';
import { request } from '../../../shared/api';
import { parseAndRecover } from '../../utils/recover-blocks';
import useStreamingGeneration from '../../hooks/use-streaming-generation';
import GenerationProgress from '../../components/generation-progress';
import GenerationPreview from '../../components/generation-preview';
import BadgeSelector from '../../components/badge-selector';
import PromptBox from '../../components/prompt-box';
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
} from '../../constants';
import openwpLogo from '../../../assets/openwp.png';
import {
	Alert,
	Button,
	Input,
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '../../../admin/components/ui';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function isFullPage(type) {
	return typeof type === 'string' && type.startsWith('full_');
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function Field({ label, hint, children }) {
	return (
		<label className="mb-3 grid gap-1.5">
			<span className="text-xs font-bold text-[#17304f]">{label}</span>
			{children}
			{hint && <span className="text-[11px] text-[#667995]">{hint}</span>}
		</label>
	);
}

function PalettePicker({ useAdminPalette, colorPalette, onChange }) {
	const activePalette = useAdminPalette ? adminPalette : colorPalette;

	return (
		<div className="mb-3">
			<div className="mb-1.5 flex items-center justify-between gap-2">
				<span className="text-xs font-bold text-[#17304f]">
					{__('Color Palette', 'openwp')}
				</span>
				<button
					type="button"
					onClick={() =>
						onChange({
							useAdminPalette: !useAdminPalette,
							colorPalette: useAdminPalette ? [...adminPalette] : [],
						})
					}
					className="!border-0 !shadow-none outline-none focus:outline-none text-[10px] font-medium text-[#0043c4] underline-offset-2 hover:underline"
				>
					{useAdminPalette
						? __('Customize for this block', 'openwp')
						: __('Use site defaults', 'openwp')}
				</button>
			</div>

			{activePalette.length === 0 && useAdminPalette && (
				<p className="text-[11px] text-[#667995]">
					{__('No site palette set. Define brand colors in OpenWP \u2192 Settings \u2192 Content Generation.', 'openwp')}
				</p>
			)}

			{activePalette.length === 0 && !useAdminPalette && (
				<p className="text-[11px] text-[#667995]">
					{__('No colors added yet.', 'openwp')}
				</p>
			)}

			{activePalette.length > 0 && (
				<div className="flex flex-wrap gap-1.5">
					{activePalette.map((color, i) =>
						useAdminPalette ? (
							<span
								key={i}
								title={color}
								className="h-6 w-6 rounded-md border border-white shadow-[0_0_0_1px_rgba(0,0,0,0.12)]"
								style={{ backgroundColor: color }}
							/>
						) : (
							<label key={i} className="group relative cursor-pointer" title={color}>
								<span
									className="block h-6 w-6 rounded-md border border-white shadow-[0_0_0_1px_rgba(0,0,0,0.12)] transition-transform group-hover:scale-110"
									style={{ backgroundColor: color }}
								/>
								<input
									type="color"
									value={color}
									onChange={(e) => {
										const updated = [...colorPalette];
										updated[i] = e.target.value;
										onChange({ colorPalette: updated });
									}}
									className="sr-only"
								/>
								<button
									type="button"
									onClick={(e) => {
										e.preventDefault();
										const updated = [...colorPalette];
										updated.splice(i, 1);
										onChange({ colorPalette: updated });
									}}
									className="!border-0 !shadow-none outline-none focus:outline-none absolute -right-1 -top-1 hidden h-3.5 w-3.5 items-center justify-center rounded-full bg-[#dc2626] text-[8px] font-bold text-white group-hover:flex"
									aria-label="Remove"
								>
									✕
								</button>
							</label>
						)
					)}

					{!useAdminPalette && colorPalette.length < 8 && (
						<button
							type="button"
							onClick={() =>
								onChange({ colorPalette: [...colorPalette, '#0057ff'] })
							}
							className="!shadow-none outline-none focus:outline-none flex h-6 items-center rounded-md !border !border-dashed !border-[#d9e2f2] px-2 text-[10px] font-semibold text-[#3f5578] hover:!border-[#0057ff] hover:text-[#0057ff]"
						>
							+
						</button>
					)}
				</div>
			)}
		</div>
	);
}

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
// Main Edit component
// ---------------------------------------------------------------------------

export default function Edit({ attributes, setAttributes, clientId }) {
	const blockProps = useBlockProps({
		className: 'rounded-xl border-2 border-dashed border-[#d9e2f2] bg-[linear-gradient(140deg,#f9fbff,#f5f8ff)] p-5',
	});

	const {
		isGenerating,
		generatedContent,
		errorMessage,
		setErrorMessage,
		generate,
		cancel,
		reset,
	} = useStreamingGeneration();

	const [generationMode, setGenerationMode] = useState(
		isFullPage(attributes.contentType || adminContentType) ? 'page' : 'section'
	);

	const {
		prompt = '',
		contentType: rawContentType,
		tone: rawTone,
		provider: rawProvider,
		model: rawModel,
		colorPalette = [],
		useAdminPalette = true,
	} = attributes;

	// Use || so empty-string legacy blocks still fall back to admin defaults.
	const contentType = rawContentType || adminContentType;
	const tone = rawTone || adminTone;
	const provider = rawProvider || defaultProvider;
	const model = rawModel || defaultModel;

	const typeOptions = generationMode === 'page' ? FULL_PAGE_TYPES : SECTION_TYPES;

	const handleModeSwitch = (newMode) => {
		setGenerationMode(newMode);
		const firstType = newMode === 'page' ? FULL_PAGE_TYPES[0].value : SECTION_TYPES[0].value;
		setAttributes({ contentType: firstType });
	};

	const effectivePalette = useAdminPalette ? adminPalette : colorPalette;

	// --- State logic ---
	const hasPreview = !isGenerating && !!generatedContent;
	const showForm = !isGenerating && !generatedContent;

	// --- Handlers ---

	const handleGenerate = () => {
		if (!prompt.trim()) {
			setErrorMessage(__('Please enter a prompt first.', 'openwp'));
			return;
		}
		generate({
			prompt,
			content_type: contentType,
			tone,
			provider,
			model,
			palette: effectivePalette,
		});
	};

	const handleInsert = () => {
		const blocks = parseAndRecover(generatedContent);
		if (!blocks.length) {
			setErrorMessage(__('No valid blocks could be parsed from the generated content.', 'openwp'));
			return;
		}
		dispatch('core/block-editor').replaceBlocks(clientId, blocks);
		applyBlockFadeIn(blocks);
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
				setAttributes({ prompt: res.enhanced_prompt });
			} else {
				setErrorMessage(__('Could not enhance prompt — try rephrasing or use it as-is.', 'openwp'));
			}
		} catch ( err ) {
			setErrorMessage( err?.message || __('Failed to enhance prompt. Please try again.', 'openwp') );
		} finally {
			setIsEnhancing(false);
		}
	}, [prompt, contentType, provider, model, isEnhancing, setAttributes, setErrorMessage]);

	return (
		<>
			{ /* ----------------------------------------------------------------
			     Inspector sidebar - Model + Color Palette only
			---------------------------------------------------------------- */ }
			<InspectorControls>
				<PanelBody title={__('Model Settings', 'openwp')} initialOpen={true}>
					<div className="grid gap-3">
						<Field label={__('Provider', 'openwp')}>
							<Select
								value={provider}
								onValueChange={(value) =>
									setAttributes({
										provider: value,
										model: getModelByProvider(value),
									})
								}
								disabled={isGenerating}
							>
								<SelectTrigger>
									<SelectValue>{optionLabel(PROVIDERS, provider)}</SelectValue>
								</SelectTrigger>
								<SelectContent>
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
									onValueChange={(value) => setAttributes({ model: value })}
									disabled={isGenerating}
								>
									<SelectTrigger>
										<SelectValue>{optionLabel(OPENROUTER_MODELS, model || 'anthropic/claude-sonnet-4-5')}</SelectValue>
									</SelectTrigger>
									<SelectContent>
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
									onChange={(event) => setAttributes({ model: event.target.value })}
									placeholder={__('Model name', 'openwp')}
									disabled={isGenerating}
								/>
							)}
						</Field>
					</div>
				</PanelBody>

				<PanelBody title={__('Color Palette', 'openwp')} initialOpen={false}>
					<PalettePicker
						useAdminPalette={useAdminPalette}
						colorPalette={colorPalette}
						onChange={(updates) => setAttributes(updates)}
					/>
				</PanelBody>
			</InspectorControls>

			{ /* ----------------------------------------------------------------
			     Block canvas
			---------------------------------------------------------------- */ }
			<div {...blockProps}>
				{ /* ---- Idle: form ---- */}
				{showForm && (
					<div className="grid gap-4">
						{ /* Header + mode toggle */}
						<div className="flex flex-wrap items-start justify-between gap-2">
							<div>
								<h3 className="mb-0.5 flex items-center gap-1.5 text-[15px] font-bold text-ink">
									<img src={openwpLogo} alt="" className="h-[30px] w-[30px]" />
									{__('OpenWP AI Agent', 'openwp')}
								</h3>
								<p className="text-[12px] text-muted">
									{__('Pick a type & tone, then describe what you want.', 'openwp')}
								</p>
							</div>
							<div className="flex rounded-lg !border !border-solid !border-[#d9e2f2] p-0.5">
								{[{ label: __('Section', 'openwp'), value: 'section' }, { label: __('Full Page', 'openwp'), value: 'page' }].map(
									(opt) => (
										<button
											key={opt.value}
											type="button"
											onClick={() => handleModeSwitch(opt.value)}
											className={`!border-0 !shadow-none outline-none focus:outline-none rounded-md px-3 py-1 text-[11px] font-semibold transition-colors ${generationMode === opt.value
												? 'bg-[#0057ff] text-white'
												: 'text-[#3f5578] hover:bg-[#f0f4ff]'
												}`}
										>
											{opt.label}
										</button>
									)
								)}
							</div>
						</div>

						{errorMessage && (
							<Alert variant="destructive">
								{errorMessage}
							</Alert>
						)}

						{ /* Content-type badges */}
						<BadgeSelector
							label={generationMode === 'page' ? __('PAGE TYPE', 'openwp') : __('SECTION TYPE', 'openwp')}
							options={typeOptions}
							value={contentType}
							onChange={(v) => setAttributes({ contentType: v })}
							disabled={isGenerating}
						/>

						{ /* Tone badges */}
						<BadgeSelector
							label={__('TONE', 'openwp')}
							options={TONES}
							value={tone}
							onChange={(v) => setAttributes({ tone: v })}
							disabled={isGenerating}
						/>

						{ /* Prompt box with rocket */}
						<PromptBox
							value={prompt}
							onChange={(e) => setAttributes({ prompt: e.target.value })}
							placeholder={
								generationMode === 'page'
									? __('Build a complete landing page for a fitness coaching business. Include testimonials and pricing.', 'openwp')
									: __('Write a hero section for a SaaS product that helps restaurants manage online orders.', 'openwp')
							}
							onSubmit={handleGenerate}
							onEnhance={handleEnhance}
							isEnhancing={isEnhancing}
							disabled={isGenerating}
						/>
					</div>
				)}

				{ /* ---- Generating: live preview ---- */}
				{isGenerating && (
					<div className="grid gap-3">
						<GenerationProgress contentType={contentType} />
						<GenerationPreview content={generatedContent} isStreaming />
						<div>
							<Button variant="secondary" onClick={cancel}>
								{__('Cancel', 'openwp')}
							</Button>
						</div>
					</div>
				)}

				{ /* ---- Result: preview + actions ---- */}
				{hasPreview && (
					<div className="grid gap-3">
						<GenerationPreview content={generatedContent} isStreaming={false} />

						{errorMessage && (
							<Alert variant="destructive">{errorMessage}</Alert>
						)}

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
					</div>
				)}
			</div>
		</>
	);
}
