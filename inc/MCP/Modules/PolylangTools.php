<?php
/**
 * Polylang MCP tools.
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
 * Polylang module.
 */
class PolylangTools implements ToolModuleInterface {
	/**
	 * Tools cache.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private $tools;

	/**
	 * Return tools map.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_tools() {
		if ( null !== $this->tools ) {
			return $this->tools;
		}

		$all = [
			'pll_get_languages'                => [ 'level' => 'read' ],
			'pll_get_post_language'            => [ 'level' => 'read',  'required' => [ 'post_id' ] ],
			'pll_set_post_language'            => [ 'level' => 'write', 'required' => [ 'post_id', 'lang' ] ],
			'pll_get_post_translations'        => [ 'level' => 'read',  'required' => [ 'post_id' ] ],
			'pll_link_translations'            => [ 'level' => 'write', 'required' => [ 'translations' ] ],
			'pll_get_term_translations'        => [ 'level' => 'read',  'required' => [ 'term_id' ] ],
			'pll_translate_term'               => [ 'level' => 'read',  'required' => [ 'term_id', 'lang' ] ],
			'pll_get_posts'                    => [ 'level' => 'read',  'required' => [ 'lang' ] ],
			'pll_get_posts_missing_translation'=> [ 'level' => 'read',  'required' => [ 'source_lang', 'target_lang' ] ],
			'pll_create_translation'           => [ 'level' => 'write', 'required' => [ 'source_post_id', 'target_lang', 'title' ] ],
			'pll_translation_status'           => [ 'level' => 'read' ],
		];

		$tools = [];
		foreach ( $all as $name => $meta ) {
			$tools[ $name ] = [
				'name'        => $name,
				'description' => 'Polylang tool: ' . $name,
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [],
					'required'   => isset( $meta['required'] ) ? $meta['required'] : [],
				],
				'accessLevel' => $meta['level'],
				'category'    => 'AI Engine (Polylang)',
				'annotations' => [
					'readOnlyHint'    => 'read' === $meta['level'],
					'destructiveHint' => false,
					'openWorldHint'   => false,
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
	 * Execute Polylang tool.
	 *
	 * @param string        $tool Tool.
	 * @param array<string,mixed> $args Args.
	 * @param ActionContext $context Context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute( $tool, $args, ActionContext $context ) {
		if ( ! function_exists( 'pll_get_post_language' ) ) {
			return new WP_Error( 'openwp_mcp_polylang_missing', __( 'Polylang is not active.', 'openwp' ) );
		}

		switch ( $tool ) {
			case 'pll_get_languages':
				return [ 'languages' => $this->languages() ];

			case 'pll_get_post_language':
				$post_id = (int) $args['post_id'];
				if ( ! get_post( $post_id ) ) {
					return new WP_Error( 'openwp_mcp_post_not_found', __( 'Post not found.', 'openwp' ) );
				}
				return [ 'post_id' => $post_id, 'language' => pll_get_post_language( $post_id ) ?: null ];

			case 'pll_set_post_language':
				$post_id = (int) $args['post_id'];
				$lang    = sanitize_text_field( (string) $args['lang'] );
				if ( ! get_post( $post_id ) ) {
					return new WP_Error( 'openwp_mcp_post_not_found', __( 'Post not found.', 'openwp' ) );
				}
				if ( ! $this->language_exists( $lang ) ) {
					return new WP_Error( 'openwp_mcp_lang_invalid', __( 'Invalid language.', 'openwp' ) );
				}
				pll_set_post_language( $post_id, $lang );
				return [ 'post_id' => $post_id, 'language' => $lang, 'updated' => true ];

			case 'pll_get_post_translations':
				$post_id = (int) $args['post_id'];
				if ( ! get_post( $post_id ) ) {
					return new WP_Error( 'openwp_mcp_post_not_found', __( 'Post not found.', 'openwp' ) );
				}
				$translations = pll_get_post_translations( $post_id );
				$items = [];
				foreach ( is_array( $translations ) ? $translations : [] as $lang => $translated_id ) {
					$post = get_post( (int) $translated_id );
					$items[ $lang ] = [
						'post_id' => (int) $translated_id,
						'title'   => $post ? (string) $post->post_title : null,
						'status'  => $post ? (string) $post->post_status : null,
					];
				}
				return [ 'source_post_id' => $post_id, 'translations' => $items ];

			case 'pll_link_translations':
				$translations = $args['translations'];
				if ( is_string( $translations ) ) {
					$translations = json_decode( $translations, true );
				}
				if ( ! is_array( $translations ) || empty( $translations ) ) {
					return new WP_Error( 'openwp_mcp_translations_invalid', __( 'translations must be a language=>post_id map.', 'openwp' ) );
				}
				foreach ( $translations as $lang => $post_id ) {
					if ( ! $this->language_exists( (string) $lang ) ) {
						return new WP_Error( 'openwp_mcp_lang_invalid', sprintf( __( 'Invalid language: %s', 'openwp' ), $lang ) );
					}
					if ( ! get_post( (int) $post_id ) ) {
						return new WP_Error( 'openwp_mcp_post_not_found', sprintf( __( 'Post not found: %d', 'openwp' ), (int) $post_id ) );
					}
				}
				pll_save_post_translations( $translations );
				return [ 'linked' => $translations, 'updated' => true ];

			case 'pll_get_term_translations':
				$term_id = (int) $args['term_id'];
				if ( ! get_term( $term_id ) ) {
					return new WP_Error( 'openwp_mcp_term_not_found', __( 'Term not found.', 'openwp' ) );
				}
				$translations = pll_get_term_translations( $term_id );
				$items = [];
				foreach ( is_array( $translations ) ? $translations : [] as $lang => $translated_id ) {
					$term = get_term( (int) $translated_id );
					$items[ $lang ] = [
						'term_id'  => (int) $translated_id,
						'name'     => $term ? (string) $term->name : null,
						'taxonomy' => $term ? (string) $term->taxonomy : null,
					];
				}
				return [ 'source_term_id' => $term_id, 'translations' => $items ];

			case 'pll_translate_term':
				$term_id = (int) $args['term_id'];
				$lang    = sanitize_text_field( (string) $args['lang'] );
				if ( ! get_term( $term_id ) ) {
					return new WP_Error( 'openwp_mcp_term_not_found', __( 'Term not found.', 'openwp' ) );
				}
				if ( ! $this->language_exists( $lang ) ) {
					return new WP_Error( 'openwp_mcp_lang_invalid', __( 'Invalid language.', 'openwp' ) );
				}
				$translated_id = pll_get_term( $term_id, $lang );
				$term = $translated_id ? get_term( $translated_id ) : null;
				return [
					'term_id'            => $term_id,
					'target_lang'        => $lang,
					'translated_term_id' => $translated_id ? (int) $translated_id : null,
					'name'               => $term ? (string) $term->name : null,
				];

			case 'pll_get_posts':
				$lang = array_key_exists( 'lang', $args ) ? (string) $args['lang'] : '';
				if ( '' !== $lang && ! $this->language_exists( $lang ) ) {
					return new WP_Error( 'openwp_mcp_lang_invalid', __( 'Invalid language.', 'openwp' ) );
				}
				$query = [
					'post_type'      => isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'post',
					'post_status'    => isset( $args['post_status'] ) ? sanitize_key( (string) $args['post_status'] ) : 'publish',
					'posts_per_page' => isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 10,
					'offset'         => isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0,
					'orderby'        => isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'date',
					'order'          => isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC',
					'lang'           => $lang,
				];
				$wpq = new \WP_Query( $query );
				$items = [];
				foreach ( $wpq->posts as $post ) {
					$items[] = [
						'ID'       => (int) $post->ID,
						'title'    => (string) $post->post_title,
						'status'   => (string) $post->post_status,
						'language' => pll_get_post_language( $post->ID ),
					];
				}
				return [ 'posts' => $items, 'count' => count( $items ) ];

			case 'pll_get_posts_missing_translation':
				$source_lang = sanitize_text_field( (string) $args['source_lang'] );
				$target_lang = sanitize_text_field( (string) $args['target_lang'] );
				if ( ! $this->language_exists( $source_lang ) || ! $this->language_exists( $target_lang ) ) {
					return new WP_Error( 'openwp_mcp_lang_invalid', __( 'Invalid source or target language.', 'openwp' ) );
				}
				$wpq = new \WP_Query(
					[
						'post_type'      => isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'post',
						'post_status'    => 'any',
						'posts_per_page' => isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 20,
						'lang'           => $source_lang,
					]
				);
				$items = [];
				foreach ( $wpq->posts as $post ) {
					$translation_id = pll_get_post( $post->ID, $target_lang );
					if ( ! $translation_id ) {
						$items[] = [
							'source_post_id' => (int) $post->ID,
							'title'          => (string) $post->post_title,
							'source_lang'    => $source_lang,
							'target_lang'    => $target_lang,
						];
					}
				}
				return [ 'items' => $items, 'count' => count( $items ) ];

			case 'pll_create_translation':
				$source_post_id = (int) $args['source_post_id'];
				$target_lang    = sanitize_text_field( (string) $args['target_lang'] );
				$source         = get_post( $source_post_id );
				if ( ! $source ) {
					return new WP_Error( 'openwp_mcp_post_not_found', __( 'Source post not found.', 'openwp' ) );
				}
				if ( ! $this->language_exists( $target_lang ) ) {
					return new WP_Error( 'openwp_mcp_lang_invalid', __( 'Invalid target language.', 'openwp' ) );
				}
				$postarr = [
					'post_type'    => $source->post_type,
					'post_status'  => isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'draft',
					'post_title'   => sanitize_text_field( (string) $args['title'] ),
					'post_content' => isset( $args['content'] ) ? wp_kses_post( (string) $args['content'] ) : (string) $source->post_content,
					'post_excerpt' => isset( $args['excerpt'] ) ? sanitize_textarea_field( (string) $args['excerpt'] ) : (string) $source->post_excerpt,
				];
				if ( ! empty( $args['post_date'] ) ) {
					$postarr['post_date'] = sanitize_text_field( (string) $args['post_date'] );
				}
				$translation_id = wp_insert_post( $postarr, true );
				if ( is_wp_error( $translation_id ) ) {
					return $translation_id;
				}
				pll_set_post_language( (int) $translation_id, $target_lang );
				$translations = pll_get_post_translations( $source_post_id );
				if ( ! is_array( $translations ) ) {
					$translations = [];
				}
				$source_lang = pll_get_post_language( $source_post_id );
				if ( $source_lang ) {
					$translations[ $source_lang ] = $source_post_id;
				}
				$translations[ $target_lang ] = (int) $translation_id;
				pll_save_post_translations( $translations );

				if ( ! empty( $args['copy_featured_image'] ) ) {
					$thumb = get_post_thumbnail_id( $source_post_id );
					if ( $thumb ) {
						set_post_thumbnail( (int) $translation_id, (int) $thumb );
					}
				}
				if ( ! isset( $args['translate_terms'] ) || ! empty( $args['translate_terms'] ) ) {
					foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
						$term_ids = wp_get_post_terms( $source_post_id, $taxonomy, [ 'fields' => 'ids' ] );
						if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) ) {
							continue;
						}
						$translated = [];
						foreach ( $term_ids as $term_id ) {
							$target_term = function_exists( 'pll_get_term' ) ? pll_get_term( (int) $term_id, $target_lang ) : 0;
							if ( $target_term ) {
								$translated[] = (int) $target_term;
							}
						}
						if ( ! empty( $translated ) ) {
							wp_set_post_terms( (int) $translation_id, $translated, $taxonomy, false );
						}
					}
				}

				return [
					'source_post_id'      => $source_post_id,
					'translation_post_id' => (int) $translation_id,
					'target_lang'         => $target_lang,
					'linked'              => true,
				];

			case 'pll_translation_status':
				$post_type = isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'post';
				$languages = $this->languages();
				$coverage  = [];
				$max_count = 0;
				foreach ( $languages as $lang ) {
					$wpq = new \WP_Query(
						[
							'post_type'      => $post_type,
							'post_status'    => 'any',
							'posts_per_page' => 1,
							'fields'         => 'ids',
							'lang'           => (string) $lang['slug'],
						]
					);
					$count = (int) $wpq->found_posts;
					if ( $count > $max_count ) {
						$max_count = $count;
					}
					$coverage[] = [
						'lang'  => (string) $lang['slug'],
						'name'  => (string) $lang['name'],
						'count' => $count,
					];
				}
				foreach ( $coverage as &$row ) {
					$row['coverage_pct'] = $max_count > 0 ? round( ( (int) $row['count'] / $max_count ) * 100, 2 ) : 0;
				}
				return [ 'post_type' => $post_type, 'coverage' => $coverage ];
		}

		return new WP_Error( 'openwp_mcp_polylang_unknown_tool', __( 'Unknown Polylang tool.', 'openwp' ) );
	}

	/**
	 * Read language list.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function languages() {
		$languages = [];
		if ( function_exists( 'PLL' ) && PLL() && isset( PLL()->model ) ) {
			$pll_languages = PLL()->model->get_languages_list();
			foreach ( $pll_languages as $lang ) {
				$languages[] = [
					'slug'       => (string) $lang->slug,
					'name'       => (string) $lang->name,
					'locale'     => (string) $lang->locale,
					'is_default' => (bool) $lang->is_default,
					'flag_url'   => (string) $lang->flag_url,
				];
			}
		} elseif ( function_exists( 'pll_languages_list' ) ) {
			foreach ( pll_languages_list() as $slug ) {
				$languages[] = [
					'slug'       => (string) $slug,
					'name'       => (string) $slug,
					'locale'     => '',
					'is_default' => false,
					'flag_url'   => '',
				];
			}
		}

		return $languages;
	}

	/**
	 * Validate language slug.
	 *
	 * @param string $lang Language slug.
	 * @return bool
	 */
	private function language_exists( $lang ) {
		$lang = sanitize_text_field( $lang );
		if ( '' === $lang ) {
			return false;
		}
		foreach ( $this->languages() as $language ) {
			if ( isset( $language['slug'] ) && $language['slug'] === $lang ) {
				return true;
			}
		}
		return false;
	}
}
