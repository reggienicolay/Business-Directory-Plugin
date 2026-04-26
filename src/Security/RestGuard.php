<?php
/**
 * REST Guard
 *
 * Closes two parallel anonymous-access surfaces that bypass our `bd/v1`
 * rate-limited controllers and leak data to scrapers:
 *
 * 1. `/wp-json/wp/v2/users*` — WordPress Core exposes user enumeration by
 *    default. `slug` is typically the user login, which is credential-stuffing
 *    fuel. Locked to `list_users` capability (admins only).
 *
 * 2. `/wp-json/wp/v2/bd_business*` and the bd_category/bd_tag/bd_area
 *    taxonomy endpoints — registered with `show_in_rest = true` so the block
 *    editor can edit them, but anonymous callers can paginate at per_page=100
 *    and bypass every rate limit on `bd/v1/businesses`. Locked to `edit_posts`
 *    capability (editors+), which is what Gutenberg requires to load the
 *    editor anyway, so this is invisible to legitimate users.
 *
 * Implementation: `rest_pre_dispatch` runs before route resolution. A non-null
 * return value short-circuits the request, so we return a WP_Error with the
 * appropriate HTTP status code.
 *
 * @package BusinessDirectory
 * @subpackage Security
 */

namespace BD\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestGuard {

	/**
	 * Routes guarded by `list_users` capability (admins only).
	 *
	 * Pattern matches /wp/v2/users, /wp/v2/users/{id}, etc.
	 *
	 * @var string
	 */
	const USERS_PATTERN = '#^/wp/v2/users(?:/|\?|$)#';

	/**
	 * Routes guarded by `edit_posts` capability (editors+).
	 *
	 * Covers the bd_business CPT and the three BD taxonomies.
	 *
	 * @var string
	 */
	const BD_PARALLEL_PATTERN = '#^/wp/v2/(bd_business|bd_category|bd_tag|bd_area)(?:/|\?|$)#';

	/**
	 * Wire the guard into the REST dispatch flow.
	 */
	public static function init() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'filter_dispatch' ), 10, 3 );
	}

	/**
	 * Short-circuit guarded routes for unauthorised callers.
	 *
	 * @param mixed            $result  Response. Non-null short-circuits dispatch.
	 * @param \WP_REST_Server  $server  Server instance (unused).
	 * @param \WP_REST_Request $request Current request.
	 * @return mixed
	 */
	public static function filter_dispatch( $result, $server, $request ) {
		// Don't override another filter that already short-circuited.
		if ( null !== $result ) {
			return $result;
		}

		$route = (string) $request->get_route();

		if ( preg_match( self::USERS_PATTERN, $route ) ) {
			if ( ! current_user_can( 'list_users' ) ) {
				return self::error_for( is_user_logged_in() );
			}
			return $result;
		}

		if ( preg_match( self::BD_PARALLEL_PATTERN, $route ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return self::error_for( is_user_logged_in() );
			}
			return $result;
		}

		return $result;
	}

	/**
	 * Build an appropriate WP_Error for the auth state.
	 *
	 * 401 when not logged in (so the client knows to authenticate);
	 * 403 when logged in but lacking the required capability.
	 *
	 * @param bool $logged_in Whether the current user is authenticated.
	 * @return \WP_Error
	 */
	private static function error_for( $logged_in ) {
		if ( $logged_in ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You are not allowed to access this endpoint.', 'business-directory' ),
				array( 'status' => 403 )
			);
		}
		return new \WP_Error(
			'rest_not_logged_in',
			__( 'Authentication required.', 'business-directory' ),
			array( 'status' => 401 )
		);
	}
}
