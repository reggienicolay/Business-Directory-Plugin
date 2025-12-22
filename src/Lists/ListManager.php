<?php
/**
 * List Manager
 *
 * Core CRUD operations for user-created business lists.
 * Follows ActivityTracker pattern with static methods.
 *
 * @package BusinessDirectory
 */

namespace BD\Lists;

use BD\Gamification\ActivityTracker;
use BD\Gamification\BadgeSystem;

class ListManager {

	/**
	 * Create a new list
	 *
	 * @param int    $user_id     User ID.
	 * @param string $title       List title.
	 * @param string $description List description.
	 * @param string $visibility  Visibility: public, private, unlisted.
	 * @return int|false List ID on success, false on failure.
	 */
	public static function create_list( $user_id, $title, $description = '', $visibility = 'private' ) {
		if ( ! $user_id || empty( $title ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		// Generate unique slug
		$slug = self::generate_unique_slug( $title );

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'     => $user_id,
				'title'       => sanitize_text_field( $title ),
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $description ),
				'visibility'  => in_array( $visibility, array( 'public', 'private', 'unlisted' ), true ) ? $visibility : 'private',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$list_id = $wpdb->insert_id;

		// Track activity for points
		ActivityTracker::track( $user_id, 'list_created', $list_id );

		// If created as public, award bonus points
		if ( 'public' === $visibility ) {
			ActivityTracker::track( $user_id, 'list_made_public', $list_id );
		}

		return $list_id;
	}

	/**
	 * Update a list
	 *
	 * @param int   $list_id List ID.
	 * @param int   $user_id User ID (for permission check).
	 * @param array $data    Data to update.
	 * @return bool Success or failure.
	 */
	public static function update_list( $list_id, $user_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		// Verify ownership
		$list = self::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $user_id ) {
			return false;
		}

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);
		$formats     = array( '%s' );

		if ( isset( $data['title'] ) ) {
			$update_data['title'] = sanitize_text_field( $data['title'] );
			$formats[]            = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
			$formats[]                  = '%s';
		}

		if ( isset( $data['visibility'] ) && in_array( $data['visibility'], array( 'public', 'private', 'unlisted' ), true ) ) {
			$old_visibility = $list['visibility'];
			$new_visibility = $data['visibility'];

			$update_data['visibility'] = $new_visibility;
			$formats[]                 = '%s';

			// Award bonus if making public for first time
			if ( 'public' !== $old_visibility && 'public' === $new_visibility ) {
				ActivityTracker::track( $user_id, 'list_made_public', $list_id );
			}
		}

