<?php
/**
 * Badge Generator
 *
 * Generates "Featured on" badges for businesses.
 *
 * @package BusinessDirectory
 */

namespace BD\BusinessTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BadgeGenerator
 */
class BadgeGenerator {

	/**
	 * Badge sizes.
	 */
	const SIZES = array(
		'small'  => 150,
		'medium' => 200,
		'large'  => 300,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// REST endpoint for badge image.
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Rewrite rule for badge images.
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_badge_request' ) );
	}

	/**
	 * Register rewrite rules.
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^badge/([^/]+)\.svg$',
			'index.php?bd_badge_slug=$matches[1]',
			'top'
		);
		add_rewrite_tag( '%bd_badge_slug%', '([^/]+)' );
	}

	/**
	 * Handle badge image request.
	 */
	public function handle_badge_request() {
		$badge_slug = get_query_var( 'bd_badge_slug' );

		if ( ! $badge_slug ) {
			return;
		}

		// Find business by slug.
		$business = get_page_by_path( $badge_slug, OBJECT, 'bd_business' );

		if ( ! $business ) {
			// Try by ID.
			if ( is_numeric( $badge_slug ) ) {
				$business = get_post( (int) $badge_slug );
			}
		}

		if ( ! $business || 'bd_business' !== $business->post_type ) {
			status_header( 404 );
			exit;
		}

		// Get style from query string.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$style = isset( $_GET['style'] ) ? sanitize_text_field( wp_unslash( $_GET['style'] ) ) : 'rating';

		// Output SVG.
		header( 'Content-Type: image/svg+xml' );
		header( 'Cache-Control: public, max-age=3600' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::generate_svg( $business->ID, $style );
		exit;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'bd/v1',
			'/badge/(?P<business_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_badge' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'business_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'style'       => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'rating',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST endpoint: Get badge.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_badge( $request ) {
		$business_id = $request->get_param( 'business_id' );
		$style       = $request->get_param( 'style' );

		$svg = self::generate_svg( $business_id, $style );

		$response = new \WP_REST_Response( $svg );
		$response->header( 'Content-Type', 'image/svg+xml' );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		return $response;
	}

	/**
	 * Generate embed code for badge.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $style       Badge style.
	 * @param string $size        Badge size.
	 * @return string Embed code.
	 */
	public static function generate_embed_code( $business_id, $style = 'rating', $size = 'medium' ) {
		$business = get_post( $business_id );
		if ( ! $business ) {
			return '';
		}

		$badge_url   = home_url( '/badge/' . $business->post_name . '.svg?style=' . $style );
		$business_url = get_permalink( $business_id );
		$width       = self::SIZES[ $size ] ?? 200;

		// Get rating for alt text.
		$rating = get_post_meta( $business_id, 'bd_rating_avg', true );
		$rating = $rating ? round( (float) $rating, 1 ) : '';

		$alt_text = $rating
			? sprintf( '%s stars on %s', $rating, get_bloginfo( 'name' ) )
			: sprintf( 'Featured on %s', get_bloginfo( 'name' ) );

		$code = sprintf(
			'<a href="%s" target="_blank" rel="noopener">
  <img src="%s" alt="%s" width="%d" style="max-width: 100%%; height: auto;">
</a>',
			esc_url( $business_url ),
			esc_url( $badge_url ),
			esc_attr( $alt_text ),
			$width
		);

		return $code;
	}

	/**
	 * Generate SVG badge.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $style       Badge style (simple, rating, reviews).
	 * @return string SVG markup.
	 */
	public static function generate_svg( $business_id, $style = 'rating' ) {
		$business = get_post( $business_id );
		if ( ! $business ) {
			return '';
		}

		// Get business data.
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as count, AVG(rating) as avg 
				FROM {$reviews_table} 
				WHERE business_id = %d AND status = 'approved'",
				$business_id
			),
			ARRAY_A
		);

		$rating       = $stats['avg'] ? round( (float) $stats['avg'], 1 ) : 0;
		$review_count = (int) $stats['count'];
		$site_name    = get_bloginfo( 'name' );

		// Brand colors.
		$colors = array(
			'primary'   => '#1a3a4a',
			'secondary' => '#7a9eb8',
			'accent'    => '#1e4258',
			'light'     => '#a8c4d4',
			'star'      => '#f59e0b',
			'white'     => '#ffffff',
		);

