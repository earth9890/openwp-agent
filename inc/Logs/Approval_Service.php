<?php
/**
 * Approval workflow service.
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
 * Approve/reject pending actions.
 */
class Approval_Service {
	/**
	 * Approval repository.
	 *
	 * @var Approval_Repository
	 */
	private $repository;

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
		$this->repository = new Approval_Repository();
		$this->executor   = new Action_Executor();
	}

	/**
	 * List approvals.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array<string,mixed>
	 */
	public function list( $args = [] ) {
		return $this->repository->list( $args );
	}

	/**
	 * Approve pending action and execute it.
	 *
	 * @param int    $approval_id Approval ID.
	 * @param int    $approver_user_id Approver ID.
	 * @param string $typed_confirmation Typed ack.
	 * @param string $note Decision note.
	 * @return array<string,mixed>|WP_Error
	 */
	public function approve( $approval_id, $approver_user_id, $typed_confirmation = '', $note = '' ) {
		$approval = $this->repository->get( $approval_id );
		if ( ! $approval ) {
			return new WP_Error( 'openwp_approval_not_found', __( 'Approval request not found.', 'openwp' ) );
		}

		if ( 'pending' !== (string) $approval['status'] ) {
			return new WP_Error( 'openwp_approval_not_pending', __( 'Approval request has already been processed.', 'openwp' ) );
		}

		$context = new ActionContext(
			[
				'user_id'          => absint( $approval['requester_user_id'] ),
				'prompt'           => is_array( $approval['prompt'] ) ? wp_json_encode( $approval['prompt'] ) : (string) $approval['prompt'],
				'model_output'     => is_array( $approval['model_output'] ) ? $approval['model_output'] : [],
				'approver_user_id' => $approver_user_id,
			]
		);

		$params = is_array( $approval['params'] ) ? $approval['params'] : [];

		$result = $this->executor->execute(
			(string) $approval['action_key'],
			$params,
			$context,
			[
				'from_approval'      => true,
				'approver_user_id'   => $approver_user_id,
				'typed_confirmation' => $typed_confirmation,
			]
		);

		if ( is_wp_error( $result ) ) {
			$this->repository->update(
				$approval_id,
				[
					'approver_user_id' => $approver_user_id,
					'status'           => 'failed',
					'decision_note'    => $note,
					'typed_confirmation' => $typed_confirmation,
				]
			);
			return $result;
		}

		$this->repository->update(
			$approval_id,
			[
				'approver_user_id' => $approver_user_id,
				'status'           => 'approved',
				'decision_note'    => $note,
				'typed_confirmation' => $typed_confirmation,
				'backup_id'        => absint( $result['backup_id'] ?? 0 ),
				'execution_log_id' => absint( $result['log_id'] ?? 0 ),
			]
		);

		$result['approval_id'] = absint( $approval_id );

		$continuation = $this->resume_multi_action_plan( $approval, $result, $approver_user_id );
		if ( is_array( $continuation ) ) {
			$result['continuation_result'] = $continuation;
		}

		return $result;
	}

	/**
	 * Resume remaining steps from a stored multi-action plan after approval.
	 *
	 * @param array<string,mixed> $approval Approval row.
	 * @param array<string,mixed> $approved_result Execution result of approved step.
	 * @param int                 $approver_user_id Approver user id.
	 * @return array<string,mixed>|null
	 */
	private function resume_multi_action_plan( $approval, $approved_result, $approver_user_id ) {
		$meta = $this->extract_multi_action_meta( $approval );
		if ( empty( $meta ) ) {
			return null;
		}

		$plan          = $meta['plan'];
		$current_step  = absint( $meta['current_step'] );
		$executions    = [];
		$status        = 'success';
		$last_execution = is_array( $approved_result ) ? $approved_result : [];

		$current_index = max( 0, $current_step - 1 );
		$current_agent = isset( $plan[ $current_index ] ) && is_array( $plan[ $current_index ] )
			? $plan[ $current_index ]
			: [
				'action'     => (string) ( $approval['action_key'] ?? '' ),
				'params'     => is_array( $approval['params'] ) ? $approval['params'] : [],
				'thought'    => '',
				'confidence' => 0.5,
			];

		$executions[] = [
			'step'      => $current_step,
			'agent'     => $current_agent,
			'status'    => isset( $approved_result['status'] ) ? (string) $approved_result['status'] : 'success',
			'execution' => $approved_result,
		];

		for ( $index = $current_index + 1; $index < count( $plan ); $index++ ) {
			$step_agent = $plan[ $index ];
			if ( ! is_array( $step_agent ) || empty( $step_agent['action'] ) ) {
				continue;
			}

			$context   = new ActionContext(
				[
					'user_id'          => absint( $approval['requester_user_id'] ),
					'prompt'           => is_array( $approval['prompt'] ) ? wp_json_encode( $approval['prompt'] ) : (string) $approval['prompt'],
					'model_output'     => $this->build_step_model_output( $step_agent, $plan, $index ),
					'approver_user_id' => $approver_user_id,
				]
			);
			$execution = $this->executor->execute(
				(string) $step_agent['action'],
				is_array( $step_agent['params'] ) ? $step_agent['params'] : [],
				$context,
				[
					'approver_user_id' => $approver_user_id,
				]
			);

			if ( is_wp_error( $execution ) ) {
				$status = 'failed';
				$executions[] = [
					'step'   => $index + 1,
					'agent'  => $step_agent,
					'status' => 'failed',
					'error'  => $execution->get_error_message(),
				];
				$last_execution = [
					'status'  => 'failed',
					'message' => $execution->get_error_message(),
				];
				break;
			}

			$step_status = isset( $execution['status'] ) ? (string) $execution['status'] : 'success';
			$executions[] = [
				'step'      => $index + 1,
				'agent'     => $step_agent,
				'status'    => $step_status,
				'execution' => $execution,
			];
			$last_execution = $execution;

			if ( 'pending_approval' === $step_status ) {
				$status = 'awaiting_approval';
				break;
			}

			if ( ! in_array( $step_status, [ 'success', 'no_action' ], true ) ) {
				$status = 'failed';
				break;
			}
		}

		return [
			'status'      => $status,
			'mode'        => 'single',
			'agent'       => [
				'action'     => isset( $plan[0]['action'] ) ? (string) $plan[0]['action'] : '',
				'params'     => isset( $plan[0]['params'] ) && is_array( $plan[0]['params'] ) ? $plan[0]['params'] : [],
				'thought'    => isset( $plan[0]['thought'] ) ? (string) $plan[0]['thought'] : '',
				'confidence' => isset( $plan[0]['confidence'] ) ? (float) $plan[0]['confidence'] : 0.5,
				'actions'    => $plan,
			],
			'execution'   => $last_execution,
			'executions'  => $executions,
			'action_plan' => $plan,
		];
	}

	/**
	 * Extract and normalize multi-action continuation metadata from approval row.
	 *
	 * @param array<string,mixed> $approval Approval row.
	 * @return array<string,mixed>|null
	 */
	private function extract_multi_action_meta( $approval ) {
		$model_output = isset( $approval['model_output'] ) && is_array( $approval['model_output'] ) ? $approval['model_output'] : [];
		$meta         = isset( $model_output['_openwp_multi_action'] ) && is_array( $model_output['_openwp_multi_action'] )
			? $model_output['_openwp_multi_action']
			: null;
		if ( ! is_array( $meta ) ) {
			return null;
		}

		$raw_plan = isset( $meta['plan'] ) && is_array( $meta['plan'] ) ? $meta['plan'] : [];
		if ( count( $raw_plan ) < 2 ) {
			return null;
		}

		$plan = [];
		foreach ( $raw_plan as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$action = isset( $item['action'] ) ? trim( (string) $item['action'] ) : '';
			$params = isset( $item['params'] ) && is_array( $item['params'] ) ? $item['params'] : [];
			if ( '' === $action || 'none' === $action ) {
				continue;
			}

			$plan[] = [
				'action'     => $action,
				'params'     => $params,
				'thought'    => isset( $item['thought'] ) ? (string) $item['thought'] : '',
				'confidence' => isset( $item['confidence'] ) && is_numeric( $item['confidence'] ) ? (float) $item['confidence'] : 0.5,
			];
		}

		if ( count( $plan ) < 2 ) {
			return null;
		}

		$current_step = absint( $meta['current_step'] ?? 1 );
		$current_step = max( 1, min( count( $plan ), $current_step ) );

		return [
			'current_step' => $current_step,
			'plan'         => $plan,
		];
	}

	/**
	 * Build model output with continuation metadata for chained approvals.
	 *
	 * @param array<string,mixed>            $step_agent Step agent payload.
	 * @param array<int,array<string,mixed>> $plan Full ordered plan.
	 * @param int                            $index Current 0-based index.
	 * @return array<string,mixed>
	 */
	private function build_step_model_output( $step_agent, $plan, $index ) {
		$output = is_array( $step_agent ) ? $step_agent : [];
		if ( count( $plan ) <= 1 ) {
			return $output;
		}

		$output['_openwp_multi_action'] = [
			'version'      => 1,
			'current_step' => absint( $index + 1 ),
			'plan'         => array_values( $plan ),
		];

		return $output;
	}

	/**
	 * Reject pending action.
	 *
	 * @param int    $approval_id Approval ID.
	 * @param int    $approver_user_id Approver ID.
	 * @param string $note Decision note.
	 * @return array<string,mixed>|WP_Error
	 */
	public function reject( $approval_id, $approver_user_id, $note = '' ) {
		$approval = $this->repository->get( $approval_id );
		if ( ! $approval ) {
			return new WP_Error( 'openwp_approval_not_found', __( 'Approval request not found.', 'openwp' ) );
		}

		if ( 'pending' !== (string) $approval['status'] ) {
			return new WP_Error( 'openwp_approval_not_pending', __( 'Approval request has already been processed.', 'openwp' ) );
		}

		$this->repository->update(
			$approval_id,
			[
				'approver_user_id' => $approver_user_id,
				'status'           => 'rejected',
				'decision_note'    => $note,
			]
		);

		return [
			'status'      => 'rejected',
			'approval_id' => absint( $approval_id ),
			'message'     => __( 'Action rejected.', 'openwp' ),
		];
	}
}
