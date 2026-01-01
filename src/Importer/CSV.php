<?php
/**
 * Enhanced CSV Importer for bulk business import
 *
 * Features:
 * - Duplicate detection (by title, external_id, or both)
 * - Import modes: skip, update, create
 * - Tags support
 * - Social media fields
 * - Featured image from URL
 * - Dry-run preview
 *
 * @package BusinessDirectory
 */

namespace BD\Importer;

class CSV {

	/**
	 * Import modes
	 */
	const MODE_SKIP   = 'skip';   // Skip if duplicate found
	const MODE_UPDATE = 'update'; // Update existing record
	const MODE_CREATE = 'create'; // Always create new (original behavior)

	/**
	 * Duplicate detection methods
	 */
	const MATCH_TITLE       = 'title';
	const MATCH_EXTERNAL_ID = 'external_id';
	const MATCH_BOTH        = 'both';

	/**
	 * Geocoding cache to avoid duplicate lookups within same import
	 *
	 * @var array
	 */
	private static $geocode_cache = array();

	/**
	 * Last geocode request time for rate limiting
	 *
	 * @var float
	 */
	private static $last_geocode_time = 0;

	/**
	 * Import businesses from CSV file
	 *
	 * @param string $file_path Path to CSV file.
	 * @param array  $options   Import options.
	 * @return array|\WP_Error Results array or error.
	 */
	public static function import( $file_path, $options = array() ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', 'CSV file not found' );
		}

		$defaults = array(
			'create_terms'    => true,
			'dry_run'         => false,
			'import_mode'     => self::MODE_SKIP,    // skip, update, create
			'match_by'        => self::MATCH_TITLE,  // title, external_id, both
			'download_images' => false,
			'geocode'         => false,              // Auto-fill lat/lng from address
		);

		$options = wp_parse_args( $options, $defaults );

