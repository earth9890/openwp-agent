<?php
/**
 * Memory actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use OpenWP\Inc\Memory\Memory_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual memory actions exposed to the OpenWP agent.
 */
class Memory_Actions {
	/**
	 * Create or update memory.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function remember_memory( $params, ActionContext $context ) {
		if ( ! self::has_explicit_intent( $context->prompt, 'remember' ) ) {
			return new WP_Error( 'openwp_memory_explicit_required', __( 'Memory can be saved only when the user explicitly asks to remember it.', 'openwp' ) );
		}

		$type = $params['type'] ?? '';
		$key  = $params['key'] ?? '';
		$text = $params['value'] ?? '';
		$tags = isset( $params['tags'] ) && is_array( $params['tags'] ) ? $params['tags'] : [];

		$service = new Memory_Service();
		$result  = $service->remember(
			$type,
			$key,
			$text,
			$tags,
			[
				'user_id' => $context->user_id,
				'source'  => 'manual_action',
				'audit'   => false,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => ! empty( $result['created'] ) ? __( 'Memory saved.', 'openwp' ) : __( 'Memory updated.', 'openwp' ),
				'data'    => [
					'created' => ! empty( $result['created'] ),
					'item'    => $result['item'] ?? [],
				],
			]
		);
	}

	/**
	 * Delete memory.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function forget_memory( $params, ActionContext $context ) {
		if ( ! self::has_explicit_intent( $context->prompt, 'forget' ) ) {
			return new WP_Error( 'openwp_memory_explicit_required', __( 'Memory can be deleted only when the user explicitly asks to forget it.', 'openwp' ) );
		}

		$type = $params['type'] ?? '';
		$key  = $params['key'] ?? '';

		$service = new Memory_Service();
		$result  = $service->forget(
			$type,
			$key,
			[
				'user_id' => $context->user_id,
				'source'  => 'manual_action',
				'audit'   => false,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => ! empty( $result['deleted'] ) ? __( 'Memory deleted.', 'openwp' ) : __( 'Memory key not found.', 'openwp' ),
				'data'    => $result,
			]
		);
	}

	/**
	 * List memory records.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function list_memory( $params, ActionContext $context ) {
		unset( $context );

		$service = new Memory_Service();
		$list    = $service->list(
			[
				'page'     => 1,
				'per_page' => isset( $params['limit'] ) ? absint( $params['limit'] ) : 50,
				'type'     => $params['type'] ?? '',
				'search'   => $params['query'] ?? '',
			]
		);

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Memory listed.', 'openwp' ),
				'data'    => [
					'total' => absint( $list['total'] ?? 0 ),
					'items' => $list['items'] ?? [],
				],
			]
		);
	}

	/**
	 * Clear all memory records.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function clear_memory( $params, ActionContext $context ) {
		unset( $params );

		if ( ! self::has_explicit_intent( $context->prompt, 'forget' ) ) {
			return new WP_Error( 'openwp_memory_explicit_required', __( 'Memory can be cleared only when the user explicitly asks to clear it.', 'openwp' ) );
		}

		$service = new Memory_Service();
		$result  = $service->clear_all(
			[
				'user_id' => $context->user_id,
				'source'  => 'manual_action',
				'audit'   => false,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => sprintf(
					/* translators: %d: number of memory records deleted */
					__( 'All memory cleared. %d record(s) deleted.', 'openwp' ),
					absint( $result['deleted_count'] ?? 0 )
				),
				'data' => $result,
			]
		);
	}

	/**
	 * Check whether the prompt explicitly asks for memory write/delete.
	 *
	 * @param mixed  $prompt Prompt value.
	 * @param string $mode   remember|forget.
	 * @return bool
	 */
	private static function has_explicit_intent( $prompt, $mode ) {
		$prompt = strtolower( sanitize_text_field( (string) $prompt ) );
		if ( '' === $prompt ) {
			return false;
		}

		$remember_patterns = [
			'/\bremember\b/',
			'/\bstore\b/',
			'/\bsave\b.+\b(memory|preference|rule)\b/',
			'/\bkeep this in mind\b/',
			'/\bmemorize\b/',
			'/\bnote this\b/',
		];

		$forget_patterns = [
			'/\bforget\b/',
			'/\bremove\b.+\b(memory|preference|rule)\b/',
			'/\bdelete\b.+\b(memory|preference|rule)\b/',
			'/\bclear\b.+\b(memory|preference|rule)\b/',
		];

		$patterns = 'forget' === $mode ? $forget_patterns : $remember_patterns;
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt ) ) {
				return true;
			}
		}

		return false;
	}
}
