<?php
/**
 * MCP tool registry.
 *
 * @package OpenWP\Inc\MCP
 */

namespace OpenWP\Inc\MCP;

use OpenWP\Inc\MCP\Modules\CoreTools;
use OpenWP\Inc\MCP\Modules\DatabaseTools;
use OpenWP\Inc\MCP\Modules\PluginTools;
use OpenWP\Inc\MCP\Modules\PolylangTools;
use OpenWP\Inc\MCP\Modules\ThemeTools;
use OpenWP\Inc\MCP\Modules\ToolModuleInterface;
use OpenWP\Inc\MCP\Modules\WooTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides normalized MCP tool catalog.
 */
class Tool_Registry {
	/**
	 * Module instances.
	 *
	 * @var array<string,ToolModuleInterface>
	 */
	private $modules = [];

	/**
	 * Cached tool map.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private $tool_map;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->boot_modules();
	}

	/**
	 * Get all modules.
	 *
	 * @return array<string,ToolModuleInterface>
	 */
	public function get_modules() {
		return $this->modules;
	}

	/**
	 * Return normalized tool list.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_tools() {
		$tools = array_values( $this->get_tool_map() );
		$tools = apply_filters( 'openwp_mcp_tools', $tools, $this );

		$normalized = [];
		foreach ( is_array( $tools ) ? $tools : [] as $tool ) {
			if ( ! is_array( $tool ) || empty( $tool['name'] ) ) {
				continue;
			}

			$schema = isset( $tool['inputSchema'] ) ? $this->normalize_input_schema( $tool['inputSchema'] ) : [
				'type'       => 'object',
				'properties' => new \stdClass(),
			];

			$normalized[] = [
				'name'        => (string) $tool['name'],
				'description' => isset( $tool['description'] ) ? (string) $tool['description'] : '',
				'inputSchema' => $schema,
				'accessLevel' => isset( $tool['accessLevel'] ) ? (string) $tool['accessLevel'] : 'admin',
				'category'    => isset( $tool['category'] ) ? (string) $tool['category'] : '',
				'annotations' => isset( $tool['annotations'] ) && is_array( $tool['annotations'] ) ? $tool['annotations'] : [],
			];
		}

		return $normalized;
	}

	/**
	 * Normalize JSON schema for MCP client compatibility.
	 *
	 * @param mixed $schema Raw schema.
	 * @return array<string,mixed>
	 */
	private function normalize_input_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return [
				'type'       => 'object',
				'properties' => new \stdClass(),
			];
		}

		$schema = $this->normalize_schema_types( $schema );

		if ( ! isset( $schema['type'] ) ) {
			$schema['type'] = 'object';
		}

		if ( ! isset( $schema['properties'] ) ) {
			$schema['properties'] = new \stdClass();
		} elseif ( is_object( $schema['properties'] ) ) {
			$schema['properties'] = (array) $schema['properties'];
		} elseif ( ! is_array( $schema['properties'] ) ) {
			$schema['properties'] = [];
		}

		$required = [];
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			foreach ( $schema['required'] as $required_key ) {
				$required_key = trim( (string) $required_key );
				if ( '' !== $required_key ) {
					$required[] = $required_key;
				}
			}
		}

		if ( is_array( $schema['properties'] ) ) {
			$properties = [];
			foreach ( $schema['properties'] as $key => $property_schema ) {
				if ( ! is_string( $key ) || '' === $key ) {
					continue;
				}
				if ( ! is_array( $property_schema ) && ! is_object( $property_schema ) && ! is_bool( $property_schema ) ) {
					$property_schema = [ 'type' => 'string' ];
				}
				if ( is_object( $property_schema ) || is_bool( $property_schema ) ) {
					$properties[ $key ] = $property_schema;
					continue;
				}
				$property_schema = $this->normalize_schema_types( $property_schema );
				$properties[ $key ] = ( is_array( $property_schema ) && empty( $property_schema ) ) ? new \stdClass() : $property_schema;
			}

			foreach ( $required as $required_key ) {
				if ( ! isset( $properties[ $required_key ] ) ) {
					$properties[ $required_key ] = [ 'type' => 'string' ];
				}
			}

			$schema['properties'] = empty( $properties ) ? new \stdClass() : $properties;
		}

		if ( ! empty( $required ) ) {
			$schema['required'] = array_values( array_unique( $required ) );
		}

		return $schema;
	}

	/**
	 * Normalize unsupported "any" types recursively.
	 *
	 * @param array<string,mixed> $schema Schema node.
	 * @return array<string,mixed>
	 */
	private function normalize_schema_types( $schema ) {
		if ( isset( $schema['type'] ) && 'any' === $schema['type'] ) {
			// Represent "any" with JSON-Schema anyOf to stay compatible with strict MCP validators.
			$schema = [
				'anyOf' => [
					[ 'type' => 'string' ],
					[ 'type' => 'number' ],
					[ 'type' => 'integer' ],
					[ 'type' => 'boolean' ],
					[ 'type' => 'object' ],
					[
						'type'  => 'array',
						'items' => new \stdClass(),
					],
					[ 'type' => 'null' ],
				],
			];
		}

		if ( isset( $schema['type'] ) && 'array' === $schema['type'] && ! array_key_exists( 'items', $schema ) ) {
			$schema['items'] = new \stdClass();
		}

		if ( isset( $schema['properties'] ) ) {
			if ( is_object( $schema['properties'] ) ) {
				$schema['properties'] = (array) $schema['properties'];
			}
			if ( is_array( $schema['properties'] ) ) {
				foreach ( $schema['properties'] as $key => $property_schema ) {
					if ( is_array( $property_schema ) ) {
						$property_schema = $this->normalize_schema_types( $property_schema );
						$schema['properties'][ $key ] = empty( $property_schema ) ? new \stdClass() : $property_schema;
					} elseif ( ! is_object( $property_schema ) && ! is_bool( $property_schema ) ) {
						$schema['properties'][ $key ] = new \stdClass();
					}
				}
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = $this->normalize_schema_types( $schema['items'] );
			if ( empty( $schema['items'] ) ) {
				$schema['items'] = new \stdClass();
			}
		} elseif ( array_key_exists( 'items', $schema ) && ! is_object( $schema['items'] ) && ! is_bool( $schema['items'] ) ) {
			$schema['items'] = new \stdClass();
		}

		if ( isset( $schema['additionalProperties'] ) && is_array( $schema['additionalProperties'] ) ) {
			$schema['additionalProperties'] = $this->normalize_schema_types( $schema['additionalProperties'] );
			if ( empty( $schema['additionalProperties'] ) ) {
				$schema['additionalProperties'] = new \stdClass();
			}
		} elseif ( array_key_exists( 'additionalProperties', $schema ) && ! is_object( $schema['additionalProperties'] ) && ! is_bool( $schema['additionalProperties'] ) ) {
			$schema['additionalProperties'] = new \stdClass();
		}

		foreach ( [ 'anyOf', 'oneOf', 'allOf' ] as $composite_key ) {
			if ( ! isset( $schema[ $composite_key ] ) || ! is_array( $schema[ $composite_key ] ) ) {
				continue;
			}
			$normalized_composite = [];
			foreach ( $schema[ $composite_key ] as $entry ) {
				if ( is_array( $entry ) ) {
					$entry = $this->normalize_schema_types( $entry );
					$normalized_composite[] = empty( $entry ) ? new \stdClass() : $entry;
				} elseif ( is_object( $entry ) || is_bool( $entry ) ) {
					$normalized_composite[] = $entry;
				} else {
					$normalized_composite[] = new \stdClass();
				}
			}
			$schema[ $composite_key ] = $normalized_composite;
		}

		return $schema;
	}

	/**
	 * Return tool map with metadata.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_tool_map() {
		if ( null !== $this->tool_map ) {
			return $this->tool_map;
		}

		$map = [
			'mcp_ping' => [
				'name'        => 'mcp_ping',
				'description' => 'Simple connectivity check for MCP endpoint health.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [],
				],
				'accessLevel' => 'read',
				'category'    => 'OpenWP MCP',
				'annotations' => [
					'readOnlyHint'    => true,
					'destructiveHint' => false,
					'openWorldHint'   => false,
				],
				'module'      => 'core',
			],
		];

		foreach ( $this->modules as $module_key => $module ) {
			foreach ( $module->get_tools() as $tool_name => $tool ) {
				if ( ! is_array( $tool ) ) {
					continue;
				}
				$tool['module'] = $module_key;
				$map[ $tool_name ] = $tool;
			}
		}

		$this->tool_map = $map;

		return $this->tool_map;
	}

	/**
	 * Find tool definition by name.
	 *
	 * @param string $name Tool name.
	 * @return array<string,mixed>|null
	 */
	public function get_tool( $name ) {
		$name = sanitize_text_field( (string) $name );
		$map  = $this->get_tool_map();

		return isset( $map[ $name ] ) ? $map[ $name ] : null;
	}

	/**
	 * Get module availability and tool counts.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function module_status() {
		$status = [];
		foreach ( $this->modules as $module_key => $module ) {
			$status[ $module_key ] = [
				'enabled' => true,
				'tools'   => count( $module->get_tools() ),
			];
		}

		$config = $this->get_module_config();
		foreach ( $config as $module_key => $enabled ) {
			if ( ! isset( $status[ $module_key ] ) ) {
				$status[ $module_key ] = [
					'enabled' => false,
					'tools'   => 0,
				];
			}
			if ( ! $enabled ) {
				$status[ $module_key ]['enabled'] = false;
			}
		}

		return $status;
	}

	/**
	 * Build module instances based on settings and active plugins.
	 *
	 * @return void
	 */
	private function boot_modules() {
		$config = $this->get_module_config();

		if ( ! empty( $config['core'] ) ) {
			$this->modules['core'] = new CoreTools();
		}

		if ( ! empty( $config['woo'] ) && class_exists( 'WooCommerce' ) ) {
			$this->modules['woo'] = new WooTools();
		}

		if ( ! empty( $config['plugin'] ) ) {
			$this->modules['plugin'] = new PluginTools();
		}

		if ( ! empty( $config['theme'] ) ) {
			$this->modules['theme'] = new ThemeTools();
		}

		if ( ! empty( $config['db'] ) ) {
			$this->modules['db'] = new DatabaseTools();
		}

		if ( ! empty( $config['polylang'] ) && function_exists( 'pll_languages_list' ) ) {
			$this->modules['polylang'] = new PolylangTools();
		}
	}

	/**
	 * Resolve module configuration.
	 *
	 * @return array<string,bool>
	 */
	private function get_module_config() {
		$defaults = [
			'core'     => true,
			'woo'      => true,
			'plugin'   => true,
			'theme'    => true,
			'db'       => true,
			'polylang' => true,
		];

		$stored = get_option( OPENWP_OPTION_MCP_MODULES, [] );
		if ( is_string( $stored ) ) {
			$decoded = json_decode( $stored, true );
			if ( is_array( $decoded ) ) {
				$stored = $decoded;
			}
		}
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$config = wp_parse_args( $stored, $defaults );
		foreach ( $config as $key => $value ) {
			$config[ $key ] = rest_sanitize_boolean( $value );
		}

		return $config;
	}
}
