<?php
/**
 * Guides Admin
 *
 * Admin interface for managing Community Guides.
 * Allows admins to designate users as Guides and manage guide-specific fields.
 *
 * @package BusinessDirectory
 * @subpackage Admin
 * @version 1.1.0
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GuidesAdmin
 */
class GuidesAdmin {

	/**
	 * Initialize the admin interface
	 */
	public static function init() {
		// Add admin menu.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );

		// Add Guide fields to user profile in admin.
		add_action( 'show_user_profile', array( __CLASS__, 'render_guide_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_guide_fields' ) );

		// Save Guide fields.
		add_action( 'personal_options_update', array( __CLASS__, 'save_guide_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_guide_fields' ) );

		// Add Guide column to users list.
		add_filter( 'manage_users_columns', array( __CLASS__, 'add_guide_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_guide_column' ), 10, 3 );

		// Quick toggle AJAX.
		add_action( 'wp_ajax_bd_toggle_guide_status', array( __CLASS__, 'ajax_toggle_guide' ) );

		// Update order AJAX.
		add_action( 'wp_ajax_bd_update_guide_order', array( __CLASS__, 'ajax_update_order' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add admin menu under Business Directory
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Community Guides', 'business-directory' ),
			__( 'Guides', 'business-directory' ),
			'manage_options',
			'bd-guides',
			array( __CLASS__, 'render_guides_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_admin_assets( $hook ) {
		// Load on guides page and user edit pages.
		if ( 'bd_business_page_bd-guides' !== $hook && 'user-edit.php' !== $hook && 'profile.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bd-guides-admin',
			BD_PLUGIN_URL . 'assets/css/admin-guides.css',
			array(),
			BD_VERSION
		);

		wp_enqueue_script(
			'bd-guides-admin',
			BD_PLUGIN_URL . 'assets/js/admin-guides.js',
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		wp_localize_script(
			'bd-guides-admin',
			'bdGuidesAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_guides_admin' ),
			)
		);
	}

	/**
	 * Render the Guides admin page
	 */
	public static function render_guides_page() {
		$notice = '';

		// FIX: Process form BEFORE rendering (so dropdown updates immediately).
		if ( isset( $_POST['bd_add_guide'] ) ) {
			// Proper nonce verification with wp_unslash.
			$nonce = isset( $_POST['bd_add_guide_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['bd_add_guide_nonce'] ) ) : '';

			if ( wp_verify_nonce( $nonce, 'bd_add_guide' ) ) {
				$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
				if ( $user_id && get_userdata( $user_id ) ) {
					update_user_meta( $user_id, 'bd_is_guide', '1' );
					update_user_meta( $user_id, 'bd_guide_order', 99 );
					$notice = '<div class="notice notice-success"><p>' . esc_html__( 'User has been added as a Guide!', 'business-directory' ) . '</p></div>';
				}
			}
		}

		// Get all guides (after potential addition).
		$guides = self::get_all_guides();

		// FIX: Batch query for review counts instead of N+1 queries.
		$review_counts = self::get_guides_review_counts( wp_list_pluck( $guides, 'user_id' ) );

		// Get all users for the "Add Guide" dropdown.
		$all_users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		// Get existing guide IDs to filter dropdown.
		$guide_ids = wp_list_pluck( $guides, 'user_id' );
		?>
		<div class="wrap bd-guides-admin">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-groups"></span>
				<?php esc_html_e( 'Community Guides', 'business-directory' ); ?>
			</h1>

			<p class="bd-page-description">
				<?php esc_html_e( 'Manage the team members featured on your Guides page. Guides have enhanced public profiles and appear on the community Guides page.', 'business-directory' ); ?>
			</p>

			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>

			<!-- Add New Guide -->
			<div class="bd-add-guide-box">
				<h2><?php esc_html_e( 'Add New Guide', 'business-directory' ); ?></h2>
				<form method="post" action="">
					<?php wp_nonce_field( 'bd_add_guide', 'bd_add_guide_nonce' ); ?>
					<div class="bd-add-guide-form">
						<select name="user_id" id="bd-add-guide-user" required>
							<option value=""><?php esc_html_e( '— Select User —', 'business-directory' ); ?></option>
							<?php foreach ( $all_users as $user ) : ?>
								<?php
								// Skip if already a guide.
								if ( in_array( $user->ID, $guide_ids, true ) ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $user->ID ); ?>">
									<?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_email ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<button type="submit" name="bd_add_guide" class="button button-primary">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add as Guide', 'business-directory' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Current Guides -->
			<div class="bd-guides-list">
				<h2><?php esc_html_e( 'Current Guides', 'business-directory' ); ?></h2>

				<?php if ( empty( $guides ) ) : ?>
					<div class="bd-no-guides">
						<p><?php esc_html_e( 'No guides yet. Add your first guide above!', 'business-directory' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th class="column-order"><?php esc_html_e( 'Order', 'business-directory' ); ?></th>
								<th class="column-avatar"><?php esc_html_e( 'Photo', 'business-directory' ); ?></th>
								<th class="column-name"><?php esc_html_e( 'Name', 'business-directory' ); ?></th>
								<th class="column-title"><?php esc_html_e( 'Title', 'business-directory' ); ?></th>
								<th class="column-cities"><?php esc_html_e( 'Cities', 'business-directory' ); ?></th>
								<th class="column-stats"><?php esc_html_e( 'Activity', 'business-directory' ); ?></th>
								<th class="column-actions"><?php esc_html_e( 'Actions', 'business-directory' ); ?></th>
							</tr>
						</thead>
						<tbody id="bd-guides-sortable">
							<?php foreach ( $guides as $guide ) : ?>
								<?php
								$user         = get_userdata( $guide['user_id'] );
								$title        = get_user_meta( $guide['user_id'], 'bd_guide_title', true );
								$cities       = get_user_meta( $guide['user_id'], 'bd_guide_cities', true );
								$profile_url  = home_url( '/profile/' . $user->user_nicename . '/' );
								$review_count = isset( $review_counts[ $guide['user_id'] ] ) ? $review_counts[ $guide['user_id'] ] : 0;
								?>
								<tr data-user-id="<?php echo esc_attr( $guide['user_id'] ); ?>">
									<td class="column-order">
										<span class="bd-drag-handle dashicons dashicons-move"></span>
										<input type="number" class="bd-guide-order" value="<?php echo esc_attr( $guide['order'] ); ?>" min="0" max="99">
									</td>
									<td class="column-avatar">
										<?php echo get_avatar( $guide['user_id'], 50 ); ?>
									</td>
									<td class="column-name">
										<strong>
											<a href="<?php echo esc_url( get_edit_user_link( $guide['user_id'] ) ); ?>">
												<?php echo esc_html( $user->display_name ); ?>
											</a>
										</strong>
										<div class="row-actions">
											<span class="edit">
												<a href="<?php echo esc_url( get_edit_user_link( $guide['user_id'] ) ); ?>">
													<?php esc_html_e( 'Edit', 'business-directory' ); ?>
												</a> |
											</span>
											<span class="view">
												<a href="<?php echo esc_url( $profile_url ); ?>" target="_blank">
													<?php esc_html_e( 'View Profile', 'business-directory' ); ?>
												</a> |
											</span>
											<span class="remove">
												<a href="#" class="bd-remove-guide" data-user-id="<?php echo esc_attr( $guide['user_id'] ); ?>">
													<?php esc_html_e( 'Remove', 'business-directory' ); ?>
												</a>
											</span>
										</div>
									</td>
									<td class="column-title">
										<?php echo esc_html( $title ?: '—' ); ?>
									</td>
									<td class="column-cities">
										<?php
										if ( ! empty( $cities ) && is_array( $cities ) ) {
											echo esc_html( implode( ', ', $cities ) );
										} else {
											echo '—';
										}
										?>
									</td>
									<td class="column-stats">
										<span class="bd-stat">
											<span class="dashicons dashicons-star-filled"></span>
											<?php
											/* translators: %d: Number of reviews */
											printf( esc_html__( '%d reviews', 'business-directory' ), intval( $review_count ) );
											?>
										</span>
									</td>
									<td class="column-actions">
										<a href="<?php echo esc_url( get_edit_user_link( $guide['user_id'] ) . '#bd-guide-settings' ); ?>" class="button button-small">
											<span class="dashicons dashicons-admin-generic"></span>
											<?php esc_html_e( 'Settings', 'business-directory' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Shortcode Info -->
			<div class="bd-shortcode-info">
				<h2><?php esc_html_e( 'Display Guides', 'business-directory' ); ?></h2>
				<p><?php esc_html_e( 'Use these shortcodes to display guides on your site:', 'business-directory' ); ?></p>
				<table class="bd-shortcode-table">
					<tr>
						<td><code>[bd_guides]</code></td>
						<td><?php esc_html_e( 'Display all guides in a grid', 'business-directory' ); ?></td>
					</tr>
					<tr>
						<td><code>[bd_guides limit="3"]</code></td>
						<td><?php esc_html_e( 'Display only 3 guides', 'business-directory' ); ?></td>
					</tr>
					<tr>
						<td><code>[bd_guides city="Livermore"]</code></td>
						<td><?php esc_html_e( 'Display guides for a specific city', 'business-directory' ); ?></td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Get review counts for multiple guides in single query
	 *
	 * FIX: Batch query instead of N+1 pattern.
	 *
	 * @param array $user_ids Array of user IDs.
	 * @return array Associative array of user_id => review_count.
	 */
	private static function get_guides_review_counts( $user_ids ) {
		if ( empty( $user_ids ) ) {
			return array();
		}

		global $wpdb;
		$reviews_table = $wpdb->prefix . 'bd_reviews';

		// Check if table exists first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $reviews_table )
		);

		if ( ! $table_exists ) {
			return array_fill_keys( $user_ids, 0 );
		}

		// Build placeholders for IN clause.
		$placeholders = implode( ', ', array_fill( 0, count( $user_ids ), '%d' ) );

		// Single query for all guides.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) as review_count 
				FROM {$reviews_table} 
				WHERE user_id IN ({$placeholders}) AND status = 'approved' 
				GROUP BY user_id",
				...$user_ids
			),
			ARRAY_A
		);

		// Map results.
		$counts = array_fill_keys( $user_ids, 0 );
		foreach ( $results as $row ) {
			$counts[ $row['user_id'] ] = intval( $row['review_count'] );
		}

		return $counts;
	}

	/**
	 * Render guide fields on user profile page
	 *
	 * @param \WP_User $user User object.
	 */
	public static function render_guide_fields( $user ) {
		// Only show to admins.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_guide     = get_user_meta( $user->ID, 'bd_is_guide', true );
		$guide_title  = get_user_meta( $user->ID, 'bd_guide_title', true );
		$guide_quote  = get_user_meta( $user->ID, 'bd_guide_quote', true );
		$guide_cities = get_user_meta( $user->ID, 'bd_guide_cities', true );
		$guide_order  = get_user_meta( $user->ID, 'bd_guide_order', true );
		$public_bio   = get_user_meta( $user->ID, 'bd_public_bio', true );

		// Available cities (Tri-Valley).
		$cities = array( 'Livermore', 'Pleasanton', 'Dublin', 'San Ramon', 'Danville' );

		// Allow filtering cities.
		$cities = apply_filters( 'bd_guide_cities', $cities );
		?>
		<h2 id="bd-guide-settings"><?php esc_html_e( 'Community Guide Settings', 'business-directory' ); ?></h2>
		<p class="description"><?php esc_html_e( 'These settings control the user\'s Guide status and public profile.', 'business-directory' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Guide Status', 'business-directory' ); ?></th>
				<td>
					<label for="bd_is_guide">
						<input type="checkbox" name="bd_is_guide" id="bd_is_guide" value="1" <?php checked( $is_guide ); ?>>
						<?php esc_html_e( 'This user is a Community Guide', 'business-directory' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Guides are featured on the Guides page and have enhanced public profiles.', 'business-directory' ); ?></p>
				</td>
			</tr>
			<tr class="bd-guide-field">
				<th scope="row"><label for="bd_guide_title"><?php esc_html_e( 'Guide Title', 'business-directory' ); ?></label></th>
				<td>
					<input type="text" name="bd_guide_title" id="bd_guide_title" value="<?php echo esc_attr( $guide_title ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'e.g., "Founder", "Community Manager", "Wine Country Expert"', 'business-directory' ); ?></p>
				</td>
			</tr>
			<tr class="bd-guide-field">
				<th scope="row"><label for="bd_public_bio"><?php esc_html_e( 'Public Bio', 'business-directory' ); ?></label></th>
				<td>
					<textarea name="bd_public_bio" id="bd_public_bio" rows="4" class="large-text"><?php echo esc_textarea( $public_bio ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Extended bio shown on their public profile. Supports basic formatting.', 'business-directory' ); ?></p>
				</td>
			</tr>
			<tr class="bd-guide-field">
				<th scope="row"><label for="bd_guide_quote"><?php esc_html_e( 'Featured Quote', 'business-directory' ); ?></label></th>
				<td>
					<textarea name="bd_guide_quote" id="bd_guide_quote" rows="2" class="large-text"><?php echo esc_textarea( $guide_quote ); ?></textarea>
					<p class="description"><?php esc_html_e( 'A quote or tagline displayed on their profile. e.g., "I\'ve watched this community grow for over a decade..."', 'business-directory' ); ?></p>
				</td>
			</tr>
			<tr class="bd-guide-field">
				<th scope="row"><?php esc_html_e( 'Cities / Areas', 'business-directory' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $cities as $city ) : ?>
							<label style="display: inline-block; margin-right: 15px;">
								<input type="checkbox" name="bd_guide_cities[]" value="<?php echo esc_attr( $city ); ?>" 
									<?php checked( is_array( $guide_cities ) && in_array( $city, $guide_cities, true ) ); ?>>
								<?php echo esc_html( $city ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Which cities does this guide specialize in?', 'business-directory' ); ?></p>
				</td>
			</tr>
			<tr class="bd-guide-field">
				<th scope="row"><label for="bd_guide_order"><?php esc_html_e( 'Display Order', 'business-directory' ); ?></label></th>
				<td>
					<input type="number" name="bd_guide_order" id="bd_guide_order" value="<?php echo esc_attr( $guide_order ?: 10 ); ?>" class="small-text" min="0" max="99">
					<p class="description"><?php esc_html_e( 'Lower numbers appear first on the Guides page.', 'business-directory' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save guide fields
	 *
	 * @param int $user_id User ID.
	 */
	public static function save_guide_fields( $user_id ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Verify nonce (from user profile form).
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {
			return;
		}

		// Save guide status.
		$is_guide = isset( $_POST['bd_is_guide'] ) ? '1' : '';
		update_user_meta( $user_id, 'bd_is_guide', $is_guide );

		// Save guide-specific fields.
		if ( isset( $_POST['bd_guide_title'] ) ) {
			update_user_meta( $user_id, 'bd_guide_title', sanitize_text_field( wp_unslash( $_POST['bd_guide_title'] ) ) );
		}

		if ( isset( $_POST['bd_public_bio'] ) ) {
			update_user_meta( $user_id, 'bd_public_bio', sanitize_textarea_field( wp_unslash( $_POST['bd_public_bio'] ) ) );
		}

		if ( isset( $_POST['bd_guide_quote'] ) ) {
			update_user_meta( $user_id, 'bd_guide_quote', sanitize_textarea_field( wp_unslash( $_POST['bd_guide_quote'] ) ) );
		}

		if ( isset( $_POST['bd_guide_cities'] ) && is_array( $_POST['bd_guide_cities'] ) ) {
			$cities = array_map( 'sanitize_text_field', wp_unslash( $_POST['bd_guide_cities'] ) );
			update_user_meta( $user_id, 'bd_guide_cities', $cities );
		} else {
			delete_user_meta( $user_id, 'bd_guide_cities' );
		}

		if ( isset( $_POST['bd_guide_order'] ) ) {
			update_user_meta( $user_id, 'bd_guide_order', absint( $_POST['bd_guide_order'] ) );
		}
	}

	/**
	 * Add Guide column to users list
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_guide_column( $columns ) {
		$columns['bd_guide'] = __( 'Guide', 'business-directory' );
		return $columns;
	}

	/**
	 * Render Guide column content
	 *
	 * @param string $output      Column output.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 * @return string Column content.
	 */
	public static function render_guide_column( $output, $column_name, $user_id ) {
		if ( 'bd_guide' !== $column_name ) {
			return $output;
		}

		$is_guide = get_user_meta( $user_id, 'bd_is_guide', true );
		if ( $is_guide ) {
			$title = get_user_meta( $user_id, 'bd_guide_title', true );
			return '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' . esc_html( $title ?: 'Guide' );
		}

		return '<span class="dashicons dashicons-minus" style="color: #ccc;"></span>';
	}

	/**
	 * AJAX: Toggle guide status
	 */
	public static function ajax_toggle_guide() {
		check_ajax_referer( 'bd_guides_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'business-directory' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$action  = isset( $_POST['toggle_action'] ) ? sanitize_key( $_POST['toggle_action'] ) : '';

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID', 'business-directory' ) ) );
		}

		if ( 'remove' === $action ) {
			delete_user_meta( $user_id, 'bd_is_guide' );
			wp_send_json_success( array( 'message' => __( 'Guide removed', 'business-directory' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Invalid action', 'business-directory' ) ) );
	}

	/**
	 * AJAX: Update guide order
	 */
	public static function ajax_update_order() {
		check_ajax_referer( 'bd_guides_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'business-directory' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$order   = isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 10;

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user ID', 'business-directory' ) ) );
		}

		update_user_meta( $user_id, 'bd_guide_order', $order );

		wp_send_json_success( array( 'message' => __( 'Order updated', 'business-directory' ) ) );
	}

	/**
	 * Get all guides ordered by display order
	 *
	 * @param array $args Optional query args.
	 * @return array Array of guide data.
	 */
	public static function get_all_guides( $args = array() ) {
		$defaults = array(
			'limit' => -1,
			'city'  => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		// FIX: Use modern meta_query format.
		$users = get_users(
			array(
				'meta_query' => array(
					array(
						'key'     => 'bd_is_guide',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		$guides = array();
		foreach ( $users as $user ) {
			$guide_cities = get_user_meta( $user->ID, 'bd_guide_cities', true );

			$guide_data = array(
				'user_id' => $user->ID,
				'order'   => absint( get_user_meta( $user->ID, 'bd_guide_order', true ) ) ?: 10,
				'cities'  => is_array( $guide_cities ) ? $guide_cities : array(),
			);

			// Filter by city if specified.
			if ( ! empty( $args['city'] ) ) {
				if ( ! in_array( $args['city'], $guide_data['cities'], true ) ) {
					continue;
				}
			}

			$guides[] = $guide_data;
		}

		// Sort by order.
		usort(
			$guides,
			function ( $a, $b ) {
				return $a['order'] - $b['order'];
			}
		);

		// Apply limit.
		if ( $args['limit'] > 0 ) {
			$guides = array_slice( $guides, 0, $args['limit'] );
		}

		return $guides;
	}
}
