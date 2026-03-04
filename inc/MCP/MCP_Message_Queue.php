<?php
/**
 * MCP transient-backed message queue.
 *
 * @package OpenWP\Inc\MCP
 */

namespace OpenWP\Inc\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores pending messages per session.
 */
class MCP_Message_Queue {
	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	private $prefix = 'openwp_mcp_msg';

	/**
	 * Store queue payload.
	 *
	 * @param string               $session_id Session id.
	 * @param array<string,mixed>  $payload Payload.
	 * @param int                  $ttl Time to live in seconds.
	 * @return void
	 */
	public function store( $session_id, $payload, $ttl = 180 ) {
		$session_id = $this->sanitize_session( $session_id );
		if ( '' === $session_id ) {
			return;
		}

		$id_key = array_key_exists( 'id', $payload ) ? (string) $payload['id'] : 'noid';
		$id_key = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', $id_key );
		if ( ! is_string( $id_key ) || '' === $id_key ) {
			$id_key = 'noid';
		}

		$uniq = str_replace( '.', '', uniqid( '', true ) );
		$key  = $this->transient_key( $session_id, $id_key . '_' . $uniq );

		set_transient( $key, $payload, max( 30, (int) $ttl ) );
	}

	/**
	 * Store kill signal.
	 *
	 * @param string $session_id Session id.
	 * @return void
	 */
	public function queue_kill( $session_id ) {
		$this->store(
			$session_id,
			[
				'jsonrpc' => '2.0',
				'method'  => 'openwp/kill',
			],
			60
		);
	}

	/**
	 * Fetch and remove queued messages.
	 *
	 * @param string $session_id Session id.
	 * @return array<int,array<string,mixed>>
	 */
	public function fetch( $session_id ) {
		global $wpdb;

		$session_id = $this->sanitize_session( $session_id );
		if ( '' === $session_id ) {
			return [];
		}

		$like = $wpdb->esc_like( '_transient_' . $this->prefix . '_' . $session_id . '_' ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like ),
			ARRAY_A
		);

		$messages = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( ! isset( $row['option_name'] ) ) {
				continue;
			}

			$option_name = (string) $row['option_name'];
			$value       = maybe_unserialize( $row['option_value'] ?? '' );
			if ( is_array( $value ) ) {
				$messages[] = $value;
			}

			delete_option( $option_name );
			delete_option( str_replace( '_transient_', '_transient_timeout_', $option_name ) );
		}

		usort(
			$messages,
			static function ( $a, $b ) {
				$aid = is_array( $a ) && isset( $a['id'] ) ? (int) $a['id'] : 0;
				$bid = is_array( $b ) && isset( $b['id'] ) ? (int) $b['id'] : 0;
				return $aid <=> $bid;
			}
		);

		return $messages;
	}

	/**
	 * Clear all queued messages for a session.
	 *
	 * @param string $session_id Session id.
	 * @return void
	 */
	public function clear( $session_id ) {
		global $wpdb;

		$session_id = $this->sanitize_session( $session_id );
		if ( '' === $session_id ) {
			return;
		}

		$like = $wpdb->esc_like( '_transient_' . $this->prefix . '_' . $session_id . '_' ) . '%';
		$rows = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);

		foreach ( is_array( $rows ) ? $rows : [] as $option_name ) {
			$option_name = (string) $option_name;
			if ( '' === $option_name ) {
				continue;
			}
			delete_option( $option_name );
			delete_option( str_replace( '_transient_', '_transient_timeout_', $option_name ) );
		}
	}

	/**
	 * Build transient key.
	 *
	 * @param string $session_id Session id.
	 * @param string $id_key Message id key.
	 * @return string
	 */
	private function transient_key( $session_id, $id_key ) {
		return $this->prefix . '_' . $session_id . '_' . $id_key;
	}

	/**
	 * Sanitize session id.
	 *
	 * @param string $session_id Session.
	 * @return string
	 */
	private function sanitize_session( $session_id ) {
		$session_id = sanitize_text_field( (string) $session_id );
		$session_id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $session_id );
		return is_string( $session_id ) ? $session_id : '';
	}
}
