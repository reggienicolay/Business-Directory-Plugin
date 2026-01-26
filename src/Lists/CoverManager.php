<?php
/**
 * Cover Manager
 *
 * Handles cover media uploads, processing, and storage for lists.
 * Supports image uploads with cropping and video covers (YouTube/Vimeo).
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

namespace BD\Lists;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoverManager {

	/**
	 * Allowed image MIME types
	 */
	const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/heic',
		'image/heif',
	);

	/**
	 * Maximum upload size in bytes (10MB)
	 */
	const MAX_UPLOAD_SIZE = 10485760;

	/**
	 * Minimum dimensions after crop
	 */
	const MIN_WIDTH  = 800;
	const MIN_HEIGHT = 400;

	/**
	 * Rate limits
	 */
	const RATE_LIMIT_IMAGE = 10;  // Max image uploads per hour
	const RATE_LIMIT_VIDEO = 20;  // Max video cover changes per hour

	/**
	 * Timeout for external requests (seconds)
	 */
	const EXTERNAL_REQUEST_TIMEOUT = 10;

	/**
	 * Cover image sizes
	 */
	const SIZES = array(
		'thumbnail' => array( 400, 225 ),   // List cards, browse
		'medium'    => array( 800, 450 ),   // Default display
		'large'     => array( 1200, 675 ),  // Single list page
		'social'    => array( 1200, 630 ),  // OG image
	);

	/**
	 * Allowed video thumbnail domains
	 */
	const ALLOWED_THUMBNAIL_DOMAINS = array(
		'img.youtube.com',
		'i.ytimg.com',
		'i.vimeocdn.com',
		'vumbnail.com',
	);

	/**
	 * Upload and set cover image for a list
	 *
	 * @param int   $list_id       List ID.
	 * @param int   $user_id       User ID (for permission check).
	 * @param array $file          $_FILES array element.
	 * @param array $crop_data     Crop data from editor.
	 * @param array $original_file Optional original file for re-cropping support.
	 * @return array|WP_Error Result with attachment IDs and URLs, or error.
	 */
	public static function upload_cover( $list_id, $user_id, $file, $crop_data = array(), $original_file = null ) {
		// Verify list ownership
		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error( 'not_found', 'List not found', array( 'status' => 404 ) );
		}

		if ( (int) $list['user_id'] !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', 'You do not have permission to edit this list', array( 'status' => 403 ) );
		}

		// Rate limiting with atomic increment
		$rate_check = self::check_rate_limit( $user_id, 'image' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Validate the cropped file
		$validation = self::validate_upload( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Clean up old cover attachments to prevent orphans
		$old_cover_id    = ! empty( $list['cover_image_id'] ) ? (int) $list['cover_image_id'] : 0;
		$old_original    = ! empty( $list['cover_original_id'] ) ? (int) $list['cover_original_id'] : 0;
		$old_video_thumb = ! empty( $list['cover_video_thumb_id'] ) ? (int) $list['cover_video_thumb_id'] : 0;

		// Handle the upload
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Upload cropped image
		$cropped_result = self::handle_upload( $file, $list_id, 'cropped' );
		if ( is_wp_error( $cropped_result ) ) {
			return $cropped_result;
		}

		$cropped_attachment_id = $cropped_result['attachment_id'];

		// Upload original if provided (for re-cropping later)
		$original_attachment_id = null;
		if ( $original_file && ! empty( $original_file['tmp_name'] ) ) {
			$original_result = self::handle_upload( $original_file, $list_id, 'original' );
			if ( ! is_wp_error( $original_result ) ) {
				$original_attachment_id = $original_result['attachment_id'];
				// Mark as private (not shown in media library)
				update_post_meta( $original_attachment_id, '_bd_cover_original', true );
			}
		}

		// Generate WebP version and additional sizes
		$processed = self::process_cover_image( $cropped_attachment_id );
		if ( is_wp_error( $processed ) ) {
			// Cleanup on failure
			wp_delete_attachment( $cropped_attachment_id, true );
			if ( $original_attachment_id ) {
				wp_delete_attachment( $original_attachment_id, true );
			}
			return $processed;
		}

		// Prepare crop data for storage
		$crop_json = self::prepare_crop_data( $crop_data );

		// Update list record
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$update_data = array(
			'cover_image_id'       => $cropped_attachment_id,
			'cover_type'           => 'image',
			'cover_crop_data'      => $crop_json,
			'cover_video_id'       => null,
			'cover_video_thumb_id' => null,
			'updated_at'           => current_time( 'mysql' ),
		);

		if ( $original_attachment_id ) {
			$update_data['cover_original_id'] = $original_attachment_id;
		}

		$wpdb->update(
			$table,
			$update_data,
			array( 'id' => $list_id ),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		// Delete old cover attachments AFTER successful update to prevent orphans
		if ( $old_cover_id && $old_cover_id !== $cropped_attachment_id ) {
			wp_delete_attachment( $old_cover_id, true );
		}
		if ( $old_original && $old_original !== $original_attachment_id ) {
			wp_delete_attachment( $old_original, true );
		}
		if ( $old_video_thumb ) {
			wp_delete_attachment( $old_video_thumb, true );
		}

		// Invalidate caches
		ListManager::invalidate_list_cache( $list_id );
		self::invalidate_user_cover_cache( $user_id );

		// Award gamification points (once per list)
		self::maybe_award_cover_points( $list_id, $user_id, 'image' );

		return array(
			'success'       => true,
			'attachment_id' => $cropped_attachment_id,
			'original_id'   => $original_attachment_id,
			'cover_url'     => wp_get_attachment_image_url( $cropped_attachment_id, 'medium' ),
			'cover_urls'    => array(
				'thumbnail' => wp_get_attachment_image_url( $cropped_attachment_id, 'thumbnail' ),
				'medium'    => wp_get_attachment_image_url( $cropped_attachment_id, 'medium' ),
				'large'     => wp_get_attachment_image_url( $cropped_attachment_id, 'large' ),
			),
		);
	}

	/**
	 * Re-crop existing cover from original
	 *
	 * @param int   $list_id   List ID.
	 * @param int   $user_id   User ID.
	 * @param array $crop_data New crop data.
	 * @param array $file      New cropped image file.
	 * @return array|WP_Error
	 */
	public static function recrop_cover( $list_id, $user_id, $crop_data, $file ) {
		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error( 'not_found', 'List not found', array( 'status' => 404 ) );
		}

		if ( (int) $list['user_id'] !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', 'Permission denied', array( 'status' => 403 ) );
		}

		// Delete old cropped image (keep original)
		if ( ! empty( $list['cover_image_id'] ) ) {
			wp_delete_attachment( $list['cover_image_id'], true );
		}

		// Upload new cropped version
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$cropped_result = self::handle_upload( $file, $list_id, 'cropped' );
		if ( is_wp_error( $cropped_result ) ) {
			return $cropped_result;
		}

		$cropped_attachment_id = $cropped_result['attachment_id'];

		// Process for WebP
		$processed = self::process_cover_image( $cropped_attachment_id );
		if ( is_wp_error( $processed ) ) {
			wp_delete_attachment( $cropped_attachment_id, true );
			return $processed;
		}

		// Update crop data
		$crop_json = self::prepare_crop_data( $crop_data );

		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$wpdb->update(
			$table,
			array(
				'cover_image_id'  => $cropped_attachment_id,
				'cover_crop_data' => $crop_json,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $list_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		ListManager::invalidate_list_cache( $list_id );

		return array(
			'success'       => true,
			'attachment_id' => $cropped_attachment_id,
			'cover_url'     => wp_get_attachment_image_url( $cropped_attachment_id, 'medium' ),
		);
	}

	/**
	 * Set video cover for a list
	 *
	 * @param int    $list_id   List ID.
	 * @param int    $user_id   User ID.
	 * @param string $video_url Video URL or ID.
	 * @return array|WP_Error
	 */
	public static function set_video_cover( $list_id, $user_id, $video_url ) {
		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error( 'not_found', 'List not found', array( 'status' => 404 ) );
		}

		if ( (int) $list['user_id'] !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', 'Permission denied', array( 'status' => 403 ) );
		}

		// Rate limiting for video covers
		$rate_check = self::check_rate_limit( $user_id, 'video' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Parse video URL
		$video_data = self::parse_video_url( $video_url );
		if ( is_wp_error( $video_data ) ) {
			return $video_data;
		}

		// Store old attachment IDs for cleanup
		$old_cover_id    = ! empty( $list['cover_image_id'] ) ? (int) $list['cover_image_id'] : 0;
		$old_original    = ! empty( $list['cover_original_id'] ) ? (int) $list['cover_original_id'] : 0;
		$old_video_thumb = ! empty( $list['cover_video_thumb_id'] ) ? (int) $list['cover_video_thumb_id'] : 0;

		// Fetch and store thumbnail locally
		$thumbnail_id = self::fetch_video_thumbnail( $video_data['platform'], $video_data['id'], $list_id );

		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$wpdb->update(
			$table,
			array(
				'cover_type'           => $video_data['platform'],
				'cover_video_id'       => $video_data['id'],
				'cover_video_thumb_id' => $thumbnail_id ?: null,
				'cover_image_id'       => null,
				'cover_original_id'    => null,
				'cover_crop_data'      => null,
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $list_id ),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Delete old cover attachments AFTER successful update
		if ( $old_cover_id ) {
			wp_delete_attachment( $old_cover_id, true );
		}
		if ( $old_original ) {
			wp_delete_attachment( $old_original, true );
		}
		if ( $old_video_thumb && $old_video_thumb !== $thumbnail_id ) {
			wp_delete_attachment( $old_video_thumb, true );
		}

		ListManager::invalidate_list_cache( $list_id );
		self::invalidate_user_cover_cache( $user_id );

		// Award gamification points
		self::maybe_award_cover_points( $list_id, $user_id, 'video' );

		return array(
			'success'       => true,
			'platform'      => $video_data['platform'],
			'video_id'      => $video_data['id'],
			'thumbnail_url' => $thumbnail_id
				? wp_get_attachment_image_url( $thumbnail_id, 'medium' )
				: self::get_video_thumbnail_url( $video_data['platform'], $video_data['id'] ),
			'embed_url'     => self::get_video_embed_url( $video_data['platform'], $video_data['id'] ),
		);
	}

	/**
	 * Remove cover from list (revert to auto)
	 *
	 * @param int $list_id List ID.
	 * @param int $user_id User ID.
	 * @return array|WP_Error
	 */
	public static function remove_cover( $list_id, $user_id ) {
		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error( 'not_found', 'List not found', array( 'status' => 404 ) );
		}

		if ( (int) $list['user_id'] !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', 'Permission denied', array( 'status' => 403 ) );
		}

		// Delete attachments
		if ( ! empty( $list['cover_image_id'] ) ) {
			wp_delete_attachment( $list['cover_image_id'], true );
		}
		if ( ! empty( $list['cover_original_id'] ) ) {
			wp_delete_attachment( $list['cover_original_id'], true );
		}
		if ( ! empty( $list['cover_video_thumb_id'] ) ) {
			wp_delete_attachment( $list['cover_video_thumb_id'], true );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$wpdb->update(
			$table,
			array(
				'cover_image_id'       => null,
				'cover_original_id'    => null,
				'cover_crop_data'      => null,
				'cover_type'           => 'auto',
				'cover_video_id'       => null,
				'cover_video_thumb_id' => null,
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $list_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		ListManager::invalidate_list_cache( $list_id );
		self::invalidate_user_cover_cache( $user_id );

		return array( 'success' => true );
	}

	/**
	 * Upload custom thumbnail for video cover
	 *
	 * Allows users to override the auto-fetched YouTube/Vimeo thumbnail
	 * with their own cropped image for better quality and positioning control.
	 *
	 * @param int   $list_id   List ID.
	 * @param int   $user_id   User ID.
	 * @param array $file      Uploaded cropped image file.
	 * @param array $crop_data Crop coordinates.
	 * @return array|WP_Error
	 */
	public static function upload_video_thumbnail( $list_id, $user_id, $file, $crop_data = array() ) {
		$list = ListManager::get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error( 'not_found', 'List not found', array( 'status' => 404 ) );
		}

		if ( (int) $list['user_id'] !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', 'Permission denied', array( 'status' => 403 ) );
		}

		// Must have an existing video cover to add custom thumbnail
		if ( ! in_array( $list['cover_type'], array( 'youtube', 'vimeo' ), true ) ) {
			return new \WP_Error( 'invalid_state', 'List must have a video cover first', array( 'status' => 400 ) );
		}

		// Rate limiting
		$rate_check = self::check_rate_limit( $user_id, 'image' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Validate the upload
		$validation = self::validate_upload( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Store old thumbnail for cleanup
		$old_thumb_id = ! empty( $list['cover_video_thumb_id'] ) ? (int) $list['cover_video_thumb_id'] : 0;

		// Handle the upload
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload_result = self::handle_upload( $file, $list_id, 'video-thumb' );
		if ( is_wp_error( $upload_result ) ) {
			return $upload_result;
		}

		$thumb_attachment_id = $upload_result['attachment_id'];

		// Mark as custom thumbnail
		update_post_meta( $thumb_attachment_id, '_bd_custom_video_thumb', true );
		update_post_meta( $thumb_attachment_id, '_bd_list_cover', $list_id );

		// Process for WebP and sizes
		self::process_cover_image( $thumb_attachment_id );

		// Prepare crop data
		$crop_json = self::prepare_crop_data( $crop_data );

		// Update list record
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$wpdb->update(
			$table,
			array(
				'cover_video_thumb_id' => $thumb_attachment_id,
				'cover_crop_data'      => $crop_json,
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $list_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		// Delete old thumbnail after successful update
		if ( $old_thumb_id && $old_thumb_id !== $thumb_attachment_id ) {
			wp_delete_attachment( $old_thumb_id, true );
		}

		ListManager::invalidate_list_cache( $list_id );

		return array(
			'success'       => true,
			'thumbnail_id'  => $thumb_attachment_id,
			'thumbnail_url' => wp_get_attachment_image_url( $thumb_attachment_id, 'medium' ),
			'is_custom'     => true,
		);
	}

	/**
	 * Check rate limit for user actions
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    'image' or 'video'.
	 * @return true|WP_Error
	 */
	private static function check_rate_limit( $user_id, $type = 'image' ) {
		$limit    = 'video' === $type ? self::RATE_LIMIT_VIDEO : self::RATE_LIMIT_IMAGE;
		$rate_key = 'bd_cover_' . $type . '_rate_' . $user_id;

		// Try atomic increment with object cache first
		if ( wp_using_ext_object_cache() ) {
			$group = 'bd_rate_limits';
			$count = wp_cache_get( $rate_key, $group );

			if ( false === $count ) {
				wp_cache_set( $rate_key, 1, $group, HOUR_IN_SECONDS );
				return true;
			}

			if ( $count >= $limit ) {
				return new \WP_Error(
					'rate_limited',
					'Too many requests. Please try again later.',
					array( 'status' => 429 )
				);
			}

			wp_cache_incr( $rate_key, 1, $group );
			return true;
		}

		// Fallback to transients (less reliable under high concurrency)
		$count = (int) get_transient( $rate_key );
		if ( $count >= $limit ) {
			return new \WP_Error(
				'rate_limited',
				'Too many requests. Please try again later.',
				array( 'status' => 429 )
			);
		}
		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Validate upload file
	 *
	 * @param array $file $_FILES element.
	 * @return true|WP_Error
	 */
	private static function validate_upload( $file ) {
		if ( empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
		}

		// Check file size
		if ( $file['size'] > self::MAX_UPLOAD_SIZE ) {
			return new \WP_Error( 'file_too_large', 'File exceeds maximum size of 10MB', array( 'status' => 400 ) );
		}

		// Raise memory limit for large files
		if ( $file['size'] > 5 * 1024 * 1024 ) {
			wp_raise_memory_limit( 'image' );
		}

		// Validate MIME type server-side using finfo
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
			return new \WP_Error( 'invalid_type', 'Please upload a JPEG, PNG, or WebP image', array( 'status' => 400 ) );
		}

		// Check dimensions
		$image_info = getimagesize( $file['tmp_name'] );
		if ( ! $image_info ) {
			return new \WP_Error( 'invalid_image', 'Could not read image dimensions', array( 'status' => 400 ) );
		}

		if ( $image_info[0] < self::MIN_WIDTH || $image_info[1] < self::MIN_HEIGHT ) {
			return new \WP_Error(
				'too_small',
				sprintf( 'Image must be at least %dx%d pixels', self::MIN_WIDTH, self::MIN_HEIGHT ),
				array( 'status' => 400 )
			);
		}

		// Security: scan for embedded code (check first and last 1KB)
		$handle = fopen( $file['tmp_name'], 'rb' );
		if ( $handle ) {
			$first_chunk = fread( $handle, 1024 );
			fseek( $handle, -1024, SEEK_END );
			$last_chunk = fread( $handle, 1024 );
			fclose( $handle );

			$contents = $first_chunk . $last_chunk;
			if ( preg_match( '/<\?php|<script|javascript:|on\w+\s*=/i', $contents ) ) {
				return new \WP_Error( 'security_error', 'File contains invalid content', array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * Handle file upload and create attachment
	 *
	 * @param array  $file    $_FILES element.
	 * @param int    $list_id List ID.
	 * @param string $type    'cropped' or 'original'.
	 * @return array|WP_Error
	 */
	private static function handle_upload( $file, $list_id, $type = 'cropped' ) {
		// Set up custom upload directory
		add_filter( 'upload_dir', array( __CLASS__, 'custom_upload_dir' ) );

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		remove_filter( 'upload_dir', array( __CLASS__, 'custom_upload_dir' ) );

		if ( isset( $upload['error'] ) ) {
			error_log( 'BD Cover: Upload failed - ' . $upload['error'] );
			return new \WP_Error( 'upload_failed', $upload['error'], array( 'status' => 500 ) );
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $upload['file'] );
			error_log( 'BD Cover: Attachment creation failed - ' . $attachment_id->get_error_message() );
			return $attachment_id;
		}

		// Generate attachment metadata
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Store reference to list
		update_post_meta( $attachment_id, '_bd_list_cover', $list_id );
		update_post_meta( $attachment_id, '_bd_cover_type', $type );

		return array(
			'attachment_id' => $attachment_id,
			'url'           => $upload['url'],
			'file'          => $upload['file'],
		);
	}

	/**
	 * Custom upload directory for covers
	 *
	 * @param array $uploads Upload directory info.
	 * @return array Modified upload directory.
	 */
	public static function custom_upload_dir( $uploads ) {
		$subdir = '/bd-covers' . $uploads['subdir'];

		return array(
			'path'   => $uploads['basedir'] . $subdir,
			'url'    => $uploads['baseurl'] . $subdir,
			'subdir' => $subdir,
		) + $uploads;
	}

	/**
	 * Process cover image - convert to WebP if possible
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return true|WP_Error
	 */
	private static function process_cover_image( $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			error_log( 'BD Cover: Processing failed - file not found for attachment ' . $attachment_id );
			return new \WP_Error( 'file_not_found', 'Attachment file not found' );
		}

		// Check if we can create WebP
		if ( function_exists( 'imagewebp' ) ) {
			$image = null;
			$type  = wp_check_filetype( $file )['type'];

			switch ( $type ) {
				case 'image/jpeg':
					$image = imagecreatefromjpeg( $file );
					break;
				case 'image/png':
					$image = imagecreatefrompng( $file );
					break;
			}

			if ( $image ) {
				$webp_file = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file );

				// Preserve transparency for PNG
				if ( 'image/png' === $type ) {
					imagepalettetotruecolor( $image );
					imagealphablending( $image, true );
					imagesavealpha( $image, true );
				}

				// Save WebP at 80% quality
				if ( imagewebp( $image, $webp_file, 80 ) ) {
					update_post_meta( $attachment_id, '_bd_webp_file', $webp_file );
				}
				imagedestroy( $image );
			}
		}

		return true;
	}

	/**
	 * Prepare crop data for storage
	 *
	 * @param array $crop_data Raw crop data.
	 * @return string JSON string.
	 */
	private static function prepare_crop_data( $crop_data ) {
		// Validate and clamp values to safe ranges
		$normalized = array(
			'version' => 1,
			'source'  => array(
				'width'  => absint( $crop_data['source_width'] ?? 0 ),
				'height' => absint( $crop_data['source_height'] ?? 0 ),
			),
			'crop'    => array(
				// Clamp percentages to 0-1 range
				'x'      => max( 0, min( 1, floatval( $crop_data['x'] ?? 0 ) ) ),
				'y'      => max( 0, min( 1, floatval( $crop_data['y'] ?? 0 ) ) ),
				'width'  => max( 0.01, min( 1, floatval( $crop_data['width'] ?? 1 ) ) ),
				'height' => max( 0.01, min( 1, floatval( $crop_data['height'] ?? 1 ) ) ),
			),
			// Clamp zoom to reasonable range
			'zoom'     => max( 0.1, min( 10, floatval( $crop_data['zoom'] ?? 1 ) ) ),
			// Normalize rotation to 0-360
			'rotation' => fmod( floatval( $crop_data['rotation'] ?? 0 ), 360 ),
			'flip'     => array(
				'horizontal' => ! empty( $crop_data['flip_h'] ),
				'vertical'   => ! empty( $crop_data['flip_v'] ),
			),
		);

		return wp_json_encode( $normalized );
	}

	/**
	 * Parse video URL to extract platform and ID
	 *
	 * @param string $url Video URL or ID.
	 * @return array|WP_Error Array with 'platform' and 'id', or error.
	 */
	public static function parse_video_url( $url ) {
		$url = trim( $url );

		// YouTube patterns
		$youtube_patterns = array(
			'/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
			'/^([a-zA-Z0-9_-]{11})$/', // Bare ID
		);

		foreach ( $youtube_patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				return array(
					'platform' => 'youtube',
					'id'       => $matches[1],
				);
			}
		}

		// Vimeo patterns
		$vimeo_patterns = array(
			'/vimeo\.com\/(\d+)/',
			'/player\.vimeo\.com\/video\/(\d+)/',
			'/^(\d{6,})$/', // Bare ID (6+ digits)
		);

		foreach ( $vimeo_patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				return array(
					'platform' => 'vimeo',
					'id'       => $matches[1],
				);
			}
		}

		return new \WP_Error( 'invalid_video_url', 'Please enter a valid YouTube or Vimeo URL', array( 'status' => 400 ) );
	}

	/**
	 * Fetch and store video thumbnail locally
	 *
	 * @param string $platform 'youtube' or 'vimeo'.
	 * @param string $video_id Video ID.
	 * @param int    $list_id  List ID.
	 * @return int|false Attachment ID or false on failure.
	 */
	private static function fetch_video_thumbnail( $platform, $video_id, $list_id ) {
		$thumbnail_url = self::get_video_thumbnail_url( $platform, $video_id );

		if ( ! $thumbnail_url ) {
			error_log( 'BD Cover: Could not get thumbnail URL for ' . $platform . ' video ' . $video_id );
			return false;
		}

		// Validate URL domain for security (prevent SSRF)
		$parsed_url = wp_parse_url( $thumbnail_url );
		$host       = $parsed_url['host'] ?? '';

		if ( ! in_array( $host, self::ALLOWED_THUMBNAIL_DOMAINS, true ) ) {
			error_log( 'BD Cover: Thumbnail URL domain not allowed: ' . $host );
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Download to temp file
		$temp_file = download_url( $thumbnail_url, self::EXTERNAL_REQUEST_TIMEOUT );

		if ( is_wp_error( $temp_file ) ) {
			error_log( 'BD Cover: Failed to download video thumbnail - ' . $temp_file->get_error_message() );
			return false;
		}

		$file_array = array(
			'name'     => $platform . '-' . $video_id . '.jpg',
			'tmp_name' => $temp_file,
		);

		// Sideload into media library
		add_filter( 'upload_dir', array( __CLASS__, 'custom_upload_dir' ) );

		$attachment_id = media_handle_sideload( $file_array, 0 );

		remove_filter( 'upload_dir', array( __CLASS__, 'custom_upload_dir' ) );

		// Clean up temp file if sideload failed
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $temp_file );
			error_log( 'BD Cover: Failed to sideload video thumbnail - ' . $attachment_id->get_error_message() );
			return false;
		}

		// Store metadata
		update_post_meta( $attachment_id, '_bd_list_cover', $list_id );
		update_post_meta( $attachment_id, '_bd_video_thumbnail', true );

		return $attachment_id;
	}

	/**
	 * Get video thumbnail URL from platform
	 *
	 * @param string $platform 'youtube' or 'vimeo'.
	 * @param string $video_id Video ID.
	 * @return string|false Thumbnail URL or false.
	 */
	public static function get_video_thumbnail_url( $platform, $video_id ) {
		// Check cache first
		$cache_key = 'bd_video_thumb_' . $platform . '_' . $video_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached ?: false; // Empty string means "no thumbnail found"
		}

		$url = false;

		if ( 'youtube' === $platform ) {
			// Validate YouTube video ID format
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{11}$/', $video_id ) ) {
				return false;
			}

			// Try maxresdefault first, fall back to sddefault, then hqdefault
			$urls = array(
				'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg',
				'https://img.youtube.com/vi/' . $video_id . '/sddefault.jpg',
				'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg',
			);

			foreach ( $urls as $try_url ) {
				$response = wp_remote_head( $try_url, array( 'timeout' => 5 ) );
				if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
					$url = $try_url;
					break;
				}
			}
		} elseif ( 'vimeo' === $platform ) {
			// Validate Vimeo video ID format
			if ( ! preg_match( '/^\d+$/', $video_id ) ) {
				return false;
			}

			// Use Vimeo oEmbed API
			$api_url  = 'https://vimeo.com/api/oembed.json?url=https://vimeo.com/' . $video_id;
			$response = wp_remote_get( $api_url, array( 'timeout' => self::EXTERNAL_REQUEST_TIMEOUT ) );

			if ( ! is_wp_error( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $data['thumbnail_url'] ) ) {
					// Get larger thumbnail and validate domain
					$thumb_url   = preg_replace( '/_\d+x\d+/', '_640', $data['thumbnail_url'] );
					$parsed      = wp_parse_url( $thumb_url );
					$thumb_host  = $parsed['host'] ?? '';

					if ( in_array( $thumb_host, self::ALLOWED_THUMBNAIL_DOMAINS, true ) ) {
						$url = $thumb_url;
					}
				}
			}
		}

		// Cache for 24 hours (even failures, as empty string)
		set_transient( $cache_key, $url ?: '', DAY_IN_SECONDS );

		return $url;
	}

	/**
	 * Get privacy-enhanced video embed URL
	 *
	 * @param string $platform 'youtube' or 'vimeo'.
	 * @param string $video_id Video ID.
	 * @return string Embed URL or empty string if invalid.
	 */
	public static function get_video_embed_url( $platform, $video_id ) {
		// Defense in depth: validate video ID format before building URL
		if ( 'youtube' === $platform ) {
			// YouTube IDs are exactly 11 chars: alphanumeric, dash, underscore
			if ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $video_id ) ) {
				return 'https://www.youtube-nocookie.com/embed/' . $video_id . '?rel=0&modestbranding=1';
			}
		} elseif ( 'vimeo' === $platform ) {
			// Vimeo IDs are numeric only
			if ( preg_match( '/^\d+$/', $video_id ) ) {
				return 'https://player.vimeo.com/video/' . $video_id . '?dnt=1&title=0&byline=0&portrait=0';
			}
		}

		return '';
	}

	/**
	 * Award gamification points for adding cover (once per list)
	 *
	 * @param int    $list_id    List ID.
	 * @param int    $user_id    User ID.
	 * @param string $cover_type 'image' or 'video'.
	 */
	private static function maybe_award_cover_points( $list_id, $user_id, $cover_type ) {
		if ( ! class_exists( 'BD\Gamification\ActivityTracker' ) ) {
			return;
		}

		$meta_key = '_bd_cover_points_awarded_' . $cover_type;
		$awarded  = get_user_meta( $user_id, $meta_key . '_' . $list_id, true );

		if ( $awarded ) {
			return; // Already awarded for this list
		}

		$activity = 'image' === $cover_type ? 'list_cover_added' : 'list_video_cover_added';
		\BD\Gamification\ActivityTracker::track( $user_id, $activity, $list_id );

		update_user_meta( $user_id, $meta_key . '_' . $list_id, time() );

		// Check for Visual Storyteller badge
		self::check_visual_storyteller_badge( $user_id );
	}

	/**
	 * Check and award Visual Storyteller badge
	 *
	 * @param int $user_id User ID.
	 */
	private static function check_visual_storyteller_badge( $user_id ) {
		if ( ! class_exists( 'BD\Gamification\BadgeSystem' ) ) {
			return;
		}

		// Use cached count
		$count = self::get_user_public_covers_count( $user_id );

		if ( $count >= 5 ) {
			\BD\Gamification\BadgeSystem::award_badge( $user_id, 'visual_storyteller' );
		}
	}

	/**
	 * Get cached count of user's public lists with covers
	 *
	 * @param int $user_id User ID.
	 * @return int Count.
	 */
	private static function get_user_public_covers_count( $user_id ) {
		$cache_key = 'bd_user_covers_count_' . $user_id;
		$count     = get_transient( $cache_key );

		if ( false === $count ) {
			global $wpdb;
			$table = $wpdb->prefix . 'bd_lists';

			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table 
					 WHERE user_id = %d 
					 AND visibility = 'public' 
					 AND cover_type IN ('image', 'youtube', 'vimeo')",
					$user_id
				)
			);

			set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Invalidate user cover count cache
	 *
	 * @param int $user_id User ID.
	 */
	private static function invalidate_user_cover_cache( $user_id ) {
		delete_transient( 'bd_user_covers_count_' . $user_id );
	}

	/**
	 * Get cover data for a list (for API responses)
	 *
	 * @param array $list List data.
	 * @return array Cover data.
	 */
	public static function get_cover_data( $list ) {
		$cover_type = $list['cover_type'] ?? 'auto';

		$data = array(
			'type'      => $cover_type,
			'has_cover' => 'auto' !== $cover_type,
		);

		if ( 'image' === $cover_type && ! empty( $list['cover_image_id'] ) ) {
			$data['image'] = array(
				'id'        => (int) $list['cover_image_id'],
				'thumbnail' => wp_get_attachment_image_url( $list['cover_image_id'], 'thumbnail' ),
				'medium'    => wp_get_attachment_image_url( $list['cover_image_id'], 'medium' ),
				'large'     => wp_get_attachment_image_url( $list['cover_image_id'], 'large' ),
			);
			$data['can_recrop'] = ! empty( $list['cover_original_id'] );
		} elseif ( in_array( $cover_type, array( 'youtube', 'vimeo' ), true ) ) {
			$data['video'] = array(
				'platform'      => $cover_type,
				'id'            => $list['cover_video_id'],
				'thumbnail_url' => ! empty( $list['cover_video_thumb_id'] )
					? wp_get_attachment_image_url( $list['cover_video_thumb_id'], 'medium' )
					: self::get_video_thumbnail_url( $cover_type, $list['cover_video_id'] ),
				'embed_url'     => self::get_video_embed_url( $cover_type, $list['cover_video_id'] ),
			);
		}

		return $data;
	}
}
