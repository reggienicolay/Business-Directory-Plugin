<?php
/**
 * Collaborators REST API Endpoints
 *
 * Add these routes to ListsEndpoint.php or create as separate file.
 *
 * @package BusinessDirectory
 */

namespace BD\API;

use BD\Lists\ListManager;
use BD\Lists\ListCollaborators;

/**
 * Register additional routes for collaborators.
 * Call this from within ListsEndpoint::register_routes()
 */
function register_collaborator_routes() {
	$namespace = 'bd/v1';

	// =========================================================================
	// INVITE LINK MANAGEMENT
	// =========================================================================

	// Get invite link for a list.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/invite',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_invite_link',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
		)
	);

	// Regenerate invite link.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/invite/regenerate',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\regenerate_invite_link',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
		)
	);

	// Update invite settings.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/invite/settings',
		array(
			'methods'             => 'PUT,PATCH',
			'callback'            => __NAMESPACE__ . '\update_invite_settings',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
			'args'                => array(
				'mode' => array(
					'required' => true,
					'type'     => 'string',
					'enum'     => array( 'auto', 'approval', 'disabled' ),
				),
			),
		)
	);

	// Join via invite link.
	register_rest_route(
		$namespace,
		'/lists/join',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\join_list',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
			'args'                => array(
				'slug'  => array(
					'required' => true,
					'type'     => 'string',
				),
				'token' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);

	// =========================================================================
	// COLLABORATOR MANAGEMENT
	// =========================================================================

	// Get collaborators for a list.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/collaborators',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_collaborators',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
		)
	);

	// Add collaborator directly.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/collaborators',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\add_collaborator',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
			'args'                => array(
				'user_id' => array(
					'required' => true,
					'type'     => 'integer',
				),
				'role'    => array(
					'type'    => 'string',
					'default' => 'contributor',
					'enum'    => array( 'contributor', 'editor' ),
				),
			),
		)
	);

	// Remove collaborator.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/collaborators/(?P<user_id>\d+)',
		array(
			'methods'             => 'DELETE',
			'callback'            => __NAMESPACE__ . '\remove_collaborator',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
		)
	);

	// Leave a list (collaborator removes self).
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/leave',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\leave_list',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	// =========================================================================
	// REQUEST MANAGEMENT
	// =========================================================================

	// Get pending requests for a list.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/requests',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_pending_requests',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
		)
	);

	// Approve a request.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/requests/(?P<user_id>\d+)/approve',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\approve_request',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
		)
	);

	// Deny a request.
	register_rest_route(
		$namespace,
		'/lists/(?P<id>\d+)/requests/(?P<user_id>\d+)/deny',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\deny_request',
			'permission_callback' => __NAMESPACE__ . '\check_list_owner',
		)
	);

	// =========================================================================
	// USER ENDPOINTS
	// =========================================================================

	// Get collaborative lists for current user.
	register_rest_route(
		$namespace,
		'/users/me/collaborative-lists',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_my_collaborative_lists',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	// Get notifications.
	register_rest_route(
		$namespace,
		'/users/me/list-notifications',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_notifications',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	// Mark notification as read.
	register_rest_route(
		$namespace,
		'/users/me/list-notifications/(?P<id>[\w]+)/read',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\mark_notification_read',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	// Mark all notifications as read.
	register_rest_route(
		$namespace,
		'/users/me/list-notifications/read-all',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\mark_all_notifications_read',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	// Search users for adding.
	register_rest_route(
		$namespace,
		'/users/search',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\search_users',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
			'args'                => array(
				'search'  => array(
					'required' => true,
					'type'     => 'string',
				),
				'list_id' => array(
					'type'    => 'integer',
					'default' => 0,
				),
			),
		)
	);

	// Get recent collaborators.
	register_rest_route(
		$namespace,
		'/users/me/recent-collaborators',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_recent_collaborators',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);
}

// =========================================================================
// PERMISSION CALLBACKS
// =========================================================================

/**
 * Check if current user is the list owner.
 */
function check_list_owner( $request ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$list_id = $request->get_param( 'id' );
	$list    = ListManager::get_list( $list_id );

	if ( ! $list ) {
		return false;
	}

	return (int) $list['user_id'] === get_current_user_id();
}

// =========================================================================
// INVITE LINK HANDLERS
// =========================================================================

/**
 * Get invite link for a list.
 */
function get_invite_link( $request ) {
	$list_id  = $request->get_param( 'id' );
	$owner_id = get_current_user_id();

	$list = ListManager::get_list( $list_id );
	$url  = ListCollaborators::get_invite_url( $list_id, $owner_id );

	if ( ! $url ) {
		return new \WP_Error(
			'invite_error',
			'Could not generate invite link.',
			array( 'status' => 500 )
		);
	}

	return rest_ensure_response(
		array(
			'url'   => $url,
			'token' => $list['invite_token'] ?? '',
			'mode'  => $list['invite_mode'] ?? 'approval',
		)
	);
}

/**
 * Regenerate invite link.
 */
function regenerate_invite_link( $request ) {
	$list_id  = $request->get_param( 'id' );
	$owner_id = get_current_user_id();

	$token = ListCollaborators::regenerate_invite_token( $list_id, $owner_id );

	if ( ! $token ) {
		return new \WP_Error(
			'regenerate_error',
			'Could not regenerate invite link.',
			array( 'status' => 500 )
		);
	}

	$url = ListCollaborators::get_invite_url( $list_id, $owner_id );

	return rest_ensure_response(
		array(
			'success' => true,
			'url'     => $url,
			'token'   => $token,
			'message' => 'Invite link regenerated. Old links are now invalid.',
		)
	);
}

/**
 * Update invite settings.
 */
function update_invite_settings( $request ) {
	$list_id  = $request->get_param( 'id' );
	$mode     = $request->get_param( 'mode' );
	$owner_id = get_current_user_id();

	$result = ListCollaborators::set_invite_mode( $list_id, $owner_id, $mode );

	if ( ! $result ) {
		return new \WP_Error(
			'update_error',
			'Could not update invite settings.',
			array( 'status' => 500 )
		);
	}

	$mode_labels = array(
		'auto'     => 'Anyone with link can join automatically',
		'approval' => 'Requests require your approval',
		'disabled' => 'Invite link is disabled',
	);

	return rest_ensure_response(
		array(
			'success' => true,
			'mode'    => $mode,
			'message' => $mode_labels[ $mode ],
		)
	);
}

/**
 * Join a list via invite link.
 */
function join_list( $request ) {
	$slug    = $request->get_param( 'slug' );
	$token   = $request->get_param( 'token' );
	$user_id = get_current_user_id();

	// Validate token and get list.
	$list = ListCollaborators::validate_invite_token( $slug, $token );

	if ( ! $list ) {
		return new \WP_Error(
			'invalid_invite',
			'Invalid or expired invite link.',
			array( 'status' => 400 )
		);
	}

	$result = ListCollaborators::request_to_join( $list['id'], $user_id, $token );

	if ( ! $result['success'] ) {
		return new \WP_Error(
			$result['error'],
			$result['message'],
			array( 'status' => 400 )
		);
	}

	return rest_ensure_response( $result );
}

// =========================================================================
// COLLABORATOR HANDLERS
// =========================================================================

/**
 * Get collaborators for a list.
 */
function get_collaborators( $request ) {
	$list_id = $request->get_param( 'id' );

	$active  = ListCollaborators::get_active_collaborators( $list_id );
	$pending = ListCollaborators::get_pending_requests( $list_id );
	$list    = ListManager::get_list( $list_id );

	return rest_ensure_response(
		array(
			'collaborators' => $active,
			'pending'       => $pending,
			'count'         => count( $active ),
			'pending_count' => count( $pending ),
			'max_allowed'   => ListCollaborators::MAX_COLLABORATORS_PER_LIST,
			'invite_mode'   => $list['invite_mode'] ?? 'approval',
			'can_add_more'  => count( $active ) < ListCollaborators::MAX_COLLABORATORS_PER_LIST,
		)
	);
}

/**
 * Add a collaborator directly.
 */
function add_collaborator( $request ) {
	$list_id  = $request->get_param( 'id' );
	$user_id  = $request->get_param( 'user_id' );
	$role     = $request->get_param( 'role' );
	$owner_id = get_current_user_id();

	$result = ListCollaborators::add_collaborator( $list_id, $owner_id, $user_id, $role );

	if ( ! $result ) {
		return new \WP_Error(
			'add_failed',
			'Could not add collaborator. They may already be a member or limits have been reached.',
			array( 'status' => 400 )
		);
	}

	$user = get_userdata( $user_id );

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => sprintf( '%s has been added as a collaborator.', $user->display_name ),
			'user'    => array(
				'id'           => $user_id,
				'display_name' => $user->display_name,
				'avatar_url'   => get_avatar_url( $user_id, array( 'size' => 40 ) ),
				'role'         => $role,
			),
		)
	);
}

/**
 * Remove a collaborator.
 */
function remove_collaborator( $request ) {
	$list_id  = $request->get_param( 'id' );
	$user_id  = $request->get_param( 'user_id' );
	$owner_id = get_current_user_id();

	$result = ListCollaborators::remove_collaborator( $list_id, $owner_id, $user_id );

	if ( ! $result ) {
		return new \WP_Error(
			'remove_failed',
			'Could not remove collaborator.',
			array( 'status' => 400 )
		);
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'Collaborator removed.',
		)
	);
}

