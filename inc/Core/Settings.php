<?php
/**
 * Settings manager.
 *
 * @package OpenWP\Inc\Core
 */

namespace OpenWP\Inc\Core;

use OpenWP\Inc\MCP\MCP_Auth;
use OpenWP\Inc\MCP\Tool_Registry;
use OpenWP\Inc\Security\Provider_Key_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option storage and defaults.
 */
class Settings {
	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return [
			'default_provider'         => 'openrouter',
			'default_model_openai'     => 'gpt-5.2',
			'default_model_anthropic'  => 'claude-3-5-sonnet-latest',
			'default_model_glm'        => 'glm-5',
			'default_model_openrouter' => 'anthropic/claude-sonnet-4-5',
			'log_retention_days'       => 90,
			'timeout_seconds'          => 45,
			'kill_switch'              => false,
			'max_actions_user_day'     => 50,
			'max_actions_site_day'     => 500,
			'max_tokens_user_day'      => 120000,
			'max_tokens_site_day'      => 1200000,
			'min_seconds_between_runs' => 5,
			'medium_requires_approval' => false,
			'high_requires_approval'   => false,
			'critical_requires_approval'  => false,
			'openwp_memory_enabled'    => true,
			// Content generation.
			'theme_palette'               => [ '#4F46E5', '#7C3AED', '#F59E0B', '#1E1B4B', '#EEF2FF', '#C7D2FE' ],
			'editor_default_content_type' => 'hero_section',
			'editor_default_tone'         => 'professional',
		];
	}

	/**
	 * Default MCP module toggles.
	 *
	 * @return array<string,bool>
	 */
	public static function mcp_module_defaults() {
		return [
			'core'     => true,
			'woo'      => true,
			'plugin'   => true,
			'theme'    => true,
			'db'       => true,
			'polylang' => true,
		];
	}

	/**
	 * Retrieve settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$settings = get_option( OPENWP_OPTION_SETTINGS, [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		return wp_parse_args( $settings, self::defaults() );
	}

	/**
	 * Persist merged settings.
	 *
	 * @param array<string,mixed> $settings Settings to merge.
	 * @return array<string,mixed>
	 */
	public static function update( $settings ) {
		$current = self::get();
		$merged  = wp_parse_args( self::sanitize( $settings ), $current );
		update_option( OPENWP_OPTION_SETTINGS, $merged );

		return $merged;
	}

	/**
	 * Persist action policy option.
	 *
	 * @param array<string,mixed> $policy Policy object.
	 * @return void
	 */
	public static function update_action_policy( $policy ) {
		if ( ! is_array( $policy ) ) {
			return;
		}

		update_option( OPENWP_OPTION_ACTION_POLICIES, $policy );
	}

	/**
	 * Get action policy map.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_action_policy() {
		$policy = get_option( OPENWP_OPTION_ACTION_POLICIES, [] );

		return is_array( $policy ) ? $policy : [];
	}

	/**
	 * Masked provider status for UI.
	 *
	 * @return array<string,mixed>
	 */
	public static function provider_status() {
		$manager = new Provider_Key_Manager();
		$keys    = $manager->get_masked_keys();

		return [
			'has_openai_key'    => ! empty( $keys['openai'] ),
			'has_anthropic_key' => ! empty( $keys['anthropic'] ),
			'has_glm_key'       => ! empty( $keys['glm'] ),
			'has_openrouter_key' => ! empty( $keys['openrouter'] ),
			'masked_keys'       => $keys,
		];
	}

	/**
	 * Return MCP settings state for API/UI.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_mcp_settings() {
		$modules = get_option( OPENWP_OPTION_MCP_MODULES, [] );
		if ( ! is_array( $modules ) ) {
			$modules = [];
		}
		$modules = wp_parse_args( $modules, self::mcp_module_defaults() );

		foreach ( $modules as $key => $value ) {
			$modules[ $key ] = rest_sanitize_boolean( $value );
		}

		$auth = new MCP_Auth();
		$token = $auth->get_encrypted_bearer_token();
		$module_status = self::mcp_module_status( $modules );

		$endpoints = [
			'sse'      => rest_url( 'mcp/v1/sse' ),
			'messages' => rest_url( 'mcp/v1/messages' ),
			'http'     => rest_url( 'mcp/v1/http' ),
			'upload'   => rest_url( 'mcp/v1/upload/{token}' ),
		];

		return [
			'enabled'          => rest_sanitize_boolean( get_option( OPENWP_OPTION_MCP_ENABLED, false ) ),
			'debug_mode'       => rest_sanitize_boolean( get_option( OPENWP_OPTION_MCP_DEBUG_MODE, false ) ),
			'modules'          => $modules,
			'module_status'    => $module_status,
			'has_bearer_token' => '' !== (string) $token,
			'endpoints'        => $endpoints,
			'snippets'         => self::mcp_snippets( $endpoints ),
		];
	}

	/**
	 * Update MCP options payload.
	 *
	 * @param array<string,mixed> $settings Raw settings.
	 * @return array<string,mixed>
	 */
	public static function update_mcp_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : [];
		$auth     = new MCP_Auth();

		if ( array_key_exists( 'enabled', $settings ) ) {
			update_option( OPENWP_OPTION_MCP_ENABLED, rest_sanitize_boolean( $settings['enabled'] ) );
		}

		if ( array_key_exists( 'debug_mode', $settings ) ) {
			update_option( OPENWP_OPTION_MCP_DEBUG_MODE, rest_sanitize_boolean( $settings['debug_mode'] ) );
		}

		if ( array_key_exists( 'modules', $settings ) && is_array( $settings['modules'] ) ) {
			$modules = wp_parse_args( $settings['modules'], self::mcp_module_defaults() );
			foreach ( $modules as $key => $value ) {
				$modules[ $key ] = rest_sanitize_boolean( $value );
			}
			update_option( OPENWP_OPTION_MCP_MODULES, $modules );
		}

		if ( array_key_exists( 'bearer_token', $settings ) ) {
			$auth->save_bearer_token( (string) $settings['bearer_token'] );
		}

		return self::get_mcp_settings();
	}

	/**
	 * Build module status map with availability and tool counts.
	 *
	 * @param array<string,bool> $modules Module enabled flags.
	 * @return array<string,array<string,mixed>>
	 */
	private static function mcp_module_status( $modules ) {
		$base_counts = [
			'core'     => 39,
			'woo'      => 25,
			'plugin'   => 13,
			'theme'    => 13,
			'db'       => 1,
			'polylang' => 11,
		];

		$availability = [
			'core'     => true,
			'woo'      => class_exists( 'WooCommerce' ),
			'plugin'   => true,
			'theme'    => true,
			'db'       => true,
			'polylang' => function_exists( 'pll_languages_list' ),
		];

		$live_counts = [];
		if ( class_exists( Tool_Registry::class ) ) {
			$registry = new Tool_Registry();
			foreach ( $registry->module_status() as $module_key => $meta ) {
				$live_counts[ $module_key ] = absint( $meta['tools'] ?? 0 );
			}
		}

		$status = [];
		foreach ( self::mcp_module_defaults() as $module => $default_enabled ) {
			$enabled = isset( $modules[ $module ] ) ? rest_sanitize_boolean( $modules[ $module ] ) : $default_enabled;
			$available = ! empty( $availability[ $module ] );
			$status[ $module ] = [
				'enabled'    => $enabled,
				'available'  => $available,
				'tool_count' => isset( $live_counts[ $module ] ) ? (int) $live_counts[ $module ] : ( $available && $enabled ? (int) ( $base_counts[ $module ] ?? 0 ) : 0 ),
				'expected'   => (int) ( $base_counts[ $module ] ?? 0 ),
			];
		}

		return $status;
	}

	/**
	 * Build MCP client setup snippets.
	 *
	 * @param array<string,string> $endpoints Endpoint URLs.
	 * @return array<string,string>
	 */
	private static function mcp_snippets( $endpoints ) {
		$http = isset( $endpoints['http'] ) ? $endpoints['http'] : '';
		$sse  = isset( $endpoints['sse'] ) ? $endpoints['sse'] : '';

		$parsed    = wp_parse_url( home_url() );
		$site_slug = isset( $parsed['host'] ) ? sanitize_title( $parsed['host'] ) : 'my-site';

		return [
			'claude_code' =>
				'claude mcp add --transport http ' . $site_slug . ' ' . $http . ' --header "Authorization: Bearer <YOUR_TOKEN>"',
			'json_config' =>
				"{\n"
				. "  \"transport\": \"streamable_http\",\n"
				. "  \"url\": \"" . $http . "\",\n"
				. "  \"headers\": {\n"
				. "    \"Authorization\": \"Bearer <YOUR_TOKEN>\"\n"
				. "  }\n"
				. "}",
			'curl_initialize' =>
				"curl -X POST '" . $http . "' \\\n"
				. "  -H 'Authorization: Bearer <YOUR_TOKEN>' \\\n"
				. "  -H 'Content-Type: application/json' \\\n"
				. "  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"2025-06-18\",\"clientInfo\":{\"name\":\"manual\",\"version\":\"1.0\"}}}'",
			'curl_tools_list' =>
				"curl -X POST '" . $http . "' \\\n"
				. "  -H 'Authorization: Bearer <YOUR_TOKEN>' \\\n"
				. "  -H 'Content-Type: application/json' \\\n"
				. "  -d '{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"tools/list\"}'",
			'sse_hint' =>
				"SSE endpoint: " . $sse . "\n"
				. "Messages endpoint: " . ( isset( $endpoints['messages'] ) ? $endpoints['messages'] : '' ),
		];
	}

	/**
	 * Sanitize incoming settings.
	 *
	 * @param array<string,mixed> $settings Raw payload.
	 * @return array<string,mixed>
	 */
	private static function sanitize( $settings ) {
		$sanitized = [];

		if ( isset( $settings['default_provider'] ) ) {
			$sanitized['default_provider'] = in_array( $settings['default_provider'], [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ? $settings['default_provider'] : 'openrouter';
		}

		if ( isset( $settings['default_model_openai'] ) ) {
			$sanitized['default_model_openai'] = sanitize_text_field( (string) $settings['default_model_openai'] );
		}

		if ( isset( $settings['default_model_anthropic'] ) ) {
			$sanitized['default_model_anthropic'] = sanitize_text_field( (string) $settings['default_model_anthropic'] );
		}

		if ( isset( $settings['default_model_glm'] ) ) {
			$sanitized['default_model_glm'] = sanitize_text_field( (string) $settings['default_model_glm'] );
		}

		if ( isset( $settings['default_model_openrouter'] ) ) {
			$sanitized['default_model_openrouter'] = sanitize_text_field( (string) $settings['default_model_openrouter'] );
		}

		$int_keys = [
			'log_retention_days',
			'timeout_seconds',
			'max_actions_user_day',
			'max_actions_site_day',
			'max_tokens_user_day',
			'max_tokens_site_day',
			'min_seconds_between_runs',
		];

		foreach ( $int_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$value = max( 1, absint( $settings[ $key ] ) );
				$sanitized[ $key ] = $value;
			}
		}

		$bool_keys = [
			'kill_switch',
			'medium_requires_approval',
			'high_requires_approval',
			'critical_requires_approval',
			'openwp_memory_enabled',
		];

		foreach ( $bool_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$sanitized[ $key ] = rest_sanitize_boolean( $settings[ $key ] );
			}
		}

		// Content generation settings.
		if ( isset( $settings['theme_palette'] ) && is_array( $settings['theme_palette'] ) ) {
			$palette = [];
			foreach ( $settings['theme_palette'] as $color ) {
				$hex = sanitize_hex_color( (string) $color );
				if ( $hex ) {
					$palette[] = $hex;
				}
			}
			$sanitized['theme_palette'] = array_values( array_unique( array_slice( $palette, 0, 7 ) ) );
		}

		$valid_content_types = [
			'hero_section', 'feature_section', 'faq_section', 'cta_section', 'blog_intro',
			'testimonials_section', 'pricing_section', 'about_section', 'newsletter_section',
			'contact_section', 'full_landing_page', 'full_blog_post', 'full_about_page',
			'full_services_page', 'full_contact_page',
		];
		if ( isset( $settings['editor_default_content_type'] ) ) {
			$type = sanitize_key( (string) $settings['editor_default_content_type'] );
			$sanitized['editor_default_content_type'] = in_array( $type, $valid_content_types, true ) ? $type : 'hero_section';
		}

		$valid_tones = [ 'professional', 'friendly', 'persuasive', 'casual', 'technical' ];
		if ( isset( $settings['editor_default_tone'] ) ) {
			$tone = sanitize_key( (string) $settings['editor_default_tone'] );
			$sanitized['editor_default_tone'] = in_array( $tone, $valid_tones, true ) ? $tone : 'professional';
		}

		return $sanitized;
	}
}
