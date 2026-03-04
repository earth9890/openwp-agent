<?php
/**
 * Content actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post/page handlers.
 */
class Content_Actions {
	/**
	 * List posts.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function list_posts( $params, ActionContext $context ) {
		$allowed_orderby = [ 'date', 'title', 'modified', 'ID', 'menu_order' ];
		$orderby         = isset( $params['orderby'] ) && in_array( $params['orderby'], $allowed_orderby, true )
			? (string) $params['orderby']
			: 'date';

		$args = [
			'post_type'      => isset( $params['post_type'] ) ? (string) $params['post_type'] : 'post',
			'post_status'    => isset( $params['post_status'] ) ? (string) $params['post_status'] : 'any',
			'posts_per_page' => isset( $params['per_page'] ) ? max( 1, min( 50, absint( $params['per_page'] ) ) ) : 10,
			'paged'          => isset( $params['page'] ) ? max( 1, absint( $params['page'] ) ) : 1,
			'orderby'        => $orderby,
			'order'          => isset( $params['order'] ) && 'ASC' === strtoupper( (string) $params['order'] ) ? 'ASC' : 'DESC',
		];

		if ( isset( $params['search'] ) && '' !== trim( (string) $params['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $params['search'] );
		}

		$query = new \WP_Query( $args );
		$items = [];
		foreach ( $query->posts as $post ) {
			$items[] = [
				'ID'          => (int) $post->ID,
				'post_type'   => (string) $post->post_type,
				'post_status' => (string) $post->post_status,
				'post_title'  => (string) $post->post_title,
				'post_date'   => (string) $post->post_date_gmt,
				'link'        => get_permalink( $post->ID ),
			];
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Posts retrieved.', 'openwp' ),
				'data'    => [
					'items'       => $items,
					'total'       => (int) $query->found_posts,
					'total_pages' => (int) $query->max_num_pages,
				],
			]
		);
	}

	/**
	 * Create post.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function create_post( $params, ActionContext $context ) {
		$raw_content = (string) ( $params['content'] ?? '' );
		$post_content = self::normalize_to_gutenberg_blocks( $raw_content );
		$post_status  = self::validate_post_status( (string) ( $params['status'] ?? 'draft' ) );
		if ( is_wp_error( $post_status ) ) {
			return $post_status;
		}

		$post_type = self::validate_post_type( (string) ( $params['post_type'] ?? 'post' ) );
		if ( is_wp_error( $post_type ) ) {
			return $post_type;
		}

		$capability_check = self::assert_post_type_create_capability( $post_type );
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		$post_author = $context->user_id;
		if ( isset( $params['post_author'] ) && absint( $params['post_author'] ) > 0 ) {
			$post_author = absint( $params['post_author'] );
		}

		$postarr = [
			'post_title'   => sanitize_text_field( (string) ( $params['title'] ?? '' ) ),
			'post_content' => $post_content,
			'post_excerpt' => sanitize_textarea_field( (string) ( $params['excerpt'] ?? '' ) ),
			'post_status'  => $post_status,
			'post_type'    => $post_type,
			'post_author'  => $post_author,
		];

		if ( ! empty( $params['meta_input'] ) && is_array( $params['meta_input'] ) ) {
			$postarr['meta_input'] = $params['meta_input'];
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Post created.', 'openwp' ),
				'data'    => [
					'post_id' => (int) $post_id,
					'link'    => get_permalink( $post_id ),
				],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_delete_post',
					'params'          => [ 'post_id' => (int) $post_id ],
				],
			]
		);
	}

	/**
	 * Update post.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function update_post( $params, ActionContext $context ) {
		$post_id = absint( $params['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'openwp_post_not_found', __( 'Post not found.', 'openwp' ) );
		}

		$capability_check = self::assert_post_capability(
			'edit_post',
			$post_id,
			'openwp_post_edit_denied',
			__( 'You do not have permission to edit this post.', 'openwp' )
		);
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		$before = [
			'post_id'      => (int) $post->ID,
			'post_title'   => (string) $post->post_title,
			'post_content' => (string) $post->post_content,
			'post_excerpt' => (string) $post->post_excerpt,
			'post_status'  => (string) $post->post_status,
		];

		$update = [ 'ID' => $post_id ];
		if ( array_key_exists( 'title', $params ) ) {
			$update['post_title'] = sanitize_text_field( (string) $params['title'] );
		}
		if ( array_key_exists( 'content', $params ) ) {
			$update['post_content'] = self::normalize_to_gutenberg_blocks( (string) $params['content'] );
		}
		if ( array_key_exists( 'excerpt', $params ) ) {
			$update['post_excerpt'] = sanitize_textarea_field( (string) $params['excerpt'] );
		}
		if ( array_key_exists( 'status', $params ) ) {
			$status = self::validate_post_status( (string) $params['status'] );
			if ( is_wp_error( $status ) ) {
				return $status;
			}
			$update['post_status'] = $status;
		}
		if ( array_key_exists( 'post_author', $params ) && absint( $params['post_author'] ) > 0 ) {
			$update['post_author'] = absint( $params['post_author'] );
		}
		if ( array_key_exists( 'meta_input', $params ) && is_array( $params['meta_input'] ) ) {
			$update['meta_input'] = $params['meta_input'];
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Post updated.', 'openwp' ),
				'data'    => [ 'post_id' => $post_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_update_post',
					'params'          => $before,
				],
			]
		);
	}

	/**
	 * Publish post.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function publish_post( $params, ActionContext $context ) {
		$post_id = absint( $params['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'openwp_post_not_found', __( 'Post not found.', 'openwp' ) );
		}

		$capability_check = self::assert_post_capability(
			'edit_post',
			$post_id,
			'openwp_post_publish_denied',
			__( 'You do not have permission to publish this post.', 'openwp' )
		);
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		$before_status = (string) $post->post_status;
		$result        = wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Post published.', 'openwp' ),
				'data'    => [ 'post_id' => $post_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_update_post',
					'params'          => [
						'post_id' => $post_id,
						'status'  => $before_status,
					],
				],
			]
		);
	}

	/**
	 * Delete post.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function delete_post( $params, ActionContext $context ) {
		$post_id = absint( $params['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'openwp_post_not_found', __( 'Post not found.', 'openwp' ) );
		}

		$capability_check = self::assert_post_capability(
			'delete_post',
			$post_id,
			'openwp_post_delete_denied',
			__( 'You do not have permission to delete this post.', 'openwp' )
		);
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		$before = [
			'post_type'    => $post->post_type,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => 'draft',
		];

		$deleted = wp_delete_post( $post_id, true );
		if ( ! $deleted ) {
			return new WP_Error( 'openwp_delete_failed', __( 'Failed to delete post.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Post deleted.', 'openwp' ),
				'data'    => [ 'post_id' => $post_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_create_post',
					'params'          => $before,
				],
			]
		);
	}

	/**
	 * Convert plain or markdown-like content into Gutenberg block markup.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private static function normalize_to_gutenberg_blocks( $content ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}

		$sanitized = wp_kses_post( $content );
		if ( '' === trim( $sanitized ) ) {
			return '';
		}

		// If block markup already exists, keep it.
		if ( function_exists( 'has_blocks' ) && has_blocks( $sanitized ) ) {
			return $sanitized;
		}
		if ( false !== strpos( $sanitized, '<!-- wp:' ) ) {
			return $sanitized;
		}

		$lines = preg_split( '/\r\n|\r|\n/', $sanitized );
		if ( ! is_array( $lines ) ) {
			return self::build_paragraph_block( $sanitized );
		}

		$blocks     = [];
		$paragraphs = [];
		$list_type  = '';
		$list_items = [];
		$quote      = [];

		foreach ( $lines as $line ) {
			$line = (string) $line;
			$trim = trim( $line );

			if ( '' === $trim ) {
				self::flush_paragraphs( $paragraphs, $blocks );
				self::flush_list( $list_type, $list_items, $blocks );
				self::flush_quote( $quote, $blocks );
				continue;
			}

			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trim, $matches ) ) {
				self::flush_paragraphs( $paragraphs, $blocks );
				self::flush_list( $list_type, $list_items, $blocks );
				self::flush_quote( $quote, $blocks );
				$level    = min( 6, max( 1, strlen( (string) $matches[1] ) ) );
				$blocks[] = self::build_heading_block( (string) $matches[2], $level );
				continue;
			}

			if ( preg_match( '/^([-*_])\1{2,}$/', $trim ) ) {
				self::flush_paragraphs( $paragraphs, $blocks );
				self::flush_list( $list_type, $list_items, $blocks );
				self::flush_quote( $quote, $blocks );
				$blocks[] = '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
				continue;
			}

			if ( preg_match( '/^>\s?(.*)$/', $trim, $matches ) ) {
				self::flush_paragraphs( $paragraphs, $blocks );
				self::flush_list( $list_type, $list_items, $blocks );
				$quote[] = (string) $matches[1];
				continue;
			}

			if ( preg_match( '/^\d+[\.\)]\s+(.+)$/', $trim, $matches ) ) {
				self::flush_paragraphs( $paragraphs, $blocks );
				self::flush_quote( $quote, $blocks );
				if ( 'ordered' !== $list_type ) {
					self::flush_list( $list_type, $list_items, $blocks );
					$list_type = 'ordered';
				}
				$list_items[] = (string) $matches[1];
				continue;
			}

			if ( preg_match( '/^[-*+]\s+(.+)$/', $trim, $matches ) ) {
				self::flush_paragraphs( $paragraphs, $blocks );
				self::flush_quote( $quote, $blocks );
				if ( 'unordered' !== $list_type ) {
					self::flush_list( $list_type, $list_items, $blocks );
					$list_type = 'unordered';
				}
				$list_items[] = (string) $matches[1];
				continue;
			}

			self::flush_list( $list_type, $list_items, $blocks );
			self::flush_quote( $quote, $blocks );
			$paragraphs[] = $trim;
		}

		self::flush_paragraphs( $paragraphs, $blocks );
		self::flush_list( $list_type, $list_items, $blocks );
		self::flush_quote( $quote, $blocks );

		if ( empty( $blocks ) ) {
			return self::build_paragraph_block( $sanitized );
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Flush paragraph buffer as paragraph block.
	 *
	 * @param array<int,string> $paragraphs Paragraph buffer.
	 * @param array<int,string> $blocks Output blocks.
	 * @return void
	 */
	private static function flush_paragraphs( &$paragraphs, &$blocks ) {
		if ( empty( $paragraphs ) ) {
			return;
		}

		$blocks[]  = self::build_paragraph_block( implode( ' ', $paragraphs ) );
		$paragraphs = [];
	}

