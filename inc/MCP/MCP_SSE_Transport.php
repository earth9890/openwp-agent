<?php
/**
 * SSE transport for MCP.
 *
 * @package OpenWP\Inc\MCP
 */

namespace OpenWP\Inc\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Server-Sent Events streaming.
 */
class MCP_SSE_Transport {
	/**
	 * Queue.
	 *
	 * @var MCP_Message_Queue
	 */
	private $queue;

	/**
	 * Constructor.
	 *
	 * @param MCP_Message_Queue $queue Queue service.
	 */
	public function __construct( MCP_Message_Queue $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Start SSE stream loop.
	 *
	 * @param string $session_id Session id.
	 * @param string $message_url Message endpoint URL for client.
	 * @param bool   $debug Debug mode.
	 * @return void
	 */
	public function stream( $session_id, $message_url, $debug = false ) {
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

		while ( ob_get_level() ) {
			ob_end_flush();
		}

		echo 'id: ' . esc_html( $session_id ) . "\n\n";
		flush();

		$this->send_event( 'endpoint', $message_url, 'text' );
		$last_activity = time();
		$max_time      = $debug ? 30 : 180;

		while ( true ) {
			$idle = ( time() - $last_activity ) >= $max_time;
			if ( connection_aborted() || $idle ) {
				$this->send_event( 'bye', null, 'text' );
				break;
			}

			$messages = $this->queue->fetch( $session_id );
			if ( ! empty( $messages ) ) {
				foreach ( $messages as $message ) {
					if ( isset( $message['method'] ) && 'openwp/kill' === $message['method'] ) {
						$this->send_event( 'bye', null, 'text' );
						exit;
					}
					$this->send_event( 'message', $message );
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
	 * Output SSE event.
	 *
	 * @param string             $event Event name.
	 * @param mixed              $payload Payload.
	 * @param string             $encoding json|text.
	 * @return void
	 */
	private function send_event( $event, $payload = null, $encoding = 'json' ) {
		echo 'event: ' . $event . "\n";
		if ( 'json' === $encoding ) {
			$data = null === $payload ? '{}' : wp_json_encode( $payload, JSON_UNESCAPED_UNICODE );
			echo 'data: ' . $data . "\n\n";
		} else {
			echo 'data: ' . (string) $payload . "\n\n";
		}

		flush();
	}
}
