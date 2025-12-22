<?php
/**
 * Reviews Admin - Full Review Management
 *
 * @package BusinessDirectory
 */

namespace BD\Admin;

/**
 * Class ReviewsAdmin
 * Provides full admin interface for managing all reviews
 */
class ReviewsAdmin {

	/**
	 * Reviews per page
	 */
	const PER_PAGE = 20;

	/**
	 * Initialize the admin page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bd_admin_review_action', array( __CLASS__, 'handle_review_action' ) );
	}

	/**
	 * Add menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'All Reviews', 'developer-developer-developer' ),
			__( 'All Reviews', 'developer-developer-developer' ),
			'manage_options',
			'bd-all-reviews',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'business_page_bd-all-reviews' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bd-reviews-admin',
			plugins_url( 'assets/css/admin-reviews.css', dirname( __DIR__ ) ),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'bd-reviews-admin',
			plugins_url( 'assets/js/admin-reviews.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'bd-reviews-admin',
			'bdReviewsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_admin_review_action' ),
			)
		);
	}

	/**
	 * Get reviews with filters
	 *
	 * @param array $args Filter arguments.
	 * @return array Reviews and total count.
	 */
	public static function get_reviews( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'      => '',
			'search'      => '',
			'business_id' => 0,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
			'page'        => 1,
			'per_page'    => self::PER_PAGE,
		);

		$args = wp_parse_args( $args, $defaults );

		$table  = $wpdb->base_prefix . 'bd_reviews';
		$where  = array( '1=1' );
		$values = array();

		// Status filter.
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		// Business filter.
		if ( ! empty( $args['business_id'] ) ) {
			$where[]  = 'business_id = %d';
			$values[] = absint( $args['business_id'] );
		}