	/**
	 * Flush list buffer as list block.
	 *
	 * @param string            $list_type ordered|unordered.
	 * @param array<int,string> $list_items List items.
	 * @param array<int,string> $blocks Output blocks.
	 * @return void
	 */
	private static function flush_list( &$list_type, &$list_items, &$blocks ) {
		if ( empty( $list_items ) ) {
			return;
		}

		$ordered   = 'ordered' === $list_type;
		$tag       = $ordered ? 'ol' : 'ul';
		$items_html = '';
		foreach ( $list_items as $item ) {
			$inline = self::inline_markdown_to_html( $item );
			$items_html .= '<li>' . $inline . '</li>';
		}

		$attrs = $ordered ? ' {"ordered":true}' : '';
		$blocks[] = '<!-- wp:list' . $attrs . ' --><' . $tag . '>' . $items_html . '</' . $tag . '><!-- /wp:list -->';

		$list_type  = '';
		$list_items = [];
	}

	/**
	 * Flush quote buffer as quote block.
	 *
	 * @param array<int,string> $quote Quote lines.
	 * @param array<int,string> $blocks Output blocks.
	 * @return void
	 */
	private static function flush_quote( &$quote, &$blocks ) {
		if ( empty( $quote ) ) {
			return;
		}

		$paragraph_html = '';
		foreach ( $quote as $line ) {
			$inline = self::inline_markdown_to_html( $line );
			$paragraph_html .= '<p>' . $inline . '</p>';
		}

		$blocks[] = '<!-- wp:quote --><blockquote class="wp-block-quote">' . $paragraph_html . '</blockquote><!-- /wp:quote -->';
		$quote = [];
	}

