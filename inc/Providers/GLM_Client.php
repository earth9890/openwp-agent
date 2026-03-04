<?php
/**
 * GLM provider client.
 *
 * @package OpenWP\Inc\Providers
 */

namespace OpenWP\Inc\Providers;

use OpenWP\Inc\Security\Provider_Key_Manager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Z.AI GLM Chat Completions API calls.
 */
class GLM_Client implements ProviderClientInterface {
	/**
	 * Generate completion from GLM Chat Completions API.
	 *
	 * @param ProviderRequest $request Request payload.
	 * @return ProviderResponse|WP_Error
	 */
	public function generate( ProviderRequest $request ) {
		$key_manager = new Provider_Key_Manager();
		$api_key     = $key_manager->get_key( 'glm' );

		if ( '' === $api_key ) {
			return new WP_Error( 'openwp_glm_key_missing', __( 'GLM API key is not configured.', 'openwp' ) );
		}

		$system_prompt = $request->system_prompt;
		if ( ! empty( $request->schema ) ) {
			$system_prompt .= "\nReturn only valid JSON object matching this schema:\n" . wp_json_encode( $request->schema );
		}

		$body = [
			'model'           => $request->model,
			'temperature'     => $request->temperature,
			'stream'          => (bool) $request->stream,
			'messages'        => [
				[
					'role'    => 'system',
					'content' => $system_prompt,
				],
				[
					'role'    => 'user',
					'content' => $this->build_user_content_payload( $request ),
				],
			],
			'response_format' => [
				'type' => 'json_object',
			],
		];

		if ( $request->max_tokens > 0 ) {
			$body['max_tokens'] = $request->max_tokens;
		}

		$http = Stream_Transport::post_json(
			'https://api.z.ai/api/paas/v4/chat/completions',
			[
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			$body,
			$request->timeout,
			(bool) $request->stream
		);

		if ( is_wp_error( $http ) ) {
			return $http;
		}

		$status   = (int) ( $http['status'] ?? 0 );
		$raw_body = (string) ( $http['body'] ?? '' );
		$events   = (bool) $request->stream ? Stream_Transport::parse_sse_json_events( $raw_body ) : [];

		if ( $status < 200 || $status >= 300 ) {
			$message = $this->extract_error_message( $raw_body, $events, __( 'GLM request failed.', 'openwp' ) );
			return new WP_Error( 'openwp_glm_request_failed', $message, [ 'status' => $status ] );
		}

		if ( ! empty( $events ) ) {
			$parsed = $this->parse_stream_response( $events, $request->model );
			return new ProviderResponse(
				[
					'provider'      => 'glm',
					'model'         => $parsed['model'],
					'output_text'   => $parsed['output_text'],
					'input_tokens'  => $parsed['input_tokens'],
					'output_tokens' => $parsed['output_tokens'],
					'raw'           => $parsed['raw'],
				]
			);
		}

		$raw = json_decode( $raw_body, true );
		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'openwp_glm_invalid_json', __( 'GLM returned invalid JSON payload.', 'openwp' ) );
		}

