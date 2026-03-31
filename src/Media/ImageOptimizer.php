<?php
/**
 * Image Optimizer
 *
 * Hooks into the WordPress attachment lifecycle to process every image
 * that enters the Media Library. Registers custom BD image sizes,
 * generates WebP siblings for each size, and strips EXIF from originals.
 *
 * Operates as a post-processing pipeline via wp_generate_attachment_metadata.
 * All entry points (admin gallery, review form, edit-listing, CSV import)
 * are handled identically without modifying any existing upload handler.
 *
 * @package BusinessDirectory
 * @subpackage Media
 * @since 0.2.0
 */

namespace BD\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ImageOptimizer {

	/**
	 * Custom image sizes for BD layouts.
	 *
	 * [ size_name => [ width, height, hard_crop ] ]
	 */
	private const SIZES = array(
		'bd-hero'           => array( 1600, 900, true ),
		'bd-card'           => array( 600, 400, true ),
		'bd-gallery-thumb'  => array( 400, 300, true ),
		'bd-lightbox'       => array( 1400, 1050, false ),
		'bd-review'         => array( 800, 600, false ),
		'bd-og'             => array( 1200, 630, true ),
	);

	/**
	 * WebP quality per size. Higher for full-screen viewing, lower for thumbnails.
	 */
	private const WEBP_QUALITY = array(
		'bd-hero'          => 82,
		'bd-card'          => 78,
		'bd-gallery-thumb' => 75,
		'bd-lightbox'      => 85,
		'bd-review'        => 78,
		'bd-og'            => 80,
		'full'             => 82,
	);

	/**
	 * JPEG re-encode quality for EXIF stripping (near-lossless).
	 */
	private const JPEG_RE_ENCODE_QUALITY = 92;

	/**
	 * Maximum dimension (either axis) to process. Larger images are
	 * handled by WordPress's big_image_size_threshold (-scaled).
	 */
	private const MAX_DIMENSION = 4096;

	/**
	 * Initialize hooks.
	 *
	 * @since 0.2.0
	 */
	public static function init() {
		// Register custom image sizes (must run before after_setup_theme completes).
		add_action( 'after_setup_theme', array( __CLASS__, 'register_sizes' ), 20 );

		// Main pipeline: process uploads after WordPress generates its sizes.
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_generate_metadata' ), 10, 2 );

		// Clean up WebP siblings when an attachment is deleted.
		add_action( 'delete_attachment', array( __CLASS__, 'cleanup_webp_files' ) );
	}

	/**
	 * Register BD-specific image sizes.
	 *
	 * @since 0.2.0
	 */
	public static function register_sizes() {
		foreach ( self::SIZES as $name => $dims ) {
			add_image_size( $name, $dims[0], $dims[1], $dims[2] );
		}
	}

