<?php
/**
 * Rate limiter.
 *
 * @package OpenWP\Inc\Security
 */

namespace OpenWP\Inc\Security;

use OpenWP\Inc\Core\Settings;
use OpenWP\Inc\Database\Tables;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces action/token budgets.
 */
class Rate_Limiter {
	/**
	 * Enforce limits before execution.
	 *
	 * @param int $user_id Current user id.
	 * @return true|WP_Error
	 */
	public function enforce( $user_id ) {
		$settings = Settings::get();
		$today    = gmdate( 'Y-m-d' );
		global $wpdb;

		$per_user = $this->get_usage( $today, $user_id );
		$site     = $this->get_usage( $today, 0 );

		if ( $per_user['actions_count'] >= (int) $settings['max_actions_user_day'] ) {
			return new WP_Error( 'openwp_user_action_limit', __( 'Daily action limit reached for your user.', 'openwp' ) );
		}

		if ( $site['actions_count'] >= (int) $settings['max_actions_site_day'] ) {
			return new WP_Error( 'openwp_site_action_limit', __( 'Daily site action limit reached.', 'openwp' ) );
		}

		$cooldown = absint( $settings['min_seconds_between_runs'] );
		if ( $cooldown > 0 && ! empty( $per_user['last_action_at'] ) ) {
			$last_ts = strtotime( (string) $per_user['last_action_at'] );
			if ( $last_ts && ( time() - $last_ts ) < $cooldown ) {
				return new WP_Error( 'openwp_cooldown', __( 'Please wait a few seconds before running another command.', 'openwp' ) );
			}
		}

		return true;
	}

	/**
	 * Record action and tokens.
	 *
	 * @param int $user_id User id.
	 * @param int $tokens_in Prompt tokens.
	 * @param int $tokens_out Completion tokens.
	 * @return void
	 */
	public function record( $user_id, $tokens_in = 0, $tokens_out = 0 ) {
		$today = gmdate( 'Y-m-d' );
		$this->upsert_usage( $today, $user_id, $tokens_in, $tokens_out );
		$this->upsert_usage( $today, 0, $tokens_in, $tokens_out );
	}

	/**
	 * Fetch usage row.
	 *
	 * @param string $day Day.
	 * @param int    $user_id User ID, site aggregate is 0.
	 * @return array<string,mixed>
	 */
	private function get_usage( $day, $user_id ) {
		global $wpdb;
		if ( ! Tables::exists( 'usage_daily' ) ) {
			return [
				'actions_count' => 0,
				'tokens_in'     => 0,
				'tokens_out'    => 0,
				'last_action_at' => null,
			];
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT usage_day, user_id, actions_count, tokens_in, tokens_out, last_action_at FROM ' . Tables::get( 'usage_daily' ) . ' WHERE usage_day = %s AND user_id = %d',
				$day,
				absint( $user_id )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $row ) ) {
			return [
				'actions_count' => 0,
				'tokens_in'     => 0,
				'tokens_out'    => 0,
				'last_action_at' => null,
			];
		}

		return $row;
	}

	/**
	 * Upsert usage counters.
	 *
	 * @param string $day Day.
	 * @param int    $user_id User ID.
	 * @param int    $tokens_in Input tokens.
	 * @param int    $tokens_out Output tokens.
	 * @return void
	 */
	private function upsert_usage( $day, $user_id, $tokens_in, $tokens_out ) {
		global $wpdb;
		if ( ! Tables::exists( 'usage_daily' ) ) {
			return;
		}
		$table = Tables::get( 'usage_daily' );

		$exists = $this->get_usage( $day, $user_id );

		if ( 0 === (int) $exists['actions_count'] && empty( $exists['last_action_at'] ) ) {
			$wpdb->insert(
				$table,
				[
					'usage_day'      => $day,
					'user_id'        => absint( $user_id ),
					'actions_count'  => 1,
					'tokens_in'      => max( 0, (int) $tokens_in ),
					'tokens_out'     => max( 0, (int) $tokens_out ),
					'last_action_at' => current_time( 'mysql', true ),
					'updated_at'     => current_time( 'mysql', true ),
				],
				[ '%s', '%d', '%d', '%d', '%d', '%s', '%s' ]
			);
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET actions_count = actions_count + 1, tokens_in = tokens_in + %d, tokens_out = tokens_out + %d, last_action_at = %s, updated_at = %s WHERE usage_day = %s AND user_id = %d",
				max( 0, (int) $tokens_in ),
				max( 0, (int) $tokens_out ),
				current_time( 'mysql', true ),
				current_time( 'mysql', true ),
				$day,
				absint( $user_id )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
