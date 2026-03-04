<?php
/**
 * Agent engine.
 *
 * @package OpenWP\Inc\Agent
 */

namespace OpenWP\Inc\Agent;

use OpenWP\Inc\Actions\ActionContext;
use OpenWP\Inc\Actions\Action_Executor;
use OpenWP\Inc\Actions\Action_Registry;
use OpenWP\Inc\Core\Settings;
use OpenWP\Inc\MCP\Tool_Registry;
use OpenWP\Inc\Memory\Memory_Service;
use OpenWP\Inc\Providers\Provider_Factory;
use OpenWP\Inc\Providers\ProviderRequest;
use OpenWP\Inc\Security\Rate_Limiter;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles prompt -> action generation and execution.
 */
class Agent_Engine {
	/**
	 * Execute user prompt via provider and action executor.
	 *
	 * @param string $prompt Natural language prompt.
	 * @param array<string,mixed> $args Optional args.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_prompt( $prompt, $args = [] ) {
		$prompt = trim( (string) $prompt );
		if ( '' === $prompt ) {
			return new WP_Error( 'openwp_empty_prompt', __( 'Prompt is required.', 'openwp' ) );
		}

		if ( OPENWP_DISABLE_AGENT ) {
			return new WP_Error( 'openwp_agent_disabled', __( 'Agent execution is disabled via OPENWP_DISABLE_AGENT.', 'openwp' ) );
		}

		$settings = Settings::get();
		if ( ! empty( $settings['kill_switch'] ) ) {
			return new WP_Error( 'openwp_agent_kill_switch', __( 'Agent execution is disabled in OpenWP settings.', 'openwp' ) );
		}

		$user_id  = get_current_user_id();
		$provider = isset( $args['provider'] ) ? sanitize_key( (string) $args['provider'] ) : $this->get_default_provider( $settings );
		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = $this->get_default_provider( $settings );
		}
		$model    = isset( $args['model'] ) ? sanitize_text_field( (string) $args['model'] ) : $this->get_default_model( $provider, $settings );

		$client = Provider_Factory::create( $provider );
		if ( ! $client ) {
			return new WP_Error( 'openwp_provider_invalid', __( 'Invalid or unsupported provider.', 'openwp' ) );
		}

		return $this->execute_single_prompt(
			$client,
			$provider,
			$model,
			$prompt,
			$settings,
			$user_id,
			$args
		);
	}

	/**
	 * Execute user prompt with real-time SSE streaming.
	 *
	 * @param string              $prompt   Natural language prompt.
	 * @param array<string,mixed> $args     Optional args.
	 * @param callable            $on_event Callback receiving typed event arrays.
	 * @return array<string,mixed>|WP_Error Final result (also sent as 'done' event).
	 */
	public function execute_prompt_stream( $prompt, $args, callable $on_event ) {
		$prompt = trim( (string) $prompt );
		if ( '' === $prompt ) {
			return new WP_Error( 'openwp_empty_prompt', __( 'Prompt is required.', 'openwp' ) );
		}

		if ( OPENWP_DISABLE_AGENT ) {
			return new WP_Error( 'openwp_agent_disabled', __( 'Agent execution is disabled via OPENWP_DISABLE_AGENT.', 'openwp' ) );
		}

		$settings = Settings::get();
		if ( ! empty( $settings['kill_switch'] ) ) {
			return new WP_Error( 'openwp_agent_kill_switch', __( 'Agent execution is disabled in OpenWP settings.', 'openwp' ) );
		}

		$user_id  = get_current_user_id();
		$provider = isset( $args['provider'] ) ? sanitize_key( (string) $args['provider'] ) : $this->get_default_provider( $settings );
		if ( ! in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ) {
			$provider = $this->get_default_provider( $settings );
		}
		$model    = isset( $args['model'] ) ? sanitize_text_field( (string) $args['model'] ) : $this->get_default_model( $provider, $settings );

		$client = Provider_Factory::create( $provider );
		if ( ! $client ) {
			return new WP_Error( 'openwp_provider_invalid', __( 'Invalid or unsupported provider.', 'openwp' ) );
		}

		return $this->execute_single_prompt_stream(
			$client,
			$provider,
			$model,
			$prompt,
			$settings,
			$user_id,
			$args,
			$on_event
		);
	}

