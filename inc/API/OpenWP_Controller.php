<?php
/**
 * Main OpenWP REST controller.
 *
 * @package OpenWP\Inc\API
 */

namespace OpenWP\Inc\API;

use OpenWP\Inc\Actions\Action_Registry;
use OpenWP\Inc\Agent\Agent_Engine;
use OpenWP\Inc\Backup\Backup_Service;
use OpenWP\Inc\Chatbot\Attachment_Service;
use OpenWP\Inc\Core\Settings;
use OpenWP\Inc\Logs\Approval_Service;
use OpenWP\Inc\Logs\Log_Repository;
use OpenWP\Inc\Logs\Rollback_Service;
use OpenWP\Inc\MCP\Tool_Registry;
use OpenWP\Inc\Memory\Memory_Service;
use OpenWP\Inc\Onboarding\Onboarding_State;
use OpenWP\Inc\Providers\ProviderRequest;
use OpenWP\Inc\Providers\Provider_Factory;
use OpenWP\Inc\Security\Provider_Key_Manager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles OpenWP API endpoints.
 */
class OpenWP_Controller extends Api_Base {
	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/bootstrap',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'bootstrap' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_run_agent' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/agent/execute',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'execute_agent' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_run_agent' );
				},
				'args'                => [
					'prompt'   => [ 'required' => true, 'type' => 'string' ],
					'provider' => [ 'required' => false, 'type' => 'string' ],
					'model'    => [ 'required' => false, 'type' => 'string' ],
					'conversation_context' => [ 'required' => false, 'type' => 'object' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/editor/generate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_editor_content' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'edit_posts' );
				},
				'args'                => [
					'prompt'       => [ 'required' => true, 'type' => 'string' ],
					'content_type' => [ 'required' => false, 'type' => 'string' ],
					'tone'         => [ 'required' => false, 'type' => 'string' ],
					'provider'     => [ 'required' => false, 'type' => 'string' ],
					'model'        => [ 'required' => false, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/agent/execute/stream',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'execute_agent_stream' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_run_agent' );
				},
				'args'                => [
					'prompt'    => [ 'required' => true, 'type' => 'string' ],
					'provider'  => [ 'required' => false, 'type' => 'string' ],
					'model'     => [ 'required' => false, 'type' => 'string' ],
					'conversation_context' => [ 'required' => false, 'type' => 'object' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/chatbot/upload',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'chatbot_upload' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_run_agent' );
				},
				'args'                => [
					'session_token' => [ 'required' => true, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/chatbot/execute/stream',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'execute_chatbot_stream' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_run_agent' );
				},
				'args'                => [
					'prompt'               => [ 'required' => true, 'type' => 'string' ],
					'provider'             => [ 'required' => false, 'type' => 'string' ],
					'model'                => [ 'required' => false, 'type' => 'string' ],
					'session_token'        => [ 'required' => true, 'type' => 'string' ],
					'attachment_ids'       => [ 'required' => false, 'type' => 'array' ],
					'conversation_context' => [ 'required' => false, 'type' => 'object' ],
					'page_context'         => [ 'required' => false, 'type' => 'object' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/chatbot/session/clear',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'clear_chatbot_session' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_run_agent' );
				},
				'args'                => [
					'session_token' => [ 'required' => true, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/editor/generate/stream',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_editor_content_stream' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'edit_posts' );
				},
				'args'                => [
					'prompt'       => [ 'required' => true, 'type' => 'string' ],
					'content_type' => [ 'required' => false, 'type' => 'string' ],
					'tone'         => [ 'required' => false, 'type' => 'string' ],
					'provider'     => [ 'required' => false, 'type' => 'string' ],
					'model'        => [ 'required' => false, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/editor/modify/stream',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'modify_editor_block_stream' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'edit_posts' );
				},
				'args'                => [
					'block_content' => [ 'required' => true, 'type' => 'string' ],
					'instruction'   => [ 'required' => true, 'type' => 'string' ],
					'provider'      => [ 'required' => false, 'type' => 'string' ],
					'model'         => [ 'required' => false, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/editor/enhance-prompt',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'enhance_prompt' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'edit_posts' );
				},
				'args'                => [
					'prompt'       => [ 'required' => true, 'type' => 'string' ],
					'content_type' => [ 'required' => false, 'type' => 'string' ],
					'provider'     => [ 'required' => false, 'type' => 'string' ],
					'model'        => [ 'required' => false, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/actions',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_actions' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_run_agent' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/approvals',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_approvals' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_approve_actions' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/approvals/(?P<id>\d+)/approve',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'approve_action' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_approve_actions' );
				},
				'args'                => [
					'typed_confirmation' => [ 'required' => false, 'type' => 'string' ],
					'note'               => [ 'required' => false, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/approvals/(?P<id>\d+)/reject',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reject_action' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_approve_actions' );
				},
				'args'                => [
					'note' => [ 'required' => false, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/logs',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_logs' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_view_logs' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/logs/(?P<id>\d+)/rollback',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rollback_log' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_approve_actions' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => function( $request ) {
						return $this->validate_permission( $request, 'openwp_manage_settings' );
					},
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => function( $request ) {
						return $this->validate_permission( $request, 'openwp_manage_settings' );
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/onboarding',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_onboarding' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_manage_settings' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/memory',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_memory' ],
					'permission_callback' => function( $request ) {
						return $this->validate_permission( $request, 'openwp_manage_settings' );
					},
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_memory' ],
					'permission_callback' => function( $request ) {
						return $this->validate_permission( $request, 'openwp_manage_settings' );
					},
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_memory' ],
					'permission_callback' => function( $request ) {
						return $this->validate_permission( $request, 'openwp_manage_settings' );
					},
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/onboarding/progress',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_onboarding_progress' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_manage_settings' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/onboarding/complete',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'complete_onboarding' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_manage_settings' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/backups',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_backups' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_view_logs' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/backups/(?P<id>\d+)/restore',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'restore_backup' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_approve_actions' );
				},
				'args'                => [
					'typed_confirmation' => [ 'required' => true, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/installed-plugins',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_installed_plugins' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'manage_options' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/mcp/tools',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_mcp_tools' ],
				'permission_callback' => function( $request ) {
					return $this->validate_permission( $request, 'openwp_run_agent' );
				},
			]
		);
	}

	/**
	 * Bootstrap endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function bootstrap( $request ) {
		$settings = Settings::get();
		return [
			'capabilities' => [
				'run_agent'      => current_user_can( 'openwp_run_agent' ) || current_user_can( 'manage_options' ),
				'approve_actions'=> current_user_can( 'openwp_approve_actions' ) || current_user_can( 'manage_options' ),
				'manage_settings'=> current_user_can( 'openwp_manage_settings' ) || current_user_can( 'manage_options' ),
				'view_logs'      => current_user_can( 'openwp_view_logs' ) || current_user_can( 'manage_options' ),
			],
			'settings'     => $settings,
			'provider'     => Settings::provider_status(),
			'actions_count'=> count( Action_Registry::all() ),
		];
	}

	/**
	 * Execute agent prompt.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_agent( $request ) {
		$engine = new Agent_Engine();
		return $engine->execute_prompt(
			(string) $request->get_param( 'prompt' ),
			[
				'provider' => $request->get_param( 'provider' ),
				'model'    => $request->get_param( 'model' ),
				'conversation_context' => $request->get_param( 'conversation_context' ),
			]
		);
	}

	/**
	 * Execute agent prompt with SSE streaming.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return null Output is sent directly via SSE.
	 */
	public function execute_agent_stream( $request ) {
		SSE_Response::start();

		$engine = new Agent_Engine();
		$result = $engine->execute_prompt_stream(
			(string) $request->get_param( 'prompt' ),
			[
				'provider'  => $request->get_param( 'provider' ),
				'model'     => $request->get_param( 'model' ),
				'conversation_context' => $request->get_param( 'conversation_context' ),
			],
			static function ( $event ) {
				SSE_Response::send( $event );
			}
		);

		if ( is_wp_error( $result ) ) {
			SSE_Response::send(
				[
					'type'    => 'error',
					'message' => $result->get_error_message(),
				]
			);
		} else {
			SSE_Response::send(
				[
					'type'    => 'done',
					'summary' => $result,
				]
			);
		}

		SSE_Response::done();
		return null;
	}

	/**
	 * Generate a direct conversational reply for chatbot-only queries.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @param string                                $prompt Prompt text.
	 * @param array<int,array<string,mixed>>        $selected_items Selected attachments.
	 * @param array<string,mixed>                   $settings OpenWP settings.
	 * @param string                                $page_context_block Page context block.
	 * @return array<string,mixed>|WP_Error
	 */
	private function generate_chatbot_direct_reply( $request, $prompt, $selected_items, $settings, $page_context_block = '' ) {
		$provider = sanitize_key( (string) ( $request->get_param( 'provider' ) ?: $this->resolve_default_provider( $settings ) ) );
		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = $this->resolve_default_provider( $settings );
		}

		$model = sanitize_text_field( (string) $request->get_param( 'model' ) );
		if ( '' === $model ) {
			$model = $this->resolve_default_model( $provider, $settings );
		}

		$client = Provider_Factory::create( $provider );
		if ( ! $client ) {
			return new WP_Error( 'openwp_provider_invalid', __( 'Invalid or unsupported provider.', 'openwp' ) );
		}

		$attachment_service = new Attachment_Service();
		$attachment_block   = $attachment_service->build_attachment_prompt_block( $selected_items );

		$conversation_block = $this->build_chatbot_conversation_context_block( $request->get_param( 'conversation_context' ) );

		$memory_service = new Memory_Service();
		$memory_items   = $memory_service->relevant_for_prompt( $prompt, 8, 1200 );
		$memory_block   = '';
		if ( ! empty( $memory_items ) ) {
			$memory_lines = [];
			foreach ( $memory_items as $item ) {
				if ( ! empty( $item['context'] ) ) {
					$memory_lines[] = (string) $item['context'];
				}
			}
			if ( ! empty( $memory_lines ) ) {
				$encoded      = wp_json_encode( array_values( $memory_lines ) );
				$memory_block = is_string( $encoded ) ? $encoded : '';
			}
		}

		$system_prompt = "You are OpenWP's sitewide chat assistant inside WordPress.\n"
			. "You handle two types of requests:\n"
			. "1. CONVERSATIONAL: Questions, explanations, advice, troubleshooting — set needs_agent=false and write a concise reply.\n"
			. "2. AGENT ACTION: Requests requiring WordPress admin operations — set needs_agent=true and leave reply as an empty string.\n"
			. "Set needs_agent=true when the user wants to: list, create, update, or delete posts/pages/media/users/comments/terms; activate/deactivate/install/update/delete plugins or themes; read or change WordPress options/settings; run database queries; manage WooCommerce products/orders/customers; work with Polylang translations; remember/save/store preferences or facts (memory); forget/remove/clear saved memories; or perform any other WordPress admin operation.\n"
			. "Set needs_agent=false for questions, explanations, how-to guidance, tips, or when no WordPress admin action is required.\n"
			. "You may use PAGE_CONTEXT, ATTACHMENTS_CONTEXT, CONVERSATION_CONTEXT and MEMORY_CONTEXT to answer.\n"
			. "MEMORY_CONTEXT contains the user's saved preferences, constraints, and facts. Use them to personalise your reply without mentioning them unless directly relevant.\n"
			. "If the request requires pixel-level or binary file inspection and context only has metadata/URLs, be transparent that direct visual/binary inspection is limited, then suggest the user share a short description or extractable text.\n"
			. "If PAGE_CONTEXT is available, ground your response in it and ask a brief follow-up when context is insufficient.\n"
			. "Keep replies concise and useful.\n"
			. "Return only valid JSON.";

		$user_prompt = "User request:\n" . $prompt;
		if ( '' !== $conversation_block ) {
			$user_prompt .= "\n\nCONVERSATION_CONTEXT=" . $conversation_block;
		}
		if ( '' !== $page_context_block ) {
			$user_prompt .= $page_context_block;
		}
		if ( '' !== $attachment_block ) {
			$user_prompt .= $attachment_block;
		}
		if ( '' !== $memory_block ) {
			$user_prompt .= "\n\nMEMORY_CONTEXT=" . $memory_block;
		}

		$user_content_blocks = $this->build_chatbot_user_content_blocks( $user_prompt, $selected_items );

		$request_args = [
			'system_prompt' => $system_prompt,
			'prompt'        => $user_prompt,
			'model'         => $model,
			'schema'        => [
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'reply', 'needs_agent' ],
				'properties'           => [
					'reply'       => [ 'type' => 'string' ],
					'needs_agent' => [ 'type' => 'boolean' ],
				],
			],
			'temperature'   => 0.4,
			'timeout'       => max( 15, absint( $settings['timeout_seconds'] ?? 45 ) ),
			'max_tokens'    => 1400,
			'user_content'  => $user_content_blocks,
		];

		$request_payload = new ProviderRequest(
			[
				'system_prompt' => $request_args['system_prompt'],
				'prompt'        => $request_args['prompt'],
				'model'         => $request_args['model'],
				'schema'        => $request_args['schema'],
				'stream'        => false,
				'temperature'   => $request_args['temperature'],
				'timeout'       => $request_args['timeout'],
				'max_tokens'    => $request_args['max_tokens'],
				'user_content'  => $request_args['user_content'],
			]
		);

		$provider_response = $client->generate( $request_payload );
		$raw_output        = '';
		$input_tokens      = 0;
		$output_tokens     = 0;
		$first_error       = null;
		if ( is_wp_error( $provider_response ) ) {
			$first_error = $provider_response;
		} else {
			$raw_output    = trim( (string) $provider_response->output_text );
			$input_tokens  = (int) $provider_response->input_tokens;
			$output_tokens = (int) $provider_response->output_tokens;
		}

		if ( '' === $raw_output ) {
			$stream_request = new ProviderRequest(
				[
					'system_prompt' => $request_args['system_prompt'],
					'prompt'        => $request_args['prompt'],
					'model'         => $request_args['model'],
					'schema'        => $request_args['schema'],
					'stream'        => true,
					'temperature'   => $request_args['temperature'],
					'timeout'       => $request_args['timeout'],
					'max_tokens'    => $request_args['max_tokens'],
					'user_content'  => $request_args['user_content'],
				]
			);

			$stream_response = $client->generate_stream(
				$stream_request,
				static function ( $delta ) {
					// Streaming fallback: text is aggregated from final provider response.
				}
			);

			if ( is_wp_error( $stream_response ) ) {
				if ( is_wp_error( $first_error ) ) {
					return $first_error;
				}
				return $stream_response;
			}

			$raw_output    = trim( (string) $stream_response->output_text );
			$input_tokens  = (int) $stream_response->input_tokens;
			$output_tokens = (int) $stream_response->output_tokens;
		}

		if ( '' === $raw_output ) {
			if ( is_wp_error( $first_error ) ) {
				return $first_error;
			}
			return new WP_Error( 'openwp_chatbot_reply_empty', __( 'No chatbot reply was generated.', 'openwp' ) );
		}

		$raw_json    = $this->extract_json_object( $raw_output );
		$decoded     = json_decode( $raw_json, true );
		$reply       = '';
		$needs_agent = false;

		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['reply'] ) ) {
				$reply = trim( (string) $decoded['reply'] );
			}
			if ( isset( $decoded['needs_agent'] ) ) {
				$needs_agent = (bool) $decoded['needs_agent'];
			}
		}

		$memory_keys = [];
		foreach ( $memory_items as $item ) {
			if ( ! empty( $item['key'] ) ) {
				$memory_keys[] = (string) $item['key'];
			}
		}

		// When the LLM flags this as an agent action request, skip the reply text.
		if ( $needs_agent ) {
			return [
				'provider'      => $provider,
				'model'         => $model,
				'reply'         => '',
				'needs_agent'   => true,
				'input_tokens'  => $input_tokens,
				'output_tokens' => $output_tokens,
				'memory_used'   => count( $memory_keys ),
				'memory_items'  => $memory_keys,
			];
		}

		if ( '' === $reply && '' !== $raw_output ) {
			$reply = trim( wp_strip_all_tags( $raw_output ) );
		}

		if ( '' === $reply ) {
			return new WP_Error( 'openwp_chatbot_reply_empty', __( 'No chatbot reply was generated.', 'openwp' ) );
		}

		return [
			'provider'      => $provider,
			'model'         => $model,
			'reply'         => $reply,
			'needs_agent'   => false,
			'input_tokens'  => $input_tokens,
			'output_tokens' => $output_tokens,
			'memory_used'   => count( $memory_keys ),
			'memory_items'  => $memory_keys,
		];
	}

	/**
	 * Build optional multimodal user content blocks for direct chatbot replies.
	 *
	 * @param string                          $user_prompt Prompt text with all context blocks.
	 * @param array<int,array<string,mixed>>  $selected_items Selected attachment records.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_chatbot_user_content_blocks( $user_prompt, $selected_items ) {
		if ( ! is_array( $selected_items ) || empty( $selected_items ) ) {
			return [];
		}

		$image_blocks             = [];
		$max_inline_images        = 2;
		$max_inline_image_bytes   = 5242880; // 5 MB per image.
		$allowed_image_mime_types = [
			'image/jpeg',
			'image/png',
			'image/webp',
			'image/gif',
			'image/bmp',
		];

		foreach ( $selected_items as $item ) {
			if ( count( $image_blocks ) >= $max_inline_images ) {
				break;
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			$mime_type = strtolower( sanitize_text_field( (string) ( $item['mime_type'] ?? '' ) ) );
			if ( '' === $mime_type || ! in_array( $mime_type, $allowed_image_mime_types, true ) ) {
				continue;
			}

			$file_path = (string) ( $item['file_path'] ?? '' );
			if ( '' === $file_path || ! is_readable( $file_path ) || ! file_exists( $file_path ) ) {
				continue;
			}

			$file_size = isset( $item['size_bytes'] ) ? absint( $item['size_bytes'] ) : 0;
			if ( $file_size <= 0 || $file_size > $max_inline_image_bytes ) {
				continue;
			}

			$image_data_uri = $this->build_chatbot_image_data_uri( $file_path, $mime_type );
			if ( '' === $image_data_uri ) {
				continue;
			}

			$image_blocks[] = [
				'type' => 'image_url',
				'url'  => $image_data_uri,
			];
		}

		if ( empty( $image_blocks ) ) {
			return [];
		}

		array_unshift(
			$image_blocks,
			[
				'type' => 'text',
				'text' => (string) $user_prompt,
			]
		);

		return $image_blocks;
	}

	/**
	 * Encode an image file as data URI for multimodal provider requests.
	 *
	 * @param string $file_path Absolute file path.
	 * @param string $mime_type Image MIME type.
	 * @return string
	 */
	private function build_chatbot_image_data_uri( $file_path, $mime_type ) {
		if ( '' === $file_path || '' === $mime_type || ! is_readable( $file_path ) ) {
			return '';
		}

		$raw = file_get_contents( $file_path );
		if ( false === $raw || '' === $raw ) {
			return '';
		}

		$encoded = base64_encode( $raw );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return '';
		}

		return 'data:' . $mime_type . ';base64,' . $encoded;
	}

	/**
	 * Try generating and streaming a direct chatbot reply.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @param string                                $prompt Prompt text.
	 * @param array<int,array<string,mixed>>        $selected_items Selected attachments.
	 * @param array<string,mixed>                   $settings OpenWP settings.
	 * @param string                                $page_context_block Page context block.
	 * @return true|WP_Error
	 */
	private function try_send_chatbot_direct_reply( $request, $prompt, $selected_items, $settings, $page_context_block = '' ) {
		$chat_reply = $this->generate_chatbot_direct_reply(
			$request,
			$prompt,
			$selected_items,
			$settings,
			$page_context_block
		);

		if ( is_wp_error( $chat_reply ) ) {
			return $chat_reply;
		}

		SSE_Response::send(
			[
				'type'    => 'result',
				'status'  => 'success',
				'message' => (string) $chat_reply['reply'],
			]
		);

		SSE_Response::send(
			[
				'type'    => 'done',
				'summary' => [
					'status'    => 'success',
					'mode'      => 'chat',
					'provider'  => $chat_reply['provider'],
					'model'     => $chat_reply['model'],
					'execution' => [
						'status'  => 'success',
						'action'  => 'chat_reply',
						'message' => $chat_reply['reply'],
						'data'    => [
							'reply'            => $chat_reply['reply'],
							'source'           => 'chatbot_direct_reply',
							'attachments_used' => count( $selected_items ),
						],
					],
					'token_usage' => [
						'input_tokens'  => (int) $chat_reply['input_tokens'],
						'output_tokens' => (int) $chat_reply['output_tokens'],
					],
				],
			]
		);

		return true;
	}

	/**
	 * Build a bounded conversation context block for chatbot direct replies.
	 *
	 * @param mixed $conversation_context Conversation context payload.
	 * @return string
	 */
	private function build_chatbot_conversation_context_block( $conversation_context ) {
		if ( ! is_array( $conversation_context ) || empty( $conversation_context['enabled'] ) ) {
			return '';
		}

		$raw_turns = isset( $conversation_context['turns'] ) && is_array( $conversation_context['turns'] )
			? $conversation_context['turns']
			: [];
		if ( empty( $raw_turns ) ) {
			return '';
		}

		$turns       = [];
		$total_chars = 0;
		foreach ( $raw_turns as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}

			$user      = $this->sanitize_chatbot_context_text( $turn['user'] ?? '', 500 );
			$assistant = $this->sanitize_chatbot_context_text( $turn['assistant'] ?? '', 700 );
			if ( '' === $user || '' === $assistant ) {
				continue;
			}

			$turn_chars = strlen( $user ) + strlen( $assistant );
			if ( ( $total_chars + $turn_chars ) > 3000 ) {
				break;
			}

			$turns[] = [
				'user'      => $user,
				'assistant' => $assistant,
			];
			$total_chars += $turn_chars;

			if ( count( $turns ) >= 3 ) {
				break;
			}
		}

		if ( empty( $turns ) ) {
			return '';
		}

		$encoded = wp_json_encode( array_values( $turns ) );
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Sanitize chatbot conversation text.
	 *
	 * @param mixed $text Input text.
	 * @param int   $max_len Maximum length.
	 * @return string
	 */
	private function sanitize_chatbot_context_text( $text, $max_len ) {
		$sanitized = wp_strip_all_tags( (string) $text );
		$sanitized = preg_replace( '/\s+/u', ' ', $sanitized );
		$sanitized = is_string( $sanitized ) ? trim( $sanitized ) : '';
		if ( '' === $sanitized ) {
			return '';
		}

		if ( strlen( $sanitized ) <= $max_len ) {
			return $sanitized;
		}

		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $sanitized, 0, $max_len );
		}

		return substr( $sanitized, 0, $max_len );
	}

	/**
	 * Normalize incoming page context for chatbot prompts.
	 *
	 * @param mixed $raw Raw page context.
	 * @return array<string,mixed>
	 */
	private function normalize_chatbot_page_context( $raw ) {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$normalized = [];
		$string_fields = [
			'surface'     => 24,
			'page_type'   => 80,
			'title'       => 180,
			'url'         => 500,
			'post_type'   => 64,
			'post_status' => 32,
			'permalink'   => 500,
			'excerpt'     => 2000,
			'screen_id'   => 120,
			'screen_base' => 120,
			'parent_base' => 120,
			'page_slug'   => 120,
			'client_url'  => 500,
			'client_title' => 180,
			'trigger'      => 24,
		];

		foreach ( $string_fields as $key => $max_len ) {
			if ( ! isset( $raw[ $key ] ) ) {
				continue;
			}

			$value = (string) $raw[ $key ];
			if ( in_array( $key, [ 'url', 'permalink', 'client_url' ], true ) ) {
				$value = esc_url_raw( $value );
			}

			$value = $this->sanitize_chatbot_page_context_text( $value, $max_len );
			if ( '' !== $value ) {
				$normalized[ $key ] = $value;
			}
		}

		if ( isset( $raw['post_id'] ) ) {
			$post_id = absint( $raw['post_id'] );
			if ( $post_id > 0 ) {
				$normalized['post_id'] = $post_id;
			}
		}

		return $normalized;
	}

	/**
	 * Sanitize and bound page context text values.
	 *
	 * @param mixed $text Raw value.
	 * @param int   $max_len Maximum length.
	 * @return string
	 */
	private function sanitize_chatbot_page_context_text( $text, $max_len ) {
		$sanitized = wp_strip_all_tags( (string) $text );
		$sanitized = preg_replace( '/\s+/u', ' ', $sanitized );
		$sanitized = is_string( $sanitized ) ? trim( $sanitized ) : '';
		if ( '' === $sanitized ) {
			return '';
		}

		if ( strlen( $sanitized ) <= $max_len ) {
			return $sanitized;
		}

		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $sanitized, 0, $max_len );
		}

		return substr( $sanitized, 0, $max_len );
	}

	/**
	 * Build page context block for chatbot prompt injection.
	 *
	 * @param array<string,mixed> $normalized_context Normalized context.
	 * @return string
	 */
	private function build_chatbot_page_context_block( $normalized_context ) {
		if ( empty( $normalized_context ) ) {
			return '';
		}

		$encoded = wp_json_encode( $normalized_context );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return '';
		}

		return "\n\nPAGE_CONTEXT=" . $encoded . "\nUse PAGE_CONTEXT only when relevant to the user request.";
	}

	/**
	 * Upload a temporary chatbot attachment.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function chatbot_upload( $request ) {
		$session_token = sanitize_text_field( (string) $request->get_param( 'session_token' ) );
		if ( '' === $session_token ) {
			return new WP_Error( 'openwp_chatbot_session_required', __( 'Session token is required.', 'openwp' ) );
		}

		$file_params = $request->get_file_params();
		$file        = isset( $file_params['file'] ) && is_array( $file_params['file'] ) ? $file_params['file'] : null;
		if ( ! is_array( $file ) ) {
			return new WP_Error( 'openwp_chatbot_file_required', __( 'File is required.', 'openwp' ) );
		}

		$service = new Attachment_Service();
		$result  = $service->upload( get_current_user_id(), $session_token, $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$items = $service->list_session_items( get_current_user_id(), $session_token );

		return [
			'success'     => true,
			'item'        => $result['item'],
			'total_files' => absint( $result['total_files'] ?? count( $items ) ),
			'items'       => $items,
		];
	}

	/**
	 * Execute chatbot prompt with optional attachment context via SSE.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return null Output is sent directly via SSE.
	 */
	public function execute_chatbot_stream( $request ) {
		SSE_Response::start();

		if ( OPENWP_DISABLE_AGENT ) {
			SSE_Response::send(
				[
					'type'    => 'error',
					'message' => __( 'Agent execution is disabled via OPENWP_DISABLE_AGENT.', 'openwp' ),
				]
			);
			SSE_Response::done();
			return null;
		}

		$settings = Settings::get();
		if ( ! empty( $settings['kill_switch'] ) ) {
			SSE_Response::send(
				[
					'type'    => 'error',
					'message' => __( 'Agent execution is disabled in OpenWP settings.', 'openwp' ),
				]
			);
			SSE_Response::done();
			return null;
		}

		$prompt = trim( (string) $request->get_param( 'prompt' ) );
		if ( '' === $prompt ) {
			SSE_Response::send(
				[
					'type'    => 'error',
					'message' => __( 'Prompt is required.', 'openwp' ),
				]
			);
			SSE_Response::done();
			return null;
		}

		$session_token = sanitize_text_field( (string) $request->get_param( 'session_token' ) );
		if ( '' === $session_token ) {
			SSE_Response::send(
				[
					'type'    => 'error',
					'message' => __( 'Session token is required.', 'openwp' ),
				]
			);
			SSE_Response::done();
			return null;
		}

		$attachment_ids = $request->get_param( 'attachment_ids' );
		$attachment_ids = is_array( $attachment_ids ) ? $attachment_ids : [];
		$page_context   = $this->normalize_chatbot_page_context( $request->get_param( 'page_context' ) );

		$attachment_service = new Attachment_Service();
		$selected_items     = $attachment_service->resolve_selected_items( get_current_user_id(), $session_token, $attachment_ids );
		if ( is_wp_error( $selected_items ) ) {
			SSE_Response::send(
				[
					'type'    => 'error',
					'message' => $selected_items->get_error_message(),
				]
			);
			SSE_Response::done();
			return null;
		}

		$page_context_block = $this->build_chatbot_page_context_block( $page_context );

		// Step 1: Classify intent + optionally generate a direct conversational reply.
		$chat_result = $this->generate_chatbot_direct_reply( $request, $prompt, $selected_items, $settings, $page_context_block );

		if ( is_wp_error( $chat_result ) ) {
			SSE_Response::send(
				[
					'type'    => 'error',
					'message' => $chat_result->get_error_message(),
				]
			);
			SSE_Response::done();
			return null;
		}

		if ( ! empty( $chat_result['needs_agent'] ) ) {
			// Step 2a: Action request — delegate to Agent_Engine.
			$engine = new Agent_Engine();
			$result = $engine->execute_prompt_stream(
				$prompt,
				[
					'provider'             => sanitize_key( (string) ( $request->get_param( 'provider' ) ?: $this->resolve_default_provider( $settings ) ) ),
					'model'                => sanitize_text_field( (string) $request->get_param( 'model' ) ),
					'conversation_context' => $request->get_param( 'conversation_context' ),
				],
				static function ( $event ) {
					SSE_Response::send( $event );
				}
			);

			if ( is_wp_error( $result ) ) {
				SSE_Response::send(
					[
						'type'    => 'error',
						'message' => $result->get_error_message(),
					]
				);
			} else {
				// Synthesize a human-readable reply from the action result data
				// so the widget can display it as natural language instead of raw JSON.
				$synthesized = $this->synthesize_chatbot_action_reply( $request, $prompt, $result, $settings );
				if ( '' !== $synthesized ) {
					if ( ! is_array( $result['execution'] ?? null ) ) {
						$result['execution'] = [];
					}
					if ( ! is_array( $result['execution']['data'] ?? null ) ) {
						$result['execution']['data'] = [];
					}
					$result['execution']['data']['reply'] = $synthesized;
				}

				SSE_Response::send(
					[
						'type'    => 'done',
						'summary' => $result,
					]
				);
			}
		} else {
			// Step 2b: Conversational reply — stream direct answer.
			SSE_Response::send(
				[
					'type'    => 'result',
					'status'  => 'success',
					'message' => (string) $chat_result['reply'],
				]
			);

			SSE_Response::send(
				[
					'type'    => 'done',
					'summary' => [
						'status'    => 'success',
						'mode'      => 'chat',
						'provider'  => $chat_result['provider'],
						'model'     => $chat_result['model'],
						'execution' => [
							'status'  => 'success',
							'action'  => 'chat_reply',
							'message' => $chat_result['reply'],
							'data'    => [
								'reply'            => $chat_result['reply'],
								'source'           => 'chatbot_direct_reply',
								'attachments_used' => count( $selected_items ),
								'memory_used'      => (int) ( $chat_result['memory_used'] ?? 0 ),
								'memory_items'     => $chat_result['memory_items'] ?? [],
							],
						],
						'token_usage' => [
							'input_tokens'  => (int) $chat_result['input_tokens'],
							'output_tokens' => (int) $chat_result['output_tokens'],
						],
					],
				]
			);
		}

		SSE_Response::done();
		return null;
	}

	/**
	 * Build a compact synopsis of execution data for the synthesis LLM prompt.
	 *
	 * Caps at ~800 characters so we never blow up the context window with
	 * a full WooCommerce order dump or a 100-plugin list.
	 *
	 * @param array<string,mixed> $data Execution data array.
	 * @return string Compact JSON synopsis string.
	 */
	private function build_data_synopsis( array $data ) {
		$synopsis = [];

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$count           = count( $value );
				$synopsis[ $key ] = [
					'count'         => $count,
					'sample_fields' => is_array( $value[0] ?? null ) ? array_keys( $value[0] ) : null,
					'sample'        => array_slice( $value, 0, 2 ),
				];
			} else {
				$synopsis[ $key ] = $value;
			}
		}

		$encoded = wp_json_encode( $synopsis );
		if ( ! is_string( $encoded ) ) {
			return '{}';
		}

		// Hard-cap at 800 chars to stay within LLM prompt budget.
		if ( strlen( $encoded ) > 800 ) {
			$encoded = substr( $encoded, 0, 797 ) . '...';
		}

		return $encoded;
	}

	/**
	 * Synthesize a human-readable reply from an agent execution result.
	 *
	 * Makes a lightweight non-streaming LLM call to turn raw action data
	 * (e.g. a plugin list array) into a natural-language sentence or paragraph
	 * suitable for display in the sitewide chatbot widget.
	 *
	 * Returns an empty string when synthesis is not needed or fails gracefully,
	 * so the caller can fall back to the existing execution.message.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request  Original REST request.
	 * @param string                               $prompt   Original user prompt.
	 * @param array<string,mixed>                  $result   Agent execution result array.
	 * @param array<string,mixed>                  $settings Plugin settings.
	 * @return string Synthesized reply, or empty string.
	 */
	private function synthesize_chatbot_action_reply( $request, $prompt, $result, $settings ) {
		// Only synthesize on success.
		$status = isset( $result['status'] ) ? (string) $result['status'] : '';
		if ( ! in_array( $status, [ 'success', 'awaiting_approval' ], true ) ) {
			return '';
		}

		$execution = isset( $result['execution'] ) && is_array( $result['execution'] ) ? $result['execution'] : [];
		$exec_data = isset( $execution['data'] ) && is_array( $execution['data'] ) ? $execution['data'] : [];

		// If execution data is empty there is nothing to synthesize.
		if ( empty( $exec_data ) ) {
			return '';
		}

		$provider = sanitize_key( (string) ( $request->get_param( 'provider' ) ?: $this->resolve_default_provider( $settings ) ) );
		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = $this->resolve_default_provider( $settings );
		}

		$model = sanitize_text_field( (string) $request->get_param( 'model' ) );
		if ( '' === $model ) {
			$model = $this->resolve_default_model( $provider, $settings );
		}

		$client = Provider_Factory::create( $provider );
		if ( ! $client ) {
			return '';
		}

		$action_key = isset( $execution['action'] ) ? (string) $execution['action'] : 'unknown';

		$system_prompt = "You are a helpful WordPress assistant. "
			. "The user issued a command and the system executed it. "
			. "The result data will be displayed visually as a table or card below your reply — do NOT list individual items. "
			. "Write exactly ONE short sentence that summarises the outcome (e.g. total count, what was done). "
			. "Be specific about counts when present. Speak naturally. No markdown. "
			. "Return only valid JSON with a single field: reply (string).";

		// Build a compact synopsis so we never send a huge payload to the LLM.
		$synopsis = $this->build_data_synopsis( $exec_data );

		$user_prompt = "User request: " . $prompt . "\n"
			. "Action performed: " . $action_key . "\n"
			. "Result synopsis: " . $synopsis;

		$request_payload = new ProviderRequest(
			[
				'system_prompt' => $system_prompt,
				'prompt'        => $user_prompt,
				'model'         => $model,
				'schema'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'reply' ],
					'properties'           => [
						'reply' => [ 'type' => 'string' ],
					],
				],
				'stream'        => false,
				'temperature'   => 0.3,
				'timeout'       => 20,
				'max_tokens'    => 600,
			]
		);

		$response = $client->generate( $request_payload );
		if ( is_wp_error( $response ) ) {
			return '';
		}

		$raw = trim( (string) $response->output_text );
		if ( '' === $raw ) {
			return '';
		}

		$raw_json = $this->extract_json_object( $raw );
		$decoded  = json_decode( $raw_json, true );

		if ( is_array( $decoded ) && isset( $decoded['reply'] ) ) {
			$reply = trim( (string) $decoded['reply'] );
			return '' !== $reply ? $reply : '';
		}

		// Fallback: if JSON parsing failed, return the raw text stripped of tags.
		$plain = trim( wp_strip_all_tags( $raw ) );
		return '' !== $plain ? $plain : '';
	}

	/**
	 * Clear temporary chatbot session files.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function clear_chatbot_session( $request ) {
		$session_token = sanitize_text_field( (string) $request->get_param( 'session_token' ) );
		if ( '' === $session_token ) {
			return new WP_Error( 'openwp_chatbot_session_required', __( 'Session token is required.', 'openwp' ) );
		}

		$service = new Attachment_Service();
		$result  = $service->clear_session( get_current_user_id(), $session_token );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'success'       => true,
			'deleted_files' => absint( $result['deleted_files'] ?? 0 ),
		];
	}

	/**
	 * Generate AI content for the Gutenberg editor block.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_editor_content( $request ) {
		if ( OPENWP_DISABLE_AGENT ) {
			return new WP_Error( 'openwp_agent_disabled', __( 'Agent execution is disabled via OPENWP_DISABLE_AGENT.', 'openwp' ) );
		}

		$settings = Settings::get();
		if ( ! empty( $settings['kill_switch'] ) ) {
			return new WP_Error( 'openwp_agent_kill_switch', __( 'Agent execution is disabled in OpenWP settings.', 'openwp' ) );
		}

		$prompt = trim( (string) $request->get_param( 'prompt' ) );
		if ( '' === $prompt ) {
			return new WP_Error( 'openwp_editor_prompt_required', __( 'Prompt is required.', 'openwp' ) );
		}

		$content_type = sanitize_text_field( (string) ( $request->get_param( 'content_type' ) ?: 'hero_section' ) );
		$tone         = sanitize_text_field( (string) ( $request->get_param( 'tone' ) ?: 'professional' ) );
		$provider     = sanitize_key( (string) ( $request->get_param( 'provider' ) ?: $this->resolve_default_provider( $settings ) ) );
		$model        = sanitize_text_field( (string) $request->get_param( 'model' ) );

		// Sanitize optional brand palette (array of hex colors).
		$palette     = [];
		$raw_palette = $request->get_param( 'palette' );
		if ( is_array( $raw_palette ) ) {
			foreach ( $raw_palette as $color ) {
				$hex = sanitize_hex_color( (string) $color );
				if ( $hex ) {
					$palette[] = $hex;
				}
			}
		}

		if ( empty( $palette ) ) {
			$palette = $this->resolve_palette( $settings );
		}

		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = $this->resolve_default_provider( $settings );
		}

		if ( '' === $model ) {
			$model = $this->resolve_default_model( $provider, $settings );
		}

		$client = Provider_Factory::create( $provider );
		if ( ! $client ) {
			return new WP_Error( 'openwp_provider_invalid', __( 'Invalid or unsupported provider.', 'openwp' ) );
		}

		// Full-page generation produces much larger responses and takes longer.
		// Override the global timeout to at least 3 minutes for full-page types.
		$is_full_page = 0 === strpos( $content_type, 'full_' );
		$request_timeout = $this->resolve_request_timeout(
			(int) $settings['timeout_seconds'],
			$provider,
			$model,
			$is_full_page
		);

		// Extend PHP execution time so the process is not killed before cURL finishes.
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( $request_timeout + 30 );
		}

		$max_tokens = $this->resolve_generation_max_tokens( $is_full_page, $provider, $model );

		$request_payload = new ProviderRequest(
			[
				'system_prompt' => $this->editor_content_system_prompt(),
				'prompt'        => $this->editor_content_user_prompt( $prompt, $content_type, $tone, $palette ),
				'model'         => $model,
				'schema'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'content' ],
					'properties'           => [
						'content' => [ 'type' => 'string' ],
					],
				],
				'stream'        => true,
				'temperature'   => 0.3,
				'timeout'       => $request_timeout,
				'max_tokens'    => $max_tokens,
			]
		);

		$provider_response = $client->generate( $request_payload );
		if ( is_wp_error( $provider_response ) ) {
			return $provider_response;
		}

		$raw_output = trim( (string) $provider_response->output_text );
		$raw_json   = $this->extract_json_object( $raw_output );
		$decoded    = json_decode( $raw_json, true );

		$content = '';
		if ( is_array( $decoded ) ) {
			$content = $this->extract_editor_content_from_payload( $decoded );
		}

		// Fallback: if JSON extraction yielded nothing, use the raw output directly.
		if ( '' === $content && '' !== $raw_output ) {
			$content = $this->strip_content_wrapper( $raw_output );
		}

		if ( '' === $content ) {
			return new WP_Error( 'openwp_editor_missing_content', __( 'Model response did not include content.', 'openwp' ) );
		}

		if ( false === strpos( $content, '<!-- wp:' ) ) {
			$content = $this->normalize_html_to_blocks( $content );
		}

		$content = $this->sanitize_block_markup( $content );

		return [
			'status'      => 'success',
			'provider'    => $provider,
			'model'       => $model,
			'content'     => $content,
			'token_usage' => [
				'input_tokens'  => (int) $provider_response->input_tokens,
				'output_tokens' => (int) $provider_response->output_tokens,
			],
		];
	}

	/**
	 * Generate AI content for the editor with SSE streaming.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return null Output is sent directly via SSE.
	 */
	public function generate_editor_content_stream( $request ) {
		if ( OPENWP_DISABLE_AGENT ) {
			SSE_Response::start();
			SSE_Response::send( [ 'type' => 'error', 'message' => __( 'Agent execution is disabled via OPENWP_DISABLE_AGENT.', 'openwp' ) ] );
			SSE_Response::done();
			return null;
		}

		$settings = Settings::get();
		if ( ! empty( $settings['kill_switch'] ) ) {
			SSE_Response::start();
			SSE_Response::send( [ 'type' => 'error', 'message' => __( 'Agent execution is disabled in OpenWP settings.', 'openwp' ) ] );
			SSE_Response::done();
			return null;
		}

		$prompt = trim( (string) $request->get_param( 'prompt' ) );
		if ( '' === $prompt ) {
			SSE_Response::start();
			SSE_Response::send( [ 'type' => 'error', 'message' => __( 'Prompt is required.', 'openwp' ) ] );
			SSE_Response::done();
			return null;
		}

		$content_type = sanitize_text_field( (string) ( $request->get_param( 'content_type' ) ?: 'hero_section' ) );
		$tone         = sanitize_text_field( (string) ( $request->get_param( 'tone' ) ?: 'professional' ) );
		$provider     = sanitize_key( (string) ( $request->get_param( 'provider' ) ?: $this->resolve_default_provider( $settings ) ) );
		$model        = sanitize_text_field( (string) $request->get_param( 'model' ) );

		$palette     = [];
		$raw_palette = $request->get_param( 'palette' );
		if ( is_array( $raw_palette ) ) {
			foreach ( $raw_palette as $color ) {
				$hex = sanitize_hex_color( (string) $color );
				if ( $hex ) {
					$palette[] = $hex;
				}
			}
		}

		if ( empty( $palette ) ) {
			$palette = $this->resolve_palette( $settings );
		}

		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = $this->resolve_default_provider( $settings );
		}

		if ( '' === $model ) {
			$model = $this->resolve_default_model( $provider, $settings );
		}

		$client = Provider_Factory::create( $provider );
		if ( ! $client ) {
			SSE_Response::start();
			SSE_Response::send( [ 'type' => 'error', 'message' => __( 'Invalid or unsupported provider.', 'openwp' ) ] );
			SSE_Response::done();
			return null;
		}

		$is_full_page = 0 === strpos( $content_type, 'full_' );
		$request_timeout = $this->resolve_request_timeout(
			(int) $settings['timeout_seconds'],
			$provider,
			$model,
			$is_full_page
		);

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( $request_timeout + 30 );
		}

		$max_tokens = $this->resolve_generation_max_tokens( $is_full_page, $provider, $model );

		$request_payload = new ProviderRequest(
			[
				'system_prompt' => $this->editor_content_system_prompt(),
				'prompt'        => $this->editor_content_user_prompt( $prompt, $content_type, $tone, $palette ),
				'model'         => $model,
				'schema'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'content' ],
					'properties'           => [
						'content' => [ 'type' => 'string' ],
					],
				],
				'stream'        => true,
				'temperature'   => 0.3,
				'timeout'       => $request_timeout,
				'max_tokens'    => $max_tokens,
			]
		);

		SSE_Response::start();

		$provider_response = $client->generate_stream(
			$request_payload,
			static function ( $text_delta ) {
				SSE_Response::send(
					[
						'type'    => 'chunk',
						'content' => $text_delta,
					]
				);
			}
		);

		if ( is_wp_error( $provider_response ) ) {
			SSE_Response::send( [ 'type' => 'error', 'message' => $provider_response->get_error_message() ] );
			SSE_Response::done();
			return null;
		}

		$raw_output = trim( (string) $provider_response->output_text );
		$raw_json   = $this->extract_json_object( $raw_output );
		$decoded    = json_decode( $raw_json, true );

		$content = '';
		if ( is_array( $decoded ) ) {
			$content = $this->extract_editor_content_from_payload( $decoded );
		}

		if ( '' === $content && '' !== $raw_output ) {
			$content = $this->strip_content_wrapper( $raw_output );
		}

		if ( '' !== $content && false === strpos( $content, '<!-- wp:' ) ) {
			$content = $this->normalize_html_to_blocks( $content );
		}

		$content = $this->sanitize_block_markup( $content );

		SSE_Response::send(
			[
				'type'        => 'done',
				'content'     => $content,
				'token_usage' => [
					'input_tokens'  => (int) $provider_response->input_tokens,
					'output_tokens' => (int) $provider_response->output_tokens,
				],
			]
		);

		SSE_Response::done();
		return null;
	}

	/**
	 * Modify an existing editor block with AI via SSE streaming.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return null Output is sent directly via SSE.
	 */
	public function modify_editor_block_stream( $request ) {
		if ( OPENWP_DISABLE_AGENT ) {
			SSE_Response::start();
			SSE_Response::send( [ 'type' => 'error', 'message' => __( 'Agent execution is disabled via OPENWP_DISABLE_AGENT.', 'openwp' ) ] );
			SSE_Response::done();
			return null;
		}

		$settings = Settings::get();
		if ( ! empty( $settings['kill_switch'] ) ) {
			SSE_Response::start();
			SSE_Response::send( [ 'type' => 'error', 'message' => __( 'Agent execution is disabled in OpenWP settings.', 'openwp' ) ] );
			SSE_Response::done();
			return null;
		}

		$block_content = trim( (string) $request->get_param( 'block_content' ) );
		$instruction   = trim( (string) $request->get_param( 'instruction' ) );

		if ( '' === $block_content || '' === $instruction ) {
			SSE_Response::start();
			SSE_Response::send( [ 'type' => 'error', 'message' => __( 'Block content and instruction are required.', 'openwp' ) ] );
			SSE_Response::done();
			return null;
		}

		$provider = sanitize_key( (string) ( $request->get_param( 'provider' ) ?: $this->resolve_default_provider( $settings ) ) );
		$model    = sanitize_text_field( (string) $request->get_param( 'model' ) );

		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = $this->resolve_default_provider( $settings );
		}

		if ( '' === $model ) {
			$model = $this->resolve_default_model( $provider, $settings );
		}

		$client = Provider_Factory::create( $provider );
		if ( ! $client ) {
			SSE_Response::start();
			SSE_Response::send( [ 'type' => 'error', 'message' => __( 'Invalid or unsupported provider.', 'openwp' ) ] );
			SSE_Response::done();
			return null;
		}

		$request_timeout = $this->resolve_request_timeout(
			(int) $settings['timeout_seconds'],
			$provider,
			$model
		);
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( $request_timeout + 30 );
		}

		$palette = isset( $settings['theme_palette'] ) && is_array( $settings['theme_palette'] )
			? array_values( $settings['theme_palette'] )
			: [];

		$request_payload = new ProviderRequest(
			[
				'system_prompt' => $this->editor_modify_system_prompt(),
				'prompt'        => $this->editor_modify_user_prompt( $block_content, $instruction, $palette ),
				'model'         => $model,
				'schema'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'content' ],
					'properties'           => [
						'content' => [ 'type' => 'string' ],
					],
				],
				'stream'        => true,
				'temperature'   => 0.2,
				'timeout'       => $request_timeout,
				'max_tokens'    => 4096,
			]
		);

		SSE_Response::start();

		$provider_response = $client->generate_stream(
			$request_payload,
			static function ( $text_delta ) {
				SSE_Response::send(
					[
						'type'    => 'chunk',
						'content' => $text_delta,
					]
				);
			}
		);

		if ( is_wp_error( $provider_response ) ) {
			SSE_Response::send( [ 'type' => 'error', 'message' => $provider_response->get_error_message() ] );
			SSE_Response::done();
			return null;
		}

		$raw_output = trim( (string) $provider_response->output_text );
		$raw_json   = $this->extract_json_object( $raw_output );
		$decoded    = json_decode( $raw_json, true );

		$content = '';
		if ( is_array( $decoded ) ) {
			$content = $this->extract_editor_content_from_payload( $decoded );
		}

		if ( '' === $content && '' !== $raw_output ) {
			$content = $this->strip_content_wrapper( $raw_output );
		}

		if ( '' !== $content && false === strpos( $content, '<!-- wp:' ) ) {
			$content = $this->normalize_html_to_blocks( $content );
		}

		$content = $this->sanitize_block_markup( $content );

		SSE_Response::send(
			[
				'type'        => 'done',
				'content'     => $content,
				'token_usage' => [
					'input_tokens'  => (int) $provider_response->input_tokens,
					'output_tokens' => (int) $provider_response->output_tokens,
				],
			]
		);

		SSE_Response::done();
		return null;
	}

	/**
	 * Enhance a user prompt using AI.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function enhance_prompt( $request ) {
		if ( OPENWP_DISABLE_AGENT ) {
			return new WP_Error( 'openwp_agent_disabled', __( 'Agent execution is disabled via OPENWP_DISABLE_AGENT.', 'openwp' ), [ 'status' => 403 ] );
		}

		$settings = Settings::get();
		if ( ! empty( $settings['kill_switch'] ) ) {
			return new WP_Error( 'openwp_agent_kill_switch', __( 'Agent execution is disabled in OpenWP settings.', 'openwp' ), [ 'status' => 403 ] );
		}

		$prompt = trim( (string) $request->get_param( 'prompt' ) );
		if ( '' === $prompt ) {
			return new WP_Error( 'openwp_prompt_required', __( 'Prompt is required.', 'openwp' ), [ 'status' => 400 ] );
		}

		$content_type = sanitize_text_field( (string) ( $request->get_param( 'content_type' ) ?: 'hero_section' ) );
		$provider     = sanitize_key( (string) ( $request->get_param( 'provider' ) ?: $this->resolve_default_provider( $settings ) ) );
		$model        = sanitize_text_field( (string) $request->get_param( 'model' ) );

		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = $this->resolve_default_provider( $settings );
		}

		if ( '' === $model ) {
			$model = $this->resolve_default_model( $provider, $settings );
		}

		$client = Provider_Factory::create( $provider );
		if ( ! $client ) {
			return new WP_Error( 'openwp_provider_invalid', __( 'Invalid or unsupported provider.', 'openwp' ), [ 'status' => 400 ] );
		}

		$request_timeout = $this->resolve_request_timeout(
			(int) $settings['timeout_seconds'],
			$provider,
			$model
		);
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( $request_timeout + 30 );
		}

		$system = "You are a prompt-enhancement assistant for WordPress content generation.\n"
			. "The user will give you a rough prompt that will later be sent to an AI content generator.\n"
			. "Your job is to rewrite it into a clearer, more detailed, and more effective prompt.\n"
			. "Keep the original intent intact. Add specificity: target audience, key sections, tone hints, and details the user may have left vague.\n"
			. "The content type is: " . str_replace( '_', ' ', $content_type ) . ".\n"
			. "IMPORTANT: The enhanced prompt must describe WHAT content to create, not HOW to lay it out.\n"
			. "Do NOT include layout directives (e.g. 'Desktop: two-column grid', 'Mobile: stacked').\n"
			. "Do NOT include color codes, CSS/styling directions, or meta-instructions.\n"
			. "If images would help, mention the subject/style only, not placement or dimensions.\n"
			. "Return a JSON object with a single key \"enhanced_prompt\" containing the improved prompt text.";

		$user_prompt = $prompt . "\n\nRespond with a JSON object containing the key \"enhanced_prompt\".";

		$input_token_estimate = (int) ( mb_strlen( $prompt ) / 4 );
		$max_tokens           = max( 768, min( 2048, $input_token_estimate * 2 ) );

		$request_payload = new ProviderRequest(
			[
				'system_prompt' => $system,
				'prompt'        => $user_prompt,
				'model'         => $model,
				'schema'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'enhanced_prompt' ],
					'properties'           => [
						'enhanced_prompt' => [ 'type' => 'string' ],
					],
				],
				'stream'        => false,
				'temperature'   => 0.7,
				'timeout'       => $request_timeout,
				'max_tokens'    => $max_tokens,
			]
		);

		$provider_response = $client->generate( $request_payload );
		$raw_output        = '';
		$first_error       = null;
		$final_response    = null;

		if ( is_wp_error( $provider_response ) ) {
			$first_error = $provider_response;
		} else {
			$final_response = $provider_response;
			$raw_output = trim( (string) $provider_response->output_text );
		}

		// Streaming fallback – many providers return an empty body for non-streamed
		// JSON-mode requests. Retry with streaming to reliably capture the output.
		if ( '' === $raw_output ) {
			$stream_request = new ProviderRequest(
				[
					'system_prompt' => $system,
					'prompt'        => $user_prompt,
					'model'         => $model,
					'schema'        => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'enhanced_prompt' ],
						'properties'           => [
							'enhanced_prompt' => [ 'type' => 'string' ],
						],
					],
					'stream'        => true,
					'temperature'   => 0.7,
					'timeout'       => $request_timeout,
					'max_tokens'    => $max_tokens,
				]
			);

			$stream_response = $client->generate_stream(
				$stream_request,
				static function ( $delta ) {
					// No-op: text is aggregated from final provider response.
				}
			);

			if ( is_wp_error( $stream_response ) ) {
				if ( is_wp_error( $first_error ) ) {
					return $first_error;
				}
				return $stream_response;
			}

			$final_response = $stream_response;
			$raw_output = trim( (string) $stream_response->output_text );
		}

		if ( '' === $raw_output ) {
			return new WP_Error(
				'openwp_enhance_failed',
				__( 'The AI model returned an empty response. Try a different model or try again.', 'openwp' ),
				[ 'status' => 502 ]
			);
		}

		$enhanced = '';

		// Strategy 1: Parse as JSON and look for enhanced_prompt key.
		$clean_json = $this->extract_json_object( $raw_output );
		$decoded    = json_decode( $clean_json, true );

		if ( is_array( $decoded ) && isset( $decoded['enhanced_prompt'] ) && '' !== trim( (string) $decoded['enhanced_prompt'] ) ) {
			$enhanced = trim( (string) $decoded['enhanced_prompt'] );
		}

		// Strategy 2: Try other common key names the model might use.
		if ( '' === $enhanced && is_array( $decoded ) ) {
			foreach ( [ 'prompt', 'enhanced', 'result', 'output', 'text', 'content' ] as $key ) {
				if ( isset( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) && '' !== trim( $decoded[ $key ] ) ) {
					$enhanced = trim( $decoded[ $key ] );
					break;
				}
			}
		}

		// Strategy 3: If JSON had a single string value, use it regardless of key name.
		if ( '' === $enhanced && is_array( $decoded ) ) {
			$string_values = array_filter( $decoded, 'is_string' );
			if ( 1 === count( $string_values ) ) {
				$enhanced = trim( (string) reset( $string_values ) );
			}
		}

		// Strategy 4: Raw output is plain text (model ignored JSON format).
		// Strip any partial JSON wrapper and use the text directly.
		if ( '' === $enhanced ) {
			$text = $raw_output;
			// Remove markdown fences.
			$text = preg_replace( '/^```(?:json)?\s*\n?/i', '', $text );
			$text = preg_replace( '/\n?```\s*$/', '', $text );
			// Remove JSON wrapper like {"enhanced_prompt":"..."}
			$text = preg_replace( '/^\s*\{\s*"[^"]*"\s*:\s*"/', '', $text );
			$text = preg_replace( '/"\s*\}\s*$/', '', $text );
			$text = trim( stripcslashes( $text ) );

			// Only use if it looks like actual prompt text (not a JSON fragment or error).
			if ( mb_strlen( $text ) > 20 && '{' !== substr( $text, 0, 1 ) ) {
				$enhanced = $text;
			}
		}

		if ( '' === $enhanced ) {
			return new WP_Error(
				'openwp_enhance_failed',
				__( 'Could not parse the enhanced prompt. Try a different model or try again.', 'openwp' ),
				[ 'status' => 502 ]
			);
		}

		return new \WP_REST_Response(
			[
				'enhanced_prompt' => $enhanced,
				'token_usage'     => [
					'input_tokens'  => is_object( $final_response ) ? (int) ( $final_response->input_tokens ?? 0 ) : 0,
					'output_tokens' => is_object( $final_response ) ? (int) ( $final_response->output_tokens ?? 0 ) : 0,
				],
			],
			200
		);
	}

	/**
	 * List actions endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function list_actions( $request ) {
		$items    = [];
		$actions  = Action_Registry::all();
		$policies = Settings::get_action_policy();

		foreach ( $actions as $key => $action ) {
			$policy = isset( $policies[ $key ] ) && is_array( $policies[ $key ] ) ? $policies[ $key ] : [];
			$items[] = [
				'key'             => $key,
				'risk'            => $action['risk'] ?? 'low',
				'capability'      => $action['capability'] ?? '',
				'mutates'         => ! empty( $action['mutates'] ),
				'requires_backup' => ! empty( $action['requires_backup'] ),
				'schema'          => $action['schema'] ?? [],
				'policy'          => [
					'enabled'          => isset( $policy['enabled'] ) ? (bool) $policy['enabled'] : true,
					'require_approval' => isset( $policy['require_approval'] ) ? (bool) $policy['require_approval'] : null,
				],
			];
		}

		return [ 'items' => $items ];
	}

	/**
	 * List approval queue.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function list_approvals( $request ) {
		$service = new Approval_Service();
		return $service->list(
			[
				'page'     => absint( $request->get_param( 'page' ) ?: 1 ),
				'per_page' => absint( $request->get_param( 'per_page' ) ?: 20 ),
				'status'   => sanitize_key( (string) $request->get_param( 'status' ) ),
			]
		);
	}

	/**
	 * Approve queued action.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function approve_action( $request ) {
		$service = new Approval_Service();
		return $service->approve(
			absint( $request->get_param( 'id' ) ),
			get_current_user_id(),
			(string) $request->get_param( 'typed_confirmation' ),
			sanitize_textarea_field( (string) $request->get_param( 'note' ) )
		);
	}

	/**
	 * Reject queued action.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function reject_action( $request ) {
		$service = new Approval_Service();
		return $service->reject(
			absint( $request->get_param( 'id' ) ),
			get_current_user_id(),
			sanitize_textarea_field( (string) $request->get_param( 'note' ) )
		);
	}

	/**
	 * List logs endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function list_logs( $request ) {
		$repository = new Log_Repository();
		return $repository->list(
			[
				'page'      => absint( $request->get_param( 'page' ) ?: 1 ),
				'per_page'  => absint( $request->get_param( 'per_page' ) ?: 20 ),
				'status'    => sanitize_key( (string) $request->get_param( 'status' ) ),
				'action'    => sanitize_key( (string) $request->get_param( 'action' ) ),
				'risk'      => sanitize_key( (string) $request->get_param( 'risk' ) ),
				'search'    => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			]
		);
	}

	/**
	 * Execute rollback endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function rollback_log( $request ) {
		$service = new Rollback_Service();
		return $service->rollback( absint( $request->get_param( 'id' ) ) );
	}

	/**
	 * Get settings endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function get_settings( $request ) {
		return [
			'settings' => Settings::get(),
			'provider' => Settings::provider_status(),
			'policy'   => Settings::get_action_policy(),
			'mcp'      => Settings::get_mcp_settings(),
		];
	}

	/**
	 * Update settings endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_settings( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = [];
		}

		if ( isset( $body['provider_keys'] ) && is_array( $body['provider_keys'] ) ) {
			$key_manager = new Provider_Key_Manager();
			$save_result = $key_manager->save_keys(
				[
					'openai'    => isset( $body['provider_keys']['openai'] ) ? (string) $body['provider_keys']['openai'] : '',
					'anthropic' => isset( $body['provider_keys']['anthropic'] ) ? (string) $body['provider_keys']['anthropic'] : '',
					'glm'       => isset( $body['provider_keys']['glm'] ) ? (string) $body['provider_keys']['glm'] : '',
					'openrouter' => isset( $body['provider_keys']['openrouter'] ) ? (string) $body['provider_keys']['openrouter'] : '',
				]
			);

			if ( is_wp_error( $save_result ) ) {
				return $save_result;
			}

			$provider_status = Settings::provider_status();
			$has_any_key     = ! empty( $provider_status['has_openai_key'] ) || ! empty( $provider_status['has_anthropic_key'] ) || ! empty( $provider_status['has_glm_key'] ) || ! empty( $provider_status['has_openrouter_key'] );
			Onboarding_State::update_progress(
				[
					'provider_saved' => $has_any_key,
				]
			);
		}

		if ( isset( $body['action_policy'] ) && is_array( $body['action_policy'] ) ) {
			Settings::update_action_policy( $body['action_policy'] );
		}

		$mcp_payload = [];
		if ( isset( $body['mcp'] ) && is_array( $body['mcp'] ) ) {
			$mcp_payload = $body['mcp'];
		}
		if ( array_key_exists( 'openwp_mcp_enabled', $body ) ) {
			$mcp_payload['enabled'] = $body['openwp_mcp_enabled'];
		}
		if ( array_key_exists( 'openwp_mcp_debug_mode', $body ) ) {
			$mcp_payload['debug_mode'] = $body['openwp_mcp_debug_mode'];
		}
		if ( array_key_exists( 'openwp_mcp_modules', $body ) ) {
			$mcp_payload['modules'] = $body['openwp_mcp_modules'];
		}
		if ( array_key_exists( 'openwp_mcp_bearer_token', $body ) ) {
			$mcp_payload['bearer_token'] = $body['openwp_mcp_bearer_token'];
		}
		if ( ! empty( $mcp_payload ) ) {
			Settings::update_mcp_settings( $mcp_payload );
		}

		$settings = Settings::update( isset( $body['settings'] ) && is_array( $body['settings'] ) ? $body['settings'] : $body );

		return [
			'success'  => true,
			'settings' => $settings,
			'provider' => Settings::provider_status(),
			'policy'   => Settings::get_action_policy(),
			'mcp'      => Settings::get_mcp_settings(),
		];
	}

	/**
	 * Get onboarding state endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function get_onboarding( $request ) {
		$progress = Onboarding_State::get_progress();
		$derived  = $this->derive_onboarding_state();

		if ( $derived['has_any_provider_key'] && empty( $progress['provider_saved'] ) ) {
			$progress = Onboarding_State::update_progress(
				[
					'provider_saved' => true,
				]
			);
		}

		return [
			'completed' => Onboarding_State::is_completed(),
			'progress'  => $progress,
			'derived'   => $derived,
		];
	}

	/**
	 * Update onboarding progress endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function update_onboarding_progress( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = [];
		}

		$patch = isset( $body['progress'] ) && is_array( $body['progress'] ) ? $body['progress'] : $body;

		$allowed = array_flip( Onboarding_State::progress_keys() );
		$clean   = [];
		foreach ( $patch as $key => $value ) {
			if ( isset( $allowed[ $key ] ) ) {
				$clean[ $key ] = rest_sanitize_boolean( $value );
			}
		}

		$progress = Onboarding_State::update_progress( $clean );

		return [
			'success'  => true,
			'progress' => $progress,
		];
	}

	/**
	 * Complete onboarding when strict criteria pass.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function complete_onboarding( $request ) {
		$progress = Onboarding_State::get_progress();
		$derived  = $this->derive_onboarding_state();

		if ( $derived['has_any_provider_key'] ) {
			$progress['provider_saved'] = true;
		}

		if ( ! Onboarding_State::can_complete_strict( $progress ) ) {
			return new WP_Error(
				'openwp_onboarding_incomplete',
				__( 'Onboarding is incomplete. Finish all required steps before completion.', 'openwp' ),
				[
					'status'  => 400,
					'missing' => Onboarding_State::missing_required_keys( $progress ),
				]
			);
		}

		Onboarding_State::set_completed( true );
		Onboarding_State::set_redirect_flag( false );
		Onboarding_State::update_progress( $progress );

		return [
			'success'   => true,
			'completed' => true,
			'progress'  => Onboarding_State::get_progress(),
			'derived'   => $derived,
		];
	}

	/**
	 * List memory endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function get_memory( $request ) {
		$service = new Memory_Service();
		$list    = $service->list(
			[
				'page'     => absint( $request->get_param( 'page' ) ?: 1 ),
				'per_page' => absint( $request->get_param( 'per_page' ) ?: 100 ),
				'type'     => sanitize_key( (string) $request->get_param( 'type' ) ),
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			]
		);

		return [
			'enabled'  => $service->is_enabled(),
			'total'    => absint( $list['total'] ?? 0 ),
			'page'     => absint( $list['page'] ?? 1 ),
			'per_page' => absint( $list['per_page'] ?? 100 ),
			'items'    => $list['items'] ?? [],
		];
	}

	/**
	 * Save memory endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function save_memory( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = [];
		}

		$type      = $body['type'] ?? '';
		$key       = $body['key'] ?? '';
		$value_raw = array_key_exists( 'value', $body ) ? $body['value'] : ( $body['text'] ?? '' );
		$text      = is_array( $value_raw ) ? (string) ( $value_raw['text'] ?? '' ) : (string) $value_raw;

		$tags = [];
		if ( isset( $body['tags'] ) ) {
			$tags = $body['tags'];
		} elseif ( is_array( $value_raw ) && isset( $value_raw['tags'] ) ) {
			$tags = $value_raw['tags'];
		}

		if ( is_string( $tags ) ) {
			$tags = array_filter( array_map( 'trim', explode( ',', $tags ) ) );
		}

		$service = new Memory_Service();
		$result  = $service->remember(
			$type,
			$key,
			$text,
			$tags,
			[
				'user_id' => get_current_user_id(),
				'source'  => 'rest_api',
				'prompt'  => 'Memory saved from OpenWP admin.',
				'audit'   => true,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'success' => true,
			'created' => ! empty( $result['created'] ),
			'item'    => $result['item'] ?? [],
		];
	}

	/**
	 * Delete memory endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function delete_memory( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = [];
		}

		$type = isset( $body['type'] ) ? $body['type'] : $request->get_param( 'type' );
		$key  = isset( $body['key'] ) ? $body['key'] : $request->get_param( 'key' );

		$service = new Memory_Service();
		$result  = $service->forget(
			$type,
			$key,
			[
				'user_id' => get_current_user_id(),
				'source'  => 'rest_api',
				'prompt'  => 'Memory deleted from OpenWP admin.',
				'audit'   => true,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'success' => true,
			'deleted' => ! empty( $result['deleted'] ),
			'type'    => $result['type'] ?? sanitize_key( (string) $type ),
			'key'     => $result['key'] ?? sanitize_title( (string) $key ),
		];
	}

	/**
	 * List backups endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>
	 */
	public function list_backups( $request ) {
		$service = new Backup_Service();
		return $service->list(
			absint( $request->get_param( 'page' ) ?: 1 ),
			absint( $request->get_param( 'per_page' ) ?: 20 )
		);
	}

	/**
	 * Restore backup endpoint.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function restore_backup( $request ) {
		$typed = strtoupper( trim( (string) $request->get_param( 'typed_confirmation' ) ) );
		if ( 'APPROVE' !== $typed ) {
			return new WP_Error( 'openwp_restore_typed_confirmation', __( 'Backup restore requires typed_confirmation=APPROVE.', 'openwp' ) );
		}

		$service = new Backup_Service();
		return $service->restore( absint( $request->get_param( 'id' ) ) );
	}

	/**
	 * Resolve default model by provider.
	 *
	 * @param string              $provider Provider slug.
	 * @param array<string,mixed> $settings OpenWP settings.
	 * @return string
	 */
	private function resolve_default_model( $provider, $settings ) {
		if ( 'anthropic' === $provider ) {
			$anthropic = isset( $settings['default_model_anthropic'] ) ? trim( (string) $settings['default_model_anthropic'] ) : '';
			return '' !== $anthropic ? $anthropic : 'claude-3-5-sonnet-latest';
		}

		if ( 'glm' === $provider ) {
			$glm = isset( $settings['default_model_glm'] ) ? trim( (string) $settings['default_model_glm'] ) : '';
			return '' !== $glm ? $glm : 'glm-5';
		}

		if ( 'openrouter' === $provider ) {
			$openrouter = isset( $settings['default_model_openrouter'] ) ? trim( (string) $settings['default_model_openrouter'] ) : '';
			return '' !== $openrouter ? $openrouter : 'anthropic/claude-sonnet-4-5';
		}

		$openai = isset( $settings['default_model_openai'] ) ? trim( (string) $settings['default_model_openai'] ) : '';
		return '' !== $openai ? $openai : 'gpt-5.2';
	}

	/**
	 * Resolve default provider with strict fallback.
	 *
	 * @param array<string,mixed> $settings OpenWP settings.
	 * @return string
	 */
	private function resolve_default_provider( $settings ) {
		$provider = isset( $settings['default_provider'] ) ? sanitize_key( (string) $settings['default_provider'] ) : 'openrouter';
		return in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ? $provider : 'openrouter';
	}

	/**
	 * Resolve request timeout with model-aware minimums.
	 *
	 * Some models (for example GPT-5 family and reasoning/thinking models) can
	 * take longer to stream complete responses, especially with large prompts.
	 *
	 * @param int    $base_timeout Base timeout from settings.
	 * @param string $provider     Active provider.
	 * @param string $model        Active model identifier.
	 * @param bool   $is_full_page Whether the request is full-page generation.
	 * @return int
	 */
	private function resolve_request_timeout( $base_timeout, $provider, $model, $is_full_page = false ) {
		$timeout = (int) $base_timeout;

		if ( $is_full_page ) {
			$timeout = max( 180, $timeout );
		}

		if ( $this->is_high_latency_model( $provider, $model ) ) {
			$timeout = max( 120, $timeout );
		}

		return $timeout;
	}

	/**
	 * Determine whether the selected model typically needs more latency budget.
	 *
	 * @param string $provider Provider slug.
	 * @param string $model    Model identifier.
	 * @return bool
	 */
	private function is_high_latency_model( $provider, $model ) {
		$model = strtolower( trim( (string) $model ) );
		if ( '' === $model ) {
			return false;
		}

		if ( preg_match( '/(?:^|\/)gpt-5(?:[.-]|$)/', $model ) ) {
			return true;
		}

		if ( preg_match( '/(?:^|\/)o[13](?:[.-]|$)/', $model ) ) {
			return true;
		}

		if ( false !== strpos( $model, 'thinking' ) || false !== strpos( $model, 'reasoning' ) ) {
			return true;
		}

		// Free-tier OpenRouter models are often slower to complete.
		if ( 'openrouter' === $provider && false !== strpos( $model, ':free' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve generation max_tokens with model-aware guardrails.
	 *
	 * High-latency models can stream for a long time when given very high output
	 * budgets. Keep full-page outputs larger, but cap sections/modifications to
	 * avoid unnecessary timeout risk.
	 *
	 * @param bool   $is_full_page Whether request is full-page generation.
	 * @param string $provider     Provider slug.
	 * @param string $model        Model identifier.
	 * @return int
	 */
	private function resolve_generation_max_tokens( $is_full_page, $provider, $model ) {
		$max_tokens = $is_full_page ? 8192 : 4096;

		if ( $this->is_high_latency_model( $provider, $model ) ) {
			return $is_full_page ? 6144 : 3072;
		}

		return $max_tokens;
	}

	/**
	 * Resolve the active brand palette from settings, falling back to defaults.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 * @return string[]
	 */
	private function resolve_palette( $settings ) {
		if ( isset( $settings['theme_palette'] ) && is_array( $settings['theme_palette'] ) && ! empty( $settings['theme_palette'] ) ) {
			return array_values( $settings['theme_palette'] );
		}

		$defaults = Settings::defaults();
		return isset( $defaults['theme_palette'] ) && is_array( $defaults['theme_palette'] )
			? $defaults['theme_palette']
			: [];
	}

	/**
	 * Build derived onboarding state from current settings.
	 *
	 * @return array<string,mixed>
	 */
	private function derive_onboarding_state() {
		$settings = Settings::get();
		$provider = Settings::provider_status();
		$default  = $this->resolve_default_provider( $settings );
		$has_key  = ! empty( $provider['has_openai_key'] ) || ! empty( $provider['has_anthropic_key'] ) || ! empty( $provider['has_glm_key'] ) || ! empty( $provider['has_openrouter_key'] );

		return [
			'has_any_provider_key' => $has_key,
			'default_provider'     => $default,
			'default_model'        => $this->resolve_default_model( $default, $settings ),
		];
	}

	/**
	 * System prompt for block content generation.
	 *
	 * @return string
	 */
	private function editor_content_system_prompt() {
		return "You are an expert web copywriter and WordPress content architect.\n"
			. "Your goal: produce stunning, conversion-optimized website content that reads like a top-tier copywriter wrote it and looks like a professional designer laid it out.\n\n"

			. "CONTENT QUALITY STANDARDS:\n"
			. "- Write compelling, specific copy. NEVER use generic filler: 'Welcome to our website', 'We are passionate about...', 'In today\\'s fast-paced world...', 'In today\\'s digital age...'\n"
			. "- Lead every section with a benefit-driven headline that makes readers want to keep reading\n"
			. "- Use concrete numbers, outcomes, and specifics instead of vague claims ('Trusted by 2,500+ businesses' not 'Trusted by many')\n"
			. "- Vary sentence rhythm: mix short punchy lines with longer descriptive ones for natural flow\n"
			. "- Write scannable content: clear headings hierarchy, bullet points for lists of 3+, bold key phrases\n"
			. "- Every section must earn its place — remove anything that doesn't add value or move the reader forward\n"
			. "- Include at least one styled wp:button CTA on every page — never rely on text links alone\n"
			. "- Create visual rhythm by alternating between full-width sections and multi-column layouts\n\n"

			. "OUTPUT FORMAT:\n"
			. "Return a single JSON object: {\"content\": \"<gutenberg block markup>\"}\n"
			. "The content MUST use WordPress Gutenberg block comments. Do NOT use markdown. Do NOT wrap in code fences.\n\n"

			. "BLOCK REFERENCE (basic structure):\n"
			. "<!-- wp:heading {\"level\":2} --><h2 class=\"wp-block-heading\">Title</h2><!-- /wp:heading -->\n"
			. "<!-- wp:heading {\"level\":3} --><h3 class=\"wp-block-heading\">Subtitle</h3><!-- /wp:heading -->\n"
			. "<!-- wp:paragraph --><p>Text here.</p><!-- /wp:paragraph -->\n"
			. "<!-- wp:paragraph --><p><strong>Bold text</strong> for emphasis.</p><!-- /wp:paragraph -->\n"
			. "<!-- wp:list --><ul class=\"wp-block-list\"><!-- wp:list-item --><li>Item</li><!-- /wp:list-item --></ul><!-- /wp:list -->\n"
			. "<!-- wp:list {\"ordered\":true} --><ol class=\"wp-block-list\"><!-- wp:list-item --><li>Item</li><!-- /wp:list-item --></ol><!-- /wp:list -->\n"
			. "<!-- wp:columns --><div class=\"wp-block-columns\"><!-- wp:column --><div class=\"wp-block-column\"><!-- blocks --></div><!-- /wp:column --><!-- wp:column --><div class=\"wp-block-column\"><!-- blocks --></div><!-- /wp:column --></div><!-- /wp:columns -->\n"
			. "<!-- wp:cover {\"url\":\"IMG_URL\",\"dimRatio\":50} --><div class=\"wp-block-cover\"><span aria-hidden=\"true\" class=\"wp-block-cover__background has-background-dim-50 has-background-dim\"></span><img class=\"wp-block-cover__image-background\" src=\"IMG_URL\" alt=\"desc\" data-object-fit=\"cover\"/><div class=\"wp-block-cover__inner-container\"><!-- inner blocks --></div></div><!-- /wp:cover -->\n"
			. "<!-- wp:image {\"sizeSlug\":\"large\"} --><figure class=\"wp-block-image size-large\"><img src=\"IMG_URL\" alt=\"desc\"/></figure><!-- /wp:image -->\n"
			. "<!-- wp:separator --><hr class=\"wp-block-separator has-alpha-channel-opacity\"/><!-- /wp:separator -->\n"
			. "<!-- wp:spacer {\"height\":\"50px\"} --><div style=\"height:50px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div><!-- /wp:spacer -->\n"
			. "<!-- wp:quote --><blockquote class=\"wp-block-quote\"><!-- wp:paragraph --><p>Quote text.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->\n\n"

			. "STYLED BLOCK EXAMPLES (FOLLOW THIS PATTERN — apply brand colors via inline style attributes):\n"
			. "Styled heading: <!-- wp:heading {\"level\":2} --><h2 class=\"wp-block-heading\" style=\"color:#4F46E5\">Benefit-Driven Headline</h2><!-- /wp:heading -->\n"
			. "Styled button: <!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} --><div class=\"wp-block-buttons\"><!-- wp:button --><div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" style=\"background-color:#7C3AED;color:#ffffff\">Get Started Free</a></div><!-- /wp:button --></div><!-- /wp:buttons -->\n"
			. "Styled group section: <!-- wp:group {\"style\":{\"spacing\":{\"padding\":{\"top\":\"60px\",\"bottom\":\"60px\"}}},\"layout\":{\"type\":\"constrained\"}} --><div class=\"wp-block-group\" style=\"background-color:#EEF2FF;padding-top:60px;padding-bottom:60px\"><!-- inner blocks with styled headings and text --></div><!-- /wp:group -->\n"
			. "Dark section: <!-- wp:group {\"style\":{\"spacing\":{\"padding\":{\"top\":\"60px\",\"bottom\":\"60px\"}}},\"layout\":{\"type\":\"constrained\"}} --><div class=\"wp-block-group\" style=\"background-color:#1E1B4B;padding-top:60px;padding-bottom:60px\"><!-- wp:heading {\"level\":2} --><h2 class=\"wp-block-heading\" style=\"color:#ffffff\">White Text on Dark</h2><!-- /wp:heading --><!-- wp:paragraph --><p style=\"color:#C7D2FE\">Light text on dark background.</p><!-- /wp:paragraph --></div><!-- /wp:group -->\n"
			. "Accent border: <!-- wp:group --><div class=\"wp-block-group\" style=\"border-left:4px solid #F59E0B;padding-left:20px\"><!-- content --></div><!-- /wp:group -->\n\n"

			. "COLOR RULES (MANDATORY):\n"
			. "You MUST apply the brand color palette from the user prompt via inline style attributes on EVERY visual element.\n"
			. "- EVERY h2/h3 heading tag MUST have style=\"color:PRIMARY_COLOR\"\n"
			. "- EVERY wp-block-button__link anchor MUST have style=\"background-color:SECONDARY_COLOR;color:#ffffff\"\n"
			. "- Section wp:group divs MUST alternate backgrounds using Light, Dark, or white with style=\"background-color:COLOR\"\n"
			. "- Use Accent color for highlights, borders, badges, and decorative elements\n"
			. "- Text inside dark backgrounds MUST use style=\"color:#ffffff\" or style=\"color:LIGHT_COLOR\"\n"
			. "- NEVER output a heading, button, or section background without an inline style attribute using a palette color\n"
			. "Generating unstyled blocks is a critical failure — every element must be visually branded.\n\n"

			. "LAYOUT TECHNIQUES:\n"
			. "- wp:cover with background image for hero sections and dramatic visual breaks\n"
			. "- wp:columns (2 or 3 col) for features, benefits, team members, pricing tiers, stats\n"
			. "- wp:group with background-color + padding for colored section bands that separate content areas\n"
			. "- wp:buttons for EVERY call-to-action\n"
			. "- wp:spacer (40-60px) between major sections for breathing room\n"
			. "- Alternate section backgrounds (white, light palette color, dark palette color) for visual separation\n\n"

			. "IMAGES:\n"
			. "Use real Unsplash URLs: https://images.unsplash.com/photo-{ID}?auto=format&fit=crop&w=1200&q=80\n"
			. "Pick photo IDs relevant to the content topic. Write descriptive alt text for every image.\n"
			. "Place images in: hero wp:cover backgrounds, feature wp:image blocks, section illustrations.\n"
			. "If you cannot recall a specific Unsplash photo ID for the topic, use https://placehold.co/1200x800/EEE/31343C?font=montserrat&text=Featured+Image as a placeholder.\n\n"

			. "MISTAKES TO AVOID:\n"
			. "- Flat layouts using only paragraphs and headings — use columns, buttons, groups, covers, spacers\n"
			. "- Generic AI copy — be specific, original, and benefit-driven\n"
			. "- Missing CTA buttons — every page/section needs at least one styled wp:button\n"
			. "- Walls of text — break up with headings, lists, columns, images, spacers\n"
			. "- Production notes, layout guidance text, color references as text, placeholder instructions, meta-commentary\n"
			. "- Everything in the output goes directly onto a live website as-is\n\n"

			. "Output ONLY the JSON object, nothing else.";
	}

	/**
	 * User prompt for block content generation.
	 *
	 * @param string        $prompt       User prompt text.
	 * @param string        $content_type Content type slug.
	 * @param string        $tone         Tone style.
	 * @param array<string> $palette      Optional brand hex colors.
	 * @return string
	 */
	private function editor_content_user_prompt( $prompt, $content_type, $tone, $palette = [] ) {
		$is_full_page = 0 === strpos( $content_type, 'full_' );

		$type_guidance = $this->get_content_type_guidance( $content_type );

		if ( $is_full_page ) {
			$page_label = str_replace( [ 'full_', '_' ], [ '', ' ' ], $content_type );
			$output     = "Generate a complete, professionally designed " . trim( $page_label ) . " in a " . $tone . " tone.\n\n"
				. $type_guidance . "\n"
				. "USER REQUEST: " . $prompt . "\n\n";
		} else {
			$output = "Generate a " . str_replace( '_', ' ', $content_type ) . " in a " . $tone . " tone.\n\n"
				. $type_guidance . "\n"
				. "USER REQUEST: " . $prompt . "\n\n";
		}

		if ( ! empty( $palette ) ) {
			$primary   = $palette[0];
			$secondary = $palette[1] ?? $palette[0];
			$accent    = $palette[2] ?? $palette[1] ?? $palette[0];

			$output .= "BRAND COLOR PALETTE — YOU MUST APPLY THESE COLORS ON EVERY STYLED ELEMENT:\n"
				. "- Primary (" . $primary . "): EVERY h2/h3 heading MUST have style=\"color:" . $primary . "\"\n"
				. "- Secondary (" . $secondary . "): EVERY button anchor MUST have style=\"background-color:" . $secondary . ";color:#ffffff\"\n"
				. "- Accent (" . $accent . "): highlights, badges, accent borders (e.g. style=\"border-left:4px solid " . $accent . "\")\n";
			if ( isset( $palette[3] ) ) {
				$output .= "- Dark (" . $palette[3] . "): dark section backgrounds (style=\"background-color:" . $palette[3] . "\") with white text (style=\"color:#ffffff\")\n";
			}
			if ( isset( $palette[4] ) ) {
				$output .= "- Light (" . $palette[4] . "): alternating section backgrounds (style=\"background-color:" . $palette[4] . "\")\n";
			}
			if ( isset( $palette[5] ) ) {
				$output .= "- Muted (" . $palette[5] . "): borders, dividers, secondary text\n";
			}
			$output .= "CRITICAL: Do NOT output any heading, button, or section group without inline style attributes using these exact hex colors. Unstyled blocks are not acceptable.\n\n";
		}

		$output .= "Include relevant Unsplash images for hero backgrounds, feature visuals, and section illustrations.\n"
			. "Output ONLY finished, publishable Gutenberg block markup. The content goes directly onto a live website.";

		return $output;
	}

	/**
	 * Get structure guidance for a specific content type.
	 *
	 * @param string $content_type Content type slug.
	 * @return string
	 */
	private function get_content_type_guidance( $content_type ) {
		$guides = [
			'full_landing_page'  => "REQUIRED SECTIONS (create all of these):\n"
				. "1. HERO: wp:cover with background image, bold headline (H1), compelling subtext, and a prominent wp:button CTA\n"
				. "2. SOCIAL PROOF: Trust indicators — stats in a 3-4 column layout (e.g. '500+ Clients', '99% Satisfaction', '10 Years')\n"
				. "3. FEATURES/BENEFITS: 3-column wp:columns layout. Each column: icon/emoji, benefit headline, 2-3 sentence description focused on outcomes\n"
				. "4. DETAIL SECTION: Deeper explanation with wp:image alongside text — show how the product/service works or what makes it different\n"
				. "5. TESTIMONIALS: 2-3 customer quotes in wp:columns, each with name and role/company\n"
				. "6. FINAL CTA: Colored wp:group background section with urgency-driven headline and a large wp:button\n"
				. "Aim for 800-1200 words of substantive, benefit-driven copy. Use wp:spacer between sections.\n",

			'full_blog_post'     => "REQUIRED STRUCTURE:\n"
				. "1. OPENING: Hook the reader immediately — use a surprising stat, provocative question, or vivid scenario. No generic intros.\n"
				. "2. BODY: 3-5 well-developed H2 sections. Each section should have a clear point, supporting evidence, and practical takeaway.\n"
				. "3. Use H3 subheadings within long sections. Break up text with wp:list for actionable steps and wp:quote for expert insights.\n"
				. "4. Include at least one wp:image between sections with a relevant, topic-specific photo.\n"
				. "5. CONCLUSION: Summarize the 2-3 key takeaways, then end with a clear call-to-action (wp:button).\n"
				. "Aim for 1000-1500 words of well-structured, engaging content with proper heading hierarchy.\n",

			'full_about_page'    => "REQUIRED SECTIONS:\n"
				. "1. HERO: wp:cover with a bold mission statement or company tagline, not just 'About Us'\n"
				. "2. STORY: 2-3 paragraphs telling the origin story — what problem did you set out to solve and why?\n"
				. "3. VALUES/APPROACH: 3-column layout showing core values or differentiators with specific descriptions\n"
				. "4. TEAM or MILESTONES: Either team member cards in wp:columns (name, role, brief bio) or a timeline of key achievements\n"
				. "5. CTA: 'Work with us' or 'Get in touch' section with wp:button\n"
				. "Make it personal and authentic — avoid corporate jargon. Show personality.\n",

			'full_services_page' => "REQUIRED SECTIONS:\n"
				. "1. HERO: wp:cover with a clear value proposition headline — what transformation do you deliver?\n"
				. "2. SERVICES OVERVIEW: Brief intro paragraph, then each service in its own wp:group with H3, description, and key deliverables\n"
				. "3. PROCESS: 3-4 step numbered process in wp:columns showing how you work (e.g. Discover → Design → Deliver → Support)\n"
				. "4. WHY CHOOSE US: 3-column benefits with specific proof points, not vague claims\n"
				. "5. CTA: Compelling closing section with wp:button — make the next step crystal clear\n"
				. "Focus on outcomes and results, not just listing what you do.\n",

			'full_contact_page'  => "REQUIRED SECTIONS:\n"
				. "1. HERO: Welcoming headline (not just 'Contact Us') — e.g. 'Let\\'s Start a Conversation' with brief subtext\n"
				. "2. CONTACT METHODS: 2-3 column layout with email, phone, and address/location. Include actual-looking details.\n"
				. "3. HOURS/AVAILABILITY: When you\\'re available, expected response times\n"
				. "4. FAQ: 3-4 common questions in a clean Q&A format to reduce unnecessary contact\n"
				. "Keep it concise and action-oriented. Make it easy to reach out.\n",

			'hero_section'       => "Create a visually striking hero section:\n"
				. "- wp:cover with a relevant background image and overlay\n"
				. "- Bold, benefit-driven H1 headline (max 10 words) that immediately communicates value\n"
				. "- Supporting subtext paragraph (1-2 sentences) that expands on the headline\n"
				. "- One or two wp:button CTAs (primary action + optional secondary)\n"
				. "- Center-aligned content inside the cover block\n",

			'feature_section'    => "Create a feature/benefits section:\n"
				. "- Section H2 headline that frames the features as benefits\n"
				. "- Optional intro paragraph (1 sentence)\n"
				. "- 3-column wp:columns layout, each column containing: emoji or symbol, H3 feature name, 2-3 sentence description focused on the benefit to the user\n"
				. "- Use specific, measurable outcomes where possible\n",

			'faq_section'        => "Create a FAQ section:\n"
				. "- Section H2 headline (e.g. 'Frequently Asked Questions' or 'Common Questions')\n"
				. "- 5-8 Q&A pairs, each with H3 question and paragraph answer\n"
				. "- Questions should address real concerns and objections — not softball questions\n"
				. "- Answers should be concise but thorough (2-4 sentences each)\n"
				. "- End with a paragraph: 'Still have questions? [CTA]'\n",

			'cta_section'        => "Create a compelling call-to-action section:\n"
				. "- wp:group with a bold background color for visual impact\n"
				. "- Urgency-driven H2 headline (e.g. 'Ready to Get Started?' or 'Don\\'t Miss Out')\n"
				. "- One supporting paragraph that reinforces the key benefit or creates urgency\n"
				. "- Prominent wp:button with action-oriented text (not just 'Submit' — use 'Get Your Free Quote', 'Start Today', etc.)\n",

			'testimonials_section' => "Create a testimonials section:\n"
				. "- Section H2 headline (e.g. 'What Our Clients Say' or 'Trusted by Teams Like Yours')\n"
				. "- 3 testimonials in wp:columns, each containing: wp:quote with the testimonial text, paragraph with the person's name, role, and company\n"
				. "- Make testimonials specific and result-oriented ('Increased our revenue by 40%') not generic ('Great service!')\n",

			'pricing_section'    => "Create a pricing section:\n"
				. "- Section H2 headline\n"
				. "- 3-column wp:columns for pricing tiers (e.g. Starter, Professional, Enterprise)\n"
				. "- Each column: H3 tier name, price with period, wp:list of included features, wp:button CTA\n"
				. "- Highlight the recommended tier with a different background color on wp:group\n"
				. "- Use specific feature names, not vague descriptions\n",

			'about_section'      => "Create an about/team section:\n"
				. "- Section H2 headline\n"
				. "- Brief intro paragraph about the team or company mission\n"
				. "- 3-4 team members in wp:columns: wp:image for photo, H3 name, paragraph with role and brief bio\n"
				. "- Make bios personable — include a fun fact or specialty\n",

			'newsletter_section' => "Create a newsletter signup section:\n"
				. "- wp:group with a contrasting background color\n"
				. "- Compelling H2 headline — sell the value of subscribing, not just 'Subscribe to our newsletter'\n"
				. "- Brief paragraph explaining what subscribers get (e.g. 'Weekly tips, exclusive guides, and early access')\n"
				. "- wp:button CTA (e.g. 'Join 5,000+ Subscribers')\n",

			'contact_section'    => "Create a contact section:\n"
				. "- Section H2 headline (welcoming, not just 'Contact')\n"
				. "- 2-3 column wp:columns with contact methods: email, phone, location\n"
				. "- Include response time expectations\n"
				. "- wp:button CTA for the primary contact method\n",

			'blog_intro'         => "Create an engaging blog introduction:\n"
				. "- Open with a hook: surprising statistic, thought-provoking question, or vivid scenario\n"
				. "- 2-3 paragraphs that set up the problem and promise a solution\n"
				. "- Preview what the reader will learn (use wp:list if listing key takeaways)\n"
				. "- Make the reader feel this article was written specifically for their challenge\n",
		];

		return $guides[ $content_type ] ?? '';
	}

	/**
	 * System prompt for modifying existing block content.
	 *
	 * @return string
	 */
	private function editor_modify_system_prompt() {
		return "You are an expert WordPress content editor.\n"
			. "You receive existing Gutenberg block markup and a user instruction.\n"
			. "Modify ONLY what the user asks for. Preserve everything else exactly as-is.\n"
			. "Keep the same block type and structure unless the user explicitly asks to change it.\n\n"
			. "Return a single JSON object: {\"content\": \"<modified gutenberg block markup>\"}\n\n"
			. "BLOCK RULES:\n"
			. "- All content MUST use WordPress Gutenberg block comments\n"
			. "- Lists: <!-- wp:list-item --><li>…</li><!-- /wp:list-item --> for every item, class=\"wp-block-list\" on ul/ol\n"
			. "- Headings: class=\"wp-block-heading\" on h2/h3/h4 tags\n"
			. "- Buttons: <!-- wp:buttons --><div class=\"wp-block-buttons\"><!-- wp:button --><div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\">Text</a></div><!-- /wp:button --></div><!-- /wp:buttons -->\n"
			. "- Columns: <!-- wp:columns --><div class=\"wp-block-columns\"><!-- wp:column --><div class=\"wp-block-column\">…</div><!-- /wp:column --></div><!-- /wp:columns -->\n"
			. "- Groups: <!-- wp:group --><div class=\"wp-block-group\">…</div><!-- /wp:group -->\n"
			. "- Separators: class=\"wp-block-separator has-alpha-channel-opacity\"\n"
			. "- Spacers: <!-- wp:spacer {\"height\":\"50px\"} --><div style=\"height:50px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div><!-- /wp:spacer -->\n\n"
			. "QUALITY RULES:\n"
			. "- When rewriting text, improve it — make it more specific, compelling, and benefit-driven\n"
			. "- Do NOT add blocks not in the original unless the user asks for additions\n"
			. "- Do NOT remove blocks unless the user asks for removal\n"
			. "- When adding images, use wp:image or wp:cover with real Unsplash URLs or https://placehold.co placeholders\n"
			. "- When a brand palette is provided, apply those colors via inline style attributes\n"
			. "- Do NOT use markdown syntax. Do NOT wrap in code fences.\n"
			. "- Output ONLY final publishable content — no notes, no meta-commentary. It goes directly onto a live website.\n\n"
			. "Output only the JSON object, nothing else.";
	}

	/**
	 * User prompt for modifying existing block content.
	 *
	 * @param string        $block_content Existing block markup.
	 * @param string        $instruction   User modification instruction.
	 * @param array<string> $palette       Optional brand hex colors.
	 * @return string
	 */
	private function editor_modify_user_prompt( $block_content, $instruction, $palette = [] ) {
		$output = "Here is the existing Gutenberg block markup:\n\n"
			. $block_content . "\n\n"
			. "Instruction: " . $instruction . "\n\n";

		if ( ! empty( $palette ) ) {
			$primary   = $palette[0];
			$secondary = $palette[1] ?? $palette[0];
			$accent    = $palette[2] ?? $palette[1] ?? $palette[0];

			$output .= "Brand color palette (apply via inline style attributes):\n"
				. "- Primary (" . $primary . "): headings → style=\"color:" . $primary . "\"\n"
				. "- Secondary (" . $secondary . "): buttons → style=\"background-color:" . $secondary . ";color:#ffffff\"\n"
				. "- Accent (" . $accent . "): highlights, borders\n";
			if ( isset( $palette[3] ) ) {
				$output .= "- Dark (" . $palette[3] . "): dark backgrounds → style=\"background-color:" . $palette[3] . "\" with white text\n";
			}
			if ( isset( $palette[4] ) ) {
				$output .= "- Light (" . $palette[4] . "): light section backgrounds\n";
			}
			if ( isset( $palette[5] ) ) {
				$output .= "- Muted (" . $palette[5] . "): borders, dividers\n";
			}
			$output .= "Ensure all headings, buttons, and section backgrounds have inline style attributes with these colors.\n\n";
		}

		$output .= "Apply the instruction to the block markup above and return the modified version as Gutenberg block markup.";

		return $output;
	}

	/**
	 * Extract first JSON object from model output.
	 *
	 * @param string $text Model output.
	 * @return string
	 */
	private function extract_json_object( $text ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return '{}';
		}

		// Strip markdown code fences that some models add.
		$text = preg_replace( '/^```(?:json)?\s*\n?/i', '', $text );
		$text = preg_replace( '/\n?```\s*$/', '', $text );
		$text = trim( $text );

		if ( '' === $text ) {
			return '{}';
		}

		if ( '{' === substr( $text, 0, 1 ) && '}' === substr( $text, -1 ) ) {
			return $text;
		}

		if ( preg_match( '/\{(?:[^{}]|(?R))*\}/s', $text, $matches ) && ! empty( $matches[0] ) ) {
			return (string) $matches[0];
		}

		return '{}';
	}

	/**
	 * Strip markdown fences and JSON {"content":"..."} wrapper from raw LLM output.
	 *
	 * @param string $raw Raw output text.
	 * @return string Cleaned content.
	 */
	private function strip_content_wrapper( $raw ) {
		$stripped = preg_replace( '/^```(?:html|json)?\s*\n?/i', '', $raw );
		$stripped = preg_replace( '/\n?```\s*$/', '', (string) $stripped );
		$stripped = trim( (string) $stripped );

		// Strategy 1: Parse as JSON and extract known content keys.
		$decoded = json_decode( $stripped, true );
		if ( is_array( $decoded ) ) {
			foreach ( [ 'content', 'block_content', 'blocks', 'markup', 'html', 'text' ] as $key ) {
				if ( isset( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) && '' !== trim( $decoded[ $key ] ) ) {
					return trim( $decoded[ $key ] );
				}
			}
		}

		// Strategy 2: Remove invalid control chars and retry JSON parse.
		$fixed = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $stripped );
		if ( null !== $fixed && $fixed !== $stripped ) {
			$decoded = json_decode( $fixed, true );
			if ( is_array( $decoded ) ) {
				foreach ( [ 'content', 'block_content', 'blocks', 'markup', 'html', 'text' ] as $key ) {
					if ( isset( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) && '' !== trim( $decoded[ $key ] ) ) {
						return trim( $decoded[ $key ] );
					}
				}
			}
		}

		// Strategy 3: Manual extraction — find {"content":" and take everything after it.
		$content_pos = strpos( $stripped, '"content"' );
		if ( false !== $content_pos ) {
			// Find the colon after "content", then the opening quote of the value.
			$after_key = substr( $stripped, $content_pos + 9 );
			$after_key = ltrim( $after_key, " \t\n\r:" );
			if ( '' !== $after_key && '"' === $after_key[0] ) {
				// Remove leading quote.
				$inner = substr( $after_key, 1 );
				// Remove trailing "} wrapper if present.
				$inner = preg_replace( '/"\s*\}\s*$/', '', $inner );
				$inner = (string) $inner;
				// Unescape JSON string escapes.
				$inner = str_replace( [ '\\n', '\\t', '\\"', '\\\\' ], [ "\n", "\t", '"', '\\' ], $inner );
				$inner = trim( $inner );
				if ( '' !== $inner ) {
					return $inner;
				}
			}
		}

		// Strategy 4: No wrapper detected — return as-is (raw HTML/block markup).
		return $stripped;
	}

	/**
	 * Convert HTML or markdown content into Gutenberg blocks.
	 *
	 * Detects HTML tags (headings, lists, blockquotes, hr) and wraps them
	 * in the correct Gutenberg block comments. Falls back to paragraph
	 * blocks for unrecognised content.
	 *
	 * @param string $content HTML or plain text content.
	 * @return string
	 */
	private function normalize_html_to_blocks( $content ) {
		$content = str_replace( [ "\r\n", "\r" ], "\n", trim( $content ) );
		if ( '' === $content ) {
			return '';
		}

		// If the content contains HTML tags, convert them to Gutenberg blocks.
		if ( preg_match( '/<(h[1-6]|p|ul|ol|blockquote|hr)\b/i', $content ) ) {
			return $this->html_elements_to_blocks( $content );
		}

		// Otherwise try markdown-style conversion (headings, lists, quotes).
		return $this->markdown_to_blocks( $content );
	}

	/**
	 * Convert HTML elements into Gutenberg block markup.
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	private function html_elements_to_blocks( $html ) {
		$blocks = [];

		// Heading blocks: <h1>–<h6>.
		$html = preg_replace_callback(
			'/<h([1-6])[^>]*>(.*?)<\/h\1>/is',
			static function ( $m ) use ( &$blocks ) {
				$level = (int) $m[1];
				$text  = trim( $m[2] );
				if ( '' !== $text ) {
					$blocks[] = '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . $text . '</h' . $level . '><!-- /wp:heading -->';
				}
				return "\n<!--BLOCK_PLACEHOLDER-->\n";
			},
			$html
		);

		// List blocks: <ul>…</ul> and <ol>…</ol>.
		$html = preg_replace_callback(
			'/<(ul|ol)[^>]*>(.*?)<\/\1>/is',
			static function ( $m ) use ( &$blocks ) {
				$tag   = strtolower( $m[1] );
				$inner = trim( $m[2] );
				if ( '' !== $inner ) {
					// Wrap each <li> in wp:list-item if not already wrapped.
					$inner = preg_replace(
						'/(?<!<!-- wp:list-item -->)<li>(.*?)<\/li>(?!<!-- \/wp:list-item -->)/is',
						'<!-- wp:list-item --><li>$1</li><!-- /wp:list-item -->',
						$inner
					);
					$attrs    = 'ol' === $tag ? ' {"ordered":true}' : '';
					$blocks[] = '<!-- wp:list' . $attrs . ' --><' . $tag . ' class="wp-block-list">' . $inner . '</' . $tag . '><!-- /wp:list -->';
				}
				return "\n<!--BLOCK_PLACEHOLDER-->\n";
			},
			$html
		);

		// Blockquote blocks.
		$html = preg_replace_callback(
			'/<blockquote[^>]*>(.*?)<\/blockquote>/is',
			static function ( $m ) use ( &$blocks ) {
				$inner = trim( $m[1] );
				if ( '' !== $inner ) {
					// Wrap bare <p> tags in wp:paragraph if not already wrapped.
					$inner = preg_replace(
						'/(?<!<!-- wp:paragraph -->)<p>(.*?)<\/p>(?!<!-- \/wp:paragraph -->)/is',
						'<!-- wp:paragraph --><p>$1</p><!-- /wp:paragraph -->',
						$inner
					);
					// If no <p> tags at all, wrap the content in a paragraph block.
					if ( false === strpos( $inner, '<p>' ) ) {
						$inner = '<!-- wp:paragraph --><p>' . $inner . '</p><!-- /wp:paragraph -->';
					}
					$blocks[] = '<!-- wp:quote --><blockquote class="wp-block-quote">' . $inner . '</blockquote><!-- /wp:quote -->';
				}
				return "\n<!--BLOCK_PLACEHOLDER-->\n";
			},
			$html
		);

		// Horizontal rule blocks.
		$html = preg_replace_callback(
			'/<hr\s*\/?>/i',
			static function () use ( &$blocks ) {
				$blocks[] = '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
				return "\n<!--BLOCK_PLACEHOLDER-->\n";
			},
			$html
		);

		// Paragraph blocks: <p>…</p>.
		$html = preg_replace_callback(
			'/<p[^>]*>(.*?)<\/p>/is',
			static function ( $m ) use ( &$blocks ) {
				$text = trim( $m[1] );
				if ( '' !== $text ) {
					$blocks[] = '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
				}
				return "\n<!--BLOCK_PLACEHOLDER-->\n";
			},
			$html
		);

		// Remaining text outside HTML tags → paragraph blocks.
		$remaining = preg_split( '/<!--BLOCK_PLACEHOLDER-->/', $html );
		if ( is_array( $remaining ) ) {
			$insert_before = [];
			foreach ( $remaining as $idx => $fragment ) {
				$fragment = trim( strip_tags( $fragment ) );
				if ( '' !== $fragment ) {
					$insert_before[ $idx ] = '<!-- wp:paragraph --><p>' . esc_html( $fragment ) . '</p><!-- /wp:paragraph -->';
				}
			}
			// Merge remainder blocks with positioned blocks.
			if ( ! empty( $insert_before ) ) {
				$merged = [];
				$block_idx = 0;
				for ( $i = 0; $i <= count( $blocks ); $i++ ) {
					if ( isset( $insert_before[ $i ] ) ) {
						$merged[] = $insert_before[ $i ];
					}
					if ( $block_idx < count( $blocks ) ) {
						$merged[] = $blocks[ $block_idx ];
						$block_idx++;
					}
				}
				$blocks = $merged;
			}
		}

		if ( empty( $blocks ) ) {
			return '<!-- wp:paragraph --><p>' . wp_kses_post( $html ) . '</p><!-- /wp:paragraph -->';
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Convert markdown-like content into Gutenberg blocks.
	 *
	 * @param string $content Markdown or plain text.
	 * @return string
	 */
	private function markdown_to_blocks( $content ) {
		$lines  = preg_split( '/\r\n|\r|\n/', $content );
		if ( ! is_array( $lines ) ) {
			return '<!-- wp:paragraph --><p>' . esc_html( $content ) . '</p><!-- /wp:paragraph -->';
		}

		$blocks     = [];
		$paragraphs = [];
		$list_type  = '';
		$list_items = [];

		$flush_para = static function () use ( &$paragraphs, &$blocks ) {
			if ( empty( $paragraphs ) ) {
				return;
			}
			$blocks[]   = '<!-- wp:paragraph --><p>' . esc_html( implode( ' ', $paragraphs ) ) . '</p><!-- /wp:paragraph -->';
			$paragraphs = [];
		};

		$flush_list = static function () use ( &$list_type, &$list_items, &$blocks ) {
			if ( empty( $list_items ) ) {
				return;
			}
			$ordered = 'ordered' === $list_type;
			$tag     = $ordered ? 'ol' : 'ul';
			$html    = '';
			foreach ( $list_items as $item ) {
				$html .= '<!-- wp:list-item --><li>' . esc_html( $item ) . '</li><!-- /wp:list-item -->';
			}
			$attrs    = $ordered ? ' {"ordered":true}' : '';
			$blocks[] = '<!-- wp:list' . $attrs . ' --><' . $tag . ' class="wp-block-list">' . $html . '</' . $tag . '><!-- /wp:list -->';
			$list_type  = '';
			$list_items = [];
		};

		foreach ( $lines as $line ) {
			$trim = trim( (string) $line );

			if ( '' === $trim ) {
				$flush_para();
				$flush_list();
				continue;
			}

			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trim, $m ) ) {
				$flush_para();
				$flush_list();
				$level    = strlen( $m[1] );
				$blocks[] = '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . esc_html( $m[2] ) . '</h' . $level . '><!-- /wp:heading -->';
				continue;
			}

			if ( preg_match( '/^([-*_])\1{2,}$/', $trim ) ) {
				$flush_para();
				$flush_list();
				$blocks[] = '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
				continue;
			}

			if ( preg_match( '/^\d+[\.\)]\s+(.+)$/', $trim, $m ) ) {
				$flush_para();
				if ( 'ordered' !== $list_type ) {
					$flush_list();
					$list_type = 'ordered';
				}
				$list_items[] = $m[1];
				continue;
			}

			if ( preg_match( '/^[-*+]\s+(.+)$/', $trim, $m ) ) {
				$flush_para();
				if ( 'unordered' !== $list_type ) {
					$flush_list();
					$list_type = 'unordered';
				}
				$list_items[] = $m[1];
				continue;
			}

			$flush_list();
			$paragraphs[] = $trim;
		}

		$flush_para();
		$flush_list();

		if ( empty( $blocks ) ) {
			return '<!-- wp:paragraph --><p>' . esc_html( $content ) . '</p><!-- /wp:paragraph -->';
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Extract editor content text from the decoded model payload.
	 *
	 * @param array<string,mixed> $payload Model JSON payload.
	 * @return string
	 */
	private function extract_editor_content_from_payload( $payload ) {
		if ( ! is_array( $payload ) ) {
			return '';
		}

		if ( array_key_exists( 'content', $payload ) ) {
			$content = $this->coerce_content_value( $payload['content'] );
			if ( '' !== $content ) {
				return $content;
			}
		}

		foreach ( [ 'block_content', 'blocks', 'markup', 'html', 'text' ] as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$content = $this->coerce_content_value( $payload[ $key ] );
				if ( '' !== $content ) {
					return $content;
				}
			}
		}

		return '';
	}

	/**
	 * Convert mixed content value into a plain string.
	 *
	 * @param mixed $value Content candidate.
	 * @return string
	 */
	private function coerce_content_value( $value ) {
		if ( is_string( $value ) ) {
			return trim( $value );
		}

		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		if ( isset( $value['content'] ) ) {
			return $this->coerce_content_value( $value['content'] );
		}

		if ( isset( $value['text'] ) ) {
			return $this->coerce_content_value( $value['text'] );
		}

		$parts = [];
		foreach ( $value as $item ) {
			$chunk = $this->coerce_content_value( $item );
			if ( '' !== $chunk ) {
				$parts[] = $chunk;
			}
		}

		return empty( $parts ) ? '' : trim( implode( "\n", $parts ) );
	}

	/**
	 * Sanitize Gutenberg block markup so blocks pass WordPress validation.
	 *
	 * Fixes common AI omissions: missing CSS classes, missing wp:list-item
	 * inner blocks, and bare paragraphs inside blockquotes.
	 *
	 * Every pattern uses a negative lookahead so it is safe to run multiple
	 * times (idempotent).
	 *
	 * @param string $content Block markup.
	 * @return string
	 */
	private function sanitize_block_markup( $content ) {
		if ( '' === $content || false === strpos( $content, '<!-- wp:' ) ) {
			return $content;
		}

		// 1. Headings: add class="wp-block-heading" when missing.
		$content = preg_replace(
			'/(<h[1-6])(?![^>]*class=)([^>]*>)/i',
			'$1 class="wp-block-heading"$2',
			$content
		);

		// 2. Lists: add class="wp-block-list" to <ul>/<ol> when missing.
		//    Pattern uses (?:\s.*?)? to safely match JSON attrs that may
		//    contain dashes (e.g. className values).
		$content = preg_replace(
			'/(<!-- wp:list(?:\s.*?)?-->)\s*<(ul|ol)(?![^>]*class=)([^>]*)>/is',
			'$1<$2 class="wp-block-list"$3>',
			$content
		);

		// 3. List items: wrap bare <li> in <!-- wp:list-item --> per list block.
		//    Processes each wp:list block individually; skips if it already
		//    contains wp:list-item comments (avoids double-wrapping).
		$content = preg_replace_callback(
			'/(<!-- wp:list(?:\s.*?)?-->)(.*?)(<!-- \/wp:list -->)/is',
			static function ( $m ) {
				if ( false !== strpos( $m[2], '<!-- wp:list-item' ) ) {
					return $m[0];
				}
				$inner = preg_replace(
					'/<li>(.*?)<\/li>/is',
					'<!-- wp:list-item --><li>$1</li><!-- /wp:list-item -->',
					$m[2]
				);
				return $m[1] . $inner . $m[3];
			},
			$content
		);

		// 4. Separators: add classes when missing.
		$content = preg_replace(
			'/(<!-- wp:separator[^>]*-->)\s*<hr(?![^>]*class=)([^>]*)\/?>/i',
			'$1<hr class="wp-block-separator has-alpha-channel-opacity"$2/>',
			$content
		);

		// 5. Blockquotes: add class="wp-block-quote" when missing.
		$content = preg_replace(
			'/(<!-- wp:quote[^>]*-->)\s*<blockquote(?![^>]*class=)([^>]*)>/i',
			'$1<blockquote class="wp-block-quote"$2>',
			$content
		);

		// 6. Blockquotes: wrap bare <p> inside wp:quote in <!-- wp:paragraph -->.
		//    Processes each wp:quote block individually; skips if it already
		//    contains wp:paragraph comments.
		$content = preg_replace_callback(
			'/(<!-- wp:quote[^>]*-->)(.*?)(<!-- \/wp:quote -->)/is',
			static function ( $m ) {
				if ( false !== strpos( $m[2], '<!-- wp:paragraph' ) ) {
					return $m[0];
				}
				$inner = preg_replace(
					'/<p>(.*?)<\/p>/is',
					'<!-- wp:paragraph --><p>$1</p><!-- /wp:paragraph -->',
					$m[2]
				);
				return $m[1] . $inner . $m[3];
			},
			$content
		);

		return $content;
	}

	/**
	 * Return installed and active plugin slugs.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_installed_plugins( $request ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', [] );

		$installed_slugs = [];
		$active_slugs    = [];

		foreach ( $all_plugins as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			$installed_slugs[] = $slug;

			if ( in_array( $file, $active_plugins, true ) ) {
				$active_slugs[] = $slug;
			}
		}

		return rest_ensure_response( [
			'success' => true,
			'plugins' => [
				'installed' => array_unique( $installed_slugs ),
				'active'    => array_unique( $active_slugs ),
			],
		] );
	}

	/**
	 * Return MCP tools grouped by module.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_mcp_tools( $request ) {
		$registry = new Tool_Registry();
		$tools    = $registry->list_tools();
		$modules  = $registry->module_status();

		$grouped = [];
		foreach ( $tools as $tool ) {
			$module = isset( $tool['module'] ) ? (string) $tool['module'] : 'core';
			if ( ! isset( $grouped[ $module ] ) ) {
				$grouped[ $module ] = [
					'module'  => $module,
					'enabled' => ! empty( $modules[ $module ]['enabled'] ),
					'tools'   => [],
				];
			}
			$grouped[ $module ]['tools'][] = [
				'name'        => $tool['name'] ?? '',
				'description' => $tool['description'] ?? '',
				'accessLevel' => $tool['accessLevel'] ?? 'admin',
				'inputSchema' => $tool['inputSchema'] ?? new \stdClass(),
			];
		}

		return rest_ensure_response( [
			'success' => true,
			'modules' => array_values( $grouped ),
		] );
	}
}
