<?php
/**
 * MIME Type Validator
 *
 * Centralised real-MIME detection used by upload-handling controllers.
 * Use server-side detection (not the client-supplied $_FILES['type']) before
 * accepting user uploads.
 *
 * @package BusinessDirectory
 * @subpackage Security
 */

namespace BD\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MimeValidator {

	/**
	 * Detect a file's real MIME type from its contents.
	 *
	 * Tries fileinfo, then getimagesize, then mime_content_type.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @return string|false MIME type, or false if detection fails.
	 */
	public static function get_real_mime_type( $file_path ) {
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$mime_type = finfo_file( $finfo, $file_path );
				if ( $mime_type ) {
					return $mime_type;
				}
			}
		}

		$image_info = @getimagesize( $file_path );
		if ( $image_info && ! empty( $image_info['mime'] ) ) {
			return $image_info['mime'];
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $file_path );
			if ( $mime_type ) {
				return $mime_type;
			}
		}

		return false;
	}
}