		if ( isset( $data['cover_image_id'] ) ) {
			$update_data['cover_image_id'] = absint( $data['cover_image_id'] );
			$formats[]                     = '%d';
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $list_id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a list
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User ID (for permission check).
	 * @return bool Success or failure.
	 */
	public static function delete_list( $list_id, $user_id ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Verify ownership (admins can delete any)
		$list = self::get_list( $list_id );
		if ( ! $list ) {
			return false;
		}

		if ( (int) $list['user_id'] !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Delete items first
		$wpdb->delete( $items_table, array( 'list_id' => $list_id ), array( '%d' ) );

		// Delete list
		$result = $wpdb->delete( $lists_table, array( 'id' => $list_id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Get a single list
	 *
	 * @param int $list_id List ID.
	 * @return array|null List data or null.
	 */
	public static function get_list( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$list = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $list_id ),
			ARRAY_A
		);

		if ( $list ) {
			$list['item_count'] = self::get_list_item_count( $list_id );
			$list['author']     = get_userdata( $list['user_id'] );
		}

		return $list;
	}

	/**
	 * Get list by slug
	 *
	 * @param string $slug List slug.
	 * @return array|null List data or null.
	 */
	public static function get_list_by_slug( $slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$list = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ),
			ARRAY_A
		);

		if ( $list ) {
			$list['item_count'] = self::get_list_item_count( $list['id'] );
			$list['author']     = get_userdata( $list['user_id'] );
		}

		return $list;
	}

	/**
	 * Get lists for a user
	 *
	 * @param int    $user_id    User ID.
	 * @param string $visibility Filter by visibility (null for all).
	 * @param int    $limit      Limit results.
	 * @param int    $offset     Offset for pagination.
	 * @return array Lists.
	 */
	public static function get_user_lists( $user_id, $visibility = null, $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$where = $wpdb->prepare( 'user_id = %d', $user_id );

		if ( $visibility && in_array( $visibility, array( 'public', 'private', 'unlisted' ), true ) ) {
			$where .= $wpdb->prepare( ' AND visibility = %s', $visibility );
		}

		$lists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE $where ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		// Add item counts
		foreach ( $lists as &$list ) {
			$list['item_count'] = self::get_list_item_count( $list['id'] );
		}

		return $lists;
	}

	/**
	 * Get public lists (for browse page)
	 *
	 * @param array $args Query arguments.
	 * @return array Lists with pagination info.
	 */
	public static function get_public_lists( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$defaults = array(
			'per_page' => 12,
			'page'     => 1,
			'orderby'  => 'updated_at',
			'order'    => 'DESC',
			'featured' => null,
			'search'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where   = "visibility = 'public'";
		$orderby = in_array( $args['orderby'], array( 'updated_at', 'created_at', 'view_count', 'title' ), true )
			? $args['orderby'] : 'updated_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		if ( null !== $args['featured'] ) {
			$where .= $wpdb->prepare( ' AND featured = %d', $args['featured'] ? 1 : 0 );
		}

		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= $wpdb->prepare( ' AND (title LIKE %s OR description LIKE %s)', $search, $search );
		}

		// Get total count
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

		// Get paginated results
		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		$lists  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name as author_name 
				FROM $table l
				LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
				WHERE $where 
				ORDER BY $orderby $order 
				LIMIT %d OFFSET %d",
				$args['per_page'],
				$offset
			),
			ARRAY_A
		);

		// Add item counts and cover images
		foreach ( $lists as &$list ) {
			$list['item_count']  = self::get_list_item_count( $list['id'] );
			$list['cover_image'] = self::get_list_cover_image( $list );
			$list['url']         = self::get_list_url( $list );
		}

		return array(
			'lists'    => $lists,
			'total'    => (int) $total,
			'pages'    => ceil( $total / $args['per_page'] ),
			'page'     => $args['page'],
			'per_page' => $args['per_page'],
		);
	}

	/**
	 * Add business to list
	 *
	 * @param int    $list_id     List ID.
	 * @param int    $business_id Business post ID.
	 * @param int    $user_id     User ID (for permission check).
	 * @param string $note        Optional user note.
	 * @return int|false Item ID on success, false on failure.
	 */
	public static function add_item( $list_id, $business_id, $user_id, $note = '' ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Verify list ownership
		$list = self::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $user_id ) {
			return false;
		}

		// Verify business exists
		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return false;
		}

		// Check if already in list
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $items_table WHERE list_id = %d AND business_id = %d",
				$list_id,
				$business_id
			)
		);

		if ( $exists ) {
			return false; // Already in list
		}

		// Get max sort order
		$max_order = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(sort_order) FROM $items_table WHERE list_id = %d",
				$list_id
			)
		);

		$result = $wpdb->insert(
			$items_table,
			array(
				'list_id'     => $list_id,
				'business_id' => $business_id,
				'user_note'   => sanitize_textarea_field( $note ),
				'sort_order'  => ( $max_order ?? 0 ) + 1,
				'added_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		if ( $result ) {
			// Update list timestamp
			$wpdb->update(
				$lists_table,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $list_id ),
				array( '%s' ),
				array( '%d' )
			);

			// Check for list_master badge (5 lists with 5+ items)
			BadgeSystem::check_and_award_badges( $user_id, 'list_item_added' );

			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Remove business from list
	 *
	 * @param int $list_id     List ID.
	 * @param int $business_id Business post ID.
	 * @param int $user_id     User ID (for permission check).
	 * @return bool Success or failure.
	 */
	public static function remove_item( $list_id, $business_id, $user_id ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Verify list ownership
		$list = self::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $user_id ) {
			return false;
		}

		$result = $wpdb->delete(
			$items_table,
			array(
				'list_id'     => $list_id,
				'business_id' => $business_id,
			),
			array( '%d', '%d' )
		);

		if ( $result ) {
			// Update list timestamp
			$wpdb->update(
				$lists_table,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $list_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return false !== $result;
	}

	/**
	 * Update item note
	 *
	 * @param int    $list_id     List ID.
	 * @param int    $business_id Business post ID.
	 * @param int    $user_id     User ID.
	 * @param string $note        New note.
	 * @return bool Success or failure.
	 */
	public static function update_item_note( $list_id, $business_id, $user_id, $note ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Verify list ownership
		$list = self::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $user_id ) {
			return false;
		}

		$result = $wpdb->update(
			$items_table,
			array( 'user_note' => sanitize_textarea_field( $note ) ),
			array(
				'list_id'     => $list_id,
				'business_id' => $business_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Reorder list items
	 *
	 * @param int   $list_id List ID.
	 * @param int   $user_id User ID.
	 * @param array $order   Array of business IDs in new order.
	 * @return bool Success or failure.
	 */
	public static function reorder_items( $list_id, $user_id, $order ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Verify list ownership
		$list = self::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $user_id ) {
			return false;
		}

		foreach ( $order as $position => $business_id ) {
			$wpdb->update(
				$items_table,
				array( 'sort_order' => $position + 1 ),
				array(
					'list_id'     => $list_id,
					'business_id' => absint( $business_id ),
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}

		return true;
	}

	/**
	 * Get list items with business data
	 *
	 * @param int $list_id List ID.
	 * @return array List items with business data.
	 */
	public static function get_list_items( $list_id ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT li.*, p.post_title as business_name, p.post_name as business_slug
				FROM $items_table li
				LEFT JOIN {$wpdb->posts} p ON li.business_id = p.ID
				WHERE li.list_id = %d
				ORDER BY li.sort_order ASC",
				$list_id
			),
			ARRAY_A
		);

		// Enrich with business data
		foreach ( $items as &$item ) {
			$item['business'] = self::format_business_for_list( $item['business_id'] );
		}

		return $items;
	}

	/**
	 * Get item count for a list
	 *
	 * @param int $list_id List ID.
	 * @return int Item count.
	 */
	public static function get_list_item_count( $list_id ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $items_table WHERE list_id = %d",
				$list_id
			)
		);
	}

	/**
	 * Check if business is in any of user's lists
	 *
	 * @param int $user_id     User ID.
	 * @param int $business_id Business ID.
	 * @return array List IDs containing this business.
	 */
	public static function get_lists_containing_business( $user_id, $business_id ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT l.id FROM $lists_table l
				INNER JOIN $items_table li ON l.id = li.list_id
				WHERE l.user_id = %d AND li.business_id = %d",
				$user_id,
				$business_id
			)
		);
	}

	/**
	 * Increment view count
	 *
	 * @param int $list_id List ID.
	 */
	public static function increment_view_count( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET view_count = view_count + 1 WHERE id = %d",
				$list_id
			)
		);
	}

	/**
	 * Toggle featured status (admin only)
	 *
	 * @param int  $list_id  List ID.
	 * @param bool $featured Featured status.
	 * @return bool Success or failure.
	 */
	public static function set_featured( $list_id, $featured ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$result = $wpdb->update(
			$table,
			array( 'featured' => $featured ? 1 : 0 ),
			array( 'id' => $list_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Generate unique slug
	 *
	 * @param string $title List title.
	 * @return string Unique slug.
	 */
	private static function generate_unique_slug( $title ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$slug      = sanitize_title( $title );
		$base_slug = $slug;
		$counter   = 1;

		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) ) ) {
			$slug = $base_slug . '-' . $counter;
			++$counter;
		}

		return $slug;
	}

	/**
	 * Get list cover image
	 *
	 * @param array $list List data.
	 * @return string|null Image URL or null.
	 */
	private static function get_list_cover_image( $list ) {
		// Use custom cover if set
		if ( ! empty( $list['cover_image_id'] ) ) {
			$url = wp_get_attachment_image_url( $list['cover_image_id'], 'medium' );
			if ( $url ) {
				return $url;
			}
		}

		// Fall back to first business image
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		$first_business_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT business_id FROM $items_table WHERE list_id = %d ORDER BY sort_order ASC LIMIT 1",
				$list['id']
			)
		);

		if ( $first_business_id ) {
			$thumbnail = get_the_post_thumbnail_url( $first_business_id, 'medium' );
			if ( $thumbnail ) {
				return $thumbnail;
			}
		}

		return null;
	}

	/**
	 * Get list URL
	 *
	 * @param array $list List data.
	 * @return string List URL.
	 */
	public static function get_list_url( $list ) {
		// Check if we have a lists page set
		$lists_page = get_option( 'bd_lists_page_id' );

		// If not set, try to find a page with [bd_list] shortcode
		if ( ! $lists_page ) {
			global $wpdb;
			$lists_page = $wpdb->get_var(
				"SELECT ID FROM {$wpdb->posts} 
				WHERE post_type = 'page' 
				AND post_status = 'publish' 
				AND post_content LIKE '%[bd_list]%' 
				LIMIT 1"
			);

			// Cache it for future use
			if ( $lists_page ) {
				update_option( 'bd_lists_page_id', $lists_page );
			}
		}

		if ( $lists_page ) {
			return add_query_arg( 'list', $list['slug'], get_permalink( $lists_page ) );
		}

		// Fall back to my-lists page with list param
		$my_lists_page = get_option( 'bd_my_lists_page_id' );
		if ( $my_lists_page ) {
			return add_query_arg( 'list', $list['slug'], get_permalink( $my_lists_page ) );
		}

		// Last resort - use current site URL with query param
		return add_query_arg( 'bd_list', $list['slug'], home_url( '/' ) );
	}

	/**
	 * Format business data for list display
	 *
	 * @param int $business_id Business post ID.
	 * @return array Formatted business data.
	 */
	private static function format_business_for_list( $business_id ) {
		$post = get_post( $business_id );
		if ( ! $post ) {
			return null;
		}

		$location = get_post_meta( $business_id, 'bd_location', true );
		$contact  = get_post_meta( $business_id, 'bd_contact', true );

		return array(
			'id'             => $business_id,
			'title'          => get_the_title( $business_id ),
			'slug'           => $post->post_name,
			'permalink'      => get_permalink( $business_id ),
			'featured_image' => get_the_post_thumbnail_url( $business_id, 'thumbnail' ),
			'rating'         => floatval( get_post_meta( $business_id, 'bd_avg_rating', true ) ),
			'review_count'   => intval( get_post_meta( $business_id, 'bd_review_count', true ) ),
			'price_level'    => get_post_meta( $business_id, 'bd_price_level', true ),
			'categories'     => wp_get_post_terms( $business_id, 'bd_category', array( 'fields' => 'names' ) ),
			'city'           => $location['city'] ?? '',
			'phone'          => $contact['phone'] ?? '',
		);
	}

	/**
	 * Get user's list count (for badges)
	 *
	 * @param int $user_id User ID.
	 * @return int List count.
	 */
	public static function get_user_list_count( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Get count of user's lists with 5+ items (for list_master badge)
	 *
	 * @param int $user_id User ID.
	 * @return int Count of qualifying lists.
	 */
	public static function get_user_qualifying_lists_count( $user_id ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT l.id
					FROM $lists_table l
					LEFT JOIN $items_table li ON l.id = li.list_id
					WHERE l.user_id = %d
					GROUP BY l.id
					HAVING COUNT(li.id) >= 5
				) as qualifying_lists",
				$user_id
			)
		);
	}
}
