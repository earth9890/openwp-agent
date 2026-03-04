import {
	Brain,
	Database,
	History,
	LayoutDashboard,
	Settings2,
	ShieldCheck,
	Sparkles,
	Wrench,
} from 'lucide-react';
import { getTabs } from './constants';
import { Badge, Button, cn } from '../ui';

const TAB_ICONS = {
	dashboard: LayoutDashboard,
	console: Sparkles,
	approvals: ShieldCheck,
	settings: Settings2,
	actions: Wrench,
	memory: Brain,
	logs: History,
	backups: Database,
};

function CountBadge( { count, isActive } ) {
	if ( count === null || Number.isNaN( Number( count ) ) ) {
		return null;
	}

	return (
		<Badge
			variant={ isActive ? 'secondary' : 'outline' }
			className={ cn(
				'ml-auto rounded-full px-1.5 py-0 text-[10px] font-semibold',
				isActive
					? '!border-[#ffffff52] !bg-[#ffffff2e] !text-white'
					: ''
			) }
		>
			{ count }
		</Badge>
	);
}

export default function SideNav( { tab, metrics, onChange } ) {
	const tabs = getTabs( metrics );

	return (
		<aside className="sticky top-[16px] h-fit overflow-hidden rounded-[16px] border border-line bg-white/92 p-2.5 shadow-sm">
			<div className="mb-2 px-1.5 text-[10px] font-semibold uppercase tracking-[0.09em] text-muted">
				Navigation
			</div>
			<div className="grid gap-1.5">
				{ tabs.map( ( item ) => {
					const isActive = tab === item.key;
					const Icon = TAB_ICONS[ item.key ] || Sparkles;

					return (
						<Button
							key={ item.key }
							variant={ isActive ? 'default' : 'ghost' }
							className={ cn(
								'!h-auto !w-full !min-w-0 !justify-start !overflow-hidden !rounded-[11px] !border !px-2.5 !py-2 text-left',
								isActive
									? '!border-transparent !bg-gradient-to-r !from-primary !to-[#3576ff] !text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.14)]'
									: '!border-transparent !bg-transparent !text-ink hover:!border-line hover:!bg-bg-soft'
							) }
							onClick={ () => onChange( item.key ) }
						>
							<span
								className={ cn(
									'mr-2.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[9px] border border-solid',
									isActive
										? 'border-[#ffffff52] bg-[#ffffff30]'
										: 'border-line bg-surface'
								) }
							>
								<Icon
									className={ cn(
										'h-4 w-4',
										isActive
											? 'text-white'
											: 'text-[#314a77]'
									) }
								/>
							</span>
							<span className="min-w-0">
								<span className="flex min-w-0 items-center text-[12.5px] font-semibold leading-tight">
									<span className="truncate">
										{ item.label }
									</span>
									<CountBadge
										count={ item.count }
										isActive={ isActive }
									/>
								</span>
							</span>
						</Button>
					);
				} ) }
			</div>
		</aside>
	);
}
