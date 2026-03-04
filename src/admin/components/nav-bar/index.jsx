import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Brain,
	Database,
	History,
	LayoutDashboard,
	Menu,
	Settings2,
	ShieldCheck,
	Sparkles,
	Wrench,
	X,
} from 'lucide-react';
import openwpLogo from '../../../assets/openwp.png';
import { getTabs } from '../side-nav/constants';
import {
	Badge,
	Button,
	cn,
} from '../ui';

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

function CountBadge( { count } ) {
	if ( count === null || Number.isNaN( Number( count ) ) ) {
		return null;
	}

	return (
		<Badge
			variant="outline"
			className="ml-0.5 rounded-full px-1.5 py-0 text-[10px] font-semibold"
		>
			{ count }
		</Badge>
	);
}

export default function NavBar( { tab, metrics, onChange } ) {
	const [ mobileOpen, setMobileOpen ] = useState( false );
	const tabs = getTabs( metrics );

	return (
		<nav className="relative mb-4 border-b border-line/80 bg-[linear-gradient(130deg,#fcfeff_0%,#f1f6fb_52%,#ebf4f2_100%)] shadow-sm -mt-[20px] -ml-[20px] -mr-[20px] px-[20px]">
			<div className="flex items-center gap-4 px-4 py-2.5">
				{ /* Brand */ }
				<button
					type="button"
					className="flex shrink-0 items-center gap-2 cursor-pointer !border-0 !shadow-none !bg-transparent !p-0 outline-none focus:outline-none -ml-5"
					onClick={ () => onChange( 'dashboard' ) }
				>
					<img
						src={ openwpLogo }
						alt={ __( 'OpenWP', 'openwp' ) }
						className="h-10 w-10"
					/>
					<span className="text-sm font-bold tracking-[-0.02em] text-ink">
						{ __( 'OpenWP', 'openwp' ) }
					</span>
				</button>

				{ /* Desktop tabs */ }
				<nav className="hidden h-full flex-1 items-center gap-1 min-[900px]:flex">
					{ tabs.map( ( item ) => {
						const isActive = tab === item.key;
						const Icon = TAB_ICONS[ item.key ] || Sparkles;

						return (
							<button
								key={ item.key }
								type="button"
								className={ cn(
									'relative inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-[12.5px] font-medium transition-colors duration-200 !border-0 !shadow-none !bg-transparent cursor-pointer outline-none focus:outline-none',
									isActive
										? 'bg-[#e9f4f3] text-primary after:content-[""] after:absolute after:bottom-[-14px] after:inset-x-2 after:h-[2px] after:bg-primary after:rounded-full'
										: 'text-muted hover:bg-[#edf3fb] hover:text-ink'
								) }
								onClick={ () => onChange( item.key ) }
							>
								<Icon className="h-3.5 w-3.5" />
								{ item.label }
								<CountBadge count={ item.count } />
							</button>
						);
					} ) }
				</nav>

				{ /* Mobile hamburger */ }
				<div className="ml-auto min-[900px]:hidden">
					<Button
						variant="ghost"
						size="sm"
						className="!h-8 !w-8 !p-0"
						onClick={ () => setMobileOpen( ( v ) => ! v ) }
					>
						{ mobileOpen ? (
							<X className="h-4 w-4" />
						) : (
							<Menu className="h-4 w-4" />
						) }
					</Button>
				</div>
			</div>

			{ /* Mobile menu */ }
			{ mobileOpen && (
				<div className="border-t border-line/80 px-2 py-2 min-[900px]:hidden">
					<div className="grid gap-1">
						{ tabs.map( ( item ) => {
							const isActive = tab === item.key;
							const Icon = TAB_ICONS[ item.key ] || Sparkles;

							return (
								<button
									key={ item.key }
									type="button"
									className={ cn(
										'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[12.5px] font-medium transition-colors',
										isActive
											? 'bg-bg-soft text-primary'
											: 'text-ink hover:bg-bg-soft'
									) }
									onClick={ () => {
										onChange( item.key );
										setMobileOpen( false );
									} }
								>
									<Icon className="h-3.5 w-3.5" />
									{ item.label }
									<CountBadge count={ item.count } />
								</button>
							);
						} ) }
					</div>
				</div>
			) }
		</nav>
	);
}