	/**
	 * Execute single-step prompt with streaming.
	 *
	 * @param object              $client    Provider client.
	 * @param string              $provider  Provider slug.
	 * @param string              $model     Model name.
	 * @param string              $prompt    User prompt.
	 * @param array<string,mixed> $settings  Settings.
	 * @param int                 $user_id   User ID.
	 * @param array<string,mixed> $args      Optional args.
	 * @param callable            $on_event  SSE event callback.
	 * @return array<string,mixed>|WP_Error
	 */
	private function execute_single_prompt_stream( $client, $provider, $model, $prompt, $settings, $user_id, $args, callable $on_event ) {
		$rate_limiter = new Rate_Limiter();
		$limit_check  = $rate_limiter->enforce( $user_id );
		if ( is_wp_error( $limit_check ) ) {
			return $limit_check;
		}

		$conversation_context = $this->normalize_conversation_context( $args['conversation_context'] ?? null );

		$decision = $this->request_agent_decision_stream(
			$client,
			$model,
			$this->build_system_prompt( $prompt, $conversation_context ),
			$prompt,
			max( 120, (int) $settings['timeout_seconds'] ),
			$on_event
		);

		if ( is_wp_error( $decision ) ) {
			return $decision;
		}

		$provider_response = $decision['provider_response'];
		$parsed            = $this->coerce_user_delete_action( $decision['agent'], $prompt );

		if ( 'none' === $parsed['action'] ) {
			$rate_limiter->record( $user_id, (int) $provider_response->input_tokens, (int) $provider_response->output_tokens );
			$reply = ! empty( $parsed['reply'] ) ? (string) $parsed['reply'] : ( ! empty( $parsed['thought'] ) ? (string) $parsed['thought'] : __( 'No action requested by the model.', 'openwp' ) );
			return [
				'status'          => 'no_action',
				'mode'            => 'single',
				'provider'        => $provider,
				'model'           => $model,
				'message'         => $reply,
				'provider_output' => $parsed,
				'token_usage'     => [
					'input_tokens'  => (int) $provider_response->input_tokens,
					'output_tokens' => (int) $provider_response->output_tokens,
				],
			];
		}

		$executor = new Action_Executor();
		$plan     = $this->expand_action_plan( $parsed );
		$total    = count( $plan );
		$steps    = [];

		foreach ( $plan as $index => $step_agent ) {
			$step_number = $index + 1;
			$on_event(
				[
					'type'   => 'action',
					'action' => $step_agent['action'],
					'params' => $step_agent['params'],
				]
			);

			$on_event(
				[
					'type'   => 'step',
					'step'   => $step_number,
					'total'  => $total,
					'status' => 'executing',
				]
			);

			$context   = new ActionContext(
				[
					'user_id'      => $user_id,
					'prompt'       => $prompt,
					'model_output' => $this->build_step_model_output( $step_agent, $plan, $index ),
				]
			);
			$execution = $executor->execute( (string) $step_agent['action'], $step_agent['params'], $context );
			if ( is_wp_error( $execution ) ) {
				$error_msg = $execution->get_error_message();

				$on_event(
					[
						'type'    => 'result',
						'status'  => 'failed',
						'message' => $error_msg,
					]
				);

				$failed_execution = [
					'status'  => 'failed',
					'action'  => $step_agent['action'],
					'message' => $error_msg,
					'data'    => $execution->get_error_data(),
				];

				$steps[] = [
					'step'      => $step_number,
					'agent'     => $step_agent,
					'status'    => 'failed',
					'execution' => $failed_execution,
				];

				$rate_limiter->record( $user_id, (int) $provider_response->input_tokens, (int) $provider_response->output_tokens );

				return [
					'status'      => 'failed',
					'mode'        => 'single',
					'provider'    => $provider,
					'model'       => $model,
					'agent'       => $parsed,
					'execution'   => $failed_execution,
					'executions'  => $steps,
					'action_plan' => $plan,
					'token_usage' => [
						'input_tokens'  => (int) $provider_response->input_tokens,
						'output_tokens' => (int) $provider_response->output_tokens,
					],
				];
			}

			$exec_status = isset( $execution['status'] ) ? (string) $execution['status'] : 'success';
			$exec_msg    = isset( $execution['message'] ) ? (string) $execution['message'] : '';

			$on_event(
				[
					'type'    => 'result',
					'status'  => $exec_status,
					'message' => $exec_msg,
				]
			);

			$steps[] = [
				'step'      => $step_number,
				'agent'     => $step_agent,
				'status'    => $exec_status,
				'execution' => $execution,
			];

			if ( 'pending_approval' === $exec_status ) {
				if ( isset( $execution['approval_id'] ) ) {
					$on_event(
						[
							'type'        => 'approval',
							'approval_id' => (int) $execution['approval_id'],
							'action'      => $step_agent['action'],
							'risk'        => $execution['risk_level'] ?? 'high',
						]
					);
				}

				$rate_limiter->record( $user_id, (int) $provider_response->input_tokens, (int) $provider_response->output_tokens );

				$last_execution = $execution;
				return [
					'status'      => 'awaiting_approval',
					'mode'        => 'single',
					'provider'    => $provider,
					'model'       => $model,
					'agent'       => $parsed,
					'execution'   => $last_execution,
					'executions'  => $steps,
					'action_plan' => $plan,
					'token_usage' => [
						'input_tokens'  => (int) $provider_response->input_tokens,
						'output_tokens' => (int) $provider_response->output_tokens,
					],
				];
			}

			if ( ! in_array( $exec_status, [ 'success', 'no_action' ], true ) ) {
				$rate_limiter->record( $user_id, (int) $provider_response->input_tokens, (int) $provider_response->output_tokens );

				$last_execution = $execution;
				return [
					'status'      => 'failed',
					'mode'        => 'single',
					'provider'    => $provider,
					'model'       => $model,
					'agent'       => $parsed,
					'execution'   => $last_execution,
					'executions'  => $steps,
					'action_plan' => $plan,
					'token_usage' => [
						'input_tokens'  => (int) $provider_response->input_tokens,
						'output_tokens' => (int) $provider_response->output_tokens,
					],
				];
			}
		}

		$rate_limiter->record( $user_id, (int) $provider_response->input_tokens, (int) $provider_response->output_tokens );

		$on_event(
			[
				'type'   => 'tokens',
				'input'  => (int) $provider_response->input_tokens,
				'output' => (int) $provider_response->output_tokens,
			]
		);

		$last_step      = ! empty( $steps ) ? $steps[ count( $steps ) - 1 ] : null;
		$last_execution = is_array( $last_step ) && isset( $last_step['execution'] ) ? $last_step['execution'] : [];

		return [
			'status'      => 'success',
			'mode'        => 'single',
			'provider'    => $provider,
			'model'       => $model,
			'agent'       => $parsed,
			'execution'   => $last_execution,
			'executions'  => $steps,
			'action_plan' => $plan,
			'token_usage' => [
				'input_tokens'  => (int) $provider_response->input_tokens,
				'output_tokens' => (int) $provider_response->output_tokens,
			],
		];
	}

