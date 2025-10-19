<?php
/**
 * Uninstall handler
 * Fires when plugin is deleted via WordPress admin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bd_locations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bd_reviews" );

// Delete all businesses
$businesses = get_posts(
	array(
		'post_type'      => 'bd_business',
		'posts_per_page' => -1,
		'post_status'    => 'any',
	)
);

foreach ( $businesses as $business ) {
	wp_delete_post( $business->ID, true );
}

// Delete taxonomies
$taxonomies = array( 'bd_category', 'bd_area', 'bd_tag' );
foreach ( $taxonomies as $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);
	foreach ( $terms as $term ) {
		wp_delete_term( $term->term_id, $taxonomy );
	}
}

// Delete plugin options
delete_option( 'bd_db_version' );
delete_option( 'bd_settings' );

// Delete transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bd_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bd_%'" );

// Remove custom role
remove_role( 'bd_manager' );
