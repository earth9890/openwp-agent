<?php
/**
 * Policy engine implementation.
 *
 * @package OpenWP\Inc\Security
 */

namespace OpenWP\Inc\Security;

use OpenWP\Inc\Core\Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves effective policy for an action.
 */
class PolicyEngine implements PolicyEngineInterface {
	/**
	 * Evaluate action permissions.
	 *
	 * @param array<string,mixed> $action Action definition.
	 * @param array<string,mixed> $user_context User info.
	 * @return array<string,mixed>|WP_Error
	 */
	public function evaluate( $action, $user_context ) {
		$capability = isset( $action['capability'] ) ? (string) $action['capability'] : 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error( 'openwp_capability_denied', __( 'You do not have permission to run this action.', 'openwp' ) );
		}

		$action_key = isset( $action['key'] ) ? (string) $action['key'] : '';
		$risk       = isset( $action['risk'] ) ? (string) $action['risk'] : 'low';
		$settings   = Settings::get();
		$policies   = Settings::get_action_policy();
		$override   = isset( $policies[ $action_key ] ) && is_array( $policies[ $action_key ] ) ? $policies[ $action_key ] : [];

		$enabled = isset( $override['enabled'] ) ? (bool) $override['enabled'] : true;
		if ( ! $enabled ) {
			return new WP_Error( 'openwp_action_disabled', __( 'This action is currently disabled by policy.', 'openwp' ) );
		}

		$require_approval = false;
		switch ( $risk ) {
			case 'medium':
				$require_approval = ! empty( $settings['medium_requires_approval'] );
				break;
			case 'high':
				$require_approval = ! empty( $settings['high_requires_approval'] );
				break;
			case 'critical':
				$require_approval = ! empty( $settings['critical_requires_approval'] );
				break;
			default:
				$require_approval = false;
		}

		if ( array_key_exists( 'require_approval', $override ) ) {
			$require_approval = (bool) $override['require_approval'];
		}

		$mutates         = ! empty( $action['mutates'] );
		$requires_backup = ! empty( $action['requires_backup'] );

		if ( ! $requires_backup && $mutates && in_array( $risk, [ 'high', 'critical' ], true ) ) {
			$requires_backup = true;
		}

		return [
			'action_key'          => $action_key,
			'risk'                => $risk,
			'require_approval'    => $require_approval,
			'requires_backup'     => $requires_backup,
			'requires_typed_ack'  => 'critical' === $risk,
			'mutates'             => $mutates,
			'capability'          => $capability,
			'user_id'             => absint( $user_context['user_id'] ?? get_current_user_id() ),
		];
	}
}
