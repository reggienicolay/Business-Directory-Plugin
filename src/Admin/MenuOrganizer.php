<?php
/**
 * Admin Menu Organizer
 *
 * Centralizes menu ordering and styling for the Directory admin menu.
 * Runs after all menu items are registered and reorders them.
 *
 * To add a new menu item:
 * 1. Register it normally in your class with add_submenu_page()
 * 2. Add the menu slug to the appropriate section in get_menu_order()
 *
 * @package BusinessDirectory
 * @version 1.0.0
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MenuOrganizer
 */
class MenuOrganizer {

	/**
	 * Parent menu slug
	 *
	 * @var string
	 */
	const PARENT_SLUG = 'edit.php?post_type=bd_business';

	/**
	 * Initialize the organizer
	 */
	public static function init() {
		// Run late to ensure all menu items are registered.
		add_action( 'admin_menu', array( __CLASS__, 'organize_menu' ), 999 );
		add_action( 'admin_head', array( __CLASS__, 'add_menu_styles' ) );
	}

	/**
	 * Get the desired menu order with sections
	 *
	 * Slugs should match exactly what's used in add_submenu_page().
	 * Items not in this list will appear at the end.
	 *
	 * @return array Menu order configuration.
	 */
	private static function get_menu_order() {
		return array(
			// ───────────────────────────────
			// CORE - Standard post type items.
			// ───────────────────────────────
			'core' => array(
				'edit.php?post_type=bd_business',           // All Businesses.
				'post-new.php?post_type=bd_business',       // Add New.
				'edit-tags.php?taxonomy=bd_category&post_type=bd_business', // Categories.
				'edit-tags.php?taxonomy=bd_area&post_type=bd_business',     // Areas.
				'edit-tags.php?taxonomy=bd_tag&post_type=bd_business',      // Tags.
				'bd-import-csv',                            // Import CSV.
			),

			// ───────────────────────────────
			// MODERATION - Items requiring admin action.
			// ───────────────────────────────
			'moderation' => array(
				'edit.php?post_type=bd_business&post_status=pending', // Pending Submissions.
				'bd-pending-claims',                        // Pending Claims.
				'bd-pending-changes',                       // Pending Changes.
				'bd-pending-reviews',                       // Pending Reviews.
				'bd-all-reviews',                           // All Reviews.
			),

			// ───────────────────────────────
			// COMMUNITY - User-generated content management.
			// ───────────────────────────────
			'community' => array(
				'bd-user-lists',                            // User Lists.
				'bd-gamification',                          // Gamification Overview.
				'bd-badge-catalog',                         // Badge Catalog.
				'bd-user-badges',                           // User Badges.
				'bd-leaderboard',                           // Leaderboard.
			),

			// ───────────────────────────────
			// TOOLS - Admin utilities and references.
			// ───────────────────────────────
			'tools' => array(
				'bd-business-tools',                        // Business Tools.
				'bd-feature-settings',                      // Feature Embed.
				'bd-shortcodes',                            // Shortcodes Reference.
			),

			// ───────────────────────────────
			// SETTINGS - Always last.
			// ───────────────────────────────
			'settings' => array(
				'bd-settings',                              // Settings.
			),
		);
	}

