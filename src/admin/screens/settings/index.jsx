import { useMemo, useState, useEffect, useRef } from '@wordpress/element';
import {
	Alert,
	Badge,
	Button,
	Input,
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
	Switch,
	Textarea,
	Tooltip,
	TooltipContent,
	TooltipProvider,
	TooltipTrigger,
	cn,
} from '../../components/ui';
import { request } from '../../../shared/api';
import { __ } from '@wordpress/i18n';
import {
	Pencil,
	X,
	Settings2,
	KeyRound,
	Gauge,
	Palette,
	Server,
	Copy,
	RefreshCw,
	Trash2,
} from 'lucide-react';
import { getOpenRouterModelOptions } from '../../../shared/utils';
import {
	getProviderMeta,
	strings,
	PRESET_PALETTES,
	DEFAULT_PALETTE,
	COLOR_NAMES,
} from './constants';

// ---------------------------------------------------------------------------
// Sidebar nav items
// ---------------------------------------------------------------------------

const SECTIONS = [
	{
		key: 'general',
		label: () => strings.defaults(),
		icon: Settings2,
	},
	{
		key: 'connections',
		label: () => strings.connections(),
		icon: KeyRound,
	},
	{
		key: 'rate-limits',
		label: () => strings.rateLimits(),
		icon: Gauge,
	},
	{
		key: 'content-generation',
		label: () => strings.contentGeneration(),
		icon: Palette,
	},
	{
		key: 'mcp',
		label: () => strings.mcp(),
		icon: Server,
	},
];

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

function Field({ label, hint, error, children, row }) {
	let helperText = null;
	if (error) {
		helperText = (
			<span className="text-[11px] font-medium text-danger">
				{error}
			</span>
		);
	} else if (hint) {
		helperText = (
			<span className="text-[11px] leading-[1.35] text-muted">
				{hint}
			</span>
		);
	}

	if (row) {
		return (
			<div className="flex items-center justify-between gap-4">
				<div className="grid gap-0.5">
					<span className="text-[13px] font-medium text-ink">
						{label}
					</span>
					{helperText}
				</div>
				{children}
			</div>
		);
	}

	return (
		<div className="grid gap-1.5">
			<span className="text-[11px] font-semibold uppercase tracking-[0.07em] text-muted">
				{label}
			</span>
			{children}
			{helperText}
		</div>
	);
}

function SectionHeading({ title, description }) {
	return (
		<div className="mb-5">
			<h3 className="m-0 text-[15px] font-semibold text-ink">
				{title}
			</h3>
			{description && (
				<p className="m-0 mt-1 text-[12px] leading-[1.5] text-muted">
					{description}
				</p>
			)}
		</div>
	);
}

function ProviderStatusBadge({ hasSavedKey, testState }) {
	if (testState?.status === 'testing') {
		return <Badge variant="warning">{strings.testing()}</Badge>;
	}
	if (testState?.status === 'success') {
		return <Badge variant="success">{strings.connectionReady()}</Badge>;
	}
	if (testState?.status === 'error') {
		return <Badge variant="danger">{strings.connectionFailed()}</Badge>;
	}
	if (hasSavedKey) {
		return <Badge variant="neutral">{strings.connectionReady()}</Badge>;
	}
	return <Badge variant="warning">{strings.connectionMissing()}</Badge>;
}

function resolveModelValue(settings, providerMeta) {
	if (providerMeta.key === 'openrouter') {
		return (
			settings.default_model_openrouter || 'anthropic/claude-sonnet-4-5'
		);
	}
	if (providerMeta.key === 'openai') {
		return settings.default_model_openai || 'gpt-5.2';
	}
	if (providerMeta.key === 'anthropic') {
		return settings.default_model_anthropic || 'claude-3-5-sonnet-latest';
	}
	if (providerMeta.key === 'glm') {
		return settings.default_model_glm || 'glm-5';
	}
	return (settings[providerMeta.modelKey] || '').trim();
}

// ---------------------------------------------------------------------------
// Closest CSS color name helper
// ---------------------------------------------------------------------------

function getColorName(hex) {
	if (!hex || typeof hex !== 'string') {
		return hex || '';
	}

	const h = hex.replace('#', '');
	if (h.length !== 6 && h.length !== 3) {
		return hex;
	}

	const full =
		h.length === 3
			? h[0] + h[0] + h[1] + h[1] + h[2] + h[2]
			: h;

	const r = parseInt(full.substring(0, 2), 16);
	const g = parseInt(full.substring(2, 4), 16);
	const b = parseInt(full.substring(4, 6), 16);

	let closest = '';
	let minDist = Infinity;

	for (const [name, ref] of COLOR_NAMES) {
		const rr = parseInt(ref.substring(1, 3), 16);
		const gg = parseInt(ref.substring(3, 5), 16);
		const bb = parseInt(ref.substring(5, 7), 16);
		const dist =
			(r - rr) * (r - rr) +
			(g - gg) * (g - gg) +
			(b - bb) * (b - bb);
		if (dist < minDist) {
			minDist = dist;
			closest = name;
		}
	}

	return closest;
}

