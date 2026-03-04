<?php
/**
 * Theme actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme lifecycle handlers.
 */
class Theme_Actions {
	/**
	 * List themes.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function list_themes( $params, ActionContext $context ) {
		$themes = wp_get_themes();
		$items  = [];

		foreach ( $themes as $slug => $theme ) {
			$items[] = [
				'slug'       => (string) $slug,
				'name'       => $theme->get( 'Name' ),
				'version'    => $theme->get( 'Version' ),
				'author'     => $theme->get( 'Author' ),
				'is_active'  => wp_get_theme()->get_stylesheet() === $slug,
			];
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Themes listed.', 'openwp' ),
				'data'    => [ 'items' => $items ],
			]
		);
	}

	/**
	 * Install theme from wordpress.org slug.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function install_theme( $params, ActionContext $context ) {
		$slug = sanitize_key( (string) ( $params['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return new WP_Error( 'openwp_theme_slug_required', __( 'Theme slug is required.', 'openwp' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$info = themes_api(
			'theme_information',
			[
				'slug'   => $slug,
				'fields' => [ 'sections' => false ],
			]
		);

		if ( is_wp_error( $info ) || empty( $info->download_link ) ) {
			return new WP_Error( 'openwp_theme_info_failed', __( 'Failed to fetch theme information.', 'openwp' ) );
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->install( $info->download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'openwp_theme_install_failed', __( 'Theme installation failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Theme installed.', 'openwp' ),
				'data'    => [ 'slug' => $slug ],
			]
		);
	}

	/**
	 * Switch active theme.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function switch_theme( $params, ActionContext $context ) {
		$stylesheet = self::validate_theme_stylesheet( (string) ( $params['stylesheet'] ?? '' ) );
		if ( is_wp_error( $stylesheet ) ) {
			return $stylesheet;
		}

		$before = wp_get_theme()->get_stylesheet();
		switch_theme( $stylesheet );

		if ( wp_get_theme()->get_stylesheet() !== $stylesheet ) {
			return new WP_Error( 'openwp_theme_switch_failed', __( 'Theme switch failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Theme switched.', 'openwp' ),
				'data'    => [ 'stylesheet' => $stylesheet ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_switch_theme',
					'params'          => [ 'stylesheet' => $before ],
				],
			]
		);
	}

	/**
	 * Update theme.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function update_theme( $params, ActionContext $context ) {
		$stylesheet = self::validate_theme_stylesheet( (string) ( $params['stylesheet'] ?? '' ) );
		if ( is_wp_error( $stylesheet ) ) {
			return $stylesheet;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( $stylesheet );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new WP_Error( 'openwp_theme_update_failed', __( 'Theme update failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Theme updated.', 'openwp' ),
				'data'    => [ 'stylesheet' => $stylesheet ],
			]
		);
	}

	/**
	 * Delete theme.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function delete_theme( $params, ActionContext $context ) {
		$stylesheet = self::validate_theme_stylesheet( (string) ( $params['stylesheet'] ?? '' ) );
		if ( is_wp_error( $stylesheet ) ) {
			return $stylesheet;
		}

		$result = delete_theme( $stylesheet );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new WP_Error( 'openwp_theme_delete_failed', __( 'Theme deletion failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Theme deleted.', 'openwp' ),
				'data'    => [ 'stylesheet' => $stylesheet ],
			]
		);
	}

	/**
	 * Validate theme stylesheet slug exists.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return string|WP_Error
	 */
	private static function validate_theme_stylesheet( $stylesheet ) {
		$stylesheet = sanitize_key( $stylesheet );
		if ( '' === $stylesheet ) {
			return new WP_Error( 'openwp_theme_stylesheet_required', __( 'stylesheet is required.', 'openwp' ) );
		}

		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'openwp_theme_not_found', __( 'Theme stylesheet was not found on this site.', 'openwp' ) );
		}

		return $stylesheet;
	}
}