	/**
	 * Main pipeline: strip EXIF + generate WebP for all sizes.
	 *
	 * Fires after WordPress generates its thumbnail sizes. Returns
	 * $metadata unchanged — WebP files are disk siblings, not WP sizes.
	 *
	 * @since 0.2.0
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment post ID.
	 * @return array Unmodified metadata.
	 */
	public static function on_generate_metadata( $metadata, $attachment_id ) {
		// Guard: valid metadata array with file path.
		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) ) {
			return $metadata;
		}

		// Guard: WebP support required.
		if ( ! function_exists( 'imagewebp' ) ) {
			return $metadata;
		}

		/**
		 * Filter whether to process this attachment.
		 *
		 * Return false to skip optimization for a specific upload.
		 * Useful for constraining processing to BD content only.
		 *
		 * @since 0.2.0
		 *
		 * @param bool $should_process Whether to process. Default true.
		 * @param int  $attachment_id  Attachment post ID.
		 * @param array $metadata      Attachment metadata.
		 */
		if ( ! apply_filters( 'bd_image_optimizer_should_process', true, $attachment_id, $metadata ) ) {
			return $metadata;
		}

		$upload_dir    = wp_get_upload_dir();
		$original_path = $upload_dir['basedir'] . '/' . $metadata['file'];

		// Guard: file exists on disk.
		if ( ! file_exists( $original_path ) ) {
			return $metadata;
		}

		// Guard: only process images (JPEG, PNG).
		$filetype = wp_check_filetype( $original_path );
		$mime     = $filetype['type'] ?? '';

		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return $metadata;
		}

		// Guard: skip very large originals (WordPress -scaled handles these).
		if ( ! empty( $metadata['width'] ) && (int) $metadata['width'] > self::MAX_DIMENSION ) {
			return $metadata;
		}
		if ( ! empty( $metadata['height'] ) && (int) $metadata['height'] > self::MAX_DIMENSION ) {
			return $metadata;
		}

		// Raise memory limit for GD processing.
		wp_raise_memory_limit( 'image' );

		// Step 1: Strip EXIF from original JPEG.
		if ( 'image/jpeg' === $mime ) {
			self::strip_exif( $original_path );
		}

		// Step 2: Generate WebP for each size + original.
		$webp_manifest = array();
		$base_dir      = dirname( $original_path );

		// Process the full/original image.
		$webp_path = self::generate_webp( $original_path, $mime, 'full' );
		if ( $webp_path ) {
			$webp_manifest['full'] = $webp_path;
		}

		// Process each generated sub-size.
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				$size_path = $base_dir . '/' . $size_data['file'];

				if ( ! file_exists( $size_path ) ) {
					continue;
				}

				$size_mime = $size_data['mime-type'] ?? $mime;
				$webp_path = self::generate_webp( $size_path, $size_mime, $size_name );

				if ( $webp_path ) {
					$webp_manifest[ $size_name ] = $webp_path;
				}
			}
		}

		// Store WebP manifest with paths relative to uploads dir.
		// Relative paths survive server migrations; resolve with wp_get_upload_dir() at read time.
		if ( ! empty( $webp_manifest ) ) {
			$relative_manifest = array();
			$basedir           = trailingslashit( $upload_dir['basedir'] );

			foreach ( $webp_manifest as $size => $abs_path ) {
				if ( 0 === strpos( $abs_path, $basedir ) ) {
					$relative_manifest[ $size ] = substr( $abs_path, strlen( $basedir ) );
				} else {
					$relative_manifest[ $size ] = $abs_path; // Fallback: store as-is.
				}
			}

			update_post_meta( $attachment_id, '_bd_webp_sizes', $relative_manifest );

			// Backward compatibility with CoverManager (_bd_webp_file expects absolute path).
			if ( isset( $webp_manifest['full'] ) ) {
				update_post_meta( $attachment_id, '_bd_webp_file', $webp_manifest['full'] );
			}
		}

		return $metadata;
	}

	/**
	 * Strip EXIF from a JPEG by re-encoding through GD.
	 *
	 * GD's imagecreatefromjpeg() loads pixel data only — EXIF (GPS,
	 * device info, timestamps) is discarded. Re-encoding at quality 92
	 * is visually lossless.
	 *
	 * @since 0.2.0
	 *
	 * @param string $path Absolute path to JPEG file.
	 */
	private static function strip_exif( $path ) {
		try {
			// Skip if EXIF is already absent (prevents quality loss on re-generation).
			// exif_read_data returns false or empty array when no EXIF exists.
			if ( function_exists( 'exif_read_data' ) ) {
				$exif = @exif_read_data( $path, 'GPS', true );
				if ( ! $exif || empty( $exif['GPS'] ) ) {
					// No GPS data — either already stripped or never had EXIF.
					// Check for any EXIF sections at all to be thorough.
					$any_exif = @exif_read_data( $path, 'ANY_TAG', false );
					// If fewer than 5 EXIF entries, likely already stripped (only basic IFD0 remains).
					if ( ! $any_exif || count( $any_exif ) < 5 ) {
						return;
					}
				}
			}

			$image = @imagecreatefromjpeg( $path );
			if ( ! $image ) {
				return;
			}

			// Write to a temp file first, then rename — atomic replacement
			// prevents corruption if the write fails (disk full, permissions).
			$temp_path = $path . '.bd-tmp';
			$success   = imagejpeg( $image, $temp_path, self::JPEG_RE_ENCODE_QUALITY );
			imagedestroy( $image );

			if ( $success && file_exists( $temp_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $temp_path, $path );
			} elseif ( file_exists( $temp_path ) ) {
				@unlink( $temp_path );
			}
		} catch ( \Throwable $e ) {
			// Free GD resource if it was allocated before the error.
			if ( isset( $image ) && $image ) {
				imagedestroy( $image );
			}
			// Clean up temp file if it was partially written.
			if ( isset( $temp_path ) && file_exists( $temp_path ) ) {
				@unlink( $temp_path );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BD ImageOptimizer: EXIF strip failed for ' . $path . ' — ' . $e->getMessage() );
		}
	}

	/**
	 * Generate a WebP sibling for a given image file.
	 *
	 * @since 0.2.0
	 *
	 * @param string $source_path Absolute path to source image.
	 * @param string $mime        MIME type (image/jpeg or image/png).
	 * @param string $size_name   Size name for quality lookup.
	 * @return string|null Absolute path to WebP file, or null on failure.
	 */
	private static function generate_webp( $source_path, $mime, $size_name ) {
		try {
			$image = null;

			if ( 'image/jpeg' === $mime ) {
				$image = @imagecreatefromjpeg( $source_path );
			} elseif ( 'image/png' === $mime ) {
				$image = @imagecreatefrompng( $source_path );

				if ( $image ) {
					// Preserve PNG transparency in WebP output.
					imagepalettetotruecolor( $image );
					imagealphablending( $image, true );
					imagesavealpha( $image, true );
				}
			}

			if ( ! $image ) {
				return null;
			}

			$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $source_path );

			// Guard: preg_replace could return null on PCRE error, or the
			// original path if no extension matched. Both mean we can't proceed.
			if ( ! $webp_path || $webp_path === $source_path ) {
				imagedestroy( $image );
				return null;
			}

			$quality = self::WEBP_QUALITY[ $size_name ] ?? 80;
			$success = imagewebp( $image, $webp_path, $quality );
			imagedestroy( $image );

			return $success ? $webp_path : null;
		} catch ( \Throwable $e ) {
			// Free GD resource if it was allocated before the error.
			if ( isset( $image ) && $image ) {
				imagedestroy( $image );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BD ImageOptimizer: WebP generation failed for ' . $source_path . ' [' . $size_name . '] — ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Clean up WebP sibling files when an attachment is deleted.
	 *
	 * Also cleans up legacy _bd_webp_file from CoverManager.
	 *
	 * @since 0.2.0
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public static function cleanup_webp_files( $attachment_id ) {
		$manifest   = get_post_meta( $attachment_id, '_bd_webp_sizes', true );
		$upload_dir = wp_get_upload_dir();
		$basedir    = trailingslashit( $upload_dir['basedir'] );

		if ( is_array( $manifest ) ) {
			foreach ( $manifest as $path ) {
				if ( ! is_string( $path ) ) {
					continue;
				}
				// Resolve relative paths (stored since 0.2.0) to absolute.
				$abs_path = ( 0 === strpos( $path, '/' ) ) ? $path : $basedir . $path;
				if ( file_exists( $abs_path ) ) {
					@unlink( $abs_path );
				}
			}
		}

		// Also check legacy single-file meta from CoverManager.
		$legacy_path = get_post_meta( $attachment_id, '_bd_webp_file', true );
		if ( $legacy_path && is_string( $legacy_path ) && file_exists( $legacy_path ) ) {
			// Only delete if not already in the manifest (avoid double-unlink).
			if ( ! is_array( $manifest ) || ! in_array( $legacy_path, $manifest, true ) ) {
				@unlink( $legacy_path );
			}
		}

		delete_post_meta( $attachment_id, '_bd_webp_sizes' );
		delete_post_meta( $attachment_id, '_bd_webp_file' );
	}
}
