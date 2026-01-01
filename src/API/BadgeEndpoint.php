<?php
/**
 * Badge Endpoint - REST API for business marketing badge generation
 *
 * Generates embeddable SVG/PNG badges showing business ratings
 * for use on external websites.
 *
 * @package BusinessDirectory
 */

namespace BD\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Badge Endpoint class.
 */
class BadgeEndpoint {

	/**
	 * Initialize the endpoint.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		// Get badge image (SVG/PNG).
		register_rest_route(
			'bd/v1',
			'/badge/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_badge' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'style'  => array(
						'type'              => 'string',
						'default'           => 'rating',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'format' => array(
						'type'              => 'string',
						'default'           => 'svg',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'size'   => array(
						'type'              => 'string',
						'default'           => 'medium',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get badge embed code.
		register_rest_route(
			'bd/v1',
			'/badge/(?P<id>\d+)/code',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_badge_code' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'style' => array(
						'type'              => 'string',
						'default'           => 'rating',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'size'  => array(
						'type'              => 'string',
						'default'           => 'medium',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get badge image.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public static function get_badge( $request ) {
		$business_id = $request->get_param( 'id' );
		$style       = $request->get_param( 'style' );
		$format      = $request->get_param( 'format' );
		$size        = $request->get_param( 'size' );

		// Clear any output buffers first.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Validate business exists and is published.
		$post = get_post( $business_id );
		if ( ! $post || 'publish' !== $post->post_status || 'bd_business' !== $post->post_type ) {
			header( 'Content-Type: text/plain' );
			echo 'Business not found: ID ' . $business_id;
			die();
		}

		// Get business data.
		$business_name = $post->post_title;
		$rating        = floatval( get_post_meta( $business_id, 'bd_avg_rating', true ) );
		$review_count  = intval( get_post_meta( $business_id, 'bd_review_count', true ) );

		// Generate SVG.
		$svg = self::generate_svg( $business_name, $rating, $review_count, $style, $size, '' );

		// Handle empty SVG.
		if ( empty( $svg ) ) {
			header( 'Content-Type: text/plain' );
			echo 'SVG generation failed for: ' . $business_name;
			die();
		}

		if ( 'png' === $format ) {
			self::serve_png( $svg, $size, $business_id );
		}

		// Serve SVG directly.
		header( 'Content-Type: image/svg+xml' );
		header( 'Content-Disposition: attachment; filename="badge-' . intval( $business_id ) . '.svg"' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'Content-Length: ' . strlen( $svg ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is generated internally
		echo $svg;
		die();
	}

	/**
	 * Get badge embed code.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_badge_code( $request ) {
		$business_id = $request->get_param( 'id' );
		$style       = $request->get_param( 'style' );
		$size        = $request->get_param( 'size' );

		// Validate business exists.
		$post = get_post( $business_id );
		if ( ! $post || 'publish' !== $post->post_status || 'bd_business' !== $post->post_type ) {
			return new \WP_Error( 'invalid_business', 'Business not found', array( 'status' => 404 ) );
		}

		$badge_url = rest_url( 'bd/v1/badge/' . $business_id . '?style=' . $style . '&size=' . $size );
		$permalink = get_permalink( $business_id );

		$code = sprintf(
			'<a href="%s" target="_blank" rel="noopener">' .
			'<img src="%s" alt="View %s on %s" style="max-width: 100%%; height: auto;" />' .
			'</a>',
			esc_url( $permalink ),
			esc_url( $badge_url ),
			esc_attr( $post->post_title ),
			esc_attr( get_bloginfo( 'name' ) )
		);

		return rest_ensure_response(
			array(
				'code'      => $code,
				'badge_url' => $badge_url,
				'permalink' => $permalink,
			)
		);
	}

	/**
	 * Generate SVG badge.
	 *
	 * @param string $business_name Business name.
	 * @param float  $rating        Average rating.
	 * @param int    $review_count  Number of reviews.
	 * @param string $style         Badge style (rating, reviews, featured).
	 * @param string $size          Badge size (small, medium, large).
	 * @param string $permalink     Business permalink.
	 * @return string SVG markup.
	 */
	private static function generate_svg( $business_name, $rating, $review_count, $style, $size, $permalink ) {
		// Size dimensions.
		$dimensions = array(
			'small'  => array(
				'width'  => 150,
				'height' => 60,
				'font'   => 10,
			),
			'medium' => array(
				'width'  => 200,
				'height' => 80,
				'font'   => 12,
			),
			'large'  => array(
				'width'  => 280,
				'height' => 100,
				'font'   => 14,
			),
		);

		$dim = $dimensions[ $size ] ?? $dimensions['medium'];

		// Truncate business name if too long.
		$max_chars    = 'small' === $size ? 15 : ( 'medium' === $size ? 22 : 30 );
		$display_name = mb_strlen( $business_name ) > $max_chars
			? mb_substr( $business_name, 0, $max_chars - 1 ) . '…'
			: $business_name;

		// Generate stars SVG.
		$stars_svg = self::generate_stars_svg( $rating, (int) ( $dim['font'] * 1.2 ) );

		// Site name.
		$site_name = get_bloginfo( 'name' );

		switch ( $style ) {
			case 'reviews':
				$svg = self::generate_reviews_badge( $display_name, $rating, $review_count, $stars_svg, $dim, $site_name );
				break;

			case 'featured':
				$svg = self::generate_featured_badge( $display_name, $dim, $site_name );
				break;

			case 'rating':
			default:
				$svg = self::generate_rating_badge( $display_name, $rating, $review_count, $stars_svg, $dim, $site_name );
				break;
		}

		return $svg;
	}

