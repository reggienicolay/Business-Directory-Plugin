<?php
/**
 * List Manager
 *
 * Core CRUD operations for user-created business lists.
 * Follows ActivityTracker pattern with static methods.
 *
 * PERFORMANCE OPTIMIZATIONS (v1.1.0):
 * - Batch query helpers to eliminate N+1 patterns
 * - Optimized get_list_items() uses batch meta/term fetching
 * - Optimized get_public_lists() uses subquery for item counts
 * - Migrated follower system to bd_list_follows table
 * - Added cache invalidation hooks
 *
 * @package BusinessDirectory
 */


namespace BD\Lists;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

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

		// Generate unique slug.
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

		// Track activity for points.
		ActivityTracker::track( $user_id, 'list_created', $list_id );

		// If created as public, award bonus points.
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

		// Verify ownership.
		$list = self::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $user_id ) {
			return false;
		}

		$update_data = array( 'updated_at' => current_time( 'mysql' ) );
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
			$old_visibility            = $list['visibility'];
			$new_visibility            = $data['visibility'];
			$update_data['visibility'] = $new_visibility;
			$formats[]                 = '%s';

			if ( 'public' !== $old_visibility && 'public' === $new_visibility ) {
				ActivityTracker::track( $user_id, 'list_made_public', $list_id );
			}
		}

		if ( isset( $data['cover_image_id'] ) ) {
			$update_data['cover_image_id'] = absint( $data['cover_image_id'] );
			$formats[]                     = '%d';
		}

		$result = $wpdb->update( $table, $update_data, array( 'id' => $list_id ), $formats, array( '%d' ) );

		if ( false !== $result && class_exists( 'BD\Social\ImageGenerator' ) ) {
			\BD\Social\ImageGenerator::invalidate_list_cache( $list_id );
		}

		// Invalidate caches.
		self::invalidate_list_cache( $list_id );

		return false !== $result;
	}

	/**
	 * Delete a list
	 */
	public static function delete_list( $list_id, $user_id ) {
		global $wpdb;
		$lists_table   = $wpdb->prefix . 'bd_lists';
		$items_table   = $wpdb->prefix . 'bd_list_items';
		$follows_table = $wpdb->prefix . 'bd_list_follows';

		$list = self::get_list( $list_id );
		if ( ! $list ) {
			return false;
		}

		if ( (int) $list['user_id'] !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Delete related data.
		$wpdb->delete( $items_table, array( 'list_id' => $list_id ), array( '%d' ) );
		$wpdb->delete( $follows_table, array( 'list_id' => $list_id ), array( '%d' ) );
		$result = $wpdb->delete( $lists_table, array( 'id' => $list_id ), array( '%d' ) );

		if ( false !== $result && class_exists( 'BD\Social\ImageGenerator' ) ) {
			\BD\Social\ImageGenerator::invalidate_list_cache( $list_id );
		}

		return false !== $result;
	}

	/**
	 * Get a single list
	 */
	public static function get_list( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$list = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $list_id ),
			ARRAY_A
		);

		if ( $list ) {
			$list['item_count']     = self::get_list_item_count( $list_id );
			$list['follower_count'] = self::get_follower_count( $list_id );
			$list['author']         = get_userdata( $list['user_id'] );
		}

		return $list;
	}

	/**
	 * Get list by slug
	 */
	public static function get_list_by_slug( $slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$list = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ),
			ARRAY_A
		);

		if ( $list ) {
			$list['item_count']     = self::get_list_item_count( $list['id'] );
			$list['follower_count'] = self::get_follower_count( $list['id'] );
			$list['author']         = get_userdata( $list['user_id'] );
		}

		return $list;
	}

	/**
	 * Get lists for a user
	 */
	public static function get_user_lists( $user_id, $visibility = null, $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		$where = $wpdb->prepare( 'l.user_id = %d', $user_id );
		if ( $visibility && in_array( $visibility, array( 'public', 'private', 'unlisted' ), true ) ) {
			$where .= $wpdb->prepare( ' AND l.visibility = %s', $visibility );
		}

		// Use subquery to get item counts in single query.
		$lists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, 
				        (SELECT COUNT(*) FROM $items_table WHERE list_id = l.id) as item_count
				 FROM $table l 
				 WHERE $where 
				 ORDER BY l.updated_at DESC 
				 LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $lists;
	}

	/**
	 * Get public lists (for browse page).
	 * OPTIMIZED: Uses subquery for item counts and batch cover image fetching.
	 *
	 * @param array $args Query arguments.
	 * @return array Lists and pagination info.
	 */
	public static function get_public_lists( $args = array() ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		$defaults = array(
			'per_page' => 12,
			'page'     => 1,
			'orderby'  => 'updated_at',
			'order'    => 'DESC',
			'featured' => null,
			'search'   => '',
			'category' => '',
			'city'     => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		// Build WHERE clause.
		$where        = "l.visibility = 'public'";
		$where_values = array();

		if ( null !== $args['featured'] ) {
			$where         .= ' AND l.featured = %d';
			$where_values[] = $args['featured'] ? 1 : 0;
		}

		if ( ! empty( $args['search'] ) ) {
			$search         = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where         .= ' AND (l.title LIKE %s OR l.description LIKE %s)';
			$where_values[] = $search;
			$where_values[] = $search;
		}

		if ( ! empty( $args['category'] ) ) {
			$cat_search     = '%' . $wpdb->esc_like( $args['category'] ) . '%';
			$where         .= ' AND (l.cached_categories LIKE %s OR l.theme_override LIKE %s)';
			$where_values[] = $cat_search;
			$where_values[] = $cat_search;
		}

		if ( ! empty( $args['city'] ) ) {
			$where         .= ' AND l.cached_city = %s';
			$where_values[] = $args['city'];
		}

		// Validate orderby.
		$allowed_orderby = array( 'updated_at', 'created_at', 'view_count', 'title', 'follower_count' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Get total count (for pagination).
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM $lists_table l WHERE $where", $where_values );
		} else {
			$count_sql = "SELECT COUNT(*) FROM $lists_table l WHERE $where";
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Calculate offset.
		$offset = ( max( 1, $args['page'] ) - 1 ) * $args['per_page'];

		// Main query with item_count subquery - eliminates N+1 for counts.
		$sql = "SELECT l.*, 
		               u.display_name as author_name,
		               (SELECT COUNT(*) FROM $items_table WHERE list_id = l.id) as item_count
		        FROM $lists_table l
		        LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
		        WHERE $where
		        ORDER BY l.$orderby $order
		        LIMIT %d OFFSET %d";

		// Build final query with all values.
		$query_values = array_merge( $where_values, array( $args['per_page'], $offset ) );
		$sql          = $wpdb->prepare( $sql, $query_values );

		$lists = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $lists ) ) {
			return array(
				'lists'    => array(),
				'total'    => $total,
				'pages'    => 0,
				'page'     => $args['page'],
				'per_page' => $args['per_page'],
			);
		}

		// Batch process cover images - eliminates N+1 for images.
		$lists = self::batch_add_cover_images( $lists );

		// Add URLs and parse cached categories (cheap string operations).
		foreach ( $lists as &$list ) {
			$list['url']        = self::get_list_url( $list );
			$list['categories'] = self::get_list_display_categories( $list );
		}

		return array(
			'lists'    => $lists,
			'total'    => $total,
			'pages'    => (int) ceil( $total / $args['per_page'] ),
			'page'     => (int) $args['page'],
			'per_page' => (int) $args['per_page'],
		);
	}

	/**
	 * Add business to list
	 */
	public static function add_item( $list_id, $business_id, $user_id, $note = '' ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		$list = self::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $user_id ) {
			return false;
		}

		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return false;
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $items_table WHERE list_id = %d AND business_id = %d",
				$list_id,
				$business_id
			)
		);
		if ( $exists ) {
			return false;
		}

		$max_order = $wpdb->get_var(
			$wpdb->prepare( "SELECT MAX(sort_order) FROM $items_table WHERE list_id = %d", $list_id )
		);

		$result = $wpdb->insert(
			$items_table,
			array(
				'list_id'     => $list_id,
				'business_id' => $business_id,
				'user_note'   => sanitize_textarea_field( $note ),
				'sort_order'  => ( $max_order ?? 0 ) + 1,
				'added_by'    => $user_id,
				'added_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s' )
		);

		if ( $result ) {
			$wpdb->update(
				$lists_table,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $list_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( class_exists( 'BD\Social\ImageGenerator' ) ) {
				\BD\Social\ImageGenerator::invalidate_list_cache( $list_id );
			}

			// Refresh cached categories and city.
			self::refresh_list_cache( $list_id );

			// Invalidate object cache.
			self::invalidate_list_cache( $list_id );

			BadgeSystem::check_and_award_badges( $user_id, 'list_item_added' );

			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Add business to list with attribution tracking.
	 * Supports both owners and collaborators.
	 *
	 * @param int    $list_id     List ID.
	 * @param int    $business_id Business post ID.
	 * @param int    $user_id     User adding the item (for attribution).
	 * @param string $note        Optional note.
	 * @return int|false Item ID on success, false on failure.
	 */
	public static function add_item_with_attribution( $list_id, $business_id, $user_id, $note = '' ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Verify list exists.
		$list = self::get_list( $list_id );
		if ( ! $list ) {
			return false;
		}

		// Check permission: owner or collaborator with add rights.
		$is_owner = (int) $list['user_id'] === $user_id;
		$can_add  = $is_owner || ListCollaborators::can_add_items( $list_id, $user_id );

		if ( ! $can_add ) {
			return false;
		}

		// Verify business exists.
		$business = get_post( $business_id );
		if ( ! $business || 'bd_business' !== $business->post_type ) {
			return false;
		}

		// Check if already in list.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $items_table WHERE list_id = %d AND business_id = %d",
				$list_id,
				$business_id
			)
		);
		if ( $exists ) {
			return false;
		}

		// Get next sort order.
		$max_order = $wpdb->get_var(
			$wpdb->prepare( "SELECT MAX(sort_order) FROM $items_table WHERE list_id = %d", $list_id )
		);

		// Insert with added_by attribution.
		$result = $wpdb->insert(
			$items_table,
			array(
				'list_id'     => $list_id,
				'business_id' => $business_id,
				'user_note'   => sanitize_textarea_field( $note ),
				'sort_order'  => ( $max_order ?? 0 ) + 1,
				'added_by'    => $user_id,
				'added_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s' )
		);

		if ( $result ) {
			// Update list timestamp.
			$wpdb->update(
				$lists_table,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $list_id ),
				array( '%s' ),
				array( '%d' )
			);

			// Refresh cached data.
			self::refresh_list_cache( $list_id );
			self::invalidate_list_cache( $list_id );

			if ( class_exists( 'BD\Social\ImageGenerator' ) ) {
				\BD\Social\ImageGenerator::invalidate_list_cache( $list_id );
			}

			// Award badge.
			if ( class_exists( 'BD\Gamification\BadgeSystem' ) ) {
				BadgeSystem::check_and_award_badges( $user_id, 'list_item_added' );
			}

			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Remove business from list
	 */
	public static function remove_item( $list_id, $business_id, $user_id ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

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
			$wpdb->update(
				$lists_table,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $list_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( class_exists( 'BD\Social\ImageGenerator' ) ) {
				\BD\Social\ImageGenerator::invalidate_list_cache( $list_id );
			}

			// Refresh cached categories and city.
			self::refresh_list_cache( $list_id );

			// Invalidate object cache.
			self::invalidate_list_cache( $list_id );
		}

		return false !== $result;
	}

	/**
	 * Update item note
	 */
	public static function update_item_note( $list_id, $business_id, $user_id, $note ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

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
	 * Remove business from list with collaborator permission support.
	 * Allows owners OR collaborators with can_remove_items permission.
	 *
	 * @param int $list_id     List ID.
	 * @param int $business_id Business post ID.
	 * @param int $user_id     User attempting removal.
	 * @return bool Success.
	 */
	public static function remove_item_with_permission( $list_id, $business_id, $user_id ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		$list = self::get_list( $list_id );
		if ( ! $list ) {
			return false;
		}

		$is_owner = (int) $list['user_id'] === $user_id;

		// If not owner, check collaborator permissions.
		if ( ! $is_owner ) {
			// Get who added this item.
			$item = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT added_by FROM $items_table WHERE list_id = %d AND business_id = %d",
					$list_id,
					$business_id
				),
				ARRAY_A
			);

			if ( ! $item ) {
				return false; // Item doesn't exist.
			}

			$added_by = (int) ( $item['added_by'] ?? 0 );

			// Check collaborator permission.
			if ( ! ListCollaborators::can_remove_item( $list_id, $user_id, $added_by ) ) {
				return false;
			}
		}

		// Perform the deletion.
		$result = $wpdb->delete(
			$items_table,
			array(
				'list_id'     => $list_id,
				'business_id' => $business_id,
			),
			array( '%d', '%d' )
		);

		if ( $result ) {
			$wpdb->update(
				$lists_table,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $list_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( class_exists( 'BD\Social\ImageGenerator' ) ) {
				\BD\Social\ImageGenerator::invalidate_list_cache( $list_id );
			}

			self::refresh_list_cache( $list_id );
			self::invalidate_list_cache( $list_id );
		}

		return false !== $result;
	}

	/**
	 * Update item note with collaborator permission support.
	 * Allows owners OR collaborators with can_edit_notes permission.
	 *
	 * @param int    $list_id     List ID.
	 * @param int    $business_id Business post ID.
	 * @param int    $user_id     User attempting edit.
	 * @param string $note        New note text.
	 * @return bool Success.
	 */
	public static function update_item_note_with_permission( $list_id, $business_id, $user_id, $note ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		$list = self::get_list( $list_id );
		if ( ! $list ) {
			return false;
		}

		$is_owner = (int) $list['user_id'] === $user_id;

		// If not owner, check collaborator permissions.
		if ( ! $is_owner ) {
			// Get who added this item.
			$item = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT added_by FROM $items_table WHERE list_id = %d AND business_id = %d",
					$list_id,
					$business_id
				),
				ARRAY_A
			);

			if ( ! $item ) {
				return false; // Item doesn't exist.
			}

			$added_by = (int) ( $item['added_by'] ?? 0 );

			// Check collaborator permission using can_edit_note helper.
			if ( ! ListCollaborators::can_edit_note( $list_id, $user_id, $added_by ) ) {
				return false;
			}
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
	 * Reorder list items.
	 * OPTIMIZED: Uses single CASE statement instead of N updates.
	 * FIXED: Added max items limit to prevent DoS.
	 */
	public static function reorder_items( $list_id, $user_id, $order ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		$list = self::get_list( $list_id );
		if ( ! $list || (int) $list['user_id'] !== (int) $user_id ) {
			return false;
		}

		if ( empty( $order ) || ! is_array( $order ) ) {
			return true;
		}

		// Limit array size to prevent DoS via huge SQL CASE statement.
		$max_items = 500;
		if ( count( $order ) > $max_items ) {
			$order = array_slice( $order, 0, $max_items, true );
		}

		// Build CASE statement for bulk update - single query instead of N.
		$cases = array();
		$ids   = array();

		foreach ( $order as $position => $business_id ) {
			$business_id = (int) $business_id;
			$position    = (int) $position + 1;
			$cases[]     = $wpdb->prepare( 'WHEN business_id = %d THEN %d', $business_id, $position );
			$ids[]       = $business_id;
		}

		if ( empty( $cases ) ) {
			return true;
		}

		$case_sql = implode( ' ', $cases );
		$ids_sql  = implode( ',', array_map( 'intval', $ids ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $items_table 
				 SET sort_order = CASE $case_sql ELSE sort_order END 
				 WHERE list_id = %d AND business_id IN ($ids_sql)",
				$list_id
			)
		);

		// Invalidate cache.
		self::invalidate_list_cache( $list_id );

		return true;
	}

	/**
	 * Get list items with business data.
	 * OPTIMIZED: Uses batch queries instead of N+1 pattern.
	 *
	 * @param int $list_id List ID.
	 * @return array Array of items with business data.
	 */
	public static function get_list_items( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_list_items';

		// Get item records.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE list_id = %d ORDER BY sort_order ASC",
				$list_id
			),
			ARRAY_A
		);

		if ( empty( $items ) ) {
			return array();
		}

		// Collect all business IDs.
		$business_ids = wp_list_pluck( $items, 'business_id' );
		$business_ids = array_map( 'absint', $business_ids );
		$business_ids = array_filter( $business_ids );

		if ( empty( $business_ids ) ) {
			return array();
		}

		// Prime the post cache in a single query.
		_prime_post_caches( $business_ids, true, true );

		// Batch fetch all meta we need.
		$locations     = self::batch_get_post_meta( $business_ids, 'bd_location' );
		$ratings       = self::batch_get_post_meta( $business_ids, 'bd_avg_rating' );
		$review_counts = self::batch_get_post_meta( $business_ids, 'bd_review_count' );
		$price_levels  = self::batch_get_post_meta( $business_ids, 'bd_price_level' );
		$contacts      = self::batch_get_post_meta( $business_ids, 'bd_contact' );

		// Batch fetch thumbnails.
		$thumbnails = self::batch_get_thumbnails( $business_ids, 'thumbnail' );

		// Batch fetch categories.
		$all_categories = self::batch_get_terms( $business_ids, 'bd_category' );

		// Now format each item using cached data.
		foreach ( $items as &$item ) {
			$bid  = absint( $item['business_id'] );
			$post = get_post( $bid ); // Served from cache.

			// Check if post exists and is published.
			if ( ! $post || 'publish' !== $post->post_status || 'bd_business' !== $post->post_type ) {
				$item['business'] = null;
				continue;
			}

			// Get categories for this business.
			$categories     = $all_categories[ $bid ] ?? array();
			$category_names = wp_list_pluck( $categories, 'name' );

			// Get location data.
			$location = $locations[ $bid ] ?? array();
			$contact  = $contacts[ $bid ] ?? array();

			// Build business data array.
			$item['business'] = array(
				'id'             => $bid,
				'title'          => $post->post_title,
				'slug'           => $post->post_name,
				'permalink'      => get_permalink( $bid ), // Uses cached post.
				'featured_image' => $thumbnails[ $bid ] ?? null,
				'rating'         => floatval( $ratings[ $bid ] ?? 0 ),
				'review_count'   => intval( $review_counts[ $bid ] ?? 0 ),
				'price_level'    => $price_levels[ $bid ] ?? '',
				'categories'     => $category_names,
				'city'           => $location['city'] ?? '',
				'phone'          => $contact['phone'] ?? '',
			);
		}

		// Filter out items with null/deleted businesses.
		$items = array_filter(
			$items,
			function ( $item ) {
				return ! empty( $item['business'] );
			}
		);

		// Re-index array to ensure sequential keys.
		return array_values( $items );
	}

	/**
	 * Get list items with coordinates for map view
	 */
	public static function get_list_items_with_coords( $list_id ) {
		$items     = self::get_list_items( $list_id );
		$map_items = array();

		// Batch fetch locations for items that need them.
		$business_ids = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['business'] ) ) {
				$business_ids[] = $item['business_id'];
			}
		}

		$locations = self::batch_get_post_meta( $business_ids, 'bd_location' );

		foreach ( $items as $item ) {
			if ( empty( $item['business'] ) ) {
				continue;
			}

			$location = $locations[ $item['business_id'] ] ?? array();
			if ( empty( $location['lat'] ) || empty( $location['lng'] ) ) {
				continue;
			}

			$map_items[] = array(
				'id'        => $item['business_id'],
				'title'     => $item['business']['title'],
				'permalink' => $item['business']['permalink'],
				'image'     => $item['business']['featured_image'],
				'rating'    => $item['business']['rating'],
				'category'  => ! empty( $item['business']['categories'] ) ? $item['business']['categories'][0] : '',
				'lat'       => floatval( $location['lat'] ),
				'lng'       => floatval( $location['lng'] ),
				'note'      => $item['user_note'] ?? '',
			);
		}

		return $map_items;
	}

	/**
	 * Get item count for a list
	 */
	public static function get_list_item_count( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_list_items';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE list_id = %d", $list_id )
		);
	}

	/**
	 * Get list item business IDs only (without full data).
	 * Useful for checking what's already in a list.
	 *
	 * @param int $list_id List ID.
	 * @return array Array of business IDs.
	 */
	public static function get_list_item_ids( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_list_items';

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT business_id FROM $table WHERE list_id = %d ORDER BY sort_order ASC",
				$list_id
			)
		);
	}

	/**
	 * Get lists containing a specific business
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

	// =========================================================================
	// FEATURE 9: "FEATURED IN X LISTS" FOR BUSINESS PAGES
	// =========================================================================

	/**
	 * Get public lists containing a specific business
	 *
	 * @param int $business_id Business post ID.
	 * @param int $limit       Max lists to return.
	 * @return array Public lists containing this business.
	 */
	public static function get_public_lists_for_business( $business_id, $limit = 5 ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		$lists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name as author_name
				FROM $lists_table l
				INNER JOIN $items_table li ON l.id = li.list_id
				LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
				WHERE li.business_id = %d AND l.visibility = 'public'
				ORDER BY l.view_count DESC, l.updated_at DESC
				LIMIT %d",
				$business_id,
				$limit
			),
			ARRAY_A
		);

		foreach ( $lists as &$list ) {
			$list['item_count'] = self::get_list_item_count( $list['id'] );
			$list['url']        = self::get_list_url( $list );
		}

		return $lists;
	}

	/**
	 * Count public lists containing a specific business
	 *
	 * @param int $business_id Business post ID.
	 * @return int Count.
	 */
	public static function count_public_lists_for_business( $business_id ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT l.id)
				FROM $lists_table l
				INNER JOIN $items_table li ON l.id = li.list_id
				WHERE li.business_id = %d AND l.visibility = 'public'",
				$business_id
			)
		);
	}

	// =========================================================================
	// FEATURE 10: LIST FOLLOWING
	// Uses dedicated bd_list_follows table for performance.
	// =========================================================================

	/**
	 * Follow a list.
	 * OPTIMIZED: Uses dedicated bd_list_follows table.
	 * FIXED: Uses INSERT IGNORE to prevent race condition.
	 *
	 * @param int $list_id List ID to follow.
	 * @param int $user_id User ID who is following.
	 * @return bool Success.
	 */
	public static function follow_list( $list_id, $user_id ) {
		global $wpdb;

		if ( ! $list_id || ! $user_id ) {
			return false;
		}

		$list = self::get_list( $list_id );
		if ( ! $list || 'private' === $list['visibility'] ) {
			return false;
		}

		// Can't follow own list.
		if ( (int) $list['user_id'] === $user_id ) {
			return false;
		}

		$table = $wpdb->prefix . 'bd_list_follows';

		// Use INSERT IGNORE to handle race condition gracefully.
		// If already following, this will do nothing (no error).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO $table (list_id, user_id, created_at) VALUES (%d, %d, %s)",
				$list_id,
				$user_id,
				current_time( 'mysql' )
			)
		);

		// Check if we actually inserted (vs already existed).
		$was_inserted = $wpdb->rows_affected > 0;

		if ( $was_inserted ) {
			// Only update stats if we actually inserted a new follow.
			$list_owner_id  = (int) $list['user_id'];
			$saves_received = (int) get_user_meta( $list_owner_id, 'bd_list_saves_received', true );
			update_user_meta( $list_owner_id, 'bd_list_saves_received', $saves_received + 1 );

			// Check for tastemaker badge (50 list saves).
			if ( class_exists( 'BD\Gamification\BadgeSystem' ) ) {
				BadgeSystem::check_and_award_badges( $list_owner_id, 'list_followed' );
			}

			if ( class_exists( 'BD\Gamification\ActivityTracker' ) ) {
				ActivityTracker::track( $user_id, 'list_followed', $list_id );
			}
		}

		return true; // Success either way (already following or just followed).
	}

	/**
	 * Unfollow a list.
	 *
	 * @param int $list_id List ID to unfollow.
	 * @param int $user_id User ID who is unfollowing.
	 * @return bool Success.
	 */
	public static function unfollow_list( $list_id, $user_id ) {
		global $wpdb;

		if ( ! $list_id || ! $user_id ) {
			return false;
		}

		$table = $wpdb->prefix . 'bd_list_follows';

		// Get list for owner stats update.
		$list = self::get_list( $list_id );

		// Delete from follows table.
		$result = $wpdb->delete(
			$table,
			array(
				'list_id' => $list_id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		// Decrement list owner's follower count.
		if ( $result && $list ) {
			$list_owner_id  = (int) $list['user_id'];
			$saves_received = (int) get_user_meta( $list_owner_id, 'bd_list_saves_received', true );
			if ( $saves_received > 0 ) {
				update_user_meta( $list_owner_id, 'bd_list_saves_received', $saves_received - 1 );
			}
		}

		return $result !== false;
	}

	/**
	 * Check if user is following a list.
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User ID.
	 * @return bool Is following.
	 */
	public static function is_following( $list_id, $user_id ) {
		global $wpdb;

		if ( ! $list_id || ! $user_id ) {
			return false;
		}

		$table = $wpdb->prefix . 'bd_list_follows';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE list_id = %d AND user_id = %d",
				$list_id,
				$user_id
			)
		);

		return (bool) $exists;
	}

	/**
	 * Get follower count for a list.
	 * OPTIMIZED: Uses dedicated bd_list_follows table with indexed query.
	 *
	 * @param int $list_id List ID.
	 * @return int Follower count.
	 */
	public static function get_follower_count( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_list_follows';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE list_id = %d",
				$list_id
			)
		);
	}

	/**
	 * Get lists a user is following.
	 *
	 * @param int $user_id User ID.
	 * @return array List of lists being followed.
	 */
	public static function get_following_lists( $user_id ) {
		global $wpdb;
		$follows_table = $wpdb->prefix . 'bd_list_follows';
		$lists_table   = $wpdb->prefix . 'bd_lists';
		$items_table   = $wpdb->prefix . 'bd_list_items';

		if ( ! $user_id ) {
			return array();
		}

		// Join to get list data and item counts in single query.
		$lists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, 
				        f.created_at as followed_at,
				        (SELECT COUNT(*) FROM $items_table WHERE list_id = l.id) as item_count
				 FROM $follows_table f
				 INNER JOIN $lists_table l ON f.list_id = l.id
				 WHERE f.user_id = %d AND l.visibility != 'private'
				 ORDER BY f.created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		// Add URLs.
		foreach ( $lists as &$list ) {
			$list['url'] = self::get_list_url( $list );
		}

		return $lists;
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Generate unique slug for list
	 */
	private static function generate_unique_slug( $title ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$slug         = sanitize_title( $title );
		$base_slug    = $slug;
		$counter      = 0;
		$max_attempts = 100; // Prevent infinite loop / DoS

		while ( $counter < $max_attempts ) {
			$test_slug = 0 === $counter ? $base_slug : $base_slug . '-' . $counter;
			$exists    = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $test_slug )
			);

			if ( ! $exists ) {
				return $test_slug;
			}
			++$counter;
		}

		// Fallback: append timestamp to guarantee uniqueness
		return $base_slug . '-' . time() . '-' . wp_rand( 100, 999 );
	}

	/**
	 * Get cover image for list.
	 * OPTIMIZED: Direct query for first item's thumbnail when no custom cover.
	 */
	public static function get_list_cover_image( $list ) {
		if ( ! empty( $list['cover_image_id'] ) ) {
			return wp_get_attachment_image_url( $list['cover_image_id'], 'medium' );
		}

		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Get first business ID only - single query.
		$first_business_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT business_id FROM $items_table WHERE list_id = %d ORDER BY sort_order ASC LIMIT 1",
				$list['id']
			)
		);

		if ( ! $first_business_id ) {
			return null;
		}

		return get_the_post_thumbnail_url( $first_business_id, 'medium' );
	}

	/**
	 * Get featured lists
	 */
	public static function get_featured_lists( $limit = 4 ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		$lists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, 
				        u.display_name as author_name,
				        (SELECT COUNT(*) FROM $items_table WHERE list_id = l.id) as item_count
				FROM $table l
				LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
				WHERE l.visibility = 'public' AND l.featured = 1
				ORDER BY l.updated_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		// Batch add cover images.
		$lists = self::batch_add_cover_images( $lists );

		foreach ( $lists as &$list ) {
			$list['url'] = self::get_list_url( $list );
		}

		return $lists;
	}

	/**
	 * Increment view count for a list
	 */
	public static function increment_view_count( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$wpdb->query(
			$wpdb->prepare( "UPDATE $table SET view_count = view_count + 1 WHERE id = %d", $list_id )
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
	 * Get list URL
	 */
	public static function get_list_url( $list ) {
		$lists_page = get_option( 'bd_lists_page_id' );

		if ( ! $lists_page ) {
			// Check transient first to avoid repeated LIKE queries.
			$lists_page = get_transient( 'bd_lists_page_fallback' );

			if ( false === $lists_page ) {
				global $wpdb;
				$lists_page = $wpdb->get_var(
					"SELECT ID FROM {$wpdb->posts} 
					WHERE post_type = 'page' AND post_status = 'publish' 
					AND post_content LIKE '%[bd_list]%' LIMIT 1"
				);

				// Cache even if null to prevent repeated lookups.
				set_transient( 'bd_lists_page_fallback', $lists_page ?: 0, DAY_IN_SECONDS );
			}

			if ( $lists_page ) {
				update_option( 'bd_lists_page_id', $lists_page );
			}
		}

		if ( $lists_page ) {
			return add_query_arg( 'list', $list['slug'], get_permalink( $lists_page ) );
		}

		$my_lists_page = get_option( 'bd_my_lists_page_id' );
		if ( $my_lists_page ) {
			return add_query_arg( 'list', $list['slug'], get_permalink( $my_lists_page ) );
		}

		return add_query_arg( 'bd_list', $list['slug'], home_url( '/' ) );
	}

	/**
	 * Format business data for list display.
	 *
	 * NOTE: This is now primarily a fallback method.
	 * get_list_items() uses batch queries for better performance.
	 * This method is kept for single-item operations and backward compatibility.
	 *
	 * @param int $business_id Business post ID.
	 * @return array|null Business data or null if not found.
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
	 */
	public static function get_user_list_count( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id )
		);
	}

	/**
	 * Get count of user's lists with 5+ items (for list_master badge)
	 */
	public static function get_user_qualifying_lists_count( $user_id ) {
		global $wpdb;
		$lists_table = $wpdb->prefix . 'bd_lists';
		$items_table = $wpdb->prefix . 'bd_list_items';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT l.id FROM $lists_table l
					LEFT JOIN $items_table li ON l.id = li.list_id
					WHERE l.user_id = %d GROUP BY l.id HAVING COUNT(li.id) >= 5
				) as qualifying_lists",
				$user_id
			)
		);
	}

	// =========================================================================
	// SHARING FUNCTIONALITY
	// =========================================================================

	/**
	 * Get comprehensive share data for a list.
	 */
	public static function get_share_data( $list ) {
		if ( is_numeric( $list ) ) {
			$list = self::get_list( $list );
		}

		if ( ! $list ) {
			return array();
		}

		$url        = self::get_list_url( $list );
		$title      = $list['title'];
		$item_count = $list['item_count'] ?? self::get_list_item_count( $list['id'] );

		$author_name = '';
		if ( ! empty( $list['user_id'] ) ) {
			$author = get_userdata( $list['user_id'] );
			if ( $author ) {
				$author_name = $author->display_name;
			}
		}

		$share_text = sprintf(
			// translators: %s is the list title.
			__( 'Check out "%1$s" - %2$d %3$s I recommend!', 'business-directory' ),
			$title,
			$item_count,
			_n( 'place', 'places', $item_count, 'business-directory' )
		);

		$site_name = sanitize_title( get_bloginfo( 'name' ) );
		$hashtags  = $site_name . ',LocalBusiness,' . sanitize_title( $list['title'] );

		return array(
			'url'         => $url,
			'title'       => $title,
			'description' => $list['description'] ?? '',
			'item_count'  => $item_count,
			'author'      => $author_name,
			'share_text'  => $share_text,
			'hashtags'    => $hashtags,
			'image_url'   => self::get_share_image_url( $list['id'] ),
			'embed_code'  => self::get_embed_code( $list ),
			'qr_code_url' => self::get_qr_code_url( $url ),
			'share_links' => array(
				'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
				'twitter'   => 'https://twitter.com/intent/tweet?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $share_text ) . '&hashtags=' . rawurlencode( $hashtags ),
				'pinterest' => 'https://pinterest.com/pin/create/button/?url=' . rawurlencode( $url ) . '&description=' . rawurlencode( $share_text ),
				'linkedin'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ),
				'email'     => 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $share_text . "\n\n" . $url ),
				'whatsapp'  => 'https://wa.me/?text=' . rawurlencode( $share_text . ' ' . $url ),
			),
		);
	}

	/**
	 * Get share image URL for a list.
	 */
	public static function get_share_image_url( $list_id ) {
		$upload_dir = wp_upload_dir();
		$cache_file = $upload_dir['basedir'] . '/bd-share-images/list-' . $list_id . '.png';

		if ( file_exists( $cache_file ) ) {
			$modified = filemtime( $cache_file );
			if ( time() - $modified < DAY_IN_SECONDS ) {
				return $upload_dir['baseurl'] . '/bd-share-images/list-' . $list_id . '.png';
			}
		}

		return add_query_arg(
			array(
				'bd_share_image' => 1,
				'list_id'        => $list_id,
			),
			home_url()
		);
	}

	/**
	 * Get embed code for a list.
	 */
	public static function get_embed_code( $list, $type = 'iframe' ) {
		if ( is_numeric( $list ) ) {
			$list = self::get_list( $list );
		}

		if ( ! $list ) {
			return '';
		}

		$url = self::get_list_url( $list );

		if ( 'shortcode' === $type ) {
			return sprintf( '[bd_list id="%d"]', $list['id'] );
		}

		$embed_url = add_query_arg( 'embed', '1', $url );

		return sprintf(
			'<iframe src="%s" width="100%%" height="500" frameborder="0" style="border: 1px solid #ddd; border-radius: 8px;" title="%s"></iframe>',
			esc_url( $embed_url ),
			esc_attr( $list['title'] )
		);
	}

	/**
	 * Get QR code URL for a list using QR Server API.
	 */
	public static function get_qr_code_url( $url, $size = 200 ) {
		return sprintf(
			'https://api.qrserver.com/v1/create-qr-code/?size=%dx%d&data=%s&format=png',
			$size,
			$size,
			rawurlencode( $url )
		);
	}

	/**
	 * Track list share event for gamification.
	 */
	public static function track_share( $list_id, $user_id, $platform = 'unknown' ) {
		if ( class_exists( 'BD\Gamification\ActivityTracker' ) ) {
			ActivityTracker::track( $user_id, 'list_shared', $list_id, array( 'platform' => $platform ) );
		}
	}

	/**
	 * Get public lists count for a user (for curator stats).
	 */
	public static function get_user_public_list_count( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE user_id = %d AND visibility = 'public'",
				$user_id
			)
		);
	}

	/**
	 * Get total views across all user's public lists.
	 */
	public static function get_user_total_list_views( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(view_count) FROM $table WHERE user_id = %d AND visibility = 'public'",
				$user_id
			)
		);
	}

	// =========================================================================
	// CATEGORY & CITY CACHING FOR BROWSE/FILTER
	// =========================================================================

	/**
	 * Refresh cached categories and city for a list.
	 * Called when items are added/removed.
	 *
	 * @param int $list_id List ID.
	 * @return bool Success.
	 */
	public static function refresh_list_cache( $list_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		// Get categories and city from list items.
		$categories = self::calculate_list_categories( $list_id );
		$city       = self::calculate_list_city( $list_id );

		// Update the cached values.
		$result = $wpdb->update(
			$table,
			array(
				'cached_categories' => ! empty( $categories ) ? implode( ',', $categories ) : null,
				'cached_city'       => $city,
			),
			array( 'id' => $list_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Calculate categories from businesses in a list.
	 * Returns top 3 most common category slugs.
	 * OPTIMIZED: Uses batch term fetching.
	 *
	 * @param int $list_id List ID.
	 * @return array Array of category slugs.
	 */
	public static function calculate_list_categories( $list_id ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Get all business IDs in the list.
		$business_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT business_id FROM $items_table WHERE list_id = %d",
				$list_id
			)
		);

		if ( empty( $business_ids ) ) {
			return array();
		}

		// Batch fetch all terms.
		$all_terms = self::batch_get_terms( $business_ids, 'bd_category' );

		// Count categories across all businesses.
		$category_counts = array();
		foreach ( $all_terms as $terms ) {
			foreach ( $terms as $term ) {
				$slug = $term['slug'];
				if ( ! isset( $category_counts[ $slug ] ) ) {
					$category_counts[ $slug ] = 0;
				}
				++$category_counts[ $slug ];
			}
		}

		if ( empty( $category_counts ) ) {
			return array();
		}

		// Sort by count descending and take top 3.
		arsort( $category_counts );
		return array_slice( array_keys( $category_counts ), 0, 3 );
	}

	/**
	 * Calculate primary city from businesses in a list.
	 * Returns the most common city.
	 * OPTIMIZED: Uses batch meta fetching.
	 *
	 * @param int $list_id List ID.
	 * @return string|null City name or null.
	 */
	public static function calculate_list_city( $list_id ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		// Get all business IDs in the list.
		$business_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT business_id FROM $items_table WHERE list_id = %d",
				$list_id
			)
		);

		if ( empty( $business_ids ) ) {
			return null;
		}

		// Batch fetch all locations.
		$locations = self::batch_get_post_meta( $business_ids, 'bd_location' );

		// Count cities across all businesses.
		$city_counts = array();
		foreach ( $locations as $location ) {
			if ( ! empty( $location['city'] ) ) {
				$city = $location['city'];
				if ( ! isset( $city_counts[ $city ] ) ) {
					$city_counts[ $city ] = 0;
				}
				++$city_counts[ $city ];
			}
		}

		if ( empty( $city_counts ) ) {
			return null;
		}

		// Return most common city.
		arsort( $city_counts );
		return array_key_first( $city_counts );
	}

	/**
	 * Get display-ready categories for a list.
	 * Uses cache if available, otherwise calculates.
	 *
	 * @param array $list List data.
	 * @return array Array of category objects with slug, name, icon.
	 */
	public static function get_list_display_categories( $list ) {
		// Use override if set.
		if ( ! empty( $list['theme_override'] ) ) {
			$slugs = array_map( 'trim', explode( ',', $list['theme_override'] ) );
		} elseif ( ! empty( $list['cached_categories'] ) ) {
			$slugs = array_map( 'trim', explode( ',', $list['cached_categories'] ) );
		} else {
			// Calculate on the fly (slower).
			$slugs = self::calculate_list_categories( $list['id'] );
		}

		if ( empty( $slugs ) ) {
			return array();
		}

		// Get term objects with names.
		$categories = array();
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'bd_category' );
			if ( $term ) {
				$categories[] = array(
					'slug' => $term->slug,
					'name' => $term->name,
					'icon' => self::get_category_icon( $term->slug ),
				);
			}
		}

		return $categories;
	}

	/**
	 * Get Font Awesome icon for a category.
	 *
	 * @param string $slug Category slug.
	 * @return string Font Awesome class.
	 */
	public static function get_category_icon( $slug ) {
		$icons = array(
			// Dining & Food
			'restaurants'     => 'fa-utensils',
			'dining'          => 'fa-utensils',
			'food'            => 'fa-utensils',
			'cafes'           => 'fa-coffee',
			'coffee'          => 'fa-coffee',
			'bakeries'        => 'fa-bread-slice',
			'bars'            => 'fa-glass-martini-alt',

			// Wine & Breweries
			'wineries'        => 'fa-wine-glass-alt',
			'wine'            => 'fa-wine-glass-alt',
			'breweries'       => 'fa-beer',
			'tasting-rooms'   => 'fa-wine-bottle',

			// Shopping
			'shopping'        => 'fa-shopping-bag',
			'retail'          => 'fa-store',
			'boutiques'       => 'fa-gem',

			// Services
			'services'        => 'fa-wrench',
			'professional'    => 'fa-briefcase',
			'health'          => 'fa-heartbeat',
			'beauty'          => 'fa-spa',

			// Entertainment
			'entertainment'   => 'fa-ticket-alt',
			'arts'            => 'fa-palette',
			'nightlife'       => 'fa-moon',

			// Recreation
			'outdoors'        => 'fa-hiking',
			'recreation'      => 'fa-running',
			'fitness'         => 'fa-dumbbell',
			'parks'           => 'fa-tree',

			// Family
			'family'          => 'fa-child',
			'kids'            => 'fa-child',
			'education'       => 'fa-graduation-cap',

			// Local Favorites
			'local-favorites' => 'fa-heart',
			'hidden-gems'     => 'fa-gem',
			'shop-local'      => 'fa-store-alt',
		);

		// Try exact match.
		if ( isset( $icons[ $slug ] ) ) {
			return 'fas ' . $icons[ $slug ];
		}

		// Try partial match.
		foreach ( $icons as $key => $icon ) {
			if ( strpos( $slug, $key ) !== false || strpos( $key, $slug ) !== false ) {
				return 'fas ' . $icon;
			}
		}

		// Default icon.
		return 'fas fa-tag';
	}

	/**
	 * Get all unique cities from public lists for filter dropdown.
	 *
	 * @return array Array of city names.
	 */
	public static function get_all_list_cities() {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$cities = $wpdb->get_col(
			"SELECT DISTINCT cached_city FROM $table 
			WHERE visibility = 'public' AND cached_city IS NOT NULL AND cached_city != ''
			ORDER BY cached_city ASC"
		);

		return $cities;
	}

	/**
	 * Get all unique categories from public lists for filter dropdown.
	 *
	 * @return array Array of category term objects.
	 */
	public static function get_all_list_categories() {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		// Get all cached categories.
		$all_cached = $wpdb->get_col(
			"SELECT cached_categories FROM $table 
			WHERE visibility = 'public' AND cached_categories IS NOT NULL AND cached_categories != ''"
		);

		// Extract unique slugs from cached data.
		$slugs = array();
		foreach ( $all_cached as $cached ) {
			$parts = array_map( 'trim', explode( ',', $cached ) );
			$slugs = array_merge( $slugs, $parts );
		}
		$slugs = array_unique( array_filter( $slugs ) );

		// Fallback: If no cached categories, calculate from all public lists.
		if ( empty( $slugs ) ) {
			$list_ids = $wpdb->get_col(
				"SELECT id FROM $table WHERE visibility = 'public'"
			);

			foreach ( $list_ids as $list_id ) {
				$calculated = self::calculate_list_categories( $list_id );
				$slugs      = array_merge( $slugs, $calculated );
			}
			$slugs = array_unique( $slugs );
		}

		if ( empty( $slugs ) ) {
			return array();
		}

		// Get term objects.
		$categories = array();
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'bd_category' );
			if ( $term ) {
				$categories[] = array(
					'slug' => $term->slug,
					'name' => $term->name,
					'icon' => self::get_category_icon( $term->slug ),
				);
			}
		}

		// Sort alphabetically by name.
		usort(
			$categories,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $categories;
	}

	/**
	 * Refresh cache for all existing lists.
	 * Run once after adding the new columns.
	 *
	 * @return int Number of lists updated.
	 */
	public static function refresh_all_list_caches() {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$list_ids = $wpdb->get_col( "SELECT id FROM $table" );
		$count    = 0;

		foreach ( $list_ids as $list_id ) {
			if ( self::refresh_list_cache( $list_id ) ) {
				++$count;
			}
		}

		return $count;
	}

	// =========================================================================
	// BATCH QUERY HELPERS (Performance Optimization)
	// =========================================================================

	/**
	 * Batch fetch post meta for multiple posts.
	 * Reduces N queries to 1 query.
	 *
	 * @param array  $post_ids Array of post IDs.
	 * @param string $meta_key Meta key to fetch.
	 * @return array Associative array of post_id => meta_value.
	 */
	public static function batch_get_post_meta( $post_ids, $meta_key ) {
		global $wpdb;

		if ( empty( $post_ids ) ) {
			return array();
		}

		// Sanitize IDs.
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// Build query with meta_key as last parameter.
		$query = $wpdb->prepare(
			"SELECT post_id, meta_value 
			 FROM {$wpdb->postmeta} 
			 WHERE post_id IN ($placeholders) AND meta_key = %s",
			array_merge( $post_ids, array( $meta_key ) )
		);

		$results = $wpdb->get_results( $query );
		$meta    = array();

		foreach ( $results as $row ) {
			$meta[ $row->post_id ] = maybe_unserialize( $row->meta_value );
		}

		return $meta;
	}

	/**
	 * Batch fetch terms for multiple posts.
	 * Reduces N queries to 1 query.
	 *
	 * @param array  $post_ids Array of post IDs.
	 * @param string $taxonomy Taxonomy name.
	 * @return array Associative array of post_id => array of terms.
	 */
	public static function batch_get_terms( $post_ids, $taxonomy ) {
		global $wpdb;

		if ( empty( $post_ids ) ) {
			return array();
		}

		// Sanitize IDs.
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		$query = $wpdb->prepare(
			"SELECT tr.object_id, t.term_id, t.name, t.slug 
			 FROM {$wpdb->term_relationships} tr
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			 WHERE tr.object_id IN ($placeholders) AND tt.taxonomy = %s",
			array_merge( $post_ids, array( $taxonomy ) )
		);

		$results = $wpdb->get_results( $query );
		$terms   = array();

		foreach ( $results as $row ) {
			$post_id = $row->object_id;
			if ( ! isset( $terms[ $post_id ] ) ) {
				$terms[ $post_id ] = array();
			}
			$terms[ $post_id ][] = array(
				'term_id' => $row->term_id,
				'name'    => $row->name,
				'slug'    => $row->slug,
			);
		}

		return $terms;
	}

	/**
	 * Batch fetch thumbnail URLs for multiple posts.
	 * Reduces N queries to 2 queries.
	 *
	 * @param array  $post_ids Array of post IDs.
	 * @param string $size     Image size.
	 * @return array Associative array of post_id => thumbnail_url.
	 */
	public static function batch_get_thumbnails( $post_ids, $size = 'medium' ) {
		global $wpdb;

		if ( empty( $post_ids ) ) {
			return array();
		}

		// First, get thumbnail IDs.
		$thumbnail_ids = self::batch_get_post_meta( $post_ids, '_thumbnail_id' );

		if ( empty( $thumbnail_ids ) ) {
			return array();
		}

		// Get attachment URLs.
		$attachment_ids = array_filter( array_values( $thumbnail_ids ) );

		if ( empty( $attachment_ids ) ) {
			return array();
		}

		// Prime attachment cache.
		_prime_post_caches( $attachment_ids, false, false );

		// Build URL map.
		$urls = array();
		foreach ( $thumbnail_ids as $post_id => $attachment_id ) {
			if ( $attachment_id ) {
				$url = wp_get_attachment_image_url( $attachment_id, $size );
				if ( $url ) {
					$urls[ $post_id ] = $url;
				}
			}
		}

		return $urls;
	}

	/**
	 * Batch add cover images to lists array.
	 * For lists without cover_image_id, fetches first item's thumbnail.
	 *
	 * @param array $lists Array of list data.
	 * @return array Lists with cover_image added.
	 */
	private static function batch_add_cover_images( $lists ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		if ( empty( $lists ) ) {
			return $lists;
		}

		// Separate lists with and without custom cover images.
		$lists_with_cover    = array();
		$lists_needing_cover = array();

		foreach ( $lists as $index => $list ) {
			if ( ! empty( $list['cover_image_id'] ) ) {
				$lists_with_cover[ $index ] = $list['cover_image_id'];
			} else {
				$lists_needing_cover[ $index ] = $list['id'];
			}
		}

		// Get custom cover image URLs.
		foreach ( $lists_with_cover as $index => $attachment_id ) {
			$lists[ $index ]['cover_image'] = wp_get_attachment_image_url( $attachment_id, 'medium' );
		}

		// For lists without custom cover, get first item's thumbnail.
		if ( ! empty( $lists_needing_cover ) ) {
			$list_ids     = array_values( $lists_needing_cover );
			$placeholders = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );

			// Get first business_id for each list (by lowest sort_order).
			$first_items_sql = $wpdb->prepare(
				"SELECT li.list_id, li.business_id 
				 FROM $items_table li
				 INNER JOIN (
				     SELECT list_id, MIN(sort_order) as min_order 
				     FROM $items_table 
				     WHERE list_id IN ($placeholders) 
				     GROUP BY list_id
				 ) first_items ON li.list_id = first_items.list_id AND li.sort_order = first_items.min_order
				 WHERE li.list_id IN ($placeholders)",
				array_merge( $list_ids, $list_ids )
			);

			$first_items = $wpdb->get_results( $first_items_sql, ARRAY_A );

			if ( ! empty( $first_items ) ) {
				// Get business IDs and fetch their thumbnails.
				$business_ids = wp_list_pluck( $first_items, 'business_id' );
				$thumbnails   = self::batch_get_thumbnails( $business_ids, 'medium' );

				// Map list_id => business_id => thumbnail.
				$list_to_business = array();
				foreach ( $first_items as $item ) {
					$list_to_business[ $item['list_id'] ] = $item['business_id'];
				}

				// Apply to lists.
				foreach ( $lists_needing_cover as $index => $list_id ) {
					$business_id                    = $list_to_business[ $list_id ] ?? null;
					$lists[ $index ]['cover_image'] = $business_id ? ( $thumbnails[ $business_id ] ?? null ) : null;
				}
			} else {
				// No items in these lists.
				foreach ( $lists_needing_cover as $index => $list_id ) {
					$lists[ $index ]['cover_image'] = null;
				}
			}
		}

		return $lists;
	}

	// =========================================================================
	// CACHE INVALIDATION
	// =========================================================================

	/**
	 * Invalidate all caches related to a list.
	 * Call this when list data changes.
	 *
	 * @param int $list_id List ID.
	 */
	public static function invalidate_list_cache( $list_id ) {
		// Clear WordPress object cache.
		wp_cache_delete( 'bd_list_' . $list_id, 'bd_lists' );
		wp_cache_delete( 'bd_list_items_' . $list_id, 'bd_lists' );

		// Clear any transients.
		delete_transient( 'bd_list_route_' . $list_id );
	}
}
