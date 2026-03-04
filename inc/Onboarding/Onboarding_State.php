<?php
/**
 * Onboarding state helper.
 *
 * @package OpenWP\Inc\Onboarding
 */

namespace OpenWP\Inc\Onboarding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles onboarding completion, progress, and redirect flags.
 */
class Onboarding_State {
	/**
	 * Progress keys used by the strict wizard flow.
	 *
	 * @return string[]
	 */
	public static function progress_keys() {
		return [
			'provider_saved',
			'connection_test_passed',
			'guardrail_preset_saved',
			'first_command_passed',
		];
	}

	/**
	 * Default onboarding progress payload.
	 *
	 * @return array<string,bool>
	 */
	public static function default_progress() {
		$defaults = [];
		foreach ( self::progress_keys() as $key ) {
			$defaults[ $key ] = false;
		}

		return $defaults;
	}

	/**
	 * Ensure onboarding options exist.
	 *
	 * @return void
	 */
	public static function initialize_defaults() {
		if ( false === get_option( OPENWP_OPTION_ONBOARDING_COMPLETED, false ) ) {
			update_option( OPENWP_OPTION_ONBOARDING_COMPLETED, 'no' );
		}

		if ( false === get_option( OPENWP_OPTION_ONBOARDING_REDIRECT, false ) ) {
			update_option( OPENWP_OPTION_ONBOARDING_REDIRECT, false );
		}

		if ( false === get_option( OPENWP_OPTION_ONBOARDING_PROGRESS, false ) ) {
			update_option( OPENWP_OPTION_ONBOARDING_PROGRESS, self::default_progress() );
		}
	}

	/**
	 * Check if onboarding is complete.
	 *
	 * @return bool
	 */
	public static function is_completed() {
		return 'yes' === get_option( OPENWP_OPTION_ONBOARDING_COMPLETED, 'no' );
	}

	/**
	 * Set onboarding completion status.
	 *
	 * @param bool $completed True to mark complete.
	 * @return bool
	 */
	public static function set_completed( $completed ) {
		return update_option( OPENWP_OPTION_ONBOARDING_COMPLETED, $completed ? 'yes' : 'no' );
	}

	/**
	 * Get onboarding redirect flag.
	 *
	 * @return bool
	 */
	public static function should_redirect() {
		return (bool) get_option( OPENWP_OPTION_ONBOARDING_REDIRECT, false );
	}

	/**
	 * Set onboarding redirect flag.
	 *
	 * @param bool $enabled True to enable redirect.
	 * @return bool
	 */
	public static function set_redirect_flag( $enabled ) {
		return update_option( OPENWP_OPTION_ONBOARDING_REDIRECT, (bool) $enabled );
	}

	/**
	 * Get progress state.
	 *
	 * @return array<string,bool>
	 */
	public static function get_progress() {
		$stored = get_option( OPENWP_OPTION_ONBOARDING_PROGRESS, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return self::sanitize_progress( wp_parse_args( $stored, self::default_progress() ) );
	}

	/**
	 * Merge and persist progress patch.
	 *
	 * @param array<string,mixed> $patch Progress patch.
	 * @return array<string,bool>
	 */
	public static function update_progress( $patch ) {
		$current = self::get_progress();
		$patch   = self::sanitize_progress( is_array( $patch ) ? $patch : [] );
		$next    = array_merge( $current, $patch );

		update_option( OPENWP_OPTION_ONBOARDING_PROGRESS, $next );

		return $next;
	}

	/**
	 * Validate strict completion criteria.
	 *
	 * @param array<string,bool> $progress Progress map.
	 * @return bool
	 */
	public static function can_complete_strict( $progress ) {
		if ( ! is_array( $progress ) ) {
			return false;
		}

		foreach ( self::progress_keys() as $key ) {
			if ( empty( $progress[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Return missing strict progress keys.
	 *
	 * @param array<string,bool> $progress Progress map.
	 * @return string[]
	 */
	public static function missing_required_keys( $progress ) {
		$missing = [];
		foreach ( self::progress_keys() as $key ) {
			if ( empty( $progress[ $key ] ) ) {
				$missing[] = $key;
			}
		}

		return $missing;
	}

	/**
	 * Sanitize incoming progress map.
	 *
	 * @param array<string,mixed> $progress Raw map.
	 * @return array<string,bool>
	 */
	private static function sanitize_progress( $progress ) {
		$sanitized = self::default_progress();
		if ( ! is_array( $progress ) ) {
			return $sanitized;
		}

		foreach ( self::progress_keys() as $key ) {
			if ( array_key_exists( $key, $progress ) ) {
				$sanitized[ $key ] = rest_sanitize_boolean( $progress[ $key ] );
			}
		}

		return $sanitized;
	}
}
