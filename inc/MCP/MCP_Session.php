<?php
/**
 * MCP session helpers.
 *
 * @package OpenWP\Inc\MCP
 */

namespace OpenWP\Inc\MCP;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages MCP session IDs.
 */
class MCP_Session {
	/**
	 * Session header key.
	 *
	 * @var string
	 */
	private $session_header = 'Mcp-Session-Id';

	/**
	 * Create new session id.
	 *
	 * @return string
	 */
	public function create() {
		return wp_generate_uuid4();
	}

	/**
	 * Generate SSE id from Last-Event-ID header or fresh UUID.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return string
	 */
	public function generate_sse_id( WP_REST_Request $request ) {
		$last = (string) $request->get_header( 'last-event-id' );
		$last = $this->sanitize( $last );
		if ( '' !== $last ) {
			return $last;
		}

		return str_replace( '-', '', wp_generate_uuid4() );
	}

	/**
	 * Resolve session from request header.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return string
	 */
	public function from_header( WP_REST_Request $request ) {
		return $this->sanitize( (string) $request->get_header( 'mcp-session-id' ) );
	}

	/**
	 * Attach MCP session header to response.
	 *
	 * @param WP_REST_Response<array<string,mixed>|null> $response Response.
	 * @param string                                     $session_id Session id.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	public function attach_header( WP_REST_Response $response, $session_id ) {
		$session_id = $this->sanitize( $session_id );
		if ( '' === $session_id ) {
			return $response;
		}

		$response->header( $this->session_header, $session_id );

		return $response;
	}

	/**
	 * Sanitize session ids.
	 *
	 * @param string $session_id Raw id.
	 * @return string
	 */
	public function sanitize( $session_id ) {
		$session_id = sanitize_text_field( (string) $session_id );
		$session_id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $session_id );

		return is_string( $session_id ) ? $session_id : '';
	}
}
