<?php
/**
 * OpenWP constants.
 *
 * @package OpenWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OPENWP_DB_VERSION' ) ) {
	define( 'OPENWP_DB_VERSION', '1.0.0' );
}

if ( ! defined( 'OPENWP_OPTION_SETTINGS' ) ) {
	define( 'OPENWP_OPTION_SETTINGS', 'openwp_settings' );
}

if ( ! defined( 'OPENWP_OPTION_PROVIDER_KEYS' ) ) {
	define( 'OPENWP_OPTION_PROVIDER_KEYS', 'openwp_provider_keys' );
}

if ( ! defined( 'OPENWP_OPTION_ACTION_POLICIES' ) ) {
	define( 'OPENWP_OPTION_ACTION_POLICIES', 'openwp_action_policies' );
}

if ( ! defined( 'OPENWP_OPTION_RATE_LIMITS' ) ) {
	define( 'OPENWP_OPTION_RATE_LIMITS', 'openwp_rate_limits' );
}

if ( ! defined( 'OPENWP_OPTION_DB_VERSION' ) ) {
	define( 'OPENWP_OPTION_DB_VERSION', 'openwp_db_version' );
}

if ( ! defined( 'OPENWP_OPTION_ONBOARDING_COMPLETED' ) ) {
	define( 'OPENWP_OPTION_ONBOARDING_COMPLETED', 'openwp_onboarding_completed' );
}

if ( ! defined( 'OPENWP_OPTION_ONBOARDING_REDIRECT' ) ) {
	define( 'OPENWP_OPTION_ONBOARDING_REDIRECT', 'openwp_do_redirect' );
}

if ( ! defined( 'OPENWP_OPTION_ONBOARDING_PROGRESS' ) ) {
	define( 'OPENWP_OPTION_ONBOARDING_PROGRESS', 'openwp_onboarding_progress' );
}

if ( ! defined( 'OPENWP_OPTION_MCP_ENABLED' ) ) {
	define( 'OPENWP_OPTION_MCP_ENABLED', 'openwp_mcp_enabled' );
}

if ( ! defined( 'OPENWP_OPTION_MCP_BEARER_TOKEN' ) ) {
	define( 'OPENWP_OPTION_MCP_BEARER_TOKEN', 'openwp_mcp_bearer_token' );
}

if ( ! defined( 'OPENWP_OPTION_MCP_DEBUG_MODE' ) ) {
	define( 'OPENWP_OPTION_MCP_DEBUG_MODE', 'openwp_mcp_debug_mode' );
}

if ( ! defined( 'OPENWP_OPTION_MCP_MODULES' ) ) {
	define( 'OPENWP_OPTION_MCP_MODULES', 'openwp_mcp_modules' );
}

if ( ! defined( 'OPENWP_DISABLE_AGENT' ) ) {
	define( 'OPENWP_DISABLE_AGENT', false );
}

if ( ! defined( 'OPENWP_DISABLE_NO_ACTION_RECOVERY' ) ) {
	define( 'OPENWP_DISABLE_NO_ACTION_RECOVERY', false );
}
