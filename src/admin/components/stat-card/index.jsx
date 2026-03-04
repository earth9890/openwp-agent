import { Card, CardContent } from '../ui';

export default function StatCard( { label, value, note } ) {
	return (
		<Card className="min-h-[72px] rounded-2xl border border-solid border-line bg-[linear-gradient(140deg,#ffffff_0%,#f7fbff_100%)] px-3 py-2.5 shadow-none">
			<CardContent className="!p-0">
				<div className="text-[11px] font-semibold uppercase tracking-[0.08em] text-muted">{ label }</div>
				<div className="mt-1 text-2xl font-bold leading-none text-ink">{ value || '0' }</div>
				{ note && <div className="mt-1.5 text-xs text-muted">{ note }</div> }
			</CardContent>
		</Card>
	);
}
