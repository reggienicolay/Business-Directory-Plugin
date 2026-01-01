<?php
/**
 * List Collaborators
 *
 * Handles collaborative list functionality including invite links,
 * direct adds, join requests, and permissions.
 *
 * @package BusinessDirectory
 * @subpackage Lists
 */


namespace BD\Lists;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\Gamification\ActivityTracker;
use BD\Gamification\BadgeSystem;

class ListCollaborators {

	/**
	 * Collaborator roles and their capabilities.
	 */
	const ROLES = array(
		'contributor' => array(
			'can_add_items'      => true,
			'can_remove_items'   => 'own', // 'own', 'all', or false
			'can_edit_notes'     => 'own',
			'can_edit_list'      => false,
			'can_manage_collabs' => false,
		),
		'editor'      => array(
			'can_add_items'      => true,
			'can_remove_items'   => 'all',
			'can_edit_notes'     => 'all',
			'can_edit_list'      => false,
			'can_manage_collabs' => false,
		),
	);

	/**
	 * Limits to prevent abuse.
	 */
	const MAX_COLLABORATORS_PER_LIST = 10;
	const MAX_PENDING_REQUESTS       = 20;
	const MAX_LISTS_USER_CAN_COLLAB  = 25;
	const INVITE_TOKEN_LENGTH        = 32;

	/**
	 * Get the collaborators table name.
	 *
	 * @return string Table name with prefix.
	 */
	private static function get_table() {
		global $wpdb;
		return $wpdb->prefix . 'bd_list_collaborators';
	}

	/**
	 * Get the lists table name.
	 *
	 * @return string Table name with prefix.
	 */
	private static function get_lists_table() {
		global $wpdb;
		return $wpdb->prefix . 'bd_lists';
	}

	// =========================================================================
	// INVITE LINK MANAGEMENT
	// =========================================================================

	/**
	 * Generate or get existing invite token for a list.
	 *
	 * @param int $list_id List ID.
	 * @param int $owner_id Owner user ID (for permission check).
	 * @return string|false Invite token or false on failure.
	 */
	public static function get_invite_token( $list_id, $owner_id ) {
		global $wpdb;
		$lists_table = self::get_lists_table();

		// Verify ownership.
		$list = ListManager::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $owner_id ) {
			return false;
		}

		// Check if token exists.
		if ( ! empty( $list['invite_token'] ) ) {
			return $list['invite_token'];
		}

		// Generate new token.
		$token = self::generate_token();

