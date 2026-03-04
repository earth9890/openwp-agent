import { __ } from '@wordpress/i18n';
import {
	Blocks,
	Bot,
	Brain,
	Database,
	Shield,
	Workflow,
	Wrench,
} from 'lucide-react';
import openwpLogo from '../../../../assets/openwp.png';
import { cn } from '../../../components/ui';

const FEATURES = [
	{
		icon: Bot,
		title: __( 'Natural-Language Console', 'openwp' ),
		description: __(
			'Run agent commands from Console using your configured provider and model.',
			'openwp'
		),
		color: 'text-primary bg-primary/10',
	},
	{
		icon: Shield,
		title: __( 'Risk Guardrails + Approvals', 'openwp' ),
		description: __(
			'Medium/high/critical actions can require approval with typed confirmation for critical flows.',
			'openwp'
		),
		color: 'text-primary bg-primary/10',
	},
	{
		icon: Database,
		title: __( 'Backups, Logs, Rollback', 'openwp' ),
		description: __(
			'Track every execution, restore backups, and rollback from audit history when needed.',
			'openwp'
		),
		color: 'text-primary bg-primary/10',
	},
	{
		icon: Brain,
		title: __( 'Memory + MCP Modules', 'openwp' ),
		description: __(
			'Store explicit memory and extend capabilities through MCP tool modules.',
			'openwp'
		),
		color: 'text-primary bg-primary/10',
	},
	{
		icon: Blocks,
		title: __( 'Gutenberg AI Generation', 'openwp' ),
		description: __(
			'Generate sections/pages directly in the block editor with streaming preview and insert.',
			'openwp'
		),
		color: 'text-primary bg-primary/10',
	},
	{
		icon: Workflow,
		title: __( 'Action Policy Control', 'openwp' ),
		description: __(
			'Tune low/medium/high/critical behavior per action category in the Actions screen.',
			'openwp'
		),
		color: 'text-primary bg-primary/10',
	},
];

const QUICK_CAPS = [
	__( 'Dashboard + Console', 'openwp' ),
	__( 'Approvals + Backups', 'openwp' ),
	__( 'Logs + Rollback', 'openwp' ),
	__( 'Settings + Actions', 'openwp' ),
	__( 'Memory + MCP', 'openwp' ),
	__( 'Editor + Sitewide Chatbot', 'openwp' ),
];

export default function WelcomeStep() {
	return (
		<div className="grid h-full min-h-0 gap-4">
			{ /* Hero */ }
			<div className="flex flex-col items-center text-center">
				<div className="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 shadow-sm">
					<img
						src={ openwpLogo }
						alt=""
						className="h-10 w-10"
					/>
				</div>
				<p className="m-0 max-w-[46ch] text-sm leading-relaxed text-muted">
					{ __(
						'Set your provider, apply guardrails, and run a live verification command. OpenWP then gives you policy-controlled automation across admin, editor, and sitewide assistant surfaces.',
						'openwp'
					) }
				</p>
			</div>

			{ /* Feature grid */ }
			<div className="max-h-[240px] overflow-y-auto pr-1 [scrollbar-width:thin]">
				<div className="grid gap-2.5 sm:grid-cols-2">
				{ FEATURES.map( ( feature ) => (
					<div
						key={ feature.title }
						className="flex gap-3 rounded-xl border border-solid border-line bg-[linear-gradient(130deg,#ffffff,#f8fbff)] p-3.5 transition-all hover:border-primary/25 hover:shadow-sm"
					>
						<div
							className={ cn(
								'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
								feature.color
							) }
						>
							<feature.icon className="h-4.5 w-4.5" strokeWidth={ 1.75 } />
						</div>
						<div>
							<div className="text-[13px] font-semibold text-ink">
								{ feature.title }
							</div>
							<div className="mt-0.5 text-xs leading-relaxed text-muted">
								{ feature.description }
							</div>
						</div>
					</div>
				) ) }
				</div>
			</div>

			<div className="rounded-xl border border-solid border-line bg-surface-2/60 p-3">
				<div className="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">
					<Wrench className="h-3.5 w-3.5 text-primary" />
					{ __( 'What You Get After Setup', 'openwp' ) }
				</div>
				<div className="flex flex-wrap gap-1.5">
					{ QUICK_CAPS.map( ( label ) => (
						<span
							key={ label }
							className="inline-flex items-center rounded-full border border-line bg-white px-2.5 py-1 text-[11px] font-medium text-[#3f5578]"
						>
							{ label }
						</span>
					) ) }
				</div>
			</div>
		</div>
	);
}
