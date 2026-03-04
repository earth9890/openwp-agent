import { useMemo, useState } from '@wordpress/element';
import EmptyState from '../../components/empty-state';
import {
	Badge,
	Button,
	Input,
	Switch,
	cn,
} from '../../components/ui';
import { strings } from './constants';
import {
	ShieldAlert,
	ShieldCheck,
	ShieldHalf,
	Shield,
	Search,
} from 'lucide-react';

const RISK_ORDER = ['critical', 'high', 'medium', 'low'];

const RISK_META = {
	critical: {
		icon: ShieldAlert,
		badgeVariant: 'danger',
		sidebarActive: 'border-danger/20 bg-danger/[0.07] text-danger',
		sidebarIcon: 'text-danger',
	},
	high: {
		icon: ShieldHalf,
		badgeVariant: 'warning',
		sidebarActive: 'border-warning/20 bg-warning/[0.07] text-warning',
		sidebarIcon: 'text-warning',
	},
	medium: {
		icon: ShieldCheck,
		badgeVariant: 'neutral',
		sidebarActive: 'border-sky-500/20 bg-sky-500/[0.07] text-sky-700',
		sidebarIcon: 'text-sky-600',
	},
	low: {
		icon: Shield,
		badgeVariant: 'success',
		sidebarActive: 'border-success/20 bg-success/[0.07] text-success',
		sidebarIcon: 'text-success',
	},
};

// ---------------------------------------------------------------------------
// Policy row - one action
// ---------------------------------------------------------------------------