		$wpdb->update(
			$lists_table,
			array(
				'invite_token' => $token,
				'invite_mode'  => 'approval', // Default to approval mode.
			),
			array( 'id' => $list_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $token;
	}

	/**
	 * Regenerate invite token (invalidates old link).
	 *
	 * @param int $list_id  List ID.
	 * @param int $owner_id Owner user ID.
	 * @return string|false New token or false on failure.
	 */
	public static function regenerate_invite_token( $list_id, $owner_id ) {
		global $wpdb;
		$lists_table = self::get_lists_table();

		// Verify ownership.
		$list = ListManager::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $owner_id ) {
			return false;
		}

		$token = self::generate_token();

		$wpdb->update(
			$lists_table,
			array( 'invite_token' => $token ),
			array( 'id' => $list_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $token;
	}

	/**
	 * Update invite mode (auto-accept or require approval).
	 *
	 * @param int    $list_id  List ID.
	 * @param int    $owner_id Owner user ID.
	 * @param string $mode     'auto' or 'approval' or 'disabled'.
	 * @return bool Success.
	 */
	public static function set_invite_mode( $list_id, $owner_id, $mode ) {
		global $wpdb;
		$lists_table = self::get_lists_table();

		// Verify ownership.
		$list = ListManager::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $owner_id ) {
			return false;
		}

		// Validate mode.
		if ( ! in_array( $mode, array( 'auto', 'approval', 'disabled' ), true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$lists_table,
			array( 'invite_mode' => $mode ),
			array( 'id' => $list_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get invite URL for a list.
	 *
	 * @param int $list_id  List ID.
	 * @param int $owner_id Owner user ID.
	 * @return string|false Invite URL or false.
	 */
	public static function get_invite_url( $list_id, $owner_id ) {
		$token = self::get_invite_token( $list_id, $owner_id );
		if ( ! $token ) {
			return false;
		}

		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return false;
		}

		// Use ListManager to get the proper list URL, then add join token.
		$list_url = ListManager::get_list_url( $list );

		return add_query_arg( 'join', $token, $list_url );
	}

	/**
	 * Validate an invite token.
	 *
	 * @param string $slug  List slug.
	 * @param string $token Invite token.
	 * @return array|false List data if valid, false otherwise.
	 */
	public static function validate_invite_token( $slug, $token ) {
		global $wpdb;
		$lists_table = self::get_lists_table();

		$list = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $lists_table WHERE slug = %s AND invite_token = %s AND invite_mode != 'disabled'",
				$slug,
				$token
			),
			ARRAY_A
		);

		return $list ?: false;
	}

	/**
	 * Generate a secure random token.
	 *
	 * @return string Token.
	 */
	private static function generate_token() {
		return bin2hex( random_bytes( self::INVITE_TOKEN_LENGTH / 2 ) );
	}

	// =========================================================================
	// COLLABORATOR MANAGEMENT
	// =========================================================================

	/**
	 * Add a collaborator directly (by owner).
	 *
	 * @param int    $list_id   List ID.
	 * @param int    $owner_id  Owner user ID.
	 * @param int    $user_id   User to add.
	 * @param string $role      Role: 'contributor' or 'editor'.
	 * @return int|false Collaborator record ID or false.
	 */
	public static function add_collaborator( $list_id, $owner_id, $user_id, $role = 'contributor' ) {
		global $wpdb;
		$table = self::get_table();

		// Verify ownership.
		$list = ListManager::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $owner_id ) {
			return false;
		}

		// Can't add yourself.
		if ( (int) $owner_id === (int) $user_id ) {
			return false;
		}

		// Check user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Check limits.
		if ( ! self::check_limits( $list_id, $user_id ) ) {
			return false;
		}

		// Validate role.
		if ( ! isset( self::ROLES[ $role ] ) ) {
			$role = 'contributor';
		}

		// Check if already a collaborator.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE list_id = %d AND user_id = %d",
				$list_id,
				$user_id
			)
		);

