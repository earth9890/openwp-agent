/**
 * Plugin install/activate utilities.
 *
 * @package OpenWP
 */

import { __, sprintf } from '@wordpress/i18n';
import { request } from '../../shared/api';

/**
 * Fetch installed plugins data from REST API.
 *
 * @return {Promise<Object>} Installed and active plugin slugs.
 */
export async function fetchInstalledPlugins() {
	const response = await request( '/openwp/v1/installed-plugins' );
	return response?.plugins || { installed: [], active: [] };
}

/**
 * Perform a plugin/theme operation (install or activate).
 *
 * @param {Object}   plugin          Plugin object.
 * @param {string}   operation       'install' or 'activate'.
 * @param {Function} setLoadingState State setter for loading slugs.
 * @return {Promise} Resolves on success.
 */
export function handlePluginOperation( plugin, operation, setLoadingState ) {
	return new Promise( ( resolve, reject ) => {
		const isInstall = operation === 'install';
		const itemType = plugin.type === 'theme' ? 'theme' : 'plugin';

		if ( ! window.wp?.updates ) {
			reject( new Error( __( 'WordPress updates API not available.', 'openwp' ) ) );
			return;
		}

		setLoadingState( ( prev ) => [ ...prev, plugin.slug ] );

		const commonOptions = {
			success: () => resolve(),
			error: ( err ) => {
				reject( new Error( err?.errorMessage || __( 'Operation failed.', 'openwp' ) ) );
			},
		};

		if ( isInstall ) {
			const installFn = wp.updates[
				`install${ itemType.charAt( 0 ).toUpperCase() + itemType.slice( 1 ) }`
			];
			installFn( { slug: plugin.slug, ...commonOptions } );
		} else {
			const actionName = itemType === 'plugin'
				? 'openwp-activate_plugin'
				: 'openwp-activate_theme';
			wp.ajax.send( actionName, {
				data: {
					slug: plugin.init,
					_ajax_nonce: window.openwpAdmin?._ajax_nonce,
				},
				...commonOptions,
			} );
		}
	} );
}

/**
 * Install and then activate a plugin/theme.
 *
 * @param {Object}   plugin              Plugin object.
 * @param {Object}   pluginsData         Installed/active data.
 * @param {Array}    installingPlugins   Currently installing slugs.
 * @param {Array}    activatingPlugins   Currently activating slugs.
 * @param {Function} setInstalling       Setter for installing state.
 * @param {Function} setActivating       Setter for activating state.
 * @param {Function} refreshPlugins      Callback to refresh plugins data.
 * @param {Function} showNotice          Callback to show notice message.
 */
export async function installAndActivatePlugin(
	plugin,
	pluginsData,
	installingPlugins,
	activatingPlugins,
	setInstalling,
	setActivating,
	refreshPlugins,
	showNotice
) {
	const typeStr = plugin.type === 'theme'
		? __( 'Theme', 'openwp' )
		: __( 'Plugin', 'openwp' );

	if ( pluginsData.installed.includes( plugin.slug ) ) {
		showNotice( sprintf( __( '%s is already installed.', 'openwp' ), typeStr ), 'info' );
		return;
	}

	if ( installingPlugins.length > 0 || activatingPlugins.length > 0 ) {
		showNotice( sprintf( __( 'Another %s operation is in progress.', 'openwp' ), typeStr ), 'info' );
		return;
	}

	try {
		await handlePluginOperation( plugin, 'install', setInstalling );
		showNotice( sprintf( __( '%s installed successfully.', 'openwp' ), typeStr ), 'success' );
		refreshPlugins();

		await handlePluginOperation( plugin, 'activate', setActivating );
		showNotice( sprintf( __( '%s activated successfully.', 'openwp' ), typeStr ), 'success' );
		refreshPlugins();
	} catch ( error ) {
		showNotice( sprintf( __( 'Failed to install %s.', 'openwp' ), typeStr ), 'error' );
	} finally {
		setInstalling( ( prev ) => prev.filter( ( s ) => s !== plugin.slug ) );
		setActivating( ( prev ) => prev.filter( ( s ) => s !== plugin.slug ) );
	}
}

/**
 * Activate an already-installed plugin/theme.
 *
 * @param {Object}   plugin            Plugin object.
 * @param {Object}   pluginsData       Installed/active data.
 * @param {Array}    installingPlugins Currently installing slugs.
 * @param {Array}    activatingPlugins Currently activating slugs.
 * @param {Function} setActivating     Setter for activating state.
 * @param {Function} refreshPlugins    Callback to refresh plugins data.
 * @param {Function} showNotice        Callback to show notice message.
 */
export async function activatePlugin(
	plugin,
	pluginsData,
	installingPlugins,
	activatingPlugins,
	setActivating,
	refreshPlugins,
	showNotice
) {
	const typeStr = plugin.type === 'theme'
		? __( 'Theme', 'openwp' )
		: __( 'Plugin', 'openwp' );

	if ( pluginsData.active.includes( plugin.slug ) ) {
		showNotice( sprintf( __( '%s is already activated.', 'openwp' ), typeStr ), 'info' );
		return;
	}

	if ( installingPlugins.length > 0 || activatingPlugins.length > 0 ) {
		showNotice( sprintf( __( 'Another %s operation is in progress.', 'openwp' ), typeStr ), 'info' );
		return;
	}

	try {
		await handlePluginOperation( plugin, 'activate', setActivating );
		showNotice( sprintf( __( '%s activated successfully.', 'openwp' ), typeStr ), 'success' );
		refreshPlugins();
	} catch ( error ) {
		showNotice( sprintf( __( 'Failed to activate %s.', 'openwp' ), typeStr ), 'error' );
	} finally {
		setActivating( ( prev ) => prev.filter( ( s ) => s !== plugin.slug ) );
	}
}