		switch ( $style ) {
			case 'simple':
				return self::svg_simple( $site_name, $colors );

			case 'reviews':
				return self::svg_reviews( $rating, $review_count, $site_name, $colors );

			case 'rating':
			default:
				return self::svg_rating( $rating, $site_name, $colors );
		}
	}

	/**
	 * Generate simple badge SVG.
	 *
	 * @param string $site_name Site name.
	 * @param array  $colors    Colors array.
	 * @return string SVG.
	 */
	private static function svg_simple( $site_name, $colors ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 50" width="200" height="50">
	<rect width="200" height="50" rx="8" fill="' . $colors['primary'] . '"/>
	<text x="25" y="32" fill="' . $colors['white'] . '" font-family="system-ui, -apple-system, sans-serif" font-size="14">
		📍 Featured on ' . esc_html( $site_name ) . '
	</text>
</svg>';
	}

	/**
	 * Generate rating badge SVG.
	 *
	 * @param float  $rating    Rating.
	 * @param string $site_name Site name.
	 * @param array  $colors    Colors array.
	 * @return string SVG.
	 */
	private static function svg_rating( $rating, $site_name, $colors ) {
		$stars = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$fill = $i <= round( $rating ) ? $colors['star'] : $colors['light'];
			$x    = 15 + ( $i - 1 ) * 18;
			$stars .= '<polygon points="' . $x . ',8 ' . ( $x + 4 ) . ',16 ' . ( $x + 13 ) . ',17 ' . ( $x + 6 ) . ',22 ' . ( $x + 8 ) . ',31 ' . $x . ',26 ' . ( $x - 8 ) . ',31 ' . ( $x - 6 ) . ',22 ' . ( $x - 13 ) . ',17 ' . ( $x - 4 ) . ',16" fill="' . $fill . '"/>';
		}

		$rating_text = $rating ? $rating : '—';

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 70" width="200" height="70">
	<defs>
		<linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
			<stop offset="0%" style="stop-color:' . $colors['primary'] . '"/>
			<stop offset="100%" style="stop-color:' . $colors['accent'] . '"/>
		</linearGradient>
	</defs>
	<rect width="200" height="70" rx="10" fill="url(#bg)"/>
	<g transform="translate(10, 8)">
		' . $stars . '
	</g>
	<text x="130" y="28" fill="' . $colors['white'] . '" font-family="system-ui, -apple-system, sans-serif" font-size="20" font-weight="bold">
		' . esc_html( $rating_text ) . '
	</text>
	<text x="100" y="55" fill="' . $colors['light'] . '" font-family="system-ui, -apple-system, sans-serif" font-size="12" text-anchor="middle">
		on ' . esc_html( $site_name ) . '
	</text>
</svg>';
	}

	/**
	 * Generate reviews badge SVG.
	 *
	 * @param float  $rating       Rating.
	 * @param int    $review_count Review count.
	 * @param string $site_name    Site name.
	 * @param array  $colors       Colors array.
	 * @return string SVG.
	 */
	private static function svg_reviews( $rating, $review_count, $site_name, $colors ) {
		$stars = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$fill = $i <= round( $rating ) ? $colors['star'] : $colors['light'];
			$x    = 12 + ( $i - 1 ) * 16;
			$stars .= '<polygon points="' . $x . ',7 ' . ( $x + 3.5 ) . ',14 ' . ( $x + 11 ) . ',15 ' . ( $x + 5 ) . ',19 ' . ( $x + 7 ) . ',27 ' . $x . ',23 ' . ( $x - 7 ) . ',27 ' . ( $x - 5 ) . ',19 ' . ( $x - 11 ) . ',15 ' . ( $x - 3.5 ) . ',14" fill="' . $fill . '"/>';
		}

		$rating_text  = $rating ? $rating : '—';
		$reviews_text = sprintf(
			// translators: %d is number of reviews.
			_n( '%d review', '%d reviews', $review_count, 'business-directory' ),
			$review_count
		);

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 80" width="220" height="80">
	<defs>
		<linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
			<stop offset="0%" style="stop-color:' . $colors['primary'] . '"/>
			<stop offset="100%" style="stop-color:' . $colors['accent'] . '"/>
		</linearGradient>
	</defs>
	<rect width="220" height="80" rx="10" fill="url(#bg)"/>
	<g transform="translate(10, 10)">
		' . $stars . '
	</g>
	<text x="115" y="30" fill="' . $colors['white'] . '" font-family="system-ui, -apple-system, sans-serif" font-size="22" font-weight="bold">
		' . esc_html( $rating_text ) . '
	</text>
	<text x="110" y="50" fill="' . $colors['light'] . '" font-family="system-ui, -apple-system, sans-serif" font-size="13" text-anchor="middle">
		' . esc_html( $reviews_text ) . '
	</text>
	<text x="110" y="70" fill="' . $colors['secondary'] . '" font-family="system-ui, -apple-system, sans-serif" font-size="11" text-anchor="middle">
		📍 ' . esc_html( $site_name ) . '
	</text>
</svg>';
	}

	/**
	 * Generate PNG badge.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $style       Badge style.
	 * @param string $size        Badge size.
	 * @return string File URL.
	 */
	public static function generate_png( $business_id, $style = 'rating', $size = 'medium' ) {
		// Get SVG first.
		$svg = self::generate_svg( $business_id, $style );

		if ( ! $svg ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		$badge_dir  = $upload_dir['basedir'] . '/bd-badges/';

		if ( ! file_exists( $badge_dir ) ) {
			wp_mkdir_p( $badge_dir );
		}

		$filename = 'badge-' . $business_id . '-' . $style . '-' . $size . '.svg';
		$filepath = $badge_dir . $filename;
		$file_url = $upload_dir['baseurl'] . '/bd-badges/' . $filename;

		// Save SVG file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $filepath, $svg );

		return $file_url;
	}
}
