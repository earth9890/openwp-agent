<?php
/**
 * Provider factory.
 *
 * @package OpenWP\Inc\Providers
 */

namespace OpenWP\Inc\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves provider clients.
 */
class Provider_Factory {
	/**
	 * Create provider client.
	 *
	 * @param string $provider Provider slug.
	 * @return ProviderClientInterface|null
	 */
	public static function create( $provider ) {
		switch ( $provider ) {
			case 'openai':
				return new OpenAI_Client();
			case 'anthropic':
				return new Anthropic_Client();
			case 'glm':
				return new GLM_Client();
			case 'openrouter':
				return new OpenRouter_Client();
			default:
				return null;
		}
	}
}
