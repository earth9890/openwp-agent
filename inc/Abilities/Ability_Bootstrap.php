<?php
/**
 * Abilities API bridge bootstrap.
 *
 * @package OpenWP\Inc\Abilities
 */

namespace OpenWP\Inc\Abilities;

use OpenWP\Inc\Actions\ActionContext;
use OpenWP\Inc\Actions\Action_Executor;
use OpenWP\Inc\Actions\Action_Registry;
use OpenWP\Inc\Traits\Get_Instance;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers OpenWP actions as WordPress abilities.
 */
class Ability_Bootstrap {
	use Get_Instance;

	/**
	 * OpenWP ability namespace.
	 *
	 * @var string
	 */
	private const ABILITY_NAMESPACE = 'openwp';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register OpenWP ability categories.
	 *
	 * @return void
	 */
	public function register_categories() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		foreach ( $this->ability_categories() as $slug => $category ) {
			if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( $slug ) ) {
				continue;
			}

			wp_register_ability_category(
				$slug,
				[
					'label'       => $category['label'],
					'description' => $category['description'],
				]
			);
		}
	}

	/**
	 * Register OpenWP actions as abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( Action_Registry::all() as $action_key => $action ) {
			$ability_name = self::ability_name_from_action( (string) $action_key );
			if ( function_exists( 'wp_has_ability' ) && wp_has_ability( $ability_name ) ) {
				continue;
			}

			$action_label = $this->humanize_action_key( (string) $action_key );
			$risk         = isset( $action['risk'] ) ? (string) $action['risk'] : 'low';
			$mutates      = ! empty( $action['mutates'] );

			// Use the action's domain category when registered, fall back to risk-based category.
			$category = isset( $action['category'] ) && '' !== (string) $action['category']
				? (string) $action['category']
				: $this->category_from_risk( $risk );

			// Expose read-only, low-risk abilities via REST for AI discoverability.
			// Mutating or higher-risk abilities stay hidden to prevent unintended calls.
			$show_in_rest = ! $mutates && 'low' === $risk;

			$args = [
				'label'               => sprintf( __( 'OpenWP: %s', 'openwp' ), $action_label ),
				'description'         => sprintf( __( 'Execute OpenWP action "%1$s" with %2$s risk guardrails.', 'openwp' ), (string) $action_key, $risk ),
				'category'            => $category,
				'input_schema'        => $this->prepare_input_schema( $action['schema'] ?? [] ),
				'execute_callback'    => function( $input = null ) use ( $action_key ) {
					return $this->execute_action_ability( (string) $action_key, $input );
				},
				'permission_callback' => function( $input = null ) use ( $action_key ) {
					return $this->can_execute_action_ability( (string) $action_key, $input );
				},
				'meta'                => [
					'annotations'  => $this->build_annotations( $action ),
					'show_in_rest' => $show_in_rest,
				],
			];

			// Pass through output schema when the action defines one.
			if ( ! empty( $action['output_schema'] ) && is_array( $action['output_schema'] ) ) {
				$args['output_schema'] = $action['output_schema'];
			}

			wp_register_ability( $ability_name, $args );
		}
	}

	/**
	 * Ability namespaced identifier for action key.
	 *
	 * @param string $action_key OpenWP action key.
	 * @return string
	 */
	public static function ability_name_from_action( $action_key ) {
		$slug = str_replace( '_', '-', sanitize_key( $action_key ) );
		if ( '' === $slug ) {
			$slug = 'action';
		}

		return self::ABILITY_NAMESPACE . '/' . $slug;
	}

	/**
	 * Execute action through the existing executor pipeline.
	 *
	 * @param string $action_key Action key.
	 * @param mixed  $input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	private function execute_action_ability( $action_key, $input = null ) {
		if ( null !== $input && ! is_array( $input ) ) {
			return new WP_Error( 'openwp_ability_invalid_input', __( 'Ability input must be an object.', 'openwp' ) );
		}

		$params = is_array( $input ) ? $input : [];
		$action = Action_Registry::get( $action_key );
		if ( ! $action ) {
			return new WP_Error( 'openwp_ability_action_missing', __( 'OpenWP action is not registered.', 'openwp' ) );
		}

		$ability_name = self::ability_name_from_action( $action_key );
		$context      = new ActionContext(
			[
				'user_id'      => get_current_user_id(),
				'prompt'       => sprintf(
					/* translators: 1: ability name, 2: action key */
					__( 'Executed via WordPress Abilities API - ability: %1$s (action: %2$s)', 'openwp' ),
					$ability_name,
					$action_key
				),
				'model_output' => [
					'source'  => 'wp_ability',
					'ability' => $ability_name,
				],
			]
		);

		$executor = new Action_Executor();
		return $executor->execute( $action_key, $params, $context );
	}

	/**
	 * Check if current user can execute action ability.
	 *
	 * @param string $action_key Action key.
	 * @param mixed  $input Ability input (unused).
	 * @return bool
	 */
	private function can_execute_action_ability( $action_key, $input = null ) {
		unset( $input );

		$action = Action_Registry::get( $action_key );
		if ( ! $action || ! is_user_logged_in() ) {
			return false;
		}

		// openwp_run_agent is the sole gate. Administrators receive it on activation.
		// We intentionally do NOT fall back to manage_options so that revoking
		// openwp_run_agent from a role has the expected effect.
		if ( ! current_user_can( 'openwp_run_agent' ) ) {
			return false;
		}

		$required_capability = isset( $action['capability'] ) ? (string) $action['capability'] : 'manage_options';
		return current_user_can( $required_capability );
	}

	/**
	 * Convert OpenWP schema to ability input schema.
	 *
	 * @param mixed $schema OpenWP action schema.
	 * @return array<string,mixed>
	 */
	private function prepare_input_schema( $schema ) {
		$schema = is_array( $schema ) ? $schema : [];

		$input_schema = [
			'type'                 => 'object',
			'properties'           => [],
			'additionalProperties' => true,
			'default'              => [],
		];

		if ( array_key_exists( 'additionalProperties', $schema ) ) {
			$input_schema['additionalProperties'] = (bool) $schema['additionalProperties'];
		}

		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			$required = [];
			foreach ( $schema['required'] as $field ) {
				if ( is_string( $field ) && '' !== $field ) {
					$required[] = $field;
				}
			}

			if ( ! empty( $required ) ) {
				$input_schema['required'] = array_values( array_unique( $required ) );
			}
		}

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $field => $rules ) {
				if ( ! is_string( $field ) || '' === $field || ! is_array( $rules ) ) {
					continue;
				}

				$input_schema['properties'][ $field ] = $this->normalize_property_schema( $rules );
			}
		}

		return $input_schema;
	}

	/**
	 * Normalize a property schema for Abilities API compatibility.
	 *
	 * @param array<string,mixed> $property_schema Property schema.
	 * @return array<string,mixed>
	 */
	private function normalize_property_schema( $property_schema ) {
		if ( isset( $property_schema['type'] ) && 'any' === $property_schema['type'] ) {
			$property_schema['type'] = [ 'integer', 'number', 'boolean', 'string', 'array', 'object', 'null' ];
		}

		return $property_schema;
	}

	/**
	 * Build ability category map.
	 *
	 * Domain categories group abilities by what they do, not just how risky they are.
	 * This makes the REST-discoverable catalogue more useful for AI agents.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function ability_categories() {
		return [
			// Domain categories.
			'openwp/content'  => [
				'label'       => __( 'OpenWP: Content', 'openwp' ),
				'description' => __( 'Abilities for managing posts, pages, and custom post types.', 'openwp' ),
			],
			'openwp/taxonomy' => [
				'label'       => __( 'OpenWP: Taxonomy', 'openwp' ),
				'description' => __( 'Abilities for managing categories, tags, and custom taxonomies.', 'openwp' ),
			],
			'openwp/media'    => [
				'label'       => __( 'OpenWP: Media', 'openwp' ),
				'description' => __( 'Abilities for managing the media library.', 'openwp' ),
			],
			'openwp/users'    => [
				'label'       => __( 'OpenWP: Users', 'openwp' ),
				'description' => __( 'Abilities for managing WordPress users and roles.', 'openwp' ),
			],
			'openwp/settings' => [
				'label'       => __( 'OpenWP: Settings', 'openwp' ),
				'description' => __( 'Abilities for reading and writing WordPress options.', 'openwp' ),
			],
			'openwp/plugins'  => [
				'label'       => __( 'OpenWP: Plugins', 'openwp' ),
				'description' => __( 'Abilities for managing WordPress plugins.', 'openwp' ),
			],
			'openwp/themes'   => [
				'label'       => __( 'OpenWP: Themes', 'openwp' ),
				'description' => __( 'Abilities for managing WordPress themes.', 'openwp' ),
			],
			'openwp/database' => [
				'label'       => __( 'OpenWP: Database', 'openwp' ),
				'description' => __( 'Abilities for database maintenance and queries.', 'openwp' ),
			],
			'openwp/comments' => [
				'label'       => __( 'OpenWP: Comments', 'openwp' ),
				'description' => __( 'Abilities for managing comments and moderation.', 'openwp' ),
			],
			'openwp/memory'   => [
				'label'       => __( 'OpenWP: Memory', 'openwp' ),
				'description' => __( 'Abilities for storing and managing explicit agent memory.', 'openwp' ),
			],
			// Risk-level fallback categories (used when no domain category is set).
			'openwp-risk-low'      => [
				'label'       => __( 'OpenWP Low Risk', 'openwp' ),
				'description' => __( 'Read or low-impact OpenWP abilities.', 'openwp' ),
			],
			'openwp-risk-medium'   => [
				'label'       => __( 'OpenWP Medium Risk', 'openwp' ),
				'description' => __( 'OpenWP abilities that can change content with moderate impact.', 'openwp' ),
			],
			'openwp-risk-high'     => [
				'label'       => __( 'OpenWP High Risk', 'openwp' ),
				'description' => __( 'OpenWP abilities with high operational impact.', 'openwp' ),
			],
			'openwp-risk-critical' => [
				'label'       => __( 'OpenWP Critical Risk', 'openwp' ),
				'description' => __( 'OpenWP abilities for critical and destructive operations.', 'openwp' ),
			],
		];
	}

	/**
	 * Resolve category slug for risk.
	 *
	 * @param string $risk Risk level.
	 * @return string
	 */
	private function category_from_risk( $risk ) {
		switch ( $risk ) {
			case 'critical':
				return 'openwp-risk-critical';
			case 'high':
				return 'openwp-risk-high';
			case 'medium':
				return 'openwp-risk-medium';
			case 'low':
			default:
				return 'openwp-risk-low';
		}
	}

	/**
	 * Convert action key into display label.
	 *
	 * @param string $action_key Action key.
	 * @return string
	 */
	private function humanize_action_key( $action_key ) {
		$label = str_replace( [ '_', '-' ], ' ', $action_key );
		return ucwords( $label );
	}

	/**
	 * Build ability behavior annotations from action metadata.
	 *
	 * @param array<string,mixed> $action Action definition.
	 * @return array<string,bool|null>
	 */
	private function build_annotations( $action ) {
		$mutates         = ! empty( $action['mutates'] );
		$requires_backup = ! empty( $action['requires_backup'] );
		$risk            = isset( $action['risk'] ) ? (string) $action['risk'] : 'low';
		$is_destructive  = $mutates && ( $requires_backup || in_array( $risk, [ 'high', 'critical' ], true ) );

		return [
			'readonly'    => ! $mutates,
			'destructive' => $is_destructive,
			'idempotent'  => $mutates ? null : true,
		];
	}
}
