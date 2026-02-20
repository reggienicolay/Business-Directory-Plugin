<?php
/**
 * Duplicate Business Finder
 *
 * Detects potential duplicate businesses using multiple detection methods.
 *
 * @package BusinessDirectory
 * @version 1.2.0
 */

namespace BD\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DuplicateFinder
 */
class DuplicateFinder {

	/**
	 * Detection method constants.
	 */
	const METHOD_EXACT_TITLE      = 'exact_title';
	const METHOD_NORMALIZED_TITLE = 'normalized_title';
	const METHOD_TITLE_CITY       = 'title_city';
	const METHOD_TITLE_ADDRESS    = 'title_address';
	const METHOD_PHONE            = 'phone';
	const METHOD_WEBSITE          = 'website';

	/**
	 * Confidence levels.
	 */
	const CONFIDENCE_HIGH   = 'high';
	const CONFIDENCE_MEDIUM = 'medium';
	const CONFIDENCE_LOW    = 'low';

	/**
	 * Cache expiration in seconds (5 minutes).
	 */
	const CACHE_EXPIRATION = 300;

	/**
	 * Get allowed detection methods.
	 *
	 * @return array Array of allowed method constants.
	 */
	public static function get_allowed_methods() {
		return array(
			self::METHOD_EXACT_TITLE,
			self::METHOD_NORMALIZED_TITLE,
			self::METHOD_TITLE_CITY,
			self::METHOD_TITLE_ADDRESS,
			self::METHOD_PHONE,
			self::METHOD_WEBSITE,
		);
	}

	/**
	 * Find all duplicate groups using specified detection methods.
	 *
	 * @param array $methods   Array of detection methods to use. Empty = all methods.
	 * @param bool  $use_cache Whether to use cached results.
	 * @return array Array of duplicate groups, each containing matching business IDs and metadata.
	 */
	public static function find_duplicates( $methods = array(), $use_cache = true ) {
		// Get allowed methods.
		$allowed_methods = self::get_allowed_methods();

		if ( empty( $methods ) ) {
			$methods = $allowed_methods;
		} else {
			// Filter to only allowed methods.
			$methods = array_intersect( $methods, $allowed_methods );
			if ( empty( $methods ) ) {
				return array();
			}
		}

		// Generate cache key based on methods.
		$cache_key = 'bd_duplicates_' . md5( wp_json_encode( $methods ) );

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$all_duplicates = array();

		foreach ( $methods as $method ) {
			$duplicates = self::find_by_method( $method );
			foreach ( $duplicates as $group ) {
				$all_duplicates[] = $group;
			}
		}

		// Merge overlapping groups (same businesses found by different methods).
		$merged = self::merge_duplicate_groups( $all_duplicates );

		// Sort by confidence (highest first).
		usort(
			$merged,
			function ( $a, $b ) {
				$order = array(
					self::CONFIDENCE_HIGH   => 1,
					self::CONFIDENCE_MEDIUM => 2,
					self::CONFIDENCE_LOW    => 3,
				);
				return ( $order[ $a['confidence'] ] ?? 4 ) <=> ( $order[ $b['confidence'] ] ?? 4 );
			}
		);

		// Cache the results.
		set_transient( $cache_key, $merged, self::CACHE_EXPIRATION );

		return $merged;
	}

