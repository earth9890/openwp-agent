<?php
/**
 * MCP tool execution engine.
 *
 * @package OpenWP\Inc\MCP
 */

namespace OpenWP\Inc\MCP;

use OpenWP\Inc\Actions\ActionContext;
use OpenWP\Inc\Logs\Log_Repository;
use OpenWP\Inc\MCP\Modules\ToolModuleInterface;
use OpenWP\Inc\Security\Rate_Limiter;
use OpenWP\Inc\Utils\Schema_Validator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates arguments, dispatches tools, and formats MCP responses.
 */
class Tool_Executor {
	/**
	 * Tool registry.
	 *
	 * @var Tool_Registry
	 */
	private $registry;

	/**
	 * Schema validator.
	 *
	 * @var Schema_Validator
	 */
	private $validator;

	/**
	 * Audit log repository.
	 *
	 * @var Log_Repository
	 */
	private $logs;

	/**
	 * Rate limiter recorder.
	 *
	 * @var Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Upload transient prefix.
	 *
	 * @var string
	 */
	private $upload_prefix = 'openwp_mcp_upload_';

	/**
	 * Constructor.
	 *
	 * @param Tool_Registry $registry Tool registry.
	 */
	public function __construct( Tool_Registry $registry ) {
		$this->registry  = $registry;
		$this->validator = new Schema_Validator();
		$this->logs      = new Log_Repository();
		$this->rate_limiter = new Rate_Limiter();
	}

	/**
	 * Execute a tool call.
	 *
	 * @param string               $tool Tool name.
	 * @param array<string,mixed>  $args Raw arguments.
	 * @return array<string,mixed>
	 * @throws \Exception When execution fails.
	 */
	public function call( $tool, $args ) {
		$started = microtime( true );
		$tool    = sanitize_text_field( (string) $tool );
		$args    = is_array( $args ) ? $args : [];

		if ( 'mcp_ping' === $tool ) {
			$data = [
				'time' => gmdate( 'Y-m-d H:i:s' ),
				'name' => get_bloginfo( 'name' ),
			];

			$this->log_call( $tool, $args, 'success', $started, $data, '' );
			$this->rate_limiter->record( get_current_user_id() );

			return [
				'content' => [
					[
						'type' => 'text',
						'text' => 'Ping successful: ' . wp_json_encode( $data, JSON_PRETTY_PRINT ),
					],
				],
				'data'    => $data,
			];
		}

		$definition = $this->registry->get_tool( $tool );
		if ( ! is_array( $definition ) ) {
			throw new \Exception( sprintf( 'Unknown MCP tool: %s', $tool ) );
		}

		$schema = isset( $definition['inputSchema'] ) && is_array( $definition['inputSchema'] ) ? $definition['inputSchema'] : [];
		$valid  = $this->validator->validate( $schema, $args );
		if ( is_wp_error( $valid ) ) {
			$this->log_call( $tool, $args, 'failed', $started, [], $valid->get_error_message(), $definition );
			throw new \Exception( $valid->get_error_message() );
		}

		$filtered = apply_filters( 'openwp_mcp_callback', null, $tool, $valid, $definition, $this );
		if ( null !== $filtered ) {
			$result = $this->format_tool_result( $filtered );
			$this->log_call( $tool, $valid, 'success', $started, $result, '', $definition );
			do_action( 'openwp_mcp_tool_called', $tool, $valid, $result );
			return $result;
		}

		$module_key = isset( $definition['module'] ) ? (string) $definition['module'] : '';
		$module     = $this->resolve_module( $module_key, $tool );
		if ( ! $module ) {
			$this->log_call( $tool, $valid, 'failed', $started, [], 'Tool module is unavailable.', $definition );
			throw new \Exception( 'Tool module is unavailable.' );
		}

		$context = new ActionContext(
			[
				'user_id'      => get_current_user_id(),
				'prompt'       => 'mcp:' . $tool,
				'model_output' => [
					'mcp_tool' => $tool,
				],
			]
		);

		try {
			$raw_result = $module->execute( $tool, $valid, $context );
		} catch ( \Throwable $throwable ) {
			$this->log_call( $tool, $valid, 'failed', $started, [], $throwable->getMessage(), $definition );
			throw new \Exception( $throwable->getMessage() );
		}

		if ( is_wp_error( $raw_result ) ) {
			$this->log_call( $tool, $valid, 'failed', $started, [], $raw_result->get_error_message(), $definition );
			throw new \Exception( $raw_result->get_error_message() );
		}

		$result = $this->format_tool_result( $raw_result );

		$this->log_call( $tool, $valid, 'success', $started, $result, '', $definition );
		$this->rate_limiter->record( get_current_user_id() );
		do_action( 'openwp_mcp_tool_called', $tool, $valid, $result );

		return $result;
	}

