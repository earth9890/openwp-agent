<?php
/**
 * Taxonomy actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomy term handlers.
 */
class Taxonomy_Actions {
	/**
	 * List terms.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function list_terms( $params, ActionContext $context ) {
		$taxonomy = sanitize_key( (string) ( $params['taxonomy'] ?? 'category' ) );
		$terms    = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => isset( $params['per_page'] ) ? max( 1, min( 100, absint( $params['per_page'] ) ) ) : 20,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$items = array_map(
			static function ( $term ) {
				return [
					'term_id' => (int) $term->term_id,
					'name'    => (string) $term->name,
					'slug'    => (string) $term->slug,
					'count'   => (int) $term->count,
				];
			},
			$terms
		);

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Terms listed.', 'openwp' ),
				'data'    => [ 'items' => $items ],
			]
		);
	}

	/**
	 * Create term.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function create_term( $params, ActionContext $context ) {
		$taxonomy = sanitize_key( (string) ( $params['taxonomy'] ?? 'category' ) );
		$name     = sanitize_text_field( (string) ( $params['name'] ?? '' ) );
		$args     = [
			'slug'        => isset( $params['slug'] ) ? sanitize_title( (string) $params['slug'] ) : '',
			'description' => sanitize_textarea_field( (string) ( $params['description'] ?? '' ) ),
		];

		$capability_check = self::assert_taxonomy_capability(
			$taxonomy,
			'manage_terms',
			'openwp_term_create_denied',
			__( 'You do not have permission to create terms in this taxonomy.', 'openwp' )
		);
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		$result = wp_insert_term( $name, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = absint( $result['term_id'] ?? 0 );

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Term created.', 'openwp' ),
				'data'    => [ 'term_id' => $term_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_delete_term',
					'params'          => [
						'term_id'  => $term_id,
						'taxonomy' => $taxonomy,
					],
				],
			]
		);
	}

	/**
	 * Update term.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function update_term( $params, ActionContext $context ) {
		$term_id  = absint( $params['term_id'] ?? 0 );
		$taxonomy = sanitize_key( (string) ( $params['taxonomy'] ?? 'category' ) );

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'openwp_term_not_found', __( 'Term not found.', 'openwp' ) );
		}

		$capability_check = self::assert_taxonomy_capability(
			$taxonomy,
			'edit_terms',
			'openwp_term_edit_denied',
			__( 'You do not have permission to edit terms in this taxonomy.', 'openwp' )
		);
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		$before = [
			'term_id'      => $term_id,
			'taxonomy'     => $taxonomy,
			'name'         => $term->name,
			'slug'         => $term->slug,
			'description'  => $term->description,
		];

		$update = [];
		if ( array_key_exists( 'name', $params ) ) {
			$update['name'] = sanitize_text_field( (string) $params['name'] );
		}
		if ( array_key_exists( 'slug', $params ) ) {
			$update['slug'] = sanitize_title( (string) $params['slug'] );
		}
		if ( array_key_exists( 'description', $params ) ) {
			$update['description'] = sanitize_textarea_field( (string) $params['description'] );
		}

		$result = wp_update_term( $term_id, $taxonomy, $update );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Term updated.', 'openwp' ),
				'data'    => [ 'term_id' => $term_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_update_term',
					'params'          => $before,
				],
			]
		);
	}

	/**
	 * Delete term.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function delete_term( $params, ActionContext $context ) {
		$term_id  = absint( $params['term_id'] ?? 0 );
		$taxonomy = sanitize_key( (string) ( $params['taxonomy'] ?? 'category' ) );
		$term     = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'openwp_term_not_found', __( 'Term not found.', 'openwp' ) );
		}

		$capability_check = self::assert_taxonomy_capability(
			$taxonomy,
			'delete_terms',
			'openwp_term_delete_denied',
			__( 'You do not have permission to delete terms in this taxonomy.', 'openwp' )
		);
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		$before = [
			'taxonomy'    => $taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
		];

		$result = wp_delete_term( $term_id, $taxonomy );
		if ( ! $result ) {
			return new WP_Error( 'openwp_term_delete_failed', __( 'Term deletion failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Term deleted.', 'openwp' ),
				'data'    => [ 'term_id' => $term_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_create_term',
					'params'          => $before,
				],
			]
		);
	}

	/**
	 * Check taxonomy-level capability.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $capability_key Taxonomy capability property.
	 * @param string $error_code Error code.
	 * @param string $error_message Error message.
	 * @return true|WP_Error
	 */
	private static function assert_taxonomy_capability( $taxonomy, $capability_key, $error_code, $error_message ) {
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object || ! isset( $taxonomy_object->cap ) ) {
			return new WP_Error( 'openwp_taxonomy_invalid', __( 'Invalid taxonomy.', 'openwp' ) );
		}

		$capability = '';
		if ( isset( $taxonomy_object->cap->$capability_key ) && is_string( $taxonomy_object->cap->$capability_key ) ) {
			$capability = $taxonomy_object->cap->$capability_key;
		}

		if ( '' === $capability || ! current_user_can( $capability ) ) {
			return new WP_Error( $error_code, $error_message );
		}

		return true;
	}
}
