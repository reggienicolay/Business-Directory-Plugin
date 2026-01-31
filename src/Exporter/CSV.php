<?php
/**
 * CSV Exporter for bulk business export
 *
 * Features:
 * - Export all businesses or filtered subset
 * - Match import format for round-trip compatibility
 * - Include all meta fields, taxonomies, and location data
 * - Streaming output for large datasets
 * - Optional inclusion of reviews summary
 *
 * @package BusinessDirectory
 * @version 1.2.0
 */

namespace BD\Exporter;

use BD\DB\LocationsTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CSV
 */
class CSV {

	/**
	 * Export formats.
	 */
	const FORMAT_STANDARD = 'standard';
	const FORMAT_EXTENDED = 'extended';
	const FORMAT_MINIMAL  = 'minimal';

	/**
	 * Maximum businesses per batch to prevent memory issues.
	 */
	const BATCH_SIZE = 500;

	/**
	 * Get allowed export format values.
	 *
	 * @return array Array of allowed format constants.
	 */
	public static function get_allowed_formats() {
		return array(
			self::FORMAT_MINIMAL,
			self::FORMAT_STANDARD,
			self::FORMAT_EXTENDED,
		);
	}

	/**
	 * Get allowed post status values.
	 *
	 * @return array Array of allowed status values.
	 */
	public static function get_allowed_statuses() {
		return array( 'any', 'publish', 'draft', 'pending', 'private' );
	}

	/**
	 * Cached review stats for batch loading.
	 *
	 * @var array
	 */
	private static $review_stats_cache = array();

	/**
	 * Cached table existence checks.
	 *
	 * @var array
	 */
	private static $table_exists_cache = array();

