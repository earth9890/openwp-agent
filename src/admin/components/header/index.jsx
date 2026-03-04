import StatCard from '../stat-card';
import { fmtInt } from '../../../shared/utils';
import { strings } from './constants';

export default function Header({ metrics }) {
	return (
		<header className="grid gap-4 rounded-3xl border border-solid border-line bg-[linear-gradient(130deg,#ffffff_0%,#f7fbff_58%,#eef8f7_100%)] p-[18px] shadow-openwp md:grid-cols-[minmax(340px,1.2fr)_minmax(340px,1fr)]">
			<div>
				<div className="mb-2.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-primary">{strings.brandKicker()}</div>
				<h1 className="m-0 text-[42px] font-bold leading-none tracking-[-0.03em]">{strings.brandTitle()}</h1>
				<p className="mt-2.5 max-w-[62ch] text-sm leading-[1.45] text-muted">
					{strings.brandSubtitle()}
				</p>
			</div>
			<div className="grid grid-cols-2 gap-2.5">
				<StatCard label={strings.registeredActions()} value={fmtInt(metrics.actions)} note={strings.actionRegistry()} />
				<StatCard label={strings.pendingApprovals()} value={fmtInt(metrics.pending)} note={strings.needsAdminDecision()} />
				<StatCard label={strings.recentLogs()} value={fmtInt(metrics.logs)} note={strings.auditVisibility()} />
				<StatCard label={strings.backups()} value={fmtInt(metrics.backups)} note={strings.recoveryCheckpoints()} />
			</div>
		</header>
	);
}
