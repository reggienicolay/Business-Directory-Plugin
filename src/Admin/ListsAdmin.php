<?php
/**
 * Lists Admin
 *
 * Admin page for managing user-created lists.
 * Follows ReviewsAdmin pattern with tabs and filters.
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

		wp_enqueue_style(
			'bd-lists-admin',
			plugins_url( 'assets/css/admin-lists.css', dirname( __DIR__ ) ),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'bd-lists-admin',
			plugins_url( 'assets/js/admin-lists.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			'1.0.0',
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
			'orderby'  => 'updated_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';

		// Filter by visibility (status)
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'public', 'private', 'unlisted', 'featured' ), true ) ) {
			if ( 'featured' === $args['status'] ) {
				$where .= ' AND featured = 1';
			} else {
				$where .= $wpdb->prepare( ' AND visibility = %s', $args['status'] );
			}
		}

		// Search
		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= $wpdb->prepare(
				' AND (title LIKE %s OR description LIKE %s)',
				$search,
				$search
			);
		}

		// Sorting
		$orderby = in_array( $args['orderby'], array( 'title', 'created_at', 'updated_at', 'view_count' ), true )
			? $args['orderby'] : 'updated_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Total count
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

		// Get lists
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

		// Add item counts
		foreach ( $lists as &$list ) {
			$list['item_count'] = ListManager::get_list_item_count( $list['id'] );
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
	 * Render admin page
	 */
	public static function render_page() {
		$current_status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$current_page   = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$orderby        = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'updated_at';
		$order          = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		$counts = self::get_status_counts();
		$result = self::get_lists(
			array(
				'status'  => $current_status,
				'search'  => $search_query,
				'orderby' => $orderby,
				'order'   => $order,
				'page'    => $current_page,
			)
		);

		$lists = $result['lists'];
		$total = $result['total'];
		$pages = $result['pages'];

		$base_url = admin_url( 'edit.php?post_type=bd_business&page=bd-user-lists' );
		?>
		<div class="wrap bd-lists-admin">
			<h1 class="wp-heading-inline">
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
						<?php esc_html_e( 'Public', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['public'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'private', $base_url ) ); ?>"
						class="<?php echo 'private' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Private', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['private'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'unlisted', $base_url ) ); ?>"
						class="<?php echo 'unlisted' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Unlisted', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['unlisted'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'featured', $base_url ) ); ?>"
						class="<?php echo 'featured' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Featured', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $counts['featured'] ); ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Search Form -->
			<form method="get" class="search-box">
				<input type="hidden" name="post_type" value="bd_business">
				<input type="hidden" name="page" value="bd-user-lists">
				<?php if ( $current_status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
				<?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" 
					placeholder="<?php esc_attr_e( 'Search lists...', 'developer-developer-developer' ); ?>">
				<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'developer-developer-developer' ); ?>">
			</form>

			<!-- Lists Table -->
			<table class="wp-list-table widefat fixed striped bd-lists-table">
				<thead>
					<tr>
						<th class="column-title" scope="col">
							<?php echo self::sortable_column_header( 'title', __( 'Title', 'developer-developer-developer' ), $orderby, $order, $base_url, $current_status, $search_query ); ?>
						</th>
						<th class="column-author" scope="col"><?php esc_html_e( 'Author', 'developer-developer-developer' ); ?></th>
						<th class="column-items" scope="col"><?php esc_html_e( 'Items', 'developer-developer-developer' ); ?></th>
						<th class="column-visibility" scope="col"><?php esc_html_e( 'Visibility', 'developer-developer-developer' ); ?></th>
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
							<td colspan="7" class="bd-no-items">
								<?php esc_html_e( 'No lists found.', 'developer-developer-developer' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $lists as $list ) : ?>
							<tr data-list-id="<?php echo esc_attr( $list['id'] ); ?>">
								<td class="column-title">
									<strong>
										<?php if ( $list['featured'] ) : ?>
											<span class="bd-featured-star" title="Featured">⭐</span>
										<?php endif; ?>
										<?php echo esc_html( $list['title'] ); ?>
									</strong>
									<?php if ( ! empty( $list['description'] ) ) : ?>
										<p class="bd-list-description"><?php echo esc_html( wp_trim_words( $list['description'], 15 ) ); ?></p>
									<?php endif; ?>
								</td>
								<td class="column-author">
									<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $list['user_id'] ) ); ?>">
										<?php echo esc_html( $list['author_name'] ); ?>
									</a>
								</td>
								<td class="column-items">
									<span class="bd-item-count"><?php echo esc_html( $list['item_count'] ); ?></span>
								</td>
								<td class="column-visibility">
									<span class="bd-visibility-badge bd-visibility-<?php echo esc_attr( $list['visibility'] ); ?>">
										<?php echo esc_html( ucfirst( $list['visibility'] ) ); ?>
									</span>
								</td>
								<td class="column-views">
									<?php echo esc_html( number_format( $list['view_count'] ) ); ?>
								</td>
								<td class="column-date">
									<span title="<?php echo esc_attr( $list['updated_at'] ); ?>">
										<?php echo esc_html( human_time_diff( strtotime( $list['updated_at'] ), current_time( 'timestamp' ) ) ); ?> ago
									</span>
								</td>
								<td class="column-actions">
									<?php if ( 'public' === $list['visibility'] ) : ?>
										<a href="<?php echo esc_url( ListManager::get_list_url( $list ) ); ?>" 
											class="button button-small" target="_blank" title="View">
											👁️
										</a>
									<?php endif; ?>
									
									<?php if ( $list['featured'] ) : ?>
										<button type="button" class="button button-small bd-unfeature-btn" 
											data-list-id="<?php echo esc_attr( $list['id'] ); ?>" title="Remove Featured">
											⭐
										</button>
									<?php else : ?>
										<button type="button" class="button button-small bd-feature-btn" 
											data-list-id="<?php echo esc_attr( $list['id'] ); ?>" title="Feature">
											☆
										</button>
									<?php endif; ?>
									
									<button type="button" class="button button-small bd-delete-btn" 
										data-list-id="<?php echo esc_attr( $list['id'] ); ?>" title="Delete">
										🗑️
									</button>
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
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
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
			'<a href="%s" class="%s"><span>%s</span><span class="sorting-indicator"></span></a>',
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
				// Admins can delete any list
				global $wpdb;
				$lists_table = $wpdb->prefix . 'bd_lists';
				$items_table = $wpdb->prefix . 'bd_list_items';

				$wpdb->delete( $items_table, array( 'list_id' => $list_id ), array( '%d' ) );
				$result = $wpdb->delete( $lists_table, array( 'id' => $list_id ), array( '%d' ) );

				if ( $result ) {
					wp_send_json_success( array( 'message' => 'List deleted!' ) );
				}
				break;
		}

		wp_send_json_error( array( 'message' => 'Action failed' ) );
	}
}
