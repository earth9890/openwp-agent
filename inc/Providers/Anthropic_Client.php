<?php
/**
 * Anthropic provider client.
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
 * Handles Anthropic Messages API calls.
 */
class Anthropic_Client implements ProviderClientInterface {
	/**
	 * Generate completion from Anthropic Messages API.
	 *
	 * @param ProviderRequest $request Request payload.
	 * @return ProviderResponse|WP_Error
	 */
	public function generate( ProviderRequest $request ) {
		$key_manager = new Provider_Key_Manager();
		$api_key     = $key_manager->get_key( 'anthropic' );

		if ( '' === $api_key ) {
			return new WP_Error( 'openwp_anthropic_key_missing', __( 'Anthropic API key is not configured.', 'openwp' ) );
		}

		$system_prompt = $request->system_prompt . "\nReturn only valid JSON object matching this schema:\n" . wp_json_encode( $request->schema );

		$max_tokens = $request->max_tokens > 0 ? $request->max_tokens : 4096;

		$body = [
			'model'       => $request->model,
			'system'      => $system_prompt,
			'stream'      => (bool) $request->stream,
			'messages'    => [
				[
					'role'    => 'user',
					'content' => $this->build_user_content_payload( $request ),
				],
			],
			'temperature' => $request->temperature,
			'max_tokens'  => $max_tokens,
		];

		$http = Stream_Transport::post_json(
			'https://api.anthropic.com/v1/messages',
			[
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
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
			$message = $this->extract_error_message( $raw_body, $events, __( 'Anthropic request failed.', 'openwp' ) );
			return new WP_Error( 'openwp_anthropic_request_failed', $message, [ 'status' => $status ] );
		}

		if ( ! empty( $events ) ) {
			$parsed = $this->parse_stream_response( $events, $request->model );
			return new ProviderResponse(
				[
					'provider'      => 'anthropic',
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
			return new WP_Error( 'openwp_anthropic_invalid_json', __( 'Anthropic returned invalid JSON payload.', 'openwp' ) );
		}

		return new ProviderResponse(
			[
				'provider'      => 'anthropic',
				'model'         => (string) ( $raw['model'] ?? $request->model ),
				'output_text'   => $this->extract_text_from_message_payload( $raw ),
				'input_tokens'  => (int) ( $raw['usage']['input_tokens'] ?? 0 ),
				'output_tokens' => (int) ( $raw['usage']['output_tokens'] ?? 0 ),
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
		$api_key     = $key_manager->get_key( 'anthropic' );

		if ( '' === $api_key ) {
			return new WP_Error( 'openwp_anthropic_key_missing', __( 'Anthropic API key is not configured.', 'openwp' ) );
		}

		$system_prompt = $request->system_prompt . "\nReturn only valid JSON object matching this schema:\n" . wp_json_encode( $request->schema );
		$max_tokens    = $request->max_tokens > 0 ? $request->max_tokens : 4096;

		$body = [
			'model'       => $request->model,
			'system'      => $system_prompt,
			'stream'      => true,
			'messages'    => [
				[
					'role'    => 'user',
					'content' => $this->build_user_content_payload( $request ),
				],
			],
			'temperature' => $request->temperature,
			'max_tokens'  => $max_tokens,
		];

		$text          = '';
		$model         = $request->model;
		$input_tokens  = 0;
		$output_tokens = 0;

		$result = Stream_Transport::post_json_stream(
			'https://api.anthropic.com/v1/messages',
			[
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			],
			$body,
			$request->timeout,
			static function ( $event ) use ( &$text, &$model, &$input_tokens, &$output_tokens, $on_token ) {
				$type = isset( $event['type'] ) ? (string) $event['type'] : '';

				if ( 'content_block_delta' === $type && isset( $event['delta']['text'] ) && is_string( $event['delta']['text'] ) ) {
					$text .= $event['delta']['text'];
					$on_token( $event['delta']['text'] );
				}

				if ( 'message_start' === $type && isset( $event['message'] ) && is_array( $event['message'] ) ) {
					$model = isset( $event['message']['model'] ) ? (string) $event['message']['model'] : $model;
					if ( isset( $event['message']['usage'] ) && is_array( $event['message']['usage'] ) ) {
						$input_tokens  = (int) ( $event['message']['usage']['input_tokens'] ?? $input_tokens );
						$output_tokens = (int) ( $event['message']['usage']['output_tokens'] ?? $output_tokens );
					}
				}

				if ( 'message_delta' === $type && isset( $event['usage'] ) && is_array( $event['usage'] ) ) {
					$output_tokens = (int) ( $event['usage']['output_tokens'] ?? $output_tokens );
				}
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status = (int) ( $result['status'] ?? 0 );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'openwp_anthropic_request_failed', __( 'Anthropic request failed.', 'openwp' ), [ 'status' => $status ] );
		}

		return new ProviderResponse(
			[
				'provider'      => 'anthropic',
				'model'         => $model,
				'output_text'   => $text,
				'input_tokens'  => $input_tokens,
				'output_tokens' => $output_tokens,
				'raw'           => [ 'stream' => true ],
			]
		);
	}

	/**
	 * Build Anthropic user content payload.
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

			if ( 'image_url' !== $type ) {
				continue;
			}

			$url = trim( (string) ( $item['url'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}

			$matches = [];
			if ( ! preg_match( '/^data:(image\\/[a-zA-Z0-9.+\\-]+);base64,([A-Za-z0-9+\\/=\\s]+)$/', $url, $matches ) ) {
				continue;
			}

			$media_type = strtolower( (string) $matches[1] );
			$data       = preg_replace( '/\\s+/', '', (string) $matches[2] );
			$data       = is_string( $data ) ? $data : '';
			if ( '' === $data ) {
				continue;
			}

			$blocks[] = [
				'type'   => 'image',
				'source' => [
					'type'       => 'base64',
					'media_type' => $media_type,
					'data'       => $data,
				],
			];
		}

		return ! empty( $blocks ) ? $blocks : $request->prompt;
	}

	/**
	 * Parse streamed Anthropic events.
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
			$type       = isset( $event['type'] ) ? (string) $event['type'] : '';

			if ( 'content_block_delta' === $type && isset( $event['delta']['text'] ) && is_string( $event['delta']['text'] ) ) {
				$text .= $event['delta']['text'];
			}

			if ( 'content_block_start' === $type && '' === $text && isset( $event['content_block']['text'] ) && is_string( $event['content_block']['text'] ) ) {
				$text = $event['content_block']['text'];
			}

			if ( 'message_start' === $type && isset( $event['message'] ) && is_array( $event['message'] ) ) {
				$model = isset( $event['message']['model'] ) ? (string) $event['message']['model'] : $model;
				if ( isset( $event['message']['usage'] ) && is_array( $event['message']['usage'] ) ) {
					$input_tokens  = (int) ( $event['message']['usage']['input_tokens'] ?? $input_tokens );
					$output_tokens = (int) ( $event['message']['usage']['output_tokens'] ?? $output_tokens );
				}
			}

			if ( 'message_delta' === $type && isset( $event['usage'] ) && is_array( $event['usage'] ) ) {
				$output_tokens = (int) ( $event['usage']['output_tokens'] ?? $output_tokens );
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
	 * Extract text from non-stream message payload shape.
	 *
	 * @param array<string,mixed> $payload API payload.
	 * @return string
	 */
	private function extract_text_from_message_payload( $payload ) {
		if ( isset( $payload['content'] ) && is_array( $payload['content'] ) ) {
			$chunks = [];
			foreach ( $payload['content'] as $block ) {
				if ( is_array( $block ) && isset( $block['text'] ) && is_string( $block['text'] ) ) {
					$chunks[] = $block['text'];
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
