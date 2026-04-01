<?php

namespace BD\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LocationsTable {

	private static $table_name = null;

	private static function table() {
		global $wpdb;
		if ( self::$table_name === null ) {
			self::$table_name = $wpdb->prefix . 'bd_locations';
		}
		return self::$table_name;
	}

	public static function insert( $data ) {
		global $wpdb;

		if ( empty( $data['business_id'] ) || ! isset( $data['lat'] ) || ! isset( $data['lng'] ) ) {
			return new \WP_Error( 'missing_fields', 'Business ID, latitude, and longitude are required' );
		}

		$clean_data = array(
			'business_id' => absint( $data['business_id'] ),
			'lat'         => floatval( $data['lat'] ),
			'lng'         => floatval( $data['lng'] ),
			'address'     => isset( $data['address'] ) ? sanitize_text_field( $data['address'] ) : null,
			'city'        => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : null,
		);

		$result = $wpdb->insert(
			self::table(),
			$clean_data,
			array( '%d', '%f', '%f', '%s', '%s' )
		);

		return $result !== false;
	}

	/**
	 * Batch-load locations for multiple business IDs in a single query.
	 * Results are cached in a static property so subsequent get() calls avoid extra queries.
	 *
	 * @param array $business_ids Array of business post IDs.
	 */
	public static function batch_load( $business_ids ) {
		global $wpdb;

		if ( empty( $business_ids ) ) {
			return;
		}

		$ids_clean    = array_map( 'absint', $business_ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids_clean ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . " WHERE business_id IN ($placeholders)",
				...$ids_clean
			),
			ARRAY_A
		);

		if ( $rows ) {
			foreach ( $rows as $row ) {
				self::$cache[ (int) $row['business_id'] ] = $row;
			}
		}
	}

	/**
	 * In-memory cache populated by batch_load().
	 *
	 * @var array
	 */
	private static $cache = array();

	public static function get( $business_id ) {
		$id = absint( $business_id );

		// Return from batch cache if available.
		if ( isset( self::$cache[ $id ] ) ) {
			return self::$cache[ $id ];
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE business_id = %d',
				$id
			),
			ARRAY_A
		);

		// Cache for subsequent calls in the same request.
		if ( $row ) {
			self::$cache[ $id ] = $row;
		}

		return $row;
	}

	public static function update( $business_id, $data ) {
		global $wpdb;

		$clean_data = array();
		$formats    = array();

		if ( isset( $data['lat'] ) ) {
			$clean_data['lat'] = floatval( $data['lat'] );
			$formats[]         = '%f';
		}
		if ( isset( $data['lng'] ) ) {
			$clean_data['lng'] = floatval( $data['lng'] );
			$formats[]         = '%f';
		}

		if ( empty( $clean_data ) ) {
			return false;
		}

		return $wpdb->update(
			self::table(),
			$clean_data,
			array( 'business_id' => absint( $business_id ) ),
			$formats,
			array( '%d' )
		);
	}

	public static function delete( $business_id ) {
		global $wpdb;

		return $wpdb->delete(
			self::table(),
			array( 'business_id' => absint( $business_id ) ),
			array( '%d' )
		);
	}
}
