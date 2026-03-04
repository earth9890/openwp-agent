<?php
/**
 * Database MCP tools.
 *
 * @package OpenWP\Inc\MCP\Modules
 */

namespace OpenWP\Inc\MCP\Modules;

use OpenWP\Inc\Actions\ActionContext;
use OpenWP\Inc\Actions\Database_Actions;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database module.
 */
class DatabaseTools implements ToolModuleInterface {
	/**
	 * Return tool map.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_tools() {
		return [
			'wp_db_query' => [
				'name'        => 'wp_db_query',
				'description' => 'Execute a SQL query on the WordPress database.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'query' => [ 'type' => 'string' ],
					],
					'required'   => [ 'query' ],
				],
				'accessLevel' => 'admin',
				'category'    => 'AI Engine (Database)',
				'annotations' => [
					'readOnlyHint'    => false,
					'destructiveHint' => true,
					'openWorldHint'   => false,
				],
			],
		];
	}

	/**
	 * Support check.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	public function supports( $tool ) {
		return 'wp_db_query' === $tool;
	}

	/**
	 * Execute database tool.
	 *
	 * @param string        $tool Tool name.
	 * @param array<string,mixed> $args Args.
	 * @param ActionContext $context Context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute( $tool, $args, ActionContext $context ) {
		if ( 'wp_db_query' !== $tool ) {
			return new WP_Error( 'openwp_mcp_db_unknown_tool', __( 'Unknown database tool.', 'openwp' ) );
		}

		$result = Database_Actions::db_query(
			[
				'query' => (string) $args['query'],
			],
			$context
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_object( $result ) && method_exists( $result, 'to_array' ) ) {
			$data = $result->to_array();
			return isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;
		}

		return is_array( $result ) ? $result : [ 'result' => $result ];
	}
}
