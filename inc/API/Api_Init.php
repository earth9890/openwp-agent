<?php
/**
 * API initializer.
 *
 * @package OpenWP\Inc\API
 */

namespace OpenWP\Inc\API;

use OpenWP\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all REST controllers.
 */
class Api_Init {
	use Get_Instance;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'add_no_cache_headers' ], 10, 3 );
	}

	/**
	 * Prevent browsers from caching OpenWP REST responses.
	 *
	 * @param \WP_REST_Response  $response Result to send to the client.
	 * @param \WP_REST_Server    $server   Server instance.
	 * @param \WP_REST_Request   $request  Request used to generate the response.
	 * @return \WP_REST_Response
	 */
	public function add_no_cache_headers( $response, $server, $request ) {
		if ( strpos( $request->get_route(), '/openwp/v1/' ) === 0 ) {
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate' );
		}
		return $response;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$controllers = [
			'\\OpenWP\\Inc\\API\\OpenWP_Controller',
		];

		foreach ( $controllers as $class ) {
			if ( class_exists( $class ) ) {
				$controller = new $class();
				$controller->register_routes();
			}
		}
	}
}
