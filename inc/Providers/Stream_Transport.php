<?php
/**
 * Streaming transport helpers for provider clients.
 *
 * @package OpenWP\Inc\Providers
 */

namespace OpenWP\Inc\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers for HTTP and SSE parsing.
 */
class Stream_Transport {
	/**
	 * Execute POST request and optionally force streamed transfer to temp file.
	 *
	 * @param string $url Endpoint URL.
	 * @param array<string,string> $headers HTTP headers.
	 * @param array<string,mixed>  $body JSON request body.
	 * @param int                  $timeout Timeout seconds.
	 * @param bool                 $use_stream Whether to request stream transfer.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function post_json( $url, $headers, $body, $timeout, $use_stream = true ) {
		$temp_file = '';

		$args = [
			'timeout' => max( 10, (int) $timeout ),
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		];

		if ( $use_stream ) {
			$temp_file = self::create_temp_stream_file();
			if ( is_string( $temp_file ) && '' !== $temp_file ) {
				$args['stream']   = true;
				$args['filename'] = $temp_file;
			}
		}

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			self::cleanup_temp_file( $temp_file );
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body_text = '';

		if ( is_string( $temp_file ) && '' !== $temp_file && file_exists( $temp_file ) ) {
			$content = file_get_contents( $temp_file );
			if ( false !== $content ) {
				$body_text = (string) $content;
			}
			self::cleanup_temp_file( $temp_file );
		}

		if ( '' === $body_text ) {
			$body_text = (string) wp_remote_retrieve_body( $response );
		}

		return [
			'status' => $status,
			'body'   => $body_text,
		];
	}

	/**
	 * Execute POST request with real-time cURL streaming via CURLOPT_WRITEFUNCTION.
	 *
	 * Instead of buffering to a temp file, this calls $on_chunk for each parsed SSE event
	 * as it arrives from the upstream provider.
	 *
	 * @param string              $url     Endpoint URL.
	 * @param array<string,string> $headers HTTP headers.
	 * @param array<string,mixed>  $body    JSON request body.
	 * @param int                  $timeout Timeout seconds.
	 * @param callable            $on_chunk Callback receiving a decoded SSE event array.
	 * @return array{status:int}|WP_Error Final HTTP status or error.
	 */
	public static function post_json_stream( $url, $headers, $body, $timeout, callable $on_chunk ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return new WP_Error( 'openwp_curl_missing', __( 'cURL extension is required for streaming.', 'openwp' ) );
		}

		$ch = curl_init();
		if ( false === $ch ) {
			return new WP_Error( 'openwp_curl_init_failed', __( 'Failed to initialize cURL.', 'openwp' ) );
		}

		$curl_headers = [];
		foreach ( $headers as $key => $value ) {
			$curl_headers[] = $key . ': ' . $value;
		}

		$json_body = wp_json_encode( $body );
		if ( ! is_string( $json_body ) ) {
			return new WP_Error( 'openwp_json_encode_failed', __( 'Failed to encode request body.', 'openwp' ) );
		}

		curl_setopt_array(
			$ch,
			[
				CURLOPT_URL            => $url,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $json_body,
				CURLOPT_HTTPHEADER     => $curl_headers,
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_TIMEOUT        => max( 10, (int) $timeout ),
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_HEADER         => false,
			]
		);

		$buffer      = '';
		$http_status = 0;

		// Capture HTTP status code from the first response.
		curl_setopt(
			$ch,
			CURLOPT_HEADERFUNCTION,
			static function ( $curl_handle, $header_line ) use ( &$http_status ) {
				if ( preg_match( '/^HTTP\/[\d.]+ (\d{3})/', $header_line, $m ) ) {
					$http_status = (int) $m[1];
				}
				return strlen( $header_line );
			}
		);

