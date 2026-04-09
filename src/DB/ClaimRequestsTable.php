<?php

namespace BD\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Claim Requests Table Handler
 * Manages business claiming requests
 */
class ClaimRequestsTable {

	/**
	 * Get table name
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bd_claim_requests';
	}

	/**
	 * Create table (call during plugin activation)
	 */
	public static function create_table() {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            claimant_name varchar(120) NOT NULL,
            claimant_email varchar(120) NOT NULL,
            claimant_phone varchar(20) DEFAULT NULL,
            relationship varchar(50) DEFAULT NULL,
            proof_files longtext DEFAULT NULL,
            message text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            admin_notes text DEFAULT NULL,
            reviewed_by bigint(20) UNSIGNED DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_business (business_id),
            KEY idx_user (user_id),
            KEY idx_email (claimant_email),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a new claim request
	 */
	public static function insert( $data ) {
		global $wpdb;

		$clean_data = array(
			'business_id'    => absint( $data['business_id'] ),
			'claimant_name'  => sanitize_text_field( $data['claimant_name'] ),
			'claimant_email' => sanitize_email( $data['claimant_email'] ),
			'claimant_phone' => isset( $data['claimant_phone'] ) ? sanitize_text_field( $data['claimant_phone'] ) : null,
			'relationship'   => isset( $data['relationship'] ) ? sanitize_text_field( $data['relationship'] ) : null,
			'proof_files'    => isset( $data['proof_files'] ) ? wp_json_encode( $data['proof_files'] ) : null,
			'message'        => isset( $data['message'] ) ? sanitize_textarea_field( $data['message'] ) : null,
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		// Only include user_id when it has a value — avoids inserting 0 for anonymous users.
		if ( ! empty( $data['user_id'] ) ) {
			$clean_data['user_id'] = absint( $data['user_id'] );
			$format[]              = '%d';
		}

		$result = $wpdb->insert( self::table(), $clean_data, $format );

		if ( false === $result ) {
			error_log( '[BD Claims] Database insert failed: ' . $wpdb->last_error );
		}

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a single claim request
	 */
	public static function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE id = %d',
				absint( $id )
			),
			ARRAY_A
		);

		if ( $row && ! empty( $row['proof_files'] ) ) {
			$row['proof_files'] = json_decode( $row['proof_files'], true );
		}

		return $row;
	}

	/**
	 * Get pending claim requests
	 */
	public static function get_pending( $limit = 50 ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " 
            WHERE status = 'pending' 
            ORDER BY created_at DESC 
            LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			if ( ! empty( $row['proof_files'] ) ) {
				$row['proof_files'] = json_decode( $row['proof_files'], true );
			}
		}

