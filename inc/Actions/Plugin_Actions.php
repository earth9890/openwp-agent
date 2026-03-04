<?php
/**
 * Plugin actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin lifecycle handlers.
 */
class Plugin_Actions {
	/**
	 * List installed plugins.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function list_plugins( $params, ActionContext $context ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = get_plugins();
		$items   = [];
		foreach ( $plugins as $file => $plugin ) {
			$items[] = [
				'file'        => $file,
				'name'        => (string) ( $plugin['Name'] ?? '' ),
				'version'     => (string) ( $plugin['Version'] ?? '' ),
				'active'      => is_plugin_active( $file ),
				'network'     => is_plugin_active_for_network( $file ),
				'author'      => (string) ( $plugin['AuthorName'] ?? '' ),
			];
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Plugins listed.', 'openwp' ),
				'data'    => [ 'items' => $items ],
			]
		);
	}

	/**
	 * Install plugin from wordpress.org slug.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function install_plugin( $params, ActionContext $context ) {
		$slug = sanitize_key( (string) ( $params['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return new WP_Error( 'openwp_plugin_slug_required', __( 'Plugin slug is required.', 'openwp' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$info = plugins_api(
			'plugin_information',
			[
				'slug'   => $slug,
				'fields' => [ 'sections' => false ],
			]
		);

		if ( is_wp_error( $info ) || empty( $info->download_link ) ) {
			return new WP_Error( 'openwp_plugin_info_failed', __( 'Failed to fetch plugin information.', 'openwp' ) );
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $info->download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new WP_Error( 'openwp_plugin_install_failed', __( 'Plugin installation failed.', 'openwp' ) );
		}

		$plugin_file = $upgrader->plugin_info();

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Plugin installed.', 'openwp' ),
				'data'    => [
					'slug'        => $slug,
					'plugin_file' => $plugin_file,
				],
			]
		);
	}

	/**
	 * Activate plugin.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function activate_plugin( $params, ActionContext $context ) {
		$plugin = self::validate_plugin_file( (string) ( $params['plugin'] ?? '' ) );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$result = activate_plugin( $plugin, '', false, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Plugin activated.', 'openwp' ),
				'data'    => [ 'plugin' => $plugin ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_deactivate_plugin',
					'params'          => [ 'plugin' => $plugin ],
				],
			]
		);
	}

	/**
	 * Deactivate plugin.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function deactivate_plugin( $params, ActionContext $context ) {
		$plugin = self::validate_plugin_file( (string) ( $params['plugin'] ?? '' ) );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		deactivate_plugins( $plugin, false, false );

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Plugin deactivated.', 'openwp' ),
				'data'    => [ 'plugin' => $plugin ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_activate_plugin',
					'params'          => [ 'plugin' => $plugin ],
				],
			]
		);
	}

	/**
	 * Update plugin.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function update_plugin( $params, ActionContext $context ) {
		$plugin = self::validate_plugin_file( (string) ( $params['plugin'] ?? '' ) );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $plugin );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new WP_Error( 'openwp_plugin_update_failed', __( 'Plugin update failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Plugin updated.', 'openwp' ),
				'data'    => [ 'plugin' => $plugin ],
			]
		);
	}

	/**
	 * Delete plugin.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function delete_plugin( $params, ActionContext $context ) {
		$plugin = self::validate_plugin_file( (string) ( $params['plugin'] ?? '' ) );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$deleted = delete_plugins( [ $plugin ] );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		if ( ! $deleted ) {
			return new WP_Error( 'openwp_plugin_delete_failed', __( 'Plugin deletion failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Plugin deleted.', 'openwp' ),
				'data'    => [ 'plugin' => $plugin ],
			]
		);
	}

	/**
	 * Validate plugin file exists.
	 *
	 * @param string $plugin Plugin file path.
	 * @return string|WP_Error
	 */
	private static function validate_plugin_file( $plugin ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin = sanitize_text_field( $plugin );
		if ( '' === $plugin ) {
			return new WP_Error( 'openwp_plugin_required', __( 'plugin is required.', 'openwp' ) );
		}

		$plugins = get_plugins();
		if ( ! is_array( $plugins ) || ! isset( $plugins[ $plugin ] ) ) {
			return new WP_Error( 'openwp_plugin_not_found', __( 'Plugin file was not found on this site.', 'openwp' ) );
		}

		return $plugin;
	}
}
