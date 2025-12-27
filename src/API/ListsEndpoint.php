<?php
/**
 * Lists REST API Endpoint
 *
 * REST API for user-created business lists.
 * Follows BusinessEndpoint pattern with static init().
 *
 * @package BusinessDirectory
 */

namespace BD\API;

use BD\Lists\ListManager;

class ListsEndpoint {

	/**
	 * Initialize REST routes
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes
	 */
	public static function register_routes() {
		$namespace = 'bd/v1';

		// Get public lists (browse).
		register_rest_route(
			$namespace,
			'/lists',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_public_lists' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 12,
					),
					'orderby'  => array(
						'type'    => 'string',
						'default' => 'updated_at',
					),
					'featured' => array( 'type' => 'boolean' ),
					'search'   => array( 'type' => 'string' ),
				),
			)
		);

		// Create new list.
		register_rest_route(
			$namespace,
			'/lists',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_list' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'title'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'type'    => 'string',
						'default' => '',
					),
					'visibility'  => array(
						'type'    => 'string',
						'default' => 'private',
						'enum'    => array( 'public', 'private', 'unlisted' ),
					),
				),
			)
		);

		// Get user's own lists.
		register_rest_route(
			$namespace,
			'/lists/my-lists',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_my_lists' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
			)
		);

		// Get single list by ID or slug.
		register_rest_route(
			$namespace,
			'/lists/(?P<id>[\w-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_list' ),
				'permission_callback' => '__return_true',
			)
		);

		// Update list.
		register_rest_route(
			$namespace,
			'/lists/(?P<id>\d+)',
			array(
				'methods'             => 'PUT,PATCH',
				'callback'            => array( __CLASS__, 'update_list' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'title'          => array( 'type' => 'string' ),
					'description'    => array( 'type' => 'string' ),
					'visibility'     => array(
						'type' => 'string',
						'enum' => array( 'public', 'private', 'unlisted' ),
					),
					'cover_image_id' => array( 'type' => 'integer' ),
				),
			)
		);

		// Delete list.
		register_rest_route(
			$namespace,
			'/lists/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_list' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
			)
		);

		// Add item to list.
		register_rest_route(
			$namespace,
			'/lists/(?P<list_id>\d+)/items',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'add_item' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'business_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'note'        => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		// Remove item from list.
		register_rest_route(
			$namespace,
			'/lists/(?P<list_id>\d+)/items/(?P<business_id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'remove_item' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
			)
		);

		// Update item note.
		register_rest_route(
			$namespace,
			'/lists/(?P<list_id>\d+)/items/(?P<business_id>\d+)',
			array(
				'methods'             => 'PUT,PATCH',
				'callback'            => array( __CLASS__, 'update_item' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'note' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		// Reorder items.
		register_rest_route(
			$namespace,
			'/lists/(?P<list_id>\d+)/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reorder_items' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'order' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);

		// Quick save (add to list from business page).
		register_rest_route(
			$namespace,
			'/lists/quick-save',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'quick_save' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'business_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'list_id'     => array( 'type' => 'integer' ),
					'new_list'    => array( 'type' => 'string' ),
				),
			)
		);

		// Get lists containing a business.
		register_rest_route(
			$namespace,
			'/lists/containing/(?P<business_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_lists_containing' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
			)
		);

		// Track list share (for gamification).
		register_rest_route(
			$namespace,
			'/lists/(?P<list_id>\d+)/share',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'track_share' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'platform' => array(
						'type'    => 'string',
						'default' => 'unknown',
					),
				),
			)
		);

		// Get share data for a list.
		register_rest_route(
			$namespace,
			'/lists/(?P<list_id>\d+)/share-data',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_share_data' ),
				'permission_callback' => '__return_true',
			)
		);

		// Follow a list (Phase 2).
		register_rest_route(
			$namespace,
			'/lists/(?P<id>\d+)/follow',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'follow_list' ),
					'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'unfollow_list' ),
					'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback: user must be logged in
	 */
	public static function check_user_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * Get public lists
	 */
	public static function get_public_lists( $request ) {
		$result = ListManager::get_public_lists(
			array(
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
				'orderby'  => $request->get_param( 'orderby' ),
				'order'    => $request->get_param( 'order' ) ?? 'DESC',
				'featured' => $request->get_param( 'featured' ),
				'search'   => $request->get_param( 'search' ),
			)
		);

		return rest_ensure_response( $result );
	}

	/**
	 * Create new list
	 */
	public static function create_list( $request ) {
		$user_id = get_current_user_id();

		$list_id = ListManager::create_list(
			$user_id,
			$request->get_param( 'title' ),
			$request->get_param( 'description' ),
			$request->get_param( 'visibility' )
		);

		if ( ! $list_id ) {
			return new \WP_Error(
				'create_failed',
				'Could not create list',
				array( 'status' => 500 )
			);
		}

		$list = ListManager::get_list( $list_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'List created successfully!',
				'list'    => $list,
			)
		);
	}

	/**
	 * Get current user's lists
	 */
	public static function get_my_lists( $request ) {
		$user_id = get_current_user_id();
		$lists   = ListManager::get_user_lists( $user_id );

		return rest_ensure_response(
			array(
				'lists' => $lists,
				'total' => count( $lists ),
			)
		);
	}

	/**
	 * Get single list
	 */
	public static function get_list( $request ) {
		$id = $request->get_param( 'id' );

		// Try by ID first, then by slug.
		if ( is_numeric( $id ) ) {
			$list = ListManager::get_list( (int) $id );
		} else {
			$list = ListManager::get_list_by_slug( $id );
		}

		if ( ! $list ) {
			return new \WP_Error(
				'not_found',
				'List not found',
				array( 'status' => 404 )
			);
		}

		// Check visibility permissions.
		$current_user_id = get_current_user_id();
		if ( 'private' === $list['visibility'] && (int) $list['user_id'] !== $current_user_id ) {
			return new \WP_Error(
				'forbidden',
				'This list is private',
				array( 'status' => 403 )
			);
		}

		// Increment view count for public/unlisted lists.
		if ( (int) $list['user_id'] !== $current_user_id ) {
			ListManager::increment_view_count( $list['id'] );
		}

		// Get list items.
		$list['items'] = ListManager::get_list_items( $list['id'] );
		$list['url']   = ListManager::get_list_url( $list );

		return rest_ensure_response( $list );
	}

	/**
	 * Update list
	 */
	public static function update_list( $request ) {
		$list_id = $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$data = array();
		if ( $request->has_param( 'title' ) ) {
			$data['title'] = $request->get_param( 'title' );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}
		if ( $request->has_param( 'visibility' ) ) {
			$data['visibility'] = $request->get_param( 'visibility' );
		}
		if ( $request->has_param( 'cover_image_id' ) ) {
			$data['cover_image_id'] = $request->get_param( 'cover_image_id' );
		}

		$result = ListManager::update_list( $list_id, $user_id, $data );

		if ( ! $result ) {
			return new \WP_Error(
				'update_failed',
				'Could not update list. You may not have permission.',
				array( 'status' => 403 )
			);
		}

		$list = ListManager::get_list( $list_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'List updated successfully!',
				'list'    => $list,
			)
		);
	}

	/**
	 * Delete list
	 */
	public static function delete_list( $request ) {
		$list_id = $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$result = ListManager::delete_list( $list_id, $user_id );

		if ( ! $result ) {
			return new \WP_Error(
				'delete_failed',
				'Could not delete list. You may not have permission.',
				array( 'status' => 403 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'List deleted successfully!',
			)
		);
	}

	/**
	 * Add item to list
	 */
	public static function add_item( $request ) {
		$list_id     = $request->get_param( 'list_id' );
		$business_id = $request->get_param( 'business_id' );
		$note        = $request->get_param( 'note' );
		$user_id     = get_current_user_id();

		$item_id = ListManager::add_item( $list_id, $business_id, $user_id, $note );

		if ( ! $item_id ) {
			return new \WP_Error(
				'add_failed',
				'Could not add business to list. It may already be in the list.',
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Business added to list!',
				'item_id' => $item_id,
			)
		);
	}

	/**
	 * Remove item from list
	 */
	public static function remove_item( $request ) {
		$list_id     = $request->get_param( 'list_id' );
		$business_id = $request->get_param( 'business_id' );
		$user_id     = get_current_user_id();

		$result = ListManager::remove_item( $list_id, $business_id, $user_id );

		if ( ! $result ) {
			return new \WP_Error(
				'remove_failed',
				'Could not remove business from list.',
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Business removed from list!',
			)
		);
	}

	/**
	 * Update item (note)
	 */
	public static function update_item( $request ) {
		$list_id     = $request->get_param( 'list_id' );
		$business_id = $request->get_param( 'business_id' );
		$note        = $request->get_param( 'note' );
		$user_id     = get_current_user_id();

		$result = ListManager::update_item_note( $list_id, $business_id, $user_id, $note );

		if ( ! $result ) {
			return new \WP_Error(
				'update_failed',
				'Could not update note.',
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Note updated!',
			)
		);
	}

	/**
	 * Reorder items
	 */
	public static function reorder_items( $request ) {
		$list_id = $request->get_param( 'list_id' );
		$order   = $request->get_param( 'order' );
		$user_id = get_current_user_id();

		$result = ListManager::reorder_items( $list_id, $user_id, $order );

		if ( ! $result ) {
			return new \WP_Error(
				'reorder_failed',
				'Could not reorder items.',
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Items reordered!',
			)
		);
	}

	/**
	 * Quick save - add business to existing or new list
	 */
	public static function quick_save( $request ) {
		$business_id = $request->get_param( 'business_id' );
		$list_id     = $request->get_param( 'list_id' );
		$new_list    = $request->get_param( 'new_list' );
		$user_id     = get_current_user_id();

		// Create new list if requested.
		if ( ! empty( $new_list ) ) {
			$list_id = ListManager::create_list( $user_id, $new_list );
			if ( ! $list_id ) {
				return new \WP_Error(
					'create_failed',
					'Could not create new list.',
					array( 'status' => 500 )
				);
			}
		}

		if ( ! $list_id ) {
			return new \WP_Error(
				'missing_list',
				'Please select a list or create a new one.',
				array( 'status' => 400 )
			);
		}

		$item_id = ListManager::add_item( $list_id, $business_id, $user_id );

		if ( ! $item_id ) {
			return new \WP_Error(
				'add_failed',
				'Business is already in this list.',
				array( 'status' => 400 )
			);
		}

		$list = ListManager::get_list( $list_id );

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => 'Saved to "' . $list['title'] . '"!',
				'list'     => $list,
				'new_list' => ! empty( $new_list ),
			)
		);
	}

	/**
	 * Get lists containing a specific business
	 */
	public static function get_lists_containing( $request ) {
		$business_id = $request->get_param( 'business_id' );
		$user_id     = get_current_user_id();

		$list_ids = ListManager::get_lists_containing_business( $user_id, $business_id );

		return rest_ensure_response(
			array(
				'list_ids' => $list_ids,
				'count'    => count( $list_ids ),
			)
		);
	}

	/**
	 * Track list share for gamification
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public static function track_share( $request ) {
		$list_id  = $request->get_param( 'list_id' );
		$platform = $request->get_param( 'platform' );
		$user_id  = get_current_user_id();

		// Verify list exists.
		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error(
				'not_found',
				'List not found',
				array( 'status' => 404 )
			);
		}

		// Track the share.
		ListManager::track_share( $list_id, $user_id, $platform );

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => 'Share tracked!',
				'platform' => $platform,
			)
		);
	}

	/**
	 * Get share data for a list (publicly accessible for non-private lists)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public static function get_share_data( $request ) {
		$list_id = $request->get_param( 'list_id' );

		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error(
				'not_found',
				'List not found',
				array( 'status' => 404 )
			);
		}

		// Check visibility - only allow for public/unlisted lists.
		$current_user_id = get_current_user_id();
		if ( 'private' === $list['visibility'] && (int) $list['user_id'] !== $current_user_id ) {
			return new \WP_Error(
				'forbidden',
				'This list is private',
				array( 'status' => 403 )
			);
		}

		$share_data = ListManager::get_share_data( $list );

		return rest_ensure_response( $share_data );
	}

	/**
	 * Follow a list (Phase 2)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public static function follow_list( $request ) {
		$list_id = absint( $request['id'] );
		$user_id = get_current_user_id();

		$result = ListManager::follow_list( $list_id, $user_id );

		if ( ! $result ) {
			return new \WP_Error(
				'follow_failed',
				__( 'Could not follow this list.', 'business-directory' ),
				array( 'status' => 400 )
			);
		}

		$list = ListManager::get_list( $list_id );

		return rest_ensure_response(
			array(
				'success'        => true,
				'message'        => __( 'You are now following this list.', 'business-directory' ),
				'follower_count' => $list['follower_count'] ?? 0,
			)
		);
	}

	/**
	 * Unfollow a list (Phase 2)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public static function unfollow_list( $request ) {
		$list_id = absint( $request['id'] );
		$user_id = get_current_user_id();

		$result = ListManager::unfollow_list( $list_id, $user_id );

		if ( ! $result ) {
			return new \WP_Error(
				'unfollow_failed',
				__( 'Could not unfollow this list.', 'business-directory' ),
				array( 'status' => 400 )
			);
		}

		$list = ListManager::get_list( $list_id );

		return rest_ensure_response(
			array(
				'success'        => true,
				'message'        => __( 'You have unfollowed this list.', 'business-directory' ),
				'follower_count' => $list['follower_count'] ?? 0,
			)
		);
	}
}

// Initialize.
ListsEndpoint::init();
