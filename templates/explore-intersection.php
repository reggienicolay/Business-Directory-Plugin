<?php
/**
 * Explore Intersection Page Template
 *
 * Displays businesses matching a specific tag × city combination
 * with heart pin map markers, a discovery bar bridging to the
 * full interactive directory, and cross-links.
 *
 * e.g., /explore/livermore/winery/
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

// Get current area + tag from query vars.
$area         = ExploreRouter::get_current_area();
$current_tag  = ExploreRouter::get_current_tag();
$current_page = ExploreRouter::get_current_page();

if ( ! $area || ! $current_tag ) {
	// Safety fallback — router should have caught this.
	wp_safe_redirect( home_url( '/explore/' ) );
	exit;
}

// Sort: read from URL, validate, default to 'rating'.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display param.
$sort = ExploreQuery::validate_sort( isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'rating' );

// Fetch data.
$result      = ExploreQuery::get_intersection( $area->slug, $tag->slug, $current_page, $sort );
$businesses  = $result['businesses'];
$total       = $result['total'];
$total_pages = $result['pages'];

// 404 if page number exceeds actual pages.
if ( $paged > 1 && $paged > $pages ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_404_template();
	exit;
}

// Build the base URL for pagination.
$base_url = ExploreRouter::get_explore_url( $area->slug, $tag->slug );

/*
 * Enqueue assets — centralized in ExploreRenderer to avoid duplication
 * between city and intersection templates.
 */
ExploreRenderer::enqueue_page_assets();

get_header();
?>

<div class="bd-explore-page bd-explore-intersection">
	<div class="bd-explore-container">

		<?php
		/**
		 * Hook: bd_explore_before_header.
		 *
		 * BD SEO plugin hooks here to output breadcrumbs.
		 * Trail: Love TriValley › Explore › {City} › {Tag}
		 */
		do_action( 'bd_explore_before_header', $area, $tag );
		?>

		<header class="bd-explore-header">
			<h1 class="bd-explore-title">
				<?php
				printf(
					/* translators: 1: Tag name, 2: City name */
					esc_html__( '%1$s in %2$s', 'business-directory' ),
					esc_html( $tag->name ),
					esc_html( $area->name )
				);
				?>
			</h1>

			<?php echo ExploreRenderer::render_intro( $area->slug, $tag->slug, $total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</header>

		<?php
		// Discovery bar — bridges to the full interactive directory.
		// Pre-selects the current city and tag. Users arriving from
		// search get a path into the full Quick Filters experience.
		echo ExploreRenderer::render_discovery_bar( $area->slug, $tag->slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<?php // Interactive map with heart pin markers. ?>
		<?php echo ExploreRenderer::render_map( $businesses ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="bd-explore-results-meta">
			<span class="bd-explore-results-count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: Business count, 2: Tag name, 3: City name */
						__( '%1$d %2$s in %3$s', 'business-directory' ),
						$total,
						strtolower( $tag->name ),
						$area->name
					)
				);
				?>
			</span>
			<?php echo ExploreRenderer::render_sort_dropdown( $sort, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<?php // Server-rendered business card grid. ?>
		<?php echo ExploreRenderer::render_grid( $businesses ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php // Pagination. ?>
		<?php echo ExploreRenderer::render_pagination( $pages, $current_page, $base_url, $sort ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php // "Also Explore" cross-linking. ?>
		<?php echo ExploreRenderer::render_cross_links( $area->slug, $tag->slug, $area->name, $tag->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php
		/**
		 * Hook: bd_explore_after_content.
		 *
		 * @param \WP_Term $area Area term.
		 * @param \WP_Term $tag  Tag term.
		 */
		do_action( 'bd_explore_after_content', $area, $tag );
		?>

	</div>
</div>

<?php
get_footer();
