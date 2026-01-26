<?php
/**
 * Cover Media Database Migration
 *
 * Adds columns to wp_bd_lists for cover media support:
 * - cover_original_id: Original uploaded image for re-cropping
 * - cover_crop_data: JSON with crop coordinates (percentage-based)
 * - cover_type: 'image', 'youtube', 'vimeo', or 'auto'
 * - cover_video_id: YouTube/Vimeo video ID
 * - cover_video_thumb_id: Local attachment ID for video thumbnail
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

namespace BD\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoverMediaMigration {

	/**
	 * Migration version
	 */
	const VERSION = '1.2.1';

	/**
	 * Option name for tracking migration version
	 */
	const VERSION_OPTION = 'bd_cover_media_version';

	/**
	 * Required columns for cover media feature
	 */
	const REQUIRED_COLUMNS = array(
		'cover_original_id',
		'cover_crop_data',
		'cover_type',
		'cover_video_id',
		'cover_video_thumb_id',
	);

	/**
	 * Run migration if needed
	 */
	public static function maybe_migrate() {
		$current_version = get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $current_version, self::VERSION, '<' ) ) {
			self::migrate();
			update_option( self::VERSION_OPTION, self::VERSION );
		}
	}

	/**
	 * Run the migration
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function migrate() {
		global $wpdb;

		$table = $wpdb->prefix . 'bd_lists';

		// Check if table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
				DB_NAME,
				$table
			)
		);

		if ( ! $table_exists ) {
			error_log( 'BD Cover Media Migration: Table ' . $table . ' does not exist' );
			return false;
		}

		// Check which columns already exist
		$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

		$columns_to_add = array();

		// cover_original_id - stores original pre-crop image for re-cropping
		if ( ! in_array( 'cover_original_id', $existing_columns, true ) ) {
			$columns_to_add[] = 'ADD COLUMN `cover_original_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `cover_image_id`';
		}

		// cover_crop_data - JSON with percentage-based crop coordinates
		if ( ! in_array( 'cover_crop_data', $existing_columns, true ) ) {
			$columns_to_add[] = 'ADD COLUMN `cover_crop_data` JSON DEFAULT NULL AFTER `cover_original_id`';
		}

		// cover_type - 'image', 'youtube', 'vimeo', or 'auto'
		if ( ! in_array( 'cover_type', $existing_columns, true ) ) {
			$columns_to_add[] = "ADD COLUMN `cover_type` VARCHAR(20) DEFAULT 'auto' AFTER `cover_crop_data`";
		}

		// cover_video_id - YouTube/Vimeo video ID
		if ( ! in_array( 'cover_video_id', $existing_columns, true ) ) {
			$columns_to_add[] = 'ADD COLUMN `cover_video_id` VARCHAR(50) DEFAULT NULL AFTER `cover_type`';
		}

		// cover_video_thumb_id - Local attachment for video thumbnail
		if ( ! in_array( 'cover_video_thumb_id', $existing_columns, true ) ) {
			$columns_to_add[] = 'ADD COLUMN `cover_video_thumb_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `cover_video_id`';
		}

		// Run ALTER TABLE if there are columns to add
		if ( ! empty( $columns_to_add ) ) {
			$sql = "ALTER TABLE `{$table}` " . implode( ', ', $columns_to_add );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $sql );

			if ( false === $result ) {
				error_log( 'BD Cover Media Migration: Column addition failed - ' . $wpdb->last_error );
				return false;
			}

			error_log( 'BD Cover Media Migration: Added columns - ' . implode( ', ', array_keys( $columns_to_add ) ) );
		}

		// Add indexes
		self::add_indexes( $table );

		error_log( 'BD Cover Media Migration completed successfully (v' . self::VERSION . ')' );

		return true;
	}

	/**
	 * Add indexes for performance
	 *
	 * @param string $table Table name.
	 */
	private static function add_indexes( $table ) {
		global $wpdb;

		$indexes_to_add = array(
			'idx_cover_type'     => 'cover_type',
			'idx_cover_video_id' => 'cover_video_id',
		);

		foreach ( $indexes_to_add as $index_name => $column ) {
			// Check if index exists
			$index_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM information_schema.statistics 
					 WHERE table_schema = %s AND table_name = %s AND index_name = %s",
					DB_NAME,
					$table,
					$index_name
				)
			);

			if ( ! $index_exists ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result = $wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `{$index_name}` (`{$column}`)" );

				if ( false === $result ) {
					error_log( 'BD Cover Media Migration: Failed to add index ' . $index_name . ' - ' . $wpdb->last_error );
				} else {
					error_log( 'BD Cover Media Migration: Added index ' . $index_name );
				}
			}
		}
	}

	/**
	 * Check if migration has been run
	 *
	 * @return bool True if migrated.
	 */
	public static function is_migrated() {
		$current_version = get_option( self::VERSION_OPTION, '0' );
		return version_compare( $current_version, self::VERSION, '>=' );
	}

	/**
	 * Get migration status for admin display
	 *
	 * @return array Status information.
	 */
	public static function get_status() {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		// Check if table exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
				DB_NAME,
				$table
			)
		);

		if ( ! $table_exists ) {
			return array(
				'version'  => get_option( self::VERSION_OPTION, '0' ),
				'required' => self::VERSION,
				'columns'  => array(),
				'indexes'  => array(),
				'complete' => false,
				'error'    => 'Table does not exist',
			);
		}

		$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

		$status = array(
			'version'  => get_option( self::VERSION_OPTION, '0' ),
			'required' => self::VERSION,
			'columns'  => array(),
			'indexes'  => array(),
		);

		// Check columns
		foreach ( self::REQUIRED_COLUMNS as $col ) {
			$status['columns'][ $col ] = in_array( $col, $existing_columns, true );
		}

		// Check indexes
		$indexes = array( 'idx_cover_type', 'idx_cover_video_id' );
		foreach ( $indexes as $index ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM information_schema.statistics 
					 WHERE table_schema = %s AND table_name = %s AND index_name = %s",
					DB_NAME,
					$table,
					$index
				)
			);
			$status['indexes'][ $index ] = (bool) $exists;
		}

		$status['complete'] = ! in_array( false, $status['columns'], true ) 
			&& ! in_array( false, $status['indexes'], true );

		return $status;
	}

	/**
	 * Force re-run migration (for admin use)
	 *
	 * @return bool Success.
	 */
	public static function force_migrate() {
		delete_option( self::VERSION_OPTION );
		return self::migrate();
	}
}
