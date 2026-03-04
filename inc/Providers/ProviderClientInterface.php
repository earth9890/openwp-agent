<?php
/**
 * Provider interface.
 *
 * @package OpenWP\Inc\Providers
 */

namespace OpenWP\Inc\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LLM provider contract.
 */
interface ProviderClientInterface {
	/**
	 * Generate model output.
	 *
	 * @param ProviderRequest $request Provider request.
	 * @return ProviderResponse|\WP_Error
	 */
	public function generate( ProviderRequest $request );

	/**
	 * Generate model output with real-time streaming.
	 *
	 * Calls $on_token with each text delta as it arrives from the provider.
	 * Returns the final ProviderResponse after the stream completes.
	 *
	 * @param ProviderRequest $request  Provider request.
	 * @param callable        $on_token Callback receiving a string text delta.
	 * @return ProviderResponse|\WP_Error
	 */
	public function generate_stream( ProviderRequest $request, callable $on_token );
}
