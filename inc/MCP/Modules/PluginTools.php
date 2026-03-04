<?php
/**
 * Plugin MCP tools.
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
 * Plugin management MCP tools.
 */
class PluginTools implements ToolModuleInterface {
	/**
	 * Tools cache.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private $tools;

	/**
	 * Return tool map.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_tools() {
		if ( null !== $this->tools ) {
			return $this->tools;
		}

		$all = [
			'wp_list_plugins_detailed' => [ 'level' => 'admin' ],
			'wp_activate_plugin'       => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_deactivate_plugin'     => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_create_plugin'         => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_copy_plugin'           => [ 'level' => 'admin', 'required' => [ 'source_slug', 'new_slug' ] ],
			'wp_rename_plugin'         => [ 'level' => 'admin', 'required' => [ 'old_slug', 'new_slug' ] ],
			'wp_delete_plugin'         => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_plugin_mkdir'          => [ 'level' => 'admin', 'required' => [ 'slug', 'dir' ] ],
			'wp_plugin_list_dir'       => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_plugin_delete_path'    => [ 'level' => 'admin', 'required' => [ 'slug', 'path' ] ],
			'wp_plugin_get_file'       => [ 'level' => 'admin', 'required' => [ 'slug', 'file' ] ],
			'wp_plugin_put_file'       => [ 'level' => 'admin', 'required' => [ 'slug', 'file', 'content' ] ],
			'wp_plugin_alter_file'     => [ 'level' => 'admin', 'required' => [ 'slug', 'file', 'search', 'replace' ] ],
		];

		$tools = [];
		foreach ( $all as $name => $meta ) {
			$tools[ $name ] = [
				'name'        => $name,
				'description' => 'Plugin tool: ' . $name,
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [],
					'required'   => isset( $meta['required'] ) ? $meta['required'] : [],
				],
				'accessLevel' => $meta['level'],
				'category'    => 'AI Engine (Plugins)',
				'annotations' => [
					'readOnlyHint'    => in_array( $name, [ 'wp_list_plugins_detailed', 'wp_plugin_get_file', 'wp_plugin_list_dir' ], true ),
					'destructiveHint' => false !== strpos( $name, 'delete' ) || 'wp_plugin_alter_file' === $name,
					'openWorldHint'   => false,
				],
			];
		}

		// Schema override for wp_create_plugin.
		if ( isset( $tools['wp_create_plugin'] ) ) {
			$tools['wp_create_plugin']['description'] = 'Create a new WordPress plugin. Optionally pass a "files" object {relative_path: content} to write multiple files at once. Also accepts description, version, author for the plugin header.';
			$tools['wp_create_plugin']['inputSchema']['properties'] = [
				'slug'        => [
					'type'        => 'string',
					'description' => 'Plugin slug (directory name).',
				],
				'name'        => [
					'type'        => 'string',
					'description' => 'Display name for the plugin.',
				],
				'description' => [
					'type'        => 'string',
					'description' => 'Plugin description for the header comment.',
				],
				'version'     => [
					'type'        => 'string',
					'description' => 'Plugin version. Defaults to 1.0.0.',
				],
				'author'      => [
					'type'        => 'string',
					'description' => 'Author name for the plugin header.',
				],
				'files'       => [
					'type'                 => 'object',
					'description'          => 'Map of relative file paths to their contents. Example: {"includes/helper.php": "<?php ...", "assets/style.css": "body{}"}. If a key matches the main file ({slug}.php), it overwrites the generated skeleton.',
					'additionalProperties' => [ 'type' => 'string' ],
				],
			];
		}

		$this->tools = $tools;
		return $this->tools;
	}

	/**
	 * Check support.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	public function supports( $tool ) {
		$tools = $this->get_tools();
		return isset( $tools[ $tool ] );
	}

	/**
	 * Execute plugin tool.
	 *
	 * @param string        $tool Tool name.
	 * @param array<string,mixed> $args Args.
	 * @param ActionContext $context Context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute( $tool, $args, ActionContext $context ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		switch ( $tool ) {
			case 'wp_list_plugins_detailed':
				$plugins = get_plugins();
				$items   = [];
				foreach ( $plugins as $file => $plugin ) {
					$slug = dirname( $file );
					if ( '.' === $slug ) {
						$slug = sanitize_title( basename( $file, '.php' ) );
					}
					$items[] = [
						'slug'      => $slug,
						'file'      => $file,
						'name'      => (string) ( $plugin['Name'] ?? '' ),
						'version'   => (string) ( $plugin['Version'] ?? '' ),
						'active'    => is_plugin_active( $file ),
						'editable'  => is_writable( WP_PLUGIN_DIR . '/' . dirname( $file ) ),
					];
				}
				return [ 'plugins' => $items, 'count' => count( $items ) ];

			case 'wp_activate_plugin':
				$plugin_file = $this->resolve_plugin_file_by_slug( (string) $args['slug'] );
				if ( '' === $plugin_file ) {
					return new WP_Error( 'openwp_mcp_plugin_not_found', __( 'Plugin slug not found.', 'openwp' ) );
				}
				$result = activate_plugin( $plugin_file, '', false, true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return [ 'slug' => sanitize_key( (string) $args['slug'] ), 'plugin_file' => $plugin_file, 'active' => true ];

			case 'wp_deactivate_plugin':
				$plugin_file = $this->resolve_plugin_file_by_slug( (string) $args['slug'] );
				if ( '' === $plugin_file ) {
					return new WP_Error( 'openwp_mcp_plugin_not_found', __( 'Plugin slug not found.', 'openwp' ) );
				}
				deactivate_plugins( $plugin_file, false, false );
				return [ 'slug' => sanitize_key( (string) $args['slug'] ), 'plugin_file' => $plugin_file, 'active' => false ];

			case 'wp_create_plugin':
				$slug = sanitize_key( (string) $args['slug'] );
				if ( '' === $slug ) {
					return new WP_Error( 'openwp_mcp_plugin_slug_required', __( 'slug is required.', 'openwp' ) );
				}
				$dir = $this->plugin_dir( $slug );
				if ( is_dir( $dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_exists', __( 'Plugin already exists.', 'openwp' ) );
				}
				if ( ! wp_mkdir_p( $dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_create_failed', __( 'Failed to create plugin directory.', 'openwp' ) );
				}
				$name = isset( $args['name'] ) ? sanitize_text_field( (string) $args['name'] ) : ucwords( str_replace( '-', ' ', $slug ) );
				$main = $dir . $slug . '.php';
				$php  = "<?php\n";
				$php .= "/*\n";
				$php .= 'Plugin Name: ' . $name . "\n";
				$description = isset( $args['description'] ) ? sanitize_text_field( (string) $args['description'] ) : 'Generated by OpenWP MCP';
				$version     = isset( $args['version'] ) ? sanitize_text_field( (string) $args['version'] ) : '1.0.0';
				$author      = isset( $args['author'] ) ? sanitize_text_field( (string) $args['author'] ) : '';
				$php        .= 'Description: ' . $description . "\n";
				$php        .= 'Version: ' . $version . "\n";
				if ( '' !== $author ) {
					$php .= 'Author: ' . $author . "\n";
				}
				$php .= "*/\n";
				$php .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n";
				file_put_contents( $main, $php );

