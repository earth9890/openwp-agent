<?php
/**
 * Plugin Name: OpenWP
 * Plugin URI: https://openwp.dev
 * Description: Plugin-native AI agent operating system for WordPress with strict action guardrails.
 * Version: 0.1.4
 * Author: OpenWP
 * License: GPLv2 or later
 * Text Domain: openwp
 * Requires at least: 6.9
 * Requires PHP: 7.4
 *
 * @package OpenWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OPENWP_FILE', __FILE__ );
define( 'OPENWP_BASE', plugin_basename( OPENWP_FILE ) );
define( 'OPENWP_DIR', plugin_dir_path( OPENWP_FILE ) );
define( 'OPENWP_URL', plugins_url( '/', OPENWP_FILE ) );
define( 'OPENWP_VERSION', '0.1.4' );

require_once OPENWP_DIR . 'constants.php';
require_once OPENWP_DIR . 'loader.php';

OpenWP\Loader::get_instance();