		return $rows;
	}

	/**
	 * Insert a claim row in an already-approved state (in-field grant).
	 *
	 * Used by the GrantAccess service when an admin (e.g. a directory manager in
	 * the field) authorises a known business owner without making them fill out
	 * the public claim form. Writes a full audit trail row: status=approved,
	 * reviewed_by + reviewed_at set, admin_notes populated, no proof_files.
	 *
	 * @param array $data {
	 *     Required keys: business_id, user_id, claimant_name, claimant_email, reviewed_by.
	 *     Optional:     claimant_phone, relationship ('owner'|'manager'|'staff'|'other'), admin_notes.
	 * }
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function insert_granted( $data ) {
		global $wpdb;

		$business_id = absint( $data['business_id'] ?? 0 );
		$user_id     = absint( $data['user_id'] ?? 0 );
		$reviewed_by = absint( $data['reviewed_by'] ?? 0 );

		if ( ! $business_id || ! $user_id || ! $reviewed_by ) {
			return false;
		}

		$relationship = isset( $data['relationship'] ) ? sanitize_text_field( $data['relationship'] ) : 'owner';
		$allowed      = array( 'owner', 'manager', 'staff', 'other' );
		if ( ! in_array( $relationship, $allowed, true ) ) {
			$relationship = 'owner';
		}

		$clean = array(
			'business_id'    => $business_id,
			'user_id'        => $user_id,
			'claimant_name'  => sanitize_text_field( $data['claimant_name'] ?? '' ),
			'claimant_email' => sanitize_email( $data['claimant_email'] ?? '' ),
			'claimant_phone' => isset( $data['claimant_phone'] ) ? sanitize_text_field( $data['claimant_phone'] ) : null,
			'relationship'   => $relationship,
			'proof_files'    => null,
			'message'        => null,
			'status'         => 'approved',
			'admin_notes'    => isset( $data['admin_notes'] ) ? sanitize_textarea_field( $data['admin_notes'] ) : null,
			'reviewed_by'    => $reviewed_by,
			'reviewed_at'    => current_time( 'mysql' ),
		);

		$format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( self::table(), $clean, $format );

		if ( false === $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( '[BD Claims] insert_granted failed: ' . $wpdb->last_error );
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find an existing approved claim row for a (business, user) pair.
	 *
	 * Used to de-dupe before creating a new grant row.
	 *
	 * @param int $business_id Business post ID.
	 * @param int $user_id     WP user ID.
	 * @return array|null Row array or null if none.
	 */
	public static function get_approved_for_user( $business_id, $user_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, relationship, reviewed_at FROM ' . self::table() . " WHERE business_id = %d AND user_id = %d AND status = 'approved' ORDER BY reviewed_at DESC LIMIT 1",
				absint( $business_id ),
				absint( $user_id )
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Get claims by business ID
	 */
	public static function get_by_business( $business_id, $status = null ) {
		global $wpdb;

		$sql    = 'SELECT * FROM ' . self::table() . ' WHERE business_id = %d';
		$params = array( absint( $business_id ) );

		if ( $status ) {
			$sql     .= ' AND status = %s';
			$params[] = $status;
		}

		$sql .= ' ORDER BY created_at DESC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		foreach ( $rows as &$row ) {
			if ( ! empty( $row['proof_files'] ) ) {
				$row['proof_files'] = json_decode( $row['proof_files'], true );
			}
		}

		return $rows;
	}

	/**
	 * Get claim by email (check for duplicates)
	 */
	public static function get_by_email( $email, $business_id = null ) {
		global $wpdb;

		$sql    = 'SELECT * FROM ' . self::table() . ' WHERE claimant_email = %s';
		$params = array( sanitize_email( $email ) );

		if ( $business_id ) {
			$sql     .= ' AND business_id = %d';
			$params[] = absint( $business_id );
		}

		$sql .= ' ORDER BY created_at DESC LIMIT 1';

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );

		if ( $row && ! empty( $row['proof_files'] ) ) {
			$row['proof_files'] = json_decode( $row['proof_files'], true );
		}

		return $row;
	}

	/**
	 * Approve a claim request
	 */
	public static function approve( $id, $admin_id, $notes = '' ) {
		global $wpdb;

		$result = $wpdb->update(
			self::table(),
			array(
				'status'      => 'approved',
				'reviewed_by' => absint( $admin_id ),
				'reviewed_at' => current_time( 'mysql' ),
				'admin_notes' => sanitize_textarea_field( $notes ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Reject a claim request
	 */
	public static function reject( $id, $admin_id, $notes = '' ) {
		global $wpdb;

		$result = $wpdb->update(
			self::table(),
			array(
				'status'      => 'rejected',
				'reviewed_by' => absint( $admin_id ),
				'reviewed_at' => current_time( 'mysql' ),
				'admin_notes' => sanitize_textarea_field( $notes ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get count of pending claims
	 */
	public static function count_pending() {
		global $wpdb;
		$table = self::table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return 0;
		}
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"
		);
	}

	/**
	 * Delete old rejected claims (housekeeping)
	 */
	public static function delete_old_rejected( $days = 90 ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::table() . " 
            WHERE status = 'rejected' 
            AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				absint( $days )
			)
		);
	}
}