/**
 * Determine whether a color is light (needs dark text) or dark (needs white text).
 */
function isLightColor(hex) {
	const h = hex.replace('#', '');
	const full =
		h.length === 3
			? h[0] + h[0] + h[1] + h[1] + h[2] + h[2]
			: h;
	const r = parseInt(full.substring(0, 2), 16);
	const g = parseInt(full.substring(2, 4), 16);
	const b = parseInt(full.substring(4, 6), 16);
	// Perceived brightness formula.
	return r * 0.299 + g * 0.587 + b * 0.114 > 150;
}

// ---------------------------------------------------------------------------
// Provider row
// ---------------------------------------------------------------------------

function ProviderRow({
	meta,
	settings,
	keys,
	maskedKeys,
	validation,
	testState,
	onSettingsChange,
	onKeyChange,
	onTest,
}) {
	const savedKey = maskedKeys[meta.key];
	const hasSavedKey = !!savedKey;
	const modelValue = resolveModelValue(settings, meta);
	const [isEditingKey, setIsEditingKey] = useState(false);
	const prevMaskedRef = useRef(savedKey);

	useEffect(() => {
		if (savedKey !== prevMaskedRef.current) {
			prevMaskedRef.current = savedKey;
			setIsEditingKey(false);
		}
	}, [savedKey]);

	const handleEditClick = () => {
		setIsEditingKey(true);
		onKeyChange(meta.key, '');
	};

	const handleCancelEdit = () => {
		setIsEditingKey(false);
		onKeyChange(meta.key, '');
	};

	return (
		<div className="overflow-hidden rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-3">
			<div className="mb-2.5 flex items-center justify-between gap-2">
				<div className="flex min-w-0 items-center gap-2">
					<span className="text-[13px] font-semibold text-ink">
						{meta.label}
					</span>
				</div>
				<ProviderStatusBadge
					hasSavedKey={hasSavedKey}
					testState={testState}
				/>
			</div>

			<div className="grid grid-cols-[1fr_1fr_auto] items-end gap-2 max-[920px]:grid-cols-1">
				<div className="min-w-0">
					<Field label={meta.keyLabel}>
						{hasSavedKey && !isEditingKey ? (
							<div className="flex items-center gap-1.5">
								<Input
									type="text"
									value={savedKey}
									disabled
									className="flex-1 font-mono text-xs"
								/>
								<Button
									variant="ghost"
									size="xs"
									type="button"
									onClick={handleEditClick}
									className="shrink-0"
									aria-label={__(
										'Edit API key',
										'openwp'
									)}
								>
									<Pencil className="h-3.5 w-3.5" />
								</Button>
							</div>
						) : (
							<div className="flex items-center gap-1.5">
								<Input
									type="password"
									value={keys[meta.key] || ''}
									onChange={(e) =>
										onKeyChange(
											meta.key,
											e.target.value
										)
									}
									placeholder={meta.placeholder}
									autoComplete="new-password"
									className="flex-1"
								/>
								{isEditingKey && (
									<Button
										variant="ghost"
										size="xs"
										type="button"
										onClick={handleCancelEdit}
										className="shrink-0"
										aria-label={__(
											'Cancel editing',
											'openwp'
										)}
									>
										<X className="h-3.5 w-3.5" />
									</Button>
								)}
							</div>
						)}
					</Field>
				</div>

				<div className="min-w-0">
					<Field
						label={strings.providerModel()}
						error={validation[meta.modelKey]}
					>
						{meta.key === 'openrouter' ? (
							<Select
								value={
									modelValue ||
									'anthropic/claude-sonnet-4-5'
								}
								onValueChange={(value) =>
									onSettingsChange({
										[meta.modelKey]: value,
									})
								}
							>
								<SelectTrigger>
									<SelectValue />
								</SelectTrigger>
								<SelectContent>
									{getOpenRouterModelOptions(
										modelValue ||
										'anthropic/claude-sonnet-4-5'
									).map((item) => (
										<SelectItem
											key={item.value}
											value={item.value}
										>
											{item.label}
										</SelectItem>
									))}
								</SelectContent>
							</Select>
						) : (
							<Input
								value={modelValue}
								onChange={(e) =>
									onSettingsChange({
										[meta.modelKey]: e.target.value,
									})
								}
								placeholder={meta.modelPlaceholder}
							/>
						)}
					</Field>
				</div>

				<Button
					variant="secondary"
					size="xs"
					type="button"
					onClick={onTest}
					disabled={testState?.status === 'testing'}
				>
					{testState?.status === 'testing'
						? strings.testing()
						: strings.testConnection()}
				</Button>
			</div>

			{testState?.message && (
				<p
					className={cn(
						'mt-2 text-xs',
						testState.status === 'error'
							? 'text-danger'
							: 'text-muted'
					)}
				>
					{testState.message}
				</p>
			)}
		</div>
	);
}

