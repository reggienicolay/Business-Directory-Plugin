<?php
/**
 * Network Lists Shortcode
 *
 * Displays lists from the main site on network subsites via REST API.
 * Supports city filtering and smart caching.
 *
 * Usage: [bd_network_lists city="Livermore" source="lovetrivalley.com" limit="6"]
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NetworkLists {

	/**
	 * Default cache duration in seconds (1 hour)
	 */
	const DEFAULT_CACHE_DURATION = 3600;

	/**
	 * Initialize shortcode
	 */
	public static function init() {
		add_shortcode( 'bd_network_lists', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register assets
	 */
	public static function register_assets() {
		wp_register_style(
			'bd-network-lists',
			plugins_url( 'assets/css/network-lists.css', dirname( __DIR__ ) ),
			array(),
			BD_VERSION
		);
	}

	/**
	 * Render the shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'city'          => '',           // Required: City to filter by
				'source'        => '',           // Source site domain (e.g., lovetrivalley.com)
				'limit'         => 6,            // Number of lists to show
				'columns'       => 3,            // Grid columns
				'layout'        => 'grid',       // grid, list, compact
				'show_covers'   => 'yes',        // Show cover images
				'show_author'   => 'yes',        // Show author name
				'show_count'    => 'yes',        // Show item count
				'has_cover'     => '',           // Filter: only with covers (yes/no)
				'min_items'     => 3,            // Minimum items in list
				'orderby'       => 'updated_at', // Sort order
				'cache'         => 60,           // Cache duration in minutes
				'title'         => '',           // Section title
				'view_all_url'  => '',           // URL for "View All" link
				'class'         => '',           // Additional CSS class
			),
			$atts,
			'bd_network_lists'
		);

		// Validate city
		if ( empty( $atts['city'] ) && empty( $atts['source'] ) ) {
			// If no city and local, just use local lists
			return self::render_local_lists( $atts );
		}

		// Determine API URL
		$api_url = self::get_api_url( $atts['source'] );

		// Build cache key
		$cache_key = self::get_cache_key( $atts );

		// Check cache
		$cached = get_transient( $cache_key );
		if ( false !== $cached && ! empty( $atts['cache'] ) ) {
			wp_enqueue_style( 'bd-network-lists' );
			return $cached;
		}

		// Fetch lists from API
		$lists = self::fetch_lists( $api_url, $atts );

		if ( is_wp_error( $lists ) ) {
			return self::render_error( $lists->get_error_message() );
		}

		if ( empty( $lists ) ) {
			return self::render_empty( $atts['city'] );
		}

		// Render HTML
		$html = self::render_lists( $lists, $atts );

		// Cache the result
		if ( ! empty( $atts['cache'] ) ) {
			$cache_duration = absint( $atts['cache'] ) * MINUTE_IN_SECONDS;
			set_transient( $cache_key, $html, $cache_duration );
		}

		wp_enqueue_style( 'bd-network-lists' );

		return $html;
	}

	/**
	 * Get API URL for source site
	 *
	 * @param string $source Source domain.
	 * @return string
	 */
	private static function get_api_url( $source ) {
		if ( empty( $source ) ) {
			return rest_url( 'bd/v1/' );
		}

		// Ensure proper URL format
		$source = preg_replace( '#^https?://#', '', $source );
		$source = rtrim( $source, '/' );

		return 'https://' . $source . '/wp-json/bd/v1/';
	}

	/**
	 * Generate cache key
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	private static function get_cache_key( $atts ) {
		$key_parts = array(
			'bd_network_lists',
			$atts['source'],
			$atts['city'],
			$atts['limit'],
			$atts['has_cover'],
			$atts['min_items'],
			$atts['orderby'],
		);

		return 'bd_nl_' . md5( implode( '_', $key_parts ) );
	}

	/**
	 * Fetch lists from API
	 *
	 * @param string $api_url Base API URL.
	 * @param array  $atts    Shortcode attributes.
	 * @return array|WP_Error
	 */
	private static function fetch_lists( $api_url, $atts ) {
		$endpoint = $api_url . 'lists';

		$params = array(
			'per_page'  => absint( $atts['limit'] ),
			'orderby'   => sanitize_text_field( $atts['orderby'] ),
			'min_items' => absint( $atts['min_items'] ),
		);

		// City filter
		if ( ! empty( $atts['city'] ) ) {
			$params['city'] = sanitize_text_field( $atts['city'] );
		}

		// Cover filter
		if ( 'yes' === $atts['has_cover'] ) {
			$params['has_cover'] = 'true';
		}

		$url = add_query_arg( $params, $endpoint );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'limit_response_size' => 512000, // 500KB max to prevent DoS
				'headers'             => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error( 'api_error', 'Failed to fetch lists (HTTP ' . $code . ')' );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['lists'] ) ) {
			return new \WP_Error( 'invalid_response', 'Invalid API response' );
		}

		return $data['lists'];
	}

	/**
	 * Render lists HTML
	 *
	 * @param array $lists List data.
	 * @param array $atts  Shortcode attributes.
	 * @return string
	 */
	private static function render_lists( $lists, $atts ) {
		$layout_class  = 'bd-nl-layout-' . sanitize_html_class( $atts['layout'] );
		$columns_class = 'bd-nl-cols-' . absint( $atts['columns'] );
		$extra_class   = sanitize_html_class( $atts['class'] );

		ob_start();
		?>
		<div class="bd-network-lists <?php echo esc_attr( "$layout_class $columns_class $extra_class" ); ?>">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<div class="bd-nl-header">
					<h2 class="bd-nl-title"><?php echo esc_html( $atts['title'] ); ?></h2>
					<?php if ( ! empty( $atts['view_all_url'] ) ) : ?>
						<a href="<?php echo esc_url( $atts['view_all_url'] ); ?>" class="bd-nl-view-all">
							View All <i class="fas fa-arrow-right"></i>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="bd-nl-grid">
				<?php foreach ( $lists as $list ) : ?>
					<?php echo self::render_list_card( $list, $atts ); ?>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $atts['title'] ) && ! empty( $atts['view_all_url'] ) ) : ?>
				<div class="bd-nl-footer">
					<a href="<?php echo esc_url( $atts['view_all_url'] ); ?>" class="bd-btn bd-btn-secondary">
						View All Lists <i class="fas fa-arrow-right"></i>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render individual list card
	 *
	 * @param array $list List data.
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	private static function render_list_card( $list, $atts ) {
		$url = $list['url'] ?? '#';

		// Handle cross-site URLs
		if ( ! empty( $atts['source'] ) && strpos( $url, 'http' ) !== 0 ) {
			$url = 'https://' . rtrim( $atts['source'], '/' ) . $url;
		}

		$cover_image = $list['cover_image'] ?? null;
		$item_count  = $list['item_count'] ?? 0;
		$author_name = $list['author_name'] ?? '';

		ob_start();
		?>
		<div class="bd-nl-card">
			<a href="<?php echo esc_url( $url ); ?>" class="bd-nl-card-link">
				<?php if ( 'yes' === $atts['show_covers'] ) : ?>
					<div class="bd-nl-card-cover">
						<?php if ( $cover_image ) : ?>
							<img src="<?php echo esc_url( $cover_image ); ?>" 
								 alt="<?php echo esc_attr( $list['title'] ); ?>"
								 loading="lazy">
						<?php else : ?>
							<div class="bd-nl-card-placeholder">
								<i class="fas fa-list"></i>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $list['cover_type'] ) && in_array( $list['cover_type'], array( 'youtube', 'vimeo' ), true ) ) : ?>
							<div class="bd-nl-play-overlay">
								<i class="fas fa-play-circle"></i>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="bd-nl-card-body">
					<h3 class="bd-nl-card-title"><?php echo esc_html( $list['title'] ); ?></h3>

					<div class="bd-nl-card-meta">
						<?php if ( 'yes' === $atts['show_count'] && $item_count > 0 ) : ?>
							<span class="bd-nl-item-count">
								<i class="fas fa-map-marker-alt"></i>
								<?php echo esc_html( $item_count ); ?> places
							</span>
						<?php endif; ?>

						<?php if ( 'yes' === $atts['show_author'] && $author_name ) : ?>
							<span class="bd-nl-author">
								<i class="fas fa-user"></i>
								<?php echo esc_html( $author_name ); ?>
							</span>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $list['cached_city'] ) ) : ?>
						<span class="bd-nl-city">
							<i class="fas fa-city"></i>
							<?php echo esc_html( $list['cached_city'] ); ?>
						</span>
					<?php endif; ?>
				</div>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render local lists (when no source specified)
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	private static function render_local_lists( $atts ) {
		if ( ! class_exists( 'BD\Lists\ListManager' ) ) {
			return self::render_error( 'Lists feature not available' );
		}

		$lists = \BD\Lists\ListManager::get_public_lists(
			array(
				'per_page'  => absint( $atts['limit'] ),
				'city'      => $atts['city'],
				'has_cover' => 'yes' === $atts['has_cover'] ? true : null,
				'min_items' => absint( $atts['min_items'] ),
				'orderby'   => $atts['orderby'],
			)
		);

		if ( empty( $lists['lists'] ) ) {
			return self::render_empty( $atts['city'] );
		}

		wp_enqueue_style( 'bd-network-lists' );

		return self::render_lists( $lists['lists'], $atts );
	}

	/**
	 * Render error message
	 *
	 * @param string $message Error message.
	 * @return string
	 */
	private static function render_error( $message ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<div class="bd-nl-error"><p><strong>Network Lists Error:</strong> ' . esc_html( $message ) . '</p></div>';
		}
		return ''; // Hide errors from non-admins
	}

	/**
	 * Render empty state
	 *
	 * @param string $city City name.
	 * @return string
	 */
	private static function render_empty( $city ) {
		ob_start();
		?>
		<div class="bd-network-lists bd-nl-empty">
			<div class="bd-nl-empty-content">
				<i class="fas fa-list"></i>
				<p>No lists found<?php echo $city ? ' for ' . esc_html( $city ) : ''; ?>.</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Clear cache for a specific city
	 *
	 * @param string $city City name.
	 */
	public static function clear_cache( $city = '' ) {
		global $wpdb;

		// Delete all network lists transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_bd_nl_' ) . '%'
			)
		);
	}
}

// Initialize
NetworkLists::init();
