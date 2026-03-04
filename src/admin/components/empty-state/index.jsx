import { strings } from './constants';
import { Card, CardContent } from '../ui';

export default function EmptyState({ title, message }) {
	return (
		<Card className="rounded-2xl border border-dashed border-line bg-[linear-gradient(145deg,#f7fbff_0%,#f2f8ff_56%,#eef3ff_100%)] shadow-none">
			<CardContent className="!p-6 text-center">
				<div className="mb-1.5 text-[15px] font-bold">{title || strings.nothingHereYet()}</div>
				{message && <div className="text-[13px] text-muted">{message}</div>}
			</CardContent>
		</Card>
	);
}
