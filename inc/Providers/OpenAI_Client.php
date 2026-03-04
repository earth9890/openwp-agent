<?php
/**
 * OpenAI provider client.
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
 * Handles OpenAI Responses API calls.
 */
class OpenAI_Client implements ProviderClientInterface {
	/**
	 * Generate completion from OpenAI Responses API.
	 *
	 * @param ProviderRequest $request Request payload.
	 * @return ProviderResponse|WP_Error
	 */
	public function generate( ProviderRequest $request ) {
		$key_manager = new Provider_Key_Manager();
		$api_key     = $key_manager->get_key( 'openai' );

		if ( '' === $api_key ) {
			return new WP_Error( 'openwp_openai_key_missing', __( 'OpenAI API key is not configured.', 'openwp' ) );
		}

		$system_prompt = $request->system_prompt;
		if ( ! empty( $request->schema ) ) {
			$system_prompt .= "\nReturn only valid JSON object matching this schema:\n" . wp_json_encode( $request->schema );
		}

		$body = [
			'model'  => $request->model,
			'stream' => (bool) $request->stream,
			'input'  => [
				[
					'role'    => 'system',
					'content' => $system_prompt,
				],
				[
					'role'    => 'user',
					'content' => $this->build_user_content_payload( $request ),
				],
			],
			'text'   => [
				'format' => [
					'type' => 'json_object',
				],
			],
		];

		if ( $this->supports_temperature( $request->model ) ) {
			$body['temperature'] = $request->temperature;
		}

		if ( $request->max_tokens > 0 ) {
			$body['max_output_tokens'] = $request->max_tokens;
		}

		$http = Stream_Transport::post_json(
			'https://api.openai.com/v1/responses',
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
			$message = $this->extract_error_message( $raw_body, $events, __( 'OpenAI request failed.', 'openwp' ) );
			return new WP_Error( 'openwp_openai_request_failed', $message, [ 'status' => $status ] );
		}

		if ( ! empty( $events ) ) {
			$parsed = $this->parse_stream_response( $events, $request->model );
			return new ProviderResponse(
				[
					'provider'      => 'openai',
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
			return new WP_Error( 'openwp_openai_invalid_json', __( 'OpenAI returned invalid JSON payload.', 'openwp' ) );
		}

		return new ProviderResponse(
			[
				'provider'      => 'openai',
				'model'         => (string) ( $raw['model'] ?? $request->model ),
				'output_text'   => $this->extract_text_from_response_object( $raw ),
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
		$api_key     = $key_manager->get_key( 'openai' );

		if ( '' === $api_key ) {
			return new WP_Error( 'openwp_openai_key_missing', __( 'OpenAI API key is not configured.', 'openwp' ) );
		}

		$system_prompt = $request->system_prompt;
		if ( ! empty( $request->schema ) ) {
			$system_prompt .= "\nReturn only valid JSON object matching this schema:\n" . wp_json_encode( $request->schema );
		}

		$body = [
			'model'  => $request->model,
			'stream' => true,
			'input'  => [
				[
					'role'    => 'system',
					'content' => $system_prompt,
				],
				[
					'role'    => 'user',
					'content' => $this->build_user_content_payload( $request ),
				],
			],
			'text'   => [
				'format' => [
					'type' => 'json_object',
				],
			],
		];

		if ( $this->supports_temperature( $request->model ) ) {
			$body['temperature'] = $request->temperature;
		}

		if ( $request->max_tokens > 0 ) {
			$body['max_output_tokens'] = $request->max_tokens;
		}

		$text           = '';
		$model          = $request->model;
		$input_tokens   = 0;
		$output_tokens  = 0;
		$final_response = [];

		$result = Stream_Transport::post_json_stream(
			'https://api.openai.com/v1/responses',
			[
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			$body,
			$request->timeout,
			static function ( $event ) use ( &$text, &$model, &$input_tokens, &$output_tokens, &$final_response, $on_token ) {
				$type = isset( $event['type'] ) ? (string) $event['type'] : '';

				if ( 'response.output_text.delta' === $type && isset( $event['delta'] ) && is_string( $event['delta'] ) ) {
					$text .= $event['delta'];
					$on_token( $event['delta'] );
				}

				if ( isset( $event['usage'] ) && is_array( $event['usage'] ) ) {
					$input_tokens  = (int) ( $event['usage']['input_tokens'] ?? $input_tokens );
					$output_tokens = (int) ( $event['usage']['output_tokens'] ?? $output_tokens );
				}

				if ( 'response.completed' === $type && isset( $event['response'] ) && is_array( $event['response'] ) ) {
					$final_response = $event['response'];
				}
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status = (int) ( $result['status'] ?? 0 );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'openwp_openai_request_failed', __( 'OpenAI request failed.', 'openwp' ), [ 'status' => $status ] );
		}

		if ( ! empty( $final_response ) ) {
			$model = (string) ( $final_response['model'] ?? $model );
			if ( isset( $final_response['usage'] ) && is_array( $final_response['usage'] ) ) {
				$input_tokens  = (int) ( $final_response['usage']['input_tokens'] ?? $input_tokens );
				$output_tokens = (int) ( $final_response['usage']['output_tokens'] ?? $output_tokens );
			}
			if ( '' === $text ) {
				$text = $this->extract_text_from_response_object( $final_response );
			}
		}

		return new ProviderResponse(
			[
				'provider'      => 'openai',
				'model'         => $model,
				'output_text'   => $text,
				'input_tokens'  => $input_tokens,
				'output_tokens' => $output_tokens,
				'raw'           => [
					'stream'   => true,
					'response' => $final_response,
				],
			]
		);
	}

	/**
	 * Build OpenAI Responses API user content payload.
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
						'type' => 'input_text',
						'text' => $text,
					];
				}
				continue;
			}

			if ( 'image_url' === $type ) {
				$url = trim( (string) ( $item['url'] ?? '' ) );
				if ( '' !== $url ) {
					$blocks[] = [
						'type'      => 'input_image',
						'image_url' => $url,
					];
				}
			}
		}

		return ! empty( $blocks ) ? $blocks : $request->prompt;
	}

	/**
	 * Parse streamed OpenAI events.
	 *
	 * @param array<int,array<string,mixed>> $events Stream events.
	 * @param string                          $fallback_model Fallback model name.
	 * @return array<string,mixed>
	 */
	private function parse_stream_response( $events, $fallback_model ) {
		$text           = '';
		$model          = $fallback_model;
		$input_tokens   = 0;
		$output_tokens  = 0;
		$final_response = [];

		foreach ( $events as $event ) {
			$type = isset( $event['type'] ) ? (string) $event['type'] : '';

			if ( 'response.output_text.delta' === $type && isset( $event['delta'] ) && is_string( $event['delta'] ) ) {
				$text .= $event['delta'];
			}

			if ( isset( $event['usage'] ) && is_array( $event['usage'] ) ) {
				$input_tokens  = (int) ( $event['usage']['input_tokens'] ?? $input_tokens );
				$output_tokens = (int) ( $event['usage']['output_tokens'] ?? $output_tokens );
			}

			if ( 'response.completed' === $type && isset( $event['response'] ) && is_array( $event['response'] ) ) {
				$final_response = $event['response'];
			}
		}

		if ( ! empty( $final_response ) ) {
			$model = (string) ( $final_response['model'] ?? $model );

			if ( '' === $text ) {
				$text = $this->extract_text_from_response_object( $final_response );
			}

			if ( isset( $final_response['usage'] ) && is_array( $final_response['usage'] ) ) {
				$input_tokens  = (int) ( $final_response['usage']['input_tokens'] ?? $input_tokens );
				$output_tokens = (int) ( $final_response['usage']['output_tokens'] ?? $output_tokens );
			}
		}

		if ( '' === $text ) {
			foreach ( array_reverse( $events ) as $event ) {
				$candidate = $this->extract_text_from_response_object( $event );
				if ( '' !== $candidate ) {
					$text = $candidate;
					break;
				}
			}
		}

		return [
			'model'         => $model,
			'output_text'   => $text,
			'input_tokens'  => $input_tokens,
			'output_tokens' => $output_tokens,
			'raw'           => [
				'stream'       => true,
				'events_count' => count( $events ),
				'response'     => $final_response,
			],
		];
	}

	/**
	 * Extract text from known OpenAI response object shapes.
	 *
	 * @param array<string,mixed> $payload Response payload.
	 * @return string
	 */
	private function extract_text_from_response_object( $payload ) {
		if ( isset( $payload['output_text'] ) && is_string( $payload['output_text'] ) ) {
			return $payload['output_text'];
		}

		if ( isset( $payload['output'][0]['content'] ) && is_array( $payload['output'][0]['content'] ) ) {
			$chunks = [];
			foreach ( $payload['output'][0]['content'] as $item ) {
				if ( is_array( $item ) && isset( $item['text'] ) && is_string( $item['text'] ) ) {
					$chunks[] = $item['text'];
				}
			}
			if ( ! empty( $chunks ) ) {
				return implode( '', $chunks );
			}
		}

		return '';
	}

	/**
	 * Whether the given model supports the temperature parameter.
	 *
	 * OpenAI reasoning models (o-series) and GPT-5 do not accept temperature.
	 *
	 * @param string $model Model identifier.
	 * @return bool
	 */
	private function supports_temperature( $model ) {
		return ! preg_match( '/^(o\d|gpt-5)/i', $model );
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