	/**
	 * Request a single decision with streaming, forwarding text deltas via events.
	 *
	 * @param object   $client        Provider client.
	 * @param string   $model         Model.
	 * @param string   $system_prompt System prompt.
	 * @param string   $user_prompt   User prompt.
	 * @param int      $timeout       Timeout seconds.
	 * @param callable $on_event      SSE event callback.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_agent_decision_stream( $client, $model, $system_prompt, $user_prompt, $timeout, callable $on_event ) {
		$request = new ProviderRequest(
			[
				'system_prompt' => $system_prompt,
				'prompt'        => $user_prompt,
				'model'         => (string) $model,
				'schema'        => $this->response_schema(),
				'stream'        => true,
				'temperature'   => 0.2,
				'timeout'       => max( 10, absint( $timeout ) ),
				'max_tokens'    => 16384,
			]
		);

		$provider_response = $client->generate_stream(
			$request,
			static function ( $text_delta ) use ( $on_event ) {
				$on_event(
					[
						'type'    => 'thinking',
						'content' => $text_delta,
					]
				);
			}
		);

		if ( is_wp_error( $provider_response ) ) {
			return $provider_response;
		}

		$raw_json = $this->extract_json_object( $provider_response->output_text );
		$parsed   = json_decode( $raw_json, true );
		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'openwp_invalid_provider_json', __( 'Provider did not return valid JSON.', 'openwp' ) );
		}

		$parsed = $this->normalize_agent_payload( $parsed );

		$validation = $this->validate_agent_payload( $parsed );
		if ( is_wp_error( $validation ) ) {
			return new WP_Error( $validation->get_error_code(), $validation->get_error_message() );
		}

		return [
			'provider_response' => $provider_response,
			'agent'             => $parsed,
		];
	}

	/**
	 * Execute single-step prompt.
	 *
	 * @param object              $client Provider client.
	 * @param string              $provider Provider slug.
	 * @param string              $model Model name.
	 * @param string              $prompt User prompt.
	 * @param array<string,mixed> $settings Settings.
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $args Optional args.
	 * @return array<string,mixed>|WP_Error
	 */
	private function execute_single_prompt( $client, $provider, $model, $prompt, $settings, $user_id, $args = [] ) {
		$rate_limiter = new Rate_Limiter();
		$limit_check  = $rate_limiter->enforce( $user_id );
		if ( is_wp_error( $limit_check ) ) {
			return $limit_check;
		}

		$conversation_context = $this->normalize_conversation_context( $args['conversation_context'] ?? null );

		$decision = $this->request_agent_decision(
			$client,
			$model,
			$this->build_system_prompt( $prompt, $conversation_context ),
			$prompt,
			max( 120, (int) $settings['timeout_seconds'] )
		);

		if ( is_wp_error( $decision ) ) {
			return $decision;
		}

		$provider_response = $decision['provider_response'];
		$parsed            = $this->coerce_user_delete_action( $decision['agent'], $prompt );

		if ( 'none' === $parsed['action'] ) {
			$rate_limiter->record( $user_id, (int) $provider_response->input_tokens, (int) $provider_response->output_tokens );
			$reply = ! empty( $parsed['reply'] ) ? (string) $parsed['reply'] : ( ! empty( $parsed['thought'] ) ? (string) $parsed['thought'] : __( 'No action requested by the model.', 'openwp' ) );
			return [
				'status'          => 'no_action',
				'mode'            => 'single',
				'provider'        => $provider,
				'model'           => $model,
				'message'         => $reply,
				'provider_output' => $parsed,
				'token_usage'     => [
					'input_tokens'  => (int) $provider_response->input_tokens,
					'output_tokens' => (int) $provider_response->output_tokens,
				],
			];
		}

		$executor = new Action_Executor();
		$plan     = $this->expand_action_plan( $parsed );
		$steps    = [];
		$status   = 'success';

		foreach ( $plan as $index => $step_agent ) {
			$context   = new ActionContext(
				[
					'user_id'      => $user_id,
					'prompt'       => $prompt,
					'model_output' => $this->build_step_model_output( $step_agent, $plan, $index ),
				]
			);
			$execution = $executor->execute( (string) $step_agent['action'], $step_agent['params'], $context );
			if ( is_wp_error( $execution ) ) {
				$failed_execution = [
					'status'  => 'failed',
					'action'  => $step_agent['action'],
					'message' => $execution->get_error_message(),
					'data'    => $execution->get_error_data(),
				];
				$steps[] = [
					'step'      => $index + 1,
					'agent'     => $step_agent,
					'status'    => 'failed',
					'execution' => $failed_execution,
				];
				$status = 'failed';
				break;
			}

			$step_status = isset( $execution['status'] ) ? (string) $execution['status'] : 'success';
			$steps[]     = [
				'step'      => $index + 1,
				'agent'     => $step_agent,
				'status'    => $step_status,
				'execution' => $execution,
			];

			if ( 'pending_approval' === $step_status ) {
				$status = 'awaiting_approval';
				break;
			}

			if ( ! in_array( $step_status, [ 'success', 'no_action' ], true ) ) {
				$status = 'failed';
				break;
			}
		}

		$rate_limiter->record( $user_id, (int) $provider_response->input_tokens, (int) $provider_response->output_tokens );

		$last_step      = ! empty( $steps ) ? $steps[ count( $steps ) - 1 ] : null;
		$last_execution = is_array( $last_step ) && isset( $last_step['execution'] ) ? $last_step['execution'] : [];

		return [
			'status'      => $status,
			'mode'        => 'single',
			'provider'    => $provider,
			'model'       => $model,
			'agent'       => $parsed,
			'execution'   => $last_execution,
			'executions'  => $steps,
			'action_plan' => $plan,
			'token_usage' => [
				'input_tokens'  => (int) $provider_response->input_tokens,
				'output_tokens' => (int) $provider_response->output_tokens,
			],
		];
	}