		if ( $existing ) {
			// Update existing record to active.
			$wpdb->update(
				$table,
				array(
					'role'   => $role,
					'status' => 'active',
				),
				array( 'id' => $existing ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return $existing;
		}

		// Insert new collaborator.
		$result = $wpdb->insert(
			$table,
			array(
				'list_id'  => $list_id,
				'user_id'  => $user_id,
				'role'     => $role,
				'status'   => 'active',
				'added_by' => $owner_id,
				'added_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( $result ) {
			// Create notification for the added user.
			self::create_notification( $user_id, 'added_to_list', $list_id, $owner_id );

			// Track for gamification.
			if ( class_exists( 'BD\Gamification\ActivityTracker' ) ) {
				ActivityTracker::track( $user_id, 'joined_collaborative_list', $list_id );
			}

			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Request to join a list (via invite link).
	 *
	 * @param int    $list_id List ID.
	 * @param int    $user_id User requesting to join.
	 * @param string $token   Invite token.
	 * @return array Result with status.
	 */
	public static function request_to_join( $list_id, $user_id, $token ) {
		global $wpdb;
		$table       = self::get_table();
		$lists_table = self::get_lists_table();

		// Get list and validate token.
		$list = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $lists_table WHERE id = %d AND invite_token = %s",
				$list_id,
				$token
			),
			ARRAY_A
		);

		if ( ! $list ) {
			return array(
				'success' => false,
				'error'   => 'invalid_token',
				'message' => 'Invalid or expired invite link.',
			);
		}

		// Check if invites are disabled.
		if ( 'disabled' === $list['invite_mode'] ) {
			return array(
				'success' => false,
				'error'   => 'invites_disabled',
				'message' => 'This list is not accepting new collaborators.',
			);
		}

		// Can't join your own list.
		if ( (int) $list['user_id'] === (int) $user_id ) {
			return array(
				'success' => false,
				'error'   => 'own_list',
				'message' => 'You already own this list.',
			);
		}

		// Check limits.
		if ( ! self::check_limits( $list_id, $user_id ) ) {
			return array(
				'success' => false,
				'error'   => 'limit_reached',
				'message' => 'This list has reached its collaborator limit.',
			);
		}

		// Check if already a collaborator.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE list_id = %d AND user_id = %d",
				$list_id,
				$user_id
			),
			ARRAY_A
		);

		if ( $existing ) {
			if ( 'active' === $existing['status'] ) {
				return array(
					'success' => false,
					'error'   => 'already_member',
					'message' => 'You are already a collaborator on this list.',
				);
			}
			if ( 'pending' === $existing['status'] ) {
				return array(
					'success' => false,
					'error'   => 'already_pending',
					'message' => 'Your request is pending approval.',
				);
			}
		}

		// Determine status based on invite mode.
		$status = ( 'auto' === $list['invite_mode'] ) ? 'active' : 'pending';

		if ( $existing ) {
			// Update existing record.
			$wpdb->update(
				$table,
				array( 'status' => $status ),
				array( 'id' => $existing['id'] ),
				array( '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new record.
			$wpdb->insert(
				$table,
				array(
					'list_id'  => $list_id,
					'user_id'  => $user_id,
					'role'     => 'contributor',
					'status'   => $status,
					'added_by' => 0, // 0 indicates joined via link.
					'added_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s' )
			);
		}

		if ( 'active' === $status ) {
			// Auto-accepted - notify owner.
			self::create_notification( $list['user_id'], 'collaborator_joined', $list_id, $user_id );

			// Track for gamification.
			if ( class_exists( 'BD\Gamification\ActivityTracker' ) ) {
				ActivityTracker::track( $user_id, 'joined_collaborative_list', $list_id );
			}

			return array(
				'success' => true,
				'status'  => 'joined',
				'message' => 'You are now a collaborator on this list!',
			);
		} else {
			// Pending approval - notify owner.
			self::create_notification( $list['user_id'], 'join_request', $list_id, $user_id );

			return array(
				'success' => true,
				'status'  => 'pending',
				'message' => 'Your request has been sent to the list owner.',
			);
		}
	}

	/**
	 * Approve a pending join request.
	 *
	 * @param int $list_id  List ID.
	 * @param int $owner_id Owner user ID.
	 * @param int $user_id  User to approve.
	 * @return bool Success.
	 */
	public static function approve_request( $list_id, $owner_id, $user_id ) {
		global $wpdb;
		$table = self::get_table();

		// Verify ownership.
		$list = ListManager::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $owner_id ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			array( 'status' => 'active' ),
			array(
				'list_id' => $list_id,
				'user_id' => $user_id,
				'status'  => 'pending',
			),
			array( '%s' ),
			array( '%d', '%d', '%s' )
		);

		if ( $result ) {
			// Notify the user.
			self::create_notification( $user_id, 'request_approved', $list_id, $owner_id );

			// Track for gamification.
			if ( class_exists( 'BD\Gamification\ActivityTracker' ) ) {
				ActivityTracker::track( $user_id, 'joined_collaborative_list', $list_id );
			}

			return true;
		}

		return false;
	}

	/**
	 * Deny a pending join request.
	 *
	 * @param int $list_id  List ID.
	 * @param int $owner_id Owner user ID.
	 * @param int $user_id  User to deny.
	 * @return bool Success.
	 */
	public static function deny_request( $list_id, $owner_id, $user_id ) {
		global $wpdb;
		$table = self::get_table();

		// Verify ownership.
		$list = ListManager::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $owner_id ) {
			return false;
		}

		$result = $wpdb->delete(
			$table,
			array(
				'list_id' => $list_id,
				'user_id' => $user_id,
				'status'  => 'pending',
			),
			array( '%d', '%d', '%s' )
		);

		// Silent denial - no notification.
		return false !== $result;
	}

	/**
	 * Remove a collaborator.
	 *
	 * @param int $list_id  List ID.
	 * @param int $owner_id Owner user ID.
	 * @param int $user_id  User to remove.
	 * @return bool Success.
	 */
	public static function remove_collaborator( $list_id, $owner_id, $user_id ) {
		global $wpdb;
		$table = self::get_table();

		// Verify ownership.
		$list = ListManager::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $owner_id ) {
			return false;
		}