/**
 * Leave a list (collaborator removes self).
 */
function leave_list( $request ) {
	$list_id = $request->get_param( 'id' );
	$user_id = get_current_user_id();

	$result = ListCollaborators::leave_list( $list_id, $user_id );

	if ( ! $result ) {
		return new \WP_Error(
			'leave_failed',
			'Could not leave list.',
			array( 'status' => 400 )
		);
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'You have left this list.',
		)
	);
}

// =========================================================================
// REQUEST HANDLERS
// =========================================================================

/**
 * Get pending requests.
 */
function get_pending_requests( $request ) {
	$list_id = $request->get_param( 'id' );
	$pending = ListCollaborators::get_pending_requests( $list_id );

	return rest_ensure_response(
		array(
			'requests' => $pending,
			'count'    => count( $pending ),
		)
	);
}

/**
 * Approve a request.
 */
function approve_request( $request ) {
	$list_id  = $request->get_param( 'id' );
	$user_id  = $request->get_param( 'user_id' );
	$owner_id = get_current_user_id();

	$result = ListCollaborators::approve_request( $list_id, $owner_id, $user_id );

	if ( ! $result ) {
		return new \WP_Error(
			'approve_failed',
			'Could not approve request.',
			array( 'status' => 400 )
		);
	}

	$user = get_userdata( $user_id );

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => sprintf( '%s is now a collaborator.', $user->display_name ),
		)
	);
}

