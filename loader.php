<?php
/**
 * Plugin loader.
 *
 * @package OpenWP
 */

namespace OpenWP;

use OpenWP\Inc\Abilities\Ability_Bootstrap;
use OpenWP\Inc\Admin\Admin_Page;
use OpenWP\Inc\Admin\Editor_Block;
use OpenWP\Inc\API\Api_Init;
use OpenWP\Inc\Core\Action_Bootstrap;
use OpenWP\Inc\Database\Migrations;
use OpenWP\Inc\Frontend\Sitewide_Chatbot;
use OpenWP\Inc\Logs\Log_Repository;
use OpenWP\Inc\MCP\MCP_Server;
use OpenWP\Inc\Onboarding\Onboarding_State;
use OpenWP\Inc\Permissions\Capability_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin loader.
 */
class Loader {
	/**
	 * Singleton instance.
	 *
	 * @var Loader|null
	 */
	private static $instance;

	/**
	 * Get singleton.
	 *
	 * @return Loader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		spl_autoload_register( [ $this, 'autoload' ] );

		register_activation_hook( OPENWP_FILE, [ $this, 'activation' ] );
		register_deactivation_hook( OPENWP_FILE, [ $this, 'deactivation' ] );

		add_action( 'plugins_loaded', [ $this, 'boot' ] );
		add_action( 'init', [ $this, 'load_textdomain' ] );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public function activation() {
		Capability_Manager::grant_capabilities();
		Migrations::activate();
		Onboarding_State::initialize_defaults();
		Onboarding_State::set_redirect_flag( true );
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivation() {
		wp_clear_scheduled_hook( 'openwp_cleanup_backups' );
	}

	/**
	 * Boot plugin components.
	 *
	 * @return void
	 */
	public function boot() {
		Capability_Manager::grant_capabilities();
		Migrations::maybe_upgrade();
		Onboarding_State::initialize_defaults();

		if ( is_multisite() ) {
			add_action( 'admin_notices', [ $this, 'multisite_notice' ] );
			return;
		}

		Action_Bootstrap::get_instance();
		Ability_Bootstrap::get_instance();
		Api_Init::get_instance();
		MCP_Server::get_instance();
		Admin_Page::get_instance();
		Editor_Block::get_instance();
		Sitewide_Chatbot::get_instance();

		add_action( 'openwp_cleanup_backups', [ '\\OpenWP\\Inc\\Backup\\Backup_Service', 'cleanup_expired_backups' ] );
		add_action( 'openwp_cleanup_backups', [ Log_Repository::class, 'cleanup_retention' ] );
		add_action( 'openwp_cleanup_backups', [ '\\OpenWP\\Inc\\Chatbot\\Attachment_Service', 'cleanup_expired_files' ] );

		if ( ! wp_next_scheduled( 'openwp_cleanup_backups' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'openwp_cleanup_backups' );
		}
	}

	/**
	 * Display multisite support notice.
	 *
	 * @return void
	 */
	public function multisite_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'OpenWP v1 currently supports single-site installs only.', 'openwp' ) . '</p></div>';
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'openwp', false, dirname( OPENWP_BASE ) . '/languages' );
	}

	/**
	 * PSR-4 style autoloader for OpenWP namespace.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, 'OpenWP\\' ) ) {
			return;
		}

		$relative = str_replace( 'OpenWP\\', '', $class );
		$relative = preg_replace( '/^Inc\\\\/', 'inc\\\\', $relative );

		if ( ! is_string( $relative ) ) {
			return;
		}

		$path = OPENWP_DIR . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
