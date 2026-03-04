<?php
/**
 * Sitewide chatbot attachment service.
 *
 * @package OpenWP\Inc\Chatbot
 */

namespace OpenWP\Inc\Chatbot;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles temporary file uploads for the sitewide chatbot.
 */
class Attachment_Service {
	/**
	 * Temporary session retention (seconds).
	 *
	 * @var int
	 */
	private const SESSION_TTL = DAY_IN_SECONDS;

	/**
	 * Maximum files allowed in a chatbot session.
	 *
	 * @var int
	 */
	private const MAX_FILES_PER_SESSION = 5;

	/**
	 * Maximum attachment size in bytes (10 MB).
	 *
	 * @var int
	 */
	private const MAX_FILE_SIZE_BYTES = 10485760;

	/**
	 * Maximum extracted text length per file.
	 *
	 * @var int
	 */
	private const MAX_EXTRACTED_TEXT_CHARS = 8000;

	/**
	 * Upload subdirectory under wp-content/uploads.
	 *
	 * @var string
	 */
	private const UPLOAD_SUBDIR = 'openwp-chatbot-temp';

	/**
	 * Session index option key.
	 *
	 * @var string
	 */
	private const SESSION_INDEX_OPTION = 'openwp_chatbot_session_index';

	/**
	 * Max session token length.
	 *
	 * @var int
	 */
	private const MAX_SESSION_TOKEN_LEN = 128;

	/**
	 * Blocked executable extension list.
	 *
	 * @var string[]
	 */
	private const BLOCKED_EXTENSIONS = [
		'php',
		'phtml',
		'php3',
		'php4',
		'php5',
		'phar',
		'cgi',
		'pl',
		'py',
		'sh',
		'bash',
		'ksh',
		'csh',
		'exe',
		'com',
		'bat',
		'cmd',
		'msi',
		'vb',
		'vbs',
		'js',
		'jar',
	];

	/**
	 * Text-like extensions eligible for extraction.
	 *
	 * @var string[]
	 */
	private const TEXT_EXTENSIONS = [
		'txt',
		'md',
		'csv',
		'json',
		'xml',
		'html',
		'htm',
		'log',
		'yaml',
		'yml',
	];

	/**
	 * Upload attachment for a chatbot session.
	 *
	 * @param int                 $user_id User ID.
	 * @param string              $session_token Session token.
	 * @param array<string,mixed> $file_array Uploaded file array.
	 * @return array<string,mixed>|WP_Error
	 */
	public function upload( $user_id, $session_token, $file_array ) {
		$session_key = $this->session_key( $user_id, $session_token );
		if ( is_wp_error( $session_key ) ) {
			return $session_key;
		}

		$items = $this->get_session_items( $session_key );
		if ( count( $items ) >= self::MAX_FILES_PER_SESSION ) {
			return new WP_Error( 'openwp_chatbot_file_limit', __( 'Attachment limit reached for this chat session.', 'openwp' ) );
		}

		if ( ! is_array( $file_array ) || empty( $file_array['tmp_name'] ) ) {
			return new WP_Error( 'openwp_chatbot_upload_missing_file', __( 'No file uploaded.', 'openwp' ) );
		}

		$error_code = isset( $file_array['error'] ) ? (int) $file_array['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error_code ) {
			return new WP_Error( 'openwp_chatbot_upload_error', $this->upload_error_message( $error_code ) );
		}

		$file_size = isset( $file_array['size'] ) ? absint( $file_array['size'] ) : 0;
		if ( $file_size <= 0 ) {
			return new WP_Error( 'openwp_chatbot_upload_empty', __( 'Uploaded file is empty.', 'openwp' ) );
		}

		if ( $file_size > self::MAX_FILE_SIZE_BYTES ) {
			return new WP_Error( 'openwp_chatbot_upload_too_large', __( 'File exceeds the 10MB limit.', 'openwp' ) );
		}

		$original_name = sanitize_file_name( (string) ( $file_array['name'] ?? '' ) );
		if ( '' === $original_name ) {
			$original_name = 'attachment';
		}

		$extension = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		if ( '' !== $extension && in_array( $extension, self::BLOCKED_EXTENSIONS, true ) ) {
			return new WP_Error( 'openwp_chatbot_upload_type_blocked', __( 'This file type is not allowed for chatbot uploads.', 'openwp' ) );
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'openwp_chatbot_upload_dir_error', (string) $upload_dir['error'] );
		}

