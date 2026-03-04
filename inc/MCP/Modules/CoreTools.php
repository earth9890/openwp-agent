<?php
/**
 * Core MCP tools (AI Engine compatible names).
 *
 * @package OpenWP\Inc\MCP\Modules
 */

namespace OpenWP\Inc\MCP\Modules;

use OpenWP\Inc\Actions\ActionContext;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress core MCP tool handlers.
 */
class CoreTools implements ToolModuleInterface {
	/**
	 * Upload transient prefix.
	 *
	 * @var string
	 */
	private $upload_prefix = 'openwp_mcp_upload_';

	/**
	 * Tool catalog.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private $tools;

	/**
	 * Return tools indexed by name.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_tools() {
		if ( null !== $this->tools ) {
			return $this->tools;
		}

		$tools = [
			'wp_list_plugins' => [
				'description' => 'List installed plugins.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'search' => [ 'type' => 'string' ] ] ],
			],
			'wp_get_users' => [
				'description' => 'Retrieve users.',
				'accessLevel' => 'read',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search' => [ 'type' => 'string' ],
						'role'   => [ 'type' => 'string' ],
						'limit'  => [ 'type' => 'integer' ],
						'offset' => [ 'type' => 'integer' ],
						'paged'  => [ 'type' => 'integer' ],
					],
				],
			],
			'wp_create_user' => [
				'description' => 'Create a user.',
				'accessLevel' => 'admin',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'user_login'   => [ 'type' => 'string' ],
						'user_email'   => [ 'type' => 'string' ],
						'user_pass'    => [ 'type' => 'string' ],
						'display_name' => [ 'type' => 'string' ],
						'role'         => [ 'type' => 'string' ],
					],
					'required'   => [ 'user_login', 'user_email' ],
				],
			],
			'wp_update_user' => [
				'description' => 'Update a user.',
				'accessLevel' => 'admin',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ID'     => [ 'type' => 'integer' ],
						'fields' => [ 'type' => 'object' ],
					],
					'required'   => [ 'ID' ],
				],
			],
			'wp_get_comments' => [
				'description' => 'Retrieve comments.',
				'accessLevel' => 'read',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'status'  => [ 'type' => 'string' ],
						'search'  => [ 'type' => 'string' ],
						'limit'   => [ 'type' => 'integer' ],
						'offset'  => [ 'type' => 'integer' ],
					],
				],
			],
			'wp_create_comment' => [
				'description' => 'Create a comment.',
				'accessLevel' => 'write',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id'              => [ 'type' => 'integer' ],
						'comment_content'      => [ 'type' => 'string' ],
						'comment_author'       => [ 'type' => 'string' ],
						'comment_author_email' => [ 'type' => 'string' ],
						'comment_author_url'   => [ 'type' => 'string' ],
						'comment_approved'     => [ 'type' => 'string' ],
					],
					'required'   => [ 'post_id', 'comment_content' ],
				],
			],
			'wp_update_comment' => [
				'description' => 'Update a comment.',
				'accessLevel' => 'write',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'comment_ID' => [ 'type' => 'integer' ],
						'fields'     => [ 'type' => 'object' ],
					],
					'required'   => [ 'comment_ID' ],
				],
			],
			'wp_delete_comment' => [
				'description' => 'Delete a comment.',
				'accessLevel' => 'admin',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'comment_ID' => [ 'type' => 'integer' ],
						'force'      => [ 'type' => 'boolean' ],
					],
					'required'   => [ 'comment_ID' ],
				],
			],
			'wp_get_option' => [
				'description' => 'Get a WordPress option value.',
				'accessLevel' => 'admin',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'key' => [ 'type' => 'string' ] ], 'required' => [ 'key' ] ],
			],
			'wp_update_option' => [
				'description' => 'Update a WordPress option.',
				'accessLevel' => 'admin',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'key'   => [ 'type' => 'string' ],
						'value' => [ 'type' => 'any' ],
					],
					'required'   => [ 'key', 'value' ],
				],
			],
			'wp_count_posts' => [
				'description' => 'Count posts by status.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'post_type' => [ 'type' => 'string' ] ] ],
			],
			'wp_count_terms' => [
				'description' => 'Count terms in a taxonomy.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'taxonomy' => [ 'type' => 'string' ] ], 'required' => [ 'taxonomy' ] ],
			],
			'wp_count_media' => [
				'description' => 'Count media items.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'after' => [ 'type' => 'string' ], 'before' => [ 'type' => 'string' ] ] ],
			],
			'wp_get_post_types' => [
				'description' => 'List public post types.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			'wp_get_posts' => [
				'description' => 'Retrieve posts.',
				'accessLevel' => 'read',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'   => [ 'type' => 'string' ],
						'post_status' => [ 'type' => 'string' ],
						'search'      => [ 'type' => 'string' ],
						'after'       => [ 'type' => 'string' ],
						'before'      => [ 'type' => 'string' ],
						'limit'       => [ 'type' => 'integer' ],
						'offset'      => [ 'type' => 'integer' ],
						'paged'       => [ 'type' => 'integer' ],
					],
				],
			],
			'wp_get_post' => [
				'description' => 'Get one post by ID.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'ID' => [ 'type' => 'integer' ] ], 'required' => [ 'ID' ] ],
			],
			'wp_get_post_snapshot' => [
				'description' => 'Get complete post snapshot including meta and terms.',
				'accessLevel' => 'read',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ID'      => [ 'type' => 'integer' ],
						'include' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'exclude' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					],
					'required'   => [ 'ID' ],
				],
			],
			'wp_create_post' => [
				'description' => 'Create a post.',
				'accessLevel' => 'write',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_title'   => [ 'type' => 'string' ],
						'post_content' => [ 'type' => 'string' ],
						'post_excerpt' => [ 'type' => 'string' ],
						'post_status'  => [ 'type' => 'string' ],
						'post_type'    => [ 'type' => 'string' ],
						'post_name'    => [ 'type' => 'string' ],
						'meta_input'   => [ 'type' => 'object' ],
					],
					'required'   => [ 'post_title' ],
				],
			],
			'wp_update_post' => [
				'description' => 'Update post fields and/or meta.',
				'accessLevel' => 'write',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ID'           => [ 'type' => 'integer' ],
						'fields'       => [ 'type' => 'object' ],
						'meta_input'   => [ 'type' => 'object' ],
						'schedule_for' => [ 'type' => 'string' ],
					],
					'required'   => [ 'ID' ],
				],
			],
			'wp_delete_post' => [
				'description' => 'Delete or trash a post.',
				'accessLevel' => 'admin',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'ID' => [ 'type' => 'integer' ], 'force' => [ 'type' => 'boolean' ] ], 'required' => [ 'ID' ] ],
			],
			'wp_alter_post' => [
				'description' => 'Search and replace inside a post field.',
				'accessLevel' => 'write',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ID'      => [ 'type' => 'integer' ],
						'field'   => [ 'type' => 'string' ],
						'search'  => [ 'type' => 'string' ],
						'replace' => [ 'type' => 'string' ],
						'regex'   => [ 'type' => 'boolean' ],
					],
					'required'   => [ 'ID', 'field', 'search', 'replace' ],
				],
			],
			'wp_get_post_meta' => [
				'description' => 'Get post meta.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'ID' => [ 'type' => 'integer' ], 'key' => [ 'type' => 'string' ] ], 'required' => [ 'ID' ] ],
			],
			'wp_update_post_meta' => [
				'description' => 'Update one or many post meta values.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'ID' => [ 'type' => 'integer' ], 'meta' => [ 'type' => 'object' ], 'key' => [ 'type' => 'string' ], 'value' => [ 'type' => 'any' ] ], 'required' => [ 'ID' ] ],
			],
			'wp_delete_post_meta' => [
				'description' => 'Delete post meta.',
				'accessLevel' => 'admin',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'ID' => [ 'type' => 'integer' ], 'key' => [ 'type' => 'string' ], 'value' => [ 'type' => 'any' ] ], 'required' => [ 'ID', 'key' ] ],
			],
			'wp_set_featured_image' => [
				'description' => 'Set or remove featured image.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'ID' => [ 'type' => 'integer' ], 'media_id' => [ 'type' => 'integer' ] ], 'required' => [ 'ID' ] ],
			],
			'wp_get_taxonomies' => [
				'description' => 'List taxonomies.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			'wp_get_terms' => [
				'description' => 'Get terms for a taxonomy.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'taxonomy' => [ 'type' => 'string' ], 'search' => [ 'type' => 'string' ], 'limit' => [ 'type' => 'integer' ] ], 'required' => [ 'taxonomy' ] ],
			],
			'wp_create_term' => [
				'description' => 'Create taxonomy term.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'taxonomy' => [ 'type' => 'string' ], 'name' => [ 'type' => 'string' ], 'slug' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ] ], 'required' => [ 'taxonomy', 'name' ] ],
			],
			'wp_update_term' => [
				'description' => 'Update taxonomy term.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'taxonomy' => [ 'type' => 'string' ], 'term_id' => [ 'type' => 'integer' ], 'name' => [ 'type' => 'string' ], 'slug' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ] ], 'required' => [ 'taxonomy', 'term_id' ] ],
			],
			'wp_delete_term' => [
				'description' => 'Delete taxonomy term.',
				'accessLevel' => 'admin',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'taxonomy' => [ 'type' => 'string' ], 'term_id' => [ 'type' => 'integer' ] ], 'required' => [ 'taxonomy', 'term_id' ] ],
			],
			'wp_get_post_terms' => [
				'description' => 'Get terms attached to a post.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'ID' => [ 'type' => 'integer' ], 'taxonomy' => [ 'type' => 'string' ] ], 'required' => [ 'ID', 'taxonomy' ] ],
			],
			'wp_add_post_terms' => [
				'description' => 'Append terms to a post.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'ID' => [ 'type' => 'integer' ], 'taxonomy' => [ 'type' => 'string' ], 'terms' => [ 'type' => 'array', 'items' => [ 'type' => [ 'string', 'integer' ] ] ] ], 'required' => [ 'ID', 'taxonomy', 'terms' ] ],
			],
			'wp_get_media' => [
				'description' => 'List media attachments.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'search' => [ 'type' => 'string' ], 'limit' => [ 'type' => 'integer' ], 'offset' => [ 'type' => 'integer' ] ] ],
			],
			'wp_upload_media' => [
				'description' => 'Upload media from remote URL.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'file_url' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ], 'alt_text' => [ 'type' => 'string' ], 'post_id' => [ 'type' => 'integer' ] ], 'required' => [ 'file_url' ] ],
			],
			'wp_upload_request' => [
				'description' => 'Create one-time upload endpoint URL and token.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'filename' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ], 'alt' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ], 'expires_in' => [ 'type' => 'integer' ] ] ],
			],
			'wp_update_media' => [
				'description' => 'Update media metadata.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'attachment_id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'caption' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ], 'alt_text' => [ 'type' => 'string' ] ], 'required' => [ 'attachment_id' ] ],
			],
			'wp_delete_media' => [
				'description' => 'Delete media attachment.',
				'accessLevel' => 'admin',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'attachment_id' => [ 'type' => 'integer' ] ], 'required' => [ 'attachment_id' ] ],
			],
			'openwp_vision' => [
				'description' => 'Analyze an image with a multimodal model.',
				'accessLevel' => 'read',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'image_url' => [ 'type' => 'string' ], 'prompt' => [ 'type' => 'string' ] ], 'required' => [ 'image_url', 'prompt' ] ],
			],
			'openwp_image' => [
				'description' => 'Generate an image from a prompt.',
				'accessLevel' => 'write',
				'inputSchema' => [ 'type' => 'object', 'properties' => [ 'prompt' => [ 'type' => 'string' ], 'size' => [ 'type' => 'string' ], 'quality' => [ 'type' => 'string' ] ], 'required' => [ 'prompt' ] ],
			],
		];

		foreach ( $tools as $name => &$tool ) {
			$tool['name']     = $name;
			$tool['category'] = 'AI Engine (Core)';
			$tool['annotations'] = [
				'readOnlyHint'    => 'read' === $tool['accessLevel'],
				'destructiveHint' => false !== strpos( $name, 'delete' ),
				'openWorldHint'   => false,
			];
		}

		$this->tools = $tools;
		return $this->tools;
	}

	/**
	 * Check if module supports a tool.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	public function supports( $tool ) {
		$tools = $this->get_tools();
		return isset( $tools[ $tool ] );
	}

	/**
	 * Execute tool.
	 *
	 * @param string        $tool Tool name.
	 * @param array<string,mixed> $args Args.
	 * @param ActionContext $context Context.
	 * @return array<string,mixed>|string|WP_Error
	 */
	public function execute( $tool, $args, ActionContext $context ) {
		switch ( $tool ) {
			case 'wp_list_plugins':
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$plugins = get_plugins();
				$search  = isset( $args['search'] ) ? strtolower( (string) $args['search'] ) : '';
				$items   = [];
				foreach ( $plugins as $file => $plugin ) {
					$name = (string) ( $plugin['Name'] ?? '' );
					if ( '' !== $search && false === strpos( strtolower( $name ), $search ) && false === strpos( strtolower( $file ), $search ) ) {
						continue;
					}
					$items[] = [
						'file'    => $file,
						'name'    => $name,
						'version' => (string) ( $plugin['Version'] ?? '' ),
						'active'  => is_plugin_active( $file ),
					];
				}
				return [ 'plugins' => $items, 'count' => count( $items ) ];

			case 'wp_get_users':
				$user_query = [
					'number' => isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 10,
					'fields' => [ 'ID', 'user_login', 'display_name', 'user_email', 'roles' ],
				];
				if ( isset( $args['offset'] ) ) {
					$user_query['offset'] = max( 0, (int) $args['offset'] );
				} elseif ( isset( $args['paged'] ) && (int) $args['paged'] > 1 ) {
					$user_query['offset'] = ( (int) $args['paged'] - 1 ) * (int) $user_query['number'];
				}
				if ( ! empty( $args['search'] ) ) {
					$user_query['search'] = '*' . sanitize_text_field( (string) $args['search'] ) . '*';
				}
				if ( ! empty( $args['role'] ) ) {
					$user_query['role'] = sanitize_key( (string) $args['role'] );
				}
				$users = get_users( $user_query );
				$items = [];
				foreach ( $users as $user ) {
					$items[] = [
						'ID'           => (int) $user->ID,
						'user_login'   => (string) $user->user_login,
						'display_name' => (string) $user->display_name,
						'user_email'   => (string) $user->user_email,
						'roles'        => is_array( $user->roles ) ? $user->roles : [],
					];
				}
				return [ 'users' => $items, 'count' => count( $items ) ];

			case 'wp_create_user':
				$user_id = wp_insert_user(
					[
						'user_login'   => sanitize_user( (string) $args['user_login'], true ),
						'user_email'   => sanitize_email( (string) $args['user_email'] ),
						'user_pass'    => ! empty( $args['user_pass'] ) ? (string) $args['user_pass'] : wp_generate_password( 24, true, true ),
						'display_name' => isset( $args['display_name'] ) ? sanitize_text_field( (string) $args['display_name'] ) : '',
						'role'         => isset( $args['role'] ) ? sanitize_key( (string) $args['role'] ) : 'subscriber',
					]
				);
				if ( is_wp_error( $user_id ) ) {
					return $user_id;
				}
				return [ 'user_id' => (int) $user_id ];

			case 'wp_update_user':
				$fields    = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : [];
				$update    = [ 'ID' => (int) $args['ID'] ];
				$allowlist = [ 'user_email', 'display_name', 'user_pass', 'role' ];
				foreach ( $allowlist as $field ) {
					if ( array_key_exists( $field, $fields ) ) {
						$update[ $field ] = 'role' === $field ? sanitize_key( (string) $fields[ $field ] ) : sanitize_text_field( (string) $fields[ $field ] );
					}
				}
				$result = wp_update_user( $update );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return [ 'user_id' => (int) $result ];

			case 'wp_get_comments':
				$comment_args = [
					'number' => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 10,
					'offset' => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
					'status' => isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'all',
				];
				if ( ! empty( $args['post_id'] ) ) {
					$comment_args['post_id'] = (int) $args['post_id'];
				}
				if ( ! empty( $args['search'] ) ) {
					$comment_args['search'] = sanitize_text_field( (string) $args['search'] );
				}
				$comments = get_comments( $comment_args );
				$items    = [];
				foreach ( $comments as $comment ) {
					$items[] = [
						'comment_ID'       => (int) $comment->comment_ID,
						'comment_post_ID'  => (int) $comment->comment_post_ID,
						'comment_author'   => (string) $comment->comment_author,
						'comment_content'  => (string) $comment->comment_content,
						'comment_date'     => (string) $comment->comment_date,
						'comment_approved' => (string) $comment->comment_approved,
					];
				}
				return [ 'comments' => $items, 'count' => count( $items ) ];

			case 'wp_create_comment':
				$comment_id = wp_insert_comment(
					[
						'comment_post_ID'      => (int) $args['post_id'],
						'comment_content'      => wp_kses_post( (string) $args['comment_content'] ),
						'comment_author'       => isset( $args['comment_author'] ) ? sanitize_text_field( (string) $args['comment_author'] ) : '',
						'comment_author_email' => isset( $args['comment_author_email'] ) ? sanitize_email( (string) $args['comment_author_email'] ) : '',
						'comment_author_url'   => isset( $args['comment_author_url'] ) ? esc_url_raw( (string) $args['comment_author_url'] ) : '',
						'comment_approved'     => isset( $args['comment_approved'] ) ? sanitize_key( (string) $args['comment_approved'] ) : 1,
					],
					true
				);
				if ( is_wp_error( $comment_id ) ) {
					return $comment_id;
				}
				return [ 'comment_ID' => (int) $comment_id ];

			case 'wp_update_comment':
				$fields       = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : [];
				$comment_data = [ 'comment_ID' => (int) $args['comment_ID'] ];
				if ( array_key_exists( 'comment_content', $fields ) ) {
					$comment_data['comment_content'] = wp_kses_post( (string) $fields['comment_content'] );
				}
				if ( array_key_exists( 'comment_approved', $fields ) ) {
					$comment_data['comment_approved'] = sanitize_key( (string) $fields['comment_approved'] );
				}
				$result = wp_update_comment( $comment_data, true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return [ 'comment_ID' => (int) $args['comment_ID'], 'updated' => true ];

			case 'wp_delete_comment':
				$deleted = wp_delete_comment( (int) $args['comment_ID'], ! empty( $args['force'] ) );
				if ( ! $deleted ) {
					return new WP_Error( 'openwp_mcp_comment_delete_failed', __( 'Failed to delete comment.', 'openwp' ) );
				}
				return [ 'comment_ID' => (int) $args['comment_ID'], 'deleted' => true ];

			case 'wp_get_option':
				return [ 'key' => (string) $args['key'], 'value' => get_option( (string) $args['key'], null ) ];

			case 'wp_update_option':
				$updated = update_option( (string) $args['key'], $args['value'] );
				return [ 'key' => (string) $args['key'], 'updated' => (bool) $updated ];

			case 'wp_count_posts':
				$post_type = isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'post';
				$counts    = wp_count_posts( $post_type );
				return [ 'post_type' => $post_type, 'counts' => is_object( $counts ) ? get_object_vars( $counts ) : [] ];

			case 'wp_count_terms':
				$taxonomy = sanitize_key( (string) $args['taxonomy'] );
				return [ 'taxonomy' => $taxonomy, 'count' => (int) wp_count_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] ) ];

			case 'wp_count_media':
				$query_args = [
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				];
				$date_query = [];
				if ( ! empty( $args['after'] ) ) {
					$date_query['after'] = sanitize_text_field( (string) $args['after'] );
				}
				if ( ! empty( $args['before'] ) ) {
					$date_query['before'] = sanitize_text_field( (string) $args['before'] );
				}
				if ( ! empty( $date_query ) ) {
					$query_args['date_query'] = [ $date_query ];
				}
				$q = new \WP_Query( $query_args );
				return [ 'count' => (int) $q->found_posts ];

			case 'wp_get_post_types':
				$types = get_post_types( [ 'public' => true ], 'objects' );
				$items = [];
				foreach ( $types as $key => $type ) {
					$items[] = [ 'key' => $key, 'label' => $type->label ];
				}
				return [ 'post_types' => $items ];

			case 'wp_get_posts':
				$query = [
					'post_type'      => isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'post',
					'post_status'    => isset( $args['post_status'] ) ? sanitize_key( (string) $args['post_status'] ) : 'any',
					'posts_per_page' => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 10,
					'paged'          => isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1,
					'offset'         => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
					'orderby'        => 'date',
					'order'          => 'DESC',
				];
				if ( ! empty( $args['search'] ) ) {
					$query['s'] = sanitize_text_field( (string) $args['search'] );
				}
				$date_query = [];
				if ( ! empty( $args['after'] ) ) {
					$date_query['after'] = sanitize_text_field( (string) $args['after'] );
				}
				if ( ! empty( $args['before'] ) ) {
					$date_query['before'] = sanitize_text_field( (string) $args['before'] );
				}
				if ( ! empty( $date_query ) ) {
					$query['date_query'] = [ $date_query ];
				}
				$wpq   = new \WP_Query( $query );
				$items = [];
				foreach ( $wpq->posts as $post ) {
					$items[] = [
						'ID'      => (int) $post->ID,
						'title'   => get_the_title( $post->ID ),
						'status'  => (string) $post->post_status,
						'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 55 ),
						'link'    => get_permalink( $post->ID ),
					];
				}
				return [ 'posts' => $items, 'count' => count( $items ), 'total' => (int) $wpq->found_posts ];

			case 'wp_get_post':
				$post = get_post( (int) $args['ID'] );
				if ( ! $post ) {
					return new WP_Error( 'openwp_mcp_post_not_found', __( 'Post not found.', 'openwp' ) );
				}
				return [
					'ID'            => (int) $post->ID,
					'post_title'    => (string) $post->post_title,
					'post_content'  => (string) $post->post_content,
					'post_excerpt'  => (string) $post->post_excerpt,
					'post_status'   => (string) $post->post_status,
					'post_type'     => (string) $post->post_type,
					'post_date'     => (string) $post->post_date,
					'post_modified' => (string) $post->post_modified,
					'link'          => get_permalink( $post->ID ),
				];

			case 'wp_get_post_snapshot':
				return $this->post_snapshot( (int) $args['ID'], $args );

			case 'wp_create_post':
				$postarr = [
					'post_title'   => sanitize_text_field( (string) $args['post_title'] ),
					'post_content' => isset( $args['post_content'] ) ? wp_kses_post( (string) $args['post_content'] ) : '',
					'post_excerpt' => isset( $args['post_excerpt'] ) ? sanitize_textarea_field( (string) $args['post_excerpt'] ) : '',
					'post_status'  => isset( $args['post_status'] ) ? sanitize_key( (string) $args['post_status'] ) : 'draft',
					'post_type'    => isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'post',
					'post_name'    => isset( $args['post_name'] ) ? sanitize_title( (string) $args['post_name'] ) : '',
				];
				if ( isset( $args['meta_input'] ) && is_array( $args['meta_input'] ) ) {
					$postarr['meta_input'] = $args['meta_input'];
				}
				$post_id = wp_insert_post( $postarr, true );
				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}
				return [ 'post_id' => (int) $post_id, 'link' => get_permalink( (int) $post_id ) ];

