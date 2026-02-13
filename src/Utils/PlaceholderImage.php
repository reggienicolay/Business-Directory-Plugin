<?php
/**
 * Placeholder Image Generator
 *
 * Generates unique SVG placeholder images for businesses without photos.
 * Uses the "Classic Contours" style with topographic lines and category icons.
 *
 * @package BusinessDirectory
 * @since 1.0.0
 */

namespace BusinessDirectory\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PlaceholderImage
 */
class PlaceholderImage {

	/**
	 * In-memory cache for generated SVGs.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Design tokens - Love TriValley brand colors.
	 *
	 * @var array
	 */
	private static $colors = array(
		'primary600' => '#133453',
		'primary700' => '#0f2530',
		'accent300'  => '#a8c4d4',
		'accent400'  => '#7a9eb8',
		'gold500'    => '#C9A227',
	);

	/**
	 * Category icon SVG paths (24x24 viewBox).
	 *
	 * @var array
	 */
	private static $category_icons = array(
		'winery'        => 'M12 2C13.1 2 14 2.9 14 4V8L19 13V20C19 21.1 18.1 22 17 22H7C5.9 22 5 21.1 5 20V13L10 8V4C10 2.9 10.9 2 12 2Z',
		'wine'          => 'M12 2C13.1 2 14 2.9 14 4V8L19 13V20C19 21.1 18.1 22 17 22H7C5.9 22 5 21.1 5 20V13L10 8V4C10 2.9 10.9 2 12 2Z',
		'restaurant'    => 'M11 9H9V2H7V9H5V2H3V9C3 11.12 4.66 12.84 6.75 12.97V22H9.25V12.97C11.34 12.84 13 11.12 13 9V2H11V9ZM16 6V14H18.5V22H21V2C18.24 2 16 4.24 16 7V6Z',
		'food'          => 'M11 9H9V2H7V9H5V2H3V9C3 11.12 4.66 12.84 6.75 12.97V22H9.25V12.97C11.34 12.84 13 11.12 13 9V2H11V9ZM16 6V14H18.5V22H21V2C18.24 2 16 4.24 16 7V6Z',
		'dining'        => 'M11 9H9V2H7V9H5V2H3V9C3 11.12 4.66 12.84 6.75 12.97V22H9.25V12.97C11.34 12.84 13 11.12 13 9V2H11V9ZM16 6V14H18.5V22H21V2C18.24 2 16 4.24 16 7V6Z',
		'eat-drink'     => 'M11 9H9V2H7V9H5V2H3V9C3 11.12 4.66 12.84 6.75 12.97V22H9.25V12.97C11.34 12.84 13 11.12 13 9V2H11V9ZM16 6V14H18.5V22H21V2C18.24 2 16 4.24 16 7V6Z',
		'entertainment' => 'M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 16.5V7.5L16 12L10 16.5Z',
		'events'        => 'M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 16.5V7.5L16 12L10 16.5Z',
		'shopping'      => 'M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3zm7 17H5V8h14v12z',
		'retail'        => 'M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3zm7 17H5V8h14v12z',
		'services'      => 'M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z',
		'health'        => 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z',
		'medical'       => 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z',
		'fitness'       => 'M20.57 14.86L22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 2.71 2.71 4.14l1.43 1.43L2 7.71l1.43 1.43L2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22l1.43-1.43L16.29 22l2.14-2.14 1.43 1.43 1.43-1.43-1.43-1.43L22 16.29z',
		'gym'           => 'M20.57 14.86L22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 2.71 2.71 4.14l1.43 1.43L2 7.71l1.43 1.43L2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22l1.43-1.43L16.29 22l2.14-2.14 1.43 1.43 1.43-1.43-1.43-1.43L22 16.29z',
		'beauty'        => 'M12 22c4.97 0 9-4.03 9-9-4.97 0-9 4.03-9 9zM5.6 10.25c0 1.38 1.12 2.5 2.5 2.5.53 0 1.01-.16 1.42-.44l-.02.19c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5l-.02-.19c.4.28.89.44 1.42.44 1.38 0 2.5-1.12 2.5-2.5 0-1-.59-1.85-1.43-2.25.84-.4 1.43-1.25 1.43-2.25 0-1.38-1.12-2.5-2.5-2.5-.53 0-1.01.16-1.42.44l.02-.19C14.5 4.12 13.38 3 12 3S9.5 4.12 9.5 5.5l.02.19c-.4-.28-.89-.44-1.42-.44-1.38 0-2.5 1.12-2.5 2.5 0 1 .59 1.85 1.43 2.25-.84.4-1.43 1.25-1.43 2.25zM12 5.5c1.38 0 2.5 1.12 2.5 2.5s-1.12 2.5-2.5 2.5S9.5 9.38 9.5 8s1.12-2.5 2.5-2.5zM3 13c0 4.97 4.03 9 9 9 0-4.97-4.03-9-9-9z',
		'spa'           => 'M12 22c4.97 0 9-4.03 9-9-4.97 0-9 4.03-9 9zM5.6 10.25c0 1.38 1.12 2.5 2.5 2.5.53 0 1.01-.16 1.42-.44l-.02.19c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5l-.02-.19c.4.28.89.44 1.42.44 1.38 0 2.5-1.12 2.5-2.5 0-1-.59-1.85-1.43-2.25.84-.4 1.43-1.25 1.43-2.25 0-1.38-1.12-2.5-2.5-2.5-.53 0-1.01.16-1.42.44l.02-.19C14.5 4.12 13.38 3 12 3S9.5 4.12 9.5 5.5l.02.19c-.4-.28-.89-.44-1.42-.44-1.38 0-2.5 1.12-2.5 2.5 0 1 .59 1.85 1.43 2.25-.84.4-1.43 1.25-1.43 2.25zM12 5.5c1.38 0 2.5 1.12 2.5 2.5s-1.12 2.5-2.5 2.5S9.5 9.38 9.5 8s1.12-2.5 2.5-2.5zM3 13c0 4.97 4.03 9 9 9 0-4.97-4.03-9-9-9z',
		'automotive'    => 'M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z',
		'auto'          => 'M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z',
		'real-estate'   => 'M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z',
		'home'          => 'M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z',
		'education'     => 'M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z',
		'school'        => 'M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z',
		'outdoor'       => 'M14 6l-3.75 5 2.85 3.8-1.6 1.2C9.81 13.75 7 10 7 10l-6 8h22L14 6z',
		'nature'        => 'M14 6l-3.75 5 2.85 3.8-1.6 1.2C9.81 13.75 7 10 7 10l-6 8h22L14 6z',
		'parks'         => 'M14 6l-3.75 5 2.85 3.8-1.6 1.2C9.81 13.75 7 10 7 10l-6 8h22L14 6z',
		'hotel'         => 'M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z',
		'lodging'       => 'M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z',
		'coffee'        => 'M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.9 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z',
		'cafe'          => 'M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.11 0 2-.9 2-2V5c0-1.11-.89-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z',
		'default'       => 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
	);