		$base_dir = trailingslashit( (string) $upload_dir['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( ! wp_mkdir_p( $base_dir ) ) {
			return new WP_Error( 'openwp_chatbot_upload_dir_create_failed', __( 'Unable to create chatbot upload directory.', 'openwp' ) );
		}

		$stored_name = $this->generate_stored_filename( $original_name, $extension );
		$file_path   = trailingslashit( $base_dir ) . $stored_name;

		$tmp_path = (string) $file_array['tmp_name'];
		$moved    = @move_uploaded_file( $tmp_path, $file_path );
		if ( ! $moved ) {
			$moved = @copy( $tmp_path, $file_path );
			if ( $moved ) {
				@unlink( $tmp_path );
			}
		}

		if ( ! $moved || ! file_exists( $file_path ) ) {
			return new WP_Error( 'openwp_chatbot_upload_move_failed', __( 'Unable to store uploaded file.', 'openwp' ) );
		}

		$detected_mime = $this->detect_mime_type( $stored_name, $file_array['type'] ?? '' );
		$extraction    = $this->extract_text_payload( $file_path, $extension, $detected_mime );

		$item = [
			'id'                => wp_generate_uuid4(),
			'original_name'     => $original_name,
			'stored_name'       => $stored_name,
			'mime_type'         => $detected_mime,
			'size_bytes'        => (int) $file_size,
			'file_path'         => $file_path,
			'url'               => trailingslashit( (string) $upload_dir['baseurl'] ) . self::UPLOAD_SUBDIR . '/' . $stored_name,
			'created_at'        => time(),
			'extract_text'      => $extraction['text'],
			'extract_truncated' => ! empty( $extraction['truncated'] ),
		];

		$items[] = $item;
		$this->set_session_items( $session_key, $items );
		$this->touch_session_index( $session_key );

		return [
			'item'        => $this->public_item( $item ),
			'total_files' => count( $items ),
		];
	}

	/**
	 * List attachment items in a session.
	 *
	 * @param int    $user_id User ID.
	 * @param string $session_token Session token.
	 * @return array<int,array<string,mixed>>
	 */
	public function list_session_items( $user_id, $session_token ) {
		$session_key = $this->session_key( $user_id, $session_token );
		if ( is_wp_error( $session_key ) ) {
			return [];
		}

		$items   = $this->get_session_items( $session_key );
		$publics = [];
		foreach ( $items as $item ) {
			$publics[] = $this->public_item( $item );
		}

		return $publics;
	}

	/**
	 * Resolve selected attachment IDs for a session.
	 *
	 * @param int                     $user_id User ID.
	 * @param string                  $session_token Session token.
	 * @param array<int|string,mixed> $attachment_ids Attachment IDs.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function resolve_selected_items( $user_id, $session_token, $attachment_ids ) {
		$session_key = $this->session_key( $user_id, $session_token );
		if ( is_wp_error( $session_key ) ) {
			return $session_key;
		}

		$items = $this->get_session_items( $session_key );
		if ( empty( $items ) || ! is_array( $attachment_ids ) ) {
			return [];
		}

		$wanted = [];
		foreach ( $attachment_ids as $attachment_id ) {
			$id = sanitize_text_field( (string) $attachment_id );
			if ( '' !== $id ) {
				$wanted[ $id ] = true;
			}
		}

		if ( empty( $wanted ) ) {
			return [];
		}

		$selected = [];
		foreach ( $items as $item ) {
			$item_id = isset( $item['id'] ) ? sanitize_text_field( (string) $item['id'] ) : '';
			if ( '' !== $item_id && isset( $wanted[ $item_id ] ) ) {
				$selected[] = $item;
			}
		}

		return $selected;
	}

	/**
	 * Build attachment context block for prompt injection.
	 *
	 * @param array<int,array<string,mixed>> $items Selected attachment items.
	 * @return string
	 */
	public function build_attachment_prompt_block( $items ) {
		if ( ! is_array( $items ) || empty( $items ) ) {
			return '';
		}

		$payload = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$payload[] = [
				'id'                => sanitize_text_field( (string) ( $item['id'] ?? '' ) ),
				'original_name'     => sanitize_text_field( (string) ( $item['original_name'] ?? '' ) ),
				'mime_type'         => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
				'size_bytes'        => absint( $item['size_bytes'] ?? 0 ),
				'url'               => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				'extract_text'      => isset( $item['extract_text'] ) ? (string) $item['extract_text'] : '',
				'extract_truncated' => ! empty( $item['extract_truncated'] ),
			];
		}

