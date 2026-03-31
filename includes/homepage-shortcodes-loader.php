<?php
/**
 * Homepage Shortcodes Loader
 *
 * Loads shortcodes used on the LoveTriValley homepage:
 * - [bd_search]         — standalone search box
 * - [bd_recent_reviews] — recent community reviews
 * - [bd_leaderboard]    — top contributors leaderboard
 *
 * @package BusinessDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'BD_HOMEPAGE_SHORTCODES_LOADED' ) ) {
	return;
}
define( 'BD_HOMEPAGE_SHORTCODES_LOADED', true );

require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/SearchShortcode.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/RecentReviewsShortcode.php';
require_once plugin_dir_path( __FILE__ ) . '../src/Frontend/LeaderboardShortcode.php';
