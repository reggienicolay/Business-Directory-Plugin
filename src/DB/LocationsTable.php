<?php
namespace BD\DB;

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

		if ( empty( $data['business_id'] ) || empty( $data['lat'] ) || empty( $data['lng'] ) ) {
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

	public static function get( $business_id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE business_id = %d',
			absint( $business_id )
		);

		return $wpdb->get_row( $sql, ARRAY_A );
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