			case 'wp_update_post':
				$post_id = (int) $args['ID'];
				if ( ! get_post( $post_id ) ) {
					return new WP_Error( 'openwp_mcp_post_not_found', __( 'Post not found.', 'openwp' ) );
				}
				$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : [];
				$update = [ 'ID' => $post_id ];
				foreach ( [ 'post_title', 'post_content', 'post_status', 'post_name', 'post_excerpt' ] as $field ) {
					if ( array_key_exists( $field, $fields ) ) {
						if ( 'post_content' === $field ) {
							$update[ $field ] = wp_kses_post( (string) $fields[ $field ] );
						} elseif ( 'post_status' === $field ) {
							$update[ $field ] = sanitize_key( (string) $fields[ $field ] );
						} elseif ( 'post_name' === $field ) {
							$update[ $field ] = sanitize_title( (string) $fields[ $field ] );
						} else {
							$update[ $field ] = sanitize_text_field( (string) $fields[ $field ] );
						}
					}
				}
				if ( isset( $fields['post_category'] ) && is_array( $fields['post_category'] ) ) {
					$update['post_category'] = array_map( 'absint', $fields['post_category'] );
				}
				if ( ! empty( $args['schedule_for'] ) ) {
					$local_datetime = sanitize_text_field( (string) $args['schedule_for'] );
					$timestamp      = strtotime( $local_datetime, current_time( 'timestamp' ) );
					if ( $timestamp ) {
						$update['post_status']   = 'future';
						$update['post_date']     = gmdate( 'Y-m-d H:i:s', $timestamp + (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
						$update['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp );
					}
				}
				$result = wp_update_post( $update, true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( isset( $args['meta_input'] ) && is_array( $args['meta_input'] ) ) {
					foreach ( $args['meta_input'] as $meta_key => $meta_value ) {
						update_post_meta( $post_id, sanitize_text_field( (string) $meta_key ), $meta_value );
					}
				}
				return [ 'post_id' => $post_id, 'updated' => true ];

			case 'wp_delete_post':
				$deleted = wp_delete_post( (int) $args['ID'], ! empty( $args['force'] ) );
				if ( ! $deleted ) {
					return new WP_Error( 'openwp_mcp_delete_post_failed', __( 'Failed to delete post.', 'openwp' ) );
				}
				return [ 'post_id' => (int) $args['ID'], 'deleted' => true ];

			case 'wp_alter_post':
				$post_id = (int) $args['ID'];
				$post    = get_post( $post_id );
				if ( ! $post ) {
					return new WP_Error( 'openwp_mcp_post_not_found', __( 'Post not found.', 'openwp' ) );
				}
				$field = sanitize_key( (string) $args['field'] );
				if ( ! in_array( $field, [ 'post_content', 'post_excerpt', 'post_title' ], true ) ) {
					return new WP_Error( 'openwp_mcp_invalid_field', __( 'Field must be post_content, post_excerpt, or post_title.', 'openwp' ) );
				}
				$current = (string) $post->$field;
				$search  = (string) $args['search'];
				$replace = (string) $args['replace'];
				if ( ! empty( $args['regex'] ) ) {
					$updated = preg_replace( $search, $replace, $current, -1, $count );
					if ( null === $updated ) {
						return new WP_Error( 'openwp_mcp_regex_invalid', __( 'Invalid regex pattern.', 'openwp' ) );
					}
				} else {
					$updated = str_replace( $search, $replace, $current, $count );
				}
				wp_update_post( [ 'ID' => $post_id, $field => $updated ] );
				return [ 'post_id' => $post_id, 'field' => $field, 'replacements' => (int) $count ];

			case 'wp_get_post_meta':
				$post_id = (int) $args['ID'];
				if ( ! empty( $args['key'] ) ) {
					$key = sanitize_text_field( (string) $args['key'] );
					return [ 'ID' => $post_id, 'key' => $key, 'value' => get_post_meta( $post_id, $key, true ) ];
				}
				return [ 'ID' => $post_id, 'meta' => get_post_meta( $post_id ) ];

			case 'wp_update_post_meta':
				$post_id = (int) $args['ID'];
				$updated = 0;
				if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
					foreach ( $args['meta'] as $meta_key => $meta_value ) {
						update_post_meta( $post_id, sanitize_text_field( (string) $meta_key ), $meta_value );
						++$updated;
					}
				} elseif ( isset( $args['key'] ) ) {
					update_post_meta( $post_id, sanitize_text_field( (string) $args['key'] ), $args['value'] ?? '' );
					$updated = 1;
				}
				return [ 'ID' => $post_id, 'updated' => $updated ];

			case 'wp_delete_post_meta':
				$post_id = (int) $args['ID'];
				$key     = sanitize_text_field( (string) $args['key'] );
				if ( array_key_exists( 'value', $args ) ) {
					$deleted = delete_post_meta( $post_id, $key, $args['value'] );
				} else {
					$deleted = delete_post_meta( $post_id, $key );
				}
				return [ 'ID' => $post_id, 'key' => $key, 'deleted' => (bool) $deleted ];

			case 'wp_set_featured_image':
				$post_id = (int) $args['ID'];
				if ( ! isset( $args['media_id'] ) || null === $args['media_id'] ) {
					delete_post_thumbnail( $post_id );
					return [ 'ID' => $post_id, 'featured_image' => null ];
				}
				$media_id = (int) $args['media_id'];
				set_post_thumbnail( $post_id, $media_id );
				return [ 'ID' => $post_id, 'featured_image' => $media_id ];

			case 'wp_get_taxonomies':
				$taxes = get_taxonomies( [ 'public' => true ], 'objects' );
				$items = [];
				foreach ( $taxes as $key => $taxonomy ) {
					$items[] = [ 'key' => $key, 'label' => $taxonomy->label ];
				}
				return [ 'taxonomies' => $items ];

			case 'wp_get_terms':
				$taxonomy = sanitize_key( (string) $args['taxonomy'] );
				$terms    = get_terms(
					[
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'search'     => isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '',
						'number'     => isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 20,
					]
				);
				if ( is_wp_error( $terms ) ) {
					return $terms;
				}
				$items = [];
				foreach ( $terms as $term ) {
					$items[] = [
						'term_id' => (int) $term->term_id,
						'name'    => (string) $term->name,
						'slug'    => (string) $term->slug,
						'count'   => (int) $term->count,
					];
				}
				return [ 'terms' => $items, 'count' => count( $items ) ];

			case 'wp_create_term':
				$result = wp_insert_term(
					sanitize_text_field( (string) $args['name'] ),
					sanitize_key( (string) $args['taxonomy'] ),
					[
						'slug'        => isset( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : '',
						'description' => isset( $args['description'] ) ? sanitize_textarea_field( (string) $args['description'] ) : '',
					]
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return [ 'term_id' => (int) ( $result['term_id'] ?? 0 ) ];

			case 'wp_update_term':
				$update = [];
				foreach ( [ 'name', 'slug', 'description' ] as $field ) {
					if ( array_key_exists( $field, $args ) ) {
						if ( 'slug' === $field ) {
							$update['slug'] = sanitize_title( (string) $args['slug'] );
						} elseif ( 'description' === $field ) {
							$update['description'] = sanitize_textarea_field( (string) $args['description'] );
						} else {
							$update['name'] = sanitize_text_field( (string) $args['name'] );
						}
					}
				}
				$result = wp_update_term( (int) $args['term_id'], sanitize_key( (string) $args['taxonomy'] ), $update );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return [ 'term_id' => (int) $args['term_id'], 'updated' => true ];

			case 'wp_delete_term':
				$result = wp_delete_term( (int) $args['term_id'], sanitize_key( (string) $args['taxonomy'] ) );
				if ( ! $result ) {
					return new WP_Error( 'openwp_mcp_term_delete_failed', __( 'Failed to delete term.', 'openwp' ) );
				}
				return [ 'term_id' => (int) $args['term_id'], 'deleted' => true ];

			case 'wp_get_post_terms':
				$terms = wp_get_post_terms( (int) $args['ID'], sanitize_key( (string) $args['taxonomy'] ) );
				if ( is_wp_error( $terms ) ) {
					return $terms;
				}
				$items = [];
				foreach ( $terms as $term ) {
					$items[] = [
						'term_id' => (int) $term->term_id,
						'name'    => (string) $term->name,
						'slug'    => (string) $term->slug,
					];
				}
				return [ 'terms' => $items ];

			case 'wp_add_post_terms':
				$terms = [];
				foreach ( $args['terms'] as $term ) {
					$terms[] = is_numeric( $term ) ? (int) $term : sanitize_text_field( (string) $term );
				}
				$result = wp_set_post_terms( (int) $args['ID'], $terms, sanitize_key( (string) $args['taxonomy'] ), true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return [ 'ID' => (int) $args['ID'], 'taxonomy' => sanitize_key( (string) $args['taxonomy'] ), 'terms' => $result ];

			case 'wp_get_media':
				$query = new \WP_Query(
					[
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'posts_per_page' => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20,
						'offset'         => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
						's'              => isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '',
					]
				);
				$items = [];
				foreach ( $query->posts as $item ) {
					$items[] = [
						'id'       => (int) $item->ID,
						'title'    => (string) $item->post_title,
						'url'      => wp_get_attachment_url( $item->ID ),
						'mime'     => (string) $item->post_mime_type,
						'alt_text' => (string) get_post_meta( $item->ID, '_wp_attachment_image_alt', true ),
					];
				}
				return [ 'media' => $items, 'count' => count( $items ) ];

			case 'wp_upload_media':
				return $this->upload_media_from_url( $args );

			case 'wp_upload_request':
				$filename = isset( $args['filename'] ) ? sanitize_file_name( (string) $args['filename'] ) : 'upload.bin';
				$title    = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '';
				$alt      = isset( $args['alt'] ) ? sanitize_text_field( (string) $args['alt'] ) : '';
				$desc     = isset( $args['description'] ) ? sanitize_textarea_field( (string) $args['description'] ) : '';
				$expires  = isset( $args['expires_in'] ) ? max( 60, min( 3600, (int) $args['expires_in'] ) ) : 600;
				$token    = wp_generate_password( 24, false, false );
				set_transient(
					$this->upload_prefix . $token,
					[
						'filename'    => $filename,
						'title'       => $title,
						'alt'         => $alt,
						'description' => $desc,
						'user_id'     => (int) $context->user_id,
					],
					$expires
				);
				return [
					'token'       => $token,
					'upload_url'  => rest_url( 'mcp/v1/upload/' . rawurlencode( $token ) ),
					'expires_in'  => $expires,
					'instructions'=> 'Send multipart form with file field: file',
				];

			case 'wp_update_media':
				$attachment_id = (int) $args['attachment_id'];
				$post          = get_post( $attachment_id );
				if ( ! $post || 'attachment' !== $post->post_type ) {
					return new WP_Error( 'openwp_mcp_media_not_found', __( 'Attachment not found.', 'openwp' ) );
				}
				$update = [ 'ID' => $attachment_id ];
				if ( array_key_exists( 'title', $args ) ) {
					$update['post_title'] = sanitize_text_field( (string) $args['title'] );
				}
				if ( array_key_exists( 'caption', $args ) ) {
					$update['post_excerpt'] = sanitize_textarea_field( (string) $args['caption'] );
				}
				if ( array_key_exists( 'description', $args ) ) {
					$update['post_content'] = sanitize_textarea_field( (string) $args['description'] );
				}
				$result = wp_update_post( $update, true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( array_key_exists( 'alt_text', $args ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['alt_text'] ) );
				}
				return [ 'attachment_id' => $attachment_id, 'updated' => true ];

			case 'wp_delete_media':
				$deleted = wp_delete_attachment( (int) $args['attachment_id'], true );
				if ( ! $deleted ) {
					return new WP_Error( 'openwp_mcp_media_delete_failed', __( 'Failed to delete media.', 'openwp' ) );
				}
				return [ 'attachment_id' => (int) $args['attachment_id'], 'deleted' => true ];

			case 'openwp_vision':
				$image_url = esc_url_raw( (string) $args['image_url'] );
				$size      = '';
				if ( '' !== $image_url && function_exists( 'getimagesize' ) ) {
					$meta = @getimagesize( $image_url );
					if ( is_array( $meta ) && isset( $meta[0], $meta[1] ) ) {
						$size = $meta[0] . 'x' . $meta[1];
					}
				}
				return [
					'message'   => 'Vision provider is not configured in OpenWP MCP. Returning metadata-only fallback.',
					'image_url' => $image_url,
					'prompt'    => (string) $args['prompt'],
					'size'      => $size,
				];

			case 'openwp_image':
				return [
					'message' => 'Image generation provider is not configured in OpenWP MCP.',
					'prompt'  => (string) $args['prompt'],
					'size'    => isset( $args['size'] ) ? (string) $args['size'] : '',
					'quality' => isset( $args['quality'] ) ? (string) $args['quality'] : '',
				];
		}

		return new WP_Error( 'openwp_mcp_tool_not_implemented', __( 'Tool is not implemented.', 'openwp' ) );
	}

	/**
	 * Create detailed post snapshot.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $args Args.
	 * @return array<string,mixed>|WP_Error
	 */
	private function post_snapshot( $post_id, $args ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'openwp_mcp_post_not_found', __( 'Post not found.', 'openwp' ) );
		}

		$include = isset( $args['include'] ) && is_array( $args['include'] ) ? $args['include'] : [ 'meta', 'terms', 'thumbnail', 'author' ];
		$exclude = isset( $args['exclude'] ) && is_array( $args['exclude'] ) ? $args['exclude'] : [];

		$post_data = [
			'ID'            => (int) $post->ID,
			'post_title'    => (string) $post->post_title,
			'post_content'  => (string) $post->post_content,
			'post_excerpt'  => (string) $post->post_excerpt,
			'post_status'   => (string) $post->post_status,
			'post_type'     => (string) $post->post_type,
			'post_name'     => (string) $post->post_name,
			'post_date'     => (string) $post->post_date,
			'post_date_gmt' => (string) $post->post_date_gmt,
			'link'          => get_permalink( $post->ID ),
		];

		if ( in_array( 'content', $exclude, true ) ) {
			unset( $post_data['post_content'] );
		}

		$result = [ 'post' => $post_data ];

		if ( in_array( 'meta', $include, true ) ) {
			$result['meta'] = get_post_meta( $post_id );
		}

		if ( in_array( 'terms', $include, true ) ) {
			$terms = [];
			foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
				$tax_terms = wp_get_post_terms( $post_id, $taxonomy );
				if ( is_wp_error( $tax_terms ) ) {
					continue;
				}
				$terms[ $taxonomy ] = array_map(
					static function ( $term ) {
						return [
							'term_id' => (int) $term->term_id,
							'name'    => (string) $term->name,
							'slug'    => (string) $term->slug,
						];
					},
					$tax_terms
				);
			}
			$result['terms'] = $terms;
		}

		if ( in_array( 'thumbnail', $include, true ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			$result['thumbnail'] = $thumbnail_id ? [
				'id'  => (int) $thumbnail_id,
				'url' => wp_get_attachment_url( $thumbnail_id ),
			] : null;
		}

		if ( in_array( 'author', $include, true ) ) {
			$author = get_userdata( (int) $post->post_author );
			$result['author'] = $author ? [
				'ID'           => (int) $author->ID,
				'user_login'   => (string) $author->user_login,
				'display_name' => (string) $author->display_name,
			] : null;
		}

		return $result;
	}

	/**
	 * Upload media from URL.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return array<string,mixed>|WP_Error
	 */
	private function upload_media_from_url( $args ) {
		$file_url = esc_url_raw( (string) $args['file_url'] );
		if ( '' === $file_url || false === wp_http_validate_url( $file_url ) ) {
			return new WP_Error( 'openwp_mcp_media_url_invalid', __( 'Invalid media URL.', 'openwp' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $file_url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$filename = basename( (string) parse_url( $file_url, PHP_URL_PATH ) );
		if ( '' === $filename ) {
			$filename = 'upload-' . time() . '.bin';
		}

		$file = [
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp,
		];

		$post_id    = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$attachment = media_handle_sideload( $file, $post_id, isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : null );
		if ( is_wp_error( $attachment ) ) {
			@unlink( $tmp );
			return $attachment;
		}

		if ( isset( $args['alt_text'] ) ) {
			update_post_meta( (int) $attachment, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['alt_text'] ) );
		}

		return [
			'attachment_id' => (int) $attachment,
			'url'           => wp_get_attachment_url( (int) $attachment ),
		];
	}
}
