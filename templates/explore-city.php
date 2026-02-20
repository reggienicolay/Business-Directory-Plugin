<?php
/**
 * Explore City Landing Page Template
 *
 * Displays all businesses in a city with a tag cloud linking
 * to intersection pages, an interactive Leaflet map with
 * heart pin markers, a discovery bar bridging to the full
 * interactive directory, and server-rendered business cards.
 *
 * e.g., /explore/livermore/
 *
 * @package    BusinessDirectory
 * @subpackage Explore
 * @since      2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BusinessDirectory\Explore\ExploreRouter;
use BusinessDirectory\Explore\ExploreQuery;
use BusinessDirectory\Explore\ExploreRenderer;

// Get current area from query vars.
$area         = ExploreRouter::get_current_area();
$current_page = ExploreRouter::get_current_page();

if ( ! $area ) {
	wp_safe_redirect( home_url( '/explore/' ) );
	exit;
}

// Sort: read from URL, validate, default to 'rating'.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display param.
$sort = ExploreQuery::validate_sort( isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'rating' );

// Fetch data.
$result      = ExploreQuery::get_city( $area->slug, $current_page, $sort );
$businesses  = $result['businesses'];
$total       = $result['total'];
$total_pages = $result['pages'];
$base_url    = ExploreRouter::get_explore_url( $area->slug );

// 404 if page number exceeds actual pages.
if ( $paged > 1 && $paged > $pages ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_404_template();
	exit;
}

// Get tags with counts for this city.
$area_tags = ExploreQuery::get_tags_for_area( $area->slug );

/*
 * Enqueue assets — centralized in ExploreRenderer to avoid duplication
 * between city and intersection templates.
 */
ExploreRenderer::enqueue_page_assets();

get_header();
?>

<div class="bd-explore-page bd-explore-city">
	<div class="bd-explore-container">

		<?php
		/**
		 * Hook: bd_explore_before_header.
		 *
		 * BD SEO plugin hooks here to output breadcrumbs.
		 * Trail: Love TriValley › Explore › {City}
		 */
		do_action( 'bd_explore_before_header', $area, null );
		?>

		<header class="bd-explore-header">
			<h1 class="bd-explore-title">
				<?php
				printf(
					/* translators: %s: City name */
					esc_html__( 'Explore %s', 'business-directory' ),
					esc_html( $area->name )
				);
				?>
			</h1>

			<?php echo ExploreRenderer::render_intro( $area->slug, '', $total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</header>

		<?php
		// Discovery bar — bridges to the full interactive directory.
		// Pre-selects the current city. Users arriving from search
		// get a path into the full Quick Filters experience.
		echo ExploreRenderer::render_discovery_bar( $area->slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<?php // City stats. ?>
		<?php echo ExploreRenderer::render_city_stats( $total, $area->slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php // Tag cloud with links to intersection pages. ?>
		<?php echo ExploreRenderer::render_tag_cloud( $area_tags, $area->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php // Interactive map with heart pin markers. ?>
		<?php echo ExploreRenderer::render_map( $businesses ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php // Results meta bar. ?>
		<div class="bd-explore-results-meta">
			<span class="bd-explore-results-count">
				<?php
				printf(
					/* translators: 1: Business count, 2: City name */
					esc_html__( '%1$d businesses in %2$s', 'business-directory' ),
					intval( $total ),
					esc_html( $area->name )
				);
				?>
			</span>
			<?php echo ExploreRenderer::render_sort_dropdown( $sort, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<?php // Server-rendered business card grid. ?>
		<?php echo ExploreRenderer::render_grid( $businesses ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php // Pagination. ?>
		<?php echo ExploreRenderer::render_pagination( $pages, $current_page, $base_url, $sort ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php // Links to other cities. ?>
		<?php echo ExploreRenderer::render_other_cities( $area->slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php
		do_action( 'bd_explore_after_content', $area, null );
		?>

	</div>
</div>

<?php
get_footer();
