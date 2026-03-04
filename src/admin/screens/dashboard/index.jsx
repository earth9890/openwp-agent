import Header from '../../components/header';
import Panel from '../../components/panel';
import { Badge } from '../../components/ui';
import { strings } from './constants';
import RecommendedPlugins from './recommended-plugins';
import QuickAccess from './quick-access';
import { __ } from '@wordpress/i18n';

export default function DashboardTab( {
	metrics,
	provider,
	model,
	bootstrap,
} ) {
	return (
		<div className="grid gap-3">
			<Header metrics={ metrics } />

			<div className="grid gap-3 lg:grid-cols-2">
				<Panel
					title={ __( 'Extend Your Website', 'openwp' ) }
					subtitle={ __(
						'Boost your site with top BSF plugins, built to work flawlessly with the OpenWP AI Agent.',
						'openwp'
					) }
				>
					<RecommendedPlugins />
				</Panel>

				<div className="grid gap-3 content-start">
					<Panel
						title={ strings.overviewTitle() }
						subtitle={ strings.overviewSubtitle() }
					>
						<div className="flex flex-wrap gap-2">
							<Badge variant="neutral">
								{ strings.defaultProvider() }:{ ' ' }
								{ provider || '-' }
							</Badge>
								<Badge variant="neutral">
									{ strings.defaultModel() }:{ ' ' }
									{ model || '-' }
								</Badge>
								<Badge
								variant={
									bootstrap?.capabilities?.run_agent
										? 'success'
										: 'warning'
								}
							>
								{ strings.agentCapability() }:{ ' ' }
								{ bootstrap?.capabilities?.run_agent
									? strings.yes()
									: strings.no() }
							</Badge>
						</div>
					</Panel>

					<Panel
						title={ __( 'Quick Access', 'openwp' ) }
						subtitle={ __(
							'Helpful resources and links.',
							'openwp'
						) }
					>
						<QuickAccess />
					</Panel>
				</div>
			</div>
		</div>
	);
}
