<?php
/**
 * Share Image Generator
 *
 * Generates dynamic share images for social media.
 *
 * @package BusinessDirectory
 */

namespace BD\Social;

class ImageGenerator {

	/**
	 * Image dimensions.
	 */
	const WIDTH  = 1200;
	const HEIGHT = 630;

	/**
	 * Brand colors - Love Tri Valley palette.
	 */
	const COLOR_DARK_NAVY   = array( 26, 58, 74 );    // #1a3a4a
	const COLOR_MEDIUM_NAVY = array( 30, 66, 88 );    // #1e4258
	const COLOR_STEEL_BLUE  = array( 122, 158, 184 ); // #7a9eb8
	const COLOR_LIGHT_BLUE  = array( 168, 196, 212 ); // #a8c4d4
	const COLOR_SLATE_GRAY  = array( 93, 122, 140 );  // #5d7a8c
	const COLOR_WHITE       = array( 255, 255, 255 );
	const COLOR_LIGHT_BG    = array( 240, 245, 248 ); // Light background
	const COLOR_TEXT        = array( 26, 26, 26 );    // #1a1a1a
	const COLOR_TEXT_LIGHT  = array( 93, 122, 140 );  // Same as slate gray

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'handle_image_request' ) );
	}

	/**
	 * Handle dynamic image generation request.
	 */
	public function handle_image_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['bd_share_image'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$business_id = isset( $_GET['business_id'] ) ? (int) $_GET['business_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$review_id = isset( $_GET['review_id'] ) ? (int) $_GET['review_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$badge_key = isset( $_GET['badge_key'] ) ? sanitize_text_field( wp_unslash( $_GET['badge_key'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;

		if ( $business_id ) {
			$this->generate_business_image( $business_id );
		} elseif ( $review_id ) {
			$this->generate_review_image( $review_id );
		} elseif ( $badge_key && $user_id ) {
			$this->generate_badge_image( $badge_key, $user_id );
		}

		exit;
	}

	/**
	 * Generate share image for a business.
	 *
	 * @param int $business_id Business ID.
	 */
	public function generate_business_image( $business_id ) {
		$business = get_post( $business_id );
		if ( ! $business ) {
			$this->output_fallback_image();
			return;
		}

		// Check cache first.
		$cached = $this->get_cached_image( 'business', $business_id );
		if ( $cached ) {
			$this->output_image( $cached );
			return;
		}

		// Create image.
		$image = $this->create_base_image();

		// Add business photo as background (if available).
		$thumbnail_id = get_post_thumbnail_id( $business_id );
		if ( $thumbnail_id ) {
			$image = $this->add_background_image( $image, $thumbnail_id );
		}

		// Add overlay gradient.
		$this->add_gradient_overlay( $image );

		// Add business info.
		$title = $business->post_title;
		$this->add_text( $image, $title, 60, 350, 36, self::COLOR_WHITE, true );

		// Rating.
		$rating = get_post_meta( $business_id, 'bd_rating_avg', true );
		if ( $rating ) {
			$stars = str_repeat( '★', round( (float) $rating ) );
			$this->add_text( $image, $stars . ' ' . number_format( (float) $rating, 1 ), 60, 420, 28, self::COLOR_STEEL_BLUE );
		}

		// Category and area.
		$meta_parts = array();
		$categories = wp_get_post_terms( $business_id, 'bd_category', array( 'fields' => 'names' ) );
		if ( ! empty( $categories ) ) {
			$meta_parts[] = $categories[0];
		}
		$areas = wp_get_post_terms( $business_id, 'bd_area', array( 'fields' => 'names' ) );
		if ( ! empty( $areas ) ) {
			$meta_parts[] = $areas[0];
		}
		if ( ! empty( $meta_parts ) ) {
			$this->add_text( $image, implode( ' · ', $meta_parts ), 60, 470, 20, self::COLOR_LIGHT_BLUE );
		}

		// Add site branding.
		$this->add_branding( $image );

		// Cache and output.
		$this->cache_image( $image, 'business', $business_id );
		$this->output_image( $image );
	}

	/**
	 * Generate share image for a review.
	 *
	 * @param int $review_id Review ID.
	 */
	public function generate_review_image( $review_id ) {
		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $reviews_table WHERE id = %d", $review_id ),
			ARRAY_A
		);

		if ( ! $review ) {
			$this->output_fallback_image();
			return;
		}

		$business = get_post( $review['business_id'] );
		if ( ! $business ) {
			$this->output_fallback_image();
			return;
		}

		// Create image.
		$image = $this->create_base_image();

		// Add dark navy background.
		$dark_navy = imagecolorallocate( $image, ...self::COLOR_DARK_NAVY );
		imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $dark_navy );

		// Add decorative elements.
		$steel_blue = imagecolorallocate( $image, ...self::COLOR_STEEL_BLUE );
		imagefilledrectangle( $image, 0, 0, self::WIDTH, 8, $steel_blue );
		imagefilledrectangle( $image, 0, self::HEIGHT - 120, self::WIDTH, self::HEIGHT, $steel_blue );

		// Add stars.
		$stars = str_repeat( '★', (int) $review['rating'] );
		$this->add_text( $image, $stars, 60, 100, 48, self::COLOR_STEEL_BLUE );

		// Add quote.
		$quote = '"' . wp_trim_words( $review['content'], 25 ) . '"';
		$this->add_wrapped_text( $image, $quote, 60, 180, 24, self::COLOR_WHITE, 1080 );

		// Add reviewer name.
		$reviewer = $review['author_name'] ?? __( 'Anonymous', 'business-directory' );
		$this->add_text( $image, '— ' . $reviewer, 60, 380, 20, self::COLOR_LIGHT_BLUE );

		// Add business name.
		$this->add_text( $image, $business->post_title, 60, self::HEIGHT - 80, 28, self::COLOR_DARK_NAVY );

		// Add branding.
		$this->add_branding( $image );

		$this->output_image( $image );
	}

	/**
	 * Generate share image for a badge.
	 *
	 * @param string $badge_key Badge key.
	 * @param int    $user_id User ID.
	 */
	public function generate_badge_image( $badge_key, $user_id ) {
		// Get badge data.
		if ( ! class_exists( 'BD\Gamification\BadgeSystem' ) ) {
			$this->output_fallback_image();
			return;
		}

		$badges = \BD\Gamification\BadgeSystem::BADGES;
		if ( ! isset( $badges[ $badge_key ] ) ) {
			$this->output_fallback_image();
			return;
		}

		$badge = $badges[ $badge_key ];
		$user  = get_userdata( $user_id );

		// Create image.
		$image = $this->create_base_image();

		// Add light background.
		$light_bg = imagecolorallocate( $image, ...self::COLOR_LIGHT_BG );
		imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $light_bg );

		// Add dark navy header.
		$dark_navy = imagecolorallocate( $image, ...self::COLOR_DARK_NAVY );
		imagefilledrectangle( $image, 0, 0, self::WIDTH, 200, $dark_navy );

		// Add steel blue accent.
		$steel_blue = imagecolorallocate( $image, ...self::COLOR_STEEL_BLUE );
		imagefilledrectangle( $image, 0, 195, self::WIDTH, 205, $steel_blue );

		// Add "Achievement Unlocked".
		$this->add_text( $image, __( 'ACHIEVEMENT UNLOCKED', 'business-directory' ), 60, 80, 18, self::COLOR_STEEL_BLUE );

		// Add badge name.
		$this->add_text( $image, $badge['name'], 60, 140, 32, self::COLOR_WHITE, true );

		// Add badge icon (as text for now).
		$this->add_text( $image, $badge['icon'] ?? '🏆', self::WIDTH / 2 - 50, 320, 100, self::COLOR_STEEL_BLUE );

		// Add description.
		$this->add_text( $image, $badge['description'] ?? '', 60, 480, 20, self::COLOR_TEXT );

		// Add user name.
		if ( $user ) {
			$earned_by = sprintf(
				// translators: %s is user name.
				__( 'Earned by %s', 'business-directory' ),
				$user->display_name
			);
			$this->add_text( $image, $earned_by, 60, 540, 18, self::COLOR_TEXT_LIGHT );
		}

		// Add branding.
		$this->add_branding( $image );

		$this->output_image( $image );
	}

	/**
	 * Create base image.
	 *
	 * @return resource GD image resource.
	 */
	private function create_base_image() {
		$image = imagecreatetruecolor( self::WIDTH, self::HEIGHT );

		// Enable alpha blending.
		imagealphablending( $image, true );
		imagesavealpha( $image, true );

		// Fill with white.
		$white = imagecolorallocate( $image, 255, 255, 255 );
		imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $white );

		return $image;
	}

	/**
	 * Add background image.
	 *
	 * @param resource $image GD image resource.
	 * @param int      $attachment_id Attachment ID.
	 * @return resource Modified image.
	 */
	private function add_background_image( $image, $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return $image;
		}

		$info = getimagesize( $file );
		if ( ! $info ) {
			return $image;
		}

		$source = null;
		switch ( $info['mime'] ) {
			case 'image/jpeg':
				$source = imagecreatefromjpeg( $file );
				break;
			case 'image/png':
				$source = imagecreatefrompng( $file );
				break;
			case 'image/webp':
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					$source = imagecreatefromwebp( $file );
				}
				break;
		}

		if ( ! $source ) {
			return $image;
		}

		// Resize and crop to fit.
		$src_w = imagesx( $source );
		$src_h = imagesy( $source );

		$scale = max( self::WIDTH / $src_w, self::HEIGHT / $src_h );
		$new_w = (int) ( $src_w * $scale );
		$new_h = (int) ( $src_h * $scale );
		$dst_x = (int) ( ( self::WIDTH - $new_w ) / 2 );
		$dst_y = (int) ( ( self::HEIGHT - $new_h ) / 2 );

		imagecopyresampled( $image, $source, $dst_x, $dst_y, 0, 0, $new_w, $new_h, $src_w, $src_h );

		return $image;
	}

	/**
	 * Add gradient overlay.
	 *
	 * @param resource $image GD image resource.
	 */
	private function add_gradient_overlay( $image ) {
		// Create gradient from bottom using dark navy.
		for ( $y = self::HEIGHT / 2; $y < self::HEIGHT; $y++ ) {
			$alpha = (int) ( ( $y - self::HEIGHT / 2 ) / ( self::HEIGHT / 2 ) * 110 );
			$color = imagecolorallocatealpha( $image, 26, 58, 74, 127 - $alpha );
			imageline( $image, 0, $y, self::WIDTH, $y, $color );
		}

		// Add solid bottom section.
		$dark_navy = imagecolorallocatealpha( $image, 26, 58, 74, 20 );
		imagefilledrectangle( $image, 0, self::HEIGHT - 200, self::WIDTH, self::HEIGHT, $dark_navy );
	}

	/**
	 * Add text to image.
	 *
	 * @param resource $image GD image resource.
	 * @param string   $text Text to add.
	 * @param int      $x X position.
	 * @param int      $y Y position.
	 * @param int      $size Font size.
	 * @param array    $color RGB color array.
	 * @param bool     $bold Use bold font.
	 */
	private function add_text( $image, $text, $x, $y, $size, $color, $bold = false ) {
		$color_allocated = imagecolorallocate( $image, $color[0], $color[1], $color[2] );

		// Try to use TrueType font.
		$font_path = $this->get_font_path( $bold );

		if ( $font_path && function_exists( 'imagettftext' ) ) {
			imagettftext( $image, $size, 0, $x, $y, $color_allocated, $font_path, $text );
		} else {
			// Fallback to built-in font.
			$font = $size > 20 ? 5 : 3;
			imagestring( $image, $font, $x, $y - 10, $text, $color_allocated );
		}
	}

	/**
	 * Add wrapped text to image.
	 *
	 * @param resource $image GD image resource.
	 * @param string   $text Text to add.
	 * @param int      $x X position.
	 * @param int      $y Y position.
	 * @param int      $size Font size.
	 * @param array    $color RGB color array.
	 * @param int      $max_width Maximum width.
	 */
	private function add_wrapped_text( $image, $text, $x, $y, $size, $color, $max_width ) {
		$font_path = $this->get_font_path( false );

		if ( ! $font_path || ! function_exists( 'imagettfbbox' ) ) {
			$this->add_text( $image, $text, $x, $y, $size, $color );
			return;
		}

		$words        = explode( ' ', $text );
		$lines        = array();
		$current_line = '';
		$line_height  = $size * 1.5;

		foreach ( $words as $word ) {
			$test_line = $current_line ? $current_line . ' ' . $word : $word;
			$bbox      = imagettfbbox( $size, 0, $font_path, $test_line );
			$width     = abs( $bbox[4] - $bbox[0] );

			if ( $width > $max_width && $current_line ) {
				$lines[]      = $current_line;
				$current_line = $word;
			} else {
				$current_line = $test_line;
			}
		}
		if ( $current_line ) {
			$lines[] = $current_line;
		}

		foreach ( $lines as $i => $line ) {
			$this->add_text( $image, $line, $x, $y + ( $i * $line_height ), $size, $color );
		}
	}

	/**
	 * Add site branding.
	 *
	 * @param resource $image GD image resource.
	 */
	private function add_branding( $image ) {
		$site_name = get_bloginfo( 'name' );
		$this->add_text( $image, '📍 ' . $site_name, self::WIDTH - 300, self::HEIGHT - 40, 18, self::COLOR_WHITE );
	}

	/**
	 * Get font path.
	 *
	 * @param bool $bold Use bold font.
	 * @return string|null Font path or null.
	 */
	private function get_font_path( $bold = false ) {
		// Check plugin fonts directory.
		$plugin_font = BD_PLUGIN_DIR . 'assets/fonts/' . ( $bold ? 'Inter-Bold.ttf' : 'Inter-Regular.ttf' );
		if ( file_exists( $plugin_font ) ) {
			return $plugin_font;
		}

		// Check system fonts.
		$system_fonts = array(
			'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
			'/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
			'/System/Library/Fonts/Helvetica.ttc',
			'C:/Windows/Fonts/arial.ttf',
		);

		foreach ( $system_fonts as $font ) {
			if ( file_exists( $font ) ) {
				return $font;
			}
		}

		return null;
	}

	/**
	 * Get cached image.
	 *
	 * @param string $type Type.
	 * @param int    $id ID.
	 * @return resource|null GD image or null.
	 */
	private function get_cached_image( $type, $id ) {
		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/bd-share-images/';
		$cache_file = $cache_dir . $type . '-' . $id . '.png';

		if ( ! file_exists( $cache_file ) ) {
			return null;
		}

		// Check if cache is still valid (24 hours).
		$modified = filemtime( $cache_file );
		if ( time() - $modified > DAY_IN_SECONDS ) {
			wp_delete_file( $cache_file );
			return null;
		}

		return imagecreatefrompng( $cache_file );
	}

	/**
	 * Cache image.
	 *
	 * @param resource $image GD image.
	 * @param string   $type Type.
	 * @param int      $id ID.
	 */
	private function cache_image( $image, $type, $id ) {
		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/bd-share-images/';

		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		$cache_file = $cache_dir . $type . '-' . $id . '.png';
		imagepng( $image, $cache_file );
	}

	/**
	 * Output image.
	 *
	 * @param resource $image GD image.
	 */
	private function output_image( $image ) {
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: public, max-age=86400' );
		imagepng( $image );
	}

	/**
	 * Output fallback image.
	 */
	private function output_fallback_image() {
		$image     = $this->create_base_image();
		$dark_navy = imagecolorallocate( $image, ...self::COLOR_DARK_NAVY );
		imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $dark_navy );

		$this->add_text( $image, get_bloginfo( 'name' ), self::WIDTH / 2 - 200, self::HEIGHT / 2, 40, self::COLOR_WHITE, true );
		$this->add_branding( $image );

		$this->output_image( $image );
	}
}