		// Process incoming data chunks incrementally.
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			static function ( $curl_handle, $data ) use ( &$buffer, $on_chunk ) {
				$buffer .= $data;

				// Split buffer on double newlines (SSE event boundary).
				while ( false !== ( $pos = strpos( $buffer, "\n\n" ) ) ) {
					$raw_event = substr( $buffer, 0, $pos );
					$buffer    = substr( $buffer, $pos + 2 );

					$lines      = preg_split( '/\r\n|\r|\n/', $raw_event );
					$data_lines = [];
					if ( is_array( $lines ) ) {
						foreach ( $lines as $line ) {
							$line = (string) $line;
							if ( 0 === strpos( $line, 'data:' ) ) {
								$data_lines[] = ltrim( substr( $line, 5 ) );
							}
						}
					}

					if ( empty( $data_lines ) ) {
						continue;
					}

					$payload = trim( implode( "\n", $data_lines ) );
					if ( '' === $payload || '[DONE]' === $payload ) {
						continue;
					}

					$decoded = json_decode( $payload, true );
					if ( is_array( $decoded ) ) {
						$on_chunk( $decoded );
					}
				}

				return strlen( $data );
			}
		);

		$success = curl_exec( $ch );
		if ( false === $success ) {
			$error_msg = curl_error( $ch );
			curl_close( $ch );
			return new WP_Error( 'openwp_curl_error', $error_msg ?: __( 'cURL request failed.', 'openwp' ) );
		}

		// If there is leftover data in the buffer (non-SSE error body), try to
		// parse it as JSON and forward to the callback so error details are captured.
		$remaining = trim( $buffer );
		if ( '' !== $remaining ) {
			$leftover = json_decode( $remaining, true );
			if ( is_array( $leftover ) ) {
				$on_chunk( $leftover );
			}
		}

		if ( 0 === $http_status ) {
			$http_status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		}

		curl_close( $ch );

		return [ 'status' => $http_status ];
	}

	/**
	 * Parse Server-Sent Events payload into decoded JSON objects.
	 *
	 * @param string $body Raw SSE payload.
	 * @return array<int,array<string,mixed>>
	 */
	public static function parse_sse_json_events( $body ) {
		$events     = [];
		$data_lines = [];
		$lines      = preg_split( '/\r\n|\r|\n/', (string) $body );

		if ( ! is_array( $lines ) ) {
			return [];
		}

		$flush = static function () use ( &$data_lines, &$events ) {
			if ( empty( $data_lines ) ) {
				return;
			}

			$payload    = trim( implode( "\n", $data_lines ) );
			$data_lines = [];

			if ( '' === $payload || '[DONE]' === $payload ) {
				return;
			}

			$decoded = json_decode( $payload, true );
			if ( is_array( $decoded ) ) {
				$events[] = $decoded;
			}
		};

		foreach ( $lines as $line ) {
			$line = (string) $line;

			if ( '' === trim( $line ) ) {
				$flush();
				continue;
			}

			if ( 0 === strpos( $line, 'data:' ) ) {
				$data_lines[] = ltrim( substr( $line, 5 ) );
			}
		}

		$flush();

		return $events;
	}

	/**
	 * Delete temporary stream file.
	 *
	 * @param string $temp_file Temp file path.
	 * @return void
	 */
	private static function cleanup_temp_file( $temp_file ) {
		if ( is_string( $temp_file ) && '' !== $temp_file && file_exists( $temp_file ) ) {
			unlink( $temp_file );
		}
	}

	/**
	 * Create a temporary file path for streamed HTTP responses.
	 *
	 * @return string
	 */
	private static function create_temp_stream_file() {
		if ( ! function_exists( 'wp_tempnam' ) && defined( 'ABSPATH' ) ) {
			$wp_file_api = ABSPATH . 'wp-admin/includes/file.php';
			if ( is_string( $wp_file_api ) && '' !== $wp_file_api && file_exists( $wp_file_api ) ) {
				require_once $wp_file_api;
			}
		}

		if ( function_exists( 'wp_tempnam' ) ) {
			$temp_file = \wp_tempnam( 'openwp-provider-stream' );
			if ( is_string( $temp_file ) && '' !== $temp_file ) {
				return $temp_file;
			}
		}

		$temp_dir = function_exists( 'sys_get_temp_dir' ) ? (string) sys_get_temp_dir() : '';
		if ( '' === $temp_dir ) {
			$temp_dir = '.';
		}

		$fallback = \tempnam( $temp_dir, 'openwp-provider-stream-' );
		return is_string( $fallback ) ? $fallback : '';
	}
}
