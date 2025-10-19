<?php
/**
 * Add database indexes for search performance
 * This file is called during plugin activation
 */

function bd_add_database_indexes() {
	global $wpdb;

	// Check if indexes already exist to avoid duplicate key errors
	$existing_indexes = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts}" );
	$index_exists     = false;

	foreach ( $existing_indexes as $index ) {
		if ( $index->Key_name === 'idx_bd_business_date' ) {
			$index_exists = true;
			break;
		}
	}

	if ( ! $index_exists ) {
		// Add index for business post type queries
		$wpdb->query(
			"
            ALTER TABLE {$wpdb->posts}
            ADD INDEX idx_bd_business_date (post_type(20), post_date, post_status(20))
        "
		);
	}

	// Check postmeta indexes
	$existing_meta_indexes = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->postmeta}" );
	$meta_index_exists     = false;

	foreach ( $existing_meta_indexes as $index ) {
		if ( $index->Key_name === 'idx_bd_meta_search' ) {
			$meta_index_exists = true;
			break;
		}
	}

	if ( ! $meta_index_exists ) {
		// Add index for meta queries (rating, price level)
		$wpdb->query(
			"
            ALTER TABLE {$wpdb->postmeta}
            ADD INDEX idx_bd_meta_search (meta_key(191), meta_value(20))
        "
		);
	}
}

/**
 * Remove indexes on plugin deactivation
 */
function bd_remove_database_indexes() {
	global $wpdb;

	// Remove posts index
	$wpdb->query(
		"
        ALTER TABLE {$wpdb->posts}
        DROP INDEX IF EXISTS idx_bd_business_date
    "
	);

	// Remove postmeta index
	$wpdb->query(
		"
        ALTER TABLE {$wpdb->postmeta}
        DROP INDEX IF EXISTS idx_bd_meta_search
    "
	);
}