	/**
	 * Build paragraph block.
	 *
	 * @param string $text Paragraph text.
	 * @return string
	 */
	private static function build_paragraph_block( $text ) {
		$inline = self::inline_markdown_to_html( $text );
		return '<!-- wp:paragraph --><p>' . $inline . '</p><!-- /wp:paragraph -->';
	}

	/**
	 * Build heading block.
	 *
	 * @param string $text Heading text.
	 * @param int    $level Heading level.
	 * @return string
	 */
	private static function build_heading_block( $text, $level ) {
		$safe_level = min( 6, max( 1, absint( $level ) ) );
		$inline     = self::inline_markdown_to_html( $text );
		return '<!-- wp:heading {"level":' . $safe_level . '} --><h' . $safe_level . '>' . $inline . '</h' . $safe_level . '><!-- /wp:heading -->';
	}

	/**
	 * Convert basic inline markdown to safe HTML.
	 *
	 * @param string $text Input line text.
	 * @return string
	 */
	private static function inline_markdown_to_html( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}

		$text = preg_replace_callback(
			'/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/i',
			static function ( $matches ) {
				$label = isset( $matches[1] ) ? (string) $matches[1] : '';
				$url   = isset( $matches[2] ) ? (string) $matches[2] : '';
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			},
			$text
		);

		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );
		$text = preg_replace( '/__(.+?)__/s', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text );
		$text = preg_replace( '/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/s', '<em>$1</em>', $text );
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

		$allowed = [
			'a'      => [ 'href' => [], 'target' => [], 'rel' => [] ],
			'strong' => [],
			'em'     => [],
			'code'   => [],
			'br'     => [],
		];

		return wp_kses( $text, $allowed );
	}

	/**
	 * Validate a post status slug.
	 *
	 * @param string $status Status candidate.
	 * @return string|WP_Error
	 */
	private static function validate_post_status( $status ) {
		$status = sanitize_key( $status );
		if ( '' === $status ) {
			return new WP_Error( 'openwp_post_status_required', __( 'Post status is required.', 'openwp' ) );
		}

		$registered = get_post_stati();
		$allowed    = is_array( $registered ) ? array_keys( $registered ) : [];
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'openwp_post_status_invalid', __( 'Invalid post status.', 'openwp' ) );
		}

		return $status;
	}

	/**
	 * Validate a post type slug.
	 *
	 * @param string $post_type Post type candidate.
	 * @return string|WP_Error
	 */
	private static function validate_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return new WP_Error( 'openwp_post_type_invalid', __( 'Invalid post type.', 'openwp' ) );
		}

		return $post_type;
	}

	/**
	 * Check post object-level capability.
	 *
	 * @param string $capability Capability key.
	 * @param int    $post_id Post ID.
	 * @param string $error_code Error code.
	 * @param string $message Error message.
	 * @return true|WP_Error
	 */
	private static function assert_post_capability( $capability, $post_id, $error_code, $message ) {
		if ( ! current_user_can( $capability, $post_id ) ) {
			return new WP_Error( $error_code, $message );
		}

		return true;
	}

	/**
	 * Check create capability for the target post type.
	 *
	 * @param string $post_type Post type.
	 * @return true|WP_Error
	 */
	private static function assert_post_type_create_capability( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! isset( $post_type_object->cap ) ) {
			return new WP_Error( 'openwp_post_type_invalid', __( 'Invalid post type.', 'openwp' ) );
		}

		$create_capability = 'edit_posts';
		if ( isset( $post_type_object->cap->create_posts ) && is_string( $post_type_object->cap->create_posts ) ) {
			$create_capability = $post_type_object->cap->create_posts;
		} elseif ( isset( $post_type_object->cap->edit_posts ) && is_string( $post_type_object->cap->edit_posts ) ) {
			$create_capability = $post_type_object->cap->edit_posts;
		}

		if ( ! current_user_can( $create_capability ) ) {
			return new WP_Error( 'openwp_post_create_denied', __( 'You do not have permission to create this post type.', 'openwp' ) );
		}

		return true;
	}
}
