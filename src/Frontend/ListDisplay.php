<?php

/**
 * List Display Frontend
 *
 * Handles rendering of list shortcodes and save buttons.
 * Follows BadgeDisplay pattern with static methods.
 *
 * @package BusinessDirectory
 */


namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

use BD\Lists\ListManager;
use BD\Lists\ListCollaborators;
use BD\Gamification\BadgeSystem;
use BD\Gamification\ActivityTracker;

class ListDisplay {




	/**
	 * Initialize shortcodes and hooks
	 */
	public static function init() {
		// Shortcodes.
		add_shortcode( 'bd_my_lists', array( __CLASS__, 'render_my_lists' ) );
		add_shortcode( 'bd_public_lists', array( __CLASS__, 'render_public_lists' ) );
		add_shortcode( 'bd_list', array( __CLASS__, 'render_single_list' ) );
		add_shortcode( 'bd_save_button', array( __CLASS__, 'render_save_button' ) );

		// Add save button to business pages.
		add_action( 'bd_after_business_title', array( __CLASS__, 'output_save_button' ) );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public static function enqueue_assets() {
		// Only load on relevant pages.
		if ( ! is_singular( 'bd_business' ) && ! self::is_lists_page() ) {
			return;
		}

		// Design tokens (fonts, colors).
		wp_enqueue_style(
			'bd-design-tokens',
			plugins_url( 'assets/css/design-tokens.css', dirname( __DIR__ ) ),
			array(),
			'1.0.0'
		);

		wp_enqueue_style(
			'bd-lists',
			plugins_url( 'assets/css/lists.css', dirname( __DIR__ ) ),
			array( 'bd-design-tokens' ),
			'1.1.0'
		);

		// Enqueue Sortable.js for drag-and-drop reordering.
		wp_enqueue_script(
			'sortablejs',
			'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
			array(),
			'1.15.0',
			true
		);

		wp_enqueue_script(
			'bd-lists',
			plugins_url( 'assets/js/lists.js', dirname( __DIR__ ) ),
			array( 'jquery', 'sortablejs' ),
			'1.1.0',
			true
		);

		// Collaborators JS.
		wp_enqueue_script(
			'bd-list-collaborators',
			plugins_url( 'assets/js/list-collaborators.js', dirname( __DIR__ ) ),
			array( 'jquery', 'bd-lists' ),
			'1.0.3',
			true
		);

		// Collaborators CSS.
		wp_enqueue_style(
			'bd-list-collaborators',
			plugins_url( 'assets/css/list-collaborators.css', dirname( __DIR__ ) ),
			array( 'bd-lists' ),
			'1.0.1'
		);

		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);

		// Enqueue Leaflet for map view.
		wp_enqueue_style(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			array(),
			'1.9.4'
		);

		wp_enqueue_script(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);

		wp_localize_script(
			'bd-lists',
			'bdLists',
			array(
				'restUrl'     => rest_url( 'bd/v1/' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'isLoggedIn'  => is_user_logged_in(),
				'loginUrl'    => wp_login_url(),
				'registerUrl' => wp_registration_url(),
				'myListsUrl'  => home_url( '/my-profile/my-lists/' ),
				'strings'     => array(
					'saved'         => __( 'Saved!', 'business-directory' ),
					'removed'       => __( 'Removed', 'business-directory' ),
					'error'         => __( 'Something went wrong', 'business-directory' ),
					'loginRequired' => __( 'Please log in to save businesses', 'business-directory' ),
					'createList'    => __( 'Create New List', 'business-directory' ),
					'copied'        => __( 'Copied to clipboard!', 'business-directory' ),
					'shareTitle'    => __( 'Share This List', 'business-directory' ),
					'following'     => __( 'Following', 'business-directory' ),
					'follow'        => __( 'Follow', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Check if current page uses lists
	 */
	private static function is_lists_page() {
		global $post;
		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'bd_my_lists' )
			|| has_shortcode( $post->post_content, 'bd_public_lists' )
			|| has_shortcode( $post->post_content, 'bd_list' );
	}

	/**
	 * Render save button on business pages
	 */
	public static function output_save_button() {
		if ( ! is_singular( 'bd_business' ) ) {
			return;
		}

		echo self::render_save_button( array( 'business_id' => get_the_ID() ) );
	}

	/**
	 * Render save button [bd_save_button]
	 */
	public static function render_save_button( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'business_id' => 0,
				'style'       => 'button',
			),
			$atts
		);

		$business_id = absint( $atts['business_id'] );
		if ( ! $business_id ) {
			$business_id = get_the_ID();
		}

		if ( ! $business_id ) {
			return '';
		}

		$is_logged_in = is_user_logged_in();
		$user_id      = get_current_user_id();
		$saved_lists  = $is_logged_in ? ListManager::get_lists_containing_business( $user_id, $business_id ) : array();
		$is_saved     = ! empty( $saved_lists );
		$user_lists   = $is_logged_in ? ListManager::get_user_lists( $user_id ) : array();

		ob_start();
		?>
		<div class="bd-save-wrapper" data-business-id="<?php echo esc_attr( $business_id ); ?>">
			<button type="button" class="bd-save-btn <?php echo $is_saved ? 'bd-saved' : ''; ?> bd-save-style-<?php echo esc_attr( $atts['style'] ); ?>"
				<?php echo ! $is_logged_in ? 'data-login-required="true"' : ''; ?>>
				<span class="bd-save-icon"><?php echo $is_saved ? '<i class="fas fa-heart"></i>' : '<i class="far fa-heart"></i>'; ?></span>
				<?php if ( 'icon' !== $atts['style'] ) : ?>
					<span class="bd-save-text"><?php echo $is_saved ? 'Saved' : 'Save'; ?></span>
				<?php endif; ?>
			</button>

			<?php if ( $is_logged_in ) : ?>
				<div class="bd-save-modal" style="display: none;">
					<div class="bd-save-modal-content">
						<div class="bd-save-modal-header">
							<h3>Save to List</h3>
							<button type="button" class="bd-save-modal-close">&times;</button>
						</div>
						<div class="bd-save-modal-body">
							<?php if ( ! empty( $user_lists ) ) : ?>
								<div class="bd-save-lists">
									<?php foreach ( $user_lists as $list ) : ?>
										<?php $in_list = in_array( $list['id'], $saved_lists, true ); ?>
										<label class="bd-save-list-item <?php echo $in_list ? 'bd-in-list' : ''; ?>">
											<input type="checkbox"
												name="list_ids[]"
												value="<?php echo esc_attr( $list['id'] ); ?>"
												<?php checked( $in_list ); ?>
												data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
											<span class="bd-save-list-title"><?php echo esc_html( $list['title'] ); ?></span>
											<span class="bd-save-list-count"><?php echo esc_html( $list['item_count'] ); ?> items</span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<div class="bd-save-new-list">
								<button type="button" class="bd-create-list-toggle">
									<span class="bd-plus-icon">+</span> Create New List
								</button>
								<div class="bd-create-list-form" style="display: none;">
									<input type="text" class="bd-new-list-title" placeholder="List name...">
									<button type="button" class="bd-create-list-btn button">Create & Save</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render My Lists page [bd_my_lists]
	 */
	public static function render_my_lists( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'per_page' => 12,
			),
			$atts
		);

		if ( ! is_user_logged_in() ) {
			return self::render_login_required( 'my lists' );
		}

		$user_id = get_current_user_id();
		$lists   = ListManager::get_user_lists( $user_id );
		$stats   = ActivityTracker::get_user_stats( $user_id );

		// Get lists user is following.
		$following_lists = ListManager::get_following_lists( $user_id );

		// Get lists user is collaborating on.
		$collab_lists = ListCollaborators::get_user_collaborative_lists( $user_id );

		ob_start();
		?>
		<div class="bd-my-lists-page">

			<!-- Header -->
			<div class="bd-lists-header">
				<div class="bd-lists-header-content">
					<h1><i class="fas fa-heart"></i> My Lists</h1>
					<p>Organize your favorite businesses into collections</p>
				</div>
				<button type="button" class="bd-btn bd-btn-primary bd-create-list-open">
					<i class="fas fa-plus"></i> Create List
				</button>
			</div>

			<!-- Stats Bar -->
			<div class="bd-lists-stats">
				<div class="bd-stat">
					<span class="bd-stat-value"><?php echo count( $lists ); ?></span>
					<span class="bd-stat-label">My Lists</span>
				</div>
				<div class="bd-stat">
					<span class="bd-stat-value"><?php echo count( $collab_lists ); ?></span>
					<span class="bd-stat-label">Collaborating</span>
				</div>
				<div class="bd-stat">
					<span class="bd-stat-value"><?php echo count( $following_lists ); ?></span>
					<span class="bd-stat-label">Following</span>
				</div>
				<div class="bd-stat">
					<span class="bd-stat-value"><?php echo number_format( $stats['total_points'] ?? 0 ); ?></span>
					<span class="bd-stat-label">Points</span>
				</div>
			</div>

			<!-- Tab Navigation -->
			<div class="bd-lists-tabs">
				<button type="button" class="bd-lists-tab bd-lists-tab-active" data-tab="my-lists">
					My Lists (<?php echo count( $lists ); ?>)
				</button>
				<button type="button" class="bd-lists-tab" data-tab="collaborating">
					Collaborating (<?php echo count( $collab_lists ); ?>)
				</button>
				<button type="button" class="bd-lists-tab" data-tab="following">
					Following (<?php echo count( $following_lists ); ?>)
				</button>
			</div>

			<!-- My Lists Tab -->
			<div class="bd-lists-tab-content bd-lists-tab-content-active" data-tab="my-lists">
				<?php if ( empty( $lists ) ) : ?>
					<div class="bd-lists-empty">
						<div class="bd-empty-icon"><i class="fas fa-clipboard-list"></i></div>
						<h3>No lists yet</h3>
						<p>Create your first list to start organizing your favorite businesses!</p>
						<button type="button" class="bd-btn bd-btn-primary bd-create-list-open">
							<i class="fas fa-plus"></i> Create Your First List
						</button>
					</div>
				<?php else : ?>
					<div class="bd-lists-grid">
						<?php foreach ( $lists as $list ) : ?>
							<?php echo self::render_list_card( $list, true ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Collaborating Tab -->
			<div class="bd-lists-tab-content" data-tab="collaborating" style="display: none;">
				<?php if ( empty( $collab_lists ) ) : ?>
					<div class="bd-lists-empty">
						<div class="bd-empty-icon"><i class="fas fa-handshake"></i></div>
						<h3>Not collaborating on any lists</h3>
						<p>When someone invites you to collaborate on their list, it will appear here!</p>
					</div>
				<?php else : ?>
					<div class="bd-lists-grid">
						<?php foreach ( $collab_lists as $list ) : ?>
							<?php echo self::render_collab_list_card( $list ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Following Tab -->
			<div class="bd-lists-tab-content" data-tab="following" style="display: none;">
				<?php if ( empty( $following_lists ) ) : ?>
					<div class="bd-lists-empty">
						<div class="bd-empty-icon"><i class="fas fa-eye"></i></div>
						<h3>Not following any lists</h3>
						<p>Follow lists from other users to get updates when they add new places!</p>
						<a href="<?php echo esc_url( home_url( '/community-lists/' ) ); ?>" class="bd-btn bd-btn-primary">
							<i class="fas fa-search"></i> Browse Community Lists
						</a>
					</div>
				<?php else : ?>
					<div class="bd-lists-grid">
						<?php foreach ( $following_lists as $list ) : ?>
							<?php echo self::render_list_card( $list, false ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Create List Modal -->
			<?php echo self::render_create_list_modal(); ?>

			<!-- Confirm Modal -->
			<?php echo self::render_confirm_modal(); ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render public lists [bd_public_lists]
	 */
	public static function render_public_lists( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'per_page'      => 12,
				'show_featured' => 'yes',
				'orderby'       => 'updated_at',
			),
			$atts
		);

		// Get filter parameters from URL.
		$page     = isset( $_GET['list_page'] ) ? absint( $_GET['list_page'] ) : 1;
		$search   = isset( $_GET['list_search'] ) ? sanitize_text_field( wp_unslash( $_GET['list_search'] ) ) : '';
		$category = isset( $_GET['list_category'] ) ? sanitize_text_field( wp_unslash( $_GET['list_category'] ) ) : '';
		$city     = isset( $_GET['list_city'] ) ? sanitize_text_field( wp_unslash( $_GET['list_city'] ) ) : '';
		$sort     = isset( $_GET['list_sort'] ) ? sanitize_text_field( wp_unslash( $_GET['list_sort'] ) ) : $atts['orderby'];
		$view     = isset( $_GET['list_view'] ) ? sanitize_text_field( wp_unslash( $_GET['list_view'] ) ) : 'grid';

		// Map sort parameter to orderby/order.
		$sort_options = array(
			'updated_at' => array(
				'orderby' => 'updated_at',
				'order'   => 'DESC',
			),
			'popular'    => array(
				'orderby' => 'view_count',
				'order'   => 'DESC',
			),
			'newest'     => array(
				'orderby' => 'created_at',
				'order'   => 'DESC',
			),
			'title'      => array(
				'orderby' => 'title',
				'order'   => 'ASC',
			),
		);
		$sort_config  = $sort_options[ $sort ] ?? $sort_options['updated_at'];

		$result = ListManager::get_public_lists(
			array(
				'per_page' => absint( $atts['per_page'] ),
				'page'     => $page,
				'orderby'  => $sort_config['orderby'],
				'order'    => $sort_config['order'],
				'search'   => $search,
				'category' => $category,
				'city'     => $city,
			)
		);

		$featured = array();
		if ( 'yes' === $atts['show_featured'] && empty( $search ) && empty( $category ) && empty( $city ) ) {
			$featured = ListManager::get_featured_lists( 4 );
		}

		// Get filter options.
		$all_categories = ListManager::get_all_list_categories();
		$all_cities     = ListManager::get_all_list_cities();

		$has_filters = ! empty( $search ) || ! empty( $category ) || ! empty( $city );

		ob_start();
		?>
		<div class="bd-public-lists-page">

			<!-- Page Header -->
			<div class="bd-lists-page-header">
				<div class="bd-lists-page-header-content">
					<h1>Community Lists</h1>
					<p>Discover curated collections from locals who know the Tri-Valley best</p>
				</div>
			</div>

			<!-- Featured Lists -->
			<?php if ( ! empty( $featured ) ) : ?>
				<div class="bd-featured-lists">
					<div class="bd-section-header">
						<h2><i class="fas fa-star"></i> Featured Lists</h2>
						<?php if ( count( $featured ) > 3 ) : ?>
							<a href="?list_sort=popular" class="bd-see-all">See All Popular <i class="fas fa-arrow-right"></i></a>
						<?php endif; ?>
					</div>
					<div class="bd-featured-lists-grid">
						<?php foreach ( $featured as $list ) : ?>
							<?php echo self::render_list_card( $list, false, true ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Filter Bar -->
			<div class="bd-lists-filter-bar">
				<form method="get" class="bd-lists-filter-form" id="bd-lists-filter-form">
					
					<!-- Search -->
					<div class="bd-filter-search">
						<i class="fas fa-search"></i>
						<input type="text" 
							name="list_search" 
							placeholder="Search lists..." 
							value="<?php echo esc_attr( $search ); ?>"
							class="bd-filter-search-input">
					</div>

					<!-- City Filter -->
					<?php if ( ! empty( $all_cities ) ) : ?>
						<div class="bd-filter-select">
							<select name="list_city" class="bd-filter-dropdown" onchange="this.form.submit()">
								<option value="">All Cities</option>
								<?php foreach ( $all_cities as $city_option ) : ?>
									<option value="<?php echo esc_attr( $city_option ); ?>" <?php selected( $city, $city_option ); ?>>
										<?php echo esc_html( $city_option ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<!-- Category Filter -->
					<?php if ( ! empty( $all_categories ) ) : ?>
						<div class="bd-filter-select">
							<select name="list_category" class="bd-filter-dropdown" onchange="this.form.submit()">
								<option value="">All Categories</option>
								<?php foreach ( $all_categories as $cat_option ) : ?>
									<option value="<?php echo esc_attr( $cat_option['slug'] ); ?>" <?php selected( $category, $cat_option['slug'] ); ?>>
										<?php echo esc_html( $cat_option['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<!-- Sort -->
					<div class="bd-filter-select">
						<select name="list_sort" class="bd-filter-dropdown" onchange="this.form.submit()">
							<option value="updated_at" <?php selected( $sort, 'updated_at' ); ?>>Recently Updated</option>
							<option value="popular" <?php selected( $sort, 'popular' ); ?>>Most Popular</option>
							<option value="newest" <?php selected( $sort, 'newest' ); ?>>Newest</option>
							<option value="title" <?php selected( $sort, 'title' ); ?>>A-Z</option>
						</select>
					</div>

					<!-- View Toggle -->
					<div class="bd-filter-view-toggle">
						<button type="submit" name="list_view" value="grid" 
							class="bd-view-btn <?php echo 'grid' === $view ? 'bd-view-btn-active' : ''; ?>"
							title="Grid View">
							<i class="fas fa-th-large"></i>
						</button>
						<button type="submit" name="list_view" value="list" 
							class="bd-view-btn <?php echo 'list' === $view ? 'bd-view-btn-active' : ''; ?>"
							title="List View">
							<i class="fas fa-list"></i>
						</button>
					</div>

				</form>
			</div>

			<!-- Active Filters -->
			<?php if ( $has_filters ) : ?>
				<div class="bd-active-filters">
					<span class="bd-active-filters-label">Filters:</span>
					<?php if ( ! empty( $search ) ) : ?>
						<a href="<?php echo esc_url( remove_query_arg( 'list_search' ) ); ?>" class="bd-filter-tag">
							"<?php echo esc_html( $search ); ?>" <i class="fas fa-times"></i>
						</a>
					<?php endif; ?>
					<?php if ( ! empty( $city ) ) : ?>
						<a href="<?php echo esc_url( remove_query_arg( 'list_city' ) ); ?>" class="bd-filter-tag">
							<?php echo esc_html( $city ); ?> <i class="fas fa-times"></i>
						</a>
					<?php endif; ?>
					<?php if ( ! empty( $category ) ) : ?>
						<?php
						$cat_term = get_term_by( 'slug', $category, 'bd_category' );
						$cat_name = $cat_term ? $cat_term->name : $category;
						?>
						<a href="<?php echo esc_url( remove_query_arg( 'list_category' ) ); ?>" class="bd-filter-tag">
							<?php echo esc_html( $cat_name ); ?> <i class="fas fa-times"></i>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" class="bd-clear-filters">
						Clear All
					</a>
				</div>
			<?php endif; ?>

			<!-- Results Count -->
			<div class="bd-lists-results-header">
				<span class="bd-results-count">
					<?php
					if ( $result['total'] === 0 ) {
						echo 'No lists found';
					} elseif ( $result['total'] === 1 ) {
						echo '1 list';
					} else {
						echo esc_html( number_format( $result['total'] ) ) . ' lists';
					}
					?>
				</span>
			</div>

			<!-- All Lists -->
			<div class="bd-all-lists">
				<?php if ( empty( $result['lists'] ) ) : ?>
					<div class="bd-lists-empty">
						<div class="bd-empty-icon"><i class="fas fa-search"></i></div>
						<h3>No lists found</h3>
						<p>
							<?php if ( $has_filters ) : ?>
								Try adjusting your filters or search terms.
							<?php else : ?>
								Be the first to share your favorites with the community!
							<?php endif; ?>
						</p>
						<?php if ( $has_filters ) : ?>
							<a href="<?php echo esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" class="bd-btn bd-btn-primary">
								<i class="fas fa-times"></i> Clear Filters
							</a>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<div class="bd-lists-<?php echo esc_attr( $view ); ?> <?php echo 'grid' === $view ? 'bd-lists-grid' : 'bd-lists-list-view'; ?>">
						<?php foreach ( $result['lists'] as $list ) : ?>
							<?php
							if ( 'list' === $view ) {
								echo self::render_list_row( $list );
							} else {
								echo self::render_list_card( $list );
							}
							?>
						<?php endforeach; ?>
					</div>

					<!-- Pagination -->
					<?php if ( $result['pages'] > 1 ) : ?>
						<div class="bd-lists-pagination">
							<?php
							echo paginate_links(
								array(
									'base'      => add_query_arg( 'list_page', '%#%' ),
									'format'    => '',
									'current'   => $page,
									'total'     => $result['pages'],
									'prev_text' => '<i class="fas fa-chevron-left"></i> Previous',
									'next_text' => 'Next <i class="fas fa-chevron-right"></i>',
								)
							);
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render single list page [bd_list]
	 */
	public static function render_single_list( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts
		);

		// Try to get list from URL param, attribute, or slug.
		$list = null;

		if ( isset( $_GET['list'] ) ) {
			$list = ListManager::get_list_by_slug( sanitize_text_field( wp_unslash( $_GET['list'] ) ) );
		} elseif ( ! empty( $atts['id'] ) ) {
			$list = ListManager::get_list( absint( $atts['id'] ) );
		} elseif ( ! empty( $atts['slug'] ) ) {
			$list = ListManager::get_list_by_slug( $atts['slug'] );
		}

		if ( ! $list ) {
			return '<div class="bd-list-not-found"><p>List not found.</p></div>';
		}

		// Check visibility.
		$current_user_id = get_current_user_id();
		$is_owner        = ( (int) $list['user_id'] === $current_user_id );

		// Check if user is a collaborator.
		$is_collaborator = false;
		$user_role       = null;
		if ( $current_user_id && ! $is_owner ) {
			$permissions     = ListCollaborators::get_user_permissions( $list['id'], $current_user_id );
			$is_collaborator = ! empty( $permissions );
			$user_role       = $permissions['role'] ?? null;
		}

		if ( 'private' === $list['visibility'] && ! $is_owner && ! $is_collaborator ) {
			return '<div class="bd-list-private"><p>This list is private.</p></div>';
		}

		// Increment view count.
		if ( ! $is_owner ) {
			ListManager::increment_view_count( $list['id'] );
		}

		// Get items.
		$items  = ListManager::get_list_items( $list['id'] );
		$author = get_userdata( $list['user_id'] );

		// Filter out items with deleted businesses (orphaned items).
		$items = array_filter(
			$items,
			function ( $item ) {
				return ! empty( $item['business'] );
			}
		);
		$items = array_values( $items ); // Re-index array.

		// Get map data for items with coordinates.
		$map_items = ListManager::get_list_items_with_coords( $list['id'] );

		// Check if user is following this list.
		$is_following   = $current_user_id ? ListManager::is_following( $list['id'], $current_user_id ) : false;
		$follower_count = $list['follower_count'] ?? 0;

		ob_start();
		?>
		<div class="bd-single-list-page" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">

			<!-- List Header -->
			<div class="bd-list-hero">
				<?php
				$cover_image = null;
				if ( ! empty( $list['cover_image_id'] ) ) {
					$cover_image = wp_get_attachment_image_url( $list['cover_image_id'], 'large' );
				} elseif ( ! empty( $items ) && ! empty( $items[0]['business']['featured_image'] ) ) {
					$cover_image = $items[0]['business']['featured_image'];
				}
				?>
				<?php if ( $cover_image ) : ?>
					<div class="bd-list-cover" style="background-image: url('<?php echo esc_url( $cover_image ); ?>');">
					</div>
				<?php endif; ?>

				<div class="bd-list-hero-content">
					<?php if ( $list['featured'] ) : ?>
						<span class="bd-featured-badge"><i class="fas fa-star"></i> Featured</span>
					<?php endif; ?>

					<h1><?php echo esc_html( $list['title'] ); ?></h1>

					<?php if ( ! empty( $list['description'] ) ) : ?>
						<p class="bd-list-description"><?php echo esc_html( $list['description'] ); ?></p>
					<?php endif; ?>

					<div class="bd-list-meta">
						<span class="bd-list-author">
							<?php echo get_avatar( $list['user_id'], 24 ); ?>
							By <?php echo esc_html( $author->display_name ?? 'Unknown' ); ?>
						</span>
						<span class="bd-list-count"><?php echo count( $items ); ?> businesses</span>
						<span class="bd-list-views"><?php echo number_format( $list['view_count'] ); ?> views</span>
						<?php if ( $follower_count > 0 ) : ?>
							<span class="bd-list-followers"><?php echo number_format( $follower_count ); ?> followers</span>
						<?php endif; ?>
					</div>

					<!-- Action Buttons -->
					<div class="bd-list-actions">
						<!-- Follow Button (for non-owners) -->
						<?php if ( ! $is_owner && 'private' !== $list['visibility'] ) : ?>
							<button type="button"
								class="bd-btn <?php echo $is_following ? 'bd-btn-secondary bd-following' : 'bd-btn-primary'; ?> bd-follow-btn"
								data-list-id="<?php echo esc_attr( $list['id'] ); ?>"
								<?php echo ! $current_user_id ? 'data-login-required="true"' : ''; ?>>
								<i class="fas <?php echo $is_following ? 'fa-check' : 'fa-plus'; ?>"></i>
								<span><?php echo $is_following ? 'Following' : 'Follow'; ?></span>
							</button>
						<?php endif; ?>

						<!-- Share Button -->
						<button type="button" class="bd-btn bd-btn-secondary bd-share-modal-open" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
							<i class="fas fa-share-alt"></i> Share
						</button>

						<!-- Map View Toggle -->
						<?php if ( ! empty( $map_items ) ) : ?>
							<button type="button" class="bd-btn bd-btn-secondary bd-map-toggle" data-view="list">
								<i class="fas fa-map"></i> <span>Map View</span>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Owner Actions -->
			<?php if ( $is_owner ) : ?>
				<div class="bd-list-owner-actions">
					<button type="button" class="bd-btn bd-btn-secondary bd-edit-list-btn">
						<i class="fas fa-edit"></i> Edit List
					</button>
					<button type="button" class="bd-btn bd-btn-secondary bd-manage-collaborators-btn" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
						<i class="fas fa-user-plus"></i> Collaborators
					</button>
					<span class="bd-visibility-badge bd-visibility-<?php echo esc_attr( $list['visibility'] ); ?>">
						<?php echo esc_html( ucfirst( $list['visibility'] ) ); ?>
					</span>
				</div>
			<?php elseif ( $is_collaborator ) : ?>
				<!-- Collaborator Badge -->
				<div class="bd-list-collab-badge">
					<i class="fas fa-user-check"></i> You're a collaborator on this list
					<span class="bd-collab-role">(<?php echo esc_html( ucfirst( $user_role ) ); ?>)</span>
				</div>
			<?php endif; ?>


			<!-- Map View Container (hidden by default) -->
			<?php if ( ! empty( $map_items ) ) : ?>
				<div class="bd-list-map-container" style="display: none;">
					<div id="bd-list-map" class="bd-list-map"></div>
				</div>
				<script>
					window.bdListMapData = <?php echo wp_json_encode( $map_items ); ?>;
				</script>
			<?php endif; ?>

			<!-- List Items -->
			<?php if ( empty( $items ) ) : ?>
				<div class="bd-list-empty">
					<div class="bd-empty-icon"><i class="fas fa-map-marker-alt"></i></div>
					<h3>This list is empty</h3>
					<?php if ( $is_owner || $is_collaborator ) : ?>
						<p>Start adding businesses to this list!</p>
						<a href="<?php echo esc_url( home_url( '/local/' ) ); ?>" class="bd-btn bd-btn-primary">
							Browse Businesses
						</a>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="bd-list-items-container">
					<div class="bd-list-items <?php echo ( $is_owner || $is_collaborator ) ? 'bd-sortable' : ''; ?>">
						<?php foreach ( $items as $index => $item ) : ?>
							<?php $business = $item['business']; ?>
							<?php
							if ( ! $business ) {
								continue;
							}
							?>

							<div class="bd-list-item" data-business-id="<?php echo esc_attr( $item['business_id'] ); ?>">
								<?php if ( $is_owner ) : ?>
									<div class="bd-list-item-handle">
										<i class="fas fa-grip-vertical"></i>
									</div>
								<?php endif; ?>

								<div class="bd-list-item-number"><?php echo $index + 1; ?></div>

								<div class="bd-list-item-image">
									<?php if ( $business['featured_image'] ) : ?>
										<img src="<?php echo esc_url( $business['featured_image'] ); ?>"
											alt="<?php echo esc_attr( $business['title'] ); ?>">
									<?php else : ?>
										<div class="bd-no-image"><i class="fas fa-map-marker-alt"></i></div>
									<?php endif; ?>
								</div>

								<div class="bd-list-item-content">
									<h3>
										<a href="<?php echo esc_url( $business['permalink'] ); ?>">
											<?php echo esc_html( $business['title'] ); ?>
										</a>
									</h3>

									<?php if ( ! empty( $business['categories'] ) ) : ?>
										<span class="bd-list-item-category">
											<?php echo esc_html( implode( ', ', $business['categories'] ) ); ?>
										</span>
									<?php endif; ?>

									<?php if ( $business['rating'] > 0 ) : ?>
										<div class="bd-list-item-rating">
											<?php
											$filled_stars = round( $business['rating'] );
											$empty_stars  = 5 - $filled_stars;
											for ( $i = 0; $i < $filled_stars; $i++ ) {
												echo '<i class="fas fa-star"></i>';
											}
											for ( $i = 0; $i < $empty_stars; $i++ ) {
												echo '<i class="far fa-star"></i>';
											}
											?>
											<span>(<?php echo esc_html( $business['review_count'] ); ?>)</span>
										</div>
									<?php endif; ?>

									<?php if ( ! empty( $business['city'] ) ) : ?>
										<span class="bd-list-item-location">
											<i class="fas fa-map-marker-alt"></i>
											<?php echo esc_html( $business['city'] ); ?>
										</span>
									<?php endif; ?>

									<?php if ( ! empty( $item['user_note'] ) ) : ?>
										<div class="bd-list-item-note">
											<i class="fas fa-comment"></i>
											"<?php echo esc_html( $item['user_note'] ); ?>"
										</div>
									<?php endif; ?>
								</div>

								<?php if ( $is_owner || $is_collaborator ) : ?>
									<div class="bd-list-item-actions">
										<button type="button" class="bd-edit-note-btn" title="Edit note">
											<i class="fas fa-comment"></i>
										</button>
										<button type="button" class="bd-remove-item-btn" title="Remove">
											<i class="fas fa-times"></i>
										</button>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Edit Modal (for owners) -->
			<?php if ( $is_owner ) : ?>
				<?php echo self::render_edit_list_modal( $list ); ?>
				<?php echo self::render_confirm_modal(); ?>
			<?php endif; ?>

			<!-- Share Modal -->
			<?php echo self::render_share_modal( $list ); ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render list card
	 */
	private static function render_list_card( $list, $show_actions = false, $is_featured = false ) {
		$cover_image = $list['cover_image'] ?? null;
		$url         = $list['url'] ?? ListManager::get_list_url( $list );
		$categories  = $list['categories'] ?? ListManager::get_list_display_categories( $list );
		$city        = $list['cached_city'] ?? null;

		ob_start();
		?>
		<div class="bd-list-card <?php echo $is_featured ? 'bd-list-featured' : ''; ?>" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
			<a href="<?php echo esc_url( $url ); ?>" class="bd-list-card-link">
					<div class="bd-list-card-cover">
					<?php if ( $cover_image ) : ?>
						<img src="<?php echo esc_url( $cover_image ); ?>" alt="<?php echo esc_attr( $list['title'] ); ?>">
					<?php else : ?>
						<div class="bd-list-card-placeholder"><i class="fas fa-clipboard-list"></i></div>
					<?php endif; ?>

					<?php if ( $is_featured ) : ?>
						<span class="bd-list-featured-badge"><i class="fas fa-star"></i></span>
					<?php endif; ?>

					<span class="bd-list-card-count"><?php echo esc_html( $list['item_count'] ); ?> places</span>
				</div>

				<div class="bd-list-card-body">
					<h3><?php echo esc_html( $list['title'] ); ?></h3>

					<?php if ( ! empty( $categories ) ) : ?>
						<div class="bd-list-card-categories">
							<?php foreach ( array_slice( $categories, 0, 2 ) as $cat ) : ?>
								<span class="bd-category-pill">
									<i class="<?php echo esc_attr( $cat['icon'] ); ?>"></i>
									<?php echo esc_html( $cat['name'] ); ?>
								</span>
							<?php endforeach; ?>
							<?php if ( count( $categories ) > 2 ) : ?>
								<span class="bd-category-pill bd-category-more">+<?php echo count( $categories ) - 2; ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $city ) ) : ?>
						<span class="bd-list-card-city">
							<i class="fas fa-map-marker-alt"></i> <?php echo esc_html( $city ); ?>
						</span>
					<?php endif; ?>

					<div class="bd-list-card-meta">
						<?php if ( ! empty( $list['author_name'] ) ) : ?>
							<span class="bd-list-card-author">
								<?php echo get_avatar( $list['user_id'], 20 ); ?>
								<?php echo esc_html( $list['author_name'] ); ?>
							</span>
						<?php endif; ?>

						<span class="bd-list-card-visibility bd-visibility-<?php echo esc_attr( $list['visibility'] ); ?>">
							<?php
							$icons = array(
								'public'   => '<i class="fas fa-globe"></i>',
								'unlisted' => '<i class="fas fa-link"></i>',
								'private'  => '<i class="fas fa-lock"></i>',
							);
							echo $icons[ $list['visibility'] ] ?? '';
							?>
						</span>
					</div>
				</div>
			</a>

			<?php if ( $show_actions ) : ?>
				<div class="bd-list-card-actions">
					<a href="<?php echo esc_url( $url ); ?>" class="bd-btn bd-btn-small">View</a>
					<button type="button" class="bd-btn bd-btn-small bd-btn-secondary bd-delete-list-btn"
						data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
						Delete
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render list row (for list view)
	 */
	private static function render_list_row( $list ) {
		$cover_image = $list['cover_image'] ?? null;
		$url         = $list['url'] ?? ListManager::get_list_url( $list );
		$categories  = $list['categories'] ?? ListManager::get_list_display_categories( $list );
		$city        = $list['cached_city'] ?? null;

		ob_start();
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="bd-list-row" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
			<div class="bd-list-row-image">
				<?php if ( $cover_image ) : ?>
					<img src="<?php echo esc_url( $cover_image ); ?>" alt="<?php echo esc_attr( $list['title'] ); ?>">
				<?php else : ?>
					<div class="bd-list-row-placeholder"><i class="fas fa-clipboard-list"></i></div>
				<?php endif; ?>
			</div>

			<div class="bd-list-row-content">
				<h3><?php echo esc_html( $list['title'] ); ?></h3>
				
				<div class="bd-list-row-details">
					<?php if ( ! empty( $categories ) ) : ?>
						<?php foreach ( array_slice( $categories, 0, 2 ) as $cat ) : ?>
							<span class="bd-category-pill bd-category-pill-small">
								<i class="<?php echo esc_attr( $cat['icon'] ); ?>"></i>
								<?php echo esc_html( $cat['name'] ); ?>
							</span>
						<?php endforeach; ?>
					<?php endif; ?>
					
					<?php if ( ! empty( $city ) ) : ?>
						<span class="bd-list-row-city">
							<i class="fas fa-map-marker-alt"></i> <?php echo esc_html( $city ); ?>
						</span>
					<?php endif; ?>

					<span class="bd-list-row-count"><?php echo esc_html( $list['item_count'] ); ?> places</span>
				</div>

				<div class="bd-list-row-meta">
					<?php if ( ! empty( $list['author_name'] ) ) : ?>
						<span class="bd-list-row-author">
							<?php echo get_avatar( $list['user_id'], 16 ); ?>
							<?php echo esc_html( $list['author_name'] ); ?>
						</span>
					<?php endif; ?>
					
					<?php if ( ! empty( $list['updated_at'] ) ) : ?>
						<span class="bd-list-row-date">
							Updated <?php echo esc_html( human_time_diff( strtotime( $list['updated_at'] ) ) ); ?> ago
						</span>
					<?php endif; ?>
				</div>
			</div>

			<div class="bd-list-row-stats">
				<?php if ( ! empty( $list['view_count'] ) ) : ?>
					<span class="bd-list-row-views" title="Views">
						<i class="fas fa-eye"></i> <?php echo esc_html( number_format( $list['view_count'] ) ); ?>
					</span>
				<?php endif; ?>
			</div>
		</a>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render collaborative list card (shows owner info and role)
	 */
	private static function render_collab_list_card( $list ) {
		$cover_image = $list['cover_image'] ?? null;
		if ( ! $cover_image ) {
			$cover_image = ListManager::get_list_cover_image( $list );
		}
		$url = $list['url'] ?? ListManager::get_list_url( $list );

		ob_start();
		?>
		<div class="bd-list-card bd-list-card-collab" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
			<a href="<?php echo esc_url( $url ); ?>" class="bd-list-card-link">
				<div class="bd-list-card-cover">
					<?php if ( $cover_image ) : ?>
						<img src="<?php echo esc_url( $cover_image ); ?>" alt="<?php echo esc_attr( $list['title'] ); ?>">
					<?php else : ?>
						<div class="bd-list-card-placeholder"><i class="fas fa-clipboard-list"></i></div>
					<?php endif; ?>

					<span class="bd-list-card-collab-badge">
						<i class="fas fa-user-check"></i> <?php echo esc_html( ucfirst( $list['role'] ?? 'Contributor' ) ); ?>
					</span>

					<span class="bd-list-card-count"><?php echo esc_html( $list['item_count'] ); ?> places</span>
				</div>

				<div class="bd-list-card-body">
					<h3><?php echo esc_html( $list['title'] ); ?></h3>

					<?php if ( ! empty( $list['description'] ) ) : ?>
						<p><?php echo esc_html( wp_trim_words( $list['description'], 12 ) ); ?></p>
					<?php endif; ?>

					<div class="bd-list-card-meta">
						<span class="bd-list-card-author">
							<?php echo get_avatar( $list['user_id'], 20 ); ?>
							By <?php echo esc_html( $list['owner_name'] ?? 'Unknown' ); ?>
						</span>
					</div>
				</div>
			</a>

			<div class="bd-list-card-actions">
				<a href="<?php echo esc_url( $url ); ?>" class="bd-btn bd-btn-small">View</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render create list modal
	 */
	private static function render_create_list_modal() {
		ob_start();
		?>
		<div class="bd-modal bd-create-list-modal" style="display: none;">
			<div class="bd-modal-overlay"></div>
			<div class="bd-modal-content">
				<div class="bd-modal-header">
					<h3><i class="fas fa-plus"></i> Create New List</h3>
					<button type="button" class="bd-modal-close">&times;</button>
				</div>
				<div class="bd-modal-body">
					<form class="bd-create-list-form">
						<div class="bd-form-group">
							<label for="bd-list-title">List Name *</label>
							<input type="text" id="bd-list-title" name="title" required
								placeholder="e.g., Best Date Night Spots">
						</div>
						<div class="bd-form-group">
							<label for="bd-list-description">Description</label>
							<textarea id="bd-list-description" name="description" rows="3"
								placeholder="What's this list about?"></textarea>
						</div>
						<div class="bd-form-group">
							<label>Visibility</label>
							<div class="bd-visibility-options">
								<label class="bd-visibility-option">
									<input type="radio" name="visibility" value="private" checked>
									<span class="bd-visibility-card">
										<i class="fas fa-lock"></i>
										<strong>Private</strong>
										<span>Only you can see</span>
									</span>
								</label>
								<label class="bd-visibility-option">
									<input type="radio" name="visibility" value="unlisted">
									<span class="bd-visibility-card">
										<i class="fas fa-link"></i>
										<strong>Unlisted</strong>
										<span>Anyone with link</span>
									</span>
								</label>
								<label class="bd-visibility-option">
									<input type="radio" name="visibility" value="public">
									<span class="bd-visibility-card">
										<i class="fas fa-globe"></i>
										<strong>Public</strong>
										<span>Visible to everyone</span>
									</span>
								</label>
							</div>
						</div>
						<div class="bd-form-actions">
							<button type="button" class="bd-btn bd-btn-secondary bd-modal-close">Cancel</button>
							<button type="submit" class="bd-btn bd-btn-primary">Create List</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render edit list modal
	 */
	private static function render_edit_list_modal( $list ) {
		ob_start();
		?>
		<div class="bd-modal bd-edit-list-modal" style="display: none;">
			<div class="bd-modal-overlay"></div>
			<div class="bd-modal-content">
				<div class="bd-modal-header">
					<h3><i class="fas fa-edit"></i> Edit List</h3>
					<button type="button" class="bd-modal-close">&times;</button>
				</div>
				<div class="bd-modal-body">
					<form class="bd-edit-list-form" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
						<div class="bd-form-group">
							<label for="bd-edit-title">List Name *</label>
							<input type="text" id="bd-edit-title" name="title" required
								value="<?php echo esc_attr( $list['title'] ); ?>">
						</div>
						<div class="bd-form-group">
							<label for="bd-edit-description">Description</label>
							<textarea id="bd-edit-description" name="description" rows="3"><?php echo esc_textarea( $list['description'] ); ?></textarea>
						</div>
						<div class="bd-form-group">
							<label>Visibility</label>
							<div class="bd-visibility-options">
								<label class="bd-visibility-option">
									<input type="radio" name="visibility" value="private" <?php checked( $list['visibility'], 'private' ); ?>>
									<span class="bd-visibility-card">
										<i class="fas fa-lock"></i>
										<strong>Private</strong>
										<span>Only you can see</span>
									</span>
								</label>
								<label class="bd-visibility-option">
									<input type="radio" name="visibility" value="unlisted" <?php checked( $list['visibility'], 'unlisted' ); ?>>
									<span class="bd-visibility-card">
										<i class="fas fa-link"></i>
										<strong>Unlisted</strong>
										<span>Anyone with link</span>
									</span>
								</label>
								<label class="bd-visibility-option">
									<input type="radio" name="visibility" value="public" <?php checked( $list['visibility'], 'public' ); ?>>
									<span class="bd-visibility-card">
										<i class="fas fa-globe"></i>
										<strong>Public</strong>
										<span>Visible to everyone</span>
									</span>
								</label>
							</div>
						</div>
						<div class="bd-form-actions">
							<button type="button" class="bd-btn bd-btn-secondary bd-modal-close">Cancel</button>
							<button type="submit" class="bd-btn bd-btn-primary">Save Changes</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render confirmation modal for destructive actions
	 *
	 * @return string HTML output.
	 */
	private static function render_confirm_modal() {
		ob_start();
		?>
		<div class="bd-modal bd-confirm-modal" style="display: none;">
			<div class="bd-modal-overlay"></div>
			<div class="bd-modal-content">
				<div class="bd-modal-header">
					<h3><i class="fas fa-exclamation-triangle"></i> <span class="bd-confirm-title">Confirm</span></h3>
					<button type="button" class="bd-modal-close">&times;</button>
				</div>
				<div class="bd-modal-body">
					<p class="bd-confirm-message">Are you sure?</p>
				</div>
				<div class="bd-form-actions">
					<button type="button" class="bd-btn bd-btn-secondary bd-confirm-cancel">Cancel</button>
					<button type="button" class="bd-btn bd-btn-danger bd-confirm-ok">Delete</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render share modal
	 */
	public static function render_share_modal( $list ) {
		$share_data = ListManager::get_share_data( $list );

		ob_start();
		?>
		<div class="bd-modal bd-share-modal" style="display: none;" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
			<div class="bd-modal-overlay"></div>
			<div class="bd-modal-content bd-share-modal-content">
				<div class="bd-modal-header">
					<h3><i class="fas fa-share-alt"></i> Share "<?php echo esc_html( $list['title'] ); ?>"</h3>
					<button type="button" class="bd-modal-close">&times;</button>
				</div>

				<!-- Share Preview -->
				<div class="bd-share-preview">
					<div class="bd-share-preview-image">
						<?php
						$preview_image = '';
						if ( ! empty( $share_data['image_url'] ) ) {
							$preview_image = $share_data['image_url'];
						} elseif ( ! empty( $list['cover_image'] ) ) {
							$preview_image = $list['cover_image'];
						}

						if ( $preview_image ) :
							?>
							<img src="<?php echo esc_url( $preview_image ); ?>" alt="<?php echo esc_attr( $list['title'] ); ?>">
						<?php else : ?>
							<div style="width:100%;height:100%;background:var(--bd-cream);display:flex;align-items:center;justify-content:center;">
								<i class="fas fa-list" style="font-size:24px;color:var(--bd-steel);"></i>
							</div>
						<?php endif; ?>
					</div>
					<div class="bd-share-preview-info">
						<h4><?php echo esc_html( $list['title'] ); ?></h4>
						<p><?php echo esc_html( $share_data['item_count'] ); ?> places<?php echo $share_data['author'] ? ' by ' . esc_html( $share_data['author'] ) : ''; ?></p>
					</div>
				</div>

				<!-- Tab Navigation -->
				<div class="bd-share-tabs">
					<button type="button" class="bd-share-tab bd-share-tab-active" data-tab="social">
						<i class="fas fa-share"></i> Social
					</button>
					<button type="button" class="bd-share-tab" data-tab="link">
						<i class="fas fa-link"></i> Link
					</button>
					<button type="button" class="bd-share-tab" data-tab="embed">
						<i class="fas fa-code"></i> Embed
					</button>
					<button type="button" class="bd-share-tab" data-tab="qr">
						<i class="fas fa-qrcode"></i> QR Code
					</button>
				</div>

				<!-- Tab Content -->
				<div class="bd-share-tab-content">

					<!-- Social Tab -->
					<div class="bd-share-tab-pane bd-share-tab-pane-active" data-tab="social">
						<div class="bd-share-buttons-grid">
							<a href="<?php echo esc_url( $share_data['share_links']['facebook'] ); ?>"
								target="_blank" rel="noopener" class="bd-share-button bd-share-facebook" data-platform="facebook">
								<i class="fab fa-facebook-f"></i>
								<span>Facebook</span>
							</a>
							<a href="<?php echo esc_url( $share_data['share_links']['twitter'] ); ?>"
								target="_blank" rel="noopener" class="bd-share-button bd-share-twitter" data-platform="twitter">
								<i class="fab fa-twitter"></i>
								<span>Twitter</span>
							</a>
							<a href="<?php echo esc_url( $share_data['share_links']['pinterest'] ); ?>"
								target="_blank" rel="noopener" class="bd-share-button bd-share-pinterest" data-platform="pinterest">
								<i class="fab fa-pinterest"></i>
								<span>Pinterest</span>
							</a>
							<a href="<?php echo esc_url( $share_data['share_links']['linkedin'] ); ?>"
								target="_blank" rel="noopener" class="bd-share-button bd-share-linkedin" data-platform="linkedin">
								<i class="fab fa-linkedin-in"></i>
								<span>LinkedIn</span>
							</a>
							<a href="<?php echo esc_url( $share_data['share_links']['whatsapp'] ); ?>"
								target="_blank" rel="noopener" class="bd-share-button bd-share-whatsapp" data-platform="whatsapp">
								<i class="fab fa-whatsapp"></i>
								<span>WhatsApp</span>
							</a>
							<a href="<?php echo esc_url( $share_data['share_links']['email'] ); ?>"
								class="bd-share-button bd-share-email" data-platform="email">
								<i class="fas fa-envelope"></i>
								<span>Email</span>
							</a>
						</div>
					</div>

					<!-- Link Tab -->
					<div class="bd-share-tab-pane" data-tab="link">
						<div class="bd-share-link-section">
							<label>Share URL</label>
							<div class="bd-share-input-group">
								<input type="text" value="<?php echo esc_url( $share_data['url'] ); ?>" readonly
									class="bd-share-url-input" id="bd-share-url-<?php echo esc_attr( $list['id'] ); ?>">
								<button type="button" class="bd-btn bd-btn-primary bd-copy-btn" data-copy-target="bd-share-url-<?php echo esc_attr( $list['id'] ); ?>">
									<i class="fas fa-copy"></i> Copy
								</button>
							</div>
						</div>
					</div>

					<!-- Embed Tab -->
					<div class="bd-share-tab-pane" data-tab="embed">
						<div class="bd-share-embed-section">
							<label>Embed Code</label>
							<p class="bd-share-help-text">Copy this code to embed this list on your website.</p>
							<div class="bd-share-input-group">
								<textarea readonly class="bd-share-embed-input" id="bd-share-embed-<?php echo esc_attr( $list['id'] ); ?>" rows="3"><?php echo esc_textarea( $share_data['embed_code'] ); ?></textarea>
								<button type="button" class="bd-btn bd-btn-primary bd-copy-btn" data-copy-target="bd-share-embed-<?php echo esc_attr( $list['id'] ); ?>">
									<i class="fas fa-copy"></i> Copy
								</button>
							</div>
						</div>
					</div>

					<!-- QR Code Tab -->
					<div class="bd-share-tab-pane" data-tab="qr">
						<div class="bd-share-qr-section">
							<p class="bd-share-help-text">Scan this QR code to open the list on any device.</p>
							<?php
							$qr_size     = 200;
							$list_url    = ! empty( $share_data['url'] ) ? $share_data['url'] : ListManager::get_list_url( $list );
							$qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $qr_size . 'x' . $qr_size . '&data=' . rawurlencode( $list_url ) . '&format=png';
							?>
							<div class="bd-qr-code-container">
								<img src="<?php echo esc_url( $qr_code_url ); ?>"
									alt="QR Code for <?php echo esc_attr( $list['title'] ); ?>"
									class="bd-qr-code-image" width="200" height="200" loading="lazy">
							</div>
							<div class="bd-qr-actions">
								<a href="<?php echo esc_url( $qr_code_url ); ?>"
									download="<?php echo esc_attr( sanitize_title( $list['title'] ) ); ?>-qr-code.png"
									class="bd-btn bd-btn-secondary">
									<i class="fas fa-download"></i> Download QR Code
								</a>
							</div>
							<p class="bd-share-qr-tip">
								<i class="fas fa-lightbulb"></i> Great for print materials, event handouts, or business cards!
							</p>
						</div>
					</div>

				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render login required message
	 */
	private static function render_login_required( $feature = 'this feature' ) {
		ob_start();
		?>
		<div class="bd-login-required">
			<div class="bd-login-icon"><i class="fas fa-lock"></i></div>
			<h2>Please Log In</h2>
			<p>You need to be logged in to access <?php echo esc_html( $feature ); ?>.</p>
			<div class="bd-login-actions">
				<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bd-btn bd-btn-primary">
					Log In
				</a>
				<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="bd-btn bd-btn-secondary">
					Create Account
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
