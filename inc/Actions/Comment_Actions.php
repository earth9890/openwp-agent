<?php
/**
 * Comment actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comment management handlers.
 */
class Comment_Actions {
	/**
	 * List comments.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function list_comments( $params, ActionContext $context ) {
		$comments = get_comments(
			[
				'status' => isset( $params['status'] ) ? sanitize_key( (string) $params['status'] ) : 'all',
				'number' => isset( $params['per_page'] ) ? max( 1, min( 100, absint( $params['per_page'] ) ) ) : 20,
			]
		);

		$items = [];
		foreach ( $comments as $comment ) {
			$items[] = [
				'comment_ID'      => (int) $comment->comment_ID,
				'comment_post_ID' => (int) $comment->comment_post_ID,
				'comment_author'  => (string) $comment->comment_author,
				'comment_content' => (string) $comment->comment_content,
				'comment_approved' => (string) $comment->comment_approved,
			];
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Comments listed.', 'openwp' ),
				'data'    => [ 'items' => $items ],
			]
		);
	}

	/**
	 * Update comment.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function update_comment( $params, ActionContext $context ) {
		$comment_id = absint( $params['comment_id'] ?? 0 );
		$comment    = get_comment( $comment_id );
		if ( ! $comment ) {
			return new WP_Error( 'openwp_comment_not_found', __( 'Comment not found.', 'openwp' ) );
		}

		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			return new WP_Error( 'openwp_comment_edit_denied', __( 'You do not have permission to edit this comment.', 'openwp' ) );
		}

		$before = [
			'comment_id' => $comment_id,
			'content'    => $comment->comment_content,
			'status'     => wp_get_comment_status( $comment_id ),
		];

		$data = [ 'comment_ID' => $comment_id ];
		if ( array_key_exists( 'content', $params ) ) {
			$data['comment_content'] = wp_kses_post( (string) $params['content'] );
		}
		if ( array_key_exists( 'status', $params ) ) {
			$data['comment_approved'] = sanitize_key( (string) $params['status'] );
		}

		$result = wp_update_comment( $data, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Comment updated.', 'openwp' ),
				'data'    => [ 'comment_id' => $comment_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_update_comment',
					'params'          => $before,
				],
			]
		);
	}

	/**
	 * Delete comment.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function delete_comment( $params, ActionContext $context ) {
		$comment_id = absint( $params['comment_id'] ?? 0 );
		$comment    = get_comment( $comment_id );
		if ( ! $comment ) {
			return new WP_Error( 'openwp_comment_not_found', __( 'Comment not found.', 'openwp' ) );
		}

		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			return new WP_Error( 'openwp_comment_delete_denied', __( 'You do not have permission to delete this comment.', 'openwp' ) );
		}

		$deleted = wp_delete_comment( $comment_id, true );
		if ( ! $deleted ) {
			return new WP_Error( 'openwp_comment_delete_failed', __( 'Comment deletion failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Comment deleted.', 'openwp' ),
				'data'    => [ 'comment_id' => $comment_id ],
			]
		);
	}
}
