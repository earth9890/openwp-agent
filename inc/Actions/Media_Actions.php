<?php
/**
 * Media actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media handlers.
 */
class Media_Actions {
	/**
	 * List media.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function list_media( $params, ActionContext $context ) {
		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => isset( $params['per_page'] ) ? max( 1, min( 50, absint( $params['per_page'] ) ) ) : 20,
				'paged'          => isset( $params['page'] ) ? max( 1, absint( $params['page'] ) ) : 1,
			]
		);

		$items = [];
		foreach ( $query->posts as $item ) {
			$items[] = [
				'id'    => (int) $item->ID,
				'title' => (string) $item->post_title,
				'url'   => wp_get_attachment_url( $item->ID ),
				'mime'  => (string) $item->post_mime_type,
			];
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Media listed.', 'openwp' ),
				'data'    => [ 'items' => $items ],
			]
		);
	}

	/**
	 * Upload media from URL.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function upload_media( $params, ActionContext $context ) {
		if ( empty( $params['file_url'] ) ) {
			return new WP_Error( 'openwp_media_missing_url', __( 'file_url is required.', 'openwp' ) );
		}

		$file_url = self::validate_remote_media_url( (string) $params['file_url'] );
		if ( is_wp_error( $file_url ) ) {
			return $file_url;
		}

		$post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'openwp_media_post_edit_denied', __( 'You do not have permission to attach media to this post.', 'openwp' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $file_url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$filename = basename( (string) parse_url( $file_url, PHP_URL_PATH ) ?: 'openwp-upload' );

		// Ensure the filename has an extension so WordPress can detect the MIME type.
		// Remote URLs (e.g. Unsplash, Pexels) often omit file extensions.
		if ( ! pathinfo( $filename, PATHINFO_EXTENSION ) ) {
			$mime_map = [
				'image/jpeg'    => 'jpg',
				'image/png'     => 'png',
				'image/gif'     => 'gif',
				'image/webp'    => 'webp',
				'image/svg+xml' => 'svg',
				'image/avif'    => 'avif',
				'video/mp4'     => 'mp4',
				'application/pdf' => 'pdf',
			];

			// Detect MIME from actual file bytes.
			$detected_mime = function_exists( 'mime_content_type' ) ? mime_content_type( $tmp ) : '';
			if ( empty( $detected_mime ) ) {
				$detected_mime = '';
			}

			$ext = isset( $mime_map[ $detected_mime ] ) ? $mime_map[ $detected_mime ] : '';

			if ( '' === $ext && '' !== $detected_mime ) {
				// Fallback: extract extension from MIME subtype (e.g. image/png → png).
				$parts = explode( '/', $detected_mime );
				if ( count( $parts ) === 2 ) {
					$ext = sanitize_file_name( $parts[1] );
				}
			}

			if ( '' !== $ext ) {
				$filename .= '.' . $ext;
			} else {
				// Cannot determine file type - clean up and abort.
				@unlink( $tmp );
				return new WP_Error( 'openwp_media_unknown_type', __( 'Could not determine the file type of the downloaded media.', 'openwp' ) );
			}
		}

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp,
		];

		$media_id = media_handle_sideload( $file_array, $post_id, isset( $params['title'] ) ? sanitize_text_field( (string) $params['title'] ) : null );

		if ( is_wp_error( $media_id ) ) {
			@unlink( $tmp );
			return $media_id;
		}

		if ( array_key_exists( 'alt_text', $params ) ) {
			update_post_meta( $media_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $params['alt_text'] ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Media uploaded.', 'openwp' ),
				'data'    => [
					'attachment_id' => (int) $media_id,
					'url'           => wp_get_attachment_url( $media_id ),
				],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_delete_media',
					'params'          => [ 'attachment_id' => (int) $media_id ],
				],
			]
		);
	}

	/**
	 * Update media metadata.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function update_media( $params, ActionContext $context ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$post          = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'openwp_media_not_found', __( 'Attachment not found.', 'openwp' ) );
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error( 'openwp_media_edit_denied', __( 'You do not have permission to edit this media item.', 'openwp' ) );
		}

		$before = [
			'attachment_id' => $attachment_id,
			'title'         => $post->post_title,
			'caption'       => $post->post_excerpt,
			'description'   => $post->post_content,
			'alt_text'      => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		];

		$update = [ 'ID' => $attachment_id ];
		if ( array_key_exists( 'title', $params ) ) {
			$update['post_title'] = sanitize_text_field( (string) $params['title'] );
		}
		if ( array_key_exists( 'caption', $params ) ) {
			$update['post_excerpt'] = sanitize_textarea_field( (string) $params['caption'] );
		}
		if ( array_key_exists( 'description', $params ) ) {
			$update['post_content'] = sanitize_textarea_field( (string) $params['description'] );
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( array_key_exists( 'alt_text', $params ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $params['alt_text'] ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Media updated.', 'openwp' ),
				'data'    => [ 'attachment_id' => $attachment_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_update_media',
					'params'          => $before,
				],
			]
		);
	}

	/**
	 * Delete media.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function delete_media( $params, ActionContext $context ) {
		$attachment_id = absint( $params['attachment_id'] ?? 0 );
		$post          = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'openwp_media_not_found', __( 'Attachment not found.', 'openwp' ) );
		}

		if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
			return new WP_Error( 'openwp_media_delete_denied', __( 'You do not have permission to delete this media item.', 'openwp' ) );
		}

		$deleted = wp_delete_attachment( $attachment_id, true );
		if ( ! $deleted ) {
			return new WP_Error( 'openwp_media_delete_failed', __( 'Attachment deletion failed.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Media deleted.', 'openwp' ),
				'data'    => [ 'attachment_id' => $attachment_id ],
			]
		);
	}

	/**
	 * Validate media sideload URL.
	 *
	 * @param string $url Candidate URL.
	 * @return string|WP_Error
	 */
	private static function validate_remote_media_url( $url ) {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return new WP_Error( 'openwp_media_invalid_url', __( 'Invalid file_url.', 'openwp' ) );
		}

		if ( false === wp_http_validate_url( $url ) ) {
			return new WP_Error( 'openwp_media_unsafe_url', __( 'Only safe public HTTP(S) URLs are allowed for media uploads.', 'openwp' ) );
		}

		return $url;
	}
}
