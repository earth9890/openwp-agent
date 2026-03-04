<?php
/**
 * Action context value object.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime context for action execution.
 */
class ActionContext {
	/**
	 * Requesting user id.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Prompt text.
	 *
	 * @var string
	 */
	public $prompt;

	/**
	 * Parsed model output.
	 *
	 * @var array<string,mixed>
	 */
	public $model_output;

	/**
	 * Optional approver user id.
	 *
	 * @var int
	 */
	public $approver_user_id;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Context args.
	 */
	public function __construct( $args = [] ) {
		$this->user_id          = absint( $args['user_id'] ?? get_current_user_id() );
		$this->prompt           = isset( $args['prompt'] ) ? (string) $args['prompt'] : '';
		$this->model_output     = isset( $args['model_output'] ) && is_array( $args['model_output'] ) ? $args['model_output'] : [];
		$this->approver_user_id = absint( $args['approver_user_id'] ?? 0 );
	}
}