		return new ProviderResponse(
			[
				'provider'      => 'glm',
				'model'         => (string) ( $raw['model'] ?? $request->model ),
				'output_text'   => $this->extract_text_from_message_payload( $raw ),
				'input_tokens'  => (int) ( $raw['usage']['prompt_tokens'] ?? 0 ),
				'output_tokens' => (int) ( $raw['usage']['completion_tokens'] ?? 0 ),
				'raw'           => $raw,
			]
		);
	}

	/**
	 * Generate completion with real-time streaming.
	 *
	 * @param ProviderRequest $request  Request payload.
	 * @param callable        $on_token Callback receiving string text deltas.
	 * @return ProviderResponse|WP_Error
	 */
	public function generate_stream( ProviderRequest $request, callable $on_token ) {
		$key_manager = new Provider_Key_Manager();
		$api_key     = $key_manager->get_key( 'glm' );

		if ( '' === $api_key ) {
			return new WP_Error( 'openwp_glm_key_missing', __( 'GLM API key is not configured.', 'openwp' ) );
		}

		$system_prompt = $request->system_prompt;
		if ( ! empty( $request->schema ) ) {
			$system_prompt .= "\nReturn only valid JSON object matching this schema:\n" . wp_json_encode( $request->schema );
		}

		$body = [
			'model'           => $request->model,
			'temperature'     => $request->temperature,
			'stream'          => true,
			'messages'        => [
				[
					'role'    => 'system',
					'content' => $system_prompt,
				],
				[
					'role'    => 'user',
					'content' => $this->build_user_content_payload( $request ),
				],
			],
			'response_format' => [
				'type' => 'json_object',
			],
		];

		if ( $request->max_tokens > 0 ) {
			$body['max_tokens'] = $request->max_tokens;
		}

		$text          = '';
		$model         = $request->model;
		$input_tokens  = 0;
		$output_tokens = 0;

		$result = Stream_Transport::post_json_stream(
			'https://api.z.ai/api/paas/v4/chat/completions',
			[
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			$body,
			$request->timeout,
			function ( $event ) use ( &$text, &$model, &$input_tokens, &$output_tokens, $on_token ) {
				if ( isset( $event['model'] ) && is_string( $event['model'] ) ) {
					$model = $event['model'];
				}

				$delta_text = $this->extract_text_from_delta_payload( $event );
				if ( '' !== $delta_text ) {
					$text .= $delta_text;
					$on_token( $delta_text );
				}

				if ( isset( $event['usage'] ) && is_array( $event['usage'] ) ) {
					$input_tokens  = (int) ( $event['usage']['prompt_tokens'] ?? $input_tokens );
					$output_tokens = (int) ( $event['usage']['completion_tokens'] ?? $output_tokens );
				}
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status = (int) ( $result['status'] ?? 0 );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'openwp_glm_request_failed', __( 'GLM request failed.', 'openwp' ), [ 'status' => $status ] );
		}

		return new ProviderResponse(
			[
				'provider'      => 'glm',
				'model'         => $model,
				'output_text'   => $text,
				'input_tokens'  => $input_tokens,
				'output_tokens' => $output_tokens,
				'raw'           => [ 'stream' => true ],
			]
		);
	}

	/**
	 * Build GLM chat user content payload.
	 *
	 * @param ProviderRequest $request Request payload.
	 * @return string|array<int,array<string,mixed>>
	 */
	private function build_user_content_payload( ProviderRequest $request ) {
		if ( empty( $request->user_content ) || ! is_array( $request->user_content ) ) {
			return $request->prompt;
		}

		$blocks = [];
		foreach ( $request->user_content as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$type = isset( $item['type'] ) ? (string) $item['type'] : '';
			if ( 'text' === $type ) {
				$text = trim( (string) ( $item['text'] ?? '' ) );
				if ( '' !== $text ) {
					$blocks[] = [
						'type' => 'text',
						'text' => $text,
					];
				}
				continue;
			}

			if ( 'image_url' === $type ) {
				$url = trim( (string) ( $item['url'] ?? '' ) );
				if ( '' !== $url ) {
					$blocks[] = [
						'type'      => 'image_url',
						'image_url' => [
							'url' => $url,
						],
					];
				}
			}
		}

		return ! empty( $blocks ) ? $blocks : $request->prompt;
	}

	/**
	 * Parse streamed GLM events.
	 *
	 * @param array<int,array<string,mixed>> $events Stream events.
	 * @param string                          $fallback_model Fallback model name.
	 * @return array<string,mixed>
	 */
	private function parse_stream_response( $events, $fallback_model ) {
		$text          = '';
		$model         = $fallback_model;
		$input_tokens  = 0;
		$output_tokens = 0;
		$last_event    = [];

		foreach ( $events as $event ) {
			$last_event = $event;

			if ( isset( $event['model'] ) && is_string( $event['model'] ) ) {
				$model = $event['model'];
			}

			$delta_text = $this->extract_text_from_delta_payload( $event );
			if ( '' !== $delta_text ) {
				$text .= $delta_text;
			}

			if ( isset( $event['usage'] ) && is_array( $event['usage'] ) ) {
				$input_tokens  = (int) ( $event['usage']['prompt_tokens'] ?? $input_tokens );
				$output_tokens = (int) ( $event['usage']['completion_tokens'] ?? $output_tokens );
			}
		}

		if ( '' === $text ) {
			$text = $this->extract_text_from_message_payload( $last_event );
		}

		return [
			'model'         => $model,
			'output_text'   => $text,
			'input_tokens'  => $input_tokens,
			'output_tokens' => $output_tokens,
			'raw'           => [
				'stream'       => true,
				'events_count' => count( $events ),
				'last_event'   => $last_event,
			],
		];
	}

	/**
	 * Extract text from streamed delta payload shape.
	 *
	 * @param array<string,mixed> $payload Event payload.
	 * @return string
	 */
	private function extract_text_from_delta_payload( $payload ) {
		$content = $payload['choices'][0]['delta']['content'] ?? null;

		if ( is_string( $content ) ) {
			return $content;
		}

		if ( is_array( $content ) ) {
			$chunks = [];
			foreach ( $content as $segment ) {
				if ( is_array( $segment ) && isset( $segment['text'] ) && is_string( $segment['text'] ) ) {
					$chunks[] = $segment['text'];
				}
			}
			if ( ! empty( $chunks ) ) {
				return implode( '', $chunks );
			}
		}

		return '';
	}

	/**
	 * Extract text from non-stream message payload shape.
	 *
	 * @param array<string,mixed> $payload API payload.
	 * @return string
	 */
	private function extract_text_from_message_payload( $payload ) {
		$content = $payload['choices'][0]['message']['content'] ?? null;

		if ( is_string( $content ) ) {
			return $content;
		}

		if ( is_array( $content ) ) {
			$chunks = [];
			foreach ( $content as $segment ) {
				if ( is_array( $segment ) && isset( $segment['text'] ) && is_string( $segment['text'] ) ) {
					$chunks[] = $segment['text'];
				}
			}
			if ( ! empty( $chunks ) ) {
				return implode( '', $chunks );
			}
		}

		return '';
	}

	/**
	 * Resolve provider error message from JSON or streamed events.
	 *
	 * @param string                          $raw_body Raw response body.
	 * @param array<int,array<string,mixed>>  $events Parsed SSE events.
	 * @param string                          $default_message Fallback message.
	 * @return string
	 */
	private function extract_error_message( $raw_body, $events, $default_message ) {
		$decoded = json_decode( $raw_body, true );
		if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
			return $decoded['error']['message'];
		}

		foreach ( $events as $event ) {
			if ( isset( $event['error']['message'] ) && is_string( $event['error']['message'] ) ) {
				return $event['error']['message'];
			}
		}

		return $default_message;
	}
}
