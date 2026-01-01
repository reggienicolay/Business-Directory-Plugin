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
		add_action( 'wp_ajax_bd_admin_bulk_action', array( __CLASS__, 'handle_bulk_action' ) );
		add_action( 'wp_ajax_bd_admin_export_reviews', array( __CLASS__, 'handle_export' ) );
		add_action( 'wp_ajax_bd_admin_save_reply', array( __CLASS__, 'handle_save_reply' ) );
	}

	/**
	 * Get the reviews table name (per-site for multisite).
	 *
	 * @return string Table name.
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'bd_reviews';
	}

	/**
	 * Add menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'All Reviews', 'business-directory' ),
			__( 'All Reviews', 'business-directory' ),
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
		if ( 'bd_business_page_bd-all-reviews' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bd-reviews-admin',
			plugins_url( 'assets/css/admin-reviews.css', dirname( __DIR__ ) ),
			array(),
			'2.0.0'
		);

		wp_enqueue_script(
			'bd-reviews-admin',
			plugins_url( 'assets/js/admin-reviews.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			'2.0.0',
			true
		);

		wp_localize_script(
			'bd-reviews-admin',
			'bdReviewsAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'bd_admin_review_action' ),
				'confirmDelete' => __( 'Are you sure you want to delete this review? This cannot be undone.', 'business-directory' ),
				'confirmBulk'   => __( 'Are you sure you want to perform this action on the selected reviews?', 'business-directory' ),
				'noSelection'   => __( 'Please select at least one review.', 'business-directory' ),
				'processing'    => __( 'Processing...', 'business-directory' ),
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
			'rating'      => 0,
			'date_from'   => '',
			'date_to'     => '',
			'seeded'      => '',
			'orderby'     => 'created_at',
			'order'       => 'DESC',
			'page'        => 1,
			'per_page'    => self::PER_PAGE,
		);

		$args = wp_parse_args( $args, $defaults );

		$table  = self::get_table_name();
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

		// Rating filter.
		if ( ! empty( $args['rating'] ) ) {
			$where[]  = 'rating = %d';
			$values[] = absint( $args['rating'] );
		}

		// Date range filter.
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'DATE(created_at) >= %s';
			$values[] = sanitize_text_field( $args['date_from'] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'DATE(created_at) <= %s';
			$values[] = sanitize_text_field( $args['date_to'] );
		}

		// Seeded filter.
		if ( 'yes' === $args['seeded'] ) {
			$where[] = "author_email LIKE '%@community.local'";
		} elseif ( 'no' === $args['seeded'] ) {
			$where[] = "author_email NOT LIKE '%@community.local'";
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_query = $wpdb->prepare( $count_query, $values );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$total = $wpdb->get_var( $count_query );

		// Sanitize orderby.
		$allowed_orderby = array( 'id', 'created_at', 'rating', 'author_name', 'status', 'helpful_count' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Pagination.
		$offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
		$limit  = absint( $args['per_page'] );

		// Get reviews.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT {$offset}, {$limit}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $values );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
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
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
			ARRAY_A
		);

		$counts = array(
			'all'      => 0,
			'approved' => 0,
			'pending'  => 0,
			'rejected' => 0,
		);

		foreach ( $results as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['count'];
			}
			$counts['all'] += (int) $row['count'];
		}

		// Get seeded count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts['seeded'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE author_email LIKE '%@community.local'"
		);

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
		$table = self::get_table_name();

		switch ( $action ) {
			case 'approve':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array( 'status' => 'approved' ),
					array( 'id' => $review_id ),
					array( '%s' ),
					array( '%d' )
				);
				self::update_business_stats( $review_id );
				do_action( 'bd_review_approved', $review_id );
				wp_send_json_success( array( 'message' => 'Review approved' ) );
				break;

			case 'reject':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array( 'status' => 'rejected' ),
					array( 'id' => $review_id ),
					array( '%s' ),
					array( '%d' )
				);
				self::update_business_stats( $review_id );
				wp_send_json_success( array( 'message' => 'Review rejected' ) );
				break;

			case 'pending':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array( 'status' => 'pending' ),
					array( 'id' => $review_id ),
					array( '%s' ),
					array( '%d' )
				);
				self::update_business_stats( $review_id );
				wp_send_json_success( array( 'message' => 'Review set to pending' ) );
				break;

			case 'delete':
				// Get business ID before deletion.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$review      = $wpdb->get_row(
					$wpdb->prepare( "SELECT business_id FROM {$table} WHERE id = %d", $review_id ),
					ARRAY_A
				);
				$business_id = $review ? $review['business_id'] : 0;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete(
					$table,
					array( 'id' => $review_id ),
					array( '%d' )
				);

				if ( $business_id ) {
					self::update_business_stats_by_id( $business_id );
				}
				wp_send_json_success( array( 'message' => 'Review deleted' ) );
				break;

			default:
				wp_send_json_error( array( 'message' => 'Unknown action' ) );
		}
	}

	/**
	 * Handle bulk actions
	 */
	public static function handle_bulk_action() {
		check_ajax_referer( 'bd_admin_review_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$action     = sanitize_text_field( $_POST['bulk_action'] ?? '' );
		$review_ids = array_map( 'absint', $_POST['review_ids'] ?? array() );

		if ( empty( $action ) || empty( $review_ids ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request' ) );
		}

		global $wpdb;
		$table        = self::get_table_name();
		$affected     = 0;
		$business_ids = array();

		// Get business IDs for all reviews.
		$ids_placeholder = implode( ',', array_fill( 0, count( $review_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, business_id FROM {$table} WHERE id IN ({$ids_placeholder})",
				$review_ids
			),
			ARRAY_A
		);
		foreach ( $reviews as $review ) {
			$business_ids[ $review['id'] ] = $review['business_id'];
		}

		switch ( $action ) {
			case 'approve':
				foreach ( $review_ids as $review_id ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$result = $wpdb->update(
						$table,
						array( 'status' => 'approved' ),
						array( 'id' => $review_id ),
						array( '%s' ),
						array( '%d' )
					);
					if ( $result ) {
						++$affected;
						do_action( 'bd_review_approved', $review_id );
					}
				}
				break;

			case 'reject':
				foreach ( $review_ids as $review_id ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$result = $wpdb->update(
						$table,
						array( 'status' => 'rejected' ),
						array( 'id' => $review_id ),
						array( '%s' ),
						array( '%d' )
					);
					if ( $result ) {
						++$affected;
					}
				}
				break;

			case 'pending':
				foreach ( $review_ids as $review_id ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$result = $wpdb->update(
						$table,
						array( 'status' => 'pending' ),
						array( 'id' => $review_id ),
						array( '%s' ),
						array( '%d' )
					);
					if ( $result ) {
						++$affected;
					}
				}
				break;

			case 'delete':
				foreach ( $review_ids as $review_id ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$result = $wpdb->delete(
						$table,
						array( 'id' => $review_id ),
						array( '%d' )
					);
					if ( $result ) {
						++$affected;
					}
				}
				break;

			default:
				wp_send_json_error( array( 'message' => 'Unknown action' ) );
				return;
		}

		// Update business stats for all affected businesses.
		$unique_business_ids = array_unique( array_values( $business_ids ) );
		foreach ( $unique_business_ids as $business_id ) {
			self::update_business_stats_by_id( $business_id );
		}

		wp_send_json_success(
			array(
				'message'  => sprintf(
					/* translators: %d: Number of reviews affected */
					__( '%d review(s) updated.', 'business-directory' ),
					$affected
				),
				'affected' => $affected,
			)
		);
	}

	/**
	 * Handle CSV export
	 */
	public static function handle_export() {
		check_ajax_referer( 'bd_admin_review_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		global $wpdb;
		$table = self::get_table_name();

		// Get all reviews (no pagination).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$reviews = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

		// Output CSV.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=reviews-export-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		// Header row.
		fputcsv(
			$output,
			array(
				'ID',
				'Business ID',
				'Business Name',
				'Author Name',
				'Author Email',
				'Rating',
				'Title',
				'Content',
				'Status',
				'Helpful Count',
				'Created At',
				'Is Seeded',
			)
		);

		foreach ( $reviews as $review ) {
			$business      = get_post( $review['business_id'] );
			$business_name = $business ? $business->post_title : 'Unknown';
			$is_seeded     = strpos( $review['author_email'], '@community.local' ) !== false ? 'Yes' : 'No';

			fputcsv(
				$output,
				array(
					$review['id'],
					$review['business_id'],
					$business_name,
					$review['author_name'],
					$review['author_email'],
					$review['rating'],
					$review['title'],
					$review['content'],
					$review['status'],
					$review['helpful_count'] ?? 0,
					$review['created_at'],
					$is_seeded,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle saving admin reply
	 */
	public static function handle_save_reply() {
		check_ajax_referer( 'bd_admin_review_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$review_id = absint( $_POST['review_id'] ?? 0 );
		$reply     = sanitize_textarea_field( $_POST['reply'] ?? '' );

		if ( ! $review_id ) {
			wp_send_json_error( array( 'message' => 'Invalid review ID' ) );
		}

		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'admin_reply'    => $reply,
				'admin_reply_at' => current_time( 'mysql' ),
				'admin_reply_by' => get_current_user_id(),
			),
			array( 'id' => $review_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			wp_send_json_success(
				array(
					'message' => empty( $reply ) ? __( 'Reply removed.', 'business-directory' ) : __( 'Reply saved.', 'business-directory' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Failed to save reply. Column may not exist.' ) );
		}
	}

	/**
	 * Update business stats after review change.
	 *
	 * @param int $review_id Review ID.
	 */
	private static function update_business_stats( $review_id ) {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$review = $wpdb->get_row(
			$wpdb->prepare( "SELECT business_id FROM {$table} WHERE id = %d", $review_id ),
			ARRAY_A
		);

		if ( $review ) {
			self::update_business_stats_by_id( $review['business_id'] );
		}
	}

	/**
	 * Update business stats by business ID.
	 *
	 * @param int $business_id Business ID.
	 */
	private static function update_business_stats_by_id( $business_id ) {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as count, AVG(rating) as avg_rating FROM {$table} WHERE business_id = %d AND status = 'approved'",
				$business_id
			),
			ARRAY_A
		);

		if ( $stats ) {
			update_post_meta( $business_id, 'bd_review_count', (int) $stats['count'] );
			update_post_meta( $business_id, 'bd_avg_rating', $stats['avg_rating'] ? round( (float) $stats['avg_rating'], 1 ) : 0 );
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
		$rating         = isset( $_GET['rating'] ) ? absint( $_GET['rating'] ) : 0;
		$date_from      = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to        = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		$seeded         = isset( $_GET['seeded'] ) ? sanitize_text_field( $_GET['seeded'] ) : '';
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
				'rating'      => $rating,
				'date_from'   => $date_from,
				'date_to'     => $date_to,
				'seeded'      => $seeded,
				'orderby'     => $orderby,
				'order'       => $order,
				'page'        => $paged,
			)
		);

		$reviews     = $result['reviews'];
		$total_pages = $result['pages'];
		$total_items = $result['total'];

		// Base URL for filters.
		$base_url = admin_url( 'edit.php?post_type=bd_business&page=bd-all-reviews' );
		?>
		<div class="wrap bd-reviews-admin">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'All Reviews', 'business-directory' ); ?>
			</h1>
			
			<!-- Export Button -->
			<a href="#" id="bd-export-reviews" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'business-directory' ); ?>
			</a>
			
			<hr class="wp-header-end">

			<!-- Status Tabs -->
			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( $base_url ); ?>" 
						class="<?php echo empty( $current_status ) && empty( $seeded ) ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'business-directory' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['all'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'approved', $base_url ) ); ?>"
						class="<?php echo 'approved' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Approved', 'business-directory' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['approved'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'pending', $base_url ) ); ?>"
						class="<?php echo 'pending' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Pending', 'business-directory' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['pending'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'status', 'rejected', $base_url ) ); ?>"
						class="<?php echo 'rejected' === $current_status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Rejected', 'business-directory' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['rejected'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'seeded', 'yes', $base_url ) ); ?>"
						class="<?php echo 'yes' === $seeded ? 'current' : ''; ?>">
						🤖 <?php esc_html_e( 'Seeded', 'business-directory' ); ?>
						<span class="count">(<?php echo esc_html( $status_counts['seeded'] ); ?>)</span>
					</a>
				</li>
			</ul>

			<!-- Filters Bar -->
			<div class="bd-filters-bar">
				<form method="get" class="bd-filters-form">
					<input type="hidden" name="post_type" value="bd_business">
					<input type="hidden" name="page" value="bd-all-reviews">
					<?php if ( $current_status ) : ?>
						<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
					<?php endif; ?>

					<!-- Search -->
					<div class="bd-filter-group">
						<input type="search" name="s" 
							value="<?php echo esc_attr( $search ); ?>" 
							placeholder="<?php esc_attr_e( 'Search...', 'business-directory' ); ?>"
							class="bd-filter-search">
					</div>

					<!-- Rating Filter -->
					<div class="bd-filter-group">
						<select name="rating" class="bd-filter-select">
							<option value=""><?php esc_html_e( 'All Ratings', 'business-directory' ); ?></option>
							<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $rating, $i ); ?>>
									<?php echo esc_html( str_repeat( '★', $i ) . str_repeat( '☆', 5 - $i ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
					</div>

					<!-- Date Range -->
					<div class="bd-filter-group">
						<input type="date" name="date_from" 
							value="<?php echo esc_attr( $date_from ); ?>" 
							placeholder="<?php esc_attr_e( 'From', 'business-directory' ); ?>"
							class="bd-filter-date">
						<span class="bd-filter-sep">—</span>
						<input type="date" name="date_to" 
							value="<?php echo esc_attr( $date_to ); ?>" 
							placeholder="<?php esc_attr_e( 'To', 'business-directory' ); ?>"
							class="bd-filter-date">
					</div>

					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'business-directory' ); ?></button>
					
					<?php if ( $search || $rating || $date_from || $date_to || $seeded ) : ?>
						<a href="<?php echo esc_url( $base_url ); ?>" class="button bd-clear-filters">
							<?php esc_html_e( 'Clear', 'business-directory' ); ?>
						</a>
					<?php endif; ?>
				</form>
			</div>

			<!-- Bulk Actions -->
			<div class="bd-bulk-actions tablenav top">
				<div class="alignleft actions bulkactions">
					<select id="bd-bulk-action-select">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'business-directory' ); ?></option>
						<option value="approve"><?php esc_html_e( 'Approve', 'business-directory' ); ?></option>
						<option value="reject"><?php esc_html_e( 'Reject', 'business-directory' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Set Pending', 'business-directory' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'business-directory' ); ?></option>
					</select>
					<button type="button" id="bd-bulk-action-apply" class="button action">
						<?php esc_html_e( 'Apply', 'business-directory' ); ?>
					</button>
					<span id="bd-bulk-status" class="bd-bulk-status"></span>
				</div>
				<div class="alignright">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %s: Number of items */
							esc_html( _n( '%s review', '%s reviews', $total_items, 'business-directory' ) ),
							number_format_i18n( $total_items )
						);
						?>
					</span>
				</div>
			</div>

			<!-- Reviews Table -->
			<table class="wp-list-table widefat fixed striped bd-reviews-table">
				<thead>
					<tr>
						<th scope="col" class="column-cb check-column">
							<input type="checkbox" id="bd-select-all" title="<?php esc_attr_e( 'Select All', 'business-directory' ); ?>">
						</th>
						<th scope="col" class="column-business">
							<?php esc_html_e( 'Business', 'business-directory' ); ?>
						</th>
						<th scope="col" class="column-author">
							<?php esc_html_e( 'Author', 'business-directory' ); ?>
						</th>
						<th scope="col" class="column-review">
							<?php esc_html_e( 'Review', 'business-directory' ); ?>
						</th>
						<th scope="col" class="column-rating">
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
								<?php esc_html_e( 'Rating', 'business-directory' ); ?>
								<?php if ( 'rating' === $orderby ) : ?>
									<span class="sorting-indicator <?php echo 'ASC' === $order ? 'asc' : 'desc'; ?>"></span>
								<?php endif; ?>
							</a>
						</th>
						<th scope="col" class="column-status">
							<?php esc_html_e( 'Status', 'business-directory' ); ?>
						</th>
						<th scope="col" class="column-date">
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
								<?php esc_html_e( 'Date', 'business-directory' ); ?>
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
							<td colspan="7" class="bd-no-reviews">
								<?php esc_html_e( 'No reviews found.', 'business-directory' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $reviews as $review ) : ?>
							<?php
							$business       = get_post( $review['business_id'] );
							$business_title = $business ? $business->post_title : __( 'Unknown Business', 'business-directory' );
							$business_url   = $business ? get_permalink( $business->ID ) . '#review-' . $review['id'] : '#';
							$edit_url       = $business ? get_edit_post_link( $business->ID ) : '#';
							$is_seeded      = strpos( $review['author_email'], '@community.local' ) !== false;
							$full_content   = $review['content'];
							$short_content  = wp_trim_words( $full_content, 15, '...' );
							$is_truncated   = strlen( $full_content ) > strlen( $short_content );
							?>
							<tr data-review-id="<?php echo esc_attr( $review['id'] ); ?>" class="bd-review-row">
								<th scope="row" class="check-column">
									<input type="checkbox" class="bd-review-checkbox" value="<?php echo esc_attr( $review['id'] ); ?>">
								</th>
								<td class="column-business">
									<strong>
										<a href="<?php echo esc_url( $edit_url ); ?>">
											<?php echo esc_html( $business_title ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="view">
											<a href="<?php echo esc_url( $business_url ); ?>" target="_blank">
												<?php esc_html_e( 'View', 'business-directory' ); ?>
											</a> |
										</span>
										<span class="edit">
											<a href="<?php echo esc_url( $edit_url ); ?>">
												<?php esc_html_e( 'Edit', 'business-directory' ); ?>
											</a>
										</span>
									</div>
								</td>
								<td class="column-author">
									<strong><?php echo esc_html( $review['author_name'] ); ?></strong>
									<?php if ( $is_seeded ) : ?>
										<span class="bd-seeded-badge" title="<?php esc_attr_e( 'AI Generated Review', 'business-directory' ); ?>">🤖</span>
									<?php endif; ?>
									<br>
									<a href="mailto:<?php echo esc_attr( $review['author_email'] ); ?>" class="bd-author-email">
										<?php echo esc_html( $review['author_email'] ); ?>
									</a>
									<?php if ( ! empty( $review['user_id'] ) ) : ?>
										<br>
										<span class="bd-user-badge">
											<?php esc_html_e( 'Member', 'business-directory' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="column-review">
									<?php if ( ! empty( $review['title'] ) ) : ?>
										<strong class="bd-review-title"><?php echo esc_html( $review['title'] ); ?></strong>
									<?php endif; ?>
									<div class="bd-review-content">
										<span class="bd-content-short"><?php echo esc_html( $short_content ); ?></span>
										<?php if ( $is_truncated ) : ?>
											<span class="bd-content-full" style="display:none;"><?php echo esc_html( $full_content ); ?></span>
											<a href="#" class="bd-toggle-content"><?php esc_html_e( 'Show more', 'business-directory' ); ?></a>
										<?php endif; ?>
									</div>
									<?php if ( ! empty( $review['photo_ids'] ) ) : ?>
										<span class="bd-has-photos">📷 <?php esc_html_e( 'Has photos', 'business-directory' ); ?></span>
									<?php endif; ?>
									
									<!-- Quick Actions -->
									<div class="bd-quick-actions">
										<?php if ( 'approved' !== $review['status'] ) : ?>
											<button type="button" class="bd-quick-btn bd-quick-approve" 
												data-action="approve" data-review-id="<?php echo esc_attr( $review['id'] ); ?>"
												title="<?php esc_attr_e( 'Approve', 'business-directory' ); ?>">✓</button>
										<?php endif; ?>
										<?php if ( 'rejected' !== $review['status'] ) : ?>
											<button type="button" class="bd-quick-btn bd-quick-reject" 
												data-action="reject" data-review-id="<?php echo esc_attr( $review['id'] ); ?>"
												title="<?php esc_attr_e( 'Reject', 'business-directory' ); ?>">✗</button>
										<?php endif; ?>
										<button type="button" class="bd-quick-btn bd-quick-delete" 
											data-action="delete" data-review-id="<?php echo esc_attr( $review['id'] ); ?>"
											title="<?php esc_attr_e( 'Delete', 'business-directory' ); ?>">🗑</button>
									</div>
								</td>
								<td class="column-rating">
									<span class="bd-rating-stars" title="<?php echo esc_attr( $review['rating'] ); ?> stars">
										<?php echo esc_html( str_repeat( '★', $review['rating'] ) ); ?><span class="bd-stars-empty"><?php echo esc_html( str_repeat( '☆', 5 - $review['rating'] ) ); ?></span>
									</span>
									<?php if ( ! empty( $review['helpful_count'] ) ) : ?>
										<br>
										<span class="bd-helpful-count" title="<?php esc_attr_e( 'Helpful votes', 'business-directory' ); ?>">
											👍 <?php echo esc_html( $review['helpful_count'] ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="column-status">
									<span class="bd-status bd-status-<?php echo esc_attr( $review['status'] ); ?>">
										<?php echo esc_html( ucfirst( $review['status'] ) ); ?>
									</span>
								</td>
								<td class="column-date">
									<span class="bd-date"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $review['created_at'] ) ) ); ?></span>
									<br>
									<span class="bd-time"><?php echo esc_html( date_i18n( 'g:i a', strtotime( $review['created_at'] ) ) ); ?></span>
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
						<span class="pagination-links">
							<?php
							$pagination_args = array(
								'status'      => $current_status,
								's'           => $search,
								'business_id' => $business_id,
								'rating'      => $rating,
								'date_from'   => $date_from,
								'date_to'     => $date_to,
								'seeded'      => $seeded,
								'orderby'     => $orderby,
								'order'       => $order,
							);
							$pagination_args = array_filter( $pagination_args );

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
