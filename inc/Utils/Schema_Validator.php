<?php
/**
 * Simple schema validator.
 *
 * @package OpenWP\Inc\Utils
 */

namespace OpenWP\Inc\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates parameter arrays against action schemas.
 */
class Schema_Validator {
	/**
	 * Validate and sanitize params.
	 *
	 * @param array<string,mixed> $schema Schema config.
	 * @param array<string,mixed> $params Raw params.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate( $schema, $params ) {
		$schema = is_array( $schema ) ? $schema : [];
		$params = is_array( $params ) ? $params : [];

		if ( isset( $schema['properties'] ) && ( is_array( $schema['properties'] ) || is_object( $schema['properties'] ) ) ) {
			$properties = (array) $schema['properties'];
		} else {
			$properties = [];
		}
		$required   = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : [];

		$allow_additional = true;
		if ( array_key_exists( 'additionalProperties', $schema ) ) {
			$allow_additional = (bool) $schema['additionalProperties'];
		}

		if ( ! $allow_additional ) {
			$unknown = array_diff( array_keys( $params ), array_keys( $properties ) );
			if ( ! empty( $unknown ) ) {
				return new WP_Error( 'openwp_schema_unknown_field', sprintf( __( 'Unknown parameter(s): %s', 'openwp' ), implode( ', ', $unknown ) ) );
			}
		}

		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $params ) ) {
				return new WP_Error( 'openwp_schema_missing_field', sprintf( __( 'Missing required field: %s', 'openwp' ), $field ) );
			}
		}

		// Handle anyOf with required - at least one branch must match.
		if ( isset( $schema['anyOf'] ) && is_array( $schema['anyOf'] ) ) {
			$any_matched = false;
			foreach ( $schema['anyOf'] as $branch ) {
				if ( ! isset( $branch['required'] ) || ! is_array( $branch['required'] ) ) {
					continue;
				}
				$branch_ok = true;
				foreach ( $branch['required'] as $field ) {
					if ( ! array_key_exists( $field, $params ) ) {
						$branch_ok = false;
						break;
					}
				}
				if ( $branch_ok ) {
					$any_matched = true;
					break;
				}
			}
			if ( ! $any_matched ) {
				$options = array_map(
					static function ( $branch ) {
						return implode( ', ', $branch['required'] ?? [] );
					},
					$schema['anyOf']
				);
				return new WP_Error(
					'openwp_schema_missing_field',
					sprintf(
						/* translators: %s: list of field groups */
						__( 'At least one of these must be provided: %s', 'openwp' ),
						implode( ' | ', $options )
					)
				);
			}
		}

		$sanitized = [];
		foreach ( $properties as $field => $rules ) {
			if ( ! array_key_exists( $field, $params ) ) {
				if ( array_key_exists( 'default', $rules ) ) {
					$sanitized[ $field ] = $rules['default'];
				}
				continue;
			}

			$value = $this->sanitize_by_type( $params[ $field ], $rules );
			if ( is_wp_error( $value ) ) {
				return $value;
			}

			$sanitized[ $field ] = $value;
		}

		if ( $allow_additional ) {
			$extra = array_diff_key( $params, $properties );
			foreach ( $extra as $key => $value ) {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize a value by schema type.
	 *
	 * @param mixed               $value Raw value.
	 * @param array<string,mixed> $rules Property rules.
	 * @return mixed|WP_Error
	 */
	private function sanitize_by_type( $value, $rules ) {
		$type = isset( $rules['type'] ) ? $rules['type'] : 'string';

		if ( is_array( $type ) ) {
			foreach ( $type as $candidate ) {
				if ( 'null' === $candidate && null === $value ) {
					return null;
				}
				$attempt = $this->sanitize_by_type(
					$value,
					[
						'type' => $candidate,
					]
				);
				if ( ! is_wp_error( $attempt ) ) {
					return $attempt;
				}
			}

			return new WP_Error( 'openwp_schema_type_error', __( 'Value does not match any allowed type in action parameters.', 'openwp' ) );
		}

		switch ( $type ) {
			case 'any':
				break;
			case 'integer':
				if ( is_int( $value ) ) {
					break;
				}

				if ( is_string( $value ) && preg_match( '/^-?\d+$/', $value ) ) {
					$value = (int) $value;
					break;
				}

				return new WP_Error( 'openwp_schema_type_error', __( 'Expected integer value in action parameters.', 'openwp' ) );
			case 'number':
				if ( ! is_numeric( $value ) ) {
					return new WP_Error( 'openwp_schema_type_error', __( 'Expected numeric value in action parameters.', 'openwp' ) );
				}

				$value = (float) $value;
				break;
			case 'boolean':
				$value = rest_sanitize_boolean( $value );
				break;
			case 'array':
				if ( ! is_array( $value ) ) {
					return new WP_Error( 'openwp_schema_type_error', __( 'Expected array value in action parameters.', 'openwp' ) );
				}
				break;
			case 'object':
				if ( is_object( $value ) ) {
					$value = (array) $value;
					break;
				}
				if ( ! is_array( $value ) ) {
					return new WP_Error( 'openwp_schema_type_error', __( 'Expected object value in action parameters.', 'openwp' ) );
				}
				break;
			case 'string':
			default:
				if ( is_array( $value ) ) {
					return new WP_Error( 'openwp_schema_type_error', __( 'Expected string value in action parameters.', 'openwp' ) );
				}

				if ( is_object( $value ) && ! method_exists( $value, '__toString' ) ) {
					return new WP_Error( 'openwp_schema_type_error', __( 'Expected string value in action parameters.', 'openwp' ) );
				}

				if ( null === $value ) {
					$value = '';
				}

				$value = (string) $value;
				break;
		}

		if ( isset( $rules['enum'] ) && is_array( $rules['enum'] ) && ! in_array( $value, $rules['enum'], true ) ) {
			return new WP_Error( 'openwp_schema_enum_error', __( 'Invalid enum value in action parameters.', 'openwp' ) );
		}

		return $value;
	}
}
