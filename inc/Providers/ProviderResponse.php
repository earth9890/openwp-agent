<?php
/**
 * Provider response value object.
 *
 * @package OpenWP\Inc\Providers
 */

namespace OpenWP\Inc\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized provider response.
 */
class ProviderResponse {
	/**
	 * Provider slug.
	 *
	 * @var string
	 */
	public $provider;

	/**
	 * Model used.
	 *
	 * @var string
	 */
	public $model;

	/**
	 * Raw text output.
	 *
	 * @var string
	 */
	public $output_text;

	/**
	 * Input token count.
	 *
	 * @var int
	 */
	public $input_tokens;

	/**
	 * Output token count.
	 *
	 * @var int
	 */
	public $output_tokens;

	/**
	 * Raw provider response.
	 *
	 * @var array<string,mixed>
	 */
	public $raw;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Init args.
	 */
	public function __construct( $args = [] ) {
		$this->provider      = isset( $args['provider'] ) ? (string) $args['provider'] : '';
		$this->model         = isset( $args['model'] ) ? (string) $args['model'] : '';
		$this->output_text   = isset( $args['output_text'] ) ? (string) $args['output_text'] : '';
		$this->input_tokens  = isset( $args['input_tokens'] ) ? (int) $args['input_tokens'] : 0;
		$this->output_tokens = isset( $args['output_tokens'] ) ? (int) $args['output_tokens'] : 0;
		$this->raw           = isset( $args['raw'] ) && is_array( $args['raw'] ) ? $args['raw'] : [];
	}

	/**
	 * Convert to array.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return [
			'provider'      => $this->provider,
			'model'         => $this->model,
			'output_text'   => $this->output_text,
			'input_tokens'  => $this->input_tokens,
			'output_tokens' => $this->output_tokens,
			'raw'           => $this->raw,
		];
	}
}