function PolicyRow({ item, policy, onPolicyChange }) {
	const currentPolicy = policy[item.key] || {};
	const enabled = currentPolicy.enabled !== false;
	const requireApproval = Object.prototype.hasOwnProperty.call(
		currentPolicy,
		'require_approval'
	)
		? !!currentPolicy.require_approval
		: item.risk !== 'low';

	const riskMeta = RISK_META[item.risk] || RISK_META.low;

	return (
		<div className="grid items-center gap-2.5 rounded-lg border-[0.5px] border-solid border-line bg-white px-3 py-2.5 [grid-template-columns:minmax(0,1fr)_auto] max-[1080px]:grid-cols-1">
			<div className="flex min-w-0 items-center gap-2">
				<code className="break-all rounded-md bg-bg-soft px-2 py-0.5 font-mono text-xs text-ink">
					{item.key}
				</code>
				<Badge
					variant={riskMeta.badgeVariant}
					className="px-1.5 py-0 text-[9px] font-bold uppercase tracking-[0.06em]"
				>
					{item.risk}
				</Badge>
			</div>

			<div className="flex flex-wrap items-center gap-1.5 max-[1080px]:justify-start">
				<Button
					variant={enabled ? 'default' : 'outline'}
					size="xs"
					type="button"
					onClick={() =>
						onPolicyChange(item.key, { enabled: !enabled })
					}
					className={cn(
						'min-w-[80px]',
						enabled
							? ''
							: '!border-line !text-muted'
					)}
				>
					{enabled ? strings.on() : strings.off()}
				</Button>

				<Button
					variant={requireApproval ? 'secondary' : 'outline'}
					size="xs"
					type="button"
					onClick={() =>
						onPolicyChange(item.key, {
							require_approval: !requireApproval,
						})
					}
					className={cn(
						'min-w-[100px]',
						requireApproval
							? ''
							: '!border-line !text-muted'
					)}
				>
					{requireApproval
						? strings.required()
						: strings.optional()}
				</Button>
			</div>
		</div>
	);
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

export default function ActionsTab({
	actions = [],
	policy,
	onPolicyChange,
	onSave,
	isDirty,
}) {
	const [filter, setFilter] = useState('');
	const [activeRisk, setActiveRisk] = useState('critical');

	const grouped = useMemo(() => {
		return RISK_ORDER.reduce((acc, risk) => {
			acc[risk] = actions.filter((a) => a.risk === risk);
			return acc;
		}, {});
	}, [actions]);

	const lowerFilter = filter.trim().toLowerCase();
	const isSearching = lowerFilter.length > 0;

	const filtered = isSearching
		? actions.filter((action) =>
			action.key.toLowerCase().includes(lowerFilter)
		)
		: null;

	// Items to render in the content area.
	const visibleItems = filtered !== null ? filtered : grouped[activeRisk] || [];

	return (
		<div className="grid min-h-0 grid-cols-[220px_1fr] gap-0 overflow-hidden rounded-[14px] border-[0.5px] border-solid border-line bg-white/95 shadow-sm max-[768px]:grid-cols-1">
			{ /* ── Sidebar ── */}
			<aside className="border-r border-line bg-surface-2/60 p-3 max-[768px]:border-b max-[768px]:border-r-0">
				<div className="mb-3 px-2 text-[10px] font-semibold uppercase tracking-[0.09em] text-muted">
					{strings.actionPolicy()}
				</div>
				{ /* Search inside sidebar */}
				<div className="mt-4 px-0.5 mb-4">
					<div className="relative">
						<Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
						<Input
							className="!h-8 !pl-8 !text-xs"
							placeholder={strings.searchActionsPlaceholder()}
							value={filter}
							onChange={(e) => setFilter(e.target.value)}
						/>
					</div>
				</div>
				<nav className="grid gap-1" role="navigation">
					{RISK_ORDER.map((risk) => {
						const meta = RISK_META[risk];
						const Icon = meta.icon;
						const count = grouped[risk]?.length || 0;
						const isActive =
							!isSearching && activeRisk === risk;

						return (
							<button
								key={risk}
								type="button"
								onClick={() => {
									setActiveRisk(risk);
									setFilter('');
								}}
								className={cn(
									'flex w-full items-center gap-2.5 rounded-lg border border-solid px-2.5 py-2 text-left text-[12.5px] font-medium transition-all',
									isActive
										? meta.sidebarActive + ' shadow-sm'
										: 'border-transparent bg-transparent text-ink hover:border-line hover:bg-white'
								)}
							>
								<Icon
									className={cn(
										'h-4 w-4 shrink-0',
										isActive
											? meta.sidebarIcon
											: 'text-muted'
									)}
								/>
								<span className="flex-1">
									{strings.riskLabel(risk)}
								</span>
								<Badge
									variant={isActive ? meta.badgeVariant : 'outline'}
									className="ml-auto px-1.5 py-0 text-[10px] font-semibold"
								>
									{count}
								</Badge>
							</button>
						);
					})}
				</nav>


			</aside>

			{ /* ── Content ── */}
			<div className="flex min-h-0 flex-col">
				<div className="flex-1 overflow-y-auto px-5 py-5">
					{ /* Header */}
					<div className="mb-4">
						<h3 className="m-0 text-[15px] font-semibold text-ink">
							{isSearching
								? strings.searchResults
									? strings.searchResults()
									: filter
								: strings.riskLabel(activeRisk)}
						</h3>
						<p className="m-0 mt-1 text-[12px] leading-[1.5] text-muted">
							{isSearching
								? strings.noActionMatches
									? `${visibleItems.length} result${visibleItems.length !== 1 ? 's' : ''}`
									: ''
								: strings.actionPolicySubtitle()}
						</p>
					</div>

					{!actions.length ? (
						<EmptyState
							title={strings.noActionsRegistered()}
							message={
								strings.noActionsRegisteredMessage()
							}
						/>
					) : visibleItems.length ? (
						<div className="grid gap-2">
							{visibleItems.map((item) => (
								<PolicyRow
									key={item.key}
									item={item}
									policy={policy}
									onPolicyChange={onPolicyChange}
								/>
							))}
						</div>
					) : (
						<EmptyState
							title={strings.noMatches()}
							message={strings.noActionMatches(filter)}
						/>
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
						<Button onClick={onSave} disabled={!isDirty}>
							{strings.savePolicy()}
						</Button>
					</div>
				</div>
			</div>
		</div>
	);
}
