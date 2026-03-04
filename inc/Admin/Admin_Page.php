<?php
/**
 * Admin page bootstrap.
 *
 * @package OpenWP\Inc\Admin
 */

namespace OpenWP\Inc\Admin;

use OpenWP\Inc\Onboarding\Onboarding_State;
use OpenWP\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers OpenWP admin page and assets.
 */
class Admin_Page {
	use Get_Instance;

	/**
	 * Menu hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'activation_redirect' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_openwp-activate_plugin', [ $this, 'ajax_activate_plugin' ] );
	}

	/**
	 * Redirect admins to onboarding after activation.
	 *
	 * @return void
	 */
	public function activation_redirect() {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_ajax() ) {
			return;
		}

		if ( is_multisite() || is_network_admin() ) {
			return;
		}

		if ( ! current_user_can( 'openwp_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Onboarding_State::should_redirect() ) {
			return;
		}

		Onboarding_State::set_redirect_flag( false );
		if ( Onboarding_State::is_completed() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=openwp#onboarding/welcome' ) );
		exit;
	}

	/**
	 * Register top-level admin page.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'OpenWP', 'openwp' ),
			__( 'OpenWP', 'openwp' ),
			'manage_options',
			'openwp',
			[ $this, 'render_page' ],
			$this->get_menu_icon(),
			3
		);
	}

	/**
	 * Render root node.
	 *
	 * @return void
	 */
	public function render_page() {
		echo '<div id="openwp-admin-root"></div>';
	}

	/**
	 * Enqueue build assets.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		$asset = [
			'dependencies' => [ 'wp-element', 'wp-api-fetch', 'wp-i18n' ],
			'version'      => OPENWP_VERSION,
		];

		$asset_file = OPENWP_DIR . 'build/index.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset_data = include $asset_file;
			if ( is_array( $asset_data ) ) {
				$asset = array_merge( $asset, $asset_data );
			}
		}

		wp_enqueue_style(
			'openwp-admin',
			OPENWP_URL . 'build/index.css',
			[],
			$asset['version']
		);

		$deps = is_array( $asset['dependencies'] ) ? $asset['dependencies'] : [ 'wp-element', 'wp-api-fetch', 'wp-i18n' ];

		// The 'updates' script provides wp.updates (installPlugin, etc.).
		if ( ! in_array( 'updates', $deps, true ) ) {
			$deps[] = 'updates';
		}

		wp_enqueue_script(
			'openwp-admin',
			OPENWP_URL . 'build/index.js',
			$deps,
			$asset['version'],
			true
		);

		wp_set_script_translations( 'openwp-admin', 'openwp', OPENWP_DIR . 'languages' );

		wp_localize_script(
			'openwp-admin',
			'openwpAdmin',
			[
				'root'                       => esc_url_raw( rest_url( 'openwp/v1' ) ),
				'nonce'                       => wp_create_nonce( 'wp_rest' ),
				'currentUser'                => get_current_user_id(),
				'pluginInstallationPermission' => current_user_can( 'install_plugins' ) ? '1' : '0',
				'_ajax_nonce'                => wp_create_nonce( 'updates' ),
				'onboarding'                 => [
					'completed' => Onboarding_State::is_completed(),
					'progress'  => Onboarding_State::get_progress(),
					'wizardUrl' => admin_url( 'admin.php?page=openwp#onboarding/welcome' ),
				],
				'dashboardUrl'              => admin_url( 'index.php' ),
			]
		);
	}

	/**
	 * Base64-encoded SVG icon for the admin menu.
	 *
	 * @return string Data URI for the menu icon.
	 */
	private function get_menu_icon() {
		$svg_path = OPENWP_DIR . 'src/assets/openwp-svg.svg';

		if ( ! file_exists( $svg_path ) ) {
			return 'dashicons-admin-generic';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
		$svg = file_get_contents( $svg_path );

		if ( empty( $svg ) ) {
			return 'dashicons-admin-generic';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for WP menu icon data URI.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * AJAX handler: activate a plugin by its file path.
	 *
	 * @return void
	 */
	public function ajax_activate_plugin() {
		check_ajax_referer( 'updates', '_ajax_nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( [ 'errorMessage' => __( 'You do not have permission to activate plugins.', 'openwp' ) ] );
		}

		$plugin = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		if ( empty( $plugin ) ) {
			wp_send_json_error( [ 'errorMessage' => __( 'No plugin specified.', 'openwp' ) ] );
		}

		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'errorMessage' => $result->get_error_message() ] );
		}

		wp_send_json_success();
	}
}
