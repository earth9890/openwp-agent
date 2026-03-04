import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from '../ui';

export default function Panel( { title, subtitle, actions, children } ) {
	return (
		<Card className="overflow-hidden rounded-2xl border border-solid border-line bg-white/90 shadow-openwp">
			<CardHeader className="flex flex-row items-center justify-between gap-3.5 border-b border-line bg-[linear-gradient(115deg,#f8fcff,#f1f8ff_56%,#edf8f6)] px-4 py-3.5">
				<div>
					<CardTitle className="m-0 text-[19px] leading-tight tracking-[-0.01em]">{ title }</CardTitle>
					{ subtitle && <CardDescription className="mt-1.5 text-xs leading-[1.5] text-muted">{ subtitle }</CardDescription> }
				</div>
				{ actions && <div className="flex items-center gap-2">{ actions }</div> }
			</CardHeader>
			<CardContent className="px-4 py-3.5">{ children }</CardContent>
		</Card>
	);
}
