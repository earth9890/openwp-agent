/**
 * Recommended Plugins section for the OpenWP dashboard.
 *
 * @package OpenWP
 */

import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Loader2, Check, Download } from 'lucide-react';
import { Button, Badge, Alert, Card } from '../../components/ui';
import { pluginAddons, recommendedSlugs } from '../../utils/plugin-addons';
import {
	fetchInstalledPlugins,
	installAndActivatePlugin,
	activatePlugin,
} from '../../utils/plugin-utils';

const MAX_CARDS = 4;

export default function RecommendedPlugins() {
	const [pluginsData, setPluginsData] = useState({
		installed: [],
		active: [],
	});
	const [installingPlugins, setInstallingPlugins] = useState([]);
	const [activatingPlugins, setActivatingPlugins] = useState([]);
	const [notice, setNotice] = useState(null);

	const loadPlugins = useCallback(async () => {
		try {
			const data = await fetchInstalledPlugins();
			setPluginsData(data);
		} catch {
			// Silently fail - buttons will show Install & Activate.
		}
	}, []);

	useEffect(() => {
		loadPlugins();
	}, []);

	const showNotice = useCallback((message, type) => {
		setNotice({ message, type });
		setTimeout(() => setNotice(null), 4000);
	}, []);

	const canInstall =
		window.openwpAdmin?.pluginInstallationPermission === '1';

	const cards = useMemo(() => {
		const all = recommendedSlugs
			.map((slug) => pluginAddons.find((p) => p.slug === slug))
			.filter(Boolean);

		// Sort: active first, then installed, then not installed.
		const sorted = [...all].sort((a, b) => {
			const aActive = pluginsData.active.includes(a.slug) ? 0 : 1;
			const bActive = pluginsData.active.includes(b.slug) ? 0 : 1;
			if (aActive !== bActive) {
				return aActive - bActive;
			}
			const aInstalled = pluginsData.installed.includes(a.slug)
				? 0
				: 1;
			const bInstalled = pluginsData.installed.includes(b.slug)
				? 0
				: 1;
			return aInstalled - bInstalled;
		});

		return sorted.slice(0, MAX_CARDS);
	}, [pluginsData]);

	const renderActionButton = (plugin) => {
		const isInstalling = installingPlugins.includes(plugin.slug);
		const isActivating = activatingPlugins.includes(plugin.slug);
		const isInstalled = pluginsData.installed.includes(plugin.slug);
		const isActive = pluginsData.active.includes(plugin.slug);
		const isBusy = isInstalling || isActivating;

		if (isActive) {
			return (
				<Badge variant="success" className="gap-1">
					<Check className="h-3 w-3" />
					{__('Activated', 'openwp')}
				</Badge>
			);
		}

		if (isInstalled) {
			return (
				<Button
					variant="outline"
					size="xs"
					disabled={isBusy || !canInstall}
					onClick={() =>
						activatePlugin(
							plugin,
							pluginsData,
							installingPlugins,
							activatingPlugins,
							setActivatingPlugins,
							loadPlugins,
							showNotice
						)
					}
				>
					{isBusy && (
						<Loader2 className="h-3.5 w-3.5 animate-spin" />
					)}
					{__('Activate', 'openwp')}
				</Button>
			);
		}

		return (
			<Button
				variant="outline"
				size="xs"
				disabled={isBusy || !canInstall}
				onClick={() =>
					installAndActivatePlugin(
						plugin,
						pluginsData,
						installingPlugins,
						activatingPlugins,
						setInstallingPlugins,
						setActivatingPlugins,
						loadPlugins,
						showNotice
					)
				}
			>
				{isBusy ? (
					<Loader2 className="h-3.5 w-3.5 animate-spin" />
				) : (
					<Download className="h-3.5 w-3.5" />
				)}
				{__('Install & Activate', 'openwp')}
			</Button>
		);
	};

	return (
		<div className="grid gap-3">
			{notice && (
				<Alert
					variant={
						notice.type === 'error' ? 'destructive' : 'default'
					}
				>
					{notice.message}
				</Alert>
			)}

			<div className="grid grid-cols-1 gap-3 sm:grid-cols-2 -mt-5">
				{cards.map((plugin) => (
					<Card
						key={plugin.slug}
						className="flex flex-col gap-3 border-[0.5px] border-solid p-4 shadow-none transition-shadow hover:shadow-sm"
					>
						<div className="flex items-center gap-2.5">
							<div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-bg-soft text-primary">
								{plugin.icon ? (
									<plugin.icon className="h-5 w-5" />
								) : (
									<Download className="h-4.5 w-4.5" />
								)}
							</div>
							<div className="min-w-0">
								<h4 className="m-0 truncate text-sm font-semibold text-ink">
									{plugin.title}
								</h4>
								<Badge
									variant="neutral"
									className="mt-0.5 px-1.5 py-0 text-[10px]"
								>
									{__('Free', 'openwp')}
								</Badge>
							</div>
						</div>

						<p className="m-0 flex-1 text-xs leading-relaxed text-muted">
							{plugin.description}
						</p>

						<div className="mt-auto">
							{renderActionButton(plugin)}
						</div>
					</Card>
				))}
			</div>
		</div>
	);
}
