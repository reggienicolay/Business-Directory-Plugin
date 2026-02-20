<?php
/**
 * Explore Pages Editorial Content
 *
 * Manages editorial introductions for explore pages.
 * Supports auto-generated fallbacks with manual overrides
 * stored in term meta and options.
 *
 * @package    BusinessDirectory
 * @subpackage Explore
 * @since      2.2.0
 */

namespace BusinessDirectory\Explore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExploreEditorial
 */
class ExploreEditorial {

	/**
	 * Term meta key for editorial intro override.
	 *
	 * @var string
	 */
	const META_KEY = 'bd_explore_intro';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Add editorial fields to bd_area and bd_tag edit screens.
		add_action( 'bd_area_edit_form_fields', array( __CLASS__, 'render_area_field' ), 20 );
		add_action( 'bd_tag_edit_form_fields', array( __CLASS__, 'render_tag_field' ), 20 );
		add_action( 'edited_bd_area', array( __CLASS__, 'save_term_field' ) );
		add_action( 'edited_bd_tag', array( __CLASS__, 'save_term_field' ) );
	}

	/**
	 * Get the editorial intro for an explore page.
	 *
	 * Priority:
	 * 1. Intersection-specific override (stored in options).
	 * 2. Tag-level override (stored in term meta).
	 * 3. Auto-generated from template.
	 *
	 * @param string $area_slug Area slug.
	 * @param string $tag_slug  Tag slug (empty for city pages).
	 * @param int    $count     Business count for template.
	 * @return string Intro text.
	 */
	public static function get_intro( $area_slug, $tag_slug = '', $count = 0 ) {
		// Use router's per-request term cache to avoid redundant DB lookups.
		$area = ExploreRouter::get_area_term( $area_slug );
		if ( ! $area ) {
			return '';
		}

		// City landing page (no tag).
		if ( empty( $tag_slug ) ) {
			return self::get_city_intro( $area, $count );
		}

		$tag = ExploreRouter::get_tag_term( $tag_slug );
		if ( ! $tag ) {
			return '';
		}

		// 1. Intersection-specific override.
		$intersection_key = 'bd_explore_intro_' . $area_slug . '_' . $tag_slug;
		$override         = get_option( $intersection_key, '' );
		if ( ! empty( $override ) ) {
			return $override;
		}

		// 2. Auto-generated.
		return self::generate_intersection_intro( $area, $tag, $count );
	}

	/**
	 * Get editorial intro for a city landing page.
	 *
	 * @param \WP_Term $area  Area term.
	 * @param int      $count Business count.
	 * @return string Intro text.
	 */
	private static function get_city_intro( $area, $count ) {
		// Check for area-level override.
		$override = get_term_meta( $area->term_id, self::META_KEY, true );
		if ( ! empty( $override ) ) {
			return $override;
		}

		// Auto-generate.
		if ( $count > 0 ) {
			return sprintf(
				/* translators: 1: Business count, 2: City name */
				__( 'Explore %1$d local businesses in %2$s, California. Find restaurants, wineries, parks, shops, and more — all recommended by our community.', 'business-directory' ),
				$count,
				$area->name
			);
		}

		return sprintf(
			/* translators: %s: City name */
			__( 'Discover local businesses and experiences in %s, California — recommended by our community.', 'business-directory' ),
			$area->name
		);
	}

	/**
	 * Generate an auto-generated intro for a tag × city intersection.
	 *
	 * @param \WP_Term $area  Area term.
	 * @param \WP_Term $tag   Tag term.
	 * @param int      $count Business count.
	 * @return string Generated intro.
	 */
	private static function generate_intersection_intro( $area, $tag, $count ) {
		if ( $count > 0 ) {
			return sprintf(
				/* translators: 1: Business count, 2: Tag name (lowercase), 3: City name */
				__( 'Discover %1$d %2$s in %3$s, California. Browse ratings, reviews, hours & maps from the Love TriValley community.', 'business-directory' ),
				$count,
				self::pluralize_tag( $tag->name ),
				$area->name
			);
		}

		return sprintf(
			/* translators: 1: Tag name (lowercase), 2: City name */
			__( 'Find the best %1$s in %2$s, California. Ratings, reviews, hours & maps from the Love TriValley community.', 'business-directory' ),
			self::pluralize_tag( $tag->name ),
			$area->name
		);
	}

	/**
	 * Simple pluralization for tag names.
	 *
	 * Returns lowercase plural form. Handles common patterns.
	 *
	 * @param string $name Tag name.
	 * @return string Pluralized lowercase name.
	 */
	private static function pluralize_tag( $name ) {
		$lower = strtolower( $name );

		// Already plural or uncountable patterns.
		$uncountable = array( 'fitness', 'wellness', 'nightlife', 'live music', 'shopping' );
		foreach ( $uncountable as $word ) {
			if ( $lower === $word ) {
				return $lower;
			}
		}

		// Contains slash — likely "Hiking/Trails", keep as-is lowercase.
		if ( false !== strpos( $lower, '/' ) ) {
			return $lower;
		}

		// Already plural — common plural endings that shouldn't get double-pluralized.
		// Matches: wineries, parks, restaurants, trails, shops, etc.
		if ( preg_match( '/(ies|[^s]s|ches|shes)$/i', $lower ) ) {
			return $lower;
		}

		// Common suffix rules for singular words.
		if ( preg_match( '/(sh|ch|x|z)$/i', $lower ) ) {
			return $lower . 'es';
		}
		if ( preg_match( '/[^aeiou]y$/i', $lower ) ) {
			return preg_replace( '/y$/i', 'ies', $lower );
		}

		// Default: add 's'.
		return $lower . 's';
	}

	/**
	 * Save an intersection-specific editorial override.
	 *
	 * Called from admin when editing intersection intros.
	 *
	 * @param string $area_slug Area slug.
	 * @param string $tag_slug  Tag slug.
	 * @param string $intro     Intro text (empty to remove override).
	 */
	public static function save_intersection_intro( $area_slug, $tag_slug, $intro ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return false;
		}

		$key = 'bd_explore_intro_' . sanitize_title( $area_slug ) . '_' . sanitize_title( $tag_slug );

		if ( empty( $intro ) ) {
			return delete_option( $key );
		} else {
			return update_option( $key, sanitize_textarea_field( $intro ), false );
		}
	}

	/**
	 * Render the editorial intro field on area term edit screen.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public static function render_area_field( $term ) {
		$value = get_term_meta( $term->term_id, self::META_KEY, true );
		self::render_field( $term, $value, __( 'Explore Page Intro', 'business-directory' ), __( 'Custom introduction text for this city\'s explore landing page. Leave blank for auto-generated content.', 'business-directory' ) );
	}

	/**
	 * Render the editorial intro field on tag term edit screen.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public static function render_tag_field( $term ) {
		$value = get_term_meta( $term->term_id, self::META_KEY, true );
		self::render_field( $term, $value, __( 'Explore Page Intro', 'business-directory' ), __( 'Custom introduction text for this tag\'s explore pages. Leave blank for auto-generated content.', 'business-directory' ) );
	}

	/**
	 * Render a textarea field on term edit screen.
	 *
	 * @param \WP_Term $term        Term object.
	 * @param string   $value       Current value.
	 * @param string   $label       Field label.
	 * @param string   $description Field description.
	 */
	private static function render_field( $term, $value, $label, $description ) {
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="bd-explore-intro"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<textarea name="bd_explore_intro" id="bd-explore-intro" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term meta field on term edit.
	 *
	 * @param int $term_id Term ID being saved.
	 */
	public static function save_term_field( $term_id ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce handled by WP core term edit form.
		if ( isset( $_POST['bd_explore_intro'] ) ) {
			$value = sanitize_textarea_field( wp_unslash( $_POST['bd_explore_intro'] ) );
			update_term_meta( $term_id, self::META_KEY, $value );
		}
	}
}
