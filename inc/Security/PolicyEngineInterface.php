<?php
/**
 * Policy engine contract.
 *
 * @package OpenWP\Inc\Security
 */

namespace OpenWP\Inc\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates action policy decisions.
 */
interface PolicyEngineInterface {
	/**
	 * Evaluate policy decision.
	 *
	 * @param array<string,mixed> $action Action definition.
	 * @param array<string,mixed> $user_context User context.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function evaluate( $action, $user_context );
}