// ---------------------------------------------------------------------------
// Palette picker - with color names and swatch labels
// ---------------------------------------------------------------------------

function PalettePicker({ palette = [], onSettingsChange }) {
	const activeName = PRESET_PALETTES.find(
		(p) => JSON.stringify(p.colors) === JSON.stringify(palette)
	)?.name;

	return (
		<div className="grid gap-4">
			<Field
				label={strings.themePalette()}
				hint={strings.themePaletteHint()}
			>
				<Select
					value={activeName || '_custom'}
					onValueChange={(value) => {
						const preset = PRESET_PALETTES.find(
							(p) => p.name === value
						);
						if (preset) {
							onSettingsChange({
								theme_palette: [...preset.colors],
							});
						}
					}}
				>
					<SelectTrigger>
						<SelectValue
							placeholder={strings.palettePresets()}
						/>
					</SelectTrigger>
					<SelectContent>
						{PRESET_PALETTES.map((preset) => (
							<SelectItem
								key={preset.name}
								value={preset.name}
							>
								<span className="flex items-center gap-2">
									<span className="flex -space-x-0.5">
										{preset.colors
											.slice(0, 7)
											.map((c, i) => (
												<span
													key={i}
													className="inline-block h-3.5 w-3.5 rounded-full shadow-[inset_0_0_0_1px_rgba(0,0,0,0.1)]"
													style={{
														backgroundColor: c,
													}}
												/>
											))}
									</span>
									<span>{preset.name}</span>
								</span>
							</SelectItem>
						))}
						{!activeName && palette.length > 0 && (
							<SelectItem value="_custom" disabled>
								{strings.paletteCustom()}
							</SelectItem>
						)}
					</SelectContent>
				</Select>
			</Field>

			{palette.length > 0 && (
				<div className="grid grid-cols-[repeat(auto-fill,minmax(90px,1fr))] gap-2">
					<TooltipProvider delayDuration={200}>
						{palette.map((color, index) => {
							const name = getColorName(color);
							const light = isLightColor(color);
							return (
								<Tooltip key={index}>
									<TooltipTrigger asChild>
										<label className="group relative flex cursor-pointer flex-col items-center gap-1">
											<span
												className="flex h-12 w-full items-end justify-center rounded-lg shadow-[inset_0_0_0_1px_rgba(0,0,0,0.08)] transition-shadow hover:shadow-[inset_0_0_0_2px_rgba(0,0,0,0.15)]"
												style={{
													backgroundColor: color,
												}}
											>
												<span
													className={cn(
														'mb-1 rounded px-1 text-[9px] font-medium leading-[1.4] opacity-80',
														light
															? 'text-black/60'
															: 'text-white/80'
													)}
												>
													{color.toUpperCase()}
												</span>
											</span>
											<span className="max-w-full truncate text-[10px] font-medium text-muted">
												{name}
											</span>
											<input
												type="color"
												value={color}
												onChange={(e) => {
													const updated = [
														...palette,
													];
													updated[index] =
														e.target.value;
													onSettingsChange({
														theme_palette: updated,
													});
												}}
												className="sr-only"
											/>
											<button
												type="button"
												onClick={(e) => {
													e.preventDefault();
													const updated = [
														...palette,
													];
													updated.splice(index, 1);
													onSettingsChange({
														theme_palette: updated,
													});
												}}
												className="absolute -right-1 -top-1 hidden h-4 w-4 items-center justify-center rounded-full bg-danger text-[8px] font-bold leading-none text-white shadow group-hover:flex"
												aria-label={__(
													'Remove color',
													'openwp'
												)}
											>
												✕
											</button>
										</label>
									</TooltipTrigger>
									<TooltipContent side="bottom">
										<span>
											{name} &mdash; {color}
										</span>
									</TooltipContent>
								</Tooltip>
							);
						})}
					</TooltipProvider>

					{palette.length < 7 && (
						<button
							type="button"
							onClick={() =>
								onSettingsChange({
									theme_palette: [
										...palette,
										'#3b82f6',
									],
								})
							}
							className="flex h-12 w-full flex-col items-center justify-center gap-0.5 rounded-lg border-[0.5px] border-dashed border-line text-muted transition-colors hover:border-primary hover:text-primary"
							title={strings.addColor()}
						>
							<span className="text-base leading-none">+</span>
							<span className="text-[9px] font-medium">
								{strings.addColor()}
							</span>
						</button>
					)}
				</div>
			)}

			{palette.length > 0 && (
				<div className="flex justify-end">
					<button
						type="button"
						onClick={() =>
							onSettingsChange({ theme_palette: [...DEFAULT_PALETTE] })
						}
						className="text-[11px] font-medium text-muted hover:text-danger"
					>
						{strings.clearPalette()}
					</button>
				</div>
			)}
		</div>
	);
}

