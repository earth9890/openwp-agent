<?php
/**
 * MCP tool module interface.
 *
 * @package OpenWP\Inc\MCP\Modules
 */

namespace OpenWP\Inc\MCP\Modules;

use OpenWP\Inc\Actions\ActionContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared module contract.
 */
interface ToolModuleInterface {
	/**
	 * Return tool definitions indexed by tool name.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_tools();

	/**
	 * Check whether module supports the tool.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	public function supports( $tool );

	/**
	 * Execute tool.
	 *
	 * @param string        $tool Tool name.
	 * @param array<string,mixed> $args Validated args.
	 * @param ActionContext $context Context.
	 * @return array<string,mixed>|string
	 */
	public function execute( $tool, $args, ActionContext $context );
}
