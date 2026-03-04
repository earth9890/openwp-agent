<?php
/**
 * Streamable HTTP transport for MCP.
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
 * Handles modern Streamable HTTP MCP transport.
 */
class MCP_HTTP_Transport {
	/**
	 * Queue.
	 *
	 * @var MCP_Message_Queue
	 */
	private $queue;

	/**
	 * Session helper.
	 *
	 * @var MCP_Session
	 */
	private $session;

	/**
	 * Constructor.
	 *
	 * @param MCP_Message_Queue $queue Queue.
	 * @param MCP_Session       $session Session helper.
	 */
	public function __construct( MCP_Message_Queue $queue, MCP_Session $session ) {
		$this->queue   = $queue;
		$this->session = $session;
	}

	/**
	 * Handle GET|POST|DELETE transport methods.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @param callable                              $post_handler Handler for POST.
	 * @param bool                                  $debug Debug mode.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	public function handle( WP_REST_Request $request, callable $post_handler, $debug = false ) {
		$method = strtoupper( (string) $request->get_method() );

		if ( 'POST' === $method ) {
			return $post_handler( $request );
		}

		if ( 'GET' === $method ) {
			return $this->handle_get( $request, $debug );
		}

		if ( 'DELETE' === $method ) {
			return $this->handle_delete( $request );
		}

		return new WP_REST_Response( [ 'error' => 'Method not allowed.' ], 405 );
	}

	/**
	 * Handle HTTP GET SSE stream.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @param bool                                  $debug Debug mode.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	private function handle_get( WP_REST_Request $request, $debug ) {
		$accept = (string) $request->get_header( 'accept' );
		if ( false === strpos( strtolower( $accept ), 'text/event-stream' ) ) {
			return new WP_REST_Response( [ 'error' => 'Accept header must include text/event-stream.' ], 406 );
		}

		$session_id = $this->session->from_header( $request );
		if ( '' === $session_id ) {
			$session_id = $this->session->create();
		}

		@ini_set( 'zlib.output_compression', '0' );
		@ini_set( 'output_buffering', '0' );
		@ini_set( 'implicit_flush', '1' );
		if ( function_exists( 'ob_implicit_flush' ) ) {
			ob_implicit_flush( true );
		}

		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );
		header( 'Mcp-Session-Id: ' . $session_id );

		while ( ob_get_level() ) {
			ob_end_flush();
		}

		echo "event: open\n";
		echo 'data: ' . wp_json_encode( [ 'session' => $session_id ] ) . "\n\n";
		flush();

		$last_activity = time();
		$max_time      = $debug ? 30 : 180;

		while ( true ) {
			$idle = ( time() - $last_activity ) >= $max_time;
			if ( connection_aborted() || $idle ) {
				break;
			}

			$messages = $this->queue->fetch( $session_id );
			if ( ! empty( $messages ) ) {
				foreach ( $messages as $message ) {
					if ( isset( $message['method'] ) && 'openwp/kill' === $message['method'] ) {
						echo "event: close\n";
						echo "data: {}\n\n";
						flush();
						exit;
					}
					echo "event: message\n";
					echo 'data: ' . wp_json_encode( $message, JSON_UNESCAPED_UNICODE ) . "\n\n";
					flush();
					$last_activity = time();
				}
			}

			$elapsed = time() - $last_activity;
			if ( $elapsed >= 10 && 0 === $elapsed % 10 ) {
				echo ": heartbeat\n\n";
				flush();
			}

			usleep( 200000 );
		}

		exit;
	}

	/**
	 * Handle HTTP DELETE session termination.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	private function handle_delete( WP_REST_Request $request ) {
		$session_id = $this->session->from_header( $request );
		if ( '' === $session_id ) {
			return new WP_REST_Response( [ 'error' => 'Mcp-Session-Id header is required.' ], 400 );
		}

		$this->queue->clear( $session_id );
		$this->queue->queue_kill( $session_id );

		return new WP_REST_Response( null, 204 );
	}
}
