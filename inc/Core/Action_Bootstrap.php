<?php
/**
 * Action registration bootstrap.
 *
 * @package OpenWP\Inc\Core
 */

namespace OpenWP\Inc\Core;

use OpenWP\Inc\Actions\Action_Registry;
use OpenWP\Inc\Actions\Comment_Actions;
use OpenWP\Inc\Actions\Content_Actions;
use OpenWP\Inc\Actions\Database_Actions;
use OpenWP\Inc\Actions\Mcp_Bridge_Action;
use OpenWP\Inc\Actions\Memory_Actions;
use OpenWP\Inc\Actions\Media_Actions;
use OpenWP\Inc\Actions\Option_Actions;
use OpenWP\Inc\Actions\Plugin_Actions;
use OpenWP\Inc\Actions\Taxonomy_Actions;
use OpenWP\Inc\Actions\Theme_Actions;
use OpenWP\Inc\Actions\User_Actions;
use OpenWP\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all supported actions.
 */
class Action_Bootstrap {
	use Get_Instance;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_actions();
	}

	/**
	 * Register action catalog.
	 *
	 * @return void
	 */
	private function register_actions() {
		// -------------------------------------------------------------------------
		// Content
		// -------------------------------------------------------------------------
		$this->register( 'wp_get_posts', [ Content_Actions::class, 'list_posts' ], 'edit_posts', 'low', false, false,
			[
				'properties' => [
					'post_type'   => [ 'type' => 'string', 'default' => 'post' ],
					'post_status' => [ 'type' => 'string', 'default' => 'any' ],
					'per_page'    => [ 'type' => 'integer', 'default' => 10 ],
					'page'        => [ 'type' => 'integer', 'default' => 1 ],
					'search'      => [ 'type' => 'string', 'description' => 'Keyword search across post title and content.' ],
					'orderby'     => [ 'type' => 'string', 'enum' => [ 'date', 'title', 'modified', 'ID', 'menu_order' ], 'default' => 'date' ],
					'order'       => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
				],
				'additionalProperties' => false,
			],
			'openwp/content',
			[
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'ID'           => [ 'type' => 'integer' ],
								'post_title'   => [ 'type' => 'string' ],
								'post_status'  => [ 'type' => 'string' ],
								'post_type'    => [ 'type' => 'string' ],
								'post_date'    => [ 'type' => 'string' ],
								'post_modified' => [ 'type' => 'string' ],
								'guid'         => [ 'type' => 'string' ],
							],
						],
					],
					'total' => [ 'type' => 'integer' ],
					'pages' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_create_post', [ Content_Actions::class, 'create_post' ], 'edit_posts', 'low', true, true,
			[
				'required' => [ 'title', 'content' ],
				'properties' => [
					'title'       => [ 'type' => 'string' ],
					'content'     => [ 'type' => 'string' ],
					'excerpt'     => [ 'type' => 'string' ],
					'status'      => [ 'type' => 'string', 'enum' => [ 'draft', 'pending', 'publish', 'private' ], 'default' => 'draft' ],
					'post_type'   => [ 'type' => 'string', 'default' => 'post' ],
					'post_author' => [ 'type' => 'integer', 'description' => 'User ID of the post author. Defaults to the current user.' ],
					'meta_input'  => [ 'type' => 'object', 'description' => 'Key/value pairs of post meta to set on creation.', 'additionalProperties' => true ],
				],
				'additionalProperties' => false,
			],
			'openwp/content',
			[
				'type'       => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_update_post', [ Content_Actions::class, 'update_post' ], 'edit_posts', 'low', true, false,
			[
				'required' => [ 'post_id' ],
				'properties' => [
					'post_id'     => [ 'type' => 'integer' ],
					'title'       => [ 'type' => 'string' ],
					'content'     => [ 'type' => 'string' ],
					'excerpt'     => [ 'type' => 'string' ],
					'status'      => [ 'type' => 'string' ],
					'post_author' => [ 'type' => 'integer', 'description' => 'Reassign post to a different author user ID.' ],
					'meta_input'  => [ 'type' => 'object', 'description' => 'Key/value pairs of post meta to update.', 'additionalProperties' => true ],
				],
				'additionalProperties' => false,
			],
			'openwp/content',
			[
				'type'       => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'publish_post', [ Content_Actions::class, 'publish_post' ], 'publish_posts', 'low', true, false,
			[
				'required' => [ 'post_id' ],
				'properties' => [ 'post_id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
			'openwp/content',
			[
				'type'       => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_delete_post', [ Content_Actions::class, 'delete_post' ], 'delete_posts', 'low', true, false,
			[
				'required' => [ 'post_id' ],
				'properties' => [ 'post_id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
			'openwp/content',
			[
				'type'       => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'deleted' => [ 'type' => 'boolean' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Taxonomy
		// -------------------------------------------------------------------------
		$this->register( 'wp_get_terms', [ Taxonomy_Actions::class, 'list_terms' ], 'manage_categories', 'low', false, false,
			[
				'properties' => [
					'taxonomy' => [ 'type' => 'string', 'default' => 'category' ],
					'per_page' => [ 'type' => 'integer', 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
			'openwp/taxonomy',
			[
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'term_id'     => [ 'type' => 'integer' ],
								'name'        => [ 'type' => 'string' ],
								'slug'        => [ 'type' => 'string' ],
								'description' => [ 'type' => 'string' ],
								'count'       => [ 'type' => 'integer' ],
							],
						],
					],
				],
			]
		);
		$this->register( 'wp_create_term', [ Taxonomy_Actions::class, 'create_term' ], 'manage_categories', 'low', true, false,
			[
				'required' => [ 'taxonomy', 'name' ],
				'properties' => [
					'taxonomy'    => [ 'type' => 'string' ],
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
			'openwp/taxonomy',
			[
				'type'       => 'object',
				'properties' => [
					'term_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_update_term', [ Taxonomy_Actions::class, 'update_term' ], 'manage_categories', 'low', true, false,
			[
				'required' => [ 'taxonomy', 'term_id' ],
				'properties' => [
					'taxonomy'    => [ 'type' => 'string' ],
					'term_id'     => [ 'type' => 'integer' ],
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
			'openwp/taxonomy',
			[
				'type'       => 'object',
				'properties' => [
					'term_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_delete_term', [ Taxonomy_Actions::class, 'delete_term' ], 'manage_categories', 'low', true, false,
			[
				'required' => [ 'taxonomy', 'term_id' ],
				'properties' => [
					'taxonomy' => [ 'type' => 'string' ],
					'term_id'  => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
			'openwp/taxonomy',
			[
				'type'       => 'object',
				'properties' => [
					'term_id' => [ 'type' => 'integer' ],
					'deleted' => [ 'type' => 'boolean' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Media
		// -------------------------------------------------------------------------
		$this->register( 'wp_get_media', [ Media_Actions::class, 'list_media' ], 'upload_files', 'low', false, false,
			[
				'properties' => [
					'per_page' => [ 'type' => 'integer', 'default' => 20 ],
					'page'     => [ 'type' => 'integer', 'default' => 1 ],
				],
				'additionalProperties' => false,
			],
			'openwp/media',
			[
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'ID'        => [ 'type' => 'integer' ],
								'title'     => [ 'type' => 'string' ],
								'url'       => [ 'type' => 'string' ],
								'mime_type' => [ 'type' => 'string' ],
								'alt_text'  => [ 'type' => 'string' ],
							],
						],
					],
					'total' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_upload_media', [ Media_Actions::class, 'upload_media' ], 'upload_files', 'low', true, false,
			[
				'required' => [ 'file_url' ],
				'properties' => [
					'file_url' => [ 'type' => 'string' ],
					'post_id'  => [ 'type' => 'integer' ],
					'title'    => [ 'type' => 'string' ],
					'alt_text' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
			'openwp/media',
			[
				'type'       => 'object',
				'properties' => [
					'attachment_id' => [ 'type' => 'integer' ],
					'url'           => [ 'type' => 'string' ],
				],
			]
		);
		$this->register( 'wp_update_media', [ Media_Actions::class, 'update_media' ], 'upload_files', 'low', true, false,
			[
				'required' => [ 'attachment_id' ],
				'properties' => [
					'attachment_id' => [ 'type' => 'integer' ],
					'title'         => [ 'type' => 'string' ],
					'caption'       => [ 'type' => 'string' ],
					'description'   => [ 'type' => 'string' ],
					'alt_text'      => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
			'openwp/media',
			[
				'type'       => 'object',
				'properties' => [
					'attachment_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_delete_media', [ Media_Actions::class, 'delete_media' ], 'delete_posts', 'low', true, false,
			[
				'required' => [ 'attachment_id' ],
				'properties' => [ 'attachment_id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
			'openwp/media',
			[
				'type'       => 'object',
				'properties' => [
					'attachment_id' => [ 'type' => 'integer' ],
					'deleted'       => [ 'type' => 'boolean' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Comments
		// -------------------------------------------------------------------------
		$this->register( 'wp_get_comments', [ Comment_Actions::class, 'list_comments' ], 'moderate_comments', 'low', false, false,
			[
				'properties' => [
					'status'   => [ 'type' => 'string', 'default' => 'all' ],
					'per_page' => [ 'type' => 'integer', 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
			'openwp/comments',
			[
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'comment_ID'      => [ 'type' => 'integer' ],
								'comment_content' => [ 'type' => 'string' ],
								'comment_status'  => [ 'type' => 'string' ],
								'comment_author'  => [ 'type' => 'string' ],
								'comment_date'    => [ 'type' => 'string' ],
							],
						],
					],
				],
			]
		);
		$this->register( 'wp_update_comment', [ Comment_Actions::class, 'update_comment' ], 'moderate_comments', 'low', true, false,
			[
				'required' => [ 'comment_id' ],
				'properties' => [
					'comment_id' => [ 'type' => 'integer' ],
					'content'    => [ 'type' => 'string' ],
					'status'     => [ 'type' => 'string', 'enum' => [ 'approve', 'hold', 'spam', 'trash', '1', '0' ] ],
				],
				'additionalProperties' => false,
			],
			'openwp/comments',
			[
				'type'       => 'object',
				'properties' => [
					'comment_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_delete_comment', [ Comment_Actions::class, 'delete_comment' ], 'moderate_comments', 'low', true, false,
			[
				'required' => [ 'comment_id' ],
				'properties' => [ 'comment_id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
			'openwp/comments',
			[
				'type'       => 'object',
				'properties' => [
					'comment_id' => [ 'type' => 'integer' ],
					'deleted'    => [ 'type' => 'boolean' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Users
		// -------------------------------------------------------------------------
		$this->register( 'wp_get_users', [ User_Actions::class, 'list_users' ], 'list_users', 'low', false, false,
			[
				'properties' => [ 'per_page' => [ 'type' => 'integer', 'default' => 50 ] ],
				'additionalProperties' => false,
			],
			'openwp/users',
			[
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'ID'           => [ 'type' => 'integer' ],
								'user_login'   => [ 'type' => 'string' ],
								'user_email'   => [ 'type' => 'string' ],
								'display_name' => [ 'type' => 'string' ],
								'roles'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
							],
						],
					],
				],
			]
		);
		$this->register( 'wp_create_user', [ User_Actions::class, 'create_user' ], 'create_users', 'low', true, false,
			[
				'required' => [ 'user_login', 'user_email' ],
				'properties' => [
					'user_login'   => [ 'type' => 'string' ],
					'user_email'   => [ 'type' => 'string' ],
					'user_pass'    => [ 'type' => 'string' ],
					'role'         => [ 'type' => 'string', 'default' => 'subscriber' ],
					'display_name' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
			'openwp/users',
			[
				'type'       => 'object',
				'properties' => [
					'user_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'wp_update_user', [ User_Actions::class, 'update_user' ], 'edit_users', 'low', true, false,
			[
				'required' => [ 'user_id' ],
				'properties' => [
					'user_id'      => [ 'type' => 'integer' ],
					'user_email'   => [ 'type' => 'string' ],
					'display_name' => [ 'type' => 'string' ],
					'user_pass'    => [ 'type' => 'string' ],
					'role'         => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
			'openwp/users',
			[
				'type'       => 'object',
				'properties' => [
					'user_id' => [ 'type' => 'integer' ],
				],
			]
		);
		$this->register( 'delete_user', [ User_Actions::class, 'delete_user' ], 'delete_users', 'low', true, false,
			[
				// At least one of user_id, user_email, or user_login must be provided.
				// JSON Schema anyOf encodes "at least one identifier required".
				'anyOf' => [
					[ 'required' => [ 'user_id' ] ],
					[ 'required' => [ 'user_email' ] ],
					[ 'required' => [ 'user_login' ] ],
				],
				'properties' => [
					'user_id'             => [ 'type' => 'integer', 'description' => 'User ID. Provide this, user_email, or user_login.' ],
					'user_email'          => [ 'type' => 'string', 'description' => 'User email. Provide this, user_id, or user_login.' ],
					'user_login'          => [ 'type' => 'string', 'description' => 'Username. Provide this, user_id, or user_email.' ],
					'reassign_to_user_id' => [ 'type' => 'integer', 'description' => 'Reassign deleted user\'s posts to this user ID.' ],
				],
				'additionalProperties' => false,
			],
			'openwp/users',
			[
				'type'       => 'object',
				'properties' => [
					'user_id'    => [ 'type' => 'integer' ],
					'user_login' => [ 'type' => 'string' ],
					'user_email' => [ 'type' => 'string' ],
				],
			]
		);
		$this->register( 'set_user_role', [ User_Actions::class, 'set_user_role' ], 'promote_users', 'low', true, false,
			[
				'required' => [ 'user_id', 'role' ],
				'properties' => [
					'user_id' => [ 'type' => 'integer' ],
					'role'    => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
			'openwp/users',
			[
				'type'       => 'object',
				'properties' => [
					'user_id' => [ 'type' => 'integer' ],
					'role'    => [ 'type' => 'string' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Settings (Options)
		// -------------------------------------------------------------------------
		$this->register( 'wp_get_option', [ Option_Actions::class, 'get_option' ], 'manage_options', 'low', false, false,
			[
				'required' => [ 'option_name' ],
				'properties' => [ 'option_name' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/settings',
			[
				'type'       => 'object',
				'properties' => [
					'option_name'  => [ 'type' => 'string' ],
					'option_value' => [
						'type' => [ 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ],
					],
				],
			]
		);
		$this->register( 'wp_update_option', [ Option_Actions::class, 'update_option' ], 'manage_options', 'low', true, false,
			[
				'required' => [ 'option_name', 'option_value' ],
				'properties' => [
					'option_name'  => [ 'type' => 'string' ],
					'option_value' => [ 'type' => 'any' ],
				],
				'additionalProperties' => true,
			],
			'openwp/settings',
			[
				'type'       => 'object',
				'properties' => [
					'updated' => [ 'type' => 'boolean' ],
				],
			]
		);
		$this->register( 'delete_option', [ Option_Actions::class, 'delete_option' ], 'manage_options', 'low', true, false,
			[
				'required' => [ 'option_name' ],
				'properties' => [ 'option_name' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/settings',
			[
				'type'       => 'object',
				'properties' => [
					'deleted' => [ 'type' => 'boolean' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Memory
		// -------------------------------------------------------------------------
		$this->register( 'remember_memory', [ Memory_Actions::class, 'remember_memory' ], 'openwp_manage_settings', 'low', true, false,
			[
				'required' => [ 'type', 'key', 'value' ],
				'properties' => [
					'type'  => [ 'type' => 'string', 'enum' => [ 'preference', 'constraint', 'fact', 'workflow' ] ],
					'key'   => [ 'type' => 'string' ],
					'value' => [ 'type' => 'string' ],
					'tags'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
				'additionalProperties' => false,
			],
			'openwp/memory',
			[
				'type'       => 'object',
				'properties' => [
					'created' => [ 'type' => 'boolean' ],
					'item'    => [ 'type' => 'object' ],
				],
			]
		);
		$this->register( 'forget_memory', [ Memory_Actions::class, 'forget_memory' ], 'openwp_manage_settings', 'low', true, false,
			[
				'required' => [ 'type', 'key' ],
				'properties' => [
					'type' => [ 'type' => 'string', 'enum' => [ 'preference', 'constraint', 'fact', 'workflow' ] ],
					'key'  => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
			'openwp/memory',
			[
				'type'       => 'object',
				'properties' => [
					'deleted' => [ 'type' => 'boolean' ],
					'type'    => [ 'type' => 'string' ],
					'key'     => [ 'type' => 'string' ],
				],
			]
		);
		$this->register( 'list_memory', [ Memory_Actions::class, 'list_memory' ], 'openwp_manage_settings', 'low', false, false,
			[
				'properties' => [
					'type'  => [ 'type' => 'string', 'enum' => [ 'preference', 'constraint', 'fact', 'workflow' ] ],
					'query' => [ 'type' => 'string' ],
					'limit' => [ 'type' => 'integer', 'default' => 50 ],
				],
				'additionalProperties' => false,
			],
			'openwp/memory',
			[
				'type'       => 'object',
				'properties' => [
					'total' => [ 'type' => 'integer' ],
					'items' => [ 'type' => 'array' ],
				],
			]
		);
		$this->register( 'clear_memory', [ Memory_Actions::class, 'clear_memory' ], 'openwp_manage_settings', 'low', true, false,
			[
				'properties'           => [],
				'additionalProperties' => false,
			],
			'openwp/memory',
			[
				'type'       => 'object',
				'properties' => [
					'cleared'       => [ 'type' => 'boolean' ],
					'deleted_count' => [ 'type' => 'integer' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Plugins
		// -------------------------------------------------------------------------
		$this->register( 'wp_list_plugins', [ Plugin_Actions::class, 'list_plugins' ], 'activate_plugins', 'low', false, false,
			[
				'properties' => [],
				'additionalProperties' => false,
			],
			'openwp/plugins',
			[
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'plugin'      => [ 'type' => 'string' ],
								'name'        => [ 'type' => 'string' ],
								'version'     => [ 'type' => 'string' ],
								'active'      => [ 'type' => 'boolean' ],
								'description' => [ 'type' => 'string' ],
							],
						],
					],
				],
			]
		);
		$this->register( 'install_plugin', [ Plugin_Actions::class, 'install_plugin' ], 'install_plugins', 'low', true, false,
			[
				'required' => [ 'slug' ],
				'properties' => [ 'slug' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/plugins',
			[
				'type'       => 'object',
				'properties' => [
					'plugin'    => [ 'type' => 'string' ],
					'installed' => [ 'type' => 'boolean' ],
				],
			]
		);
		$this->register( 'wp_activate_plugin', [ Plugin_Actions::class, 'activate_plugin' ], 'activate_plugins', 'low', true, true,
			[
				'required' => [ 'plugin' ],
				'properties' => [ 'plugin' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/plugins',
			[
				'type'       => 'object',
				'properties' => [
					'plugin'    => [ 'type' => 'string' ],
					'activated' => [ 'type' => 'boolean' ],
				],
			]
		);
		$this->register( 'wp_deactivate_plugin', [ Plugin_Actions::class, 'deactivate_plugin' ], 'activate_plugins', 'low', true, true,
			[
				'required' => [ 'plugin' ],
				'properties' => [ 'plugin' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/plugins',
			[
				'type'       => 'object',
				'properties' => [
					'plugin'      => [ 'type' => 'string' ],
					'deactivated' => [ 'type' => 'boolean' ],
				],
			]
		);
		$this->register( 'update_plugin', [ Plugin_Actions::class, 'update_plugin' ], 'update_plugins', 'low', true, false,
			[
				'required' => [ 'plugin' ],
				'properties' => [ 'plugin' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/plugins',
			[
				'type'       => 'object',
				'properties' => [
					'plugin'  => [ 'type' => 'string' ],
					'updated' => [ 'type' => 'boolean' ],
				],
			]
		);
		$this->register( 'wp_delete_plugin', [ Plugin_Actions::class, 'delete_plugin' ], 'delete_plugins', 'low', true, false,
			[
				'required' => [ 'plugin' ],
				'properties' => [ 'plugin' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/plugins',
			[
				'type'       => 'object',
				'properties' => [
					'plugin'  => [ 'type' => 'string' ],
					'deleted' => [ 'type' => 'boolean' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Themes
		// -------------------------------------------------------------------------
		$this->register( 'wp_list_themes', [ Theme_Actions::class, 'list_themes' ], 'switch_themes', 'low', false, false,
			[
				'properties' => [],
				'additionalProperties' => false,
			],
			'openwp/themes',
			[
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'stylesheet'  => [ 'type' => 'string' ],
								'name'        => [ 'type' => 'string' ],
								'version'     => [ 'type' => 'string' ],
								'active'      => [ 'type' => 'boolean' ],
								'description' => [ 'type' => 'string' ],
							],
						],
					],
				],
			]
		);
		$this->register( 'install_theme', [ Theme_Actions::class, 'install_theme' ], 'install_themes', 'low', true, false,
			[
				'required' => [ 'slug' ],
				'properties' => [ 'slug' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/themes',
			[
				'type'       => 'object',
				'properties' => [
					'stylesheet' => [ 'type' => 'string' ],
					'installed'  => [ 'type' => 'boolean' ],
				],
			]
		);
		$this->register( 'wp_switch_theme', [ Theme_Actions::class, 'switch_theme' ], 'switch_themes', 'low', true, false,
			[
				'required' => [ 'stylesheet' ],
				'properties' => [ 'stylesheet' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/themes',
			[
				'type'       => 'object',
				'properties' => [
					'stylesheet' => [ 'type' => 'string' ],
					'switched'   => [ 'type' => 'boolean' ],
				],
			]
		);
		$this->register( 'update_theme', [ Theme_Actions::class, 'update_theme' ], 'update_themes', 'low', true, false,
			[
				'required' => [ 'stylesheet' ],
				'properties' => [ 'stylesheet' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/themes',
			[
				'type'       => 'object',
				'properties' => [
					'stylesheet' => [ 'type' => 'string' ],
					'updated'    => [ 'type' => 'boolean' ],
				],
			]
		);
		$this->register( 'wp_delete_theme', [ Theme_Actions::class, 'delete_theme' ], 'delete_themes', 'low', true, false,
			[
				'required' => [ 'stylesheet' ],
				'properties' => [ 'stylesheet' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
			'openwp/themes',
			[
				'type'       => 'object',
				'properties' => [
					'stylesheet' => [ 'type' => 'string' ],
					'deleted'    => [ 'type' => 'boolean' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// Database
		// -------------------------------------------------------------------------
		$this->register( 'db_optimize_tables', [ Database_Actions::class, 'db_optimize_tables' ], 'manage_options', 'low', true, false,
			[
				'properties' => [
					'tables' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Table names to optimize. Omit to optimize all tables.' ],
				],
				'additionalProperties' => false,
			],
			'openwp/database',
			[
				'type'       => 'object',
				'properties' => [
					'optimized' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
			]
		);
		$this->register( 'db_repair_tables', [ Database_Actions::class, 'db_repair_tables' ], 'manage_options', 'low', true, false,
			[
				'properties' => [
					'tables' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Table names to repair. Omit to repair all tables.' ],
				],
				'additionalProperties' => false,
			],
			'openwp/database',
			[
				'type'       => 'object',
				'properties' => [
					'repaired' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
			]
		);
		$this->register( 'db_analyze_tables', [ Database_Actions::class, 'db_analyze_tables' ], 'manage_options', 'low', true, false,
			[
				'properties' => [
					'tables' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Table names to analyze. Omit to analyze all tables.' ],
				],
				'additionalProperties' => false,
			],
			'openwp/database',
			[
				'type'       => 'object',
				'properties' => [
					'analyzed' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
			]
		);
		$this->register( 'wp_db_query', [ Database_Actions::class, 'db_query' ], 'manage_options', 'low', true, false,
			[
				'required' => [ 'query' ],
				'properties' => [
					'query' => [ 'type' => 'string', 'description' => 'Raw SQL query. Passed through the SQL guard; only SELECT is permitted without explicit override.' ],
				],
				'additionalProperties' => false,
			],
			'openwp/database',
			[
				'type'       => 'object',
				'properties' => [
					'rows'         => [ 'type' => 'array' ],
					'rows_affected' => [ 'type' => 'integer' ],
				],
			]
		);

		// -------------------------------------------------------------------------
		// MCP Bridge Actions
		// Three risk-stratified bridges that let the agent engine call any MCP tool.
		// The LLM is told which bridge to use per tool via MCP_TOOL_CATALOG in the
		// system prompt. Action_Executor enforces policy/approval/backup as usual.
		// -------------------------------------------------------------------------
		$mcp_bridge_schema = [
			'required'   => [ 'tool', 'args' ],
			'properties' => [
				'tool' => [ 'type' => 'string', 'description' => 'MCP tool name from MCP_TOOL_CATALOG.' ],
				'args' => [ 'type' => 'object', 'description' => 'Tool arguments per the tool\'s inputSchema in MCP_TOOL_CATALOG.', 'additionalProperties' => true ],
			],
			'additionalProperties' => false,
		];

		// Read-only MCP tools (no approval, no backup).
		$this->register(
			'mcp_read_tool',
			[ Mcp_Bridge_Action::class, 'call_mcp_tool' ],
			'edit_posts',
			'low',
			false,
			false,
			$mcp_bridge_schema,
			'openwp/mcp',
			[ 'type' => 'object' ]
		);

		// Write MCP tools (requires approval, no backup).
		$this->register(
			'mcp_write_tool',
			[ Mcp_Bridge_Action::class, 'call_mcp_tool' ],
			'edit_posts',
			'medium',
			true,
			false,
			$mcp_bridge_schema,
			'openwp/mcp',
			[ 'type' => 'object' ]
		);

		// Admin/destructive MCP tools (requires approval + pre-action backup).
		$this->register(
			'mcp_admin_tool',
			[ Mcp_Bridge_Action::class, 'call_mcp_tool' ],
			'manage_options',
			'high',
			true,
			true,
			$mcp_bridge_schema,
			'openwp/mcp',
			[ 'type' => 'object' ]
		);
	}

	/**
	 * Register an action definition.
	 *
	 * @param string              $key             Action key.
	 * @param callable            $callback        Callback.
	 * @param string              $capability      Capability.
	 * @param string              $risk            Risk level.
	 * @param bool                $mutates         Mutating action.
	 * @param bool                $requires_backup Requires pre-action backup.
	 * @param array<string,mixed> $schema          JSON input schema.
	 * @param string              $category        Domain category slug (e.g. 'openwp/content').
	 * @param array<string,mixed> $output_schema   JSON output schema.
	 * @return void
	 */
	private function register( $key, $callback, $capability, $risk, $mutates, $requires_backup, $schema, $category = '', $output_schema = [] ) {
		Action_Registry::register(
			$key,
			[
				'callback'        => $callback,
				'capability'      => $capability,
				'risk'            => $risk,
				'mutates'         => (bool) $mutates,
				'requires_backup' => (bool) $requires_backup,
				'schema'          => $schema,
				'category'        => $category,
				'output_schema'   => $output_schema,
			]
		);
	}
}
