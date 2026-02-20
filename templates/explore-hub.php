<?php
/**
 * Explore Hub Page Template
 *
 * The main /explore/ page showing all cities with their
 * top tags and business counts.
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

// Fetch hub data: all cities with tag breakdowns.
$hub_data = ExploreQuery::get_hub_data();

// Enqueue assets.
wp_enqueue_style(
	'bd-explore',
	BD_PLUGIN_URL . 'assets/css/explore.css',
	array(),
	BD_VERSION
);

// Font Awesome for arrow icon in CTAs.
if ( ! wp_style_is( 'font-awesome', 'enqueued' ) && ! wp_style_is( 'font-awesome-5', 'enqueued' ) ) {
	wp_enqueue_style(
		'font-awesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
		array(),
		'5.15.4'
	);
}

get_header();
?>

<div class="bd-explore-page bd-explore-hub">
	<div class="bd-explore-container">

		<?php do_action( 'bd_explore_before_header', null, null ); ?>

		<header class="bd-explore-header">
			<h1 class="bd-explore-title"><?php esc_html_e( 'Discover the Tri-Valley', 'business-directory' ); ?></h1>
			<p class="bd-explore-intro">
				<?php esc_html_e( 'Five cities, countless experiences. Explore local businesses, restaurants, wineries, parks, and more — all recommended by our community.', 'business-directory' ); ?>
			</p>
		</header>

		<?php if ( ! empty( $hub_data ) ) : ?>
			<div class="bd-explore-hub-cities">
				<?php foreach ( $hub_data as $city_data ) : ?>
					<?php $city = $city_data['term']; ?>
					<div class="bd-explore-hub-city">
						<div class="bd-explore-hub-city-header">
							<h2>
								<a href="<?php echo esc_url( $city_data['url'] ); ?>">
									<?php echo esc_html( $city->name ); ?>
								</a>
							</h2>
							<span class="bd-explore-hub-count">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: Business count */
										_n( '%d business', '%d businesses', $city_data['count'], 'business-directory' ),
										$city_data['count']
									)
								);
								?>
							</span>
						</div>

						<?php if ( ! empty( $city_data['top_tags'] ) ) : ?>
							<div class="bd-explore-hub-tags">
								<?php foreach ( $city_data['top_tags'] as $tag ) : ?>
									<a href="<?php echo esc_url( $tag['url'] ); ?>" class="bd-explore-hub-tag">
										<?php echo esc_html( $tag['name'] ); ?>
										<span class="bd-explore-hub-tag-count"><?php echo intval( $tag['count'] ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<a href="<?php echo esc_url( $city_data['url'] ); ?>" class="bd-explore-hub-cta">
							<?php
							printf(
								/* translators: %s: City name */
								esc_html__( 'Explore %s', 'business-directory' ),
								esc_html( $city->name )
							);
							?>
							<i class="fas fa-arrow-right"></i>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php do_action( 'bd_explore_after_content', null, null ); ?>

	</div>
</div>

<?php
get_footer();
