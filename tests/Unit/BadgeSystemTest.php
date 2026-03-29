<?php
/**
 * Tests for BD\Gamification\BadgeSystem
 *
 * Structure validation tests — verifies that badge and rank constants
 * are well-formed and internally consistent. No database needed.
 *
 * @package BusinessDirectory\Tests\Unit
 */

namespace BD\Tests\Unit;

use BD\Gamification\BadgeSystem;
use WP_UnitTestCase;

class BadgeSystemTest extends WP_UnitTestCase {

	/**
	 * Required keys for every badge definition.
	 */
	private $required_badge_keys = array( 'name', 'icon', 'color', 'description', 'requirement', 'rarity', 'points' );

	/**
	 * Valid rarity levels.
	 */
	private $valid_rarities = array( 'common', 'rare', 'epic', 'legendary', 'special' );

	// =========================================================================
	// Badge structure validation
	// =========================================================================

	public function test_badges_constant_is_not_empty() {
		$this->assertNotEmpty( BadgeSystem::BADGES );
	}

	public function test_all_badges_have_required_keys() {
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			foreach ( $this->required_badge_keys as $required_key ) {
				$this->assertArrayHasKey(
					$required_key,
					$badge,
					"Badge '{$key}' is missing required key '{$required_key}'"
				);
			}
		}
	}

	public function test_all_badges_have_non_empty_name() {
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			$this->assertNotEmpty( $badge['name'], "Badge '{$key}' has empty name" );
		}
	}

	public function test_all_badges_have_valid_rarity() {
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			$this->assertContains(
				$badge['rarity'],
				$this->valid_rarities,
				"Badge '{$key}' has invalid rarity '{$badge['rarity']}'"
			);
		}
	}

	public function test_all_badges_have_positive_points() {
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			$this->assertGreaterThan(
				0,
				$badge['points'],
				"Badge '{$key}' has non-positive points: {$badge['points']}"
			);
		}
	}

	public function test_all_badges_have_valid_color_hex() {
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			$this->assertMatchesRegularExpression(
				'/^#[0-9a-fA-F]{6}$/',
				$badge['color'],
				"Badge '{$key}' has invalid color '{$badge['color']}'"
			);
		}
	}

	public function test_all_badges_have_icon_html() {
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			$this->assertStringContainsString(
				'<i class="fa-',
				$badge['icon'],
				"Badge '{$key}' has invalid icon HTML"
			);
		}
	}

	public function test_automatic_badges_have_check_and_threshold() {
		foreach ( BadgeSystem::BADGES as $key => $badge ) {
			// Skip manual badges.
			if ( ! empty( $badge['manual'] ) ) {
				continue;
			}

			// Auto badges must have check and threshold (unless they have 'auto' key for special handling).
			if ( empty( $badge['auto'] ) ) {
				$this->assertArrayHasKey(
					'check',
					$badge,
					"Automatic badge '{$key}' is missing 'check' key"
				);
				$this->assertArrayHasKey(
					'threshold',
					$badge,
					"Automatic badge '{$key}' is missing 'threshold' key"
				);
				$this->assertGreaterThan(
					0,
					$badge['threshold'],
					"Automatic badge '{$key}' has non-positive threshold"
				);
			}
		}
	}

	public function test_manual_badges_have_manual_flag() {
		$manual_badges = array_filter(
			BadgeSystem::BADGES,
			function ( $badge ) {
				return ! empty( $badge['manual'] );
			}
		);

		// There should be at least some manual badges.
		$this->assertNotEmpty( $manual_badges, 'No manual badges found' );

		// Manual badges should NOT have check/threshold.
		foreach ( $manual_badges as $key => $badge ) {
			$this->assertArrayNotHasKey(
				'check',
				$badge,
				"Manual badge '{$key}' should not have 'check' key"
			);
		}
	}

	// =========================================================================
	// Rank structure validation
	// =========================================================================

	public function test_ranks_constant_is_not_empty() {
		$this->assertNotEmpty( BadgeSystem::RANKS );
	}

	public function test_ranks_start_at_zero() {
		$this->assertArrayHasKey( 0, BadgeSystem::RANKS );
	}

	public function test_ranks_thresholds_are_ascending() {
		$thresholds = array_keys( BadgeSystem::RANKS );
		$sorted     = $thresholds;
		sort( $sorted );

		$this->assertSame( $sorted, $thresholds, 'Rank thresholds are not in ascending order' );
	}

	public function test_all_ranks_have_required_fields() {
		$required = array( 'name', 'icon', 'color', 'desc' );

		foreach ( BadgeSystem::RANKS as $threshold => $rank ) {
			foreach ( $required as $field ) {
				$this->assertArrayHasKey(
					$field,
					$rank,
					"Rank at threshold {$threshold} is missing '{$field}'"
				);
			}
		}
	}

	public function test_all_ranks_have_non_empty_name() {
		foreach ( BadgeSystem::RANKS as $threshold => $rank ) {
			$this->assertNotEmpty( $rank['name'], "Rank at threshold {$threshold} has empty name" );
		}
	}

	// =========================================================================
	// Badge categories validation
	// =========================================================================

	public function test_badge_categories_cover_all_badges() {
		$categorized_badges = array();
		foreach ( BadgeSystem::BADGE_CATEGORIES as $cat_key => $category ) {
			foreach ( $category['badges'] as $badge_key ) {
				$categorized_badges[] = $badge_key;
			}
		}

		$all_badge_keys = array_keys( BadgeSystem::BADGES );

		foreach ( $all_badge_keys as $badge_key ) {
			$this->assertContains(
				$badge_key,
				$categorized_badges,
				"Badge '{$badge_key}' is not in any BADGE_CATEGORIES group"
			);
		}
	}

	public function test_badge_categories_reference_existing_badges() {
		$all_badge_keys = array_keys( BadgeSystem::BADGES );

		foreach ( BadgeSystem::BADGE_CATEGORIES as $cat_key => $category ) {
			foreach ( $category['badges'] as $badge_key ) {
				$this->assertContains(
					$badge_key,
					$all_badge_keys,
					"Category '{$cat_key}' references non-existent badge '{$badge_key}'"
				);
			}
		}
	}

	public function test_badge_categories_have_required_fields() {
		$required = array( 'name', 'icon', 'desc', 'badges' );

		foreach ( BadgeSystem::BADGE_CATEGORIES as $cat_key => $category ) {
			foreach ( $required as $field ) {
				$this->assertArrayHasKey(
					$field,
					$category,
					"Badge category '{$cat_key}' is missing '{$field}'"
				);
			}
		}
	}

	public function test_no_duplicate_badges_across_categories() {
		$seen = array();

		foreach ( BadgeSystem::BADGE_CATEGORIES as $cat_key => $category ) {
			foreach ( $category['badges'] as $badge_key ) {
				$existing_cat = isset( $seen[ $badge_key ] ) ? $seen[ $badge_key ] : '';
				$this->assertArrayNotHasKey(
					$badge_key,
					$seen,
					"Badge '{$badge_key}' appears in multiple categories: '{$existing_cat}' and '{$cat_key}'"
				);
				$seen[ $badge_key ] = $cat_key;
			}
		}
	}

	// =========================================================================
	// Badge key uniqueness
	// =========================================================================

	public function test_badge_keys_are_valid_slugs() {
		foreach ( array_keys( BadgeSystem::BADGES ) as $key ) {
			$this->assertMatchesRegularExpression(
				'/^[a-z][a-z0-9_]*$/',
				$key,
				"Badge key '{$key}' is not a valid slug"
			);
		}
	}
}
