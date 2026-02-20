<?php
/**
 * Explore Sitemap Provider for WP Core Sitemaps
 *
 * @package    BusinessDirectory
 * @subpackage Explore
 * @since      2.2.0
 */

namespace BusinessDirectory\Explore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ExploreSitemapProvider
 *
 * WP Core Sitemaps provider for explore pages.
 */
class ExploreSitemapProvider extends \WP_Sitemaps_Provider {

	/**
	 * Provider name.
	 *
	 * @var string
	 */
	protected $name = 'explore';

	/**
	 * Object type.
	 *
	 * @var string
	 */
	protected $object_type = 'explore';

	/**
	 * Get URL list for a sitemap page.
	 *
	 * @param int    $page_num       Page number.
	 * @param string $object_subtype Not used.
	 * @return array Array of sitemap entry arrays.
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$all_urls = ExploreSitemap::get_explore_urls();

		$offset = ( $page_num - 1 ) * 2000;
		$urls   = array_slice( $all_urls, $offset, 2000 );

		$entries = array();
		foreach ( $urls as $url_data ) {
			$entry = array( 'loc' => $url_data['loc'] );
			if ( ! empty( $url_data['lastmod'] ) ) {
				$entry['lastmod'] = $url_data['lastmod'];
			}
			$entries[] = $entry;
		}

		return $entries;
	}

	/**
	 * Get max number of pages.
	 *
	 * @param string $object_subtype Not used.
	 * @return int Max pages.
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		$all_urls = ExploreSitemap::get_explore_urls();
		return max( 1, (int) ceil( count( $all_urls ) / 2000 ) );
	}
}
