<?php
/**
 * PHPStan bootstrap file.
 *
 * Defines constants that PHPStan needs for static analysis.
 *
 * @package OpenWP
 */

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'OPENWP_FILE', '/tmp/wordpress/wp-content/plugins/openwp/openwp.php' );
define( 'OPENWP_BASE', 'openwp/openwp.php' );
define( 'OPENWP_DIR', '/tmp/wordpress/wp-content/plugins/openwp/' );
define( 'OPENWP_URL', 'https://example.com/wp-content/plugins/openwp/' );
define( 'OPENWP_VERSION', '0.1.4' );
define( 'OPENWP_DB_VERSION', '1.0.0' );
define( 'OPENWP_OPTION_SETTINGS', 'openwp_settings' );
define( 'OPENWP_OPTION_PROVIDER_KEYS', 'openwp_provider_keys' );
define( 'OPENWP_OPTION_ACTION_POLICIES', 'openwp_action_policies' );
define( 'OPENWP_OPTION_RATE_LIMITS', 'openwp_rate_limits' );
define( 'OPENWP_OPTION_DB_VERSION', 'openwp_db_version' );
define( 'OPENWP_OPTION_ONBOARDING_COMPLETED', 'openwp_onboarding_completed' );
define( 'OPENWP_OPTION_ONBOARDING_REDIRECT', 'openwp_do_redirect' );
define( 'OPENWP_OPTION_ONBOARDING_PROGRESS', 'openwp_onboarding_progress' );
define( 'OPENWP_DISABLE_AGENT', false );
define( 'OPENWP_DISABLE_NO_ACTION_RECOVERY', false );
