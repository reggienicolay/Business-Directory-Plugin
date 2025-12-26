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

use BD\Lists\ListManager;
use BD\Gamification\BadgeSystem;
use BD\Gamification\ActivityTracker;

class ListDisplay {


	/**
	 * Initialize shortcodes and hooks
	 */
	public static function init() {
		// Shortcodes
		add_shortcode( 'bd_my_lists', array( __CLASS__, 'render_my_lists' ) );
		add_shortcode( 'bd_public_lists', array( __CLASS__, 'render_public_lists' ) );
		add_shortcode( 'bd_list', array( __CLASS__, 'render_single_list' ) );
		add_shortcode( 'bd_save_button', array( __CLASS__, 'render_save_button' ) );

		// Add save button to business pages
		add_action( 'bd_after_business_title', array( __CLASS__, 'output_save_button' ) );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public static function enqueue_assets() {
		// Only load on relevant pages
		if ( ! is_singular( 'bd_business' ) && ! self::is_lists_page() ) {
			return;
		}

		// Design tokens (fonts, colors)
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
			'1.0.0'
		);

		wp_enqueue_script(
			'bd-lists',
			plugins_url( 'assets/js/lists.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);

		wp_localize_script(
			'bd-lists',
			'bdLists',
			array(
				'restUrl'    => rest_url( 'bd/v1/' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'isLoggedIn' => is_user_logged_in(),
				'loginUrl'   => wp_login_url( get_permalink() ),
				'strings'    => array(
					'saved'         => __( 'Saved!', 'developer-developer-developer' ),
					'removed'       => __( 'Removed', 'developer-developer-developer' ),
					'error'         => __( 'Something went wrong', 'developer-developer-developer' ),
					'loginRequired' => __( 'Please log in to save businesses', 'developer-developer-developer' ),
					'createList'    => __( 'Create New List', 'developer-developer-developer' ),
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
				'style'       => 'button', // button, icon, text
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
				<span class="bd-save-icon"><?php echo $is_saved ? '❤️' : '🤍'; ?></span>
				<?php if ( 'icon' !== $atts['style'] ) : ?>
					<span class="bd-save-text"><?php echo $is_saved ? 'Saved' : 'Save'; ?></span>
				<?php endif; ?>
			</button>

			<?php if ( $is_logged_in ) : ?>
				<!-- Save Modal (hidden by default) -->
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
				<div class="bd-list-stat">
					<span class="bd-list-stat-value"><?php echo count( $lists ); ?></span>
					<span class="bd-list-stat-label">Lists</span>
				</div>
				<div class="bd-list-stat">
					<span class="bd-list-stat-value">
						<?php
						$total_items = array_sum( array_column( $lists, 'item_count' ) );
						echo esc_html( $total_items );
						?>
					</span>
					<span class="bd-list-stat-label">Saved Businesses</span>
				</div>
			</div>

			<!-- Lists Grid -->
			<?php if ( empty( $lists ) ) : ?>
				<div class="bd-lists-empty">
					<div class="bd-empty-icon">📋</div>
					<h3>No lists yet</h3>
					<p>Create your first list to start saving businesses!</p>
					<button type="button" class="bd-btn bd-btn-primary bd-create-list-open">
						Create Your First List
					</button>
				</div>
			<?php else : ?>
				<div class="bd-lists-grid">
					<?php foreach ( $lists as $list ) : ?>
						<?php echo self::render_list_card( $list, true ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Create List Modal -->
			<?php echo self::render_create_list_modal(); ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render public lists browse page [bd_public_lists]
	 */
	public static function render_public_lists( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'per_page'      => 12,
				'show_featured' => 'yes',
			),
			$atts
		);

		$page = isset( $_GET['list_page'] ) ? max( 1, intval( $_GET['list_page'] ) ) : 1;

		// Get featured lists
		$featured = array();
		if ( 'yes' === $atts['show_featured'] ) {
			$featured_result = ListManager::get_public_lists(
				array(
					'featured' => true,
					'per_page' => 4,
				)
			);
			$featured        = $featured_result['lists'];
		}

		// Get all public lists
		$result = ListManager::get_public_lists(
			array(
				'per_page' => $atts['per_page'],
				'page'     => $page,
			)
		);

		ob_start();
		?>
		<div class="bd-public-lists-page">

			<!-- Header -->
			<div class="bd-lists-header">
				<div class="bd-lists-header-content">
					<h1><i class="fas fa-compass"></i> Community Lists</h1>
					<p>Discover curated collections from our community</p>
				</div>
			</div>

			<!-- Featured Lists -->
			<?php if ( ! empty( $featured ) ) : ?>
				<div class="bd-featured-lists">
					<h2><i class="fas fa-star"></i> Featured Lists</h2>
					<div class="bd-featured-lists-grid">
						<?php foreach ( $featured as $list ) : ?>
							<?php echo self::render_list_card( $list, false, true ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- All Lists -->
			<div class="bd-all-lists">
				<h2>All Lists</h2>

				<?php if ( empty( $result['lists'] ) ) : ?>
					<div class="bd-lists-empty">
						<div class="bd-empty-icon">📋</div>
						<h3>No public lists yet</h3>
						<p>Be the first to share your favorites with the community!</p>
					</div>
				<?php else : ?>
					<div class="bd-lists-grid">
						<?php foreach ( $result['lists'] as $list ) : ?>
							<?php echo self::render_list_card( $list ); ?>
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
									'prev_text' => '&laquo; Previous',
									'next_text' => 'Next &raquo;',
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

		// Try to get list from URL param, attribute, or slug
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

		// Check visibility
		$current_user_id = get_current_user_id();
		$is_owner        = ( (int) $list['user_id'] === $current_user_id );

		if ( 'private' === $list['visibility'] && ! $is_owner ) {
			return '<div class="bd-list-private"><p>This list is private.</p></div>';
		}

		// Increment view count
		if ( ! $is_owner ) {
			ListManager::increment_view_count( $list['id'] );
		}

		// Get items
		$items  = ListManager::get_list_items( $list['id'] );
		$author = get_userdata( $list['user_id'] );

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
					</div>

					<!-- Share Buttons -->
					<div class="bd-list-share">
						<span>Share:</span>
						<?php echo self::render_share_buttons( $list ); ?>
					</div>
				</div>
			</div>

			<!-- Owner Actions -->
			<?php if ( $is_owner ) : ?>
				<div class="bd-list-owner-actions">
					<button type="button" class="bd-btn bd-btn-secondary bd-edit-list-btn">
						<i class="fas fa-edit"></i> Edit List
					</button>
					<span class="bd-visibility-badge bd-visibility-<?php echo esc_attr( $list['visibility'] ); ?>">
						<?php echo esc_html( ucfirst( $list['visibility'] ) ); ?>
					</span>
				</div>
			<?php endif; ?>

			<!-- List Items -->
			<?php if ( empty( $items ) ) : ?>
				<div class="bd-list-empty">
					<div class="bd-empty-icon">📍</div>
					<h3>This list is empty</h3>
					<?php if ( $is_owner ) : ?>
						<p>Start adding businesses to your list!</p>
						<a href="<?php echo esc_url( home_url( '/local/' ) ); ?>" class="bd-btn bd-btn-primary">
							Browse Businesses
						</a>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="bd-list-items <?php echo $is_owner ? 'bd-sortable' : ''; ?>">
					<?php foreach ( $items as $index => $item ) : ?>
						<?php $business = $item['business']; ?>
						<?php
						if ( ! $business ) {
							continue;}
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
									<div class="bd-no-image">📍</div>
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
										<?php echo str_repeat( '★', round( $business['rating'] ) ); ?>
										<?php echo str_repeat( '☆', 5 - round( $business['rating'] ) ); ?>
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

							<?php if ( $is_owner ) : ?>
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
			<?php endif; ?>

			<!-- Edit Modal (for owners) -->
			<?php if ( $is_owner ) : ?>
				<?php echo self::render_edit_list_modal( $list ); ?>
			<?php endif; ?>

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

		ob_start();
		?>
		<div class="bd-list-card <?php echo $is_featured ? 'bd-list-featured' : ''; ?>" data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
			<a href="<?php echo esc_url( $url ); ?>" class="bd-list-card-link">
				<div class="bd-list-card-cover">
					<?php if ( $cover_image ) : ?>
						<img src="<?php echo esc_url( $cover_image ); ?>" alt="<?php echo esc_attr( $list['title'] ); ?>">
					<?php else : ?>
						<div class="bd-list-card-placeholder">📋</div>
					<?php endif; ?>

					<?php if ( $is_featured ) : ?>
						<span class="bd-list-featured-badge"><i class="fas fa-star"></i></span>
					<?php endif; ?>

					<span class="bd-list-card-count"><?php echo esc_html( $list['item_count'] ); ?> places</span>
				</div>

				<div class="bd-list-card-body">
					<h3><?php echo esc_html( $list['title'] ); ?></h3>

					<?php if ( ! empty( $list['description'] ) ) : ?>
						<p><?php echo esc_html( wp_trim_words( $list['description'], 12 ) ); ?></p>
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
								'public'   => '🌍',
								'unlisted' => '🔗',
								'private'  => '🔒',
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
							<label for="bd-list-visibility">Visibility</label>
							<select id="bd-list-visibility" name="visibility">
								<option value="private">🔒 Private - Only you can see</option>
								<option value="unlisted">🔗 Unlisted - Anyone with link can see</option>
								<option value="public">🌍 Public - Visible in community lists</option>
							</select>
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
							<label for="bd-edit-list-title">List Name *</label>
							<input type="text" id="bd-edit-list-title" name="title" required
								value="<?php echo esc_attr( $list['title'] ); ?>">
						</div>
						<div class="bd-form-group">
							<label for="bd-edit-list-description">Description</label>
							<textarea id="bd-edit-list-description" name="description" rows="3"><?php echo esc_textarea( $list['description'] ); ?></textarea>
						</div>
						<div class="bd-form-group">
							<label for="bd-edit-list-visibility">Visibility</label>
							<select id="bd-edit-list-visibility" name="visibility">
								<option value="private" <?php selected( $list['visibility'], 'private' ); ?>>🔒 Private</option>
								<option value="unlisted" <?php selected( $list['visibility'], 'unlisted' ); ?>>🔗 Unlisted</option>
								<option value="public" <?php selected( $list['visibility'], 'public' ); ?>>🌍 Public</option>
							</select>
						</div>
						<div class="bd-form-actions">
							<button type="button" class="bd-btn bd-btn-danger bd-delete-list-btn"
								data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
								Delete List
							</button>
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
	 * Render share buttons
	 */
	private static function render_share_buttons( $list ) {
		$url   = urlencode( ListManager::get_list_url( $list ) );
		$title = urlencode( $list['title'] );

		ob_start();
		?>
		<div class="bd-share-buttons">
			<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>"
				target="_blank" class="bd-share-btn bd-share-facebook" title="Share on Facebook">
				<i class="fab fa-facebook-f"></i>
			</a>
			<a href="https://twitter.com/intent/tweet?url=<?php echo $url; ?>&text=<?php echo $title; ?>"
				target="_blank" class="bd-share-btn bd-share-twitter" title="Share on Twitter">
				<i class="fab fa-twitter"></i>
			</a>
			<a href="https://pinterest.com/pin/create/button/?url=<?php echo $url; ?>&description=<?php echo $title; ?>"
				target="_blank" class="bd-share-btn bd-share-pinterest" title="Share on Pinterest">
				<i class="fab fa-pinterest"></i>
			</a>
			<button type="button" class="bd-share-btn bd-share-copy" data-url="<?php echo esc_attr( ListManager::get_list_url( $list ) ); ?>" title="Copy Link">
				<i class="fas fa-link"></i>
			</button>
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
			<div class="bd-login-icon">🔐</div>
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
