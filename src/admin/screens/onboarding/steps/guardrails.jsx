import { __ } from '@wordpress/i18n';
import { Scale, Shield, Zap, Check } from 'lucide-react';
import { cn } from '../../../components/ui';
import { GUARDRAIL_PRESETS } from '../constants';

const PRESET_ICONS = {
	balanced: Scale,
	strict: Shield,
	fast: Zap,
};

const PRESET_COLORS = {
	balanced: {
		active: 'border-primary bg-primary/5',
		icon: 'text-primary bg-primary/10',
		check: 'bg-primary text-white',
	},
	strict: {
		active: 'border-success bg-success/10',
		icon: 'text-success bg-success/10',
		check: 'bg-success text-white',
	},
	fast: {
		active: 'border-accent bg-accent/10',
		icon: 'text-accent bg-accent/10',
		check: 'bg-accent text-white',
	},
};

export default function GuardrailsStep( { selectedPreset, onSelectPreset } ) {
	return (
		<div className="grid gap-4">
			<div className="grid gap-3">
				{ Object.entries( GUARDRAIL_PRESETS ).map(
					( [ key, preset ] ) => {
						const active = selectedPreset === key;
						const Icon = PRESET_ICONS[ key ] || Scale;
						const colors = PRESET_COLORS[ key ] || PRESET_COLORS.balanced;

						return (
							<button
								key={ key }
								type="button"
								onClick={ () => onSelectPreset( key ) }
								className={ cn(
									'relative flex cursor-pointer items-start gap-3 rounded-xl border-2 bg-[linear-gradient(130deg,#ffffff,#f7fbff)] p-4 text-left transition-all',
									active
										? colors.active
										: 'border-line hover:border-primary/20 hover:bg-bg-soft/30'
								) }
							>
								<div
									className={ cn(
										'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
										colors.icon
									) }
								>
									<Icon className="h-5 w-5" />
								</div>
								<div className="flex-1">
									<div className="flex items-center gap-2">
										<span className="text-sm font-semibold text-ink">
											{ preset.label }
										</span>
									</div>
									<p className="m-0 mt-0.5 text-sm leading-relaxed text-muted">
										{ preset.description }
									</p>
									<p className="m-0 mt-1 text-xs text-muted/70">
										{ preset.note }
									</p>
								</div>
								{ active && (
									<div
										className={ cn(
											'flex h-5 w-5 shrink-0 items-center justify-center rounded-full',
											colors.check
										) }
									>
										<Check className="h-3 w-3" strokeWidth={ 3 } />
									</div>
								) }
							</button>
						);
					}
				) }
			</div>

			<p className="m-0 text-xs text-muted">
				{ __(
					'You can fine-tune these settings anytime in Settings.',
					'openwp'
				) }
			</p>
		</div>
	);
}