				$files_written = [ basename( $main ) ];
				if ( ! empty( $args['files'] ) && is_array( $args['files'] ) ) {
					foreach ( $args['files'] as $rel_path => $content ) {
						$safe = $this->safe_path( $slug, (string) $rel_path );
						if ( '' === $safe ) {
							continue;
						}
						$parent = dirname( $safe );
						if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
							continue;
						}
						file_put_contents( $safe, (string) $content );
						$files_written[] = (string) $rel_path;
					}
				}

				return [ 'slug' => $slug, 'path' => $dir, 'main_file' => basename( $main ), 'files_written' => array_unique( $files_written ) ];

			case 'wp_copy_plugin':
				$source = sanitize_key( (string) $args['source_slug'] );
				$new    = sanitize_key( (string) $args['new_slug'] );
				if ( '' === $source || '' === $new ) {
					return new WP_Error( 'openwp_mcp_plugin_copy_invalid', __( 'source_slug and new_slug are required.', 'openwp' ) );
				}
				$source_dir = $this->plugin_dir( $source );
				$new_dir    = $this->plugin_dir( $new );
				if ( ! is_dir( $source_dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_source_missing', __( 'Source plugin does not exist.', 'openwp' ) );
				}
				if ( is_dir( $new_dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_target_exists', __( 'Target plugin already exists.', 'openwp' ) );
				}
				if ( ! $this->copy_dir( $source_dir, $new_dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_copy_failed', __( 'Failed to copy plugin.', 'openwp' ) );
				}
				if ( ! empty( $args['new_name'] ) ) {
					$main_file = $new_dir . $new . '.php';
					if ( file_exists( $main_file ) ) {
						$content = file_get_contents( $main_file );
						if ( is_string( $content ) ) {
							$content = preg_replace( '/Plugin Name:\s*(.+)/', 'Plugin Name: ' . sanitize_text_field( (string) $args['new_name'] ), $content, 1 );
							file_put_contents( $main_file, $content );
						}
					}
				}
				return [ 'source_slug' => $source, 'new_slug' => $new, 'path' => $new_dir ];

			case 'wp_rename_plugin':
				$old_slug = sanitize_key( (string) $args['old_slug'] );
				$new_slug = sanitize_key( (string) $args['new_slug'] );
				$old_dir  = $this->plugin_dir( $old_slug );
				$new_dir  = $this->plugin_dir( $new_slug );
				if ( '' === $old_slug || '' === $new_slug ) {
					return new WP_Error( 'openwp_mcp_plugin_rename_invalid', __( 'old_slug and new_slug are required.', 'openwp' ) );
				}
				if ( ! is_dir( $old_dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_old_missing', __( 'Old plugin does not exist.', 'openwp' ) );
				}
				if ( is_dir( $new_dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_new_exists', __( 'New plugin slug already exists.', 'openwp' ) );
				}
				$old_file   = $this->resolve_plugin_file_by_slug( $old_slug );
				$was_active = '' !== $old_file ? is_plugin_active( $old_file ) : false;
				if ( $was_active ) {
					deactivate_plugins( $old_file, false, false );
				}
				if ( ! rename( $old_dir, $new_dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_rename_failed', __( 'Failed to rename plugin directory.', 'openwp' ) );
				}
				$new_main = $new_dir . $old_slug . '.php';
				if ( file_exists( $new_main ) ) {
					rename( $new_main, $new_dir . $new_slug . '.php' );
				}
				$new_file = $this->resolve_plugin_file_by_slug( $new_slug );
				if ( $was_active && '' !== $new_file ) {
					activate_plugin( $new_file, '', false, true );
				}
				return [ 'old_slug' => $old_slug, 'new_slug' => $new_slug, 'active' => $was_active ];

			case 'wp_delete_plugin':
				$plugin_file = $this->resolve_plugin_file_by_slug( (string) $args['slug'] );
				if ( '' === $plugin_file ) {
					return new WP_Error( 'openwp_mcp_plugin_not_found', __( 'Plugin slug not found.', 'openwp' ) );
				}
				if ( is_plugin_active( $plugin_file ) ) {
					deactivate_plugins( $plugin_file, false, false );
				}
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$result = delete_plugins( [ $plugin_file ] );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( ! $result ) {
					return new WP_Error( 'openwp_mcp_plugin_delete_failed', __( 'Failed to delete plugin.', 'openwp' ) );
				}
				return [ 'slug' => sanitize_key( (string) $args['slug'] ), 'deleted' => true ];

			case 'wp_plugin_mkdir':
				$path = $this->safe_path( (string) $args['slug'], (string) $args['dir'] );
				if ( '' === $path ) {
					return new WP_Error( 'openwp_mcp_plugin_path_invalid', __( 'Invalid directory path.', 'openwp' ) );
				}
				if ( ! wp_mkdir_p( $path ) ) {
					return new WP_Error( 'openwp_mcp_plugin_mkdir_failed', __( 'Failed to create directory.', 'openwp' ) );
				}
				return [ 'path' => $path, 'created' => true ];

			case 'wp_plugin_list_dir':
				$dir = isset( $args['dir'] ) ? (string) $args['dir'] : '';
				$path = $this->safe_path( (string) $args['slug'], $dir );
				if ( '' === $path || ! is_dir( $path ) ) {
					return new WP_Error( 'openwp_mcp_plugin_dir_missing', __( 'Directory not found.', 'openwp' ) );
				}
				$items = [];
				$scan  = scandir( $path );
				foreach ( is_array( $scan ) ? $scan : [] as $name ) {
					if ( '.' === $name || '..' === $name ) {
						continue;
					}
					$abs = trailingslashit( $path ) . $name;
					$items[] = [
						'name'     => $name,
						'type'     => is_dir( $abs ) ? 'dir' : 'file',
						'size'     => is_file( $abs ) ? filesize( $abs ) : 0,
						'writable' => is_writable( $abs ),
					];
				}
				return [ 'path' => $path, 'items' => $items, 'count' => count( $items ) ];

			case 'wp_plugin_delete_path':
				$path = $this->safe_path( (string) $args['slug'], (string) $args['path'] );
				if ( '' === $path || ! file_exists( $path ) ) {
					return new WP_Error( 'openwp_mcp_plugin_path_missing', __( 'Path not found.', 'openwp' ) );
				}
				$ok = is_dir( $path ) ? $this->delete_dir( $path ) : unlink( $path );
				if ( ! $ok ) {
					return new WP_Error( 'openwp_mcp_plugin_delete_path_failed', __( 'Failed to delete path.', 'openwp' ) );
				}
				return [ 'path' => $path, 'deleted' => true ];

			case 'wp_plugin_get_file':
				$file = $this->safe_path( (string) $args['slug'], (string) $args['file'] );
				if ( '' === $file || ! is_file( $file ) ) {
					return new WP_Error( 'openwp_mcp_plugin_file_missing', __( 'File not found.', 'openwp' ) );
				}
				$content = file_get_contents( $file );
				if ( false === $content ) {
					return new WP_Error( 'openwp_mcp_plugin_file_read_failed', __( 'Failed to read file.', 'openwp' ) );
				}
				return [ 'file' => $file, 'content' => $content ];

			case 'wp_plugin_put_file':
				$file = $this->safe_path( (string) $args['slug'], (string) $args['file'] );
				if ( '' === $file ) {
					return new WP_Error( 'openwp_mcp_plugin_file_invalid', __( 'Invalid file path.', 'openwp' ) );
				}
				$dir = dirname( $file );
				if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
					return new WP_Error( 'openwp_mcp_plugin_dir_create_failed', __( 'Failed to create directory.', 'openwp' ) );
				}
				$bytes = file_put_contents( $file, (string) $args['content'] );
				if ( false === $bytes ) {
					return new WP_Error( 'openwp_mcp_plugin_file_write_failed', __( 'Failed to write file.', 'openwp' ) );
				}
				return [ 'file' => $file, 'bytes' => (int) $bytes ];

			case 'wp_plugin_alter_file':
				$file = $this->safe_path( (string) $args['slug'], (string) $args['file'] );
				if ( '' === $file || ! is_file( $file ) ) {
					return new WP_Error( 'openwp_mcp_plugin_file_missing', __( 'File not found.', 'openwp' ) );
				}
				$content = file_get_contents( $file );
				if ( false === $content ) {
					return new WP_Error( 'openwp_mcp_plugin_file_read_failed', __( 'Failed to read file.', 'openwp' ) );
				}
				if ( ! empty( $args['regex'] ) ) {
					$updated = preg_replace( (string) $args['search'], (string) $args['replace'], $content, -1, $count );
					if ( null === $updated ) {
						return new WP_Error( 'openwp_mcp_plugin_regex_invalid', __( 'Invalid regex pattern.', 'openwp' ) );
					}
				} else {
					$updated = str_replace( (string) $args['search'], (string) $args['replace'], $content, $count );
				}
				if ( (int) $count > 0 ) {
					file_put_contents( $file, $updated );
				}
				return [ 'file' => $file, 'replacements' => (int) $count ];
		}

		return new WP_Error( 'openwp_mcp_plugin_unknown_tool', __( 'Unknown plugin tool.', 'openwp' ) );
	}

	/**
	 * Resolve plugin directory.
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	private function plugin_dir( $slug ) {
		$slug = sanitize_key( $slug );
		return trailingslashit( WP_PLUGIN_DIR . '/' . $slug );
	}

	/**
	 * Resolve safe path inside plugin directory.
	 *
	 * @param string $slug Plugin slug.
	 * @param string $relative Relative path.
	 * @return string
	 */
	private function safe_path( $slug, $relative ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return '';
		}

		$base = $this->plugin_dir( $slug );
		$rel  = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
		if ( false !== strpos( $rel, '..' ) ) {
			return '';
		}

		$path = '' === $rel ? $base : $base . $rel;
		$norm_base = wp_normalize_path( $base );
		$norm_path = wp_normalize_path( $path );

		if ( 0 !== strpos( $norm_path, $norm_base ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Resolve plugin file by slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	private function resolve_plugin_file_by_slug( $slug ) {
		$slug    = sanitize_key( $slug );
		$plugins = get_plugins();
		foreach ( $plugins as $file => $plugin ) {
			$dirname = dirname( $file );
			if ( '.' === $dirname ) {
				$dirname = sanitize_title( basename( $file, '.php' ) );
			}
			if ( $dirname === $slug ) {
				return $file;
			}
		}

		$candidate = $slug . '/' . $slug . '.php';
		if ( isset( $plugins[ $candidate ] ) ) {
			return $candidate;
		}

		return '';
	}

	/**
	 * Recursive directory copy.
	 *
	 * @param string $src Source.
	 * @param string $dst Destination.
	 * @return bool
	 */
	private function copy_dir( $src, $dst ) {
		if ( ! is_dir( $src ) || ! wp_mkdir_p( $dst ) ) {
			return false;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$target = $dst . substr( $item->getPathname(), strlen( $src ) );
			if ( $item->isDir() ) {
				if ( ! is_dir( $target ) && ! wp_mkdir_p( $target ) ) {
					return false;
				}
			} else {
				if ( ! copy( $item->getPathname(), $target ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Recursive directory delete.
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private function delete_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return true;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$ok = $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
			if ( ! $ok ) {
				return false;
			}
		}

		return rmdir( $dir );
	}
}