// ---------------------------------------------------------------------------
// Section content components
// ---------------------------------------------------------------------------

function GeneralSection({ settings, validation, onSettingsChange }) {
	return (
		<>
			<SectionHeading
				title={strings.defaults()}
				description={__(
					'Set the default AI provider, timeout, and agent memory behavior.',
					'openwp'
				)}
			/>
			<div className="grid grid-cols-2 gap-4 max-[920px]:grid-cols-1">
				<Field
					label={strings.defaultProvider()}
					error={validation.default_provider}
				>
					<Select
						value={settings.default_provider || 'openrouter'}
						onValueChange={(value) =>
							onSettingsChange({
								default_provider: value,
							})
						}
					>
						<SelectTrigger>
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value="openrouter">
								{strings.openrouter()}
							</SelectItem>
							<SelectItem value="openai">
								{strings.openai()}
							</SelectItem>
							<SelectItem value="anthropic">
								{strings.anthropic()}
							</SelectItem>
							<SelectItem value="glm">
								{strings.glm()}
							</SelectItem>
						</SelectContent>
					</Select>
				</Field>

				<Field
					label={strings.httpTimeout()}
					error={validation.timeout_seconds}
				>
					<Input
						type="number"
						min={5}
						max={300}
						value={settings.timeout_seconds || 45}
						onChange={(e) =>
							onSettingsChange({
								timeout_seconds: Number(
									e.target.value || 0
								),
							})
						}
					/>
				</Field>
			</div>

			<div className="mt-5 rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-3">
				<Field
					row
					label={strings.memoryFeature()}
					hint={strings.memoryFeatureHint()}
				>
					<Switch
						checked={settings.openwp_memory_enabled !== false}
						onCheckedChange={(checked) =>
							onSettingsChange({
								openwp_memory_enabled: checked,
							})
						}
					/>
				</Field>
			</div>
		</>
	);
}

function ConnectionsSection({
	settings,
	keys,
	maskedKeys,
	validation,
	testStates,
	providerMeta,
	onSettingsChange,
	onKeyChange,
	onTest,
}) {
	return (
		<>
			<SectionHeading
				title={strings.connections()}
				description={__(
					'Add your API keys and select default models for each LLM provider.',
					'openwp'
				)}
			/>
			<div className="grid gap-3">
				{providerMeta.map((meta) => (
					<ProviderRow
						key={meta.key}
						meta={meta}
						settings={settings}
						keys={keys}
						maskedKeys={maskedKeys}
						validation={validation}
						testState={testStates[meta.key]}
						onSettingsChange={onSettingsChange}
						onKeyChange={onKeyChange}
						onTest={() => onTest(meta)}
					/>
				))}
			</div>
		</>
	);
}

function RateLimitsSection({ settings, validation, onSettingsChange }) {
	return (
		<>
			<SectionHeading
				title={strings.rateLimits()}
				description={__(
					'Control the maximum number of agent actions allowed per day.',
					'openwp'
				)}
			/>
			<div className="grid grid-cols-2 gap-4 max-[920px]:grid-cols-1">
				<Field
					label={strings.userActionsPerDay()}
					hint={strings.hintPerUser()}
					error={validation.max_actions_user_day}
				>
					<Input
						type="number"
						min={1}
						value={settings.max_actions_user_day || 50}
						onChange={(e) =>
							onSettingsChange({
								max_actions_user_day: Number(
									e.target.value || 0
								),
							})
						}
					/>
				</Field>
				<Field
					label={strings.siteActionsPerDay()}
					hint={strings.hintAcrossUsers()}
					error={validation.max_actions_site_day}
				>
					<Input
						type="number"
						min={1}
						value={settings.max_actions_site_day || 500}
						onChange={(e) =>
							onSettingsChange({
								max_actions_site_day: Number(
									e.target.value || 0
								),
							})
						}
					/>
				</Field>
			</div>
		</>
	);
}

