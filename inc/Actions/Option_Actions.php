<?php
/**
 * Option actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option CRUD handlers.
 */
class Option_Actions {
	/**
	 * Get option.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function get_option( $params, ActionContext $context ) {
		$name = sanitize_key( (string) ( $params['option_name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'openwp_option_name_required', __( 'option_name is required.', 'openwp' ) );
		}

		$value = get_option( $name, null );

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Option read.', 'openwp' ),
				'data'    => [
					'option_name'  => $name,
					'option_value' => $value,
				],
			]
		);
	}

	/**
	 * Update option.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function update_option( $params, ActionContext $context ) {
		$name = sanitize_key( (string) ( $params['option_name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'openwp_option_name_required', __( 'option_name is required.', 'openwp' ) );
		}

		$before = get_option( $name, null );
		$value  = $params['option_value'] ?? '';
		$stored = update_option( $name, $value );

		return new ActionResult(
			[
				'success' => true,
				'message' => $stored ? __( 'Option updated.', 'openwp' ) : __( 'Option unchanged.', 'openwp' ),
				'data'    => [
					'option_name' => $name,
					'updated'     => (bool) $stored,
				],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_update_option',
					'params'          => [
						'option_name'  => $name,
						'option_value' => $before,
					],
				],
			]
		);
	}

	/**
	 * Delete option.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function delete_option( $params, ActionContext $context ) {
		$name = sanitize_key( (string) ( $params['option_name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'openwp_option_name_required', __( 'option_name is required.', 'openwp' ) );
		}

		$before  = get_option( $name, null );
		$deleted = delete_option( $name );

		return new ActionResult(
			[
				'success' => true,
				'message' => $deleted ? __( 'Option deleted.', 'openwp' ) : __( 'Option did not exist.', 'openwp' ),
				'data'    => [
					'option_name' => $name,
					'deleted'     => (bool) $deleted,
				],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_update_option',
					'params'          => [
						'option_name'  => $name,
						'option_value' => $before,
					],
				],
			]
		);
	}
}