	/**
	 * Get menu item labels with dashicons
	 *
	 * Override default labels and add dashicons.
	 * Key = menu slug, Value = array with 'label' and 'icon'.
	 *
	 * @return array Menu labels and icons.
	 */
	private static function get_menu_labels() {
		// Get dynamic counts.
		$pending_businesses = wp_count_posts( 'bd_business' )->pending;
		$pending_claims     = self::get_pending_claims_count();
		$pending_changes    = self::get_pending_changes_count();
		$pending_reviews    = self::get_pending_reviews_count();

		return array(
			// Core.
			'edit.php?post_type=bd_business' => array(
				'label' => __( 'All Businesses', 'business-directory' ),
				'icon'  => 'dashicons-store',
			),
			'post-new.php?post_type=bd_business' => array(
				'label' => __( 'Add New', 'business-directory' ),
				'icon'  => 'dashicons-plus-alt',
			),
			'edit-tags.php?taxonomy=bd_category&post_type=bd_business' => array(
				'label' => __( 'Categories', 'business-directory' ),
				'icon'  => 'dashicons-category',
			),
			'edit-tags.php?taxonomy=bd_area&post_type=bd_business' => array(
				'label' => __( 'Areas', 'business-directory' ),
				'icon'  => 'dashicons-location',
			),
			'edit-tags.php?taxonomy=bd_tag&post_type=bd_business' => array(
				'label' => __( 'Tags', 'business-directory' ),
				'icon'  => 'dashicons-tag',
			),
			'bd-import-csv' => array(
				'label' => __( 'Import CSV', 'business-directory' ),
				'icon'  => 'dashicons-upload',
			),

			// Moderation.
			'edit.php?post_type=bd_business&post_status=pending' => array(
				'label' => __( 'Pending Submissions', 'business-directory' ),
				'icon'  => 'dashicons-businesswoman',
				'count' => $pending_businesses,
			),
			'bd-pending-claims' => array(
				'label' => __( 'Pending Claims', 'business-directory' ),
				'icon'  => 'dashicons-id-alt',
				'count' => $pending_claims,
			),
			'bd-pending-changes' => array(
				'label' => __( 'Pending Changes', 'business-directory' ),
				'icon'  => 'dashicons-edit-page',
				'count' => $pending_changes,
			),
			'bd-pending-reviews' => array(
				'label' => __( 'Pending Reviews', 'business-directory' ),
				'icon'  => 'dashicons-star-half',
				'count' => $pending_reviews,
			),
			'bd-all-reviews' => array(
				'label' => __( 'All Reviews', 'business-directory' ),
				'icon'  => 'dashicons-star-filled',
			),

			// Community.
			'bd-user-lists' => array(
				'label' => __( 'User Lists', 'business-directory' ),
				'icon'  => 'dashicons-list-view',
			),
			'bd-gamification' => array(
				'label' => __( 'Gamification', 'business-directory' ),
				'icon'  => 'dashicons-awards',
			),
			'bd-badge-catalog' => array(
				'label' => __( 'Badge Catalog', 'business-directory' ),
				'icon'  => 'dashicons-shield',
			),
			'bd-user-badges' => array(
				'label' => __( 'User Badges', 'business-directory' ),
				'icon'  => 'dashicons-groups',
			),
			'bd-leaderboard' => array(
				'label' => __( 'Leaderboard', 'business-directory' ),
				'icon'  => 'dashicons-chart-bar',
			),

			// Tools.
			'bd-business-tools' => array(
				'label' => __( 'Business Tools', 'business-directory' ),
				'icon'  => 'dashicons-hammer',
			),
			'bd-feature-settings' => array(
				'label' => __( 'Feature Embed', 'business-directory' ),
				'icon'  => 'dashicons-embed-generic',
			),
			'bd-shortcodes' => array(
				'label' => __( 'Shortcodes', 'business-directory' ),
				'icon'  => 'dashicons-shortcode',
			),

			// Settings.
			'bd-settings' => array(
				'label' => __( 'Settings', 'business-directory' ),
				'icon'  => 'dashicons-admin-settings',
			),
		);
	}

	/**
	 * Organize the submenu
	 */
	public static function organize_menu() {
		global $submenu;

		$parent = self::PARENT_SLUG;

		if ( ! isset( $submenu[ $parent ] ) ) {
			return;
		}

		$current_items = $submenu[ $parent ];
		$menu_order    = self::get_menu_order();
		$menu_labels   = self::get_menu_labels();

		// Build ordered array.
		$ordered   = array();
		$position  = 0;
		$remaining = $current_items;

		foreach ( $menu_order as $section => $slugs ) {
			foreach ( $slugs as $slug ) {
				// Find this item in current menu.
				foreach ( $current_items as $key => $item ) {
					if ( self::menu_item_matches( $item, $slug ) ) {
						// Apply custom label and icon if defined.
						if ( isset( $menu_labels[ $slug ] ) ) {
							$config = $menu_labels[ $slug ];
							$label  = $config['label'];

							// Add count badge if present.
							if ( ! empty( $config['count'] ) && $config['count'] > 0 ) {
								$label .= sprintf(
									' <span class="awaiting-mod count-%d">' .
									'<span class="pending-count">%s</span></span>',
									$config['count'],
									number_format_i18n( $config['count'] )
								);
							}

							$item[0] = $label;
						}

						// Add section class for styling.
						$item['section'] = $section;

						$ordered[ $position ] = $item;
						++$position;

						// Remove from remaining.
						unset( $remaining[ $key ] );
						break;
					}
				}
			}
		}

		// Add any remaining items not in our order (future-proofing).
		foreach ( $remaining as $item ) {
			$ordered[ $position ] = $item;
			++$position;
		}

		// Replace submenu.
		$submenu[ $parent ] = $ordered;
	}

