<?php
/**
 * SSE (Server-Sent Events) response helper.
 *
 * @package OpenWP\Inc\API
 */

namespace OpenWP\Inc\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Server-Sent Events output from WordPress REST endpoints.
 */
class SSE_Response {
	/**
	 * Start an SSE stream by setting headers and disabling output buffering.
	 *
	 * @return void
	 */
	public static function start() {
		// Prevent WordPress from compressing output.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		// Extend PHP execution time for long-running streams.
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}

		// Stop if the client disconnects.
		ignore_user_abort( false );

		// Disable all output buffering layers.
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		header( 'Content-Type: text/event-stream; charset=UTF-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'X-Accel-Buffering: no' ); // Nginx.
		header( 'X-Content-Type-Options: nosniff' );

		// Flush headers immediately.
		if ( function_exists( 'flush' ) ) {
			flush();
		}
	}

	/**
	 * Send a single SSE data line.
	 *
	 * @param array<string,mixed> $data Payload to JSON-encode and send.
	 * @return void
	 */
	public static function send( $data ) {
		if ( connection_aborted() ) {
			return;
		}

		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) ) {
			return;
		}

		echo 'data: ' . $json . "\n\n";

		if ( function_exists( 'flush' ) ) {
			flush();
		}
	}

	/**
	 * Send the terminal [DONE] marker and exit.
	 *
	 * @return void
	 */
	public static function done() {
		if ( ! connection_aborted() ) {
			echo "data: [DONE]\n\n";
			if ( function_exists( 'flush' ) ) {
				flush();
			}
		}
		exit;
	}
}
