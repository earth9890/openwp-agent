/**
 * Quick Access section for the OpenWP dashboard.
 *
 * @package OpenWP
 */

import { __ } from '@wordpress/i18n';
import { HelpCircle, MessagesSquare, Star, ExternalLink } from 'lucide-react';

const items = [
	{
		id: 'help',
		icon: <HelpCircle className="h-4 w-4" />,
		label: __( 'Help Center', 'openwp' ),
		link: 'https://developer.wordpress.org/',
	},
	{
		id: 'community',
		icon: <MessagesSquare className="h-4 w-4" />,
		label: __( 'Join the Community', 'openwp' ),
		link: 'https://www.facebook.com/groups/surecrafted',
	},
	{
		id: 'rate',
		icon: <Star className="h-4 w-4" />,
		label: __( 'Rate Us', 'openwp' ),
		link: 'https://wordpress.org/support/plugin/openwp/reviews/#new-post',
	},
];

export default function QuickAccess() {
	return (
		<div className="grid gap-2">
			{ items.map( ( item ) => (
				<a
					key={ item.id }
					href={ item.link }
					target="_blank"
					rel="noreferrer"
className="group flex items-center gap-3 rounded-xl border-[0.5px] border-solid border-line bg-surface px-4 py-3 shadow-sm transition-all duration-200 hover:shadow-sm hover:bg-surface-2 no-underline"
				>
					<span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-bg-soft text-primary transition-colors group-hover:bg-primary/10">
						{ item.icon }
					</span>
					<span className="flex-1 text-sm font-medium text-ink">
						{ item.label }
					</span>
					<ExternalLink className="h-3.5 w-3.5 text-muted opacity-0 transition-opacity group-hover:opacity-100" />
				</a>
			) ) }
		</div>
	);
}
