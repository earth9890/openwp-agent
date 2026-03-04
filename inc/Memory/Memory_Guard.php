<?php
/**
 * Memory validation and sanitization guard.
 *
 * @package OpenWP\Inc\Memory
 */

namespace OpenWP\Inc\Memory;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates memory payloads and blocks secret-like values.
 */
class Memory_Guard {
	/**
	 * Allowed memory types.
	 *
	 * @return string[]
	 */
	public static function allowed_types() {
		return [ 'preference', 'constraint', 'fact', 'workflow' ];
	}

	/**
	 * Sanitize memory type.
	 *
	 * @param mixed $type Raw type.
	 * @param bool  $allow_empty Allow empty values.
	 * @return string
	 */
	public function sanitize_type( $type, $allow_empty = false ) {
		$type = sanitize_key( (string) $type );
		if ( '' === $type && $allow_empty ) {
			return '';
		}

		return in_array( $type, self::allowed_types(), true ) ? $type : '';
	}

	/**
	 * Sanitize memory key as slug.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	public function sanitize_key( $key ) {
		$key = sanitize_title( (string) $key );
		return trim( $key );
	}

	/**
	 * Sanitize free-form text.
	 *
	 * @param mixed $text Raw text.
	 * @return string
	 */
	public function sanitize_text( $text ) {
		return trim( sanitize_textarea_field( (string) $text ) );
	}

	/**
	 * Sanitize tag list.
	 *
	 * @param mixed $tags Raw tags.
	 * @return string[]
	 */
	public function sanitize_tags( $tags ) {
		if ( ! is_array( $tags ) ) {
			return [];
		}

		$clean = [];
		foreach ( $tags as $tag ) {
			$value = sanitize_key( (string) $tag );
			if ( '' !== $value ) {
				$clean[] = $value;
			}
		}

		$clean = array_values( array_unique( $clean ) );
		return array_slice( $clean, 0, 10 );
	}

	/**
	 * Normalize list/search query.
	 *
	 * @param mixed $query Raw query.
	 * @return string
	 */
	public function sanitize_query( $query ) {
		$query = sanitize_text_field( (string) $query );

		if ( $this->strlen( $query ) > 120 ) {
			$query = function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 120 ) : substr( $query, 0, 120 );
		}

		return trim( $query );
	}

	/**
	 * Normalize per-page value.
	 *
	 * @param mixed $limit Raw limit.
	 * @param int   $default Default value.
	 * @param int   $max Max value.
	 * @return int
	 */
	public function sanitize_limit( $limit, $default = 50, $max = 200 ) {
		$limit = absint( $limit );
		if ( $limit < 1 ) {
			$limit = $default;
		}

		return min( $max, $limit );
	}

	/**
	 * Validate write payload.
	 *
	 * @param mixed $type Memory type.
	 * @param mixed $key Memory key.
	 * @param mixed $text Memory text.
	 * @param mixed $tags Memory tags.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate_payload( $type, $key, $text, $tags = [] ) {
		$type = $this->sanitize_type( $type );
		if ( '' === $type ) {
			return new WP_Error( 'openwp_memory_invalid_type', __( 'Invalid memory type.', 'openwp' ) );
		}

		$key = $this->sanitize_key( $key );
		if ( '' === $key ) {
			return new WP_Error( 'openwp_memory_key_required', __( 'Memory key is required.', 'openwp' ) );
		}

		if ( $this->strlen( $key ) > 120 ) {
			return new WP_Error( 'openwp_memory_key_too_long', __( 'Memory key is too long.', 'openwp' ) );
		}

		$text = $this->sanitize_text( $text );
		if ( '' === $text ) {
			return new WP_Error( 'openwp_memory_text_required', __( 'Memory text is required.', 'openwp' ) );
		}

		if ( $this->strlen( $text ) > 1000 ) {
			return new WP_Error( 'openwp_memory_text_too_long', __( 'Memory text exceeds maximum length.', 'openwp' ) );
		}

		if ( $this->contains_secret( $key ) || $this->contains_secret( $text ) ) {
			return new WP_Error( 'openwp_memory_secret_blocked', __( 'Potential secret detected. Memory was not saved.', 'openwp' ) );
		}

		$tags = $this->sanitize_tags( $tags );

		return [
			'type' => $type,
			'key'  => $key,
			'text' => $text,
			'tags' => $tags,
		];
	}

	/**
	 * Check text for secret-like patterns.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private function contains_secret( $value ) {
		if ( '' === $value ) {
			return false;
		}

		$patterns = [
			'/sk-[a-z0-9]{16,}/i',
			'/\b(?:api[_-]?key|access[_-]?token|auth[_-]?token|secret|password)\b/i',
			'/-----BEGIN [A-Z ]+ PRIVATE KEY-----/i',
			'/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/',
			'/\bAKIA[0-9A-Z]{16}\b/',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Multibyte-safe string length helper.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private function strlen( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