	/**
	 * Request a single decision from the provider and validate it.
	 *
	 * @param object $client Provider client.
	 * @param string $model Model.
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt User prompt.
	 * @param int    $timeout Timeout seconds.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_agent_decision( $client, $model, $system_prompt, $user_prompt, $timeout ) {
		$request = new ProviderRequest(
			[
				'system_prompt' => $system_prompt,
				'prompt'        => $user_prompt,
				'model'         => (string) $model,
				'schema'        => $this->response_schema(),
				'stream'        => true,
				'temperature'   => 0.2,
				'timeout'       => max( 10, absint( $timeout ) ),
				'max_tokens'    => 16384,
			]
		);

		$provider_response = $client->generate( $request );
		if ( is_wp_error( $provider_response ) ) {
			return $provider_response;
		}

		$raw_json = $this->extract_json_object( $provider_response->output_text );
		$parsed   = json_decode( $raw_json, true );
		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'openwp_invalid_provider_json', __( 'Provider did not return valid JSON.', 'openwp' ) );
		}

		$parsed = $this->normalize_agent_payload( $parsed );

		$validation = $this->validate_agent_payload( $parsed );
		if ( is_wp_error( $validation ) ) {
			return new WP_Error( $validation->get_error_code(), $validation->get_error_message() );
		}

		return [
			'provider_response' => $provider_response,
			'agent'             => $parsed,
		];
	}

	/**
	 * Build strict system prompt.
	 *
	 * @return string
	 */
	private function build_system_prompt( $user_prompt = '', $conversation_context = null ) {
		$actions = Action_Registry::all();
		$catalog = [];

		foreach ( $actions as $key => $definition ) {
			$catalog[] = [
				'action' => $key,
				'risk'   => $definition['risk'] ?? 'low',
				'schema' => $definition['schema'] ?? [],
			];
		}

		$settings      = Settings::get();
		$brand_palette = isset( $settings['theme_palette'] ) && is_array( $settings['theme_palette'] )
			? array_values( $settings['theme_palette'] )
			: [];
		$active_theme  = wp_get_theme();

		$context = [
			'site_name'        => get_bloginfo( 'name' ),
			'site_url'         => site_url(),
			'site_description' => get_bloginfo( 'description' ),
			'post_types'       => array_values( get_post_types( [ 'public' => true ] ) ),
			'plugins'          => $this->installed_plugins(),
			'themes'           => $this->installed_themes(),
			'active_theme'     => $active_theme->get( 'Name' ),
			'brand_palette'    => $brand_palette,
		];

		$system_prompt = "You are OpenWP, an AI agent operating inside WordPress.\n"
			. "You MUST return exactly one strict JSON object, no markdown or prose.\n"
			. "Allowed action keys are listed in ACTION_CATALOG.\n"
			. "If no action should run, return action=none with empty params and put your reply in a 'reply' field.\n"
			. "For listing or querying data (plugins, themes, posts, users, etc.), ALWAYS call the appropriate action (wp_list_plugins, wp_list_themes, wp_get_posts, wp_get_users, etc.) to return fresh data to the user. Do NOT answer from SITE_CONTEXT alone — SITE_CONTEXT is only for resolving identifiers (plugin file paths, theme slugs) needed as action parameters.\n"
			. "When user intent requires multiple operations, return ordered actions[] and set top-level action/params to the first action for compatibility.\n"
			. "Never invent action names or params that violate schema.\n"
			. "Manual memory policy: only call remember_memory when the user explicitly asks to remember/save a preference or rule.\n"
			. "Manual memory policy: only call forget_memory when the user explicitly asks to forget/remove a specific saved memory.\n"
			. "Manual memory policy: only call clear_memory when the user explicitly asks to clear/wipe/reset all memory.\n"
			. "For user deletion requests that already include a user email or login, choose delete_user directly; do not call wp_get_users first.\n"
			. "For plugin actions (wp_activate_plugin, wp_deactivate_plugin, wp_delete_plugin, update_plugin), use the plugin 'file' field from SITE_CONTEXT as the 'plugin' param (e.g. 'sureforms/sureforms.php'), NOT the display name.\n"
			. "For theme actions (wp_switch_theme, wp_delete_theme, update_theme), use the theme 'stylesheet' field from SITE_CONTEXT as the 'stylesheet' param, NOT the display name.\n"
			. "For wp_create_post/wp_update_post content, generate rich Gutenberg block markup using wp:heading, wp:paragraph, wp:list, wp:columns, wp:buttons, wp:group, wp:cover, wp:image, wp:spacer, and wp:separator blocks. "
			. "Write compelling, specific copy — never generic filler like 'Welcome to...' or 'We are passionate...'. "
			. "Use wp:columns for feature grids, wp:buttons for CTAs, wp:group with background colors for section separation, and wp:cover with images for hero sections. "
			. "Every page should include at least one styled wp:button CTA. "
			. "For images, use real Unsplash URLs (https://images.unsplash.com/photo-{ID}?auto=format&fit=crop&w=1200&q=80) or https://placehold.co placeholders."
			. ( ! empty( $brand_palette )
				? " Apply the brand_palette colors from SITE_CONTEXT via inline style attributes: Primary for headings (style=\"color:#HEX\"), Secondary for button backgrounds (style=\"background-color:#HEX;color:#FFF\"), Accent for highlights, remaining colors for section backgrounds and borders."
				: "" )
			. "\n"
			. "ACTION_CATALOG=" . wp_json_encode( $catalog ) . "\n"
			. "SITE_CONTEXT=" . wp_json_encode( $context );

		$mcp_catalog_block = $this->build_mcp_catalog_block();
		if ( '' !== $mcp_catalog_block ) {
			$system_prompt .= $mcp_catalog_block;
		}

		$memory_context = $this->build_memory_context( $user_prompt );
		if ( '' !== $memory_context ) {
			$system_prompt .= "\nMEMORY_CONTEXT=" . $memory_context;
		}

		$conversation_context_block = $this->build_conversation_context_block( $conversation_context );
		if ( '' !== $conversation_context_block ) {
			$system_prompt .= "\nCONVERSATION_CONTEXT=" . $conversation_context_block;
		}

		return $system_prompt;
	}

