<?php
/**
 * Gutenberg editor AI content generator block.
 *
 * @package OpenWP\Inc\Admin
 */

namespace OpenWP\Inc\Admin;

use OpenWP\Inc\Core\Settings;
use OpenWP\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers OpenWP editor block assets.
 */
class Editor_Block {
	use Get_Instance;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 1 );
	}

	/**
	 * Register OpenWP block category at the top of the inserter.
	 *
	 * @param array<int,array<string,string>> $categories Existing block categories.
	 * @return array<int,array<string,string>>
	 */
	public function register_block_category( $categories ) {
		return array_merge(
			[
				[
					'slug'  => 'openwp',
					'title' => __( 'OpenWP', 'openwp' ),
				],
			],
			$categories
		);
	}

	/**
	 * Register Gutenberg block and assets.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$asset = [
			'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-block-editor', 'wp-data', 'wp-plugins', 'wp-keyboard-shortcuts' ],
			'version'      => OPENWP_VERSION,
		];

		$asset_file = OPENWP_DIR . 'build/ai-content-generator-block.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset_data = include $asset_file;
			if ( is_array( $asset_data ) ) {
				$asset = array_merge( $asset, $asset_data );
			}
		}

		$settings = Settings::get();
		$provider = isset( $settings['default_provider'] ) ? sanitize_key( (string) $settings['default_provider'] ) : 'openrouter';
		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = 'openrouter';
		}

		wp_register_script(
			'openwp-ai-content-generator-block',
			OPENWP_URL . 'build/ai-content-generator-block.js',
			is_array( $asset['dependencies'] ) ? $asset['dependencies'] : [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-block-editor', 'wp-data', 'wp-plugins', 'wp-keyboard-shortcuts' ],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'openwp-ai-content-generator-block', 'openwp', OPENWP_DIR . 'languages' );

		$theme_palette = isset( $settings['theme_palette'] ) && is_array( $settings['theme_palette'] ) && ! empty( $settings['theme_palette'] )
			? array_values( $settings['theme_palette'] )
			: self::default_palette();

		wp_localize_script(
			'openwp-ai-content-generator-block',
			'openwpAiContentGenerator',
			[
				'defaultProvider'    => $provider,
				'defaultModel'       => $this->resolve_default_model( $provider, $settings ),
				'providers'          => [
					'openai'     => isset( $settings['default_model_openai'] ) ? (string) $settings['default_model_openai'] : 'gpt-5.2',
					'anthropic'  => isset( $settings['default_model_anthropic'] ) ? (string) $settings['default_model_anthropic'] : 'claude-3-5-sonnet-latest',
					'glm'        => isset( $settings['default_model_glm'] ) ? (string) $settings['default_model_glm'] : 'glm-5',
					'openrouter' => isset( $settings['default_model_openrouter'] ) ? (string) $settings['default_model_openrouter'] : 'anthropic/claude-sonnet-4-5',
				],
				'themePalette'       => $theme_palette,
				'defaultContentType' => isset( $settings['editor_default_content_type'] ) ? (string) $settings['editor_default_content_type'] : 'hero_section',
				'defaultTone'        => isset( $settings['editor_default_tone'] ) ? (string) $settings['editor_default_tone'] : 'professional',
			]
		);

		wp_register_style(
			'openwp-ai-content-generator-block',
			OPENWP_URL . 'build/ai-content-generator-block.css',
			[],
			$asset['version']
		);

		register_block_type(
			'openwp/ai-content-generator',
			[
				'api_version'     => 2,
				'editor_script'   => 'openwp-ai-content-generator-block',
				'editor_style'    => 'openwp-ai-content-generator-block',
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Dynamic block render callback.
	 *
	 * @return string
	 */
	public function render_block() {
		return '';
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
			return isset( $settings['default_model_anthropic'] ) ? (string) $settings['default_model_anthropic'] : 'claude-3-5-sonnet-latest';
		}

		if ( 'glm' === $provider ) {
			return isset( $settings['default_model_glm'] ) ? (string) $settings['default_model_glm'] : 'glm-5';
		}

		if ( 'openrouter' === $provider ) {
			return isset( $settings['default_model_openrouter'] ) ? (string) $settings['default_model_openrouter'] : 'anthropic/claude-sonnet-4-5';
		}

		$openai = isset( $settings['default_model_openai'] ) ? trim( (string) $settings['default_model_openai'] ) : '';
		return '' !== $openai ? $openai : 'gpt-5.2';
	}

	/**
	 * Default palette (Royal Indigo).
	 *
	 * @return string[]
	 */
	private static function default_palette() {
		$defaults = Settings::defaults();
		return isset( $defaults['theme_palette'] ) && is_array( $defaults['theme_palette'] )
			? $defaults['theme_palette']
			: [];
	}
}
