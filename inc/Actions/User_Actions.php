<?php
/**
 * User actions.
 *
 * @package OpenWP\Inc\Actions
 */

namespace OpenWP\Inc\Actions;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User management handlers.
 */
class User_Actions {
	/**
	 * List users.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function list_users( $params, ActionContext $context ) {
		$users = get_users(
			[
				'number' => isset( $params['per_page'] ) ? max( 1, min( 200, absint( $params['per_page'] ) ) ) : 50,
				'fields' => [ 'ID', 'user_login', 'user_email', 'display_name', 'roles' ],
			]
		);

		$items = array_map(
			static function ( $user ) {
				return [
					'ID'           => (int) $user->ID,
					'user_login'   => (string) $user->user_login,
					'user_email'   => (string) $user->user_email,
					'display_name' => (string) $user->display_name,
					'roles'        => is_array( $user->roles ) ? $user->roles : [],
				];
			},
			$users
		);

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'Users listed.', 'openwp' ),
				'data'    => [ 'items' => $items ],
			]
		);
	}

	/**
	 * Create user.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function create_user( $params, ActionContext $context ) {
		$role = self::validate_role( (string) ( $params['role'] ?? 'subscriber' ) );
		if ( is_wp_error( $role ) ) {
			return $role;
		}

		$args = [
			'user_login'   => sanitize_user( (string) ( $params['user_login'] ?? '' ), true ),
			'user_email'   => sanitize_email( (string) ( $params['user_email'] ?? '' ) ),
			'user_pass'    => ! empty( $params['user_pass'] ) ? (string) $params['user_pass'] : wp_generate_password( 24, true, true ),
			'role'         => $role,
			'display_name' => sanitize_text_field( (string) ( $params['display_name'] ?? '' ) ),
		];

		$user_id = wp_insert_user( $args );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'User created.', 'openwp' ),
				'data'    => [ 'user_id' => (int) $user_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'delete_user',
					'params'          => [ 'user_id' => (int) $user_id ],
				],
			]
		);
	}

	/**
	 * Update user.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function update_user( $params, ActionContext $context ) {
		$user_id = absint( $params['user_id'] ?? 0 );
		$user    = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'openwp_user_not_found', __( 'User not found.', 'openwp' ) );
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return new WP_Error( 'openwp_user_edit_denied', __( 'You do not have permission to edit this user.', 'openwp' ) );
		}

		$before = [
			'user_id'      => $user_id,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'role'         => isset( $user->roles[0] ) ? $user->roles[0] : 'subscriber',
		];

		$update = [ 'ID' => $user_id ];
		if ( array_key_exists( 'user_email', $params ) ) {
			$update['user_email'] = sanitize_email( (string) $params['user_email'] );
		}
		if ( array_key_exists( 'display_name', $params ) ) {
			$update['display_name'] = sanitize_text_field( (string) $params['display_name'] );
		}
		if ( array_key_exists( 'user_pass', $params ) ) {
			$update['user_pass'] = (string) $params['user_pass'];
		}
		if ( array_key_exists( 'role', $params ) ) {
			$role = self::validate_role( (string) $params['role'] );
			if ( is_wp_error( $role ) ) {
				return $role;
			}
			$update['role'] = $role;
		}

		$result = wp_update_user( $update );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'User updated.', 'openwp' ),
				'data'    => [ 'user_id' => $user_id ],
				'rollback_snapshot' => [
					'rollback_action' => 'wp_update_user',
					'params'          => $before,
				],
			]
		);
	}

	/**
	 * Delete user.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function delete_user( $params, ActionContext $context ) {
		$user = self::resolve_user_target( $params );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$user_id = (int) $user->ID;
		if ( ! current_user_can( 'delete_user', $user_id ) ) {
			return new WP_Error( 'openwp_user_delete_denied', __( 'You do not have permission to delete this user.', 'openwp' ) );
		}

		if ( get_current_user_id() === $user_id ) {
			return new WP_Error( 'openwp_delete_self_user', __( 'Cannot delete the current user.', 'openwp' ) );
		}

		$reassign_to_user_id = absint( $params['reassign_to_user_id'] ?? 0 );
		if ( $reassign_to_user_id > 0 ) {
			if ( $reassign_to_user_id === $user_id ) {
				return new WP_Error( 'openwp_user_reassign_invalid', __( 'Reassign user cannot match deleted user.', 'openwp' ) );
			}

			$reassign_target = get_user_by( 'id', $reassign_to_user_id );
			if ( ! $reassign_target ) {
				return new WP_Error( 'openwp_user_reassign_missing', __( 'Reassign target user not found.', 'openwp' ) );
			}
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$result = $reassign_to_user_id > 0 ? wp_delete_user( $user_id, $reassign_to_user_id ) : wp_delete_user( $user_id );
		if ( ! $result ) {
			return new WP_Error( 'openwp_user_delete_failed', __( 'Failed to delete user.', 'openwp' ) );
		}

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'User deleted.', 'openwp' ),
				'data'    => [
					'user_id'    => $user_id,
					'user_login' => (string) $user->user_login,
					'user_email' => (string) $user->user_email,
				],
			]
		);
	}

	/**
	 * Set user role.
	 *
	 * @param array<string,mixed> $params Params.
	 * @param ActionContext       $context Context.
	 * @return ActionResult|WP_Error
	 */
	public static function set_user_role( $params, ActionContext $context ) {
		$user_id = absint( $params['user_id'] ?? 0 );
		$role    = self::validate_role( (string) ( $params['role'] ?? '' ) );
		if ( is_wp_error( $role ) ) {
			return $role;
		}

		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new WP_Error( 'openwp_user_not_found', __( 'User not found.', 'openwp' ) );
		}