		$result = $wpdb->delete(
			$table,
			array(
				'list_id' => $list_id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Leave a list (collaborator removes themselves).
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User leaving.
	 * @return bool Success.
	 */
	public static function leave_list( $list_id, $user_id ) {
		global $wpdb;
		$table = self::get_table();

		$result = $wpdb->delete(
			$table,
			array(
				'list_id' => $list_id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	// =========================================================================
	// QUERY METHODS
	// =========================================================================

	/**
	 * Get collaborators for a list.
	 *
	 * @param int    $list_id List ID.
	 * @param string $status  Filter by status: 'active', 'pending', or null for all.
	 * @return array Collaborators with user data.
	 */
	public static function get_collaborators( $list_id, $status = null ) {
		global $wpdb;
		$table = self::get_table();

		$where = $wpdb->prepare( 'list_id = %d', $list_id );
		if ( $status ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		$collaborators = $wpdb->get_results(
			"SELECT c.*, u.display_name, u.user_email 
			FROM $table c
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			WHERE $where
			ORDER BY c.added_at DESC",
			ARRAY_A
		);

		// Add avatar URLs.
		foreach ( $collaborators as &$collab ) {
			$collab['avatar_url'] = get_avatar_url( $collab['user_id'], array( 'size' => 40 ) );
		}

		return $collaborators;
	}

	/**
	 * Get pending requests for a list.
	 *
	 * @param int $list_id List ID.
	 * @return array Pending collaborators.
	 */
	public static function get_pending_requests( $list_id ) {
		return self::get_collaborators( $list_id, 'pending' );
	}

	/**
	 * Get active collaborators for a list.
	 *
	 * @param int $list_id List ID.
	 * @return array Active collaborators.
	 */
	public static function get_active_collaborators( $list_id ) {
		return self::get_collaborators( $list_id, 'active' );
	}

	/**
	 * Get lists a user collaborates on.
	 *
	 * @param int $user_id User ID.
	 * @return array Lists.
	 */
	public static function get_user_collaborative_lists( $user_id ) {
		global $wpdb;
		$table       = self::get_table();
		$lists_table = self::get_lists_table();

		$lists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, c.role, c.added_at as joined_at, u.display_name as owner_name
				FROM $table c
				INNER JOIN $lists_table l ON c.list_id = l.id
				LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
				WHERE c.user_id = %d AND c.status = 'active'
				ORDER BY c.added_at DESC",
				$user_id
			),
			ARRAY_A
		);

		// Add item counts.
		foreach ( $lists as &$list ) {
			$list['item_count'] = ListManager::get_list_item_count( $list['id'] );
		}

		return $lists;
	}

	/**
	 * Get user's pending invitations.
	 *
	 * @param int $user_id User ID.
	 * @return array Pending invitations.
	 */
	public static function get_user_pending_invitations( $user_id ) {
		global $wpdb;
		$table       = self::get_table();
		$lists_table = self::get_lists_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, c.added_at as invited_at, u.display_name as owner_name
				FROM $table c
				INNER JOIN $lists_table l ON c.list_id = l.id
				LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
				WHERE c.user_id = %d AND c.status = 'pending' AND c.added_by > 0
				ORDER BY c.added_at DESC",
				$user_id
			),
			ARRAY_A
		);
	}

	/**
	 * Check if user can edit a list (owner or collaborator).
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User ID.
	 * @return array|false Permissions array or false.
	 */
	public static function get_user_permissions( $list_id, $user_id ) {
		global $wpdb;
		$table       = self::get_table();
		$lists_table = self::get_lists_table();

		// Check if owner.
		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return false;
		}

		if ( (int) $list['user_id'] === (int) $user_id ) {
			// Owner has all permissions.
			return array(
				'role'               => 'owner',
				'can_add_items'      => true,
				'can_remove_items'   => 'all',
				'can_edit_notes'     => 'all',
				'can_edit_list'      => true,
				'can_manage_collabs' => true,
			);
		}

		// Check if collaborator.
		$collab = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE list_id = %d AND user_id = %d AND status = 'active'",
				$list_id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $collab ) {
			return false;
		}

		$role          = $collab['role'];
		$perms         = self::ROLES[ $role ] ?? self::ROLES['contributor'];
		$perms['role'] = $role;

		return $perms;
	}

