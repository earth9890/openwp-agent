<?php
/**
 * Provider request value object.
 *
 * @package OpenWP\Inc\Providers
 */

namespace OpenWP\Inc\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Request payload for provider calls.
 */
class ProviderRequest {
	/**
	 * System prompt.
	 *
	 * @var string
	 */
	public $system_prompt;

	/**
	 * User prompt.
	 *
	 * @var string
	 */
	public $prompt;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	public $model;

	/**
	 * JSON schema.
	 *
	 * @var array<string,mixed>
	 */
	public $schema;

	/**
	 * Temperature.
	 *
	 * @var float
	 */
	public $temperature;

	/**
	 * Timeout.
	 *
	 * @var int
	 */
	public $timeout;

	/**
	 * Whether provider request should use streaming mode.
	 *
	 * @var bool
	 */
	public $stream;

	/**
	 * Maximum tokens in the response (0 = provider default).
	 *
	 * @var int
	 */
	public $max_tokens;

	/**
	 * Optional user content blocks for multimodal prompts.
	 *
	 * Each block should include `type` and provider-neutral payload keys.
	 * Supported types:
	 * - text: { type: "text", text: "..." }
	 * - image_url: { type: "image_url", url: "data:image/...;base64,..." }
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public $user_content;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Args.
	 */
	public function __construct( $args = [] ) {
		$this->system_prompt = isset( $args['system_prompt'] ) ? (string) $args['system_prompt'] : '';
		$this->prompt        = isset( $args['prompt'] ) ? (string) $args['prompt'] : '';
		$this->model         = isset( $args['model'] ) ? (string) $args['model'] : '';
		$this->schema        = isset( $args['schema'] ) && is_array( $args['schema'] ) ? $args['schema'] : [];
		$this->temperature   = isset( $args['temperature'] ) ? (float) $args['temperature'] : 0.2;
		$this->timeout       = isset( $args['timeout'] ) ? max( 60, (int) $args['timeout'] ) : 60;
		$this->stream        = ! isset( $args['stream'] ) || (bool) $args['stream'];
		$this->max_tokens    = isset( $args['max_tokens'] ) ? absint( $args['max_tokens'] ) : 0;
		$this->user_content  = isset( $args['user_content'] ) && is_array( $args['user_content'] ) ? array_values( $args['user_content'] ) : [];
	}
}
