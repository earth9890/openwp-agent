<?php
/**
 * OpenWP MCP server.
 *
 * @package OpenWP\Inc\MCP
 */

namespace OpenWP\Inc\MCP;

use OpenWP\Inc\Security\Rate_Limiter;
use OpenWP\Inc\Traits\Get_Instance;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and serves MCP transports.
 */
class MCP_Server {
	use Get_Instance;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'mcp/v1';

	/**
	 * MCP protocol version.
	 *
	 * @var string
	 */
	private $protocol_version = '2025-06-18';

	/**
	 * Server semantic version.
	 *
	 * @var string
	 */
	private $server_version = '0.1.0';

	/**
	 * Auth manager.
	 *
	 * @var MCP_Auth
	 */
	private $auth;

	/**
	 * Session helper.
	 *
	 * @var MCP_Session
	 */
	private $session;

	/**
	 * Queue helper.
	 *
	 * @var MCP_Message_Queue
	 */
	private $queue;

	/**
	 * SSE transport.
	 *
	 * @var MCP_SSE_Transport
	 */
	private $sse_transport;

	/**
	 * HTTP transport.
	 *
	 * @var MCP_HTTP_Transport
	 */
	private $http_transport;

	/**
	 * Tool registry.
	 *
	 * @var Tool_Registry
	 */
	private $registry;

	/**
	 * Tool executor.
	 *
	 * @var Tool_Executor
	 */
	private $executor;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->auth          = new MCP_Auth();
		$this->session       = new MCP_Session();
		$this->queue         = new MCP_Message_Queue();
		$this->sse_transport = new MCP_SSE_Transport( $this->queue );
		$this->http_transport = new MCP_HTTP_Transport( $this->queue, $this->session );
		$this->registry      = new Tool_Registry();
		$this->executor      = new Tool_Executor( $this->registry );

