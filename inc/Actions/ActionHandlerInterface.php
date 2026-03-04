<?php
/**
 * Action handler contract.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for action handlers.
 */
interface ActionHandlerInterface {
	/**
	 * Execute action.
	 *
	 * @param array<string,mixed> $params Action params.
	 * @param ActionContext       $context Execution context.
	 * @return ActionResult|\WP_Error
	 */
	public function execute( $params, ActionContext $context );
}
