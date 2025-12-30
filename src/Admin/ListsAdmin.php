<?php
/**
 * Lists Admin
 *
 * Admin page for managing user-created lists.
 * Enhanced with filters, bulk actions, and better display.
 *
 * @package BusinessDirectory
 */

namespace BD\Admin;

use BD\Lists\ListManager;

/**
 * Class ListsAdmin
 */
class ListsAdmin {

	/**
	 * Initialize admin page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bd_admin_list_action', array( __CLASS__, 'handle_ajax_action' ) );
		add_action( 'wp_ajax_bd_admin_bulk_action', array( __CLASS__, 'handle_bulk_action' ) );
	}

	/**
	 * Add menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'User Lists', 'developer-developer-developer' ),
			__( 'User Lists', 'developer-developer-developer' ),
			'manage_options',
			'bd-user-lists',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'bd_business_page_bd-user-lists' !== $hook ) {
			return;
		}

		// Font Awesome for icons.
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);

		wp_enqueue_style(
			'bd-lists-admin',
			plugins_url( 'assets/css/admin-lists.css', dirname( __DIR__ ) ),
			array( 'font-awesome' ),
			'1.1.0'
		);

		wp_enqueue_script(
			'bd-lists-admin',
			plugins_url( 'assets/js/admin-lists.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			'1.1.0',
			true
		);

		wp_localize_script(
			'bd-lists-admin',
			'bdListsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_admin_list_action' ),
			)
		);
	}

	/**
	 * Get lists with filters
	 *
	 * @param array $args Query arguments.
	 * @return array Lists and pagination info.
	 */
	private static function get_lists( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$defaults = array(
			'status'   => '',
			'search'   => '',
			'city'     => '',
			'category' => '',
			'author'   => '',
			'orderby'  => 'updated_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';

		// Filter by visibility (status).
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'public', 'private', 'unlisted', 'featured' ), true ) ) {
			if ( 'featured' === $args['status'] ) {
				$where .= ' AND featured = 1';
			} else {
				$where .= $wpdb->prepare( ' AND visibility = %s', $args['status'] );
			}
		}

		// Filter by city.
		if ( ! empty( $args['city'] ) ) {
			$where .= $wpdb->prepare( ' AND cached_city = %s', $args['city'] );
		}

		// Filter by category.
		if ( ! empty( $args['category'] ) ) {
			$cat_search = '%' . $wpdb->esc_like( $args['category'] ) . '%';
			$where     .= $wpdb->prepare( ' AND cached_categories LIKE %s', $cat_search );
		}

		// Filter by author.
		if ( ! empty( $args['author'] ) ) {
			$where .= $wpdb->prepare( ' AND user_id = %d', $args['author'] );
		}

		// Search.
		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= $wpdb->prepare(
				' AND (title LIKE %s OR description LIKE %s)',
				$search,
				$search
			);
		}

		// Sorting.
		$allowed_orderby = array( 'title', 'created_at', 'updated_at', 'view_count', 'cached_city' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Total count.
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

		// Get lists.
		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		$lists  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name as author_name, u.user_email as author_email
				FROM $table l
				LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
				WHERE $where
				ORDER BY $orderby $order
				LIMIT %d OFFSET %d",
				$args['per_page'],
				$offset
			),
			ARRAY_A
		);

		// Add item counts and follower counts.
		$follows_table = $wpdb->prefix . 'bd_list_follows';
		foreach ( $lists as &$list ) {
			$list['item_count']     = ListManager::get_list_item_count( $list['id'] );
			$list['follower_count'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM $follows_table WHERE list_id = %d", $list['id'] )
			);
			$list['cover_image']    = self::get_list_cover_image( $list );
		}

		return array(
			'lists'    => $lists,
			'total'    => (int) $total,
			'pages'    => ceil( $total / $args['per_page'] ),
			'page'     => $args['page'],
			'per_page' => $args['per_page'],
		);
	}

	/**
	 * Get list cover image URL
	 *
	 * @param array $list List data.
	 * @return string|null Image URL.
	 */
	private static function get_list_cover_image( $list ) {
		if ( ! empty( $list['cover_image_id'] ) ) {
			$url = wp_get_attachment_image_url( $list['cover_image_id'], 'thumbnail' );
			if ( $url ) {
				return $url;
			}
		}

		// Fall back to first business image.
		global $wpdb;
		$items_table = $wpdb->prefix . 'bd_list_items';

		$first_business_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT business_id FROM $items_table WHERE list_id = %d ORDER BY sort_order ASC LIMIT 1",
				$list['id']
			)
		);

		if ( $first_business_id ) {
			$thumbnail = get_the_post_thumbnail_url( $first_business_id, 'thumbnail' );
			if ( $thumbnail ) {
				return $thumbnail;
			}
		}

		return null;
	}

	/**
	 * Get status counts for tabs
	 *
	 * @return array Status counts.
	 */
	private static function get_status_counts() {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$counts = $wpdb->get_results(
			"SELECT visibility, COUNT(*) as count FROM $table GROUP BY visibility",
			OBJECT_K
		);

		$featured_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE featured = 1" );
		$total_count    = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

		return array(
			'all'      => (int) $total_count,
			'public'   => (int) ( $counts['public']->count ?? 0 ),
			'private'  => (int) ( $counts['private']->count ?? 0 ),
			'unlisted' => (int) ( $counts['unlisted']->count ?? 0 ),
			'featured' => (int) $featured_count,
		);
	}

	/**
	 * Get unique cities for filter dropdown
	 *
	 * @return array Cities.
	 */
	private static function get_filter_cities() {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		return $wpdb->get_col(
			"SELECT DISTINCT cached_city FROM $table 
			WHERE cached_city IS NOT NULL AND cached_city != '' 
			ORDER BY cached_city ASC"
		);
	}

	/**
	 * Get unique categories for filter dropdown
	 *
	 * @return array Categories with name and slug.
	 */
	private static function get_filter_categories() {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$cached = $wpdb->get_col(
			"SELECT DISTINCT cached_categories FROM $table 
			WHERE cached_categories IS NOT NULL AND cached_categories != ''"
		);

		$all_slugs = array();
		foreach ( $cached as $cats ) {
			$slugs     = array_map( 'trim', explode( ',', $cats ) );
			$all_slugs = array_merge( $all_slugs, $slugs );
		}
		$all_slugs = array_unique( array_filter( $all_slugs ) );

		$categories = array();
		foreach ( $all_slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'bd_category' );
			if ( $term ) {
				$categories[] = array(
					'slug' => $term->slug,
					'name' => $term->name,
				);
			}
		}

		usort(
			$categories,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $categories;
	}

	/**
	 * Get authors who have created lists
	 *
	 * @return array Authors.
	 */
	private static function get_filter_authors() {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		return $wpdb->get_results(
			"SELECT DISTINCT l.user_id, u.display_name 
			FROM $table l
			LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
			ORDER BY u.display_name ASC",
			ARRAY_A
		);
	}

	/**
	 * Render admin page
	 */
	public static function render_page() {
		// Get filter values.
		$current_status   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$search_query     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$current_city     = isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : '';
		$current_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
		$current_author   = isset( $_GET['author'] ) ? absint( $_GET['author'] ) : '';
		$current_page     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$orderby          = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'updated_at';
		$order            = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		$counts     = self::get_status_counts();
		$cities     = self::get_filter_cities();
		$categories = self::get_filter_categories();
		$authors    = self::get_filter_authors();

		$result = self::get_lists(
			array(
				'status'   => $current_status,
				'search'   => $search_query,
				'city'     => $current_city,
				'category' => $current_category,
				'author'   => $current_author,
				'orderby'  => $orderby,
				'order'    => $order,
				'page'     => $current_page,
			)
		);

		$lists = $result['lists'];
		$total = $result['total'];
		$pages = $result['pages'];

		$base_url = admin_url( 'edit.php?post_type=bd_business&page=bd-user-lists' );
		?>
		<div class="wrap bd-lists-admin">
			<h1 class="wp-heading-inline">
				<i class="fas fa-clipboard-list"></i>
				<?php esc_html_e( 'User Lists', 'developer-developer-developer' ); ?>
			</h1>
			<hr class="wp-header-end">

			<!-- Status Tabs -->
			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( $base_url ); ?>" 
						class="<?php echo empty( $current_status ) ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['all'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'public', $base_url ) ); ?>"
						class="<?php echo 'public' === $current_status ? 'current' : ''; ?>">
						<i class="fas fa-globe"></i>
						<?php esc_html_e( 'Public', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['public'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'private', $base_url ) ); ?>"
						class="<?php echo 'private' === $current_status ? 'current' : ''; ?>">
						<i class="fas fa-lock"></i>
						<?php esc_html_e( 'Private', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['private'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'unlisted', $base_url ) ); ?>"
						class="<?php echo 'unlisted' === $current_status ? 'current' : ''; ?>">
						<i class="fas fa-link"></i>
						<?php esc_html_e( 'Unlisted', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['unlisted'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'featured', $base_url ) ); ?>"
						class="<?php echo 'featured' === $current_status ? 'current' : ''; ?>">
						<i class="fas fa-star"></i>
						<?php esc_html_e( 'Featured', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['featured'] ); ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Filters Bar -->
			<div class="bd-admin-filters">
				<form method="get" class="bd-filters-form">
					<input type="hidden" name="post_type" value="bd_business">
					<input type="hidden" name="page" value="bd-user-lists">
					<?php if ( $current_status ) : ?>
						<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
					<?php endif; ?>

					<!-- Search -->
					<div class="bd-filter-group">
						<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" 
							placeholder="<?php esc_attr_e( 'Search lists...', 'developer-developer-developer' ); ?>"
							class="bd-filter-search">
					</div>

					<!-- City Filter -->
					<?php if ( ! empty( $cities ) ) : ?>
						<div class="bd-filter-group">
							<select name="city" class="bd-filter-select">
								<option value=""><?php esc_html_e( 'All Cities', 'developer-developer-developer' ); ?></option>
								<?php foreach ( $cities as $city ) : ?>
									<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $current_city, $city ); ?>>
										<?php echo esc_html( $city ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<!-- Category Filter -->
					<?php if ( ! empty( $categories ) ) : ?>
						<div class="bd-filter-group">
							<select name="category" class="bd-filter-select">
								<option value=""><?php esc_html_e( 'All Categories', 'developer-developer-developer' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat['slug'] ); ?>" <?php selected( $current_category, $cat['slug'] ); ?>>
										<?php echo esc_html( $cat['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<!-- Author Filter -->
					<?php if ( ! empty( $authors ) ) : ?>
						<div class="bd-filter-group">
							<select name="author" class="bd-filter-select">
								<option value=""><?php esc_html_e( 'All Authors', 'developer-developer-developer' ); ?></option>
								<?php foreach ( $authors as $author ) : ?>
									<option value="<?php echo esc_attr( $author['user_id'] ); ?>" <?php selected( $current_author, $author['user_id'] ); ?>>
										<?php echo esc_html( $author['display_name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<button type="submit" class="button">
						<i class="fas fa-filter"></i>
						<?php esc_html_e( 'Filter', 'developer-developer-developer' ); ?>
					</button>

					<?php if ( $search_query || $current_city || $current_category || $current_author ) : ?>
						<a href="<?php echo esc_url( $current_status ? add_query_arg( 'status', $current_status, $base_url ) : $base_url ); ?>" class="button bd-clear-filters">
							<i class="fas fa-times"></i>
							<?php esc_html_e( 'Clear', 'developer-developer-developer' ); ?>
						</a>
					<?php endif; ?>
				</form>
			</div>

			<!-- Bulk Actions -->
			<div class="bd-bulk-actions">
				<select id="bd-bulk-action" class="bd-bulk-select">
					<option value=""><?php esc_html_e( 'Bulk Actions', 'developer-developer-developer' ); ?></option>
					<option value="feature"><?php esc_html_e( 'Feature', 'developer-developer-developer' ); ?></option>
					<option value="unfeature"><?php esc_html_e( 'Remove Featured', 'developer-developer-developer' ); ?></option>
					<option value="make_public"><?php esc_html_e( 'Make Public', 'developer-developer-developer' ); ?></option>
					<option value="make_private"><?php esc_html_e( 'Make Private', 'developer-developer-developer' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'developer-developer-developer' ); ?></option>
				</select>
				<button type="button" id="bd-bulk-apply" class="button">
					<?php esc_html_e( 'Apply', 'developer-developer-developer' ); ?>
				</button>
				<span class="bd-bulk-status"></span>
			</div>

			<!-- Lists Table -->
			<table class="wp-list-table widefat fixed striped bd-lists-table">
				<thead>
					<tr>
						<th class="column-cb check-column">
							<input type="checkbox" id="bd-select-all">
						</th>
						<th class="column-thumb"><?php esc_html_e( 'Image', 'developer-developer-developer' ); ?></th>
						<th class="column-title" scope="col">
							<?php echo self::sortable_column_header( 'title', __( 'Title', 'developer-developer-developer' ), $orderby, $order, $base_url, $current_status, $search_query ); ?>
						</th>
						<th class="column-author" scope="col"><?php esc_html_e( 'Author', 'developer-developer-developer' ); ?></th>
						<th class="column-categories" scope="col"><?php esc_html_e( 'Categories', 'developer-developer-developer' ); ?></th>
						<th class="column-city" scope="col">
							<?php echo self::sortable_column_header( 'cached_city', __( 'City', 'developer-developer-developer' ), $orderby, $order, $base_url, $current_status, $search_query ); ?>
						</th>
						<th class="column-items" scope="col"><?php esc_html_e( 'Items', 'developer-developer-developer' ); ?></th>
						<th class="column-followers" scope="col"><?php esc_html_e( 'Followers', 'developer-developer-developer' ); ?></th>
						<th class="column-visibility" scope="col"><?php esc_html_e( 'Status', 'developer-developer-developer' ); ?></th>
						<th class="column-views" scope="col">
							<?php echo self::sortable_column_header( 'view_count', __( 'Views', 'developer-developer-developer' ), $orderby, $order, $base_url, $current_status, $search_query ); ?>
						</th>
						<th class="column-date" scope="col">
							<?php echo self::sortable_column_header( 'updated_at', __( 'Updated', 'developer-developer-developer' ), $orderby, $order, $base_url, $current_status, $search_query ); ?>
						</th>
						<th class="column-actions" scope="col"><?php esc_html_e( 'Actions', 'developer-developer-developer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $lists ) ) : ?>
						<tr>
							<td colspan="12" class="bd-no-items">
								<i class="fas fa-inbox"></i>
								<?php esc_html_e( 'No lists found.', 'developer-developer-developer' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $lists as $list ) : ?>
							<?php
							$categories_display = self::format_categories_display( $list['cached_categories'] ?? '' );
							?>
							<tr data-list-id="<?php echo esc_attr( $list['id'] ); ?>" class="bd-list-row">
								<th class="check-column">
									<input type="checkbox" class="bd-list-checkbox" value="<?php echo esc_attr( $list['id'] ); ?>">
								</th>
								<td class="column-thumb">
									<?php if ( $list['cover_image'] ) : ?>
										<img src="<?php echo esc_url( $list['cover_image'] ); ?>" alt="" class="bd-list-thumb">
									<?php else : ?>
										<div class="bd-list-thumb-placeholder">
											<i class="fas fa-clipboard-list"></i>
										</div>
									<?php endif; ?>
								</td>
								<td class="column-title">
									<div class="bd-list-title-wrap">
										<?php if ( $list['featured'] ) : ?>
											<span class="bd-featured-star" title="Featured"><i class="fas fa-star"></i></span>
										<?php endif; ?>
										<strong class="bd-list-title"><?php echo esc_html( $list['title'] ); ?></strong>
									</div>
									<?php if ( ! empty( $list['description'] ) ) : ?>
										<p class="bd-list-description" title="<?php echo esc_attr( $list['description'] ); ?>">
											<?php echo esc_html( wp_trim_words( $list['description'], 10 ) ); ?>
										</p>
									<?php endif; ?>
								</td>
								<td class="column-author">
									<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $list['user_id'] ) ); ?>" class="bd-author-link">
										<?php echo get_avatar( $list['user_id'], 24 ); ?>
										<span><?php echo esc_html( $list['author_name'] ); ?></span>
									</a>
								</td>
								<td class="column-categories">
									<?php if ( $categories_display ) : ?>
										<div class="bd-category-pills">
											<?php foreach ( $categories_display as $cat ) : ?>
												<span class="bd-category-pill"><?php echo esc_html( $cat ); ?></span>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<span class="bd-no-data">—</span>
									<?php endif; ?>
								</td>
								<td class="column-city">
									<?php if ( ! empty( $list['cached_city'] ) ) : ?>
										<span class="bd-city-badge">
											<i class="fas fa-map-marker-alt"></i>
											<?php echo esc_html( $list['cached_city'] ); ?>
										</span>
									<?php else : ?>
										<span class="bd-no-data">—</span>
									<?php endif; ?>
								</td>
								<td class="column-items">
									<span class="bd-item-count" title="<?php esc_attr_e( 'Businesses in list', 'developer-developer-developer' ); ?>">
										<i class="fas fa-store"></i>
										<?php echo esc_html( $list['item_count'] ); ?>
									</span>
								</td>
								<td class="column-followers">
									<span class="bd-follower-count" title="<?php esc_attr_e( 'Followers', 'developer-developer-developer' ); ?>">
										<i class="fas fa-users"></i>
										<?php echo esc_html( $list['follower_count'] ); ?>
									</span>
								</td>
								<td class="column-visibility">
									<span class="bd-visibility-badge bd-visibility-<?php echo esc_attr( $list['visibility'] ); ?>">
										<?php
										$visibility_icons = array(
											'public'   => 'fa-globe',
											'private'  => 'fa-lock',
											'unlisted' => 'fa-link',
										);
										$icon             = $visibility_icons[ $list['visibility'] ] ?? 'fa-question';
										?>
										<i class="fas <?php echo esc_attr( $icon ); ?>"></i>
										<?php echo esc_html( ucfirst( $list['visibility'] ) ); ?>
									</span>
								</td>
								<td class="column-views">
									<span class="bd-view-count">
										<i class="fas fa-eye"></i>
										<?php echo esc_html( number_format( $list['view_count'] ) ); ?>
									</span>
								</td>
								<td class="column-date">
									<span class="bd-date" title="<?php echo esc_attr( $list['updated_at'] ); ?>">
										<?php echo esc_html( human_time_diff( strtotime( $list['updated_at'] ), current_time( 'timestamp' ) ) ); ?> ago
									</span>
								</td>
								<td class="column-actions">
									<div class="bd-action-buttons">
										<?php if ( 'private' !== $list['visibility'] ) : ?>
											<a href="<?php echo esc_url( ListManager::get_list_url( $list ) ); ?>" 
												class="bd-action-btn bd-view-btn" target="_blank" title="View List">
												<i class="fas fa-external-link-alt"></i>
											</a>
										<?php endif; ?>
										
										<?php if ( $list['featured'] ) : ?>
											<button type="button" class="bd-action-btn bd-unfeature-btn bd-featured" 
												data-list-id="<?php echo esc_attr( $list['id'] ); ?>" title="Remove Featured">
												<i class="fas fa-star"></i>
											</button>
										<?php else : ?>
											<button type="button" class="bd-action-btn bd-feature-btn" 
												data-list-id="<?php echo esc_attr( $list['id'] ); ?>" title="Feature This List">
												<i class="far fa-star"></i>
											</button>
										<?php endif; ?>
										
										<button type="button" class="bd-action-btn bd-delete-btn" 
											data-list-id="<?php echo esc_attr( $list['id'] ); ?>" title="Delete List">
											<i class="fas fa-trash-alt"></i>
										</button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html( _n( '%s item', '%s items', $total, 'developer-developer-developer' ) ),
								esc_html( number_format( $total ) )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php
							$pagination_args = array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '<i class="fas fa-chevron-left"></i>',
								'next_text' => '<i class="fas fa-chevron-right"></i>',
								'total'     => $pages,
								'current'   => $current_page,
							);
							echo paginate_links( $pagination_args );
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Format categories for display
	 *
	 * @param string $cached_categories Comma-separated category slugs.
	 * @return array Category names.
	 */
	private static function format_categories_display( $cached_categories ) {
		if ( empty( $cached_categories ) ) {
			return array();
		}

		$slugs = array_map( 'trim', explode( ',', $cached_categories ) );
		$names = array();

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'bd_category' );
			if ( $term ) {
				$names[] = $term->name;
			}
		}

		return array_slice( $names, 0, 3 ); // Show max 3.
	}

	/**
	 * Generate sortable column header
	 */
	private static function sortable_column_header( $column, $label, $current_orderby, $current_order, $base_url, $status, $search ) {
		$is_current = ( $column === $current_orderby );
		$new_order  = ( $is_current && 'ASC' === strtoupper( $current_order ) ) ? 'desc' : 'asc';

		$url = add_query_arg(
			array(
				'orderby' => $column,
				'order'   => $new_order,
				'status'  => $status,
				's'       => $search,
			),
			$base_url
		);

		$class = 'sortable';
		if ( $is_current ) {
			$class .= ' sorted ' . strtolower( $current_order );
		}

		return sprintf(
			'<a href="%s" class="%s"><span>%s</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>',
			esc_url( $url ),
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Handle AJAX actions
	 */
	public static function handle_ajax_action() {
		check_ajax_referer( 'bd_admin_list_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$action  = isset( $_POST['list_action'] ) ? sanitize_text_field( wp_unslash( $_POST['list_action'] ) ) : '';
		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;

		if ( ! $list_id ) {
			wp_send_json_error( array( 'message' => 'Invalid list ID' ) );
		}

		switch ( $action ) {
			case 'feature':
				$result = ListManager::set_featured( $list_id, true );
				if ( $result ) {
					wp_send_json_success( array( 'message' => 'List featured!' ) );
				}
				break;

			case 'unfeature':
				$result = ListManager::set_featured( $list_id, false );
				if ( $result ) {
					wp_send_json_success( array( 'message' => 'List unfeatured!' ) );
				}
				break;

			case 'delete':
				global $wpdb;
				$lists_table   = $wpdb->prefix . 'bd_lists';
				$items_table   = $wpdb->prefix . 'bd_list_items';
				$follows_table = $wpdb->prefix . 'bd_list_follows';

				$wpdb->delete( $items_table, array( 'list_id' => $list_id ), array( '%d' ) );
				$wpdb->delete( $follows_table, array( 'list_id' => $list_id ), array( '%d' ) );
				$result = $wpdb->delete( $lists_table, array( 'id' => $list_id ), array( '%d' ) );

				if ( $result ) {
					wp_send_json_success( array( 'message' => 'List deleted!' ) );
				}
				break;
		}

		wp_send_json_error( array( 'message' => 'Action failed' ) );
	}

	/**
	 * Handle bulk actions
	 */
	public static function handle_bulk_action() {
		check_ajax_referer( 'bd_admin_list_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$action   = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$list_ids = isset( $_POST['list_ids'] ) ? array_map( 'absint', $_POST['list_ids'] ) : array();

		if ( empty( $list_ids ) ) {
			wp_send_json_error( array( 'message' => 'No lists selected' ) );
		}

		global $wpdb;
		$table         = $wpdb->prefix . 'bd_lists';
		$items_table   = $wpdb->prefix . 'bd_list_items';
		$follows_table = $wpdb->prefix . 'bd_list_follows';
		$count         = 0;

		switch ( $action ) {
			case 'feature':
				foreach ( $list_ids as $list_id ) {
					if ( ListManager::set_featured( $list_id, true ) ) {
						++$count;
					}
				}
				wp_send_json_success(
					array(
						'message' => sprintf( '%d list(s) featured!', $count ),
						'count'   => $count,
					)
				);
				break;

			case 'unfeature':
				foreach ( $list_ids as $list_id ) {
					if ( ListManager::set_featured( $list_id, false ) ) {
						++$count;
					}
				}
				wp_send_json_success(
					array(
						'message' => sprintf( '%d list(s) unfeatured!', $count ),
						'count'   => $count,
					)
				);
				break;

			case 'make_public':
				$ids_placeholder = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
				$count           = $wpdb->query(
					$wpdb->prepare(
						"UPDATE $table SET visibility = 'public' WHERE id IN ($ids_placeholder)",
						$list_ids
					)
				);
				wp_send_json_success(
					array(
						'message' => sprintf( '%d list(s) made public!', $count ),
						'count'   => $count,
					)
				);
				break;

			case 'make_private':
				$ids_placeholder = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
				$count           = $wpdb->query(
					$wpdb->prepare(
						"UPDATE $table SET visibility = 'private' WHERE id IN ($ids_placeholder)",
						$list_ids
					)
				);
				wp_send_json_success(
					array(
						'message' => sprintf( '%d list(s) made private!', $count ),
						'count'   => $count,
					)
				);
				break;

			case 'delete':
				foreach ( $list_ids as $list_id ) {
					$wpdb->delete( $items_table, array( 'list_id' => $list_id ), array( '%d' ) );
					$wpdb->delete( $follows_table, array( 'list_id' => $list_id ), array( '%d' ) );
					if ( $wpdb->delete( $table, array( 'id' => $list_id ), array( '%d' ) ) ) {
						++$count;
					}
				}
				wp_send_json_success(
					array(
						'message' => sprintf( '%d list(s) deleted!', $count ),
						'count'   => $count,
					)
				);
				break;
		}

		wp_send_json_error( array( 'message' => 'Invalid action' ) );
	}
}