		if ( ! current_user_can( 'promote_user', $user_id ) ) {
			return new WP_Error( 'openwp_user_promote_denied', __( 'You do not have permission to change this user role.', 'openwp' ) );
		}

		$before_role = isset( $user->roles[0] ) ? $user->roles[0] : 'subscriber';
		$user->set_role( $role );

		return new ActionResult(
			[
				'success' => true,
				'message' => __( 'User role updated.', 'openwp' ),
				'data'    => [
					'user_id' => $user_id,
					'role'    => $role,
				],
				'rollback_snapshot' => [
					'rollback_action' => 'set_user_role',
					'params'          => [
						'user_id' => $user_id,
						'role'    => $before_role,
					],
				],
			]
		);
	}

	/**
	 * Validate role slug exists.
	 *
	 * @param string $role Role candidate.
	 * @return string|WP_Error
	 */
	private static function validate_role( $role ) {
		$role = sanitize_key( $role );
		if ( '' === $role ) {
			return new WP_Error( 'openwp_role_required', __( 'Role is required.', 'openwp' ) );
		}

		if ( ! get_role( $role ) ) {
			return new WP_Error( 'openwp_role_invalid', __( 'Invalid user role.', 'openwp' ) );
		}

		return $role;
	}

	/**
	 * Resolve user from flexible identifiers.
	 *
	 * @param array<string,mixed> $params Action params.
	 * @return \WP_User|WP_Error
	 */
	private static function resolve_user_target( $params ) {
		$user_id = absint( $params['user_id'] ?? 0 );
		if ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				return $user;
			}
		}

		$user_email = sanitize_email( (string) ( $params['user_email'] ?? '' ) );
		if ( '' !== $user_email ) {
			$user = get_user_by( 'email', $user_email );
			if ( $user ) {
				return $user;
			}
		}

		$user_login = sanitize_user( (string) ( $params['user_login'] ?? '' ), true );
		if ( '' !== $user_login ) {
			$user = get_user_by( 'login', $user_login );
			if ( $user ) {
				return $user;
			}
		}

		return new WP_Error( 'openwp_user_not_found', __( 'User not found. Provide a valid user_id, user_email, or user_login.', 'openwp' ) );
	}
}