		if ( empty( $payload ) ) {
			return '';
		}

		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return '';
		}

		return "\n\nATTACHMENTS_CONTEXT=" . $encoded . "\nUse attachment context only when relevant to the user request.";
	}

	/**
	 * Clear a chatbot session and delete temp files.
	 *
	 * @param int    $user_id User ID.
	 * @param string $session_token Session token.
	 * @return array<string,mixed>|WP_Error
	 */
	public function clear_session( $user_id, $session_token ) {
		$session_key = $this->session_key( $user_id, $session_token );
		if ( is_wp_error( $session_key ) ) {
			return $session_key;
		}

		$items   = $this->get_session_items( $session_key );
		$deleted = 0;
		foreach ( $items as $item ) {
			$file_path = isset( $item['file_path'] ) ? (string) $item['file_path'] : '';
			if ( '' !== $file_path && file_exists( $file_path ) ) {
				if ( self::delete_file( $file_path ) ) {
					++$deleted;
				}
			}
		}

		delete_transient( $session_key );
		$this->remove_session_index( $session_key );

		return [
			'success'       => true,
			'deleted_files' => $deleted,
		];
	}

	/**
	 * Cleanup expired chatbot temp files and stale session transients.
	 *
	 * @return void
	 */
	public static function cleanup_expired_files() {
		$now = time();

		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['error'] ) ) {
			$base_dir = trailingslashit( (string) $upload_dir['basedir'] ) . self::UPLOAD_SUBDIR;
			if ( is_dir( $base_dir ) ) {
				$files = glob( trailingslashit( $base_dir ) . '*' );
				if ( is_array( $files ) ) {
					foreach ( $files as $file_path ) {
						if ( ! is_string( $file_path ) || ! is_file( $file_path ) ) {
							continue;
						}

						$file_mtime = filemtime( $file_path );
						if ( false !== $file_mtime && ( $now - (int) $file_mtime ) > self::SESSION_TTL ) {
							self::delete_file( $file_path );
						}
					}
				}
			}
		}

		$index = get_option( self::SESSION_INDEX_OPTION, [] );
		if ( ! is_array( $index ) ) {
			return;
		}

		$changed = false;
		foreach ( $index as $session_key => $last_seen ) {
			$last_seen = absint( $last_seen );
			if ( '' === (string) $session_key || $last_seen <= 0 ) {
				unset( $index[ $session_key ] );
				$changed = true;
				continue;
			}

			if ( ( $now - $last_seen ) > self::SESSION_TTL ) {
				delete_transient( (string) $session_key );
				unset( $index[ $session_key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( self::SESSION_INDEX_OPTION, $index, false );
		}
	}

	/**
	 * Build the transient key for a user + session token.
	 *
	 * @param int    $user_id User ID.
	 * @param string $session_token Session token.
	 * @return string|WP_Error
	 */
	private function session_key( $user_id, $session_token ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'openwp_chatbot_invalid_user', __( 'Invalid user context for chatbot session.', 'openwp' ) );
		}

		$session_token = sanitize_text_field( (string) $session_token );
		if ( '' === $session_token || strlen( $session_token ) > self::MAX_SESSION_TOKEN_LEN ) {
			return new WP_Error( 'openwp_chatbot_invalid_session', __( 'Invalid chatbot session token.', 'openwp' ) );
		}

		if ( ! preg_match( '/^[A-Za-z0-9_\-]+$/', $session_token ) ) {
			return new WP_Error( 'openwp_chatbot_invalid_session', __( 'Invalid chatbot session token.', 'openwp' ) );
		}

		return 'openwp_chatbot_session_' . $user_id . '_' . sha1( $session_token );
	}

	/**
	 * Fetch session items from transient.
	 *
	 * @param string $session_key Session key.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_session_items( $session_key ) {
		$raw = get_transient( $session_key );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$items = [];
		foreach ( $raw as $item ) {
			if ( is_array( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Persist session items with TTL.
	 *
	 * @param string                         $session_key Session key.
	 * @param array<int,array<string,mixed>> $items Items.
	 * @return void
	 */
	private function set_session_items( $session_key, $items ) {
		set_transient( $session_key, array_values( $items ), self::SESSION_TTL );
	}

	/**
	 * Add/update session key in cleanup index.
	 *
	 * @param string $session_key Session key.
	 * @return void
	 */
	private function touch_session_index( $session_key ) {
		$index = get_option( self::SESSION_INDEX_OPTION, [] );
		if ( ! is_array( $index ) ) {
			$index = [];
		}

		$index[ $session_key ] = time();
		if ( count( $index ) > 500 ) {
			asort( $index );
			$index = array_slice( $index, -500, null, true );
		}

		update_option( self::SESSION_INDEX_OPTION, $index, false );
	}

	/**
	 * Remove session key from cleanup index.
	 *
	 * @param string $session_key Session key.
	 * @return void
	 */
	private function remove_session_index( $session_key ) {
		$index = get_option( self::SESSION_INDEX_OPTION, [] );
		if ( ! is_array( $index ) || ! isset( $index[ $session_key ] ) ) {
			return;
		}

		unset( $index[ $session_key ] );
		update_option( self::SESSION_INDEX_OPTION, $index, false );
	}

	/**
	 * Return upload error message.
	 *
	 * @param int $error_code PHP upload error code.
	 * @return string
	 */
	private function upload_error_message( $error_code ) {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'Uploaded file is too large.', 'openwp' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'File upload was interrupted. Please retry.', 'openwp' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file uploaded.', 'openwp' );
			default:
				return __( 'File upload failed.', 'openwp' );
		}
	}

	/**
	 * Generate unique stored filename.
	 *
	 * @param string $original_name Original filename.
	 * @param string $extension File extension.
	 * @return string
	 */
	private function generate_stored_filename( $original_name, $extension ) {
		$base = sanitize_file_name( pathinfo( $original_name, PATHINFO_FILENAME ) );
		if ( '' === $base ) {
			$base = 'attachment';
		}

		$random = strtolower( wp_generate_password( 10, false, false ) );
		$suffix = '' !== $extension ? '.' . $extension : '';

		return $base . '-' . $random . $suffix;
	}

	/**
	 * Detect best-effort MIME type.
	 *
	 * @param string $stored_name Stored filename.
	 * @param mixed  $fallback Fallback mime string.
	 * @return string
	 */
	private function detect_mime_type( $stored_name, $fallback ) {
		$detected = wp_check_filetype( $stored_name );
		if ( isset( $detected['type'] ) && is_string( $detected['type'] ) && '' !== $detected['type'] ) {
			return $detected['type'];
		}

		$fallback = sanitize_text_field( (string) $fallback );
		if ( '' !== $fallback ) {
			return $fallback;
		}

		return 'application/octet-stream';
	}

	/**
	 * Build sanitized public metadata for frontend UI.
	 *
	 * @param array<string,mixed> $item Session attachment item.
	 * @return array<string,mixed>
	 */
	private function public_item( $item ) {
		return [
			'id'            => sanitize_text_field( (string) ( $item['id'] ?? '' ) ),
			'original_name' => sanitize_text_field( (string) ( $item['original_name'] ?? '' ) ),
			'mime_type'     => sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ),
			'size_bytes'    => absint( $item['size_bytes'] ?? 0 ),
			'url'           => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
			'created_at'    => absint( $item['created_at'] ?? 0 ),
		];
	}

	/**
	 * Extract text payload for text-like attachments.
	 *
	 * @param string $file_path Stored file path.
	 * @param string $extension File extension.
	 * @param string $mime_type Mime type.
	 * @return array<string,mixed>
	 */
	private function extract_text_payload( $file_path, $extension, $mime_type ) {
		$extension = strtolower( (string) $extension );
		$mime_type = strtolower( (string) $mime_type );

		$should_extract = in_array( $extension, self::TEXT_EXTENSIONS, true )
			|| 0 === strpos( $mime_type, 'text/' )
			|| 'application/json' === $mime_type
			|| 'application/xml' === $mime_type;

		if ( ! $should_extract || ! is_readable( $file_path ) ) {
			return [
				'text'      => '',
				'truncated' => false,
			];
		}

		$limit_plus_one = self::MAX_EXTRACTED_TEXT_CHARS + 1;
		$raw            = file_get_contents( $file_path, false, null, 0, $limit_plus_one );
		if ( false === $raw || '' === $raw ) {
			return [
				'text'      => '',
				'truncated' => false,
			];
		}

		$normalized = wp_check_invalid_utf8( (string) $raw, true );
		$normalized = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $normalized );
		$normalized = is_string( $normalized ) ? trim( $normalized ) : '';

		if ( '' === $normalized ) {
			return [
				'text'      => '',
				'truncated' => false,
			];
		}

		$truncated = false;
		if ( $this->string_length( $normalized ) > self::MAX_EXTRACTED_TEXT_CHARS ) {
			$truncated  = true;
			$normalized = $this->string_slice( $normalized, self::MAX_EXTRACTED_TEXT_CHARS );
		}

		return [
			'text'      => $normalized,
			'truncated' => $truncated,
		];
	}

	/**
	 * Multibyte-safe string length.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function string_length( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $text );
		}

		return (int) strlen( $text );
	}

	/**
	 * Multibyte-safe string slice.
	 *
	 * @param string $text Text.
	 * @param int    $max_len Max length.
	 * @return string
	 */
	private function string_slice( $text, $max_len ) {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, $max_len );
		}

		return (string) substr( $text, 0, $max_len );
	}

	/**
	 * Delete a file path safely.
	 *
	 * @param string $file_path File path.
	 * @return bool
	 */
	private static function delete_file( $file_path ) {
		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $file_path );
			return ! file_exists( $file_path );
		}

		return @unlink( $file_path );
	}
}