	/**
	 * Check if user can add items to a list.
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User ID.
	 * @return bool Can add items.
	 */
	public static function can_add_items( $list_id, $user_id ) {
		$perms = self::get_user_permissions( $list_id, $user_id );
		return $perms && ! empty( $perms['can_add_items'] );
	}

	/**
	 * Check if user can remove a specific item.
	 *
	 * @param int $list_id     List ID.
	 * @param int $user_id     User ID.
	 * @param int $added_by_id User who added the item.
	 * @return bool Can remove item.
	 */
	public static function can_remove_item( $list_id, $user_id, $added_by_id ) {
		$perms = self::get_user_permissions( $list_id, $user_id );
		if ( ! $perms ) {
			return false;
		}

		$remove_perm = $perms['can_remove_items'];

		if ( 'all' === $remove_perm ) {
			return true;
		}
		if ( 'own' === $remove_perm ) {
			return (int) $user_id === (int) $added_by_id;
		}

		return false;
	}

	// =========================================================================
	// LIMITS & VALIDATION
	// =========================================================================

	/**
	 * Check if adding a collaborator would exceed limits.
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User being added.
	 * @return bool Within limits.
	 */
	private static function check_limits( $list_id, $user_id ) {
		global $wpdb;
		$table = self::get_table();

		// Check list collaborator limit.
		$list_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE list_id = %d AND status = 'active'",
				$list_id
			)
		);

		if ( $list_count >= self::MAX_COLLABORATORS_PER_LIST ) {
			return false;
		}

		// Check pending limit.
		$pending_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE list_id = %d AND status = 'pending'",
				$list_id
			)
		);

		if ( $pending_count >= self::MAX_PENDING_REQUESTS ) {
			return false;
		}

		// Check user's collaboration limit.
		$user_collab_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE user_id = %d AND status = 'active'",
				$user_id
			)
		);

		if ( $user_collab_count >= self::MAX_LISTS_USER_CAN_COLLAB ) {
			return false;
		}

		return true;
	}

	/**
	 * Get collaborator count for a list.
	 *
	 * @param int $list_id List ID.
	 * @return int Count.
	 */
	public static function get_collaborator_count( $list_id ) {
		global $wpdb;
		$table = self::get_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE list_id = %d AND status = 'active'",
				$list_id
			)
		);
	}

	/**
	 * Get pending request count for a list.
	 *
	 * @param int $list_id List ID.
	 * @return int Count.
	 */
	public static function get_pending_count( $list_id ) {
		global $wpdb;
		$table = self::get_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE list_id = %d AND status = 'pending'",
				$list_id
			)
		);
	}

	// =========================================================================
	// NOTIFICATIONS
	// =========================================================================

	/**
	 * Create an in-app notification.
	 *
	 * Stores in user meta for simple retrieval.
	 *
	 * @param int    $user_id   User to notify.
	 * @param string $type      Notification type.
	 * @param int    $list_id   Related list ID.
	 * @param int    $actor_id  User who triggered the notification.
	 */
	private static function create_notification( $user_id, $type, $list_id, $actor_id ) {
		$notifications = get_user_meta( $user_id, 'bd_list_notifications', true );
		if ( ! is_array( $notifications ) ) {
			$notifications = array();
		}

		$list  = ListManager::get_list( $list_id );
		$actor = get_userdata( $actor_id );

		$notification = array(
			'id'         => uniqid( 'ln_' ),
			'type'       => $type,
			'list_id'    => $list_id,
			'list_title' => $list ? $list['title'] : '',
			'actor_id'   => $actor_id,
			'actor_name' => $actor ? $actor->display_name : '',
			'created_at' => current_time( 'mysql' ),
			'read'       => false,
		);

		// Add to beginning of array.
		array_unshift( $notifications, $notification );

		// Keep only last 50 notifications.
		$notifications = array_slice( $notifications, 0, 50 );

		update_user_meta( $user_id, 'bd_list_notifications', $notifications );
	}

	/**
	 * Get user's notifications.
	 *
	 * @param int  $user_id    User ID.
	 * @param bool $unread_only Only unread.
	 * @return array Notifications.
	 */
	public static function get_notifications( $user_id, $unread_only = false ) {
		$notifications = get_user_meta( $user_id, 'bd_list_notifications', true );
		if ( ! is_array( $notifications ) ) {
			return array();
		}

		if ( $unread_only ) {
			$notifications = array_filter(
				$notifications,
				function ( $n ) {
					return empty( $n['read'] );
				}
			);
		}

		return $notifications;
	}

	/**
	 * Get unread notification count.
	 *
	 * @param int $user_id User ID.
	 * @return int Count.
	 */
	public static function get_unread_count( $user_id ) {
		return count( self::get_notifications( $user_id, true ) );
	}

	/**
	 * Mark notification as read.
	 *
	 * @param int    $user_id         User ID.
	 * @param string $notification_id Notification ID.
	 * @return bool Success.
	 */
	public static function mark_notification_read( $user_id, $notification_id ) {
		$notifications = get_user_meta( $user_id, 'bd_list_notifications', true );
		if ( ! is_array( $notifications ) ) {
			return false;
		}

		foreach ( $notifications as &$notification ) {
			if ( $notification['id'] === $notification_id ) {
				$notification['read'] = true;
				update_user_meta( $user_id, 'bd_list_notifications', $notifications );
				return true;
			}
		}

		return false;
	}

	/**
	 * Mark all notifications as read.
	 *
	 * @param int $user_id User ID.
	 * @return bool Success.
	 */
	public static function mark_all_read( $user_id ) {
		$notifications = get_user_meta( $user_id, 'bd_list_notifications', true );
		if ( ! is_array( $notifications ) ) {
			return false;
		}

		foreach ( $notifications as &$notification ) {
			$notification['read'] = true;
		}

		update_user_meta( $user_id, 'bd_list_notifications', $notifications );
		return true;
	}

	/**
	 * Clear all notifications.
	 *
	 * @param int $user_id User ID.
	 * @return bool Success.
	 */
	public static function clear_notifications( $user_id ) {
		return delete_user_meta( $user_id, 'bd_list_notifications' );
	}

	// =========================================================================
	// USER SEARCH
	// =========================================================================

	/**
	 * Search users for adding as collaborators.
	 *
	 * @param string $search  Search term.
	 * @param int    $list_id List ID (to exclude existing collaborators).
	 * @param int    $limit   Max results.
	 * @return array Users.
	 */
	public static function search_users( $search, $list_id = 0, $limit = 10 ) {
		global $wpdb;
		$table = self::get_table();

		// Get existing collaborator IDs.
		$exclude_ids = array();
		if ( $list_id ) {
			$list = ListManager::get_list( $list_id );
			if ( $list ) {
				$exclude_ids[] = $list['user_id']; // Exclude owner.
			}

			$existing    = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT user_id FROM $table WHERE list_id = %d",
					$list_id
				)
			);
			$exclude_ids = array_merge( $exclude_ids, $existing );
		}

		// Build user query.
		$args = array(
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
			'number'         => $limit,
			'exclude'        => $exclude_ids,
			'fields'         => array( 'ID', 'display_name', 'user_email' ),
		);

		$user_query = new \WP_User_Query( $args );
		$users      = $user_query->get_results();

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
			);
		}

		return $results;
	}

	/**
	 * Get recent collaborators for quick-add.
	 *
	 * @param int $owner_id Owner user ID.
	 * @param int $limit    Max results.
	 * @return array Users.
	 */
	public static function get_recent_collaborators( $owner_id, $limit = 5 ) {
		global $wpdb;
		$table       = self::get_table();
		$lists_table = self::get_lists_table();

		// Get users who have collaborated on owner's lists.
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT c.user_id, u.display_name, MAX(c.added_at) as last_collab
				FROM $table c
				INNER JOIN $lists_table l ON c.list_id = l.id
				LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
				WHERE l.user_id = %d AND c.status = 'active'
				GROUP BY c.user_id, u.display_name
				ORDER BY last_collab DESC
				LIMIT %d",
				$owner_id,
				$limit
			),
			ARRAY_A
		);

		foreach ( $users as &$user ) {
			$user['avatar_url'] = get_avatar_url( $user['user_id'], array( 'size' => 40 ) );
		}

		return $users;
	}
}