function ContentGenerationSection({ settings, onSettingsChange }) {
	return (
		<>
			<SectionHeading
				title={strings.contentGeneration()}
				description={strings.contentGenerationSubtitle()}
			/>

			<PalettePicker
				palette={settings.theme_palette?.length ? settings.theme_palette : DEFAULT_PALETTE}
				onSettingsChange={onSettingsChange}
			/>

			<div className="mt-5 grid grid-cols-2 gap-4 max-[920px]:grid-cols-1">
				<Field
					label={strings.defaultContentType()}
					hint={strings.defaultContentTypeHint()}
				>
					<Select
						value={
							settings.editor_default_content_type ||
							'hero_section'
						}
						onValueChange={(value) =>
							onSettingsChange({
								editor_default_content_type: value,
							})
						}
					>
						<SelectTrigger>
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value="hero_section">
								{strings.heroSection()}
							</SelectItem>
							<SelectItem value="feature_section">
								{strings.featureSection()}
							</SelectItem>
							<SelectItem value="faq_section">
								{strings.faqSection()}
							</SelectItem>
							<SelectItem value="cta_section">
								{strings.ctaSection()}
							</SelectItem>
							<SelectItem value="blog_intro">
								{strings.blogIntro()}
							</SelectItem>
							<SelectItem value="testimonials_section">
								{strings.testimonialsSection()}
							</SelectItem>
							<SelectItem value="pricing_section">
								{strings.pricingSection()}
							</SelectItem>
							<SelectItem value="about_section">
								{strings.aboutSection()}
							</SelectItem>
							<SelectItem value="newsletter_section">
								{strings.newsletterSection()}
							</SelectItem>
							<SelectItem value="contact_section">
								{strings.contactSection()}
							</SelectItem>
							<SelectItem value="full_landing_page">
								{strings.fullLandingPage()}
							</SelectItem>
							<SelectItem value="full_blog_post">
								{strings.fullBlogPost()}
							</SelectItem>
							<SelectItem value="full_about_page">
								{strings.fullAboutPage()}
							</SelectItem>
							<SelectItem value="full_services_page">
								{strings.fullServicesPage()}
							</SelectItem>
							<SelectItem value="full_contact_page">
								{strings.fullContactPage()}
							</SelectItem>
						</SelectContent>
					</Select>
				</Field>

				<Field
					label={strings.defaultTone()}
					hint={strings.defaultToneHint()}
				>
					<Select
						value={
							settings.editor_default_tone || 'professional'
						}
						onValueChange={(value) =>
							onSettingsChange({
								editor_default_tone: value,
							})
						}
					>
						<SelectTrigger>
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value="professional">
								{strings.toneProfessional()}
							</SelectItem>
							<SelectItem value="friendly">
								{strings.toneFriendly()}
							</SelectItem>
							<SelectItem value="persuasive">
								{strings.tonePersuasive()}
							</SelectItem>
							<SelectItem value="casual">
								{strings.toneCasual()}
							</SelectItem>
							<SelectItem value="technical">
								{strings.toneTechnical()}
							</SelectItem>
						</SelectContent>
					</Select>
				</Field>
			</div>
		</>
	);
}

function generateBearerToken() {
	const alphabet =
		'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
	const length = 48;
	const bytes = new Uint8Array(length);

	if (window?.crypto?.getRandomValues) {
		window.crypto.getRandomValues(bytes);
	} else {
		for (let i = 0; i < length; i += 1) {
			bytes[i] = Math.floor(Math.random() * 256);
		}
	}

	let token = '';
	for (let i = 0; i < length; i += 1) {
		token += alphabet[bytes[i] % alphabet.length];
	}
	return token;
}

function moduleLabel(moduleKey) {
	switch (moduleKey) {
		case 'core':
			return strings.mcpModuleCore();
		case 'woo':
			return strings.mcpModuleWoo();
		case 'plugin':
			return strings.mcpModulePlugin();
		case 'theme':
			return strings.mcpModuleTheme();
		case 'db':
			return strings.mcpModuleDb();
		case 'polylang':
			return strings.mcpModulePolylang();
		default:
			return moduleKey;
	}
}

