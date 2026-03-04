<?php
/**
 * Sitewide chatbot asset bootstrap.
 *
 * @package OpenWP\Inc\Frontend
 */

namespace OpenWP\Inc\Frontend;

use OpenWP\Inc\Core\Settings;
use OpenWP\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues sitewide chatbot assets on frontend and wp-admin.
 */
class Sitewide_Chatbot {
	use Get_Instance;

	/**
	 * Keyword used to request page context from the widget.
	 *
	 * @var string
	 */
	private const CONTEXT_KEYWORD = '@page';

	/**
	 * Keyword used to explicitly request memory recall.
	 *
	 * @var string
	 */
	private const MEMORY_KEYWORD = '@memory';

	/**
	 * Script handle.
	 *
	 * @var string
	 */
	private const SCRIPT_HANDLE = 'openwp-sitewide-chatbot';

	/**
	 * Style handle.
	 *
	 * @var string
	 */
	private const STYLE_HANDLE = 'openwp-sitewide-chatbot';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Enqueue on frontend pages.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$this->enqueue_assets();
	}

	/**
	 * Enqueue on wp-admin pages.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$this->enqueue_assets();
	}

	/**
	 * Register and enqueue sitewide chatbot assets.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		if ( ! $this->can_render_widget() ) {
			return;
		}

		$asset = [
			'dependencies' => [ 'wp-element', 'wp-i18n' ],
			'version'      => OPENWP_VERSION,
		];

		$asset_file = OPENWP_DIR . 'build/sitewide-chatbot.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset_data = include $asset_file;
			if ( is_array( $asset_data ) ) {
				$asset = array_merge( $asset, $asset_data );
			}
		}

		$script_file = OPENWP_DIR . 'build/sitewide-chatbot.js';
		if ( ! file_exists( $script_file ) ) {
			return;
		}

		$css_file = OPENWP_DIR . 'build/style-sitewide-chatbot.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				self::STYLE_HANDLE,
				OPENWP_URL . 'build/style-sitewide-chatbot.css',
				[],
				$asset['version']
			);
			wp_style_add_data( self::STYLE_HANDLE, 'rtl', 'replace' );
		}

		$deps = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: [ 'wp-element', 'wp-i18n' ];

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			OPENWP_URL . 'build/sitewide-chatbot.js',
			$deps,
			$asset['version'],
			true
		);

		wp_set_script_translations( self::SCRIPT_HANDLE, 'openwp', OPENWP_DIR . 'languages' );

		$settings         = Settings::get();
		$default_provider = isset( $settings['default_provider'] ) ? sanitize_key( (string) $settings['default_provider'] ) : 'openrouter';
		if ( ! in_array( $default_provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$default_provider = 'openrouter';
		}
		$page_context     = $this->build_page_context();

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'openwpSiteChat',
			[
				'root'              => esc_url_raw( rest_url() ),
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'defaultProvider'   => $default_provider,
				'defaultModel'      => $this->resolve_default_model( $default_provider, $settings ),
				'providers'         => [
					'openai'     => isset( $settings['default_model_openai'] ) ? (string) $settings['default_model_openai'] : 'gpt-5.2',
					'anthropic'  => isset( $settings['default_model_anthropic'] ) ? (string) $settings['default_model_anthropic'] : 'claude-3-5-sonnet-latest',
					'glm'        => isset( $settings['default_model_glm'] ) ? (string) $settings['default_model_glm'] : 'glm-5',
					'openrouter' => isset( $settings['default_model_openrouter'] ) ? (string) $settings['default_model_openrouter'] : 'anthropic/claude-sonnet-4-5',
				],
				'limits'            => [
					'maxFiles'         => 5,
					'maxFileSizeBytes' => 10485760,
				],
				'availableTags'     => [ self::CONTEXT_KEYWORD, self::MEMORY_KEYWORD ],
				'memoryKeyword'     => self::MEMORY_KEYWORD,
				'contextKeyword'    => self::CONTEXT_KEYWORD,
				'pageContext'       => $page_context,
				'currentUser'       => get_current_user_id(),
				'strings'           => [
					'title'              => __( 'OpenWP Assistant', 'openwp' ),
					'subtitle'           => __( 'Session chat context resets on reload.', 'openwp' ),
					'placeholder'        => __( 'Ask OpenWP to help with your site…', 'openwp' ),
					'send'               => __( 'Send', 'openwp' ),
					'upload'             => __( 'Add files', 'openwp' ),
					'clear'              => __( 'Clear chat', 'openwp' ),
					'attachments'        => __( 'Attachments', 'openwp' ),
					'includeForMessage'  => __( 'Include in next message', 'openwp' ),
					'fileLimitReached'   => __( 'File limit reached for this session.', 'openwp' ),
					'uploadTooLarge'     => __( 'File exceeds the 10MB limit.', 'openwp' ),
					'working'            => __( 'Working…', 'openwp' ),
					'errorPrefix'        => __( 'Error:', 'openwp' ),
					'waitingResponse'    => __( 'OpenWP is thinking…', 'openwp' ),
					'contextHint'        => __( 'Type @page to include current page context.', 'openwp' ),
					'contextEmptyPrompt' => __( 'Add a request after @page so OpenWP knows what to do.', 'openwp' ),
					'tagPageContext'     => __( 'Include current page/screen context', 'openwp' ),
					'tagMemory'          => __( 'Reference your saved preferences and rules', 'openwp' ),
					'tagAvailable'       => __( 'Available tag', 'openwp' ),
				],
			]
		);
	}

	/**
	 * Build context payload for the current page.
	 *
	 * @return array<string,mixed>
	 */
	private function build_page_context() {
		return is_admin()
			? $this->build_admin_page_context()
			: $this->build_frontend_page_context();
	}

	/**
	 * Build context payload for frontend pages.
	 *
	 * @return array<string,mixed>
	 */
	private function build_frontend_page_context() {
		$context = [
			'surface'   => 'frontend',
			'page_type' => 'generic',
			'title'     => sanitize_text_field( wp_get_document_title() ),
			'url'       => $this->safe_current_url(),
		];

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$context['page_type']   = 'singular';
				$context['post_id']     = (int) $post->ID;
				$context['post_type']   = sanitize_key( (string) $post->post_type );
				$context['post_status'] = sanitize_key( (string) $post->post_status );
				$context['title']       = sanitize_text_field( get_the_title( $post ) );
				$context['permalink']   = esc_url_raw( (string) get_permalink( $post ) );
				$context['excerpt']     = $this->sanitize_excerpt_text( (string) $post->post_content, 2000 );
				return $context;
			}
		}

		if ( is_front_page() ) {
			$context['page_type'] = 'front_page';
		} elseif ( is_home() ) {
			$context['page_type'] = 'blog_index';
		} elseif ( is_archive() ) {
			$context['page_type'] = 'archive';
		} elseif ( is_search() ) {
			$context['page_type'] = 'search';
		} elseif ( is_404() ) {
			$context['page_type'] = 'not_found';
		}

		return $context;
	}

	/**
	 * Build context payload for wp-admin pages.
	 *
	 * @return array<string,mixed>
	 */
	private function build_admin_page_context() {
		$context = [
			'surface'   => 'admin',
			'page_type' => 'admin_screen',
			'title'     => sanitize_text_field( get_admin_page_title() ),
			'url'       => $this->safe_current_url(),
		];

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen instanceof \WP_Screen ) {
				$context['screen_id']   = sanitize_key( (string) $screen->id );
				$context['screen_base'] = sanitize_key( (string) $screen->base );
				$context['parent_base'] = sanitize_key( (string) $screen->parent_base );
			}
		}

		$page_slug = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page_slug ) {
			$context['page_slug'] = $page_slug;
		}

		// Include saved post metadata/content only for existing-post edit screens.
		$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( 'post.php' === $pagenow ) {
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				if ( $post instanceof \WP_Post ) {
					$context['page_type']   = 'admin_post_edit';
					$context['post_id']     = (int) $post->ID;
					$context['post_type']   = sanitize_key( (string) $post->post_type );
					$context['post_status'] = sanitize_key( (string) $post->post_status );
					$context['title']       = sanitize_text_field( get_the_title( $post ) );
					$context['permalink']   = esc_url_raw( (string) get_permalink( $post ) );
					$context['excerpt']     = $this->sanitize_excerpt_text( (string) $post->post_content, 2000 );
				}
			}
		}

		return $context;
	}

	/**
	 * Build a safe URL for current page context payload.
	 *
	 * @return string
	 */
	private function safe_current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		$url = '';
		if ( '' !== $host && '' !== $uri ) {
			$url = $scheme . $host . $uri;
		} elseif ( '' !== $uri ) {
			$url = home_url( $uri );
		}

		if ( '' === $url ) {
			$url = is_admin() ? admin_url() : home_url( '/' );
		}

		$url = esc_url_raw( $url );
		$url = remove_query_arg(
			[
				'_wpnonce',
				'_ajax_nonce',
				'nonce',
				'token',
				'auth',
				'key',
			],
			$url
		);

		return esc_url_raw( (string) $url );
	}

	/**
	 * Sanitize and bound excerpt text for prompt context.
	 *
	 * @param string $text Raw source text.
	 * @param int    $max_len Maximum length.
	 * @return string
	 */
	private function sanitize_excerpt_text( $text, $max_len ) {
		$text = (string) $text;
		if ( function_exists( 'strip_shortcodes' ) ) {
			$text = strip_shortcodes( $text );
		}
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';
		if ( '' === $text ) {
			return '';
		}

		if ( strlen( $text ) <= $max_len ) {
			return $text;
		}

		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $max_len );
		}

		return substr( $text, 0, $max_len );
	}

	/**
	 * Determine if the current user should receive the chatbot widget.
	 *
	 * @return bool
	 */
	private function can_render_widget() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return current_user_can( 'openwp_run_agent' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Resolve default model by provider.
	 *
	 * @param string              $provider Provider slug.
	 * @param array<string,mixed> $settings OpenWP settings.
	 * @return string
	 */
	private function resolve_default_model( $provider, $settings ) {
		if ( 'anthropic' === $provider ) {
			$anthropic = isset( $settings['default_model_anthropic'] ) ? trim( (string) $settings['default_model_anthropic'] ) : '';
			return '' !== $anthropic ? $anthropic : 'claude-3-5-sonnet-latest';
		}

		if ( 'glm' === $provider ) {
			$glm = isset( $settings['default_model_glm'] ) ? trim( (string) $settings['default_model_glm'] ) : '';
			return '' !== $glm ? $glm : 'glm-5';
		}

		if ( 'openrouter' === $provider ) {
			$openrouter = isset( $settings['default_model_openrouter'] ) ? trim( (string) $settings['default_model_openrouter'] ) : '';
			return '' !== $openrouter ? $openrouter : 'anthropic/claude-sonnet-4-5';
		}

		$openai = isset( $settings['default_model_openai'] ) ? trim( (string) $settings['default_model_openai'] ) : '';
		return '' !== $openai ? $openai : 'gpt-5.2';
	}
}
