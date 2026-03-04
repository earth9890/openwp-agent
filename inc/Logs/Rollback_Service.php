<?php
/**
 * Rollback service.
 *
 * @package OpenWP\Inc\Logs
 */

namespace OpenWP\Inc\Logs;

use OpenWP\Inc\Actions\ActionContext;
use OpenWP\Inc\Actions\Action_Executor;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes rollback snapshots from action logs.
 */
class Rollback_Service {
	/**
	 * Log repository.
	 *
	 * @var Log_Repository
	 */
	private $logs;

	/**
	 * Action executor.
	 *
	 * @var Action_Executor
	 */
	private $executor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logs     = new Log_Repository();
		$this->executor = new Action_Executor();
	}

	/**
	 * Execute rollback by source log id.
	 *
	 * @param int $log_id Source log id.
	 * @return array<string,mixed>|WP_Error
	 */
	public function rollback( $log_id ) {
		$log = $this->logs->get( $log_id );
		if ( ! $log ) {
			return new WP_Error( 'openwp_log_not_found', __( 'Log entry not found.', 'openwp' ) );
		}

		$snapshot = isset( $log['rollback_snapshot'] ) && is_array( $log['rollback_snapshot'] ) ? $log['rollback_snapshot'] : [];
		if ( empty( $snapshot['rollback_action'] ) || empty( $snapshot['params'] ) || ! is_array( $snapshot['params'] ) ) {
			return new WP_Error( 'openwp_no_rollback_snapshot', __( 'This log entry does not contain rollback data.', 'openwp' ) );
		}

		$context = new ActionContext(
			[
				'user_id'          => get_current_user_id(),
				'prompt'           => 'Rollback request for log #' . absint( $log_id ),
				'model_output'     => [ 'type' => 'rollback' ],
				'approver_user_id' => get_current_user_id(),
			]
		);

		$result = $this->executor->execute(
			(string) $snapshot['rollback_action'],
			$snapshot['params'],
			$context,
			[
				'from_approval'    => true,
				'approver_user_id' => get_current_user_id(),
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['rollback_of_log_id'] = absint( $log_id );
		return $result;
	}
}