	/**
	 * Build bounded memory context string for prompt injection.
	 *
	 * @param string $user_prompt Current user prompt.
	 * @return string
	 */
	private function build_memory_context( $user_prompt ) {
		$service = new Memory_Service();
		$items   = $service->relevant_for_prompt( (string) $user_prompt, 8, 1200 );
		if ( empty( $items ) ) {
			return '';
		}

		$lines = [];
		foreach ( $items as $item ) {
			if ( ! empty( $item['context'] ) ) {
				$lines[] = (string) $item['context'];
			}
		}

		if ( empty( $lines ) ) {
			return '';
		}

		$encoded = wp_json_encode( array_values( $lines ) );
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Build MCP tool catalog block for prompt injection.
	 *
	 * Enumerates all active MCP tools and maps each one to the appropriate
	 * risk-stratified bridge action (mcp_read_tool / mcp_write_tool /
	 * mcp_admin_tool). Returns an empty string on failure so the rest of the
	 * system prompt is never broken.
	 *
	 * @return string Empty string or "\nMCP_TOOL_CATALOG=..." + routing instruction.
	 */
	private function build_mcp_catalog_block() {
		try {
			$mcp_registry = new Tool_Registry();
			$mcp_tools    = $mcp_registry->list_tools();

			if ( empty( $mcp_tools ) ) {
				return '';
			}

			$mcp_catalog = [];
			foreach ( $mcp_tools as $tool ) {
				// Skip MCP tools that already exist in ACTION_CATALOG to avoid duplication.
				if ( Action_Registry::get( $tool['name'] ) ) {
					continue;
				}

				$access = isset( $tool['accessLevel'] ) ? (string) $tool['accessLevel'] : 'admin';
				if ( 'read' === $access ) {
					$bridge = 'mcp_read_tool';
				} elseif ( 'write' === $access ) {
					$bridge = 'mcp_write_tool';
				} else {
					$bridge = 'mcp_admin_tool';
				}

				$mcp_catalog[] = [
					'name'        => $tool['name'],
					'description' => $tool['description'],
					'bridge'      => $bridge,
					'inputSchema' => $tool['inputSchema'],
				];
			}

			$encoded = wp_json_encode( $mcp_catalog );
			if ( ! is_string( $encoded ) ) {
				return '';
			}

			return "\nMCP_TOOL_CATALOG=" . $encoded
				. "\nFor operations in MCP_TOOL_CATALOG use action=<bridge> with params.tool=<name> and params.args={...}.";
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Normalize conversation context from API request.
	 *
	 * @param mixed $conversation_context Conversation context payload.
	 * @return array<string,mixed>
	 */
	private function normalize_conversation_context( $conversation_context ) {
		$empty = [
			'enabled' => false,
			'turns'   => [],
		];
		if ( ! is_array( $conversation_context ) || empty( $conversation_context['enabled'] ) ) {
			return $empty;
		}

		$raw_turns = isset( $conversation_context['turns'] ) && is_array( $conversation_context['turns'] ) ? $conversation_context['turns'] : [];
		if ( empty( $raw_turns ) ) {
			return $empty;
		}

		$turns       = [];
		$total_chars = 0;
		foreach ( $raw_turns as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}

			$user      = $this->sanitize_conversation_text( $turn['user'] ?? '', 500 );
			$assistant = $this->sanitize_conversation_text( $turn['assistant'] ?? '', 700 );
			if ( '' === $user || '' === $assistant ) {
				continue;
			}

			$turn_chars = $this->string_length( $user ) + $this->string_length( $assistant );
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
			return $empty;
		}

		return [
			'enabled' => true,
			'turns'   => $turns,
		];
	}

	/**
	 * Build encoded conversation context block for prompt injection.
	 *
	 * @param mixed $conversation_context Conversation context payload.
	 * @return string
	 */
	private function build_conversation_context_block( $conversation_context ) {
		$normalized = $this->normalize_conversation_context( $conversation_context );
		if ( empty( $normalized['enabled'] ) || empty( $normalized['turns'] ) ) {
			return '';
		}

		$encoded = wp_json_encode( array_values( $normalized['turns'] ) );
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Sanitize and truncate conversation text.
	 *
	 * @param mixed $text Raw text.
	 * @param int   $max_len Maximum characters.
	 * @return string
	 */
	private function sanitize_conversation_text( $text, $max_len ) {
		$sanitized = wp_strip_all_tags( (string) $text );
		$sanitized = preg_replace( '/\s+/u', ' ', $sanitized );
		$sanitized = is_string( $sanitized ) ? trim( $sanitized ) : '';
		if ( '' === $sanitized ) {
			return '';
		}

		if ( $this->string_length( $sanitized ) <= $max_len ) {
			return $sanitized;
		}

		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $sanitized, 0, $max_len );
		}

		return substr( $sanitized, 0, $max_len );
	}

	/**
	 * String length helper with multibyte support.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function string_length( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( (string) $text );
		}

		return (int) strlen( (string) $text );
	}

	/**
	 * Coerce model output for direct user deletion when prompt is explicit.
	 *
	 * @param array<string,mixed> $agent Parsed agent output.
	 * @param string              $prompt User prompt.
	 * @return array<string,mixed>
	 */
	private function coerce_user_delete_action( $agent, $prompt ) {
		$action = isset( $agent['action'] ) ? (string) $agent['action'] : '';
		if ( 'wp_get_users' !== $action ) {
			return $agent;
		}

		$normalized_prompt = strtolower( $prompt );
		$mentions_delete   = false !== strpos( $normalized_prompt, 'delete' ) || false !== strpos( $normalized_prompt, 'remove' );
		$mentions_user     = false !== strpos( $normalized_prompt, 'user' );
		if ( ! $mentions_delete || ! $mentions_user ) {
			return $agent;
		}

		$matches = [];
		if ( ! preg_match( '/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $prompt, $matches ) ) {
			return $agent;
		}

		$email = sanitize_email( (string) $matches[1] );
		if ( '' === $email || ! is_email( $email ) ) {
			return $agent;
		}

		$agent['action'] = 'delete_user';
		$agent['params'] = [
			'user_email' => $email,
		];
		if ( isset( $agent['actions'] ) && is_array( $agent['actions'] ) && isset( $agent['actions'][0] ) && is_array( $agent['actions'][0] ) ) {
			$agent['actions'][0]['action'] = 'delete_user';
			$agent['actions'][0]['params'] = [
				'user_email' => $email,
			];
		}

		return $agent;
	}

	/**
	 * JSON schema for strict agent output.
	 *
	 * @return array<string,mixed>
	 */
	private function response_schema() {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'thought', 'action', 'params', 'confidence' ],
			'properties'           => [
				'thought'    => [ 'type' => 'string' ],
				'action'     => [ 'type' => 'string' ],
				'params'     => [ 'type' => 'object' ],
				'confidence' => [ 'type' => 'number' ],
				'actions'    => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'action', 'params' ],
						'properties'           => [
							'action'     => [ 'type' => 'string' ],
							'params'     => [ 'type' => 'object' ],
							'thought'    => [ 'type' => 'string' ],
							'confidence' => [ 'type' => 'number' ],
						],
					],
				],
			],
		];
	}

	/**
	 * Validate agent response shape.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	private function validate_agent_payload( $payload ) {
		if ( ! is_string( $payload['thought'] ) || $this->string_length( $payload['thought'] ) > 200 ) {
			return new WP_Error( 'openwp_agent_invalid_thought', __( 'Agent thought must be a string up to 200 chars.', 'openwp' ) );
		}

		if ( ! is_string( $payload['action'] ) ) {
			return new WP_Error( 'openwp_agent_invalid_action', __( 'Agent action must be a string.', 'openwp' ) );
		}

		if ( 'none' !== $payload['action'] && ! Action_Registry::get( $payload['action'] ) ) {
			return new WP_Error( 'openwp_agent_action_unregistered', __( 'Agent selected an unregistered action.', 'openwp' ) );
		}

		if ( ! is_array( $payload['params'] ) ) {
			return new WP_Error( 'openwp_agent_invalid_params', __( 'Agent params must be an object.', 'openwp' ) );
		}

		if ( ! is_numeric( $payload['confidence'] ) ) {
			return new WP_Error( 'openwp_agent_invalid_confidence', __( 'Agent confidence must be numeric.', 'openwp' ) );
		}

		if ( isset( $payload['actions'] ) ) {
			if ( ! is_array( $payload['actions'] ) ) {
				return new WP_Error( 'openwp_agent_invalid_actions', __( 'Agent actions must be an array when provided.', 'openwp' ) );
			}

			if ( count( $payload['actions'] ) > 6 ) {
				return new WP_Error( 'openwp_agent_too_many_actions', __( 'Agent actions supports up to 6 ordered actions.', 'openwp' ) );
			}

			foreach ( $payload['actions'] as $item ) {
				if ( ! is_array( $item ) ) {
					return new WP_Error( 'openwp_agent_invalid_actions', __( 'Each action plan item must be an object.', 'openwp' ) );
				}
				$item_action = isset( $item['action'] ) ? (string) $item['action'] : '';
				$item_params = isset( $item['params'] ) ? $item['params'] : null;
				if ( '' === $item_action ) {
					return new WP_Error( 'openwp_agent_invalid_actions', __( 'Each action plan item requires action.', 'openwp' ) );
				}
				if ( 'none' !== $item_action && ! Action_Registry::get( $item_action ) ) {
					return new WP_Error( 'openwp_agent_action_unregistered', __( 'Agent selected an unregistered action in plan.', 'openwp' ) );
				}
				if ( ! is_array( $item_params ) ) {
					return new WP_Error( 'openwp_agent_invalid_params', __( 'Each action plan item params must be an object.', 'openwp' ) );
				}
			}
		}

		return true;
	}

	/**
	 * Normalize provider payload into OpenWP response shape.
	 *
	 * @param array<string,mixed> $payload Raw provider payload.
	 * @return array<string,mixed>
	 */
	private function normalize_agent_payload( $payload ) {
		foreach ( [ 'response', 'agent', 'result', 'data' ] as $container_key ) {
			if ( isset( $payload[ $container_key ] ) && is_array( $payload[ $container_key ] ) ) {
				$payload = $payload[ $container_key ];
				break;
			}
		}

		if ( ! isset( $payload['action'] ) ) {
			foreach ( [ 'action_name', 'tool', 'function', 'command' ] as $candidate ) {
				if ( isset( $payload[ $candidate ] ) && is_string( $payload[ $candidate ] ) ) {
					$payload['action'] = $payload[ $candidate ];
					break;
				}
			}
		}

		if ( ! isset( $payload['params'] ) ) {
			foreach ( [ 'arguments', 'args', 'input' ] as $candidate ) {
				if ( isset( $payload[ $candidate ] ) ) {
					$payload['params'] = $payload[ $candidate ];
					break;
				}
			}
		}

		if ( isset( $payload['params'] ) && is_string( $payload['params'] ) ) {
			$decoded_params = json_decode( $payload['params'], true );
			if ( is_array( $decoded_params ) ) {
				$payload['params'] = $decoded_params;
			}
		}

		$thought = '';
		if ( isset( $payload['thought'] ) && is_string( $payload['thought'] ) ) {
			$thought = $payload['thought'];
		} elseif ( isset( $payload['reasoning'] ) && is_string( $payload['reasoning'] ) ) {
			$thought = $payload['reasoning'];
		} elseif ( isset( $payload['explanation'] ) && is_string( $payload['explanation'] ) ) {
			$thought = $payload['explanation'];
		}

		$confidence = 0.5;
		if ( isset( $payload['confidence'] ) && is_numeric( $payload['confidence'] ) ) {
			$confidence = (float) $payload['confidence'];
		}

		$action = 'none';
		if ( isset( $payload['action'] ) && is_string( $payload['action'] ) ) {
			$action = $payload['action'];
		}

		$params = [];
		if ( isset( $payload['params'] ) && is_array( $payload['params'] ) ) {
			$params = $payload['params'];
		}

		$raw_actions = null;
		if ( isset( $payload['actions'] ) && is_array( $payload['actions'] ) ) {
			$raw_actions = $payload['actions'];
		} elseif ( isset( $payload['steps'] ) && is_array( $payload['steps'] ) ) {
			$raw_actions = $payload['steps'];
		}

		$actions = [];
		if ( is_array( $raw_actions ) ) {
			foreach ( $raw_actions as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$item_action = '';
				if ( isset( $item['action'] ) && is_string( $item['action'] ) ) {
					$item_action = trim( $item['action'] );
				} else {
					foreach ( [ 'action_name', 'tool', 'function', 'command' ] as $candidate ) {
						if ( isset( $item[ $candidate ] ) && is_string( $item[ $candidate ] ) ) {
							$item_action = trim( $item[ $candidate ] );
							break;
						}
					}
				}

				$item_params = [];
				if ( isset( $item['params'] ) && is_array( $item['params'] ) ) {
					$item_params = $item['params'];
				} elseif ( isset( $item['params'] ) && is_string( $item['params'] ) ) {
					$decoded = json_decode( $item['params'], true );
					if ( is_array( $decoded ) ) {
						$item_params = $decoded;
					}
				} else {
					foreach ( [ 'arguments', 'args', 'input' ] as $candidate ) {
						if ( isset( $item[ $candidate ] ) && is_array( $item[ $candidate ] ) ) {
							$item_params = $item[ $candidate ];
							break;
						}
					}
				}

				$item_thought = '';
				if ( isset( $item['thought'] ) && is_string( $item['thought'] ) ) {
					$item_thought = $this->truncate_thought( $item['thought'] );
				}

				$item_confidence = 0.5;
				if ( isset( $item['confidence'] ) && is_numeric( $item['confidence'] ) ) {
					$item_confidence = (float) $item['confidence'];
				}

				if ( '' === $item_action ) {
					continue;
				}

				$actions[] = [
					'action'     => $item_action,
					'params'     => $item_params,
					'thought'    => $item_thought,
					'confidence' => $item_confidence,
				];
			}
		}

		if ( ! empty( $actions ) ) {
			$first = $actions[0];
			$action = $first['action'];
			$params = $first['params'];
			if ( '' === $this->truncate_thought( $thought ) && '' !== $first['thought'] ) {
				$thought = $first['thought'];
			}
			if ( ! is_numeric( $payload['confidence'] ?? null ) ) {
				$confidence = (float) $first['confidence'];
			}
		}

		$normalized_thought = $this->truncate_thought( $thought );

		return [
			'thought'    => $normalized_thought,
			'action'     => trim( $action ),
			'params'     => $params,
			'confidence' => $confidence,
			'actions'    => $actions,
		];
	}

	/**
	 * Build a bounded ordered action plan from parsed agent payload.
	 *
	 * @param array<string,mixed> $agent Parsed agent output.
	 * @return array<int,array<string,mixed>>
	 */
	private function expand_action_plan( $agent ) {
		$plan = [];
		if ( isset( $agent['actions'] ) && is_array( $agent['actions'] ) ) {
			foreach ( $agent['actions'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$action = isset( $item['action'] ) ? trim( (string) $item['action'] ) : '';
				$params = isset( $item['params'] ) && is_array( $item['params'] ) ? $item['params'] : [];
				if ( '' === $action || 'none' === $action ) {
					continue;
				}

				$plan[] = [
					'action'     => $action,
					'params'     => $params,
					'thought'    => isset( $item['thought'] ) ? $this->truncate_thought( (string) $item['thought'] ) : '',
					'confidence' => isset( $item['confidence'] ) && is_numeric( $item['confidence'] ) ? (float) $item['confidence'] : 0.5,
				];

				if ( count( $plan ) >= 6 ) {
					break;
				}
			}
		}

		if ( empty( $plan ) ) {
			$plan[] = [
				'action'     => isset( $agent['action'] ) ? trim( (string) $agent['action'] ) : '',
				'params'     => isset( $agent['params'] ) && is_array( $agent['params'] ) ? $agent['params'] : [],
				'thought'    => isset( $agent['thought'] ) ? $this->truncate_thought( (string) $agent['thought'] ) : '',
				'confidence' => isset( $agent['confidence'] ) && is_numeric( $agent['confidence'] ) ? (float) $agent['confidence'] : 0.5,
			];
		}

		return $plan;
	}

	/**
	 * Build model_output for each step including continuation metadata.
	 *
	 * @param array<string,mixed>              $step_agent Step agent payload.
	 * @param array<int,array<string,mixed>>   $plan Full ordered plan.
	 * @param int                              $index Current 0-based index.
	 * @return array<string,mixed>
	 */
	private function build_step_model_output( $step_agent, $plan, $index ) {
		$output = is_array( $step_agent ) ? $step_agent : [];
		if ( count( $plan ) <= 1 ) {
			return $output;
		}

		$output['_openwp_multi_action'] = [
			'version'      => 1,
			'current_step' => absint( $index + 1 ),
			'plan'         => array_values( $plan ),
		];

		return $output;
	}

	/**
	 * Truncate thought string to safe length.
	 *
	 * @param string $thought Thought text.
	 * @return string
	 */
	private function truncate_thought( $thought ) {
		$normalized_thought = trim( (string) $thought );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $normalized_thought, 0, 200 );
		}

		return substr( $normalized_thought, 0, 200 );
	}

	/**
	 * Extract JSON object string from model output.
	 *
	 * @param string $text Provider output text.
	 * @return string
	 */
	private function extract_json_object( $text ) {
		$text = trim( (string) $text );
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
	 * Resolve default model by provider.
	 *
	 * @param string              $provider Provider slug.
	 * @param array<string,mixed> $settings Settings map.
	 * @return string
	 */
	private function get_default_model( $provider, $settings ) {
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
	 * @param array<string,mixed> $settings Settings map.
	 * @return string
	 */
	private function get_default_provider( $settings ) {
		$provider = isset( $settings['default_provider'] ) ? sanitize_key( (string) $settings['default_provider'] ) : 'openrouter';
		return in_array( $provider, [ 'openai', 'anthropic', 'glm', 'openrouter' ], true ) ? $provider : 'openrouter';
	}

	/**
	 * Get lightweight plugin context.
	 *
	 * @return string[]
	 */
	private function installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins        = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', [] );
		$list           = [];

		foreach ( $plugins as $file => $plugin ) {
			if ( empty( $plugin['Name'] ) ) {
				continue;
			}
			$list[] = [
				'name'   => (string) $plugin['Name'],
				'file'   => (string) $file,
				'active' => in_array( $file, $active_plugins, true ),
			];
		}

		return $list;
	}

	/**
	 * Return installed themes with name, stylesheet, and active status.
	 *
	 * @return array<int,array{name:string,stylesheet:string,active:bool}>
	 */
	private function installed_themes() {
		$themes     = wp_get_themes();
		$active     = get_stylesheet();
		$list       = [];

		foreach ( $themes as $stylesheet => $theme ) {
			$list[] = [
				'name'       => (string) $theme->get( 'Name' ),
				'stylesheet' => (string) $stylesheet,
				'active'     => ( (string) $stylesheet === $active ),
			];
		}

		return $list;
	}
}
