<?php
/**
 * Hours Display Logic
 *
 * Centralises the rules for whether the public Hours card / Open-Now badges
 * should appear on a business listing. Two universal rules apply:
 *
 * 1. If the business has no actual hours filled (the meta exists but every
 *    day is blank), hide the entire Hours UI. Showing a partial weekday grid
 *    or a misleading "Closed" pill on a sparsely-populated listing is worse
 *    than showing nothing.
 *
 * 2. For listings in the "Get Outside" category (parks, trails, open spaces,
 *    etc.), the weekday grid metaphor doesn't fit. Owners can set an
 *    "outdoor access type" (sunrise-to-sunset, 24/7, seasonal, custom) on
 *    the edit screen, and the Hours card is replaced with a simple Access
 *    line. If no access type AND no real hours, the listing defaults to
 *    "Open daily, sunrise to sunset" — the most common case for parks.
 *
 * Display priority (sidebar card):
 *   - Outdoor access type configured  → "Access" card with the label
 *   - Outdoor category + no real hours → "Access" card, default label
 *   - Real hours filled                → normal weekday "Hours" card
 *   - Otherwise                        → no card
 *
 * @package BusinessDirectory
 * @subpackage Frontend
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HoursDisplay {

	/**
	 * Category slugs treated as "outdoor."
	 *
	 * Primary signal is the `get-outside` category. Tag fallback covers
	 * listings where the owner used a tag instead of a category.
	 *
	 * @var array<int,string>
	 */
	private static $outdoor_category_slugs = array( 'get-outside' );

	/**
	 * Tag slugs treated as "outdoor" when no matching category is set.
	 *
	 * @var array<int,string>
	 */
	private static $outdoor_tag_slugs = array(
		'park',
		'hiking-trails',
		'open-space',
		'dog-park',
		'playground',
		'gardens',
		'sports-fields',
	);

	/**
	 * Outdoor access presets: slug => label.
	 *
	 * @var array<string,string>
	 */
	private static $access_presets = array(
		'sunrise-sunset' => 'Open daily, sunrise to sunset',
		'daily-24-7'     => 'Open 24/7',
		'daylight'       => 'Open during daylight hours',
		'seasonal'       => 'Seasonal — check signage at entrance',
		'custom'         => '', // uses bd_outdoor_access_custom freeform.
	);

	/**
	 * Per-request memo: business_id => bool.
	 *
	 * @var array<int,bool>
	 */
	private static $has_hours_cache = array();

	/**
	 * Per-request memo: business_id => bool.
	 *
	 * @var array<int,bool>
	 */
	private static $is_outdoor_cache = array();

	/**
	 * Does the business have at least one day with both open and close times?
	 *
	 * The bd_hours meta is saved by MetaBoxes for all 7 days even when the
	 * owner left them blank, so a truthy check on the meta is meaningless.
	 * This walks the array and looks for actual data.
	 *
	 * @param int $business_id Business post ID.
	 * @return bool
	 */
	public static function has_hours( $business_id ) {
		$business_id = (int) $business_id;
		if ( ! $business_id ) {
			return false;
		}

		if ( isset( self::$has_hours_cache[ $business_id ] ) ) {
			return self::$has_hours_cache[ $business_id ];
		}

		$hours = get_post_meta( $business_id, 'bd_hours', true );
		if ( ! is_array( $hours ) || empty( $hours ) ) {
			self::$has_hours_cache[ $business_id ] = false;
			return false;
		}

		foreach ( $hours as $day ) {
			if ( ! is_array( $day ) ) {
				continue;
			}
			if ( ! empty( $day['open'] ) && ! empty( $day['close'] ) ) {
				self::$has_hours_cache[ $business_id ] = true;
				return true;
			}
		}

		self::$has_hours_cache[ $business_id ] = false;
		return false;
	}

	/**
	 * Is this listing an outdoor place (park / trail / open space)?
	 *
	 * @param int $business_id Business post ID.
	 * @return bool
	 */
	public static function is_outdoor( $business_id ) {
		$business_id = (int) $business_id;
		if ( ! $business_id ) {
			return false;
		}

		if ( isset( self::$is_outdoor_cache[ $business_id ] ) ) {
			return self::$is_outdoor_cache[ $business_id ];
		}

		$cats = get_the_terms( $business_id, 'bd_category' );
		if ( is_array( $cats ) ) {
			foreach ( $cats as $term ) {
				if ( in_array( $term->slug, self::$outdoor_category_slugs, true ) ) {
					self::$is_outdoor_cache[ $business_id ] = true;
					return true;
				}
			}
		}

		$tags = get_the_terms( $business_id, 'bd_tag' );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $term ) {
				if ( in_array( $term->slug, self::$outdoor_tag_slugs, true ) ) {
					self::$is_outdoor_cache[ $business_id ] = true;
					return true;
				}
			}
		}

		self::$is_outdoor_cache[ $business_id ] = false;
		return false;
	}

	/**
	 * Outdoor access label for this listing, or empty string if not applicable.
	 *
	 * Resolution order:
	 *   1. Owner-selected preset (bd_outdoor_access_type) → preset label
	 *   2. Owner-selected "custom" + freeform text → freeform text
	 *   3. is_outdoor() && ! has_hours() → default sunrise-to-sunset
	 *   4. Otherwise → empty string (caller should not render the Access card)
	 *
	 * @param int $business_id Business post ID.
	 * @return string
	 */
	public static function get_access_label( $business_id ) {
		$business_id = (int) $business_id;
		if ( ! $business_id ) {
			return '';
		}

		$type = (string) get_post_meta( $business_id, 'bd_outdoor_access_type', true );

		if ( 'custom' === $type ) {
			$custom = (string) get_post_meta( $business_id, 'bd_outdoor_access_custom', true );
			$custom = trim( $custom );
			if ( '' !== $custom ) {
				return $custom;
			}
			// Custom selected but no text — fall through to defaults.
		}

		if ( '' !== $type && 'none' !== $type && isset( self::$access_presets[ $type ] ) && '' !== self::$access_presets[ $type ] ) {
			return __( self::$access_presets[ $type ], 'business-directory' ); // phpcs:ignore WordPress.WP.I18n
		}

		// Auto-default: outdoor listing with no explicit access type and no real hours.
		if ( self::is_outdoor( $business_id ) && ! self::has_hours( $business_id ) ) {
			return __( 'Open daily, sunrise to sunset', 'business-directory' );
		}

		return '';
	}

	/**
	 * Should the Open Now / Closed badge render anywhere on this listing?
	 *
	 * The badge needs real weekday hours to compute against; outdoor access
	 * labels are descriptive, not computed open/closed states.
	 *
	 * @param int $business_id Business post ID.
	 * @return bool
	 */
	public static function should_show_open_status( $business_id ) {
		return self::has_hours( $business_id ) && '' === self::get_access_label( $business_id );
	}

	/**
	 * Get the preset list for the admin dropdown.
	 *
	 * @return array<string,string> slug => translated label
	 */
	public static function get_admin_presets() {
		$out = array(
			'none' => __( '— None (use weekday hours above, or hide if blank) —', 'business-directory' ),
		);
		foreach ( self::$access_presets as $slug => $label ) {
			if ( 'custom' === $slug ) {
				$out[ $slug ] = __( 'Custom message', 'business-directory' );
				continue;
			}
			$out[ $slug ] = __( $label, 'business-directory' ); // phpcs:ignore WordPress.WP.I18n
		}
		return $out;
	}
}