function McpSection({ settings, validation, onSettingsChange }) {
	const mcp = settings.mcp || {};
	const modules = {
		core: true,
		woo: true,
		plugin: true,
		theme: true,
		db: true,
		polylang: true,
		...(mcp.modules || {}),
	};
	const moduleStatus = mcp.module_status || {};
	const snippets = mcp.snippets || {};
	const endpoints = mcp.endpoints || {};
	const [snippetKey, setSnippetKey] = useState('claude_code');
	const [copyState, setCopyState] = useState('');

	const tokenValue = mcp.bearer_token || '';
	const tokenConfigured =
		!!(tokenValue && tokenValue.trim()) || !!mcp.has_bearer_token;

	const updateMcp = (patch) => {
		onSettingsChange({
			mcp: {
				...mcp,
				...patch,
				modules: {
					...modules,
					...(patch.modules || {}),
				},
			},
		});
	};

	const copyText = async (text, key) => {
		if (!text) {
			return;
		}

		try {
			await window.navigator.clipboard.writeText(text);
			setCopyState(key);
			setTimeout(() => setCopyState(''), 1200);
		} catch {
			setCopyState('');
		}
	};

	const snippetText = snippets[snippetKey] || '';

	return (
		<>
			<SectionHeading
				title={strings.mcpTitle()}
				description={strings.mcpSubtitle()}
			/>

			<div className="grid gap-4">
				<div className="rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-3">
					<div className="grid gap-2.5">
						<Field
							row
							label={strings.mcpEnabled()}
							hint={strings.mcpEnabledHint()}
						>
							<Switch
								checked={!!mcp.enabled}
								onCheckedChange={(checked) =>
									updateMcp({ enabled: checked })
								}
							/>
						</Field>

						<Field
							row
							label={strings.mcpDebug()}
							hint={strings.mcpDebugHint()}
						>
							<Switch
								checked={!!mcp.debug_mode}
								onCheckedChange={(checked) =>
									updateMcp({ debug_mode: checked })
								}
							/>
						</Field>
					</div>
				</div>

				<div className="rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-3">
					<div className="mb-2 flex items-center justify-between gap-2">
						<div className="text-[12px] font-semibold text-ink">
							{strings.mcpBearerToken()}
						</div>
						<Badge variant={tokenConfigured ? 'success' : 'warning'}>
							{tokenConfigured
								? strings.mcpTokenConfigured()
								: strings.mcpTokenMissing()}
						</Badge>
					</div>

					<div className="grid gap-2">
						<Field
							label={strings.mcpBearerToken()}
							hint={strings.mcpBearerHint()}
							error={validation.mcp_bearer_token}
						>
							<Input
								type="text"
								value={tokenValue}
								onChange={(e) =>
									updateMcp({
										bearer_token: e.target.value,
									})
								}
								placeholder="owp_..."
								autoComplete="off"
							/>
						</Field>
						<div className="flex flex-wrap gap-2">
							<Button
								variant="secondary"
								size="xs"
								type="button"
								onClick={() =>
									updateMcp({
										bearer_token: generateBearerToken(),
									})
								}
							>
								<RefreshCw className="mr-1 h-3.5 w-3.5" />
								{strings.mcpGenerateToken()}
							</Button>
							<Button
								variant="ghost"
								size="xs"
								type="button"
								onClick={() =>
									updateMcp({ bearer_token: '' })
								}
							>
								<Trash2 className="mr-1 h-3.5 w-3.5" />
								{strings.mcpRevokeToken()}
							</Button>
						</div>
					</div>
				</div>

				<div className="rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-3">
					<div className="mb-2 text-[12px] font-semibold text-ink">
						{strings.mcpModuleToggles()}
					</div>
					<div className="grid gap-2">
						{Object.keys(modules).map((moduleKey) => {
							const status = moduleStatus[moduleKey] || {};
							const available = status.available !== false;
							const toolsCount = Number(
								status.tool_count || 0
							);
							return (
								<div
									key={moduleKey}
									className="flex items-center justify-between rounded-md border border-line bg-white/70 px-2.5 py-2"
								>
									<div className="grid gap-0.5">
										<div className="text-[12px] font-medium text-ink">
											{moduleLabel(moduleKey)}
										</div>
										<div className="flex items-center gap-1.5 text-[11px] text-muted">
											<Badge
												variant={
													available
														? 'success'
														: 'neutral'
												}
											>
												{available
													? strings.mcpAvailable()
													: strings.mcpUnavailable()}
											</Badge>
											<span>
												{strings.mcpTools(
													toolsCount
												)}
											</span>
										</div>
									</div>
									<Switch
										checked={!!modules[moduleKey]}
										disabled={!available}
										onCheckedChange={(checked) =>
											updateMcp({
												modules: {
													[moduleKey]: checked,
												},
											})
										}
									/>
								</div>
							);
						})}
					</div>
				</div>

				<div className="rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-3">
					<div className="mb-2 text-[12px] font-semibold text-ink">
						{strings.mcpClientSteps()}
					</div>
					<div className="grid gap-1 text-[12px] text-muted">
						<p className="m-0">{strings.mcpStepOne()}</p>
						<p className="m-0">{strings.mcpStepTwo()}</p>
						<p className="m-0">{strings.mcpStepThree()}</p>
						<p className="m-0">{strings.mcpStepFour()}</p>
					</div>

					<div className="mt-3 grid gap-2">
						<div className="grid grid-cols-[1fr_auto_auto] gap-2 max-[920px]:grid-cols-1">
							<Input
								value={endpoints.http || ''}
								readOnly
								placeholder="/wp-json/mcp/v1/http"
							/>
							<Button
								variant="secondary"
								size="xs"
								type="button"
								onClick={() =>
									copyText(
										endpoints.http || '',
										'http'
									)
								}
							>
								<Copy className="mr-1 h-3.5 w-3.5" />
								{copyState === 'http'
									? 'Copied'
									: strings.mcpCopyHttpEndpoint()}
							</Button>
							<Button
								variant="secondary"
								size="xs"
								type="button"
								onClick={() =>
									copyText(
										endpoints.sse || '',
										'sse'
									)
								}
							>
								<Copy className="mr-1 h-3.5 w-3.5" />
								{copyState === 'sse'
									? 'Copied'
									: strings.mcpCopySseEndpoint()}
							</Button>
						</div>
					</div>
				</div>

				<div className="rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-3">
					<div className="mb-2 text-[12px] font-semibold text-ink">
						{strings.mcpSnippet()}
					</div>
					<div className="grid grid-cols-[220px_1fr_auto] gap-2 max-[920px]:grid-cols-1">
						<Select
							value={snippetKey}
							onValueChange={(value) =>
								setSnippetKey(value)
							}
						>
							<SelectTrigger>
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								<SelectItem value="claude_code">
									{strings.mcpSnippetClaudeCode()}
								</SelectItem>
								<SelectItem value="json_config">
									{strings.mcpSnippetJson()}
								</SelectItem>
								<SelectItem value="curl_initialize">
									{strings.mcpSnippetCurlInit()}
								</SelectItem>
								<SelectItem value="curl_tools_list">
									{strings.mcpSnippetCurlTools()}
								</SelectItem>
								<SelectItem value="sse_hint">
									{strings.mcpSnippetSse()}
								</SelectItem>
							</SelectContent>
						</Select>
						<Textarea
							readOnly
							value={snippetText}
							rows={8}
							className="font-mono text-[11px]"
						/>
						<Button
							variant="secondary"
							size="xs"
							type="button"
							onClick={() =>
								copyText(
									snippetText,
									`snippet-${snippetKey}`
								)
							}
						>
							<Copy className="mr-1 h-3.5 w-3.5" />
							{copyState === `snippet-${snippetKey}`
								? 'Copied'
								: strings.mcpCopySnippet()}
						</Button>
					</div>
				</div>
			</div>
		</>
	);
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

export default function SettingsTab({
	settings,
	keys,
	maskedKeys,
	onSettingsChange,
	onKeyChange,
	onSave,
	isDirty,
}) {
	const providerMeta = getProviderMeta();
	const [activeSection, setActiveSection] = useState('general');
	const [testStates, setTestStates] = useState({});

	const validation = useMemo(() => {
		const errors = {};

		if (!settings.default_provider) {
			errors.default_provider = strings.requiredField();
		}

		const timeoutSeconds = Number(settings.timeout_seconds || 0);
		if (
			!Number.isFinite(timeoutSeconds) ||
			timeoutSeconds < 5 ||
			timeoutSeconds > 300
		) {
			errors.timeout_seconds = strings.numberRange(5, 300);
		}

		const maxUser = Number(settings.max_actions_user_day || 0);
		if (!Number.isFinite(maxUser) || maxUser < 1) {
			errors.max_actions_user_day = strings.numberMin(1);
		}

		const maxSite = Number(settings.max_actions_site_day || 0);
		if (!Number.isFinite(maxSite) || maxSite < 1) {
			errors.max_actions_site_day = strings.numberMin(1);
		}

		providerMeta.forEach((meta) => {
			const modelValue = resolveModelValue(settings, meta);
			if (!modelValue) {
				errors[meta.modelKey] = strings.requiredField();
			}
		});

		const mcp = settings.mcp || {};
		const hasToken =
			!!mcp.has_bearer_token ||
			!!(mcp.bearer_token && String(mcp.bearer_token).trim());
		if (mcp.enabled && !hasToken) {
			errors.mcp_bearer_token = strings.requiredField();
		}

		return errors;
	}, [providerMeta, settings]);

	const hasErrors = Object.keys(validation).length > 0;

	const runConnectionTest = async (meta) => {
		const model =
			resolveModelValue(settings, meta) || meta.modelPlaceholder;
		const hasSavedKey = !!maskedKeys[meta.key];

		if (!hasSavedKey) {
			setTestStates((prev) => ({
				...prev,
				[meta.key]: {
					status: 'error',
					message: strings.saveToTestNotice(),
				},
			}));
			return;
		}

		setTestStates((prev) => ({
			...prev,
			[meta.key]: { status: 'testing', message: '' },
		}));

		try {
			await request('/openwp/v1/editor/generate', {
				method: 'POST',
				data: {
					prompt: 'Return one short sentence confirming API connectivity.',
					content_type: 'paragraph',
					tone: 'professional',
					provider: meta.key,
					model,
				},
			});

			setTestStates((prev) => ({
				...prev,
				[meta.key]: {
					status: 'success',
					message: `${strings.connectionSuccess()} ${strings.testedWithModel(
						model
					)}`,
				},
			}));
		} catch (err) {
			setTestStates((prev) => ({
				...prev,
				[meta.key]: {
					status: 'error',
					message: err?.message || strings.connectionFailed(),
				},
			}));
		}
	};

	const renderSection = () => {
		switch (activeSection) {
			case 'general':
				return (
					<GeneralSection
						settings={settings}
						validation={validation}
						onSettingsChange={onSettingsChange}
					/>
				);
			case 'connections':
				return (
					<ConnectionsSection
						settings={settings}
						keys={keys}
						maskedKeys={maskedKeys}
						validation={validation}
						testStates={testStates}
						providerMeta={providerMeta}
						onSettingsChange={onSettingsChange}
						onKeyChange={onKeyChange}
						onTest={runConnectionTest}
					/>
				);
			case 'rate-limits':
				return (
					<RateLimitsSection
						settings={settings}
						validation={validation}
						onSettingsChange={onSettingsChange}
					/>
				);
			case 'content-generation':
				return (
					<ContentGenerationSection
						settings={settings}
						onSettingsChange={onSettingsChange}
					/>
				);
			case 'mcp':
				return (
					<McpSection
						settings={settings}
						validation={validation}
						onSettingsChange={onSettingsChange}
					/>
				);
			default:
				return null;
		}
	};

	return (
		<div className="grid min-h-0 grid-cols-[220px_1fr] gap-0 overflow-hidden rounded-[14px] border-[0.5px] border-solid border-line bg-white/95 shadow-sm max-[768px]:grid-cols-1">
			{ /* ── Sidebar ── */}
			<aside className="border-r border-line bg-surface-2/60 p-3 max-[768px]:border-b max-[768px]:border-r-0">
				<div className="mb-3 px-2 text-[10px] font-semibold uppercase tracking-[0.09em] text-muted">
					{strings.settingsTitle()}
				</div>
				<nav className="grid gap-1" role="navigation">
					{SECTIONS.map((section) => {
						const isActive = activeSection === section.key;
						const Icon = section.icon;
						return (
							<button
								key={section.key}
								type="button"
								onClick={() =>
									setActiveSection(section.key)
								}
								className={cn(
									'flex w-full items-center gap-2.5 rounded-lg border border-solid px-2.5 py-2 text-left text-[12.5px] font-medium transition-all',
									isActive
										? 'border-primary/15 bg-primary/[0.07] text-primary shadow-sm'
										: 'border-transparent bg-transparent text-ink hover:border-line hover:bg-white'
								)}
							>
								<Icon
									className={cn(
										'h-4 w-4 shrink-0',
										isActive
											? 'text-primary'
											: 'text-muted'
									)}
								/>
								<span>{section.label()}</span>
							</button>
						);
					})}
				</nav>
			</aside>

			{ /* ── Content ── */}
			<div className="flex min-h-0 flex-col">
				<div className="flex-1 overflow-y-auto px-5 py-5">
					{renderSection()}

					{hasErrors && (
						<Alert className="mt-5" variant="destructive">
							{strings.fixValidationErrors()}
						</Alert>
					)}
				</div>

				{ /* ── Sticky save bar ── */}
				<div className="border-t border-line bg-white/96 px-5 py-3 backdrop-blur">
					<div className="flex flex-wrap items-center justify-between gap-3">
						<div className="text-xs font-medium text-muted">
							{isDirty
								? strings.unsavedChanges()
								: strings.allChangesSaved()}
						</div>
						<Button
							onClick={onSave}
							disabled={!isDirty || hasErrors}
						>
							{strings.saveSettings()}
						</Button>
					</div>
				</div>
			</div>
		</div>
	);
}