		// Search filter.
		if ( ! empty( $args['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(author_name LIKE %s OR author_email LIKE %s OR title LIKE %s OR content LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count.
		$count_query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
		if ( ! empty( $values ) ) {
			$count_query = $wpdb->prepare( $count_query, $values );
		}
		$total = $wpdb->get_var( $count_query );

		// Sanitize orderby.
		$allowed_orderby = array( 'id', 'created_at', 'rating', 'author_name', 'status', 'helpful_count' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Pagination.
		$offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
		$limit  = absint( $args['per_page'] );

		// Get reviews.
		$query = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT $offset, $limit";
		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}
		$reviews = $wpdb->get_results( $query, ARRAY_A );

		return array(
			'reviews' => $reviews,
			'total'   => (int) $total,
			'pages'   => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Get status counts
	 *
	 * @return array Status counts.
	 */
	public static function get_status_counts() {
		global $wpdb;
		$table = $wpdb->base_prefix . 'bd_reviews';

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM $table GROUP BY status",
			ARRAY_A
		);

		$counts = array(
			'all'      => 0,
			'approved' => 0,
			'pending'  => 0,
			'rejected' => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row['status'] ] = (int) $row['count'];
			$counts['all']           += (int) $row['count'];
		}

		return $counts;
	}

	/**
	 * Handle AJAX review actions
	 */
	public static function handle_review_action() {
		check_ajax_referer( 'bd_admin_review_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$review_id = absint( $_POST['review_id'] ?? 0 );
		$action    = sanitize_text_field( $_POST['review_action'] ?? '' );

		if ( ! $review_id || ! $action ) {
			wp_send_json_error( array( 'message' => 'Invalid request' ) );
		}

		global $wpdb;
		$table = $wpdb->base_prefix . 'bd_reviews';

		switch ( $action ) {
			case 'approve':
				$wpdb->update(
					$table,
					array( 'status' => 'approved' ),
					array( 'id' => $review_id ),
					array( '%s' ),
					array( '%d' )
				);
				do_action( 'bd_review_approved', $review_id );
				wp_send_json_success( array( 'message' => 'Review approved' ) );
				break;

			case 'reject':
				$wpdb->update(
					$table,
					array( 'status' => 'rejected' ),
					array( 'id' => $review_id ),
					array( '%s' ),
					array( '%d' )
				);
				wp_send_json_success( array( 'message' => 'Review rejected' ) );
				break;

			case 'pending':
				$wpdb->update(
					$table,
					array( 'status' => 'pending' ),
					array( 'id' => $review_id ),
					array( '%s' ),
					array( '%d' )
				);
				wp_send_json_success( array( 'message' => 'Review set to pending' ) );
				break;

			case 'delete':
				$wpdb->delete(
					$table,
					array( 'id' => $review_id ),
					array( '%d' )
				);
				wp_send_json_success( array( 'message' => 'Review deleted' ) );
				break;

			default:
				wp_send_json_error( array( 'message' => 'Unknown action' ) );
		}
	}

	/**
	 * Render the admin page
	 */
	public static function render_page() {
		// Get filter values.
		$current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$search         = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$business_id    = isset( $_GET['business_id'] ) ? absint( $_GET['business_id'] ) : 0;
		$orderby        = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
		$order          = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
		$paged          = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		// Get data.
		$status_counts = self::get_status_counts();
		$result        = self::get_reviews(
			array(
				'status'      => $current_status,
				'search'      => $search,
				'business_id' => $business_id,
				'orderby'     => $orderby,
				'order'       => $order,
				'page'        => $paged,
			)
		);

		$reviews     = $result['reviews'];
		$total_pages = $result['pages'];
		$total_items = $result['total'];

		// Base URL for filters.
		$base_url = admin_url( 'edit.php?post_type=business&page=bd-all-reviews' );
		?>
		<div class="wrap bd-reviews-admin">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'All Reviews', 'developer-developer-developer' ); ?>
			</h1>
			<hr class="wp-header-end">

			<!-- Status Tabs -->
			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( $base_url ); ?>" 
						class="<?php echo empty( $current_status ) ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['all'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'approved', $base_url ) ); ?>"
						class="<?php echo 'approved' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Approved', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['approved'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'pending', $base_url ) ); ?>"
						class="<?php echo 'pending' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Pending', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['pending'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'rejected', $base_url ) ); ?>"
						class="<?php echo 'rejected' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Rejected', 'developer-developer-developer' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['rejected'] ); ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Search Box -->
			<form method="get" class="search-form">
				<input type="hidden" name="post_type" value="business">
				<input type="hidden" name="page" value="bd-all-reviews">
				<?php if ( $current_status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="review-search-input">
						<?php esc_html_e( 'Search Reviews', 'developer-developer-developer' ); ?>
					</label>
					<input type="search" id="review-search-input" name="s" 
						value="<?php echo esc_attr( $search ); ?>" 
						placeholder="<?php esc_attr_e( 'Search by name, email, or content...', 'developer-developer-developer' ); ?>">
					<input type="submit" id="search-submit" class="button" 
						value="<?php esc_attr_e( 'Search Reviews', 'developer-developer-developer' ); ?>">
				</p>
			</form>

			<!-- Reviews Table -->
			<table class="wp-list-table widefat fixed striped bd-reviews-table">
				<thead>
					<tr>
						<th scope="col" class="column-business" style="width: 15%;">
							<?php esc_html_e( 'Business', 'developer-developer-developer' ); ?>
						</th>
						<th scope="col" class="column-author" style="width: 15%;">
							<?php esc_html_e( 'Author', 'developer-developer-developer' ); ?>
						</th>
						<th scope="col" class="column-review" style="width: 30%;">
							<?php esc_html_e( 'Review', 'developer-developer-developer' ); ?>
						</th>
						<th scope="col" class="column-rating" style="width: 10%;">
							<?php
							$rating_url = add_query_arg(
								array(
									'orderby' => 'rating',
									'order'   => 'rating' === $orderby && 'DESC' === $order ? 'ASC' : 'DESC',
								),
								$base_url
							);
							?>
							<a href="<?php echo esc_url( $rating_url ); ?>">
								<?php esc_html_e( 'Rating', 'developer-developer-developer' ); ?>
								<?php if ( 'rating' === $orderby ) : ?>
									<span class="sorting-indicator <?php echo 'ASC' === $order ? 'asc' : 'desc'; ?>"></span>
								<?php endif; ?>
							</a>
						</th>
						<th scope="col" class="column-helpful" style="width: 8%;">
							<?php
							$helpful_url = add_query_arg(
								array(
									'orderby' => 'helpful_count',
									'order'   => 'helpful_count' === $orderby && 'DESC' === $order ? 'ASC' : 'DESC',
								),
								$base_url
							);
							?>
							<a href="<?php echo esc_url( $helpful_url ); ?>">
								<?php esc_html_e( 'Helpful', 'developer-developer-developer' ); ?>
								<?php if ( 'helpful_count' === $orderby ) : ?>
									<span class="sorting-indicator <?php echo 'ASC' === $order ? 'asc' : 'desc'; ?>"></span>
								<?php endif; ?>
							</a>
						</th>
						<th scope="col" class="column-status" style="width: 10%;">
							<?php esc_html_e( 'Status', 'developer-developer-developer' ); ?>
						</th>
						<th scope="col" class="column-date" style="width: 12%;">
							<?php
							$date_url = add_query_arg(
								array(
									'orderby' => 'created_at',
									'order'   => 'created_at' === $orderby && 'DESC' === $order ? 'ASC' : 'DESC',
								),
								$base_url
							);
							?>
							<a href="<?php echo esc_url( $date_url ); ?>">
								<?php esc_html_e( 'Date', 'developer-developer-developer' ); ?>
								<?php if ( 'created_at' === $orderby ) : ?>
									<span class="sorting-indicator <?php echo 'ASC' === $order ? 'asc' : 'desc'; ?>"></span>
								<?php endif; ?>
							</a>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $reviews ) ) : ?>
						<tr>
							<td colspan="7">
								<?php esc_html_e( 'No reviews found.', 'developer-developer-developer' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $reviews as $review ) : ?>
							<?php
							$business       = get_post( $review['business_id'] );
							$business_title = $business ? $business->post_title : __( 'Unknown Business', 'developer-developer-developer' );
							$business_url   = $business ? get_permalink( $business->ID ) : '#';
							$edit_url       = $business ? get_edit_post_link( $business->ID ) : '#';
							?>
							<tr data-review-id="<?php echo esc_attr( $review['id'] ); ?>">
								<td class="column-business">
									<strong>
										<a href="<?php echo esc_url( $business_url ); ?>" target="_blank">
											<?php echo esc_html( $business_title ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<a href="<?php echo esc_url( $edit_url ); ?>">
											<?php esc_html_e( 'Edit Business', 'developer-developer-developer' ); ?>
										</a>
									</div>
								</td>
								<td class="column-author">
									<strong><?php echo esc_html( $review['author_name'] ); ?></strong>
									<br>
									<a href="mailto:<?php echo esc_attr( $review['author_email'] ); ?>">
										<?php echo esc_html( $review['author_email'] ); ?>
									</a>
									<?php if ( ! empty( $review['user_id'] ) ) : ?>
										<br>
										<span class="bd-user-badge">
											<?php esc_html_e( 'Registered User', 'developer-developer-developer' ); ?>
										</span>
									<?php else : ?>
										<br>
										<span class="bd-guest-badge">
											<?php esc_html_e( 'Guest', 'developer-developer-developer' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="column-review">
									<?php if ( ! empty( $review['title'] ) ) : ?>
										<strong><?php echo esc_html( $review['title'] ); ?></strong>
										<br>
									<?php endif; ?>
									<?php echo esc_html( wp_trim_words( $review['content'], 20 ) ); ?>
									<?php if ( ! empty( $review['photo_ids'] ) ) : ?>
										<br>
										<span class="bd-has-photos">
											📷 <?php esc_html_e( 'Has photos', 'developer-developer-developer' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="column-rating">
									<span class="bd-rating-stars">
										<?php echo esc_html( str_repeat( '★', $review['rating'] ) ); ?>
										<?php echo esc_html( str_repeat( '☆', 5 - $review['rating'] ) ); ?>
									</span>
								</td>
								<td class="column-helpful">
									<?php echo esc_html( $review['helpful_count'] ?? 0 ); ?>
								</td>
								<td class="column-status">
									<span class="bd-status bd-status-<?php echo esc_attr( $review['status'] ); ?>">
										<?php echo esc_html( ucfirst( $review['status'] ) ); ?>
									</span>
								</td>
								<td class="column-date">
									<?php echo esc_html( date_i18n( 'M j, Y', strtotime( $review['created_at'] ) ) ); ?>
									<br>
									<span class="bd-time">
										<?php echo esc_html( date_i18n( 'g:i a', strtotime( $review['created_at'] ) ) ); ?>
									</span>
								</td>
							</tr>
							<tr class="bd-actions-row" data-review-id="<?php echo esc_attr( $review['id'] ); ?>">
								<td colspan="7">
									<div class="bd-review-actions">
										<?php if ( 'approved' !== $review['status'] ) : ?>
											<button type="button" class="button button-primary bd-action-btn" 
												data-action="approve" data-review-id="<?php echo esc_attr( $review['id'] ); ?>">
												<?php esc_html_e( 'Approve', 'developer-developer-developer' ); ?>
											</button>
										<?php endif; ?>
										<?php if ( 'pending' !== $review['status'] ) : ?>
											<button type="button" class="button bd-action-btn" 
												data-action="pending" data-review-id="<?php echo esc_attr( $review['id'] ); ?>">
												<?php esc_html_e( 'Set Pending', 'developer-developer-developer' ); ?>
											</button>
										<?php endif; ?>
										<?php if ( 'rejected' !== $review['status'] ) : ?>
											<button type="button" class="button bd-action-btn" 
												data-action="reject" data-review-id="<?php echo esc_attr( $review['id'] ); ?>">
												<?php esc_html_e( 'Reject', 'developer-developer-developer' ); ?>
											</button>
										<?php endif; ?>
										<button type="button" class="button button-link-delete bd-action-btn" 
											data-action="delete" data-review-id="<?php echo esc_attr( $review['id'] ); ?>">
											<?php esc_html_e( 'Delete', 'developer-developer-developer' ); ?>
										</button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: Number of items */
								esc_html( _n( '%s item', '%s items', $total_items, 'developer-developer-developer' ) ),
								number_format_i18n( $total_items )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php
							$pagination_args = array(
								'status'      => $current_status,
								's'           => $search,
								'business_id' => $business_id,
								'orderby'     => $orderby,
								'order'       => $order,
							);

							// First page.
							if ( $paged > 1 ) :
								?>
								<a class="first-page button" href="<?php echo esc_url( add_query_arg( array_merge( $pagination_args, array( 'paged' => 1 ) ), $base_url ) ); ?>">
									<span aria-hidden="true">«</span>
								</a>
								<a class="prev-page button" href="<?php echo esc_url( add_query_arg( array_merge( $pagination_args, array( 'paged' => $paged - 1 ) ), $base_url ) ); ?>">
									<span aria-hidden="true">‹</span>
								</a>
							<?php else : ?>
								<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
								<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
							<?php endif; ?>

							<span class="paging-input">
								<span class="tablenav-paging-text">
									<?php echo esc_html( $paged ); ?> of 
									<span class="total-pages"><?php echo esc_html( $total_pages ); ?></span>
								</span>
							</span>

							<?php if ( $paged < $total_pages ) : ?>
								<a class="next-page button" href="<?php echo esc_url( add_query_arg( array_merge( $pagination_args, array( 'paged' => $paged + 1 ) ), $base_url ) ); ?>">
									<span aria-hidden="true">›</span>
								</a>
								<a class="last-page button" href="<?php echo esc_url( add_query_arg( array_merge( $pagination_args, array( 'paged' => $total_pages ) ), $base_url ) ); ?>">
									<span aria-hidden="true">»</span>
								</a>
							<?php else : ?>
								<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
								<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
