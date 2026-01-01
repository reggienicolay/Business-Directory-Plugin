<?php
/**
 * Frontend Editor Installation
 *
 * Creates database tables and initializes components.
 * Call this from your main plugin activation hook.
 *
 * @package BusinessDirectory
 */

namespace BD\Install;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\DB\ChangeRequestsTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrontendEditorInstaller
 */
class FrontendEditorInstaller {

	/**
	 * Run installation.
	 */
	public static function install() {
		self::create_tables();
		self::add_capabilities();
		self::schedule_cleanup();
	}

	/**
	 * Create database tables.
	 */
	private static function create_tables() {
		ChangeRequestsTable::create_table();
	}

	/**
	 * Add capabilities to roles.
	 */
	private static function add_capabilities() {
		// Admin can manage change requests.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'bd_manage_change_requests' );
		}

		// Directory manager can also manage change requests.
		$manager = get_role( 'bd_manager' );
		if ( $manager ) {
			$manager->add_cap( 'bd_manage_change_requests' );
		}
	}

	/**
	 * Schedule cleanup cron job.
	 */
	private static function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'bd_cleanup_change_requests' ) ) {
			wp_schedule_event( time(), 'daily', 'bd_cleanup_change_requests' );
		}
	}

	/**
	 * Run cleanup of old requests.
	 */
	public static function cleanup() {
		ChangeRequestsTable::cleanup( 90 ); // Delete requests older than 90 days.
	}

	/**
	 * Uninstall.
	 */
	public static function uninstall() {
		global $wpdb;

		// Drop table.
		$table = $wpdb->prefix . 'bd_change_requests';
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'bd_cleanup_change_requests' );

		// Remove capabilities.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( 'bd_manage_change_requests' );
		}

		$manager = get_role( 'bd_manager' );
		if ( $manager ) {
			$manager->remove_cap( 'bd_manage_change_requests' );
		}
	}
}

// Hook up the cleanup cron.
add_action( 'bd_cleanup_change_requests', array( 'BD\\Install\\FrontendEditorInstaller', 'cleanup' ) );
