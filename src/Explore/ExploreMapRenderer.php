<?php
/**
 * Explore Map Renderer
 *
 * Renders the Leaflet map container and marker data JSON block
 * for explore pages. The companion explore-map.js reads the
 * JSON data and initializes the interactive map with heart
 * pin markers and cluster rollups.
 *
 * @package    BD
 * @subpackage Explore
 * @since      2.3.0
 */

namespace BusinessDirectory\Explore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExploreMapRenderer
 */
class ExploreMapRenderer {

	/**
	 * Render an interactive Leaflet map with markers for businesses.
	 *
	 * Outputs a map container and an inline JSON script with marker data.
	 * The explore-map.js script reads this data and initializes the map.
	 *
	 * @param array $businesses Array of formatted business data from ExploreQuery.
	 * @return string Map HTML (container + JSON data block), or empty if no valid pins.
	 */
	public static function render_map( $businesses ) {
		$pins = self::extract_pins( $businesses );

		if ( empty( $pins ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="bd-explore-map-wrap">
			<div id="bd-explore-map" class="bd-explore-map"></div>
			<script type="application/json" id="bd-explore-map-data"><?php echo wp_json_encode( $pins ); ?></script>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Extract map pin data from businesses.
	 *
	 * Filters to businesses with valid lat/lng and formats the
	 * data structure that explore-map.js expects.
	 *
	 * @param array $businesses Array of formatted business data.
	 * @return array Array of pin data arrays.
	 */
	private static function extract_pins( $businesses ) {
		$pins = array();

		foreach ( $businesses as $biz ) {
			if ( empty( $biz['location'] ) ) {
				continue;
			}

			$lat = floatval( $biz['location']['lat'] ?? 0 );
			$lng = floatval( $biz['location']['lng'] ?? 0 );

			if ( 0.0 === $lat && 0.0 === $lng ) {
				continue;
			}

			$address_parts = array_filter(
				array(
					$biz['location']['address'] ?? '',
					$biz['location']['city'] ?? '',
				)
			);

			$pins[] = array(
				'lat'     => $lat,
				'lng'     => $lng,
				'title'   => $biz['title'],
				'url'     => $biz['permalink'],
				'image'   => $biz['featured_image'] ?? '',
				'rating'  => $biz['rating'] ?? 0,
				'address' => implode( ', ', $address_parts ),
			);
		}

		return $pins;
	}
}
