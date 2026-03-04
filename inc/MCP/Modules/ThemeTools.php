<?php
/**
 * Theme MCP tools.
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
 * Theme management MCP tools.
 */
class ThemeTools implements ToolModuleInterface {
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
			'wp_list_themes'          => [ 'level' => 'admin' ],
			'wp_switch_theme'         => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_create_theme'         => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_copy_theme'           => [ 'level' => 'admin', 'required' => [ 'source_slug', 'new_slug' ] ],
			'wp_rename_theme'         => [ 'level' => 'admin', 'required' => [ 'old_slug', 'new_slug' ] ],
			'wp_delete_theme'         => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_theme_mkdir'          => [ 'level' => 'admin', 'required' => [ 'slug', 'dir' ] ],
			'wp_theme_list_dir'       => [ 'level' => 'admin', 'required' => [ 'slug' ] ],
			'wp_theme_delete_path'    => [ 'level' => 'admin', 'required' => [ 'slug', 'path' ] ],
			'wp_theme_get_file'       => [ 'level' => 'admin', 'required' => [ 'slug', 'file' ] ],
			'wp_theme_put_file'       => [ 'level' => 'admin', 'required' => [ 'slug', 'file', 'content' ] ],
			'wp_theme_alter_file'     => [ 'level' => 'admin', 'required' => [ 'slug', 'file', 'search', 'replace' ] ],
			'wp_theme_set_screenshot' => [ 'level' => 'admin', 'required' => [ 'slug', 'source' ] ],
		];

		$tools = [];
		foreach ( $all as $name => $meta ) {
			$tools[ $name ] = [
				'name'        => $name,
				'description' => 'Theme tool: ' . $name,
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [],
					'required'   => isset( $meta['required'] ) ? $meta['required'] : [],
				],
				'accessLevel' => $meta['level'],
				'category'    => 'AI Engine (Themes)',
				'annotations' => [
					'readOnlyHint'    => in_array( $name, [ 'wp_list_themes', 'wp_theme_get_file', 'wp_theme_list_dir' ], true ),
					'destructiveHint' => false !== strpos( $name, 'delete' ) || 'wp_theme_alter_file' === $name,
					'openWorldHint'   => false,
				],
			];
		}

		// Schema override for wp_create_theme.
		if ( isset( $tools['wp_create_theme'] ) ) {
			$tools['wp_create_theme']['description'] = 'Create a new WordPress theme. Optionally pass a "files" object {relative_path: content} to write multiple files at once. Also accepts description, version, author for the theme header.';
			$tools['wp_create_theme']['inputSchema']['properties'] = [
				'slug'        => [
					'type'        => 'string',
					'description' => 'Theme slug (directory name).',
				],
				'name'        => [
					'type'        => 'string',
					'description' => 'Display name for the theme.',
				],
				'description' => [
					'type'        => 'string',
					'description' => 'Theme description for the style.css header.',
				],
				'version'     => [
					'type'        => 'string',
					'description' => 'Theme version. Defaults to 1.0.0.',
				],
				'author'      => [
					'type'        => 'string',
					'description' => 'Author name for the theme header.',
				],
				'files'       => [
					'type'                 => 'object',
					'description'          => 'Map of relative file paths to their contents. Example: {"functions.php": "<?php ...", "header.php": "..."}. If a key matches style.css or index.php, it overwrites the generated default.',
					'additionalProperties' => [ 'type' => 'string' ],
				],
			];
		}

		$this->tools = $tools;
		return $this->tools;
	}

	/**
	 * Supports tool.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	public function supports( $tool ) {
		$tools = $this->get_tools();
		return isset( $tools[ $tool ] );
	}

	/**
	 * Execute theme tool.
	 *
	 * @param string        $tool Tool name.
	 * @param array<string,mixed> $args Args.
	 * @param ActionContext $context Context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute( $tool, $args, ActionContext $context ) {
		switch ( $tool ) {
			case 'wp_list_themes':
				$themes = wp_get_themes();
				$active = wp_get_theme()->get_stylesheet();
				$items  = [];
				foreach ( $themes as $slug => $theme ) {
					$items[] = [
						'slug'      => (string) $slug,
						'name'      => (string) $theme->get( 'Name' ),
						'version'   => (string) $theme->get( 'Version' ),
						'author'    => (string) $theme->get( 'Author' ),
						'active'    => $active === $slug,
						'editable'  => is_writable( $this->theme_dir( $slug ) ),
					];
				}
				return [ 'themes' => $items, 'count' => count( $items ) ];

			case 'wp_switch_theme':
				$slug = sanitize_key( (string) $args['slug'] );
				$theme = wp_get_theme( $slug );
				if ( ! $theme->exists() ) {
					return new WP_Error( 'openwp_mcp_theme_not_found', __( 'Theme not found.', 'openwp' ) );
				}
				switch_theme( $slug );
				return [ 'slug' => $slug, 'active' => wp_get_theme()->get_stylesheet() === $slug ];

			case 'wp_create_theme':
				$slug = sanitize_key( (string) $args['slug'] );
				if ( '' === $slug ) {
					return new WP_Error( 'openwp_mcp_theme_slug_required', __( 'slug is required.', 'openwp' ) );
				}
				$dir = $this->theme_dir( $slug );
				if ( is_dir( $dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_exists', __( 'Theme already exists.', 'openwp' ) );
				}
				if ( ! wp_mkdir_p( $dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_create_failed', __( 'Failed to create theme directory.', 'openwp' ) );
				}
				$name = isset( $args['name'] ) ? sanitize_text_field( (string) $args['name'] ) : ucwords( str_replace( '-', ' ', $slug ) );
				$style  = "/*\n";
				$style .= 'Theme Name: ' . $name . "\n";
				$description = isset( $args['description'] ) ? sanitize_text_field( (string) $args['description'] ) : 'Generated by OpenWP MCP';
				$version     = isset( $args['version'] ) ? sanitize_text_field( (string) $args['version'] ) : '1.0.0';
				$author      = isset( $args['author'] ) ? sanitize_text_field( (string) $args['author'] ) : '';
				$style      .= 'Description: ' . $description . "\n";
				$style      .= 'Version: ' . $version . "\n";
				if ( '' !== $author ) {
					$style .= 'Author: ' . $author . "\n";
				}
				$style .= "*/\n";
				file_put_contents( $dir . 'style.css', $style );
				file_put_contents( $dir . 'index.php', "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\nget_header();\n?>\n<main id=\"primary\">\n\t<?php while ( have_posts() ) : the_post(); ?>\n\t\t<h1><?php the_title(); ?></h1>\n\t\t<?php the_content(); ?>\n\t<?php endwhile; ?>\n</main>\n<?php get_footer();\n" );

				$files_written = [ 'style.css', 'index.php' ];
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

				return [ 'slug' => $slug, 'path' => $dir, 'files_written' => array_unique( $files_written ) ];

			case 'wp_copy_theme':
				$source = sanitize_key( (string) $args['source_slug'] );
				$new    = sanitize_key( (string) $args['new_slug'] );
				if ( '' === $source || '' === $new ) {
					return new WP_Error( 'openwp_mcp_theme_copy_invalid', __( 'source_slug and new_slug are required.', 'openwp' ) );
				}
				$source_dir = $this->theme_dir( $source );
				$new_dir    = $this->theme_dir( $new );
				if ( ! is_dir( $source_dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_source_missing', __( 'Source theme does not exist.', 'openwp' ) );
				}
				if ( is_dir( $new_dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_target_exists', __( 'Target theme already exists.', 'openwp' ) );
				}
				if ( ! $this->copy_dir( $source_dir, $new_dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_copy_failed', __( 'Failed to copy theme.', 'openwp' ) );
				}
				if ( ! empty( $args['new_name'] ) ) {
					$style = $new_dir . 'style.css';
					if ( file_exists( $style ) ) {
						$content = file_get_contents( $style );
						if ( is_string( $content ) ) {
							$content = preg_replace( '/Theme Name:\s*(.+)/', 'Theme Name: ' . sanitize_text_field( (string) $args['new_name'] ), $content, 1 );
							file_put_contents( $style, $content );
						}
					}
				}
				return [ 'source_slug' => $source, 'new_slug' => $new, 'path' => $new_dir ];

			case 'wp_rename_theme':
				$old_slug = sanitize_key( (string) $args['old_slug'] );
				$new_slug = sanitize_key( (string) $args['new_slug'] );
				$old_dir  = $this->theme_dir( $old_slug );
				$new_dir  = $this->theme_dir( $new_slug );
				if ( '' === $old_slug || '' === $new_slug ) {
					return new WP_Error( 'openwp_mcp_theme_rename_invalid', __( 'old_slug and new_slug are required.', 'openwp' ) );
				}
				if ( ! is_dir( $old_dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_old_missing', __( 'Old theme does not exist.', 'openwp' ) );
				}
				if ( is_dir( $new_dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_new_exists', __( 'New theme slug already exists.', 'openwp' ) );
				}
				$active = wp_get_theme()->get_stylesheet();
				if ( ! rename( $old_dir, $new_dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_rename_failed', __( 'Failed to rename theme.', 'openwp' ) );
				}
				if ( $active === $old_slug ) {
					switch_theme( $new_slug );
				}
				return [ 'old_slug' => $old_slug, 'new_slug' => $new_slug, 'active' => wp_get_theme()->get_stylesheet() === $new_slug ];

			case 'wp_delete_theme':
				$slug = sanitize_key( (string) $args['slug'] );
				if ( wp_get_theme()->get_stylesheet() === $slug ) {
					$default_theme = WP_DEFAULT_THEME;
					if ( ! wp_get_theme( $default_theme )->exists() ) {
						$themes = wp_get_themes();
						foreach ( array_keys( $themes ) as $candidate ) {
							if ( $candidate !== $slug ) {
								$default_theme = $candidate;
								break;
							}
						}
					}
					switch_theme( $default_theme );
				}
				$result = delete_theme( $slug );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( false === $result ) {
					return new WP_Error( 'openwp_mcp_theme_delete_failed', __( 'Failed to delete theme.', 'openwp' ) );
				}
				return [ 'slug' => $slug, 'deleted' => true ];

			case 'wp_theme_mkdir':
				$path = $this->safe_path( (string) $args['slug'], (string) $args['dir'] );
				if ( '' === $path ) {
					return new WP_Error( 'openwp_mcp_theme_path_invalid', __( 'Invalid directory path.', 'openwp' ) );
				}
				if ( ! wp_mkdir_p( $path ) ) {
					return new WP_Error( 'openwp_mcp_theme_mkdir_failed', __( 'Failed to create directory.', 'openwp' ) );
				}
				return [ 'path' => $path, 'created' => true ];

			case 'wp_theme_list_dir':
				$dir  = isset( $args['dir'] ) ? (string) $args['dir'] : '';
				$path = $this->safe_path( (string) $args['slug'], $dir );
				if ( '' === $path || ! is_dir( $path ) ) {
					return new WP_Error( 'openwp_mcp_theme_dir_missing', __( 'Directory not found.', 'openwp' ) );
				}
				$items = [];
				$scan = scandir( $path );
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

			case 'wp_theme_delete_path':
				$path = $this->safe_path( (string) $args['slug'], (string) $args['path'] );
				if ( '' === $path || ! file_exists( $path ) ) {
					return new WP_Error( 'openwp_mcp_theme_path_missing', __( 'Path not found.', 'openwp' ) );
				}
				$ok = is_dir( $path ) ? $this->delete_dir( $path ) : unlink( $path );
				if ( ! $ok ) {
					return new WP_Error( 'openwp_mcp_theme_delete_path_failed', __( 'Failed to delete path.', 'openwp' ) );
				}
				return [ 'path' => $path, 'deleted' => true ];

			case 'wp_theme_get_file':
				$file = $this->safe_path( (string) $args['slug'], (string) $args['file'] );
				if ( '' === $file || ! is_file( $file ) ) {
					return new WP_Error( 'openwp_mcp_theme_file_missing', __( 'File not found.', 'openwp' ) );
				}
				$content = file_get_contents( $file );
				if ( false === $content ) {
					return new WP_Error( 'openwp_mcp_theme_file_read_failed', __( 'Failed to read file.', 'openwp' ) );
				}
				return [ 'file' => $file, 'content' => $content ];

			case 'wp_theme_put_file':
				$file = $this->safe_path( (string) $args['slug'], (string) $args['file'] );
				if ( '' === $file ) {
					return new WP_Error( 'openwp_mcp_theme_file_invalid', __( 'Invalid file path.', 'openwp' ) );
				}
				$dir = dirname( $file );
				if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
					return new WP_Error( 'openwp_mcp_theme_dir_create_failed', __( 'Failed to create directory.', 'openwp' ) );
				}
				$bytes = file_put_contents( $file, (string) $args['content'] );
				if ( false === $bytes ) {
					return new WP_Error( 'openwp_mcp_theme_file_write_failed', __( 'Failed to write file.', 'openwp' ) );
				}
				return [ 'file' => $file, 'bytes' => (int) $bytes ];

			case 'wp_theme_alter_file':
				$file = $this->safe_path( (string) $args['slug'], (string) $args['file'] );
				if ( '' === $file || ! is_file( $file ) ) {
					return new WP_Error( 'openwp_mcp_theme_file_missing', __( 'File not found.', 'openwp' ) );
				}
				$content = file_get_contents( $file );
				if ( false === $content ) {
					return new WP_Error( 'openwp_mcp_theme_file_read_failed', __( 'Failed to read file.', 'openwp' ) );
				}
				if ( ! empty( $args['regex'] ) ) {
					$updated = preg_replace( (string) $args['search'], (string) $args['replace'], $content, -1, $count );
					if ( null === $updated ) {
						return new WP_Error( 'openwp_mcp_theme_regex_invalid', __( 'Invalid regex pattern.', 'openwp' ) );
					}
				} else {
					$updated = str_replace( (string) $args['search'], (string) $args['replace'], $content, $count );
				}
				if ( (int) $count > 0 ) {
					file_put_contents( $file, $updated );
				}
				return [ 'file' => $file, 'replacements' => (int) $count ];

			case 'wp_theme_set_screenshot':
				$slug   = sanitize_key( (string) $args['slug'] );
				$source = (string) $args['source'];
				$target = $this->theme_dir( $slug ) . 'screenshot.png';
				if ( ! is_dir( $this->theme_dir( $slug ) ) ) {
					return new WP_Error( 'openwp_mcp_theme_not_found', __( 'Theme not found.', 'openwp' ) );
				}
				$copied = false;
				if ( wp_http_validate_url( $source ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					$tmp = download_url( esc_url_raw( $source ) );
					if ( is_wp_error( $tmp ) ) {
						return $tmp;
					}
					$copied = copy( $tmp, $target );
					@unlink( $tmp );
				} else {
					$path = wp_normalize_path( (string) $source );
					if ( is_file( $path ) && is_readable( $path ) ) {
						$copied = copy( $path, $target );
					}
				}
				if ( ! $copied ) {
					return new WP_Error( 'openwp_mcp_theme_screenshot_failed', __( 'Failed to set screenshot.', 'openwp' ) );
				}
				return [ 'slug' => $slug, 'screenshot' => $target, 'updated' => true ];
		}

		return new WP_Error( 'openwp_mcp_theme_unknown_tool', __( 'Unknown theme tool.', 'openwp' ) );
	}

	/**
	 * Theme directory.
	 *
	 * @param string $slug Theme slug.
	 * @return string
	 */
	private function theme_dir( $slug ) {
		$slug = sanitize_key( $slug );
		return trailingslashit( get_theme_root() . '/' . $slug );
	}

	/**
	 * Resolve safe path in theme.
	 *
	 * @param string $slug Theme slug.
	 * @param string $relative Relative path.
	 * @return string
	 */
	private function safe_path( $slug, $relative ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return '';
		}

		$base = $this->theme_dir( $slug );
		$rel  = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
		if ( false !== strpos( $rel, '..' ) ) {
			return '';
		}

		$path = '' === $rel ? $base : $base . $rel;
		if ( 0 !== strpos( wp_normalize_path( $path ), wp_normalize_path( $base ) ) ) {
			return '';
		}
		return $path;
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
