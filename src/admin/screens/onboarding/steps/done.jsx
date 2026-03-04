import { __ } from '@wordpress/i18n';
import {
	CheckCircle2,
	CircleDashed,
	ExternalLink,
	PartyPopper,
} from 'lucide-react';
import { cn } from '../../../components/ui';

const CHECKLIST_ITEMS = [
	{
		progressKey: 'provider_saved',
		label: __( 'AI provider connected', 'openwp' ),
	},
	{
		progressKey: 'connection_test_passed',
		label: __( 'Connection verified', 'openwp' ),
	},
	{
		progressKey: 'guardrail_preset_saved',
		label: __( 'Safety guardrails configured', 'openwp' ),
	},
	{
		progressKey: 'first_command_passed',
		label: __( 'Test command successful', 'openwp' ),
	},
];

export default function DoneStep( { progress = {} } ) {
	const allPassed = CHECKLIST_ITEMS.every(
		( item ) => progress[ item.progressKey ]
	);

	return (
		<div className="grid gap-5">
			{ /* Celebration icon */ }
			<div className="flex justify-center">
				<div className="flex h-16 w-16 items-center justify-center rounded-full bg-success/12">
					<PartyPopper className="h-8 w-8 text-success" />
				</div>
			</div>

			{ /* Summary text */ }
			<p className="m-0 text-center text-sm leading-relaxed text-muted">
				{ allPassed
					? __(
						'Everything is configured and working. OpenWP is ready for production use.',
						'openwp'
					)
					: __(
						'Some steps were skipped. You can complete them later in Settings.',
						'openwp'
					) }
			</p>

			{ /* Checklist */ }
			<div className="rounded-xl border border-solid border-line bg-[linear-gradient(135deg,#f9fbff,#f1f9f8)] p-4">
				<div className="grid gap-2.5">
					{ CHECKLIST_ITEMS.map( ( item ) => {
						const passed = !! progress[ item.progressKey ];
						return (
							<div
								key={ item.progressKey }
								className="flex items-center gap-2.5"
							>
								{ passed ? (
									<CheckCircle2 className="h-4 w-4 text-success" />
								) : (
									<CircleDashed className="h-4 w-4 text-muted/50" />
								) }
								<span
									className={ cn(
										'text-sm',
										passed ? 'text-ink' : 'text-muted'
									) }
								>
									{ item.label }
								</span>
							</div>
						);
					} ) }
				</div>
			</div>

			{ /* Documentation link */ }
			<div className="flex justify-center">
				<a
					href="https://openwp.ai/docs"
					target="_blank"
					rel="noopener noreferrer"
					className="inline-flex items-center gap-1.5 text-xs text-muted hover:text-primary"
				>
					{ __( 'Read the documentation', 'openwp' ) }
					<ExternalLink className="h-3 w-3" />
				</a>
			</div>
		</div>
	);
}
