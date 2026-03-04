<?php
/**
 * Action executor.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use OpenWP\Inc\Backup\Backup_Service;
use OpenWP\Inc\Logs\Approval_Repository;
use OpenWP\Inc\Logs\Log_Repository;
use OpenWP\Inc\Security\PolicyEngine;
use OpenWP\Inc\Utils\Schema_Validator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs registered actions through policy, backup, and logging.
 */
class Action_Executor {
	/**
	 * Policy engine.
	 *
	 * @var PolicyEngine
	 */
	private $policy_engine;

	/**
	 * Schema validator.
	 *
	 * @var Schema_Validator
	 */
	private $validator;

	/**
	 * Approval repository.
	 *
	 * @var Approval_Repository
	 */
	private $approvals;

	/**
	 * Log repository.
	 *
	 * @var Log_Repository
	 */
	private $logs;

	/**
	 * Backup service.
	 *
	 * @var Backup_Service
	 */
	private $backup_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->policy_engine  = new PolicyEngine();
		$this->validator      = new Schema_Validator();
		$this->approvals      = new Approval_Repository();
		$this->logs           = new Log_Repository();
		$this->backup_service = new Backup_Service();
	}

	/**
	 * Execute action by key.
	 *
	 * @param string        $action_key Action key.
	 * @param array<string,mixed> $params Action params.
	 * @param ActionContext $context Action context.
	 * @param array<string,mixed> $options Execution options.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute( $action_key, $params, ActionContext $context, $options = [] ) {
		$action = Action_Registry::get( $action_key );
		if ( ! $action ) {
			return new WP_Error( 'openwp_action_not_found', __( 'Requested action is not registered.', 'openwp' ) );
		}

		$params = is_array( $params ) ? $params : [];
		$validated_params = $this->validator->validate( $action['schema'] ?? [], $params );
		if ( is_wp_error( $validated_params ) ) {
			return $validated_params;
		}

		$decision = $this->policy_engine->evaluate(
			$action,
			[
				'user_id' => $context->user_id,
			]
		);

		if ( is_wp_error( $decision ) ) {
			return $decision;
		}

		$from_approval = ! empty( $options['from_approval'] );
		$approver_id   = absint( $options['approver_user_id'] ?? 0 );
		$typed_ack     = isset( $options['typed_confirmation'] ) ? (string) $options['typed_confirmation'] : '';

		if ( ! $from_approval && ! empty( $decision['require_approval'] ) ) {
			$approval_id = $this->approvals->create(
				[
					'requester_user_id' => $context->user_id,
					'action_key'        => $action_key,
					'risk_level'        => $decision['risk'],
					'prompt'            => $context->prompt,
					'model_output'      => $context->model_output,
					'params'            => $validated_params,
					'backup_required'   => ! empty( $decision['requires_backup'] ),
				]
			);

			$log_id = $this->logs->insert(
				[
					'user_id'      => $context->user_id,
					'action_key'   => $action_key,
					'risk_level'   => $decision['risk'],
					'prompt'       => $context->prompt,
					'model_output' => $context->model_output,
					'params'       => $validated_params,
					'result'       => [ 'approval_id' => $approval_id ],
					'status'       => 'pending_approval',
				]
			);

			return [
				'status'      => 'pending_approval',
				'approval_id' => $approval_id,
				'log_id'      => $log_id,
				'action'      => $action_key,
				'risk'        => $decision['risk'],
			];
		}

		if ( ! empty( $decision['requires_typed_ack'] ) && 'APPROVE' !== strtoupper( trim( $typed_ack ) ) ) {
			return new WP_Error( 'openwp_typed_ack_required', __( 'Critical actions require typed confirmation value APPROVE.', 'openwp' ) );
		}

		$backup_id = 0;
		if ( ! empty( $decision['requires_backup'] ) ) {
			$backup = $this->backup_service->create( $context->user_id, 'pre-action:' . $action_key );
			if ( is_wp_error( $backup ) ) {
				$this->logs->insert(
					[
						'user_id'        => $context->user_id,
						'approver_id'    => $approver_id,
						'action_key'     => $action_key,
						'risk_level'     => $decision['risk'],
						'prompt'         => $context->prompt,
						'model_output'   => $context->model_output,
						'params'         => $validated_params,
						'result'         => [],
						'status'         => 'failed_backup_gate',
						'error_message'  => $backup->get_error_message(),
					]
				);
				return $backup;
			}

			$backup_id = absint( $backup['id'] ?? 0 );
		}

		$callback = $action['callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error( 'openwp_action_callback_invalid', __( 'Action callback is not callable.', 'openwp' ) );
		}

		$result = call_user_func( $callback, $validated_params, $context );
		if ( is_wp_error( $result ) ) {
			$log_id = $this->logs->insert(
				[
					'user_id'      => $context->user_id,
					'approver_id'  => $approver_id,
					'action_key'   => $action_key,
					'risk_level'   => $decision['risk'],
					'prompt'       => $context->prompt,
					'model_output' => $context->model_output,
					'params'       => $validated_params,
					'result'       => [],
					'status'       => 'failed',
					'error_message'=> $result->get_error_message(),
					'backup_id'    => $backup_id,
				]
			);

			$result->add_data( [ 'log_id' => $log_id ] );
			return $result;
		}

		if ( $result instanceof ActionResult ) {
			$result_array = $result->to_array();
		} elseif ( is_array( $result ) ) {
			$result_array = [
				'success'           => true,
				'message'           => __( 'Action executed.', 'openwp' ),
				'data'              => $result,
				'rollback_snapshot' => [],
			];
		} else {
			$result_array = [
				'success'           => true,
				'message'           => __( 'Action executed.', 'openwp' ),
				'data'              => [ 'value' => $result ],
				'rollback_snapshot' => [],
			];
		}

		$log_id = $this->logs->insert(
			[
				'user_id'           => $context->user_id,
				'approver_id'       => $approver_id,
				'action_key'        => $action_key,
				'risk_level'        => $decision['risk'],
				'prompt'            => $context->prompt,
				'model_output'      => $context->model_output,
				'params'            => $validated_params,
				'result'            => $result_array['data'],
				'status'            => ! empty( $result_array['success'] ) ? 'success' : 'failed',
				'rollback_snapshot' => $result_array['rollback_snapshot'],
				'backup_id'         => $backup_id,
			]
		);

		return [
			'status'            => ! empty( $result_array['success'] ) ? 'success' : 'failed',
			'action'            => $action_key,
			'risk'              => $decision['risk'],
			'data'              => $result_array['data'],
			'message'           => $result_array['message'],
			'backup_id'         => $backup_id,
			'log_id'            => $log_id,
			'rollback_snapshot' => $result_array['rollback_snapshot'],
		];
	}
}