	/**
	 * Check if menu item matches a slug
	 *
	 * @param array  $item Menu item array.
	 * @param string $slug Slug to match.
	 * @return bool
	 */
	private static function menu_item_matches( $item, $slug ) {
		// $item[2] is the menu slug.
		if ( ! isset( $item[2] ) ) {
			return false;
		}

		$item_slug = $item[2];

		// Direct match.
		if ( $item_slug === $slug ) {
			return true;
		}

		// For simple page slugs (no special chars), do exact match only.
		if ( strpos( $slug, '?' ) === false && strpos( $slug, '&' ) === false ) {
			return $item_slug === $slug;
		}

		// For URL-style slugs, parse and compare parameters.
		if ( strpos( $slug, 'post_status=pending' ) !== false ) {
			// Special handling for pending status URLs.
			if ( strpos( $item_slug, 'post_status=pending' ) !== false ) {
				return true;
			}
		}

		// Handle taxonomy matching - extract taxonomy name.
		if ( strpos( $slug, 'taxonomy=' ) !== false ) {
			preg_match( '/taxonomy=([^&]+)/', $slug, $matches );
			if ( ! empty( $matches[1] ) ) {
				$taxonomy = $matches[1];
				if ( strpos( $item_slug, 'taxonomy=' . $taxonomy ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Add CSS for menu styling
	 */
	public static function add_menu_styles() {
		$screen = get_current_screen();

		// Only on our post type screens.
		if ( ! $screen || strpos( $screen->id, 'bd_business' ) === false ) {
			// Still add styles if we're in the Directory menu area.
			if ( ! isset( $_GET['post_type'] ) || 'bd_business' !== $_GET['post_type'] ) {
				// Check if current page is one of our custom pages.
				$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
				if ( strpos( $page, 'bd-' ) !== 0 ) {
					return;
				}
			}
		}

		?>
		<style>
			/* Directory Menu Styling */
			#adminmenu .wp-submenu .awaiting-mod {
				background: #d63638;
				color: #fff;
				display: inline-block;
				padding: 0 5px;
				min-width: 7px;
				height: 17px;
				line-height: 17px;
				text-align: center;
				font-size: 9px;
				font-weight: 600;
				border-radius: 11px;
				margin-left: 4px;
				vertical-align: middle;
			}

			/* Divider before Moderation section (Pending Submissions) */
			#adminmenu #menu-posts-bd_business .wp-submenu li a[href*="post_status=pending"] {
				margin-top: 10px;
				padding-top: 10px;
				border-top: 1px solid rgba(255,255,255,0.1);
			}

			/* Divider before Community section (User Lists) */
			#adminmenu #menu-posts-bd_business .wp-submenu li a[href*="page=bd-user-lists"] {
				margin-top: 10px;
				padding-top: 10px;
				border-top: 1px solid rgba(255,255,255,0.1);
			}

			/* Divider before Tools section (Business Tools) */
			#adminmenu #menu-posts-bd_business .wp-submenu li a[href*="page=bd-business-tools"] {
				margin-top: 10px;
				padding-top: 10px;
				border-top: 1px solid rgba(255,255,255,0.1);
			}

			/* Divider before Settings */
			#adminmenu #menu-posts-bd_business .wp-submenu li a[href*="page=bd-settings"] {
				margin-top: 10px;
				padding-top: 10px;
				border-top: 1px solid rgba(255,255,255,0.1);
			}
		</style>
		<?php
	}

	/**
	 * Get pending claims count
	 *
	 * @return int
	 */
	private static function get_pending_claims_count() {
		if ( ! class_exists( '\BD\DB\ClaimRequestsTable' ) ) {
			return 0;
		}

		return \BD\DB\ClaimRequestsTable::count_pending();
	}

	/**
	 * Get pending changes count
	 *
	 * @return int
	 */
	private static function get_pending_changes_count() {
		if ( ! class_exists( '\BD\DB\ChangeRequestsTable' ) ) {
			return 0;
		}

		return \BD\DB\ChangeRequestsTable::count_pending();
	}

	/**
	 * Get pending reviews count
	 *
	 * @return int
	 */
	private static function get_pending_reviews_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'bd_reviews';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"
		);
	}
}
