<?php
/**
 * Featured Businesses Admin
 *
 * Allows admins to select and order featured businesses
 * that appear first in directory results.
 *
 * @package BusinessDirectory
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeaturedAdmin {

	/**
	 * Option name for storing featured business IDs.
	 */
	const OPTION_NAME = 'bd_featured_businesses';

	/**
	 * Maximum number of featured businesses.
	 */
	const MAX_FEATURED = 20;

	/**
	 * Cache duration in seconds (1 hour).
	 */
	const CACHE_DURATION = 3600;

	/**
	 * Whether init has been called.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize the admin page.
	 */
	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bd_featured_search', array( __CLASS__, 'ajax_search_businesses' ) );
		add_action( 'wp_ajax_bd_featured_add', array( __CLASS__, 'ajax_add_business' ) );
		add_action( 'wp_ajax_bd_featured_remove', array( __CLASS__, 'ajax_remove_business' ) );
		add_action( 'wp_ajax_bd_featured_reorder', array( __CLASS__, 'ajax_reorder_businesses' ) );
	}

	/**
	 * Add submenu page under Directory.
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Featured Businesses', 'business-directory' ),
			__( 'Featured', 'business-directory' ),
			'manage_options',
			'bd-featured',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'bd_business_page_bd-featured' !== $hook ) {
			return;
		}

		// jQuery UI Sortable (bundled with WordPress).
		wp_enqueue_script( 'jquery-ui-sortable' );

		// Our admin styles.
		wp_enqueue_style(
			'bd-admin-featured',
			BD_PLUGIN_URL . 'assets/css/admin-featured.css',
			array(),
			BD_VERSION
		);

		// Our admin script.
		wp_enqueue_script(
			'bd-admin-featured',
			BD_PLUGIN_URL . 'assets/js/admin-featured.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			BD_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'bd-admin-featured',
			'bdFeatured',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bd_featured_nonce' ),
				'maxFeatured' => self::MAX_FEATURED,
				'strings'     => array(
					'confirmRemove'     => __( 'Remove this business from featured?', 'business-directory' ),
					'maxReached'        => sprintf(
						/* translators: %d: maximum number of featured businesses */
						__( 'Maximum of %d featured businesses reached.', 'business-directory' ),
						self::MAX_FEATURED
					),
					'searchPlaceholder' => __( 'Search businesses...', 'business-directory' ),
					'noResults'         => __( 'No businesses found', 'business-directory' ),
					'adding'            => __( 'Adding...', 'business-directory' ),
					'removing'          => __( 'Removing...', 'business-directory' ),
					'saved'             => __( 'Order saved!', 'business-directory' ),
					'error'             => __( 'An error occurred. Please try again.', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Render the admin page.
	 */
	public static function render_page() {
		$featured_ids = self::get_featured_ids();
		$businesses   = self::get_featured_businesses( $featured_ids );
		?>
		<div class="wrap bd-featured-wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-star-filled" style="color: #C9A227;"></span>
				<?php esc_html_e( 'Featured Businesses', 'business-directory' ); ?>
			</h1>

			<div class="bd-featured-description">
				<p>
					<?php esc_html_e( 'Featured businesses appear first in directory results when "Featured First" sort is active.', 'business-directory' ); ?>
					<br>
					<strong><?php esc_html_e( 'Drag to reorder.', 'business-directory' ); ?></strong>
					<?php
					printf(
						/* translators: %d: maximum number of featured businesses */
						esc_html__( 'Maximum %d businesses.', 'business-directory' ),
						self::MAX_FEATURED
					);
					?>
				</p>
			</div>

			<!-- Featured List -->
			<div class="bd-featured-container">
				<div class="bd-featured-header">
					<span class="bd-featured-count">
						<?php
						printf(
							/* translators: %1$d: current count, %2$d: maximum */
							esc_html__( '%1$d of %2$d featured', 'business-directory' ),
							count( $featured_ids ),
							self::MAX_FEATURED
						);
						?>
					</span>
				</div>

				<ul id="bd-featured-list" class="bd-featured-list">
					<?php if ( empty( $businesses ) ) : ?>
						<li class="bd-featured-empty">
							<span class="dashicons dashicons-info-outline"></span>
							<?php esc_html_e( 'No featured businesses yet. Click "Add Business" to get started.', 'business-directory' ); ?>
						</li>
					<?php else : ?>
						<?php foreach ( $businesses as $index => $business ) : ?>
							<li class="bd-featured-item" data-id="<?php echo esc_attr( $business['id'] ); ?>">
								<span class="bd-featured-handle" title="<?php esc_attr_e( 'Drag to reorder', 'business-directory' ); ?>">
									<span class="dashicons dashicons-menu"></span>
								</span>
								<span class="bd-featured-position"><?php echo esc_html( $index + 1 ); ?></span>
								<div class="bd-featured-thumb">
									<?php if ( $business['thumbnail'] ) : ?>
										<img src="<?php echo esc_url( $business['thumbnail'] ); ?>" alt="">
									<?php else : ?>
										<span class="dashicons dashicons-store"></span>
									<?php endif; ?>
								</div>
								<div class="bd-featured-info">
									<strong class="bd-featured-title">
										<a href="<?php echo esc_url( get_edit_post_link( $business['id'] ) ); ?>" target="_blank">
											<?php echo esc_html( $business['title'] ); ?>
										</a>
									</strong>
									<span class="bd-featured-meta">
										<?php echo esc_html( $business['city'] ); ?>
										<?php if ( $business['category'] ) : ?>
											&bull; <?php echo esc_html( $business['category'] ); ?>
										<?php endif; ?>
									</span>
								</div>
								<button type="button" class="bd-featured-remove" data-id="<?php echo esc_attr( $business['id'] ); ?>" title="<?php esc_attr_e( 'Remove from featured', 'business-directory' ); ?>">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>

				<div class="bd-featured-actions">
					<button type="button" id="bd-add-featured" class="button button-primary" <?php echo count( $featured_ids ) >= self::MAX_FEATURED ? 'disabled' : ''; ?>>
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Business', 'business-directory' ); ?>
					</button>
				</div>
			</div>

			<!-- Add Business Modal -->
			<div id="bd-featured-modal" class="bd-featured-modal" style="display: none;">
				<div class="bd-featured-modal-content">
					<div class="bd-featured-modal-header">
						<h2><?php esc_html_e( 'Add Featured Business', 'business-directory' ); ?></h2>
						<button type="button" class="bd-featured-modal-close">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="bd-featured-modal-body">
						<div class="bd-featured-search-wrap">
							<span class="dashicons dashicons-search"></span>
							<input type="text" id="bd-featured-search" placeholder="<?php esc_attr_e( 'Search businesses...', 'business-directory' ); ?>" autocomplete="off">
						</div>
						<div id="bd-featured-results" class="bd-featured-results">
							<p class="bd-featured-results-hint">
								<?php esc_html_e( 'Type to search for businesses', 'business-directory' ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>

			<!-- Toast notification -->
			<div id="bd-featured-toast" class="bd-featured-toast" style="display: none;"></div>
		</div>
		<?php
	}

	/**
	 * Get featured business IDs.
	 *
	 * @return array Ordered array of business post IDs.
	 */
	public static function get_featured_ids() {
		$cached = get_transient( 'bd_featured_ids' );
		if ( false !== $cached ) {
			return $cached;
		}

		$ids = get_option( self::OPTION_NAME, array() );
		$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();

		// Validate that all IDs still exist and are published.
		$valid_ids = array();
		foreach ( $ids as $id ) {
			if ( get_post_status( $id ) === 'publish' && get_post_type( $id ) === 'bd_business' ) {
				$valid_ids[] = $id;
			}
		}

		// Update if any were removed.
		if ( count( $valid_ids ) !== count( $ids ) ) {
			update_option( self::OPTION_NAME, $valid_ids );
		}

		set_transient( 'bd_featured_ids', $valid_ids, self::CACHE_DURATION );

		return $valid_ids;
	}

	/**
	 * Get featured business data for display.
	 *
	 * @param array $ids Array of business IDs.
	 * @return array Business data.
	 */
	private static function get_featured_businesses( $ids ) {
		if ( empty( $ids ) ) {
			return array();
		}

		$businesses = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$location = get_post_meta( $id, 'bd_location', true );
			$city     = is_array( $location ) && ! empty( $location['city'] ) ? $location['city'] : '';

			$categories = wp_get_post_terms( $id, 'bd_category', array( 'fields' => 'names' ) );
			$category   = ! is_wp_error( $categories ) && ! empty( $categories ) ? $categories[0] : '';

			$businesses[] = array(
				'id'        => $id,
				'title'     => $post->post_title,
				'city'      => $city,
				'category'  => $category,
				'thumbnail' => get_the_post_thumbnail_url( $id, 'thumbnail' ),
			);
		}

		return $businesses;
	}

	/**
	 * Check if a business is featured.
	 *
	 * @param int $business_id Business post ID.
	 * @return bool True if featured.
	 */
	public static function is_featured( $business_id ) {
		$featured_ids = self::get_featured_ids();
		return in_array( absint( $business_id ), $featured_ids, true );
	}

	/**
	 * Clear the featured cache.
	 */
	private static function clear_cache() {
		delete_transient( 'bd_featured_ids' );
	}

	/**
	 * AJAX: Search businesses for add modal.
	 */
	public static function ajax_search_businesses() {
		check_ajax_referer( 'bd_featured_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'business-directory' ) ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'businesses' => array() ) );
		}

		$featured_ids = self::get_featured_ids();

		$args = array(
			'post_type'      => 'bd_business',
			'post_status'    => 'publish',
			's'              => $search,
			'posts_per_page' => 10,
			'post__not_in'   => $featured_ids, // Exclude already featured.
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );

		$businesses = array();
		foreach ( $query->posts as $post ) {
			$location = get_post_meta( $post->ID, 'bd_location', true );
			$city     = is_array( $location ) && ! empty( $location['city'] ) ? $location['city'] : '';

			$businesses[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'city'      => $city,
				'thumbnail' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ),
			);
		}

		wp_send_json_success( array( 'businesses' => $businesses ) );
	}

	/**
	 * AJAX: Add business to featured list.
	 */
	public static function ajax_add_business() {
		check_ajax_referer( 'bd_featured_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'business-directory' ) ) );
		}

		$business_id = isset( $_POST['business_id'] ) ? absint( $_POST['business_id'] ) : 0;

		if ( ! $business_id || get_post_type( $business_id ) !== 'bd_business' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid business.', 'business-directory' ) ) );
		}

		$featured_ids = self::get_featured_ids();

		// Check max limit.
		if ( count( $featured_ids ) >= self::MAX_FEATURED ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: maximum number */
						__( 'Maximum of %d featured businesses reached.', 'business-directory' ),
						self::MAX_FEATURED
					),
				)
			);
		}

		// Check if already featured.
		if ( in_array( $business_id, $featured_ids, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Business is already featured.', 'business-directory' ) ) );
		}

		// Add to end of list.
		$featured_ids[] = $business_id;
		update_option( self::OPTION_NAME, $featured_ids );
		self::clear_cache();

		// Get business data for response.
		$post     = get_post( $business_id );
		$location = get_post_meta( $business_id, 'bd_location', true );
		$city     = is_array( $location ) && ! empty( $location['city'] ) ? $location['city'] : '';

		$categories = wp_get_post_terms( $business_id, 'bd_category', array( 'fields' => 'names' ) );
		$category   = ! is_wp_error( $categories ) && ! empty( $categories ) ? $categories[0] : '';

		$business = array(
			'id'        => $business_id,
			'title'     => $post->post_title,
			'city'      => $city,
			'category'  => $category,
			'thumbnail' => get_the_post_thumbnail_url( $business_id, 'thumbnail' ),
			'editUrl'   => get_edit_post_link( $business_id, 'raw' ),
		);

		do_action( 'bd_featured_updated', $featured_ids );

		wp_send_json_success(
			array(
				'message'  => __( 'Business added to featured!', 'business-directory' ),
				'business' => $business,
				'count'    => count( $featured_ids ),
			)
		);
	}

	/**
	 * AJAX: Remove business from featured list.
	 */
	public static function ajax_remove_business() {
		check_ajax_referer( 'bd_featured_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'business-directory' ) ) );
		}

		$business_id = isset( $_POST['business_id'] ) ? absint( $_POST['business_id'] ) : 0;

		if ( ! $business_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid business.', 'business-directory' ) ) );
		}

		$featured_ids = self::get_featured_ids();
		$key          = array_search( $business_id, $featured_ids, true );

		if ( false === $key ) {
			wp_send_json_error( array( 'message' => __( 'Business is not featured.', 'business-directory' ) ) );
		}

		// Remove from array.
		unset( $featured_ids[ $key ] );
		$featured_ids = array_values( $featured_ids ); // Re-index.

		update_option( self::OPTION_NAME, $featured_ids );
		self::clear_cache();

		do_action( 'bd_featured_updated', $featured_ids );

		wp_send_json_success(
			array(
				'message' => __( 'Business removed from featured.', 'business-directory' ),
				'count'   => count( $featured_ids ),
			)
		);
	}

	/**
	 * AJAX: Reorder featured businesses.
	 */
	public static function ajax_reorder_businesses() {
		check_ajax_referer( 'bd_featured_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'business-directory' ) ) );
		}

		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();

		if ( empty( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'business-directory' ) ) );
		}

		// Validate all IDs are valid businesses.
		$valid_order = array();
		foreach ( $order as $id ) {
			if ( get_post_type( $id ) === 'bd_business' && get_post_status( $id ) === 'publish' ) {
				$valid_order[] = $id;
			}
		}

		update_option( self::OPTION_NAME, $valid_order );
		self::clear_cache();

		do_action( 'bd_featured_updated', $valid_order );

		wp_send_json_success(
			array(
				'message' => __( 'Order saved!', 'business-directory' ),
			)
		);
	}
}

// Initialize.
FeaturedAdmin::init();
