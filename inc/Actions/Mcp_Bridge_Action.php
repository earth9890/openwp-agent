<?php
/**
 * MCP bridge action handler.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use OpenWP\Inc\MCP\Tool_Registry;
use OpenWP\Inc\Utils\Schema_Validator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges Agent_Engine action calls to MCP tool modules.
 *
 * Registered as three Action_Registry entries (mcp_read_tool, mcp_write_tool,
 * mcp_admin_tool). The LLM picks the appropriate bridge based on the tool's
 * access level as described in MCP_TOOL_CATALOG injected into the system prompt.
 */
class Mcp_Bridge_Action {

	/**
	 * Delegate an action call to the matching MCP tool module.
	 *
	 * Expected $params shape:
	 *   - tool (string) : MCP tool name from MCP_TOOL_CATALOG
	 *   - args (object/array) : Arguments per the tool's inputSchema
	 *
	 * @param array<string,mixed> $params  Validated params supplied by Action_Executor.
	 * @param ActionContext       $context Execution context (user_id, prompt, etc.).
	 * @return array<string,mixed>|WP_Error
	 */
	public static function call_mcp_tool( $params, ActionContext $context ) {
		$tool_name = isset( $params['tool'] ) ? sanitize_text_field( (string) $params['tool'] ) : '';
		$tool_args = isset( $params['args'] ) && is_array( $params['args'] ) ? $params['args'] : [];

		if ( '' === $tool_name ) {
			return new WP_Error(
				'openwp_mcp_bridge_no_tool',
				__( 'MCP bridge: tool name is required.', 'openwp' )
			);
		}

		$registry = new Tool_Registry();
		$tool_def = $registry->get_tool( $tool_name );

		if ( null === $tool_def ) {
			return new WP_Error(
				'openwp_mcp_bridge_unknown_tool',
				/* translators: %s: MCP tool name */
				sprintf( __( 'MCP bridge: unknown tool "%s".', 'openwp' ), $tool_name )
			);
		}

		// Validate args against the tool's inputSchema.
		$input_schema = isset( $tool_def['inputSchema'] ) && is_array( $tool_def['inputSchema'] ) ? $tool_def['inputSchema'] : [];
		if ( ! empty( $input_schema ) ) {
			$validator      = new Schema_Validator();
			$validated_args = $validator->validate( $input_schema, $tool_args );
			if ( is_wp_error( $validated_args ) ) {
				return $validated_args;
			}
			$tool_args = $validated_args;
		}

		// Resolve module: prefer the stored 'module' key, fall back to iterating.
		$module     = null;
		$module_key = isset( $tool_def['module'] ) ? (string) $tool_def['module'] : '';
		$modules    = $registry->get_modules();

		if ( '' !== $module_key && isset( $modules[ $module_key ] ) ) {
			$module = $modules[ $module_key ];
		} else {
			foreach ( $modules as $candidate ) {
				if ( $candidate->supports( $tool_name ) ) {
					$module = $candidate;
					break;
				}
			}
		}

		if ( null === $module ) {
			return new WP_Error(
				'openwp_mcp_bridge_module_unavailable',
				/* translators: %s: MCP tool name */
				sprintf( __( 'MCP bridge: no active module supports tool "%s". The required plugin may be inactive.', 'openwp' ), $tool_name )
			);
		}

		$result = $module->execute( $tool_name, $tool_args, $context );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_array( $result ) ) {
			return $result;
		}

		return [ 'output' => $result ];
	}
}