		// Register late and override collisions so OpenWP owns mcp/v1 when multiple MCP plugins are active.
		add_action( 'rest_api_init', [ $this, 'register_routes' ], 9999 );
	}

	/**
	 * Register MCP routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/sse',
			[
				'methods'             => [ 'GET', 'POST', 'HEAD' ],
				'callback'            => [ $this, 'handle_sse' ],
				'permission_callback' => [ $this, 'authorize' ],
			],
			true
		);

		register_rest_route(
			$this->namespace,
			'/messages',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_messages' ],
				'permission_callback' => [ $this, 'authorize' ],
			],
			true
		);

		register_rest_route(
			$this->namespace,
			'/http',
			[
				'methods'             => [ 'GET', 'POST', 'DELETE' ],
				'callback'            => [ $this, 'handle_http' ],
				'permission_callback' => [ $this, 'authorize' ],
			],
			true
		);

		register_rest_route(
			$this->namespace,
			'/upload/(?P<token>[a-zA-Z0-9]+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_upload' ],
				'permission_callback' => '__return_true',
			],
			true
		);
	}

	/**
	 * Permission callback for authenticated MCP routes.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return true|\WP_Error
	 */
	public function authorize( WP_REST_Request $request ) {
		$auth = $this->auth->authenticate_request( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$limiter = new Rate_Limiter();
		return $limiter->enforce( get_current_user_id() );
	}

	/**
	 * Handle SSE transport endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	public function handle_sse( WP_REST_Request $request ) {
		if ( 'HEAD' === strtoupper( (string) $request->get_method() ) ) {
			return new WP_REST_Response(
				null,
				200,
				[
					'Content-Type'  => 'text/event-stream',
					'Cache-Control' => 'no-cache',
				]
			);
		}

		if ( 'POST' === strtoupper( (string) $request->get_method() ) ) {
			return $this->handle_direct_jsonrpc_post( $request );
		}

		$session_id  = $this->session->generate_sse_id( $request );
		$message_url = sprintf( '%s/messages?session_id=%s', rest_url( $this->namespace ), rawurlencode( $session_id ) );

		$this->sse_transport->stream( $session_id, $message_url, $this->auth->is_debug_mode() );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Handle messages ingress endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	public function handle_messages( WP_REST_Request $request ) {
		$session_id = $this->session->sanitize( (string) $request->get_param( 'session_id' ) );
		if ( '' === $session_id ) {
			return new WP_REST_Response( [ 'error' => 'session_id is required.' ], 400 );
		}

		$data = json_decode( (string) $request->get_body(), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$this->queue->store( $session_id, $this->rpc_error( null, -32700, 'Parse error: invalid JSON.' ) );
			return new WP_REST_Response( null, 204 );
		}

		$reply = $this->process_json_rpc( $request, $data, $session_id );
		if ( is_array( $reply ) ) {
			$this->queue->store( $session_id, $reply );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Handle streamable HTTP transport.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	public function handle_http( WP_REST_Request $request ) {
		return $this->http_transport->handle( $request, [ $this, 'handle_http_post' ], $this->auth->is_debug_mode() );
	}

	/**
	 * Handle upload endpoint for one-time upload tokens.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return WP_REST_Response<array<string,mixed>>
	 */
	public function handle_upload( WP_REST_Request $request ) {
		return $this->executor->handle_upload( $request );
	}

	/**
	 * Handle JSON-RPC POST for /http endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	public function handle_http_post( WP_REST_Request $request ) {
		return $this->handle_direct_jsonrpc_post( $request );
	}

	/**
	 * Process direct JSON-RPC request and return immediate JSON response.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return WP_REST_Response<array<string,mixed>|null>
	 */
	private function handle_direct_jsonrpc_post( WP_REST_Request $request ) {
		$raw = (string) $request->get_body();
		if ( '' === trim( $raw ) ) {
			$response = new WP_REST_Response( $this->rpc_error( null, -32700, 'Parse error: empty body.' ), 400 );
			$response->set_headers( [ 'Content-Type' => 'application/json' ] );
			return $response;
		}

		$data = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$response = new WP_REST_Response( $this->rpc_error( null, -32700, 'Parse error: invalid JSON.' ), 400 );
			$response->set_headers( [ 'Content-Type' => 'application/json' ] );
			return $response;
		}

		$session_id = $this->session->from_header( $request );
		if ( '' === $session_id || ( isset( $data['method'] ) && 'initialize' === $data['method'] ) ) {
			$session_id = $this->session->create();
		}

		$reply = $this->process_json_rpc( $request, $data, $session_id );
		if ( ! is_array( $reply ) ) {
			$response = new WP_REST_Response( null, 204 );
			return $this->session->attach_header( $response, $session_id );
		}

		$response = new WP_REST_Response( $reply, 200 );
		$response->set_headers( [ 'Content-Type' => 'application/json' ] );
		return $this->session->attach_header( $response, $session_id );
	}

	/**
	 * Execute JSON-RPC method.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @param array<string,mixed>                  $payload Request payload.
	 * @param string                               $session_id Session id.
	 * @return array<string,mixed>|null
	 */
	private function process_json_rpc( WP_REST_Request $request, $payload, $session_id ) {
		$method = isset( $payload['method'] ) ? (string) $payload['method'] : '';
		$id     = array_key_exists( 'id', $payload ) ? $payload['id'] : null;

		if ( '' === $method ) {
			return $this->rpc_error( $id, -32600, 'Invalid Request: method missing.' );
		}

		if ( 'notifications/initialized' === $method || 'initialized' === $method ) {
			return null;
		}
		if ( 'notifications/cancelled' === $method || 'openwp/kill' === $method ) {
			$this->queue->queue_kill( $session_id );
			return null;
		}

		try {
			switch ( $method ) {
				case 'initialize':
					return [
						'jsonrpc' => '2.0',
						'id'      => $id,
						'result'  => [
							'protocolVersion' => $this->protocol_version,
							'serverInfo'      => (object) [
								'name'    => 'OpenWP - ' . get_bloginfo( 'name' ),
								'version' => OPENWP_VERSION,
							],
							'capabilities'    => (object) [
								'tools'     => new \stdClass(),
								'resources' => new \stdClass(),
								'prompts'   => new \stdClass(),
							],
						],
					];

				case 'tools/list':
					return [
						'jsonrpc' => '2.0',
						'id'      => $id,
						'result'  => [
							'tools' => $this->registry->list_tools(),
						],
					];

				case 'tools/call':
					$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : [];
					$tool   = isset( $params['name'] ) ? (string) $params['name'] : '';
					$args   = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : [];

					if ( '' === $tool ) {
						return $this->rpc_error( $id, -32602, 'Invalid params: tool name is required.' );
					}

					$result = $this->executor->call( $tool, $args );

					return [
						'jsonrpc' => '2.0',
						'id'      => $id,
						'result'  => $result,
					];

				case 'resources/list':
					return [
						'jsonrpc' => '2.0',
						'id'      => $id,
						'result'  => [
							'resources' => [],
						],
					];

				case 'prompts/list':
					return [
						'jsonrpc' => '2.0',
						'id'      => $id,
						'result'  => [
							'prompts' => [],
						],
					];

				default:
					if ( null === $id && 0 === strpos( $method, 'notifications/' ) ) {
						return null;
					}
					return $this->rpc_error( $id, -32601, 'Method not found: ' . $method );
			}
		} catch ( \Throwable $throwable ) {
			return $this->rpc_error( $id, -32603, 'Internal error', $throwable->getMessage() );
		}
	}

	/**
	 * Build JSON-RPC error payload.
	 *
	 * @param mixed  $id Rpc id.
	 * @param int    $code Error code.
	 * @param string $message Message.
	 * @param mixed  $data Optional data.
	 * @return array<string,mixed>
	 */
	private function rpc_error( $id, $code, $message, $data = null ) {
		$error = [
			'code'    => (int) $code,
			'message' => (string) $message,
		];

		if ( null !== $data ) {
			$error['data'] = $data;
		}

		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => $error,
		];
	}
}