		$results = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => array(),
			'preview'  => array(), // For dry-run mode
		);

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', 'Could not open CSV file' );
		}

		// Read header row
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return new \WP_Error( 'invalid_csv', 'CSV has no header row' );
		}

		// Normalize headers (lowercase, trim)
		$headers = array_map(
			function ( $h ) {
				return strtolower( trim( $h ) );
			},
			$headers
		);

		// Process rows
		$row_number = 1;
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			++$row_number;

			// Skip empty rows
			if ( empty( array_filter( $data ) ) ) {
				continue;
			}

			// Handle row count mismatch
			if ( count( $data ) !== count( $headers ) ) {
				// Pad or trim data to match headers
				$data = array_pad( $data, count( $headers ), '' );
				$data = array_slice( $data, 0, count( $headers ) );
			}

			// Combine headers with data
			$row = array_combine( $headers, $data );

			// Validate required fields
			if ( empty( $row['title'] ) ) {
				++$results['skipped'];
				$results['errors'][] = "Row $row_number: Missing required field (title)";
				continue;
			}

			// Check for duplicates
			$existing_id = self::find_existing_business( $row, $options['match_by'] );

			// Determine action based on mode
			$action = 'create';
			if ( $existing_id ) {
				switch ( $options['import_mode'] ) {
					case self::MODE_SKIP:
						$action = 'skip';
						break;
					case self::MODE_UPDATE:
						$action = 'update';
						break;
					case self::MODE_CREATE:
						$action = 'create';
						break;
				}
			}

			// Dry run - just record what would happen
			if ( $options['dry_run'] ) {
				$results['preview'][] = array(
					'row'         => $row_number,
					'title'       => $row['title'],
					'action'      => $action,
					'existing_id' => $existing_id,
				);

				if ( 'skip' === $action ) {
					++$results['skipped'];
				} elseif ( 'update' === $action ) {
					++$results['updated'];
				} else {
					++$results['imported'];
				}
				continue;
			}

			// Execute the action
			switch ( $action ) {
				case 'skip':
					++$results['skipped'];
					break;

				case 'update':
					$result = self::update_business( $existing_id, $row, $options );
					if ( is_wp_error( $result ) ) {
						$results['errors'][] = "Row $row_number: " . $result->get_error_message();
						++$results['skipped'];
					} else {
						++$results['updated'];
					}
					break;

				case 'create':
				default:
					$result = self::create_business( $row, $options );
					if ( is_wp_error( $result ) ) {
						$results['errors'][] = "Row $row_number: " . $result->get_error_message();
						++$results['skipped'];
					} else {
						++$results['imported'];
					}
					break;
			}
		}

		fclose( $handle );

		return $results;
	}

	/**
	 * Find existing business by match criteria
	 *
	 * @param array  $data     Row data.
	 * @param string $match_by Match method.
	 * @return int|false Post ID if found, false otherwise.
	 */
	private static function find_existing_business( $data, $match_by ) {
		global $wpdb;

		// Try external_id first if requested
		if ( in_array( $match_by, array( self::MATCH_EXTERNAL_ID, self::MATCH_BOTH ), true ) ) {
			if ( ! empty( $data['external_id'] ) ) {
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} 
						WHERE meta_key = 'bd_external_id' 
						AND meta_value = %s 
						LIMIT 1",
						sanitize_text_field( $data['external_id'] )
					)
				);

				if ( $existing ) {
					// Verify it's a published business
					$post = get_post( $existing );
					if ( $post && 'bd_business' === $post->post_type && 'trash' !== $post->post_status ) {
						return (int) $existing;
					}
				}
			}
		}

		// Try title match if requested
		if ( in_array( $match_by, array( self::MATCH_TITLE, self::MATCH_BOTH ), true ) ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} 
					WHERE post_type = 'bd_business' 
					AND post_status != 'trash'
					AND post_title = %s 
					LIMIT 1",
					sanitize_text_field( $data['title'] )
				)
			);

			if ( $existing ) {
				return (int) $existing;
			}
		}

		return false;
	}

	/**
	 * Create a new business
	 *
	 * @param array $data    Row data.
	 * @param array $options Import options.
	 * @return int|WP_Error Post ID or error.
	 */
	private static function create_business( $data, $options ) {
		$post_data = array(
			'post_title'   => sanitize_text_field( $data['title'] ),
			'post_content' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
			'post_excerpt' => isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '',
			'post_type'    => 'bd_business',
			'post_status'  => 'publish',
		);

		$business_id = wp_insert_post( $post_data );

		if ( is_wp_error( $business_id ) ) {
			return $business_id;
		}

		// Save all meta fields
		self::save_business_meta( $business_id, $data, $options );

		return $business_id;
	}

	/**
	 * Update an existing business
	 *
	 * @param int   $business_id Existing post ID.
	 * @param array $data        Row data.
	 * @param array $options     Import options.
	 * @return int|WP_Error Post ID or error.
	 */
	private static function update_business( $business_id, $data, $options ) {
		$post_data = array(
			'ID'           => $business_id,
			'post_title'   => sanitize_text_field( $data['title'] ),
			'post_content' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
		);

		// Only update excerpt if provided
		if ( ! empty( $data['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $data['excerpt'] );
		}

		$result = wp_update_post( $post_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update all meta fields
		self::save_business_meta( $business_id, $data, $options );

		return $business_id;
	}

	/**
	 * Save all business meta fields
	 *
	 * @param int   $business_id Post ID.
	 * @param array $data        Row data.
	 * @param array $options     Import options.
	 */
	private static function save_business_meta( $business_id, $data, $options ) {
		// External ID (for future imports)
		if ( ! empty( $data['external_id'] ) ) {
			update_post_meta( $business_id, 'bd_external_id', sanitize_text_field( $data['external_id'] ) );
		}

		// Category
		if ( ! empty( $data['category'] ) ) {
			self::set_taxonomy_terms( $business_id, $data['category'], 'bd_category', $options['create_terms'] );
		}

		// Area
		if ( ! empty( $data['area'] ) ) {
			self::set_taxonomy_terms( $business_id, $data['area'], 'bd_area', $options['create_terms'] );
		}

		// Tags (comma-separated)
		if ( ! empty( $data['tags'] ) ) {
			self::set_taxonomy_terms( $business_id, $data['tags'], 'bd_tag', $options['create_terms'] );
		}

		// Location meta
		$location = array(
			'lat'     => isset( $data['lat'] ) && '' !== $data['lat'] ? floatval( $data['lat'] ) : 0,
			'lng'     => isset( $data['lng'] ) && '' !== $data['lng'] ? floatval( $data['lng'] ) : 0,
			'address' => isset( $data['address'] ) ? sanitize_text_field( $data['address'] ) : '',
			'city'    => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : '',
			'state'   => isset( $data['state'] ) ? sanitize_text_field( $data['state'] ) : '',
			'zip'     => isset( $data['zip'] ) ? sanitize_text_field( $data['zip'] ) : '',
			'country' => isset( $data['country'] ) ? sanitize_text_field( $data['country'] ) : '',
		);

		// Geocode if enabled and coordinates are missing
		if ( $options['geocode'] && ( empty( $location['lat'] ) || empty( $location['lng'] ) ) ) {
			$address_string = self::build_address_string( $location );
			if ( ! empty( $address_string ) ) {
				$coords = self::geocode_address( $address_string );
				if ( $coords ) {
					$location['lat'] = $coords['lat'];
					$location['lng'] = $coords['lng'];
				}
			}
		}

		// Only save location if we have coordinates or address
		if ( $location['lat'] || $location['lng'] || $location['address'] ) {
			update_post_meta( $business_id, 'bd_location', $location );
		}

		// Contact meta
		$contact = array(
			'phone'   => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
			'website' => isset( $data['website'] ) ? esc_url_raw( $data['website'] ) : '',
			'email'   => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
		);

		if ( array_filter( $contact ) ) {
			update_post_meta( $business_id, 'bd_contact', $contact );
		}

		// Social media
		$social = array(
			'facebook'  => isset( $data['facebook'] ) ? esc_url_raw( $data['facebook'] ) : '',
			'instagram' => isset( $data['instagram'] ) ? sanitize_text_field( $data['instagram'] ) : '',
			'twitter'   => isset( $data['twitter'] ) ? sanitize_text_field( $data['twitter'] ) : '',
			'linkedin'  => isset( $data['linkedin'] ) ? esc_url_raw( $data['linkedin'] ) : '',
			'youtube'   => isset( $data['youtube'] ) ? esc_url_raw( $data['youtube'] ) : '',
			'tiktok'    => isset( $data['tiktok'] ) ? sanitize_text_field( $data['tiktok'] ) : '',
			'yelp'      => isset( $data['yelp'] ) ? esc_url_raw( $data['yelp'] ) : '',
		);

		if ( array_filter( $social ) ) {
			update_post_meta( $business_id, 'bd_social', $social );
		}

		// Price level
		if ( ! empty( $data['price_level'] ) ) {
			update_post_meta( $business_id, 'bd_price_level', sanitize_text_field( $data['price_level'] ) );
		}

		// Business hours
		self::save_business_hours( $business_id, $data );

		// Featured image from URL
		if ( ! empty( $data['image_url'] ) && $options['download_images'] ) {
			self::set_featured_image_from_url( $business_id, $data['image_url'], $data['title'] );
		}

		// Additional custom fields
		if ( ! empty( $data['year_established'] ) ) {
			update_post_meta( $business_id, 'bd_year_established', absint( $data['year_established'] ) );
		}

		if ( ! empty( $data['owner_name'] ) ) {
			update_post_meta( $business_id, 'bd_owner_name', sanitize_text_field( $data['owner_name'] ) );
		}
	}

	/**
	 * Set taxonomy terms (handles comma-separated values)
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $term_string  Comma-separated terms.
	 * @param string $taxonomy     Taxonomy name.
	 * @param bool   $create_terms Whether to create missing terms.
	 */
	private static function set_taxonomy_terms( $post_id, $term_string, $taxonomy, $create_terms = true ) {
		$term_names = array_map( 'trim', explode( ',', $term_string ) );
		$term_ids   = array();

		foreach ( $term_names as $term_name ) {
			if ( empty( $term_name ) ) {
				continue;
			}

			$term = get_term_by( 'name', $term_name, $taxonomy );

			if ( ! $term && $create_terms ) {
				$term_data = wp_insert_term( $term_name, $taxonomy );
				if ( ! is_wp_error( $term_data ) ) {
					$term_ids[] = (int) $term_data['term_id'];
				}
			} elseif ( $term ) {
				$term_ids[] = $term->term_id;
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * Save business hours from CSV data
	 *
	 * @param int   $business_id Post ID.
	 * @param array $data        Row data.
	 */
	private static function save_business_hours( $business_id, $data ) {
		$hours = array();
		$days  = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );

		foreach ( $days as $day ) {
			// Check for hours_monday format
			$hours_key = 'hours_' . $day;
			if ( ! empty( $data[ $hours_key ] ) ) {
				$parsed = self::parse_hours_string( $data[ $hours_key ] );
				if ( $parsed ) {
					$hours[ $day ] = $parsed;
				}
			}

			// Also check for monday_hours format
			$alt_key = $day . '_hours';
			if ( ! empty( $data[ $alt_key ] ) ) {
				$parsed = self::parse_hours_string( $data[ $alt_key ] );
				if ( $parsed ) {
					$hours[ $day ] = $parsed;
				}
			}
		}

		// Also support single "hours" field with formatted string
		if ( ! empty( $data['hours'] ) && empty( $hours ) ) {
			// Try to parse a general hours string
			update_post_meta( $business_id, 'bd_hours_text', sanitize_textarea_field( $data['hours'] ) );
		}

		if ( ! empty( $hours ) ) {
			update_post_meta( $business_id, 'bd_hours', $hours );
		}
	}

	/**
	 * Parse hours string into open/close array
	 *
	 * @param string $hours_string Hours string (e.g., "9:00-17:00" or "9am-5pm" or "Closed").
	 * @return array|false Parsed hours or false.
	 */
	private static function parse_hours_string( $hours_string ) {
		$hours_string = trim( strtolower( $hours_string ) );

		// Handle closed/holiday
		if ( in_array( $hours_string, array( 'closed', 'holiday', '-', 'n/a' ), true ) ) {
			return array(
				'closed' => true,
			);
		}

		// Handle 24 hours
		if ( in_array( $hours_string, array( '24 hours', '24h', 'open 24 hours' ), true ) ) {
			return array(
				'open'  => '00:00',
				'close' => '23:59',
			);
		}

		// Try to parse "open-close" format
		if ( strpos( $hours_string, '-' ) !== false ) {
			$times = explode( '-', $hours_string );
			if ( count( $times ) === 2 ) {
				return array(
					'open'  => trim( $times[0] ),
					'close' => trim( $times[1] ),
				);
			}
		}

		return false;
	}

	/**
	 * Download and set featured image from URL
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $image_url Image URL.
	 * @param string $title     Image title.
	 * @return int|false Attachment ID or false.
	 */
	private static function set_featured_image_from_url( $post_id, $image_url, $title ) {
		// Check if post already has a featured image
		if ( has_post_thumbnail( $post_id ) ) {
			return get_post_thumbnail_id( $post_id );
		}

		// Require media functions
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the image
		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		// Prepare file array
		$file_array = array(
			'name'     => sanitize_file_name( basename( wp_parse_url( $image_url, PHP_URL_PATH ) ) ),
			'tmp_name' => $tmp,
		);

		// If no extension, try to detect from content type
		if ( ! preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $file_array['name'] ) ) {
			$file_array['name'] .= '.jpg';
		}

		// Upload to media library
		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

		// Clean up temp file
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		// Set as featured image
		set_post_thumbnail( $post_id, $attachment_id );

		return $attachment_id;
	}

	/**
	 * Get sample CSV content for download
	 *
	 * @return string CSV content.
	 */
	public static function get_sample_csv() {
		$headers = array(
			'title',
			'description',
			'excerpt',
			'external_id',
			'category',
			'area',
			'tags',
			'address',
			'city',
			'state',
			'zip',
			'country',
			'lat',
			'lng',
			'phone',
			'email',
			'website',
			'facebook',
			'instagram',
			'twitter',
			'yelp',
			'price_level',
			'hours_monday',
			'hours_tuesday',
			'hours_wednesday',
			'hours_thursday',
			'hours_friday',
			'hours_saturday',
			'hours_sunday',
			'image_url',
			'year_established',
			'owner_name',
		);

		$sample_row = array(
			'Sample Business Name',
			'A great description of the business with all the details customers need.',
			'Short excerpt shown in listings',
			'BIZ-001',
			'Restaurant',
			'Downtown',
			'family-friendly, outdoor seating, wifi',
			'123 Main Street',
			'Livermore',
			'CA',
			'94550',
			'USA',
			'37.6819',
			'-121.7680',
			'(925) 555-1234',
			'info@samplebusiness.com',
			'https://samplebusiness.com',
			'https://facebook.com/samplebusiness',
			'@samplebusiness',
			'@samplebiz',
			'https://yelp.com/biz/sample-business',
			'$$',
			'9:00-17:00',
			'9:00-17:00',
			'9:00-17:00',
			'9:00-17:00',
			'9:00-21:00',
			'10:00-16:00',
			'Closed',
			'https://example.com/image.jpg',
			'2010',
			'John Smith',
		);

		$csv  = implode( ',', $headers ) . "\n";
		$csv .= '"' . implode( '","', $sample_row ) . '"' . "\n";

		return $csv;
	}

	/**
	 * Build address string for geocoding
	 *
	 * @param array $location Location array with address components.
	 * @return string Address string or empty.
	 */
	private static function build_address_string( $location ) {
		$parts = array();

		if ( ! empty( $location['address'] ) ) {
			$parts[] = $location['address'];
		}

		if ( ! empty( $location['city'] ) ) {
			$parts[] = $location['city'];
		}

		if ( ! empty( $location['state'] ) ) {
			$parts[] = $location['state'];
		}

		if ( ! empty( $location['zip'] ) ) {
			$parts[] = $location['zip'];
		}

		if ( ! empty( $location['country'] ) ) {
			$parts[] = $location['country'];
		}

		return implode( ', ', $parts );
	}

	/**
	 * Geocode an address using Nominatim (OpenStreetMap)
	 *
	 * @param string $address Address string to geocode.
	 * @return array|false Array with lat/lng or false on failure.
	 */
	private static function geocode_address( $address ) {
		if ( empty( $address ) ) {
			return false;
		}

		// Check cache first
		$cache_key = md5( strtolower( trim( $address ) ) );
		if ( isset( self::$geocode_cache[ $cache_key ] ) ) {
			return self::$geocode_cache[ $cache_key ];
		}

		// Check WordPress transient cache (persists across imports)
		$transient_key = 'bd_geocode_' . $cache_key;
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			self::$geocode_cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Rate limiting - Nominatim requires max 1 request per second
		$now = microtime( true );
		if ( self::$last_geocode_time > 0 ) {
			$elapsed = $now - self::$last_geocode_time;
			if ( $elapsed < 1.1 ) {
				usleep( (int) ( ( 1.1 - $elapsed ) * 1000000 ) );
			}
		}
		self::$last_geocode_time = microtime( true );

		// Build request URL
		$url = add_query_arg(
			array(
				'q'      => rawurlencode( $address ),
				'format' => 'json',
				'limit'  => 1,
			),
			'https://nominatim.openstreetmap.org/search'
		);

		// Make request with proper user agent (required by Nominatim)
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'BusinessDirectoryPlugin/1.0 (WordPress)',
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || ! is_array( $data ) || ! isset( $data[0]['lat'] ) ) {
			// Cache the failure to avoid repeated lookups
			self::$geocode_cache[ $cache_key ] = false;
			set_transient( $transient_key, false, HOUR_IN_SECONDS );
			return false;
		}

		$result = array(
			'lat' => floatval( $data[0]['lat'] ),
			'lng' => floatval( $data[0]['lon'] ),
		);

		// Cache the result
		self::$geocode_cache[ $cache_key ] = $result;
		set_transient( $transient_key, $result, WEEK_IN_SECONDS );

		return $result;
	}

	/**
	 * Clear geocode cache
	 *
	 * @param string $address Optional specific address to clear, or empty for all.
	 */
	public static function clear_geocode_cache( $address = '' ) {
		if ( empty( $address ) ) {
			// Clear in-memory cache
			self::$geocode_cache = array();
			// Note: Clearing all transients would require database query
			return;
		}

		$cache_key     = md5( strtolower( trim( $address ) ) );
		$transient_key = 'bd_geocode_' . $cache_key;

		unset( self::$geocode_cache[ $cache_key ] );
		delete_transient( $transient_key );
	}
}