	/**
	 * Generate rating style badge.
	 *
	 * @param string $name         Business name.
	 * @param float  $rating       Rating value.
	 * @param int    $review_count Review count.
	 * @param string $stars_svg    Stars SVG markup.
	 * @param array  $dim          Dimensions array.
	 * @param string $site_name    Site name.
	 * @return string SVG markup.
	 */
	private static function generate_rating_badge( $name, $rating, $review_count, $stars_svg, $dim, $site_name ) {
		$rating_display = $rating > 0 ? number_format( $rating, 1 ) : 'New';
		$reviews_text   = $review_count > 0 ? sprintf( '(%d reviews)', $review_count ) : '';

		return sprintf(
			'<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
	<defs>
		<linearGradient id="bgGrad" x1="0%%" y1="0%%" x2="0%%" y2="100%%">
			<stop offset="0%%" style="stop-color:#ffffff;stop-opacity:1" />
			<stop offset="100%%" style="stop-color:#f0f5f8;stop-opacity:1" />
		</linearGradient>
	</defs>
	<rect width="%d" height="%d" rx="8" fill="url(#bgGrad)" stroke="#d1dce5" stroke-width="1"/>
	<text x="12" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="600" fill="#1a3a4a">%s</text>
	<g transform="translate(12, %d)">%s</g>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="#5d7a8c">%s %s</text>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="#7a9eb8" text-anchor="end">%s</text>
</svg>',
			$dim['width'],
			$dim['height'],
			$dim['width'],
			$dim['height'],
			$dim['width'],
			$dim['height'],
			$dim['font'] + 10,
			$dim['font'],
			esc_html( $name ),
			$dim['font'] + 18,
			$stars_svg,
			12 + ( $dim['font'] * 6 ),
			$dim['font'] + 30,
			$dim['font'] - 2,
			$rating_display,
			$reviews_text,
			$dim['width'] - 10,
			$dim['height'] - 8,
			$dim['font'] - 3,
			esc_html( $site_name )
		);
	}

	/**
	 * Generate reviews style badge.
	 *
	 * @param string $name         Business name.
	 * @param float  $rating       Rating value.
	 * @param int    $review_count Review count.
	 * @param string $stars_svg    Stars SVG markup.
	 * @param array  $dim          Dimensions array.
	 * @param string $site_name    Site name.
	 * @return string SVG markup.
	 */
	private static function generate_reviews_badge( $name, $rating, $review_count, $stars_svg, $dim, $site_name ) {
		$rating_display = $rating > 0 ? number_format( $rating, 1 ) : '-';

		return sprintf(
			'<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
	<rect width="%d" height="%d" rx="8" fill="#1a3a4a"/>
	<text x="12" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="600" fill="#ffffff">%s</text>
	<g transform="translate(12, %d)">%s</g>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="700" fill="#ffffff">%s</text>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="#a8c4d4">%d reviews</text>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="#7a9eb8" text-anchor="end">%s</text>
</svg>',
			$dim['width'],
			$dim['height'],
			$dim['width'],
			$dim['height'],
			$dim['width'],
			$dim['height'],
			$dim['font'] + 10,
			$dim['font'],
			esc_html( $name ),
			$dim['font'] + 18,
			$stars_svg,
			12 + ( $dim['font'] * 6 ),
			$dim['font'] + 26,
			$dim['font'] + 4,
			$rating_display,
			12 + ( $dim['font'] * 6 ),
			$dim['font'] + 40,
			$dim['font'] - 2,
			$review_count,
			$dim['width'] - 10,
			$dim['height'] - 8,
			$dim['font'] - 3,
			esc_html( $site_name )
		);
	}