	/**
	 * Clear duplicate detection cache.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_bd_duplicates_%' 
			OR option_name LIKE '_transient_timeout_bd_duplicates_%'
			OR option_name LIKE '_transient_bd_duplicate_count%'
			OR option_name LIKE '_transient_timeout_bd_duplicate_count%'"
		);
	}

	/**
	 * Get just the count of duplicate groups (optimized for menu badge).
	 *
	 * @return int Number of duplicate groups.
	 */
	public static function get_duplicate_count() {
		$cache_key = 'bd_duplicate_count';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		// Quick count using just exact title (fastest detection method).
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT post_title
				FROM {$wpdb->posts}
				WHERE post_type = 'bd_business'
				AND post_status IN ('publish', 'draft', 'pending')
				AND post_title != ''
				GROUP BY post_title
				HAVING COUNT(*) > 1
			) as dups"
		);

		$count = absint( $count );
		set_transient( $cache_key, $count, self::CACHE_EXPIRATION );

		return $count;
	}

	/**
	 * Find duplicates using a specific detection method.
	 *
	 * @param string $method Detection method constant.
	 * @return array Array of duplicate groups.
	 */
	public static function find_by_method( $method ) {
		switch ( $method ) {
			case self::METHOD_EXACT_TITLE:
				return self::find_exact_title_duplicates();

			case self::METHOD_NORMALIZED_TITLE:
				return self::find_normalized_title_duplicates();

			case self::METHOD_TITLE_CITY:
				return self::find_title_city_duplicates();

			case self::METHOD_TITLE_ADDRESS:
				return self::find_title_address_duplicates();

			case self::METHOD_PHONE:
				return self::find_phone_duplicates();

			case self::METHOD_WEBSITE:
				return self::find_website_duplicates();

			default:
				return array();
		}
	}

	/**
	 * Find businesses with exact matching titles.
	 *
	 * @return array Duplicate groups.
	 */
	private static function find_exact_title_duplicates() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT post_title, GROUP_CONCAT(ID ORDER BY ID) as ids, COUNT(*) as count
			FROM {$wpdb->posts}
			WHERE post_type = 'bd_business'
			AND post_status IN ('publish', 'draft', 'pending')
			AND post_title != ''
			GROUP BY post_title
			HAVING count > 1
			ORDER BY count DESC
			LIMIT 500",
			ARRAY_A
		);

		return self::format_groups( $results ?? array(), self::METHOD_EXACT_TITLE, self::CONFIDENCE_HIGH );
	}

	/**
	 * Find businesses with normalized matching titles.
	 * Handles variations like "Joe's Pizza" vs "Joes Pizza" vs "JOE'S PIZZA".
	 *
	 * @return array Duplicate groups.
	 */
	private static function find_normalized_title_duplicates() {
		global $wpdb;

		// Get all businesses - limit to prevent memory issues.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$businesses = $wpdb->get_results(
			"SELECT ID, post_title
			FROM {$wpdb->posts}
			WHERE post_type = 'bd_business'
			AND post_status IN ('publish', 'draft', 'pending')
			AND post_title != ''
			LIMIT 5000",
			ARRAY_A
		);

		if ( empty( $businesses ) ) {
			return array();
		}

		// Build lookup of original titles by ID for later use.
		$titles_by_id = array();
		foreach ( $businesses as $business ) {
			$titles_by_id[ $business['ID'] ] = $business['post_title'];
		}

		// Group by normalized title.
		$normalized_groups = array();
		foreach ( $businesses as $business ) {
			$normalized = self::normalize_title( $business['post_title'] );
			if ( empty( $normalized ) ) {
				continue;
			}
			if ( ! isset( $normalized_groups[ $normalized ] ) ) {
				$normalized_groups[ $normalized ] = array();
			}
			$normalized_groups[ $normalized ][] = $business['ID'];
		}

		// Filter to only groups with duplicates.
		$duplicates = array();
		foreach ( $normalized_groups as $normalized_title => $ids ) {
			if ( count( $ids ) > 1 ) {
				// Check if this isn't already caught by exact match.
				$titles = array();
				foreach ( $ids as $id ) {
					// Use cached title instead of get_the_title() to avoid N+1 queries.
					$titles[ $titles_by_id[ $id ] ?? '' ] = true;
				}
				// Only include if titles are different (not exact matches).
				if ( count( $titles ) > 1 ) {
					$duplicates[] = array(
						'post_title' => $normalized_title . ' (normalized)',
						'ids'        => implode( ',', $ids ),
						'count'      => count( $ids ),
					);
				}
			}
		}

		return self::format_groups( $duplicates, self::METHOD_NORMALIZED_TITLE, self::CONFIDENCE_MEDIUM );
	}

	/**
	 * Find businesses with matching title AND city.
	 *
	 * @return array Duplicate groups.
	 */
	private static function find_title_city_duplicates() {
		global $wpdb;

		$locations_table = $wpdb->prefix . 'bd_locations';

		// Check if locations table exists.
		if ( ! self::table_exists( $locations_table ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT 
				CONCAT(p.post_title, ' | ', COALESCE(l.city, '')) as match_key,
				GROUP_CONCAT(p.ID ORDER BY p.ID) as ids,
				COUNT(*) as count,
				p.post_title,
				l.city
			FROM {$wpdb->posts} p
			INNER JOIN {$locations_table} l ON p.ID = l.business_id
			WHERE p.post_type = 'bd_business'
			AND p.post_status IN ('publish', 'draft', 'pending')
			AND p.post_title != ''
			AND l.city IS NOT NULL AND l.city != ''
			GROUP BY p.post_title, l.city
			HAVING count > 1
			ORDER BY count DESC
			LIMIT 500",
			ARRAY_A
		);

		$groups = array();
		foreach ( $results ?? array() as $row ) {
			$groups[] = array(
				'post_title' => $row['post_title'] . ' (' . $row['city'] . ')',
				'ids'        => $row['ids'],
				'count'      => $row['count'],
			);
		}

		return self::format_groups( $groups, self::METHOD_TITLE_CITY, self::CONFIDENCE_HIGH );
	}

	/**
	 * Find businesses with matching title AND address.
	 *
	 * @return array Duplicate groups.
	 */
	private static function find_title_address_duplicates() {
		global $wpdb;

		$locations_table = $wpdb->prefix . 'bd_locations';

		// Check if locations table exists.
		if ( ! self::table_exists( $locations_table ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT 
				CONCAT(p.post_title, ' | ', COALESCE(l.address, '')) as match_key,
				GROUP_CONCAT(p.ID ORDER BY p.ID) as ids,
				COUNT(*) as count,
				p.post_title,
				l.address
			FROM {$wpdb->posts} p
			INNER JOIN {$locations_table} l ON p.ID = l.business_id
			WHERE p.post_type = 'bd_business'
			AND p.post_status IN ('publish', 'draft', 'pending')
			AND p.post_title != ''
			AND l.address IS NOT NULL AND l.address != ''
			GROUP BY p.post_title, l.address
			HAVING count > 1
			ORDER BY count DESC
			LIMIT 500",
			ARRAY_A
		);

		$groups = array();
		foreach ( $results ?? array() as $row ) {
			$groups[] = array(
				'post_title' => $row['post_title'] . ' @ ' . $row['address'],
				'ids'        => $row['ids'],
				'count'      => $row['count'],
			);
		}

		return self::format_groups( $groups, self::METHOD_TITLE_ADDRESS, self::CONFIDENCE_HIGH );
	}

	/**
	 * Find businesses with matching phone numbers.
	 *
	 * @return array Duplicate groups.
	 */
	private static function find_phone_duplicates() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT 
				meta_value as phone,
				GROUP_CONCAT(post_id ORDER BY post_id) as ids,
				COUNT(*) as count
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = 'bd_phone'
			AND pm.meta_value != ''
			AND p.post_type = 'bd_business'
			AND p.post_status IN ('publish', 'draft', 'pending')
			GROUP BY pm.meta_value
			HAVING count > 1
			ORDER BY count DESC
			LIMIT 500",
			ARRAY_A
		);

		$groups = array();
		foreach ( $results ?? array() as $row ) {
			$groups[] = array(
				'post_title' => 'Phone: ' . $row['phone'],
				'ids'        => $row['ids'],
				'count'      => $row['count'],
			);
		}

		return self::format_groups( $groups, self::METHOD_PHONE, self::CONFIDENCE_HIGH );
	}

	/**
	 * Find businesses with matching website URLs.
	 *
	 * @return array Duplicate groups.
	 */
	private static function find_website_duplicates() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT 
				meta_value as website,
				GROUP_CONCAT(post_id ORDER BY post_id) as ids,
				COUNT(*) as count
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = 'bd_website'
			AND pm.meta_value != ''
			AND p.post_type = 'bd_business'
			AND p.post_status IN ('publish', 'draft', 'pending')
			GROUP BY pm.meta_value
			HAVING count > 1
			ORDER BY count DESC
			LIMIT 500",
			ARRAY_A
		);

		$groups = array();
		foreach ( $results ?? array() as $row ) {
			// Normalize URL for display.
			$url      = preg_replace( '#^https?://(www\.)?#', '', $row['website'] );
			$groups[] = array(
				'post_title' => 'Website: ' . $url,
				'ids'        => $row['ids'],
				'count'      => $row['count'],
			);
		}

		return self::format_groups( $groups, self::METHOD_WEBSITE, self::CONFIDENCE_HIGH );
	}

	/**
	 * Check if a database table exists.
	 *
	 * @param string $table_name Full table name.
	 * @return bool True if table exists.
	 */
	private static function table_exists( $table_name ) {
		global $wpdb;

		static $cache = array();

		if ( isset( $cache[ $table_name ] ) ) {
			return $cache[ $table_name ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		) === $table_name;

		$cache[ $table_name ] = $exists;

		return $exists;
	}

	/**
	 * Format raw query results into standardized duplicate groups.
	 *
	 * @param array  $results    Raw query results.
	 * @param string $method     Detection method used.
	 * @param string $confidence Confidence level.
	 * @return array Formatted duplicate groups.
	 */
	private static function format_groups( $results, $method, $confidence ) {
		$groups = array();

		foreach ( $results as $row ) {
			if ( empty( $row['ids'] ) ) {
				continue;
			}

			$ids = array_map( 'absint', explode( ',', $row['ids'] ) );
			$ids = array_filter( $ids ); // Remove any zero values.

			if ( count( $ids ) < 2 ) {
				continue;
			}

			$groups[] = array(
				'match_key'    => $row['post_title'] ?? '',
				'method'       => $method,
				'confidence'   => $confidence,
				'business_ids' => $ids,
				'count'        => count( $ids ),
			);
		}

		return $groups;
	}

	/**
	 * Merge overlapping duplicate groups.
	 * If business A appears in multiple groups, merge those groups.
	 *
	 * @param array $groups Array of duplicate groups.
	 * @return array Merged groups.
	 */
	private static function merge_duplicate_groups( $groups ) {
		$business_to_group = array();
		$merged_groups     = array();
		$group_index       = 0;

		foreach ( $groups as $group ) {
			$found_group = null;

			foreach ( $group['business_ids'] as $id ) {
				if ( isset( $business_to_group[ $id ] ) ) {
					$found_group = $business_to_group[ $id ];
					break;
				}
			}

			if ( null !== $found_group ) {
				$existing                 = &$merged_groups[ $found_group ];
				$existing['business_ids'] = array_values(
					array_unique(
						array_merge( $existing['business_ids'], $group['business_ids'] )
					)
				);
				$existing['count']        = count( $existing['business_ids'] );
				$existing['methods']      = array_unique(
					array_merge(
						$existing['methods'] ?? array( $existing['method'] ),
						array( $group['method'] )
					)
				);

				if ( self::CONFIDENCE_HIGH === $group['confidence'] ) {
					$existing['confidence'] = self::CONFIDENCE_HIGH;
				}

				foreach ( $group['business_ids'] as $id ) {
					$business_to_group[ $id ] = $found_group;
				}
			} else {
				$group['methods']              = array( $group['method'] );
				$merged_groups[ $group_index ] = $group;

				foreach ( $group['business_ids'] as $id ) {
					$business_to_group[ $id ] = $group_index;
				}

				++$group_index;
			}
		}

		return array_values( $merged_groups );
	}

	/**
	 * Normalize a business title for comparison.
	 *
	 * @param string $title Original title.
	 * @return string Normalized title.
	 */
	public static function normalize_title( $title ) {
		$normalized = strtolower( $title );

		$suffixes = array( 'inc', 'llc', 'ltd', 'corp', 'co', 'company', 'incorporated' );
		foreach ( $suffixes as $suffix ) {
			$normalized = preg_replace( '/\b' . preg_quote( $suffix, '/' ) . '\.?\b/i', '', $normalized );
		}

		$normalized = preg_replace( '/[^\w\s]/', '', $normalized );
		$normalized = preg_replace( '/\s+/', ' ', $normalized );
		$normalized = trim( $normalized );

		return $normalized;
	}

	/**
	 * Get detailed information about businesses in a duplicate group.
	 * Optimized to batch load data and minimize queries.
	 *
	 * @param array $business_ids Array of business post IDs.
	 * @return array Detailed business information.
	 */
	public static function get_group_details( $business_ids ) {
		global $wpdb;

		if ( empty( $business_ids ) ) {
			return array();
		}

		$business_ids = array_map( 'absint', $business_ids );
		$business_ids = array_filter( $business_ids );

		if ( empty( $business_ids ) ) {
			return array();
		}

		// Prime the post cache (single query for all posts).
		_prime_post_caches( $business_ids, true, true );

		// Prime meta cache.
		update_meta_cache( 'post', $business_ids );

		// Prime term cache.
		update_object_term_cache( $business_ids, 'bd_business' );

		// Batch load locations.
		$locations_table = $wpdb->prefix . 'bd_locations';
		$locations_cache = array();

		if ( self::table_exists( $locations_table ) ) {
			$ids_placeholder = implode( ',', array_fill( 0, count( $business_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$locations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT business_id, address, city FROM {$locations_table} WHERE business_id IN ({$ids_placeholder})",
					...$business_ids
				),
				ARRAY_A
			);
			foreach ( $locations ?? array() as $loc ) {
				$locations_cache[ $loc['business_id'] ] = $loc;
			}
		}

		// Batch load review stats.
		$reviews_table = $wpdb->prefix . 'bd_reviews';
		$reviews_cache = array();

		if ( self::table_exists( $reviews_table ) ) {
			$ids_placeholder = implode( ',', array_fill( 0, count( $business_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$reviews = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT business_id, COUNT(*) as count, AVG(rating) as avg_rating
					FROM {$reviews_table}
					WHERE business_id IN ({$ids_placeholder}) AND status = 'approved'
					GROUP BY business_id",
					...$business_ids
				),
				ARRAY_A
			);
			foreach ( $reviews ?? array() as $review ) {
				$reviews_cache[ $review['business_id'] ] = $review;
			}
		}

		$details = array();

		foreach ( $business_ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$location = $locations_cache[ $id ] ?? null;
			$phone    = get_post_meta( $id, 'bd_phone', true );
			$website  = get_post_meta( $id, 'bd_website', true );
			$email    = get_post_meta( $id, 'bd_email', true );
			// Use get_the_terms() to leverage the primed object cache.
			$cat_terms    = get_the_terms( $id, 'bd_category' );
			$categories   = ( $cat_terms && ! is_wp_error( $cat_terms ) ) ? wp_list_pluck( $cat_terms, 'name' ) : array();
			$review_stats = $reviews_cache[ $id ] ?? array(
				'count'      => 0,
				'avg_rating' => 0,
			);
			$claimed_by   = get_post_meta( $id, 'bd_claimed_by', true );

			$details[] = array(
				'id'            => $id,
				'title'         => $post->post_title,
				'status'        => $post->post_status,
				'date_created'  => $post->post_date,
				'date_modified' => $post->post_modified,
				'address'       => $location['address'] ?? '',
				'city'          => $location['city'] ?? '',
				'phone'         => $phone,
				'website'       => $website,
				'email'         => $email,
				'categories'    => $categories,
				'review_count'  => absint( $review_stats['count'] ?? 0 ),
				'avg_rating'    => round( floatval( $review_stats['avg_rating'] ?? 0 ), 1 ),
				'is_claimed'    => ! empty( $claimed_by ),
				'claimed_by'    => $claimed_by ? absint( $claimed_by ) : null,
				'thumbnail'     => get_the_post_thumbnail_url( $id, 'thumbnail' ),
				'edit_link'     => get_edit_post_link( $id, 'raw' ),
				'view_link'     => get_permalink( $id ),
			);
		}

		return $details;
	}

	/**
	 * Get summary statistics about duplicates.
	 *
	 * @param array|null $duplicates Pre-fetched duplicates array, or null to fetch.
	 * @return array Statistics.
	 */
	public static function get_statistics( $duplicates = null ) {
		if ( null === $duplicates ) {
			$duplicates = self::find_duplicates();
		}

		$stats = array(
			'total_groups'     => count( $duplicates ),
			'total_duplicates' => 0,
			'by_method'        => array(),
			'by_confidence'    => array(
				self::CONFIDENCE_HIGH   => 0,
				self::CONFIDENCE_MEDIUM => 0,
				self::CONFIDENCE_LOW    => 0,
			),
		);

		foreach ( $duplicates as $group ) {
			$stats['total_duplicates'] += $group['count'];

			$method = $group['method'];
			if ( ! isset( $stats['by_method'][ $method ] ) ) {
				$stats['by_method'][ $method ] = 0;
			}
			++$stats['by_method'][ $method ];

			$confidence = $group['confidence'] ?? self::CONFIDENCE_MEDIUM;
			if ( isset( $stats['by_confidence'][ $confidence ] ) ) {
				++$stats['by_confidence'][ $confidence ];
			}
		}

		return $stats;
	}

	/**
	 * Get method display name.
	 *
	 * @param string $method Method constant.
	 * @return string Display name.
	 */
	public static function get_method_label( $method ) {
		$labels = array(
			self::METHOD_EXACT_TITLE      => __( 'Exact Title Match', 'business-directory' ),
			self::METHOD_NORMALIZED_TITLE => __( 'Similar Title', 'business-directory' ),
			self::METHOD_TITLE_CITY       => __( 'Title + City', 'business-directory' ),
			self::METHOD_TITLE_ADDRESS    => __( 'Title + Address', 'business-directory' ),
			self::METHOD_PHONE            => __( 'Phone Number', 'business-directory' ),
			self::METHOD_WEBSITE          => __( 'Website', 'business-directory' ),
		);

		return $labels[ $method ] ?? $method;
	}

	/**
	 * Get confidence display info.
	 *
	 * @param string $confidence Confidence constant.
	 * @return array Display info with label and color.
	 */
	public static function get_confidence_info( $confidence ) {
		$info = array(
			self::CONFIDENCE_HIGH   => array(
				'label' => __( 'High', 'business-directory' ),
				'color' => '#dc2626',
				'icon'  => '🔴',
			),
			self::CONFIDENCE_MEDIUM => array(
				'label' => __( 'Medium', 'business-directory' ),
				'color' => '#f59e0b',
				'icon'  => '🟡',
			),
			self::CONFIDENCE_LOW    => array(
				'label' => __( 'Low', 'business-directory' ),
				'color' => '#10b981',
				'icon'  => '🟢',
			),
		);

		return $info[ $confidence ] ?? $info[ self::CONFIDENCE_MEDIUM ];
	}
}