	/**
	 * Generate hash code from string.
	 * Uses crc32 for consistent cross-platform results.
	 *
	 * @param string $str Input string.
	 * @return int Positive hash integer.
	 */
	private static function hash_code( $str ) {
		// crc32 is faster and consistent across 32/64-bit PHP.
		return abs( crc32( $str ) );
	}

	/**
	 * Get the icon path for a category.
	 *
	 * @param string $category Category name.
	 * @return string SVG path data.
	 */
	private static function get_category_icon( $category ) {
		if ( empty( $category ) ) {
			return self::$category_icons['default'];
		}

		$category = strtolower( trim( $category ) );
		$category = preg_replace( '/[^a-z0-9-]/', '-', $category );

		if ( isset( self::$category_icons[ $category ] ) ) {
			return self::$category_icons[ $category ];
		}

		// Partial match fallback.
		foreach ( self::$category_icons as $key => $path ) {
			if ( strpos( $category, $key ) !== false || strpos( $key, $category ) !== false ) {
				return $path;
			}
		}

		return self::$category_icons['default'];
	}

	/**
	 * Generate the Classic Contours SVG placeholder.
	 *
	 * @param array $args Generation arguments.
	 * @return string SVG markup.
	 */
	public static function generate( $args ) {
		$defaults = array(
			'name'     => 'Business',
			'category' => 'default',
			'lat'      => 37.6819,
			'lng'      => -121.7680,
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize inputs.
		$name     = sanitize_text_field( $args['name'] );
		$category = sanitize_text_field( $args['category'] );
		$lat      = floatval( $args['lat'] );
		$lng      = floatval( $args['lng'] );

		// Clamp lat/lng to valid ranges.
		$lat = max( -90, min( 90, $lat ) );
		$lng = max( -180, min( 180, $lng ) );

		$hash = self::hash_code( $name );

		$center_x = 150 + ( $hash % 60 ) - 30;
		$center_y = 100 + ( self::hash_code( $name . 'y' ) % 40 ) - 20;

		$contours  = self::generate_contours( $name, $center_x, $center_y );
		$icon_path = self::get_category_icon( $category );

		// Build coordinates text.
		$lat_dir    = $lat >= 0 ? 'N' : 'S';
		$lng_dir    = $lng >= 0 ? 'E' : 'W';
		$coord_text = number_format( abs( $lat ), 2 ) . $lat_dir . ' ' . number_format( abs( $lng ), 2 ) . $lng_dir;

		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200" preserveAspectRatio="xMidYMid slice" style="width:100%;height:100%;">';
		$svg .= '<rect width="300" height="200" fill="' . esc_attr( self::$colors['primary600'] ) . '"/>';
		$svg .= $contours;
		$svg .= '<circle cx="' . esc_attr( $center_x ) . '" cy="' . esc_attr( $center_y ) . '" r="40" fill="rgba(255,255,255,0.15)"/>';

		$icon_offset_x = $center_x - 26;
		$icon_offset_y = $center_y - 26;
		$svg          .= '<g transform="translate(' . esc_attr( $icon_offset_x ) . ',' . esc_attr( $icon_offset_y ) . ') scale(2.2)" opacity="0.9">';
		$svg          .= '<path d="' . esc_attr( $icon_path ) . '" fill="white"/>';
		$svg          .= '</g>';

		$svg .= '<text x="150" y="190" text-anchor="middle" fill="' . esc_attr( self::$colors['gold500'] ) . '" font-size="10" font-family="sans-serif" opacity="0.5">';
		$svg .= esc_html( $coord_text );
		$svg .= '</text>';

		$svg .= '</svg>';

		return $svg;
	}

	/**
	 * Generate contour line paths.
	 *
	 * @param string $name     Business name for deterministic randomness.
	 * @param float  $center_x Center X coordinate.
	 * @param float  $center_y Center Y coordinate.
	 * @return string SVG path elements.
	 */
	private static function generate_contours( $name, $center_x, $center_y ) {
		$contours = '';
		$colors   = self::$colors;

		for ( $i = 0; $i < 9; $i++ ) {
			$radius = 20 + $i * 18;
			$points = 20;
			$path   = '';

			for ( $j = 0; $j <= $points; $j++ ) {
				$angle = ( $j / $points ) * M_PI * 2;
				$noise = ( self::hash_code( $name . $i . $j ) % 14 ) - 7;
				$x     = $center_x + cos( $angle ) * ( $radius + $noise );
				$y     = $center_y + sin( $angle ) * ( $radius + $noise );

				if ( 0 === $j ) {
					$path .= 'M ' . round( $x, 2 ) . ' ' . round( $y, 2 );
				} else {
					$path .= ' L ' . round( $x, 2 ) . ' ' . round( $y, 2 );
				}
			}
			$path .= ' Z';

			$stroke_color = ( 0 === $i % 2 ) ? $colors['gold500'] : $colors['accent300'];
			$stroke_width = ( 0 === $i % 3 ) ? 2.5 : 1.5;
			$opacity      = 0.25 + $i * 0.07;

			$contours .= '<path d="' . esc_attr( $path ) . '" fill="none" ';
			$contours .= 'stroke="' . esc_attr( $stroke_color ) . '" ';
			$contours .= 'stroke-width="' . esc_attr( $stroke_width ) . '" ';
			$contours .= 'opacity="' . esc_attr( $opacity ) . '"/>';
		}

		return $contours;
	}

	/**
	 * Generate placeholder for a business post.
	 *
	 * @param int|WP_Post $business Business post ID or object.
	 * @return string SVG markup.
	 */
	public static function generate_for_business( $business ) {
		$business = get_post( $business );

		if ( ! $business ) {
			return self::generate( array() );
		}

		// Check in-memory cache first.
		$cache_key = 'business_' . $business->ID;
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$name     = $business->post_title;
		$location = get_post_meta( $business->ID, 'bd_location', true );

		// Get category safely.
		$category = 'default';
		if ( taxonomy_exists( 'bd_category' ) ) {
			$categories = wp_get_post_terms( $business->ID, 'bd_category', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
				$category = $categories[0];
			}
		}

		$lat = 37.6819;
		$lng = -121.7680;

		if ( is_array( $location ) && isset( $location['lat'], $location['lng'] ) ) {
			$lat = floatval( $location['lat'] );
			$lng = floatval( $location['lng'] );
		}

		$svg = self::generate(
			array(
				'name'     => $name,
				'category' => $category,
				'lat'      => $lat,
				'lng'      => $lng,
			)
		);

		// Store in memory cache.
		self::$cache[ $cache_key ] = $svg;

		return $svg;
	}

	/**
	 * Get placeholder as a data URI.
	 *
	 * @param int|WP_Post $business Business post ID or object.
	 * @return string Data URI string.
	 */
	public static function get_data_uri( $business ) {
		$business = get_post( $business );

		if ( ! $business ) {
			$svg = self::generate( array() );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			return 'data:image/svg+xml;base64,' . base64_encode( $svg );
		}

		// Check in-memory cache for data URI.
		$cache_key = 'uri_' . $business->ID;
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$svg = self::generate_for_business( $business );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data_uri = 'data:image/svg+xml;base64,' . base64_encode( $svg );

		// Store in memory cache.
		self::$cache[ $cache_key ] = $data_uri;

		return $data_uri;
	}

	/**
	 * Clear the in-memory cache.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$cache = array();
	}
}
