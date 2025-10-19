<?php
namespace BD\DB;

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
			'user_id'        => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
			'claimant_name'  => sanitize_text_field( $data['claimant_name'] ),
			'claimant_email' => sanitize_email( $data['claimant_email'] ),
			'claimant_phone' => isset( $data['claimant_phone'] ) ? sanitize_text_field( $data['claimant_phone'] ) : null,
			'relationship'   => isset( $data['relationship'] ) ? sanitize_text_field( $data['relationship'] ) : null,
			'proof_files'    => isset( $data['proof_files'] ) ? wp_json_encode( $data['proof_files'] ) : null,
			'message'        => isset( $data['message'] ) ? sanitize_textarea_field( $data['message'] ) : null,
		);

		$result = $wpdb->insert(
			self::table(),
			$clean_data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

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

		return (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . self::table() . " WHERE status = 'pending'"
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