	/**
	 * Generate featured style badge.
	 *
	 * @param string $name      Business name.
	 * @param array  $dim       Dimensions array.
	 * @param string $site_name Site name.
	 * @return string SVG markup.
	 */
	private static function generate_featured_badge( $name, $dim, $site_name ) {
		return sprintf(
			'<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
	<defs>
		<linearGradient id="featGrad" x1="0%%" y1="0%%" x2="100%%" y2="100%%">
			<stop offset="0%%" style="stop-color:#f59e0b;stop-opacity:1" />
			<stop offset="100%%" style="stop-color:#d97706;stop-opacity:1" />
		</linearGradient>
	</defs>
	<rect width="%d" height="%d" rx="8" fill="url(#featGrad)"/>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="700" fill="#ffffff" text-anchor="middle">★ FEATURED ★</text>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="600" fill="#ffffff" text-anchor="middle">%s</text>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="#fef3c7" text-anchor="middle">on %s</text>
</svg>',
			$dim['width'],
			$dim['height'],
			$dim['width'],
			$dim['height'],
			$dim['width'],
			$dim['height'],
			$dim['width'] / 2,
			$dim['font'] + 12,
			$dim['font'],
			$dim['width'] / 2,
			$dim['height'] / 2 + 4,
			$dim['font'] + 2,
			esc_html( $name ),
			$dim['width'] / 2,
			$dim['height'] - 10,
			$dim['font'] - 2,
			esc_html( $site_name )
		);
	}

	/**
	 * Generate stars SVG using actual star paths.
	 *
	 * @param float $rating    Rating value (0-5).
	 * @param int   $star_size Size of each star.
	 * @return string SVG markup for stars.
	 */
	private static function generate_stars_svg( $rating, $star_size ) {
		$stars = '';
		$gold  = '#f59e0b';
		$gray  = '#d1dce5';

		// Star path (scaled to 12x12 viewbox).
		$star_path = 'M6 0l1.76 3.57 3.94.57-2.85 2.78.67 3.93L6 9.09l-3.52 1.85.67-3.93L.3 4.14l3.94-.57z';

		for ( $i = 1; $i <= 5; $i++ ) {
			$fill = ( $i <= $rating ) ? $gold : $gray;
			$x    = ( $i - 1 ) * ( $star_size + 2 );

			$stars .= sprintf(
				'<g transform="translate(%d, 0) scale(%s)"><path d="%s" fill="%s"/></g>',
				$x,
				round( $star_size / 12, 2 ),
				$star_path,
				esc_attr( $fill )
			);
		}

		return $stars;
	}

	/**
	 * Serve PNG version of badge.
	 *
	 * @param string $svg         SVG markup.
	 * @param string $size        Badge size.
	 * @param int    $business_id Business ID.
	 */
	private static function serve_png( $svg, $size, $business_id ) {
		// Clear any output buffers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Check if Imagick is available.
		if ( ! class_exists( 'Imagick' ) ) {
			// Fallback: serve SVG instead with a note.
			header( 'Content-Type: image/svg+xml' );
			header( 'Content-Disposition: attachment; filename="badge-' . $business_id . '.svg"' );
			header( 'X-PNG-Note: PNG conversion requires Imagick. Serving SVG instead.' );
			header( 'Content-Length: ' . strlen( $svg ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $svg;
			die();
		}

		try {
			$dimensions = array(
				'small'  => array( 150, 60 ),
				'medium' => array( 200, 80 ),
				'large'  => array( 280, 100 ),
			);

			$dim = $dimensions[ $size ] ?? $dimensions['medium'];

			$imagick = new \Imagick();
			$imagick->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			$imagick->readImageBlob( $svg );
			$imagick->setImageFormat( 'png' );
			$imagick->resizeImage( $dim[0] * 2, $dim[1] * 2, \Imagick::FILTER_LANCZOS, 1 ); // 2x for retina.

			$png_data = $imagick->getImageBlob();
			$imagick->destroy();

			header( 'Content-Type: image/png' );
			header( 'Content-Disposition: attachment; filename="badge-' . $business_id . '.png"' );
			header( 'Cache-Control: public, max-age=3600' );
			header( 'Content-Length: ' . strlen( $png_data ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $png_data;
			die();

		} catch ( \Exception $e ) {
			// Fallback to SVG on error.
			header( 'Content-Type: image/svg+xml' );
			header( 'Content-Disposition: attachment; filename="badge-' . $business_id . '.svg"' );
			header( 'X-PNG-Error: ' . $e->getMessage() );
			header( 'Content-Length: ' . strlen( $svg ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $svg;
			die();
		}
	}
}

BadgeEndpoint::init();