/**
 * Deny a request.
 */
function deny_request( $request ) {
	$list_id  = $request->get_param( 'id' );
	$user_id  = $request->get_param( 'user_id' );
	$owner_id = get_current_user_id();

	$result = ListCollaborators::deny_request( $list_id, $owner_id, $user_id );

	if ( ! $result ) {
		return new \WP_Error(
			'deny_failed',
			'Could not deny request.',
			array( 'status' => 400 )
		);
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'Request denied.',
		)
	);
}

// =========================================================================
// USER HANDLERS
// =========================================================================

/**
 * Get collaborative lists for current user.
 */
function get_my_collaborative_lists( $request ) {
	$user_id = get_current_user_id();
	$lists   = ListCollaborators::get_user_collaborative_lists( $user_id );

	return rest_ensure_response(
		array(
			'lists' => $lists,
			'count' => count( $lists ),
		)
	);
}

/**
 * Get notifications.
 */
function get_notifications( $request ) {
	$user_id       = get_current_user_id();
	$notifications = ListCollaborators::get_notifications( $user_id );
	$unread_count  = ListCollaborators::get_unread_count( $user_id );

	return rest_ensure_response(
		array(
			'notifications' => $notifications,
			'unread_count'  => $unread_count,
		)
	);
}

/**
 * Mark notification as read.
 */
function mark_notification_read( $request ) {
	$user_id         = get_current_user_id();
	$notification_id = $request->get_param( 'id' );

	ListCollaborators::mark_notification_read( $user_id, $notification_id );

	return rest_ensure_response(
		array(
			'success'      => true,
			'unread_count' => ListCollaborators::get_unread_count( $user_id ),
		)
	);
}

/**
 * Mark all notifications as read.
 */
function mark_all_notifications_read( $request ) {
	$user_id = get_current_user_id();

	ListCollaborators::mark_all_read( $user_id );

	return rest_ensure_response(
		array(
			'success'      => true,
			'unread_count' => 0,
		)
	);
}

/**
 * Search users.
 */
function search_users( $request ) {
	$search  = $request->get_param( 'search' );
	$list_id = $request->get_param( 'list_id' );

	if ( strlen( $search ) < 2 ) {
		return rest_ensure_response( array( 'users' => array() ) );
	}

	$users = ListCollaborators::search_users( $search, $list_id );

	return rest_ensure_response(
		array(
			'users' => $users,
		)
	);
}

/**
 * Get recent collaborators.
 */
function get_recent_collaborators( $request ) {
	$user_id = get_current_user_id();
	$recent  = ListCollaborators::get_recent_collaborators( $user_id );

	return rest_ensure_response(
		array(
			'users' => $recent,
		)
	);
}

// Hook to register routes.
add_action( 'rest_api_init', __NAMESPACE__ . '\register_collaborator_routes' );
