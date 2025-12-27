<?php
/**
 * Database Installer
 *
 * Creates and upgrades database tables.
 *
 * @package BusinessDirectory
 * @version 2.3.0
 */

namespace BD\DB;

class Installer {

	/**
	 * Database version - bump this when schema changes.
	 */
	const DB_VERSION = '2.3.0';

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::create_roles();
		self::flush_rewrite_rules();

		$current_version = get_option( 'bd_db_version', '1.0.0' );
		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			self::upgrade_database( $current_version );
		}

		update_option( 'bd_db_version', self::DB_VERSION );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create all database tables.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// =====================================================================
		// LOCATIONS TABLE
		// =====================================================================
		$locations_table = $wpdb->prefix . 'bd_locations';
		$locations_sql   = "CREATE TABLE IF NOT EXISTS $locations_table (
			business_id bigint(20) UNSIGNED NOT NULL,
			lat double NOT NULL,
			lng double NOT NULL,
			geohash char(12) DEFAULT NULL,
			address varchar(255) DEFAULT NULL,
			city varchar(120) DEFAULT NULL,
			state varchar(80) DEFAULT NULL,
			postal_code varchar(20) DEFAULT NULL,
			country varchar(80) DEFAULT NULL,
			PRIMARY KEY (business_id),
			KEY idx_lat (lat),
			KEY idx_lng (lng),
			KEY idx_geohash (geohash)
		) $charset_collate;";

		// =====================================================================
		// REVIEWS TABLE
		// =====================================================================
		$reviews_table = $wpdb->prefix . 'bd_reviews';
		$reviews_sql   = "CREATE TABLE IF NOT EXISTS $reviews_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			business_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			author_name varchar(120) DEFAULT NULL,
			author_email varchar(120) DEFAULT NULL,
			rating tinyint(1) UNSIGNED NOT NULL,
			title varchar(180) DEFAULT NULL,
			content text DEFAULT NULL,
			photo_ids text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_business (business_id),
			KEY idx_status (status),
			KEY idx_created (created_at)
		) $charset_collate;";

		// =====================================================================
		// SUBMISSIONS TABLE
		// =====================================================================
		$submissions_table = $wpdb->prefix . 'bd_submissions';
		$submissions_sql   = "CREATE TABLE IF NOT EXISTS $submissions_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			business_data longtext NOT NULL,
			submitted_by bigint(20) UNSIGNED DEFAULT NULL,
			submitter_name varchar(120) DEFAULT NULL,
			submitter_email varchar(120) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			admin_notes text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status (status),
			KEY idx_created (created_at)
		) $charset_collate;";

		// =====================================================================
		// GAMIFICATION: USER REPUTATION TABLE
		// =====================================================================
		$user_reputation_table = $wpdb->prefix . 'bd_user_reputation';
		$user_reputation_sql   = "CREATE TABLE IF NOT EXISTS $user_reputation_table (
			user_id bigint(20) UNSIGNED NOT NULL,
			total_points int(11) NOT NULL DEFAULT 0,
			total_reviews int(11) NOT NULL DEFAULT 0,
			helpful_votes int(11) NOT NULL DEFAULT 0,
			lists_created int(11) NOT NULL DEFAULT 0,
			photos_uploaded int(11) NOT NULL DEFAULT 0,
			categories_reviewed int(11) NOT NULL DEFAULT 0,
			badges text DEFAULT NULL,
			badge_count int(11) NOT NULL DEFAULT 0,
			current_rank varchar(50) NOT NULL DEFAULT 'newcomer',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (user_id),
			KEY idx_points (total_points),
			KEY idx_rank (current_rank)
		) $charset_collate;";

		// =====================================================================
		// GAMIFICATION: USER ACTIVITY TABLE
		// =====================================================================
		$user_activity_table = $wpdb->prefix . 'bd_user_activity';
		$user_activity_sql   = "CREATE TABLE IF NOT EXISTS $user_activity_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			activity_type varchar(50) NOT NULL,
			points int(11) NOT NULL DEFAULT 0,
			reference_id bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user (user_id),
			KEY idx_type (activity_type),
			KEY idx_created (created_at)
		) $charset_collate;";

		// =====================================================================
		// GAMIFICATION: BADGE AWARDS TABLE
		// =====================================================================
		$badge_awards_table = $wpdb->prefix . 'bd_badge_awards';
		$badge_awards_sql   = "CREATE TABLE IF NOT EXISTS $badge_awards_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			badge_key varchar(100) NOT NULL,
			awarded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			awarded_by bigint(20) UNSIGNED DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idx_user_badge (user_id, badge_key),
			KEY idx_user (user_id),
			KEY idx_badge (badge_key),
			KEY idx_awarded (awarded_at)
		) $charset_collate;";

		// =====================================================================
		// LISTS TABLE (with collaborative columns)
		// =====================================================================
		$lists_table = $wpdb->prefix . 'bd_lists';
		$lists_sql   = "CREATE TABLE IF NOT EXISTS $lists_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			title varchar(200) NOT NULL,
			slug varchar(200) NOT NULL,
			description text DEFAULT NULL,
			cover_image_id bigint(20) UNSIGNED DEFAULT NULL,
			visibility varchar(20) NOT NULL DEFAULT 'private',
			featured tinyint(1) NOT NULL DEFAULT 0,
			view_count int(11) NOT NULL DEFAULT 0,
			invite_token varchar(64) DEFAULT NULL,
			invite_mode varchar(20) DEFAULT 'approval',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_slug (slug),
			KEY idx_user (user_id),
			KEY idx_visibility (visibility),
			KEY idx_featured (featured),
			KEY idx_updated (updated_at),
			KEY idx_invite_token (invite_token)
		) $charset_collate;";

		// =====================================================================
		// LIST ITEMS TABLE
		// =====================================================================
		$list_items_table = $wpdb->prefix . 'bd_list_items';
		$list_items_sql   = "CREATE TABLE IF NOT EXISTS $list_items_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			list_id bigint(20) UNSIGNED NOT NULL,
			business_id bigint(20) UNSIGNED NOT NULL,
			user_note text DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			added_by bigint(20) UNSIGNED DEFAULT NULL,
			added_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_list_business (list_id, business_id),
			KEY idx_list (list_id),
			KEY idx_business (business_id),
			KEY idx_sort (sort_order),
			KEY idx_added_by (added_by)
		) $charset_collate;";

		// =====================================================================
		// LIST COLLABORATORS TABLE (NEW in 2.3.0)
		// =====================================================================
		$collaborators_table = $wpdb->prefix . 'bd_list_collaborators';
		$collaborators_sql   = "CREATE TABLE IF NOT EXISTS $collaborators_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			list_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			role varchar(20) NOT NULL DEFAULT 'contributor',
			status varchar(20) NOT NULL DEFAULT 'pending',
			added_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			added_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_list_user (list_id, user_id),
			KEY idx_user (user_id),
			KEY idx_status (status),
			KEY idx_list_status (list_id, status)
		) $charset_collate;";

		// =====================================================================
		// LIST FOLLOWS TABLE
		// =====================================================================
		$list_follows_table = $wpdb->prefix . 'bd_list_follows';
		$list_follows_sql   = "CREATE TABLE IF NOT EXISTS $list_follows_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			list_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			followed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_list_user (list_id, user_id),
			KEY idx_user (user_id),
			KEY idx_list (list_id)
		) $charset_collate;";

		// Run all table creations.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $locations_sql );
		dbDelta( $reviews_sql );
		dbDelta( $submissions_sql );
		dbDelta( $user_reputation_sql );
		dbDelta( $user_activity_sql );
		dbDelta( $badge_awards_sql );
		dbDelta( $lists_sql );
		dbDelta( $list_items_sql );
		dbDelta( $collaborators_sql );
		dbDelta( $list_follows_sql );
	}

	/**
	 * Handle database upgrades between versions.
	 *
	 * @param string $from_version Previous version.
	 */
	private static function upgrade_database( $from_version ) {
		global $wpdb;

		// Upgrade from pre-2.0.0: Add missing review columns.
		if ( version_compare( $from_version, '2.0.0', '<' ) ) {
			$reviews_table = $wpdb->prefix . 'bd_reviews';

			$columns      = $wpdb->get_results( "SHOW COLUMNS FROM $reviews_table" );
			$column_names = array_column( $columns, 'Field' );

			if ( ! in_array( 'photo_ids', $column_names, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE $reviews_table ADD COLUMN photo_ids text DEFAULT NULL AFTER content" );
			}
			if ( ! in_array( 'author_email', $column_names, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE $reviews_table ADD COLUMN author_email varchar(120) DEFAULT NULL AFTER author_name" );
			}
			if ( ! in_array( 'ip_address', $column_names, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE $reviews_table ADD COLUMN ip_address varchar(45) DEFAULT NULL AFTER status" );
			}
		}

		// Upgrade from pre-2.1.0: Create gamification tables.
		if ( version_compare( $from_version, '2.1.0', '<' ) ) {
			self::create_tables();
		}

		// Upgrade from pre-2.3.0: Add collaborative lists columns and table.
		if ( version_compare( $from_version, '2.3.0', '<' ) ) {
			$lists_table = $wpdb->prefix . 'bd_lists';

			// Check and add invite_token column.
			$columns      = $wpdb->get_results( "SHOW COLUMNS FROM $lists_table" );
			$column_names = array_column( $columns, 'Field' );

			if ( ! in_array( 'invite_token', $column_names, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE $lists_table ADD COLUMN invite_token varchar(64) DEFAULT NULL" );
			}

			if ( ! in_array( 'invite_mode', $column_names, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE $lists_table ADD COLUMN invite_mode varchar(20) DEFAULT 'approval'" );
			}

			// Add index for invite_token if not exists.
			$indexes = $wpdb->get_results( "SHOW INDEX FROM $lists_table WHERE Key_name = 'idx_invite_token'" );
			if ( empty( $indexes ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE $lists_table ADD KEY idx_invite_token (invite_token)" );
			}

			// Check and add added_by column to list_items.
			$list_items_table = $wpdb->prefix . 'bd_list_items';
			$item_columns     = $wpdb->get_results( "SHOW COLUMNS FROM $list_items_table" );
			$item_col_names   = array_column( $item_columns, 'Field' );

			if ( ! in_array( 'added_by', $item_col_names, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE $list_items_table ADD COLUMN added_by bigint(20) UNSIGNED DEFAULT NULL AFTER sort_order" );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE $list_items_table ADD KEY idx_added_by (added_by)" );
			}

			// Create collaborators table (create_tables handles IF NOT EXISTS).
			self::create_tables();
		}
	}

	/**
	 * Create custom roles.
	 */
	private static function create_roles() {
		\BD\Roles\Manager::create();
	}

	/**
	 * Flush rewrite rules after registering post types.
	 */
	private static function flush_rewrite_rules() {
		\BD\PostTypes\Business::register();
		\BD\Taxonomies\Category::register();
		\BD\Taxonomies\Area::register();
		\BD\Taxonomies\Tag::register();
		flush_rewrite_rules();
	}
}
