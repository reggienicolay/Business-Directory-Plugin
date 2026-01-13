<?php
/**
 * Profile Template
 *
 * Template for displaying user profiles at /profile/username/
 *
 * @package BusinessDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$user_id = get_query_var( 'bd_profile_user_id' );

if ( $user_id ) {
	echo \BD\Frontend\Profile::render_profile( $user_id );
} else {
	echo '<p>' . esc_html__( 'User not found.', 'business-directory' ) . '</p>';
}

get_footer();
