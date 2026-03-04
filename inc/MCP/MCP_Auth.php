<?php
/**
 * MCP authentication and admin context bootstrap.
 *
 * @package OpenWP\Inc\MCP
 */

namespace OpenWP\Inc\MCP;

use OpenWP\Inc\Security\Encryption_Service;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles bearer token auth for MCP endpoints.
 */
class MCP_Auth {
	/**
	 * Bearer option key.
	 *
	 * @var string
	 */
	private $option_key = OPENWP_OPTION_MCP_BEARER_TOKEN;

	/**
	 * Encryption helper.
	 *
	 * @var Encryption_Service
	 */
	private $encryption;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->encryption = new Encryption_Service();
	}

	/**
	 * Check whether MCP is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return rest_sanitize_boolean( get_option( OPENWP_OPTION_MCP_ENABLED, false ) );
	}

	/**
	 * Check whether debug mode is enabled.
	 *
	 * @return bool
	 */
	public function is_debug_mode() {
		return rest_sanitize_boolean( get_option( OPENWP_OPTION_MCP_DEBUG_MODE, false ) );
	}

	/**
	 * Save bearer token encrypted at rest.
	 *
	 * @param string $token Raw token.
	 * @return bool|WP_Error
	 */
	public function save_bearer_token( $token ) {
		$token = trim( (string) $token );
		if ( '' === $token ) {
			delete_option( $this->option_key );
			return true;
		}

		$encrypted = $this->encryption->encrypt( $token );
		if ( '' === $encrypted ) {
			return new WP_Error( 'openwp_mcp_token_encrypt_failed', __( 'Failed to encrypt MCP bearer token.', 'openwp' ) );
		}

		return update_option( $this->option_key, $encrypted );
	}

	/**
	 * Return raw encrypted token.
	 *
	 * @return string
	 */
	public function get_encrypted_bearer_token() {
		$value = get_option( $this->option_key, '' );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Return decrypted bearer token.
	 *
	 * @return string
	 */
	public function get_bearer_token() {
		$stored = $this->get_encrypted_bearer_token();
		if ( '' === $stored ) {
			return '';
		}

		return $this->encryption->decrypt( $stored );
	}

	/**
	 * Authenticate MCP request and set admin context.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request object.
	 * @return true|WP_Error
	 */
	public function authenticate_request( WP_REST_Request $request ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error(
				'openwp_mcp_disabled',
				__( 'MCP is disabled in OpenWP settings.', 'openwp' ),
				[ 'status' => 403 ]
			);
		}

		$filtered = apply_filters( 'openwp_mcp_authenticate', null, $request, $this );
		if ( true === $filtered ) {
			return $this->bootstrap_admin_context();
		}
		if ( is_wp_error( $filtered ) ) {
			return $filtered;
		}

		$expected = $this->get_bearer_token();
		if ( '' === $expected ) {
			return new WP_Error(
				'openwp_mcp_token_missing',
				__( 'MCP bearer token is not configured.', 'openwp' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		$header = (string) $request->get_header( 'authorization' );
		if ( '' === $header ) {
			return new WP_Error(
				'openwp_mcp_missing_authorization',
				__( 'Authorization bearer token is required.', 'openwp' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		if ( ! preg_match( '/Bearer\s+(.+)/i', $header, $matches ) ) {
			return new WP_Error(
				'openwp_mcp_invalid_authorization',
				__( 'Authorization header must use Bearer scheme.', 'openwp' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		$provided = trim( (string) ( $matches[1] ?? '' ) );
		if ( '' === $provided || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error(
				'openwp_mcp_invalid_token',
				__( 'Invalid MCP bearer token.', 'openwp' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return $this->bootstrap_admin_context();
	}

	/**
	 * Set current user to administrator for token-authenticated calls.
	 *
	 * @return true|WP_Error
	 */
	public function bootstrap_admin_context() {
		$admins = get_users(
			[
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			]
		);

		if ( empty( $admins ) || ! is_array( $admins ) ) {
			return new WP_Error(
				'openwp_mcp_admin_missing',
				__( 'No administrator user found for MCP execution.', 'openwp' ),
				[ 'status' => 500 ]
			);
		}

		$admin = $admins[0];
		if ( ! isset( $admin->ID ) ) {
			return new WP_Error(
				'openwp_mcp_admin_invalid',
				__( 'Invalid administrator user context.', 'openwp' ),
				[ 'status' => 500 ]
			);
		}

		wp_set_current_user( (int) $admin->ID, isset( $admin->user_login ) ? (string) $admin->user_login : '' );

		return true;
	}
}