	/**
	 * Export businesses to CSV.
	 *
	 * @param array $args Export arguments.
	 * @return void Outputs CSV directly.
	 */
	public static function export( $args = array() ) {
		$defaults = array(
			'status'           => 'publish',          // publish, draft, pending, any
			'category'         => '',                 // Category slug or ID
			'area'             => '',                 // Area slug or ID
			'city'             => '',                 // Filter by city
			'format'           => self::FORMAT_STANDARD,
			'include_reviews'  => false,              // Include review stats
			'include_claimed'  => true,               // Include claim info
			'include_id'       => true,               // Include post ID
			'filename'         => '',                 // Custom filename
			'business_ids'     => array(),            // Specific IDs to export
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate format against allowed values.
		if ( ! in_array( $args['format'], self::get_allowed_formats(), true ) ) {
			$args['format'] = self::FORMAT_STANDARD;
		}

		// Validate status against allowed values.
		if ( ! in_array( $args['status'], self::get_allowed_statuses(), true ) ) {
			$args['status'] = 'publish';
		}

		// Set up headers for CSV download.
		$filename = ! empty( $args['filename'] )
			? sanitize_file_name( $args['filename'] )
			: 'businesses-export-' . gmdate( 'Y-m-d-His' ) . '.csv';

		// Ensure .csv extension.
		if ( ! str_ends_with( $filename, '.csv' ) ) {
			$filename .= '.csv';
		}

		// Security: Strip any characters that could enable header injection.
		$filename = preg_replace( '/[\r\n\t]/', '', $filename );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );

		// Open output stream.
		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'Failed to open output stream for CSV export.', 'business-directory' ) );
		}

		// Add BOM for Excel UTF-8 compatibility.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Write header row.
		$headers = self::get_headers( $args );
		fputcsv( $output, $headers );

		// Get businesses in batches.
		$offset = 0;

		do {
			$businesses = self::get_businesses( $args, $offset, self::BATCH_SIZE );

			// Batch load review stats if needed (prevents N+1 queries).
			if ( $args['include_reviews'] && ! empty( $businesses ) ) {
				$business_ids = wp_list_pluck( $businesses, 'ID' );
				self::batch_load_review_stats( $business_ids );
			}

			foreach ( $businesses as $business ) {
				$row = self::format_business_row( $business, $args );
				fputcsv( $output, $row );
			}

			$offset += self::BATCH_SIZE;

			// Clear batch caches to free memory.
			self::$review_stats_cache = array();

			// Flush output buffer to prevent memory buildup.
			if ( ob_get_level() > 0 ) {
				ob_flush();
			}
			flush();

		} while ( count( $businesses ) === self::BATCH_SIZE );

		fclose( $output );
	}

	/**
	 * Get export count without fetching all data.
	 *
	 * @param array $args Export arguments.
	 * @return int Number of businesses matching criteria.
	 */
	public static function get_export_count( $args = array() ) {
		$query_args = self::build_query_args( $args );
		$query_args['fields']         = 'ids';
		$query_args['posts_per_page'] = -1;

		$query = new \WP_Query( $query_args );

		// City filter requires post-query filtering since it's stored in separate table.
		if ( ! empty( $args['city'] ) ) {
			$count = 0;
			foreach ( $query->posts as $post_id ) {
				$location = self::get_location_data( $post_id );
				if ( ! empty( $location['city'] ) && strcasecmp( $location['city'], $args['city'] ) === 0 ) {
					++$count;
				}
			}
			return $count;
		}

		return $query->found_posts;
	}

	/**
	 * Get CSV headers based on format.
	 *
	 * @param array $args Export arguments.
	 * @return array Column headers.
	 */
	private static function get_headers( $args ) {
		$headers = array();

		// Always include ID if requested (helps with re-import).
		if ( $args['include_id'] ) {
			$headers[] = 'id';
		}

		// Core fields (always included).
		$headers = array_merge(
			$headers,
			array(
				'title',
				'description',
				'excerpt',
				'external_id',
				'status',
			)
		);

		// Taxonomy fields.
		$headers = array_merge(
			$headers,
			array(
				'category',
				'area',
				'tags',
			)
		);

		// Location fields.
		$headers = array_merge(
			$headers,
			array(
				'address',
				'city',
				'state',
				'zip',
				'country',
				'lat',
				'lng',
			)
		);

		// Contact fields.
		$headers = array_merge(
			$headers,
			array(
				'phone',
				'email',
				'website',
			)
		);

		// Social media (standard and extended formats).
		if ( in_array( $args['format'], array( self::FORMAT_STANDARD, self::FORMAT_EXTENDED ), true ) ) {
			$headers = array_merge(
				$headers,
				array(
					'facebook',
					'instagram',
					'twitter',
					'linkedin',
					'youtube',
					'tiktok',
					'yelp',
				)
			);
		}

		// Business details.
		$headers = array_merge(
			$headers,
			array(
				'price_level',
			)
		);

		// Hours (standard and extended formats).
		if ( in_array( $args['format'], array( self::FORMAT_STANDARD, self::FORMAT_EXTENDED ), true ) ) {
			$headers = array_merge(
				$headers,
				array(
					'hours_monday',
					'hours_tuesday',
					'hours_wednesday',
					'hours_thursday',
					'hours_friday',
					'hours_saturday',
					'hours_sunday',
				)
			);
		}

		// Extended fields.
		if ( self::FORMAT_EXTENDED === $args['format'] ) {
			$headers = array_merge(
				$headers,
				array(
					'year_established',
					'owner_name',
					'google_place_id',
					'featured_image_url',
				)
			);
		}

		// Review stats (optional).
		if ( $args['include_reviews'] ) {
			$headers = array_merge(
				$headers,
				array(
					'review_count',
					'avg_rating',
				)
			);
		}

		// Claim info (optional).
		if ( $args['include_claimed'] ) {
			$headers = array_merge(
				$headers,
				array(
					'is_claimed',
					'claimed_by_user_id',
				)
			);
		}

		// Meta fields for extended format.
		if ( self::FORMAT_EXTENDED === $args['format'] ) {
			$headers = array_merge(
				$headers,
				array(
					'created_date',
					'modified_date',
					'author_id',
					'view_count',
				)
			);
		}

		return $headers;
	}

	/**
	 * Build WP_Query arguments from export args.
	 *
	 * @param array $args Export arguments.
	 * @return array WP_Query arguments.
	 */
	private static function build_query_args( $args ) {
		// Ensure status is validated.
		$status = $args['status'] ?? 'publish';
		if ( ! in_array( $status, self::get_allowed_statuses(), true ) ) {
			$status = 'publish';
		}

		$query_args = array(
			'post_type'      => 'bd_business',
			'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private' ) : $status,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		);

		// Specific IDs.
		if ( ! empty( $args['business_ids'] ) ) {
			$query_args['post__in'] = array_map( 'absint', (array) $args['business_ids'] );
		}

		// Category filter.
		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'bd_category',
				'field'    => is_numeric( $args['category'] ) ? 'term_id' : 'slug',
				'terms'    => $args['category'],
			);
		}

		// Area filter.
		if ( ! empty( $args['area'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'bd_area',
				'field'    => is_numeric( $args['area'] ) ? 'term_id' : 'slug',
				'terms'    => $args['area'],
			);
		}

		return $query_args;
	}

	/**
	 * Get businesses for export.
	 *
	 * @param array $args   Export arguments.
	 * @param int   $offset Query offset.
	 * @param int   $limit  Query limit.
	 * @return array Array of WP_Post objects with meta.
	 */
	private static function get_businesses( $args, $offset, $limit ) {
		$query_args                   = self::build_query_args( $args );
		$query_args['offset']         = $offset;
		$query_args['posts_per_page'] = $limit;

		$query = new \WP_Query( $query_args );

		if ( empty( $query->posts ) ) {
			return array();
		}

		// Prime caches for efficiency.
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		update_meta_cache( 'post', $post_ids );
		update_object_term_cache( $post_ids, 'bd_business' );

		// Filter by city if specified (requires location lookup).
		if ( ! empty( $args['city'] ) ) {
			$filtered_posts = array();
			foreach ( $query->posts as $post ) {
				$location = self::get_location_data( $post->ID );
				if ( ! empty( $location['city'] ) && strcasecmp( $location['city'], $args['city'] ) === 0 ) {
					$filtered_posts[] = $post;
				}
			}
			return $filtered_posts;
		}

		return $query->posts;
	}

	/**
	 * Format a single business as a CSV row.
	 *
	 * @param \WP_Post $business Business post object.
	 * @param array    $args     Export arguments.
	 * @return array Row data.
	 */
	private static function format_business_row( $business, $args ) {
		$row = array();

		// Get all meta at once.
		$meta = get_post_meta( $business->ID );

		// Get location data.
		$location = self::get_location_data( $business->ID );

		// Get taxonomy terms (use get_the_terms which uses object cache primed earlier).
		$categories = get_the_terms( $business->ID, 'bd_category' );
		$areas      = get_the_terms( $business->ID, 'bd_area' );
		$tags       = get_the_terms( $business->ID, 'bd_tag' );

		// ID.
		if ( $args['include_id'] ) {
			$row[] = $business->ID;
		}

		// Core fields.
		$row[] = $business->post_title;
		$row[] = $business->post_content;
		$row[] = $business->post_excerpt;
		$row[] = self::get_meta_value( $meta, 'bd_external_id' );
		$row[] = $business->post_status;

		// Taxonomies (handle WP_Error and false returns).
		$row[] = is_array( $categories ) ? implode( ', ', wp_list_pluck( $categories, 'name' ) ) : '';
		$row[] = is_array( $areas ) ? implode( ', ', wp_list_pluck( $areas, 'name' ) ) : '';
		$row[] = is_array( $tags ) ? implode( ', ', wp_list_pluck( $tags, 'name' ) ) : '';

		// Location.
		$row[] = $location['address'] ?? '';
		$row[] = $location['city'] ?? '';
		$row[] = $location['state'] ?? '';
		$row[] = $location['zip'] ?? '';
		$row[] = $location['country'] ?? '';
		$row[] = $location['lat'] ?? '';
		$row[] = $location['lng'] ?? '';

		// Contact (check both flat meta and nested array).
		$contact = self::get_meta_value( $meta, 'bd_contact', array() );
		if ( ! is_array( $contact ) ) {
			$contact = array();
		}
		$row[] = self::get_meta_value( $meta, 'bd_phone' ) ?: ( $contact['phone'] ?? '' );
		$row[] = self::get_meta_value( $meta, 'bd_email' ) ?: ( $contact['email'] ?? '' );
		$row[] = self::get_meta_value( $meta, 'bd_website' ) ?: ( $contact['website'] ?? '' );

		// Social media.
		if ( in_array( $args['format'], array( self::FORMAT_STANDARD, self::FORMAT_EXTENDED ), true ) ) {
			$social = self::get_meta_value( $meta, 'bd_social', array() );
			if ( ! is_array( $social ) ) {
				$social = array();
			}
			$row[] = self::get_meta_value( $meta, 'bd_social_facebook' ) ?: ( $social['facebook'] ?? '' );
			$row[] = self::get_meta_value( $meta, 'bd_social_instagram' ) ?: ( $social['instagram'] ?? '' );
			$row[] = self::get_meta_value( $meta, 'bd_social_twitter' ) ?: ( $social['twitter'] ?? '' );
			$row[] = self::get_meta_value( $meta, 'bd_social_linkedin' ) ?: ( $social['linkedin'] ?? '' );
			$row[] = self::get_meta_value( $meta, 'bd_social_youtube' ) ?: ( $social['youtube'] ?? '' );
			$row[] = self::get_meta_value( $meta, 'bd_social_tiktok' ) ?: ( $social['tiktok'] ?? '' );
			$row[] = self::get_meta_value( $meta, 'bd_social_yelp' ) ?: ( $social['yelp'] ?? '' );
		}

		// Price level.
		$row[] = self::get_meta_value( $meta, 'bd_price_level' );

		// Hours.
		if ( in_array( $args['format'], array( self::FORMAT_STANDARD, self::FORMAT_EXTENDED ), true ) ) {
			$hours = self::get_meta_value( $meta, 'bd_hours', array() );
			if ( ! is_array( $hours ) ) {
				$hours = array();
			}
			$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
			foreach ( $days as $day ) {
				$row[] = self::format_hours_for_export( $hours[ $day ] ?? null );
			}
		}

		// Extended fields.
		if ( self::FORMAT_EXTENDED === $args['format'] ) {
			$row[] = self::get_meta_value( $meta, 'bd_year_established' );
			$row[] = self::get_meta_value( $meta, 'bd_owner_name' );
			$row[] = self::get_meta_value( $meta, 'bd_google_place_id' );
			$row[] = get_the_post_thumbnail_url( $business->ID, 'full' ) ?: '';
		}

		// Review stats (uses batch-loaded cache).
		if ( $args['include_reviews'] ) {
			$review_stats = self::get_review_stats( $business->ID );
			$row[]        = $review_stats['count'];
			$row[]        = $review_stats['avg_rating'];
		}

		// Claim info.
		if ( $args['include_claimed'] ) {
			$claimed_by = self::get_meta_value( $meta, 'bd_claimed_by' );
			$row[]      = ! empty( $claimed_by ) ? 'yes' : 'no';
			$row[]      = $claimed_by ?: '';
		}

		// Extended meta.
		if ( self::FORMAT_EXTENDED === $args['format'] ) {
			$row[] = $business->post_date;
			$row[] = $business->post_modified;
			$row[] = $business->post_author;
			$row[] = self::get_meta_value( $meta, 'bd_view_count', 0 );
		}

		return $row;
	}

	/**
	 * Get location data for a business.
	 *
	 * @param int $business_id Business post ID.
	 * @return array Location data.
	 */
	private static function get_location_data( $business_id ) {
		// Try custom locations table first.
		if ( class_exists( '\BD\DB\LocationsTable' ) ) {
			$location = LocationsTable::get( $business_id );
			if ( $location ) {
				return $location;
			}
		}

		// Fall back to post meta.
		$location = get_post_meta( $business_id, 'bd_location', true );
		if ( is_array( $location ) ) {
			return $location;
		}

		return array();
	}

	/**
	 * Get meta value from meta array.
	 *
	 * @param array  $meta    All post meta.
	 * @param string $key     Meta key.
	 * @param mixed  $default Default value.
	 * @return mixed Meta value.
	 */
	private static function get_meta_value( $meta, $key, $default = '' ) {
		if ( isset( $meta[ $key ] ) && is_array( $meta[ $key ] ) ) {
			$value = $meta[ $key ][0] ?? $default;
			return maybe_unserialize( $value );
		}
		return $default;
	}

	/**
	 * Format hours array for CSV export.
	 *
	 * @param array|null $hours_data Hours data for a single day.
	 * @return string Formatted hours string.
	 */
	private static function format_hours_for_export( $hours_data ) {
		if ( empty( $hours_data ) ) {
			return '';
		}

		if ( is_string( $hours_data ) ) {
			return $hours_data;
		}

		if ( ! empty( $hours_data['closed'] ) ) {
			return 'Closed';
		}

		if ( isset( $hours_data['open'] ) && isset( $hours_data['close'] ) ) {
			return $hours_data['open'] . '-' . $hours_data['close'];
		}

		return '';
	}

	/**
	 * Batch load review statistics for multiple businesses.
	 *
	 * @param array $business_ids Array of business post IDs.
	 */
	private static function batch_load_review_stats( $business_ids ) {
		if ( empty( $business_ids ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bd_reviews';

		// Check if table exists (cached).
		if ( ! self::table_exists( $table ) ) {
			// Pre-fill cache with zeros.
			foreach ( $business_ids as $id ) {
				self::$review_stats_cache[ $id ] = array(
					'count'      => 0,
					'avg_rating' => 0,
				);
			}
			return;
		}

		// Build placeholders for IN clause.
		$placeholders = implode( ', ', array_fill( 0, count( $business_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT business_id, COUNT(*) as count, AVG(rating) as avg_rating 
				FROM {$table} 
				WHERE business_id IN ({$placeholders}) AND status = 'approved'
				GROUP BY business_id",
				...$business_ids
			),
			ARRAY_A
		);

		// Index results by business_id.
		$stats_by_id = array();
		foreach ( $results as $row ) {
			$stats_by_id[ $row['business_id'] ] = array(
				'count'      => absint( $row['count'] ),
				'avg_rating' => round( floatval( $row['avg_rating'] ), 1 ),
			);
		}

		// Fill cache (including zeros for businesses with no reviews).
		foreach ( $business_ids as $id ) {
			self::$review_stats_cache[ $id ] = $stats_by_id[ $id ] ?? array(
				'count'      => 0,
				'avg_rating' => 0,
			);
		}
	}

	/**
	 * Check if a database table exists (cached).
	 *
	 * @param string $table Full table name.
	 * @return bool Whether table exists.
	 */
	private static function table_exists( $table ) {
		if ( isset( self::$table_exists_cache[ $table ] ) ) {
			return self::$table_exists_cache[ $table ];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		) === $table;

		self::$table_exists_cache[ $table ] = $exists;

		return $exists;
	}

	/**
	 * Get review statistics for a business.
	 *
	 * @param int $business_id Business post ID.
	 * @return array Review stats.
	 */
	private static function get_review_stats( $business_id ) {
		// Check cache first (populated by batch_load_review_stats).
		if ( isset( self::$review_stats_cache[ $business_id ] ) ) {
			return self::$review_stats_cache[ $business_id ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bd_reviews';

		// Check if table exists (cached).
		if ( ! self::table_exists( $table ) ) {
			return array(
				'count'      => 0,
				'avg_rating' => 0,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as count, AVG(rating) as avg_rating 
				FROM {$table} 
				WHERE business_id = %d AND status = 'approved'",
				$business_id
			),
			ARRAY_A
		);

		return array(
			'count'      => absint( $stats['count'] ?? 0 ),
			'avg_rating' => round( floatval( $stats['avg_rating'] ?? 0 ), 1 ),
		);
	}

	/**
	 * Get available export formats.
	 *
	 * @return array Format options.
	 */
	public static function get_formats() {
		return array(
			self::FORMAT_MINIMAL  => __( 'Minimal (core fields only)', 'business-directory' ),
			self::FORMAT_STANDARD => __( 'Standard (includes social & hours)', 'business-directory' ),
			self::FORMAT_EXTENDED => __( 'Extended (all fields + metadata)', 'business-directory' ),
		);
	}

	/**
	 * Get column descriptions for help text.
	 *
	 * @return array Column descriptions.
	 */
	public static function get_column_descriptions() {
		return array(
			'id'                 => __( 'WordPress post ID', 'business-directory' ),
			'title'              => __( 'Business name', 'business-directory' ),
			'description'        => __( 'Full description', 'business-directory' ),
			'excerpt'            => __( 'Short excerpt', 'business-directory' ),
			'external_id'        => __( 'External system ID', 'business-directory' ),
			'status'             => __( 'Post status (publish, draft, pending)', 'business-directory' ),
			'category'           => __( 'Business categories (comma-separated)', 'business-directory' ),
			'area'               => __( 'Business areas/neighborhoods', 'business-directory' ),
			'tags'               => __( 'Tags (comma-separated)', 'business-directory' ),
			'address'            => __( 'Street address', 'business-directory' ),
			'city'               => __( 'City', 'business-directory' ),
			'state'              => __( 'State/Province', 'business-directory' ),
			'zip'                => __( 'ZIP/Postal code', 'business-directory' ),
			'country'            => __( 'Country', 'business-directory' ),
			'lat'                => __( 'Latitude coordinate', 'business-directory' ),
			'lng'                => __( 'Longitude coordinate', 'business-directory' ),
			'phone'              => __( 'Phone number', 'business-directory' ),
			'email'              => __( 'Email address', 'business-directory' ),
			'website'            => __( 'Website URL', 'business-directory' ),
			'price_level'        => __( 'Price level ($, $$, $$$, $$$$)', 'business-directory' ),
			'review_count'       => __( 'Number of approved reviews', 'business-directory' ),
			'avg_rating'         => __( 'Average star rating', 'business-directory' ),
			'is_claimed'         => __( 'Whether business is claimed', 'business-directory' ),
			'claimed_by_user_id' => __( 'User ID of claimer', 'business-directory' ),
		);
	}
}
