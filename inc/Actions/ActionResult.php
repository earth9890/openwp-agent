<?php
/**
 * Action result value object.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standard action execution result.
 */
class ActionResult {
	/**
	 * Success flag.
	 *
	 * @var bool
	 */
	public $success;

	/**
	 * Human-readable message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Result payload.
	 *
	 * @var array<string,mixed>
	 */
	public $data;

	/**
	 * Rollback snapshot.
	 *
	 * @var array<string,mixed>
	 */
	public $rollback_snapshot;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Result args.
	 */
	public function __construct( $args = [] ) {
		$this->success           = ! empty( $args['success'] );
		$this->message           = isset( $args['message'] ) ? (string) $args['message'] : '';
		$this->data              = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : [];
		$this->rollback_snapshot = isset( $args['rollback_snapshot'] ) && is_array( $args['rollback_snapshot'] ) ? $args['rollback_snapshot'] : [];
	}

	/**
	 * Convert to array.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return [
			'success'           => $this->success,
			'message'           => $this->message,
			'data'              => $this->data,
			'rollback_snapshot' => $this->rollback_snapshot,
		];
	}
}
