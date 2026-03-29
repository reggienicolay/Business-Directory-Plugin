<?php
/**
 * Cover Media Gamification Integration
 *
 * This file provides the code to add cover media activities and badges
 * to your existing gamification system.
 *
 * INSTALLATION: Apply the patches below to your existing files.
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

// Exit if accessed directly - this file is documentation only.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
================================================================================
PATCH 1: Add to ActivityTracker::ACTIVITY_POINTS constant
================================================================================

File: src/Gamification/ActivityTracker.php

Find this block:
```php
const ACTIVITY_POINTS = array(
    'review_created'        => 10,
    'review_with_photo'     => 5,
    ...
    'first_login'           => 5,
);
```

Add these lines before the closing );
```php
    'list_cover_added'       => 5,   // Photo cover added to list
    'list_video_cover_added' => 10,  // Video cover added to list
```

================================================================================
PATCH 2: Add Visual Storyteller badge to BadgeSystem::BADGES constant
================================================================================

File: src/Gamification/BadgeSystem.php

Find the BADGES constant and add this badge in the Curator section
(after 'list_leader' badge):

```php
'visual_storyteller'      => array(
    'name'        => 'Visual Storyteller',
    'icon'        => '<i class="fa-solid fa-camera-retro"></i>',
    'color'       => '#ec4899',
    'description' => 'Your covers bring lists to life and inspire exploration',
    'requirement' => 'Add custom covers to 5 public lists',
    'check'       => 'public_lists_with_covers',
    'threshold'   => 5,
    'points'      => 50,
    'rarity'      => 'rare',
),
```

================================================================================
PATCH 3: Add badge check method to BadgeSystem
================================================================================

File: src/Gamification/BadgeSystem.php

In the check_badge_requirement() method, add this case:

```php
case 'public_lists_with_covers':
    global $wpdb;
    $lists_table = $wpdb->prefix . 'bd_lists';
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $lists_table 
             WHERE user_id = %d 
             AND visibility = 'public' 
             AND cover_type IN ('image', 'youtube', 'vimeo')",
            $user_id
        )
    );
    return $count >= $threshold;
```

================================================================================
PATCH 4: Add to BadgeAdmin activity formatting (optional)
================================================================================

File: src/Admin/BadgeAdmin.php

In the format_activity() method's $labels array, add:

```php
'list_cover_added'       => 'added a cover to a list',
'list_video_cover_added' => 'added a video cover to a list',
```

================================================================================
PATCH 5: Add to BADGE_CATEGORIES (optional)
================================================================================

File: src/Gamification/BadgeSystem.php

In the BADGE_CATEGORIES constant, find the 'curator' category and add
'visual_storyteller' to its badges array:

```php
'curator'   => array(
    'name'   => 'Curator',
    'icon'   => '<i class="fa-solid fa-layer-group"></i>',
    'desc'   => 'Create and share collections of your favorites',
    'badges' => array( 'curator', 'list_master', 'tastemaker', 'team_player', 'list_leader', 'visual_storyteller' ),
),
```

================================================================================
*/

/**
 * Helper class for checking if integration is complete
 */
class CoverMediaGamificationCheck {

	/**
	 * Check if all patches have been applied
	 *
	 * @return array Status of each patch.
	 */
	public static function check_integration() {
		$status = array();

		// Check ActivityTracker points
		if ( class_exists( 'BD\Gamification\ActivityTracker' ) ) {
			$points = \BD\Gamification\ActivityTracker::ACTIVITY_POINTS;
			$status['activity_points'] = array(
				'list_cover_added'       => isset( $points['list_cover_added'] ),
				'list_video_cover_added' => isset( $points['list_video_cover_added'] ),
			);
		} else {
			$status['activity_points'] = false;
		}

		// Check BadgeSystem badges
		if ( class_exists( 'BD\Gamification\BadgeSystem' ) ) {
			$badges = \BD\Gamification\BadgeSystem::BADGES;
			$status['badges'] = array(
				'visual_storyteller' => isset( $badges['visual_storyteller'] ),
			);
		} else {
			$status['badges'] = false;
		}

		return $status;
	}

	/**
	 * Display integration status in admin
	 */
	public static function display_status() {
		$status = self::check_integration();

		echo '<div class="bd-gamification-integration-status">';
		echo '<h4>Cover Media Gamification Integration</h4>';

		// Activity points
		if ( is_array( $status['activity_points'] ) ) {
			echo '<p><strong>Activity Points:</strong></p><ul>';
			foreach ( $status['activity_points'] as $activity => $ok ) {
				$icon = $ok ? '✅' : '❌';
				echo '<li>' . esc_html( $icon . ' ' . $activity ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>❌ ActivityTracker class not found</p>';
		}

		// Badges
		if ( is_array( $status['badges'] ) ) {
			echo '<p><strong>Badges:</strong></p><ul>';
			foreach ( $status['badges'] as $badge => $ok ) {
				$icon = $ok ? '✅' : '❌';
				echo '<li>' . esc_html( $icon . ' ' . $badge ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>❌ BadgeSystem class not found</p>';
		}

		echo '</div>';
	}
}

