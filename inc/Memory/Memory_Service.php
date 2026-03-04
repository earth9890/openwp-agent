<?php
/**
 * Memory service.
 *
 * @package OpenWP\Inc\Memory
 */

namespace OpenWP\Inc\Memory;

use OpenWP\Inc\Core\Settings;
use OpenWP\Inc\Logs\Log_Repository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates memory CRUD + prompt relevance retrieval.
 */
class Memory_Service {
	/**
	 * Storage repository.
	 *
	 * @var Memory_Repository
	 */
	private $repository;

	/**
	 * Validation guard.
	 *
	 * @var Memory_Guard
	 */
	private $guard;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new Memory_Repository();
		$this->guard      = new Memory_Guard();
	}

	/**
	 * Feature flag: memory enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$settings = Settings::get();
		return ! isset( $settings['openwp_memory_enabled'] ) || false !== $settings['openwp_memory_enabled'];
	}

	/**
	 * List memory records.
	 *
	 * @param array<string,mixed> $args List arguments.
	 * @return array<string,mixed>
	 */
	public function list( $args = [] ) {
		$args = is_array( $args ) ? $args : [];

		return $this->repository->list(
			[
				'page'     => max( 1, absint( $args['page'] ?? 1 ) ),
				'per_page' => $this->guard->sanitize_limit( $args['per_page'] ?? 50, 50, 200 ),
				'type'     => $this->guard->sanitize_type( $args['type'] ?? '', true ),
				'search'   => $this->guard->sanitize_query( $args['search'] ?? '' ),
			]
		);
	}

	/**
	 * Create or update a memory record.
	 *
	 * @param mixed               $type Memory type.
	 * @param mixed               $key Memory key.
	 * @param mixed               $text Memory text.
	 * @param mixed               $tags Memory tags.
	 * @param array<string,mixed> $meta Context metadata.
	 * @return array<string,mixed>|WP_Error
	 */
	public function remember( $type, $key, $text, $tags = [], $meta = [] ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'openwp_memory_disabled', __( 'Memory is disabled in settings.', 'openwp' ) );
		}

		$validated = $this->guard->validate_payload( $type, $key, $text, $tags );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$existing = $this->repository->get( $validated['type'], $validated['key'] );

		$existing_value = [];
		if ( is_array( $existing ) && isset( $existing['value'] ) && is_array( $existing['value'] ) ) {
			$existing_value = $existing['value'];
		}

		$created_by = isset( $existing_value['created_by'] ) ? absint( $existing_value['created_by'] ) : absint( $meta['user_id'] ?? get_current_user_id() );
		$created_at = isset( $existing_value['created_at'] ) ? sanitize_text_field( (string) $existing_value['created_at'] ) : gmdate( 'c' );

		$payload = [
			'text'       => $validated['text'],
			'tags'       => $validated['tags'],
			'created_by' => $created_by,
			'source'     => sanitize_key( (string) ( $meta['source'] ?? 'manual_action' ) ),
			'created_at' => $created_at,
			'updated_at' => gmdate( 'c' ),
		];

		$value_json = wp_json_encode( $payload );
		if ( ! is_string( $value_json ) ) {
			return new WP_Error( 'openwp_memory_encode_error', __( 'Failed to encode memory payload.', 'openwp' ) );
		}

		$stored = $this->repository->upsert( $validated['type'], $validated['key'], $value_json );
		if ( ! $stored ) {
			return new WP_Error( 'openwp_memory_store_failed', __( 'Failed to save memory.', 'openwp' ) );
		}

		$item = $this->repository->get( $validated['type'], $validated['key'] );
		if ( ! is_array( $item ) ) {
			return new WP_Error( 'openwp_memory_read_failed', __( 'Memory saved but could not be reloaded.', 'openwp' ) );
		}

		$created = ! is_array( $existing );

		if ( ! empty( $meta['audit'] ) ) {
			$this->log_memory_event(
				'remember_memory',
				$meta,
				[
					'type'    => $validated['type'],
					'key'     => $validated['key'],
					'created' => $created,
				],
				$item
			);
		}

		return [
			'created' => $created,
			'item'    => $item,
		];
	}

	/**
	 * Delete a memory record.
	 *
	 * @param mixed               $type Memory type.
	 * @param mixed               $key Memory key.
	 * @param array<string,mixed> $meta Context metadata.
	 * @return array<string,mixed>|WP_Error
	 */
	public function forget( $type, $key, $meta = [] ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'openwp_memory_disabled', __( 'Memory is disabled in settings.', 'openwp' ) );
		}

		$type = $this->guard->sanitize_type( $type );
		$key  = $this->guard->sanitize_key( $key );
		if ( '' === $type || '' === $key ) {
			return new WP_Error( 'openwp_memory_invalid_delete', __( 'Memory type and key are required.', 'openwp' ) );
		}

		$existing = $this->repository->get( $type, $key );
		if ( ! is_array( $existing ) ) {
			return [
				'deleted' => false,
				'type'    => $type,
				'key'     => $key,
			];
		}

		$deleted = $this->repository->delete( $type, $key );
		if ( ! $deleted ) {
			return new WP_Error( 'openwp_memory_delete_failed', __( 'Failed to delete memory.', 'openwp' ) );
		}

		if ( ! empty( $meta['audit'] ) ) {
			$this->log_memory_event(
				'forget_memory',
				$meta,
				[
					'type' => $type,
					'key'  => $key,
				],
				[
					'deleted' => true,
				]
			);
		}

		return [
			'deleted' => true,
			'type'    => $type,
			'key'     => $key,
		];
	}

	/**
	 * Delete all memory records.
	 *
	 * @param array<string,mixed> $meta Context metadata.
	 * @return array<string,mixed>|WP_Error
	 */
	public function clear_all( $meta = [] ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'openwp_memory_disabled', __( 'Memory is disabled in settings.', 'openwp' ) );
		}

		$deleted_count = $this->repository->delete_all();

		if ( ! empty( $meta['audit'] ) ) {
			$this->log_memory_event(
				'clear_memory',
				$meta,
				[ 'deleted_count' => $deleted_count ],
				[ 'cleared' => true, 'deleted_count' => $deleted_count ]
			);
		}

		return [
			'cleared'       => true,
			'deleted_count' => $deleted_count,
		];
	}

	/**
	 * Build prompt-relevant memory context lines.
	 *
	 * @param string $prompt User prompt.
	 * @param int    $limit Max entries.
	 * @param int    $max_chars Max character budget.
	 * @return array<int,array<string,mixed>>
	 */
	public function relevant_for_prompt( $prompt, $limit = 8, $max_chars = 1200 ) {
		if ( ! $this->is_enabled() ) {
			return [];
		}

		$prompt = trim( sanitize_text_field( (string) $prompt ) );
		if ( '' === $prompt ) {
			return [];
		}

		$tokens = $this->tokenize( $prompt );
		if ( empty( $tokens ) ) {
			return [];
		}

		$rows = $this->repository->fetch_recent( 200 );
		if ( empty( $rows ) ) {
			return [];
		}

		$scored = [];
		foreach ( $rows as $row ) {
			$score = $this->score_row( $row, $tokens );
			if ( $score <= 0 ) {
				continue;
			}

			$row['score']   = $score;
			$row['context'] = $this->format_context_line( $row );
			$scored[]       = $row;
		}

		if ( empty( $scored ) ) {
			return [];
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				$score_cmp = (float) $b['score'] <=> (float) $a['score'];
				if ( 0 !== $score_cmp ) {
					return $score_cmp;
				}

				return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
			}
		);

		$limit     = max( 1, min( 20, absint( $limit ) ) );
		$max_chars = max( 120, min( 5000, absint( $max_chars ) ) );
		$selected  = [];
		$used      = 0;

		foreach ( $scored as $row ) {
			if ( count( $selected ) >= $limit ) {
				break;
			}

			$line = (string) ( $row['context'] ?? '' );
			if ( '' === $line ) {
				continue;
			}

			$line_len = function_exists( 'mb_strlen' ) ? mb_strlen( $line ) : strlen( $line );
			if ( ! empty( $selected ) && $used + $line_len > $max_chars ) {
				break;
			}

			$used      += $line_len;
			$selected[] = $row;
		}

		return $selected;
	}

	/**
	 * Tokenize text for lexical matching.
	 *
	 * @param string $text Input text.
	 * @return string[]
	 */
	private function tokenize( $text ) {
		$text = strtolower( wp_strip_all_tags( $text ) );
		$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';
		if ( '' === $text ) {
			return [];
		}

		$parts = preg_split( '/\s+/', $text );
		if ( ! is_array( $parts ) ) {
			return [];
		}

		$stopwords = [
			'this',
			'that',
			'with',
			'from',
			'into',
			'about',
			'have',
			'your',
			'just',
			'then',
			'when',
			'what',
			'where',
			'will',
			'would',
			'should',
			'could',
			'please',
			'make',
			'need',
			'want',
			'site',
			'word',
			'press',
		];

		$tokens = [];
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( strlen( $part ) < 3 || in_array( $part, $stopwords, true ) ) {
				continue;
			}

			$tokens[] = $part;
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Score a memory row against prompt tokens.
	 *
	 * @param array<string,mixed> $row Memory row.
	 * @param string[]            $tokens Prompt tokens.
	 * @return float
	 */
	private function score_row( $row, $tokens ) {
		$type = isset( $row['type'] ) ? strtolower( (string) $row['type'] ) : '';
		$key  = isset( $row['key'] ) ? strtolower( (string) $row['key'] ) : '';
		$text = isset( $row['text'] ) ? strtolower( (string) $row['text'] ) : '';

		$tags = '';
		if ( isset( $row['tags'] ) && is_array( $row['tags'] ) ) {
			$tags = strtolower( implode( ' ', array_map( 'strval', $row['tags'] ) ) );
		}

		$haystack = trim( "{$type} {$key} {$text} {$tags}" );
		if ( '' === $haystack ) {
			return 0;
		}

		$score = 0.0;
		foreach ( $tokens as $token ) {
			if ( false !== strpos( $haystack, $token ) ) {
				$score += 1.5;
			}
			if ( false !== strpos( $key, $token ) ) {
				$score += 1.0;
			}
			if ( false !== strpos( $tags, $token ) ) {
				$score += 0.75;
			}
		}

		return $score;
	}

	/**
	 * Build human-readable context line for prompt injection.
	 *
	 * @param array<string,mixed> $row Memory row.
	 * @return string
	 */
	private function format_context_line( $row ) {
		$type = sanitize_key( (string) ( $row['type'] ?? '' ) );
		$key  = sanitize_title( (string) ( $row['key'] ?? '' ) );
		$text = trim( sanitize_textarea_field( (string) ( $row['text'] ?? '' ) ) );

		if ( '' === $type || '' === $key || '' === $text ) {
			return '';
		}

		$max_text = 220;
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max_text ) {
			$text = mb_substr( $text, 0, $max_text ) . '...';
		} elseif ( strlen( $text ) > $max_text ) {
			$text = substr( $text, 0, $max_text ) . '...';
		}

		$line = '[' . $type . '/' . $key . '] ' . $text;

		if ( ! empty( $row['tags'] ) && is_array( $row['tags'] ) ) {
			$line .= ' (tags: ' . implode( ', ', array_map( 'sanitize_key', $row['tags'] ) ) . ')';
		}

		return $line;
	}

	/**
	 * Write an audit log entry for REST memory operations.
	 *
	 * @param string              $action_key Action key.
	 * @param array<string,mixed> $meta Context metadata.
	 * @param array<string,mixed> $params Params.
	 * @param array<string,mixed> $result Result.
	 * @return void
	 */
	private function log_memory_event( $action_key, $meta, $params, $result ) {
		$logs = new Log_Repository();
		$logs->insert(
			[
				'user_id'      => absint( $meta['user_id'] ?? get_current_user_id() ),
				'action_key'   => sanitize_key( $action_key ),
				'risk_level'   => 'low',
				'prompt'       => sanitize_text_field( (string) ( $meta['prompt'] ?? 'Memory operation via REST API.' ) ),
				'model_output' => [
					'source' => sanitize_key( (string) ( $meta['source'] ?? 'rest_api' ) ),
				],
				'params'       => $params,
				'result'       => $result,
				'status'       => 'success',
			]
		);
	}
}
