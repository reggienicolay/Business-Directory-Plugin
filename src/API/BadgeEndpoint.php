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
	 * Map pin icon SVG path data.
	 */
	const PIN_ICON = 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z';

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
						'enum'              => array( 'rating', 'reviews', 'featured', 'simple' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'format' => array(
						'type'              => 'string',
						'default'           => 'svg',
						'enum'              => array( 'svg', 'png' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'size'   => array(
						'type'              => 'string',
						'default'           => 'medium',
						'enum'              => array( 'small', 'medium', 'large' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'theme'  => array(
						'type'              => 'string',
						'default'           => 'minimal',
						'enum'              => array( 'minimal', 'dark', 'glass', 'premium' ),
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
					'theme' => array(
						'type'              => 'string',
						'default'           => 'minimal',
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
		$theme       = $request->get_param( 'theme' );

		// Clear any output buffers first.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Validate business exists and is published.
		$post = get_post( $business_id );
		if ( ! $post || 'publish' !== $post->post_status || 'bd_business' !== $post->post_type ) {
			header( 'Content-Type: text/plain' );
			echo 'Business not found: ID ' . intval( $business_id );
			die();
		}

		// Get business data.
		$business_name = $post->post_title;
		$rating        = floatval( get_post_meta( $business_id, 'bd_avg_rating', true ) );
		$review_count  = intval( get_post_meta( $business_id, 'bd_review_count', true ) );

		// Check object cache first.
		$cache_key  = 'bd_badge_' . $business_id . '_' . $style . '_' . $theme . '_' . $size;
		$cached_svg = wp_cache_get( $cache_key, 'bd_badges' );
		if ( false !== $cached_svg && 'png' !== $format ) {
			// Serve cached SVG.
			header( 'Content-Type: image/svg+xml' );
			header( 'Cache-Control: public, max-age=3600' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is generated internally
			echo $cached_svg;
			die();
		}

		// Generate SVG.
		$svg = self::generate_svg( $business_id, $business_name, $rating, $review_count, $style, $size, '', $theme );

		// Handle empty SVG.
		if ( empty( $svg ) ) {
			header( 'Content-Type: text/plain' );
			echo 'SVG generation failed for: ' . esc_html( $business_name );
			die();
		}

		// Cache the generated SVG.
		wp_cache_set( $cache_key, $svg, 'bd_badges', 3600 );

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
		$theme       = $request->get_param( 'theme' );

		// Validate business exists.
		$post = get_post( $business_id );
		if ( ! $post || 'publish' !== $post->post_status || 'bd_business' !== $post->post_type ) {
			return new \WP_Error( 'invalid_business', 'Business not found', array( 'status' => 404 ) );
		}

		$badge_url = rest_url( 'bd/v1/badge/' . $business_id . '?style=' . $style . '&size=' . $size . '&theme=' . $theme );
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
	 * @param string $theme         Badge theme (minimal, dark, glass).
	 * @return string SVG markup.
	 */
	private static function generate_svg( $business_id, $business_name, $rating, $review_count, $style, $size, $permalink, $theme = 'minimal' ) {
		// Generate unique prefix for SVG element IDs to prevent collisions when multiple badges on same page.
		$uid = 'bd' . $business_id . substr( md5( $style . $theme . $size ), 0, 4 );

		// Size dimensions.
		$dimensions = array(
			'small'  => array(
				'width'  => 180,
				'height' => 70,
				'font'   => 11,
			),
			'medium' => array(
				'width'  => 240,
				'height' => 90,
				'font'   => 13,
			),
			'large'  => array(
				'width'  => 320,
				'height' => 110,
				'font'   => 15,
			),
		);

		$dim = $dimensions[ $size ] ?? $dimensions['medium'];

		// Truncate business name if too long.
		$max_chars    = 'small' === $size ? 15 : ( 'medium' === $size ? 22 : 30 );
		$display_name = mb_strlen( $business_name ) > $max_chars
			? mb_substr( $business_name, 0, $max_chars - 1 ) . '…'
			: $business_name;

		// Generate stars SVG.
		$stars_svg = self::generate_stars_svg( $rating, (int) ( $dim['font'] * 1.2 ), $uid );

		// Site name.
		$site_name = get_bloginfo( 'name' );

		// Backward compatibility: map 'simple' to 'featured'.
		if ( 'simple' === $style ) {
			$style = 'featured';
		}

		switch ( $style ) {
			case 'reviews':
				$svg = self::generate_reviews_badge( $display_name, $rating, $review_count, $stars_svg, $dim, $site_name, $theme, $uid );
				break;

			case 'featured':
				$svg = self::generate_featured_badge( $display_name, $dim, $site_name, $theme, $uid );
				break;

			case 'rating':
			default:
				$svg = self::generate_rating_badge( $display_name, $rating, $review_count, $stars_svg, $dim, $site_name, $theme, $uid );
				break;
		}

		return $svg;
	}

	/**
	 * Generate the pin icon SVG element.
	 *
	 * @param float  $x    X position.
	 * @param float  $y    Y position.
	 * @param float  $size Icon size.
	 * @param string $fill Fill color.
	 * @return string SVG markup.
	 */
	private static function generate_pin_icon( $x, $y, $size, $fill ) {
		$scale = $size / 24;
		return sprintf(
			'<g transform="translate(%s, %s) scale(%s)"><path d="%s" fill="%s"/></g>',
			$x,
			$y,
			round( $scale, 3 ),
			self::PIN_ICON,
			esc_attr( $fill )
		);
	}

	/**
	 * Generate theme-specific defs (gradients, filters, etc.).
	 *
	 * @param string $theme Badge theme.
	 * @param array  $dim   Dimensions array.
	 * @return string SVG defs markup.
	 */
	private static function generate_theme_defs( $theme, $dim, $uid = '' ) {
		switch ( $theme ) {
			case 'dark':
				return sprintf(
					'<defs>
			<linearGradient id="%1$s-darkBg" x1="0%%" y1="0%%" x2="0%%" y2="100%%">
				<stop offset="0%%" stop-color="#1a3a4a"/>
				<stop offset="100%%" stop-color="#0f2635"/>
			</linearGradient>
			<radialGradient id="%1$s-darkGlow" cx="20%%" cy="20%%" r="60%%">
				<stop offset="0%%" stop-color="#ffffff" stop-opacity="0.04"/>
				<stop offset="100%%" stop-color="#ffffff" stop-opacity="0"/>
			</radialGradient>
		</defs>',
					$uid
				);

			case 'premium':
				return sprintf(
					'<defs>
			<linearGradient id="%1$s-premBg" x1="0%%" y1="0%%" x2="100%%" y2="100%%">
				<stop offset="0%%" stop-color="#0a1a22"/>
				<stop offset="50%%" stop-color="#133453"/>
				<stop offset="100%%" stop-color="#0f2530"/>
			</linearGradient>
			<linearGradient id="%1$s-goldShine" x1="0%%" y1="0%%" x2="100%%" y2="100%%">
				<stop offset="0%%" stop-color="#f8e88c"/>
				<stop offset="30%%" stop-color="#C9A227"/>
				<stop offset="70%%" stop-color="#d4a830"/>
				<stop offset="100%%" stop-color="#f8e88c"/>
			</linearGradient>
			<radialGradient id="%1$s-premGlow" cx="30%%" cy="30%%" r="65%%">
				<stop offset="0%%" stop-color="#C9A227" stop-opacity="0.08"/>
				<stop offset="100%%" stop-color="#C9A227" stop-opacity="0"/>
			</radialGradient>
			<filter id="%1$s-emboss">
				<feGaussianBlur in="SourceAlpha" stdDeviation="0.5" result="b"/>
				<feOffset in="b" dx="-0.5" dy="-0.5" result="hi"/>
				<feOffset in="b" dx="0.5" dy="0.5" result="lo"/>
				<feFlood flood-color="#f8e88c" flood-opacity="0.3" result="hiC"/>
				<feFlood flood-color="#000" flood-opacity="0.4" result="loC"/>
				<feComposite in="hiC" in2="hi" operator="in" result="hiClip"/>
				<feComposite in="loC" in2="lo" operator="in" result="loClip"/>
				<feMerge><feMergeNode in="loClip"/><feMergeNode in="SourceGraphic"/><feMergeNode in="hiClip"/></feMerge>
			</filter>
			<filter id="%1$s-shadow">
				<feDropShadow dx="0" dy="4" stdDeviation="8" flood-color="#000" flood-opacity="0.35"/>
			</filter>
			<clipPath id="%1$s-shimClip"><rect width="%2$d" height="%3$d" rx="14"/></clipPath>
		</defs>',
					$uid,
					$dim['width'],
					$dim['height']
				);

			case 'glass':
				return sprintf(
					'<defs>
			<filter id="%1$s-shadow">
				<feDropShadow dx="0" dy="3" stdDeviation="6" flood-color="#000" flood-opacity="0.12"/>
			</filter>
			<filter id="%1$s-noise" x="0%%" y="0%%" width="100%%" height="100%%">
				<feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="4" result="noise"/>
				<feColorMatrix type="saturate" values="0" in="noise" result="grayNoise"/>
				<feComposite in="grayNoise" in2="SourceGraphic" operator="in" result="noiseClip"/>
			</filter>
		</defs>',
					$uid
				);

			case 'minimal':
			default:
				return sprintf(
					'<defs>
			<filter id="%1$s-shadow">
				<feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#000" flood-opacity="0.08"/>
			</filter>
		</defs>',
					$uid
				);
		}
	}

	/**
	 * Generate theme-specific background elements.
	 *
	 * @param string $theme Badge theme.
	 * @param array  $dim   Dimensions array.
	 * @return string SVG background markup.
	 */
	private static function generate_theme_background( $theme, $dim, $uid = '' ) {
		$w = $dim['width'];
		$h = $dim['height'];

		switch ( $theme ) {
			case 'dark':
				return sprintf(
					'<rect width="%d" height="%d" rx="12" fill="url(#%s-darkBg)"/>' .
					'<rect width="%d" height="2" y="0" rx="1" fill="#f59e0b"/>' .
					'<rect width="%d" height="%d" rx="12" fill="url(#%s-darkGlow)"/>',
					$w,
					$h,
					$uid,
					$w,
					$w,
					$h,
					$uid
				);

			case 'glass':
				return sprintf(
					'<rect width="%d" height="%d" rx="16" fill="#ffffff" fill-opacity="0.75" filter="url(#%s-shadow)" stroke="#ffffff" stroke-opacity="0.4" stroke-width="1"/>' .
					'<rect width="%d" height="%d" rx="16" filter="url(#%s-noise)" opacity="0.03"/>',
					$w,
					$h,
					$uid,
					$w,
					$h,
					$uid
				);

			case 'premium':
				return sprintf(
					'<rect width="%d" height="%d" rx="14" fill="url(#%s-premBg)" filter="url(#%s-shadow)"/>' .
					'<rect width="%d" height="%d" rx="14" fill="url(#%s-premGlow)"/>' .
					'<rect width="%d" height="1.5" y="0" rx="0.75" fill="url(#%s-goldShine)"/>' .
					'<rect width="%d" height="1.5" y="%s" rx="0.75" fill="url(#%s-goldShine)" opacity="0.5"/>' .
					'<line x1="0" y1="0" x2="0" y2="%d" stroke="url(#%s-goldShine)" stroke-width="1" opacity="0.15"/>' .
					'<line x1="%d" y1="0" x2="%d" y2="%d" stroke="url(#%s-goldShine)" stroke-width="1" opacity="0.15"/>' .
					'<g clip-path="url(#%s-shimClip)"><rect x="0" y="-20" width="60" height="%d" fill="url(#%s-goldShine)" opacity="0.04" transform="rotate(25) translate(-200,0)">' .
					'<animateTransform attributeName="transform" type="translate" from="-300,0" to="500,0" dur="4s" repeatCount="indefinite"/></rect></g>',
					$w,
					$h,
					$uid,
					$uid,
					$w,
					$h,
					$uid,
					$w,
					$uid,
					$w,
					$h - 1.5,
					$uid,
					$h,
					$uid,
					$w,
					$w,
					$h,
					$uid,
					$uid,
					$h + 40,
					$uid
				);

			case 'minimal':
			default:
				return sprintf(
					'<rect width="%d" height="%d" rx="12" fill="#ffffff" filter="url(#%s-shadow)" stroke="#e2e8f0" stroke-width="1"/>',
					$w,
					$h,
					$uid
				);
		}
	}

	/**
	 * Get theme-specific text colors.
	 *
	 * @param string $theme Badge theme.
	 * @return array Associative array of color keys.
	 */
	private static function get_theme_colors( $theme ) {
		switch ( $theme ) {
			case 'dark':
				return array(
					'name'    => '#ffffff',
					'rating'  => '#ffffff',
					'reviews' => '#a8c4d4',
					'footer'  => '#7a9eb8',
					'pin'     => '#7a9eb8',
				);

			case 'premium':
				return array(
					'name'    => '#f0f7fa',
					'rating'  => '#C9A227',
					'reviews' => '#7a9eb8',
					'footer'  => '#C9A227',
					'pin'     => '#C9A227',
					'emboss'  => true,
				);

			case 'glass':
			case 'minimal':
			default:
				return array(
					'name'    => '#1a3a4a',
					'rating'  => '#1a3a4a',
					'reviews' => '#7a9eb8',
					'footer'  => '#7a9eb8',
					'pin'     => '#7a9eb8',
				);
		}
	}

	/**
	 * Generate rating style badge.
	 *
	 * Layout:
	 *   Row 1: Business name (left aligned, truncated)
	 *   Row 2: Stars + rating number + "(X reviews)"
	 *   Row 3: Pin icon + "Rated on [site]" (right-aligned)
	 *
	 * @param string $name         Business name.
	 * @param float  $rating       Rating value.
	 * @param int    $review_count Review count.
	 * @param string $stars_svg    Stars SVG markup.
	 * @param array  $dim          Dimensions array.
	 * @param string $site_name    Site name.
	 * @param string $theme        Badge theme (minimal, dark, glass).
	 * @return string SVG markup.
	 */
	private static function generate_rating_badge( $name, $rating, $review_count, $stars_svg, $dim, $site_name, $theme = 'minimal', $uid = '' ) {
		$rating_display = $rating > 0 ? number_format( $rating, 1 ) : 'New';
		$reviews_text   = $review_count > 0 ? sprintf( '(%d reviews)', $review_count ) : '';
		$colors         = self::get_theme_colors( $theme );
		$text_filter    = ! empty( $colors['emboss'] ) ? sprintf( ' filter="url(#%s-emboss)"', $uid ) : '';

		$w = $dim['width'];
		$h = $dim['height'];
		$f = $dim['font'];

		$defs       = self::generate_theme_defs( $theme, $dim, $uid );
		$background = self::generate_theme_background( $theme, $dim, $uid );

		// Row positions.
		$name_y     = $f + 12;
		$stars_y    = $f + 22;
		$star_size  = (int) ( $f * 1.2 );
		$rating_x   = 14 + ( 5 * ( $star_size + 2 ) ) + 8;
		$rating_y   = $f + 34;
		$footer_y   = $h - 8;
		$pin_icon   = self::generate_pin_icon( $w - 10 - ( $f * 0.5 ) - mb_strlen( 'Rated on ' . $site_name ) * ( ( $f - 3 ) * 0.55 ), $footer_y - ( $f - 3 ) + 1, 8, $colors['pin'] );
		$footer_x   = $w - 10;
		$small_font = $f - 3;

		return sprintf(
			'<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
	%s
	%s
	<text x="14" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="600" fill="%s"%s>%s</text>
	<g transform="translate(14, %d)">%s</g>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="700" fill="%s"%s>%s</text>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="%s">%s</text>
	<g>%s</g>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="%s"%s text-anchor="end">Rated on %s</text>
</svg>',
			$w,
			$h,
			$w,
			$h,
			$defs,
			$background,
			$name_y,
			$f,
			esc_attr( $colors['name'] ),
			$text_filter,
			esc_html( $name ),
			$stars_y,
			$stars_svg,
			$rating_x,
			$rating_y,
			$f - 1,
			esc_attr( $colors['rating'] ),
			$text_filter,
			$rating_display,
			$rating_x + ( $f * 2 ),
			$rating_y,
			$f - 2,
			esc_attr( $colors['reviews'] ),
			$reviews_text,
			$pin_icon,
			$footer_x,
			$footer_y,
			$small_font,
			esc_attr( $colors['footer'] ),
			$text_filter,
			esc_html( $site_name )
		);
	}

	/**
	 * Generate reviews style badge.
	 *
	 * Layout:
	 *   Row 1: Business name (left aligned)
	 *   Row 2: Stars + rating number bold
	 *   Row 3: "X reviews" text
	 *   Row 4: Pin icon + "Rated on [site]" (right-aligned)
	 *
	 * @param string $name         Business name.
	 * @param float  $rating       Rating value.
	 * @param int    $review_count Review count.
	 * @param string $stars_svg    Stars SVG markup.
	 * @param array  $dim          Dimensions array.
	 * @param string $site_name    Site name.
	 * @param string $theme        Badge theme (minimal, dark, glass).
	 * @return string SVG markup.
	 */
	private static function generate_reviews_badge( $name, $rating, $review_count, $stars_svg, $dim, $site_name, $theme = 'minimal', $uid = '' ) {
		$rating_display = $rating > 0 ? number_format( $rating, 1 ) : '-';
		$colors         = self::get_theme_colors( $theme );
		$text_filter    = ! empty( $colors['emboss'] ) ? sprintf( ' filter="url(#%s-emboss)"', $uid ) : '';

		$w = $dim['width'];
		$h = $dim['height'];
		$f = $dim['font'];

		$defs       = self::generate_theme_defs( $theme, $dim, $uid );
		$background = self::generate_theme_background( $theme, $dim, $uid );

		// Row positions.
		$name_y     = $f + 12;
		$stars_y    = $f + 22;
		$star_size  = (int) ( $f * 1.2 );
		$rating_x   = 14 + ( 5 * ( $star_size + 2 ) ) + 8;
		$rating_y   = $f + 34;
		$reviews_y  = $f + 48;
		$footer_y   = $h - 8;
		$footer_x   = $w - 10;
		$small_font = $f - 3;
		$pin_icon   = self::generate_pin_icon( $w - 10 - ( $f * 0.5 ) - mb_strlen( 'Rated on ' . $site_name ) * ( $small_font * 0.55 ), $footer_y - $small_font + 1, 8, $colors['pin'] );

		return sprintf(
			'<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
	%s
	%s
	<text x="14" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="600" fill="%s">%s</text>
	<g transform="translate(14, %d)">%s</g>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="700" fill="%s">%s</text>
	<text x="14" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="%s">%d reviews</text>
	<g>%s</g>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="%s" text-anchor="end">Rated on %s</text>
</svg>',
			$w,
			$h,
			$w,
			$h,
			$defs,
			$background,
			$name_y,
			$f,
			esc_attr( $colors['name'] ),
			esc_html( $name ),
			$stars_y,
			$stars_svg,
			$rating_x,
			$rating_y,
			$f + 4,
			esc_attr( $colors['rating'] ),
			$rating_display,
			$reviews_y,
			$f - 2,
			esc_attr( $colors['reviews'] ),
			$review_count,
			$pin_icon,
			$footer_x,
			$footer_y,
			$small_font,
			esc_attr( $colors['footer'] ),
			esc_html( $site_name )
		);
	}

	/**
	 * Generate featured style badge (no stars).
	 *
	 * Layout:
	 *   Row 1: "VERIFIED" in small caps, letter-spacing 3
	 *   Row 2: Business name larger, bold
	 *   Row 3: Pin icon + "[site]" centered
	 *
	 * @param string $name      Business name.
	 * @param array  $dim       Dimensions array.
	 * @param string $site_name Site name.
	 * @param string $theme     Badge theme (minimal, dark, glass).
	 * @return string SVG markup.
	 */
	private static function generate_featured_badge( $name, $dim, $site_name, $theme = 'minimal', $uid = '' ) {
		$colors      = self::get_theme_colors( $theme );
		$text_filter = ! empty( $colors['emboss'] ) ? sprintf( ' filter="url(#%s-emboss)"', $uid ) : '';

		$w = $dim['width'];
		$h = $dim['height'];
		$f = $dim['font'];

		$defs       = self::generate_theme_defs( $theme, $dim, $uid );
		$background = self::generate_theme_background( $theme, $dim, $uid );

		// Row positions.
		$verified_y = $f + 12;
		$name_y     = (int) ( $h / 2 ) + 4;
		$footer_y   = $h - 10;
		$center_x   = (int) ( $w / 2 );
		$small_font = $f - 3;

		// Pin icon centered before site name.
		$pin_icon = self::generate_pin_icon( $center_x - ( mb_strlen( $site_name ) * ( $small_font * 0.55 ) / 2 ) - 12, $footer_y - $small_font + 1, 8, $colors['pin'] );

		return sprintf(
			'<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">
	%s
	%s
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="%s" text-anchor="middle" letter-spacing="3" font-variant="small-caps">VERIFIED</text>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" font-weight="700" fill="%s" text-anchor="middle">%s</text>
	<g>%s</g>
	<text x="%d" y="%d" font-family="system-ui, -apple-system, sans-serif" font-size="%d" fill="%s" text-anchor="middle">%s</text>
</svg>',
			$w,
			$h,
			$w,
			$h,
			$defs,
			$background,
			$center_x,
			$verified_y,
			$f - 2,
			esc_attr( $colors['footer'] ),
			$center_x,
			$name_y,
			$f + 2,
			esc_attr( $colors['name'] ),
			esc_html( $name ),
			$pin_icon,
			$center_x,
			$footer_y,
			$small_font,
			esc_attr( $colors['footer'] ),
			esc_html( $site_name )
		);
	}

	/**
	 * Generate stars SVG with fractional fill support.
	 *
	 * For a rating of 4.3, stars 1-4 are fully gold and star 5 is 30% gold
	 * using a linearGradient with a hard color stop.
	 *
	 * @param float $rating    Rating value (0-5).
	 * @param int   $star_size Size of each star.
	 * @return string SVG markup for stars.
	 */
	private static function generate_stars_svg( $rating, $star_size, $uid = '' ) {
		$gold = '#f59e0b';
		$gray = '#e2e8f0';

		// Star path (scaled to 12x12 viewbox).
		$star_path = 'M6 0l1.76 3.57 3.94.57-2.85 2.78.67 3.93L6 9.09l-3.52 1.85.67-3.93L.3 4.14l3.94-.57z';

		$full_stars   = (int) floor( $rating );
		$fraction     = $rating - $full_stars;
		$fraction_pct = (int) round( $fraction * 100 );

		// Build defs for partial star gradient if needed.
		$defs  = '';
		$stars = '';

		if ( $fraction > 0 && $full_stars < 5 ) {
			$partial_index = $full_stars + 1;
			$defs          = sprintf(
				'<defs><linearGradient id="%s-partial-star-%d">' .
				'<stop offset="%d%%" stop-color="%s"/>' .
				'<stop offset="%d%%" stop-color="%s"/>' .
				'</linearGradient></defs>',
				$uid,
				$partial_index,
				$fraction_pct,
				$gold,
				$fraction_pct,
				$gray
			);
		}

		for ( $i = 1; $i <= 5; $i++ ) {
			$x = ( $i - 1 ) * ( $star_size + 2 );

			if ( $i <= $full_stars ) {
				$fill = $gold;
			} elseif ( $i === $full_stars + 1 && $fraction > 0 ) {
				$fill = sprintf( 'url(#%s-partial-star-%d)', $uid, $i );
			} else {
				$fill = $gray;
			}

			$stars .= sprintf(
				'<g transform="translate(%d, 0) scale(%s)"><path d="%s" fill="%s"/></g>',
				$x,
				round( $star_size / 12, 2 ),
				$star_path,
				$fill
			);
		}

		return $defs . $stars;
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
				'small'  => array( 180, 70 ),
				'medium' => array( 240, 90 ),
				'large'  => array( 320, 110 ),
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
			error_log( 'BD Badge PNG conversion error: ' . $e->getMessage() );
			header( 'X-PNG-Error: Conversion failed' );
			header( 'Content-Length: ' . strlen( $svg ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $svg;
			die();
		}
	}
}

BadgeEndpoint::init();