	/**
	 * Process one-time upload token route.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return WP_REST_Response<array<string,mixed>>
	 */
	public function handle_upload( WP_REST_Request $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		if ( '' === $token ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'Missing token.' ], 400 );
		}

		$data = get_transient( $this->upload_prefix . $token );
		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'Invalid or expired upload token.' ], 403 );
		}

		delete_transient( $this->upload_prefix . $token );

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'No file provided. Use multipart file field: file.' ], 400 );
		}

		$upload = $files['file'];
		if ( ! isset( $upload['error'] ) || (int) $upload['error'] !== UPLOAD_ERR_OK ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'Upload error code: ' . (int) ( $upload['error'] ?? -1 ) ], 400 );
		}

		if ( isset( $data['user_id'] ) && (int) $data['user_id'] > 0 ) {
			wp_set_current_user( (int) $data['user_id'] );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file = [
			'name'     => isset( $data['filename'] ) ? sanitize_file_name( (string) $data['filename'] ) : sanitize_file_name( (string) $upload['name'] ),
			'tmp_name' => $upload['tmp_name'],
		];

		$attachment_id = media_handle_sideload( $file, 0, isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '' );
		if ( is_wp_error( $attachment_id ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => $attachment_id->get_error_message() ], 500 );
		}

		if ( ! empty( $data['title'] ) ) {
			wp_update_post(
				[
					'ID'         => (int) $attachment_id,
					'post_title' => sanitize_text_field( (string) $data['title'] ),
				]
			);
		}
		if ( ! empty( $data['alt'] ) ) {
			update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $data['alt'] ) );
		}

		return new WP_REST_Response(
			[
				'success'       => true,
				'attachment_id' => (int) $attachment_id,
				'url'           => wp_get_attachment_url( (int) $attachment_id ),
			],
			200
		);
	}

	/**
	 * Resolve module instance.
	 *
	 * @param string $module_key Module key.
	 * @param string $tool Tool name.
	 * @return ToolModuleInterface|null
	 */
	private function resolve_module( $module_key, $tool ) {
		$modules = $this->registry->get_modules();
		if ( isset( $modules[ $module_key ] ) ) {
			return $modules[ $module_key ];
		}

		foreach ( $modules as $module ) {
			if ( $module->supports( $tool ) ) {
				return $module;
			}
		}

		return null;
	}

	/**
	 * Normalize tool output to MCP content blocks.
	 *
	 * @param mixed $result Raw result.
	 * @return array<string,mixed>
	 */
	private function format_tool_result( $result ) {
		if ( is_array( $result ) && isset( $result['content'] ) && is_array( $result['content'] ) ) {
			return $result;
		}

		if ( is_string( $result ) ) {
			return [
				'content' => [
					[
						'type' => 'text',
						'text' => $result,
					],
				],
			];
		}

		if ( is_array( $result ) ) {
			return [
				'content' => [
					[
						'type' => 'text',
						'text' => wp_json_encode( $result, JSON_PRETTY_PRINT ),
					],
				],
				'data'    => $result,
			];
		}

		return [
			'content' => [
				[
					'type' => 'text',
					'text' => (string) $result,
				],
			],
		];
	}

	/**
	 * Write MCP call audit log.
	 *
	 * @param string               $tool Tool name.
	 * @param array<string,mixed>  $args Tool args.
	 * @param string               $status success|failed.
	 * @param float                $started Start microtime.
	 * @param array<string,mixed>  $result Result payload.
	 * @param string               $error Error text.
	 * @param array<string,mixed>  $definition Tool metadata.
	 * @return void
	 */
	private function log_call( $tool, $args, $status, $started, $result = [], $error = '', $definition = [] ) {
		$duration_ms = (int) round( ( microtime( true ) - $started ) * 1000 );
		$args_hash   = sha1( wp_json_encode( $args ) );
		$risk        = $this->map_access_to_risk( isset( $definition['accessLevel'] ) ? (string) $definition['accessLevel'] : 'admin' );

		$output_summary = '';
		if ( is_array( $result ) ) {
			if ( isset( $result['content'][0]['text'] ) && is_string( $result['content'][0]['text'] ) ) {
				$output_summary = mb_substr( $result['content'][0]['text'], 0, 400 );
			} else {
				$output_summary = mb_substr( wp_json_encode( $result ), 0, 400 );
			}
		}

		$this->logs->insert(
			[
				'user_id'      => get_current_user_id(),
				'action_key'   => sanitize_key( str_replace( '/', '_', $tool ) ),
				'risk_level'   => $risk,
				'prompt'       => [ 'mcp' => true, 'tool' => $tool ],
				'model_output' => [
					'args_hash'    => $args_hash,
					'duration_ms'  => $duration_ms,
					'mcp_module'   => isset( $definition['module'] ) ? $definition['module'] : '',
				],
				'params'       => $args,
				'result'       => [
					'summary'     => $output_summary,
					'duration_ms' => $duration_ms,
				],
				'status'       => 'success' === $status ? 'success' : 'failed',
				'error_message'=> $error,
			]
		);
	}

	/**
	 * Map tool access level to existing risk levels.
	 *
	 * @param string $level Tool access level.
	 * @return string
	 */
	private function map_access_to_risk( $level ) {
		if ( 'read' === $level ) {
			return 'low';
		}
		if ( 'write' === $level ) {
			return 'medium';
		}

		return 'high';
	}
}
