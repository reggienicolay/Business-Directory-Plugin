<?php
/**
 * Change Requests Database Table
 *
 * Handles storage and retrieval of business edit requests.
 *
 * @package BusinessDirectory
 */

namespace BD\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ChangeRequestsTable
 */
class ChangeRequestsTable {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_NAME = 'bd_change_requests';

	/**
	 * Get full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			business_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
			changes_json LONGTEXT NOT NULL,
			original_json LONGTEXT DEFAULT NULL,
			change_summary TEXT DEFAULT NULL,
			admin_notes TEXT DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			reviewed_at DATETIME DEFAULT NULL,
			reviewed_by BIGINT UNSIGNED DEFAULT NULL,
			INDEX idx_business (business_id),
			INDEX idx_user (user_id),
			INDEX idx_status (status),
			INDEX idx_created (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a new change request.
	 *
	 * @param int    $business_id   Business post ID.
	 * @param int    $user_id       User ID submitting the change.
	 * @param array  $changes       Array of changed fields.
	 * @param array  $original      Array of original values.
	 * @param string $summary       Human-readable summary of changes.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function insert( $business_id, $user_id, $changes, $original, $summary = '' ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::get_table_name(),
			array(
				'business_id'    => $business_id,
				'user_id'        => $user_id,
				'status'         => 'pending',
				'changes_json'   => wp_json_encode( $changes ),
				'original_json'  => wp_json_encode( $original ),
				'change_summary' => $summary,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a change request by ID.
	 *
	 * @param int $request_id Request ID.
	 * @return array|null Request data or null.
	 */
	public static function get( $request_id ) {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $request_id ),
			ARRAY_A
		);

		if ( $row ) {
			$row['changes']  = json_decode( $row['changes_json'], true );
			$row['original'] = json_decode( $row['original_json'], true );
		}

		return $row;
	}

	/**
	 * Get pending requests.
	 *
	 * @param int $limit Optional limit.
	 * @return array Array of pending requests.
	 */
	public static function get_pending( $limit = 50 ) {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$row['changes']  = json_decode( $row['changes_json'], true );
			$row['original'] = json_decode( $row['original_json'], true );
		}

		return $rows;
	}

	/**
	 * Get requests by business ID.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $status      Optional status filter.
	 * @return array Array of requests.
	 */
	public static function get_by_business( $business_id, $status = null ) {
		global $wpdb;

		$table = self::get_table_name();

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE business_id = %d AND status = %s ORDER BY created_at DESC",
					$business_id,
					$status
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE business_id = %d ORDER BY created_at DESC",
					$business_id
				),
				ARRAY_A
			);
		}

		foreach ( $rows as &$row ) {
			$row['changes']  = json_decode( $row['changes_json'], true );
			$row['original'] = json_decode( $row['original_json'], true );
		}

		return $rows;
	}

	/**
	 * Get requests by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of requests.
	 */
	public static function get_by_user( $user_id ) {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$row['changes']  = json_decode( $row['changes_json'], true );
			$row['original'] = json_decode( $row['original_json'], true );
		}

		return $rows;
	}

	/**
	 * Check if business has pending request.
	 *
	 * @param int $business_id Business ID.
	 * @return bool True if pending request exists.
	 */
	public static function has_pending( $business_id ) {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND status = 'pending'",
				$business_id
			)
		);

		return $count > 0;
	}

	/**
	 * Count pending requests.
	 *
	 * @return int Number of pending requests.
	 */
	public static function count_pending() {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
	}

	/**
	 * Approve a change request.
	 *
	 * @param int    $request_id Request ID.
	 * @param int    $admin_id   Admin user ID.
	 * @param string $notes      Optional admin notes.
	 * @return bool Success.
	 */
	public static function approve( $request_id, $admin_id, $notes = '' ) {
		global $wpdb;

		$request = self::get( $request_id );
		if ( ! $request || 'pending' !== $request['status'] ) {
			return false;
		}

		// Apply the changes to the business.
		$applied = self::apply_changes( $request['business_id'], $request['changes'] );

		if ( ! $applied ) {
			return false;
		}

		// Update request status.
		$result = $wpdb->update(
			self::get_table_name(),
			array(
				'status'      => 'approved',
				'admin_notes' => $notes,
				'reviewed_at' => current_time( 'mysql' ),
				'reviewed_by' => $admin_id,
			),
			array( 'id' => $request_id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Reject a change request.
	 *
	 * @param int    $request_id Request ID.
	 * @param int    $admin_id   Admin user ID.
	 * @param string $notes      Rejection reason (required).
	 * @return bool Success.
	 */
	public static function reject( $request_id, $admin_id, $notes ) {
		global $wpdb;

		$result = $wpdb->update(
			self::get_table_name(),
			array(
				'status'      => 'rejected',
				'admin_notes' => $notes,
				'reviewed_at' => current_time( 'mysql' ),
				'reviewed_by' => $admin_id,
			),
			array( 'id' => $request_id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Apply changes to a business post.
	 *
	 * @param int   $business_id Business post ID.
	 * @param array $changes     Array of changes to apply.
	 * @return bool Success.
	 */
	private static function apply_changes( $business_id, $changes ) {
		$business = get_post( $business_id );
		if ( ! $business ) {
			return false;
		}

		// Prepare post update array.
		$post_update = array( 'ID' => $business_id );

		// Handle title change.
		if ( isset( $changes['title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( $changes['title'] );
		}

		// Handle description change.
		if ( isset( $changes['description'] ) ) {
			$post_update['post_content'] = wp_kses_post( $changes['description'] );
		}

		// Update post if needed.
		if ( count( $post_update ) > 1 ) {
			$result = wp_update_post( $post_update, true );
			if ( is_wp_error( $result ) ) {
				return false;
			}
		}

		// Handle meta fields.
		$meta_fields = array(
			'contact'  => 'bd_contact',
			'location' => 'bd_location',
			'hours'    => 'bd_hours',
			'social'   => 'bd_social',
			'features' => 'bd_features',
		);

		foreach ( $meta_fields as $change_key => $meta_key ) {
			if ( isset( $changes[ $change_key ] ) ) {
				update_post_meta( $business_id, $meta_key, $changes[ $change_key ] );
			}
		}

		// Handle categories.
		if ( isset( $changes['categories'] ) && is_array( $changes['categories'] ) ) {
			wp_set_object_terms( $business_id, array_map( 'intval', $changes['categories'] ), 'bd_category' );
		}

		// Handle tags.
		if ( isset( $changes['tags'] ) && is_array( $changes['tags'] ) ) {
			wp_set_object_terms( $business_id, array_map( 'intval', $changes['tags'] ), 'bd_tag' );
		}

		// Handle photos.
		if ( isset( $changes['photos'] ) && is_array( $changes['photos'] ) ) {
			update_post_meta( $business_id, 'bd_photos', array_map( 'intval', $changes['photos'] ) );
		}

		// Handle featured image.
		if ( isset( $changes['featured_image'] ) ) {
			if ( $changes['featured_image'] ) {
				set_post_thumbnail( $business_id, intval( $changes['featured_image'] ) );
			} else {
				delete_post_thumbnail( $business_id );
			}
		}

		// Trigger action for extensions.
		do_action( 'bd_changes_applied', $business_id, $changes );

		return true;
	}

	/**
	 * Delete old rejected/approved requests (cleanup).
	 *
	 * @param int $days_old Delete requests older than this many days.
	 * @return int Number of deleted rows.
	 */
	public static function cleanup( $days_old = 90 ) {
		global $wpdb;

		$table = self::get_table_name();
		$date  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ('approved', 'rejected') AND reviewed_at < %s",
				$date
			)
		);
	}
}
