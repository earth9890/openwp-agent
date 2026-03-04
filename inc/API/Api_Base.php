<?php
/**
 * API base class.
 *
 * @package OpenWP\Inc\API
 */

namespace OpenWP\Inc\API;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared REST helpers.
 */
abstract class Api_Base extends WP_REST_Controller {
	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'openwp/v1';

	/**
	 * Validate REST nonce and required capability.
	 *
	 * @param WP_REST_Request<array<string,mixed>> $request Request object.
	 * @param string                               $capability Capability.
	 * @return true|WP_Error
	 */
	protected function validate_permission( $request, $capability ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'openwp_rest_nonce_invalid',
				__( 'Invalid nonce.', 'openwp' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		$has_capability = current_user_can( $capability ) || current_user_can( 'manage_options' );
		if ( ! is_user_logged_in() || ! $has_capability ) {
			return new WP_Error(
				'openwp_rest_capability_denied',
				__( 'You do not have permission for this endpoint.', 'openwp' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}
}
