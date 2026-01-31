<?php
/**
 * Duplicates Admin Page
 *
 * Admin interface for detecting and managing duplicate businesses.
 *
 * @package BusinessDirectory
 * @version 1.2.0
 */

namespace BD\Admin;

use BD\DB\DuplicateFinder;
use BD\DB\DuplicateMerger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DuplicatesAdmin
 */
class DuplicatesAdmin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 25 );
		add_action( 'admin_post_bd_merge_duplicates', array( $this, 'handle_merge' ) );
		add_action( 'admin_post_bd_undo_merge', array( $this, 'handle_undo' ) );
		add_action( 'wp_ajax_bd_preview_merge', array( $this, 'ajax_preview_merge' ) );
		add_action( 'wp_ajax_bd_get_group_details', array( $this, 'ajax_get_group_details' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_menu_page() {
		// Use optimized count method instead of running all detection queries.
		$count      = DuplicateFinder::get_duplicate_count();
		$menu_title = $count > 0
			? sprintf(
				/* translators: %s: count badge */
				__( 'Duplicates %s', 'business-directory' ),
				'<span class="awaiting-mod">' . esc_html( $count ) . '</span>'
			)
			: __( 'Duplicates', 'business-directory' );

		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Manage Duplicates', 'business-directory' ),
			$menu_title,
			'manage_options',
			'bd-duplicates',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'bd_business_page_bd-duplicates' !== $hook ) {
			return;
		}

		// Register a dummy stylesheet handle to attach inline styles.
		wp_register_style( 'bd-duplicates-admin', false, array(), '1.2.0' );
		wp_enqueue_style( 'bd-duplicates-admin' );
		wp_add_inline_style( 'bd-duplicates-admin', $this->get_inline_styles() );

		// Register and enqueue a minimal script for localization.
		wp_register_script( 'bd-duplicates-admin', '', array( 'jquery' ), '1.2.0', true );
		wp_enqueue_script( 'bd-duplicates-admin' );

		// Localize script data including nonce for AJAX.
		wp_localize_script(
			'bd-duplicates-admin',
			'bdDuplicates',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_duplicates_nonce' ),
				'strings' => array(
					'selectPrimary' => __( 'Please select a primary business to keep.', 'business-directory' ),
					'confirmMerge'  => __( 'Are you sure you want to merge these businesses? This action may not be fully reversible.', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Get inline CSS styles.
	 *
	 * @return string CSS styles.
	 */
	private function get_inline_styles() {
		return '
			.bd-duplicates-wrap { max-width: 1400px; }
			.bd-duplicates-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
			.bd-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
			.bd-stat-card h3 { margin: 0 0 8px; font-size: 14px; color: #6b7280; font-weight: 500; }
			.bd-stat-card .stat-value { font-size: 32px; font-weight: 700; color: #1f2937; }
			.bd-stat-card.highlight { background: linear-gradient(135deg, #722F37 0%, #8B3A42 100%); }
			.bd-stat-card.highlight h3, .bd-stat-card.highlight .stat-value { color: #fff; }

			.bd-filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
			.bd-filter-tab { padding: 8px 16px; background: #f3f4f6; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.2s; text-decoration: none; color: #374151; }
			.bd-filter-tab:hover { background: #e5e7eb; color: #1f2937; }
			.bd-filter-tab.active { background: #722F37; color: #fff; }
			.bd-filter-tab .count { background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; margin-left: 6px; font-size: 12px; }

			.bd-duplicate-group { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px; overflow: hidden; }
			.bd-group-header { padding: 16px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
			.bd-group-header:hover { background: #f3f4f6; }
			.bd-group-title { font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 12px; }
			.bd-group-meta { display: flex; align-items: center; gap: 16px; font-size: 13px; color: #6b7280; }
			.bd-confidence-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
			.bd-confidence-high { background: #fee2e2; color: #dc2626; }
			.bd-confidence-medium { background: #fef3c7; color: #d97706; }
			.bd-confidence-low { background: #d1fae5; color: #059669; }
			.bd-method-badge { background: #e0e7ff; color: #4f46e5; padding: 4px 10px; border-radius: 12px; font-size: 11px; }

			.bd-group-body { display: none; padding: 20px; }
			.bd-group-body.expanded { display: block; }

			.bd-business-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px; }
			.bd-business-card { border: 2px solid #e5e7eb; border-radius: 8px; padding: 16px; position: relative; transition: all 0.2s; }
			.bd-business-card:hover { border-color: #722F37; }
			.bd-business-card.selected { border-color: #722F37; background: #fdf2f2; }
			.bd-business-card.selected::before { content: "✓ Keep"; position: absolute; top: -10px; right: 12px; background: #722F37; color: #fff; padding: 2px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }

			.bd-card-header { display: flex; gap: 12px; margin-bottom: 12px; }
			.bd-card-thumb { width: 60px; height: 60px; border-radius: 6px; object-fit: cover; background: #f3f4f6; flex-shrink: 0; }
			.bd-card-thumb-placeholder { width: 60px; height: 60px; border-radius: 6px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #9ca3af; flex-shrink: 0; }
			.bd-card-info h4 { margin: 0 0 4px; font-size: 15px; }
			.bd-card-info h4 a { color: #1f2937; text-decoration: none; }
			.bd-card-info h4 a:hover { color: #722F37; }
			.bd-card-status { font-size: 11px; padding: 2px 8px; border-radius: 4px; background: #e5e7eb; color: #374151; display: inline-block; margin-right: 4px; }
			.bd-card-status.publish { background: #d1fae5; color: #059669; }
			.bd-card-status.claimed { background: #fef3c7; color: #d97706; }

			.bd-card-details { font-size: 13px; color: #6b7280; }
			.bd-card-details p { margin: 4px 0; display: flex; align-items: center; gap: 6px; }
			.bd-card-details .dashicons { font-size: 14px; width: 14px; height: 14px; }

			.bd-card-stats { display: flex; gap: 16px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 12px; }
			.bd-card-stats span { display: flex; align-items: center; gap: 4px; }

			.bd-card-actions { margin-top: 12px; display: flex; gap: 8px; }
			.bd-select-btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; text-decoration: none; display: inline-block; }
			.bd-select-btn.primary { background: #722F37; color: #fff; }
			.bd-select-btn.primary:hover { background: #5a252c; }
			.bd-select-btn.secondary { background: #f3f4f6; color: #374151; }
			.bd-select-btn.secondary:hover { background: #e5e7eb; }

			.bd-merge-actions { background: #f9fafb; padding: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
			.bd-merge-options { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
			.bd-merge-options label { display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; }
			.bd-merge-options select { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }

			.bd-merge-btn { padding: 10px 24px; background: #722F37; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
			.bd-merge-btn:hover { background: #5a252c; }
			.bd-merge-btn:disabled { background: #9ca3af; cursor: not-allowed; }

			.bd-no-duplicates { text-align: center; padding: 60px 20px; background: #fff; border-radius: 8px; }
			.bd-no-duplicates .dashicons { font-size: 48px; width: 48px; height: 48px; color: #10b981; margin-bottom: 16px; }
			.bd-no-duplicates h2 { margin: 0 0 8px; color: #1f2937; }
			.bd-no-duplicates p { color: #6b7280; margin: 0; }

			.bd-notice { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
			.bd-notice-success { background: #d1fae5; color: #065f46; }
			.bd-notice-error { background: #fee2e2; color: #991b1b; }
			.bd-notice-info { background: #dbeafe; color: #1e40af; }

			.bd-toggle-icon { transition: transform 0.2s; }
			.bd-toggle-icon.expanded { transform: rotate(180deg); }
		';
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		// Handle notices.
		$this->show_notices();

		// Get filter from query string.
		$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';

		// Validate filter against allowed values.
		$allowed_filters = array_merge( array( 'all' ), DuplicateFinder::get_allowed_methods() );
		if ( ! in_array( $filter, $allowed_filters, true ) ) {
			$filter = 'all';
		}

		// Get duplicates based on filter.
		$methods = array();
		if ( 'all' !== $filter ) {
			$methods = array( $filter );
		}

		// Fetch duplicates once and reuse for statistics.
		$duplicates = DuplicateFinder::find_duplicates( $methods );
		$stats      = DuplicateFinder::get_statistics( $duplicates );

		?>
		<div class="wrap bd-duplicates-wrap">
			<h1><?php esc_html_e( 'Manage Duplicate Businesses', 'business-directory' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Review and merge duplicate business listings to keep your directory clean.', 'business-directory' ); ?></p>

			<!-- Statistics Cards -->
			<div class="bd-duplicates-stats">
				<div class="bd-stat-card highlight">
					<h3><?php esc_html_e( 'Duplicate Groups', 'business-directory' ); ?></h3>
					<div class="stat-value"><?php echo esc_html( $stats['total_groups'] ); ?></div>
				</div>
				<div class="bd-stat-card">
					<h3><?php esc_html_e( 'Total Duplicates', 'business-directory' ); ?></h3>
					<div class="stat-value"><?php echo esc_html( $stats['total_duplicates'] ); ?></div>
				</div>
				<div class="bd-stat-card">
					<h3><?php esc_html_e( 'High Confidence', 'business-directory' ); ?></h3>
					<div class="stat-value"><?php echo esc_html( $stats['by_confidence']['high'] ?? 0 ); ?></div>
				</div>
				<div class="bd-stat-card">
					<h3><?php esc_html_e( 'Medium Confidence', 'business-directory' ); ?></h3>
					<div class="stat-value"><?php echo esc_html( $stats['by_confidence']['medium'] ?? 0 ); ?></div>
				</div>
			</div>

			<!-- Filter Tabs -->
			<div class="bd-filter-tabs">
				<?php
				$filters = array(
					'all'              => __( 'All', 'business-directory' ),
					'exact_title'      => __( 'Exact Title', 'business-directory' ),
					'normalized_title' => __( 'Similar Title', 'business-directory' ),
					'title_city'       => __( 'Title + City', 'business-directory' ),
					'title_address'    => __( 'Title + Address', 'business-directory' ),
					'phone'            => __( 'Phone', 'business-directory' ),
					'website'          => __( 'Website', 'business-directory' ),
				);

				foreach ( $filters as $key => $label ) :
					$count  = 'all' === $key ? $stats['total_groups'] : ( $stats['by_method'][ $key ] ?? 0 );
					$active = $filter === $key ? 'active' : '';
					$url    = add_query_arg( 'filter', $key, admin_url( 'edit.php?post_type=bd_business&page=bd-duplicates' ) );
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="bd-filter-tab <?php echo esc_attr( $active ); ?>">
						<?php echo esc_html( $label ); ?>
						<?php if ( $count > 0 ) : ?>
							<span class="count"><?php echo esc_html( $count ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $duplicates ) ) : ?>
				<!-- No Duplicates -->
				<div class="bd-no-duplicates">
					<span class="dashicons dashicons-yes-alt"></span>
					<h2><?php esc_html_e( 'No Duplicates Found!', 'business-directory' ); ?></h2>
					<p><?php esc_html_e( 'Your directory is clean. No duplicate businesses were detected.', 'business-directory' ); ?></p>
				</div>
			<?php else : ?>
				<!-- Duplicate Groups -->
				<?php foreach ( $duplicates as $index => $group ) : ?>
					<?php $this->render_duplicate_group( $group, $index ); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Toggle group expansion.
			$('.bd-group-header').on('click', function() {
				var $body = $(this).siblings('.bd-group-body');
				var $icon = $(this).find('.bd-toggle-icon');
				$body.toggleClass('expanded');
				$icon.toggleClass('expanded');
			});

			// Select primary business.
			$('.bd-select-primary').on('click', function() {
				var $card = $(this).closest('.bd-business-card');
				var $group = $card.closest('.bd-duplicate-group');
				
				$group.find('.bd-business-card').removeClass('selected');
				$group.find('.bd-primary-input').prop('checked', false);
				
				$card.addClass('selected');
				$card.find('.bd-primary-input').prop('checked', true);
				
				$group.find('.bd-merge-btn').prop('disabled', false);
			});

			// Form submission validation.
			$('.bd-merge-form').on('submit', function(e) {
				var $form = $(this);
				var primarySelected = $form.find('.bd-primary-input:checked').length > 0;
				
				if (!primarySelected) {
					e.preventDefault();
					alert(bdDuplicates.strings.selectPrimary);
					return false;
				}
				
				return confirm(bdDuplicates.strings.confirmMerge);
			});
		});
		</script>
		<?php
	}

	/**
	 * Render a single duplicate group.
	 *
	 * @param array $group Duplicate group data.
	 * @param int   $index Group index.
	 */
	private function render_duplicate_group( $group, $index ) {
		$confidence_info = DuplicateFinder::get_confidence_info( $group['confidence'] );
		$method_label    = DuplicateFinder::get_method_label( $group['method'] );
		$businesses      = DuplicateFinder::get_group_details( $group['business_ids'] );

		?>
		<div class="bd-duplicate-group" data-group-id="<?php echo esc_attr( $index ); ?>">
			<div class="bd-group-header">
				<div class="bd-group-title">
					<span class="bd-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
					<?php echo esc_html( $group['match_key'] ); ?>
					<span class="bd-confidence-badge bd-confidence-<?php echo esc_attr( $group['confidence'] ); ?>">
						<?php echo esc_html( $confidence_info['label'] ); ?>
					</span>
				</div>
				<div class="bd-group-meta">
					<span class="bd-method-badge"><?php echo esc_html( $method_label ); ?></span>
					<span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of businesses */
								_n( '%d business', '%d businesses', $group['count'], 'business-directory' ),
								$group['count']
							)
						);
						?>
					</span>
				</div>
			</div>

			<div class="bd-group-body">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bd-merge-form">
					<?php wp_nonce_field( 'bd_merge_duplicates' ); ?>
					<input type="hidden" name="action" value="bd_merge_duplicates">
					<input type="hidden" name="group_index" value="<?php echo esc_attr( $index ); ?>">

					<p class="description" style="margin-bottom: 16px;">
						<?php esc_html_e( 'Select the primary business to keep. Data from duplicates will be merged into the primary, then duplicates will be processed according to your chosen action.', 'business-directory' ); ?>
					</p>

					<div class="bd-business-grid">
						<?php foreach ( $businesses as $business ) : ?>
							<?php $this->render_business_card( $business ); ?>
						<?php endforeach; ?>
					</div>

					<div class="bd-merge-actions">
						<div class="bd-merge-options">
							<label>
								<?php esc_html_e( 'Action for duplicates:', 'business-directory' ); ?>
								<select name="merge_action">
									<option value="trash"><?php esc_html_e( 'Move to Trash (can undo)', 'business-directory' ); ?></option>
									<option value="redirect"><?php esc_html_e( 'Create Redirect', 'business-directory' ); ?></option>
									<option value="delete"><?php esc_html_e( 'Delete Permanently', 'business-directory' ); ?></option>
								</select>
							</label>
							<label>
								<input type="checkbox" name="merge_reviews" value="1" checked>
								<?php esc_html_e( 'Merge reviews', 'business-directory' ); ?>
							</label>
							<label>
								<input type="checkbox" name="merge_photos" value="1" checked>
								<?php esc_html_e( 'Merge photos', 'business-directory' ); ?>
							</label>
						</div>
						<button type="submit" class="bd-merge-btn" disabled>
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Merge Selected', 'business-directory' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a business card within a duplicate group.
	 *
	 * @param array $business Business data.
	 */
	private function render_business_card( $business ) {
		?>
		<div class="bd-business-card" data-business-id="<?php echo esc_attr( $business['id'] ); ?>">
			<input type="radio" name="primary_id" value="<?php echo esc_attr( $business['id'] ); ?>" class="bd-primary-input" style="display: none;">
			<input type="hidden" name="business_ids[]" value="<?php echo esc_attr( $business['id'] ); ?>">

			<div class="bd-card-header">
				<?php if ( ! empty( $business['thumbnail'] ) ) : ?>
					<img src="<?php echo esc_url( $business['thumbnail'] ); ?>" alt="" class="bd-card-thumb">
				<?php else : ?>
					<div class="bd-card-thumb-placeholder">
						<span class="dashicons dashicons-store"></span>
					</div>
				<?php endif; ?>
				<div class="bd-card-info">
					<h4>
						<a href="<?php echo esc_url( $business['edit_link'] ); ?>" target="_blank">
							<?php echo esc_html( $business['title'] ); ?>
						</a>
					</h4>
					<span class="bd-card-status <?php echo esc_attr( $business['status'] ); ?>">
						<?php echo esc_html( ucfirst( $business['status'] ) ); ?>
					</span>
					<?php if ( $business['is_claimed'] ) : ?>
						<span class="bd-card-status claimed"><?php esc_html_e( 'Claimed', 'business-directory' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="bd-card-details">
				<?php if ( ! empty( $business['address'] ) ) : ?>
					<p><span class="dashicons dashicons-location"></span> <?php echo esc_html( $business['address'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $business['city'] ) ) : ?>
					<p><span class="dashicons dashicons-admin-site-alt3"></span> <?php echo esc_html( $business['city'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $business['phone'] ) ) : ?>
					<p><span class="dashicons dashicons-phone"></span> <?php echo esc_html( $business['phone'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $business['website'] ) ) : ?>
					<?php $host = wp_parse_url( $business['website'], PHP_URL_HOST ); ?>
					<p><span class="dashicons dashicons-admin-links"></span> <?php echo esc_html( $host ? $host : $business['website'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $business['categories'] ) ) : ?>
					<p><span class="dashicons dashicons-category"></span> <?php echo esc_html( implode( ', ', $business['categories'] ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="bd-card-stats">
				<span title="<?php esc_attr_e( 'Reviews', 'business-directory' ); ?>">
					<span class="dashicons dashicons-star-filled" style="color: #f59e0b;"></span>
					<?php echo esc_html( $business['avg_rating'] ); ?> (<?php echo esc_html( $business['review_count'] ); ?>)
				</span>
				<span title="<?php esc_attr_e( 'Created', 'business-directory' ); ?>">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php
					$created_date = ! empty( $business['date_created'] ) ? mysql2date( 'M j, Y', $business['date_created'] ) : '';
					echo esc_html( $created_date ? $created_date : __( 'Unknown', 'business-directory' ) );
					?>
				</span>
				<span title="<?php esc_attr_e( 'ID', 'business-directory' ); ?>">
					#<?php echo esc_html( $business['id'] ); ?>
				</span>
			</div>

			<div class="bd-card-actions">
				<button type="button" class="bd-select-btn primary bd-select-primary">
					<?php esc_html_e( 'Select as Primary', 'business-directory' ); ?>
				</button>
				<a href="<?php echo esc_url( $business['view_link'] ); ?>" target="_blank" class="bd-select-btn secondary">
					<?php esc_html_e( 'View', 'business-directory' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Show admin notices.
	 */
	private function show_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['merged'] ) ) {
			$count = absint( $_GET['merged'] );
			echo '<div class="bd-notice bd-notice-success">';
			echo '<strong>' . esc_html__( 'Success!', 'business-directory' ) . '</strong> ';
			echo esc_html(
				sprintf(
					/* translators: %d: number of businesses */
					_n(
						'%d duplicate business was merged.',
						'%d duplicate businesses were merged.',
						$count,
						'business-directory'
					),
					$count
				)
			);
			echo '</div>';
		}

		if ( isset( $_GET['undone'] ) ) {
			echo '<div class="bd-notice bd-notice-success">';
			echo '<strong>' . esc_html__( 'Merge undone!', 'business-directory' ) . '</strong> ';
			echo esc_html__( 'The previously merged businesses have been restored.', 'business-directory' );
			echo '</div>';
		}

		if ( isset( $_GET['error'] ) ) {
			echo '<div class="bd-notice bd-notice-error">';
			echo '<strong>' . esc_html__( 'Error:', 'business-directory' ) . '</strong> ';
			echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) );
			echo '</div>';
		}
		// phpcs:enable
	}

	/**
	 * Handle merge form submission.
	 */
	public function handle_merge() {
		check_admin_referer( 'bd_merge_duplicates' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'business-directory' ) );
		}

		$primary_id   = isset( $_POST['primary_id'] ) ? absint( $_POST['primary_id'] ) : 0;
		$business_ids = isset( $_POST['business_ids'] ) ? array_map( 'absint', (array) $_POST['business_ids'] ) : array();
		$action       = isset( $_POST['merge_action'] ) ? sanitize_key( $_POST['merge_action'] ) : 'trash';

		// Validate action.
		if ( ! in_array( $action, DuplicateMerger::get_allowed_actions(), true ) ) {
			$action = 'trash';
		}

		if ( ! $primary_id || empty( $business_ids ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'bd-duplicates',
						'error' => __( 'Invalid selection', 'business-directory' ),
					),
					admin_url( 'edit.php?post_type=bd_business' )
				)
			);
			exit;
		}

		// Remove primary from duplicate list.
		$duplicate_ids = array_values(
			array_filter(
				$business_ids,
				function ( $id ) use ( $primary_id ) {
					return absint( $id ) !== absint( $primary_id );
				}
			)
		);

		$options = array(
			'merge_reviews' => ! empty( $_POST['merge_reviews'] ),
			'merge_photos'  => ! empty( $_POST['merge_photos'] ),
		);

		$result = DuplicateMerger::merge( $primary_id, $duplicate_ids, $action, $options );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'bd-duplicates',
						'error' => $result->get_error_message(),
					),
					admin_url( 'edit.php?post_type=bd_business' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'bd-duplicates',
					'merged' => count( $duplicate_ids ),
				),
				admin_url( 'edit.php?post_type=bd_business' )
			)
		);
		exit;
	}

	/**
	 * Handle undo merge action.
	 */
	public function handle_undo() {
		check_admin_referer( 'bd_undo_merge' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'business-directory' ) );
		}

		$primary_id = isset( $_GET['primary_id'] ) ? absint( $_GET['primary_id'] ) : 0;

		if ( ! $primary_id ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'bd-duplicates',
						'error' => __( 'Invalid business ID', 'business-directory' ),
					),
					admin_url( 'edit.php?post_type=bd_business' )
				)
			);
			exit;
		}

		$result = DuplicateMerger::undo_merge( $primary_id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'bd-duplicates',
						'error' => $result->get_error_message(),
					),
					admin_url( 'edit.php?post_type=bd_business' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'bd-duplicates',
					'undone' => 1,
				),
				admin_url( 'edit.php?post_type=bd_business' )
			)
		);
		exit;
	}

	/**
	 * AJAX handler for merge preview.
	 */
	public function ajax_preview_merge() {
		check_ajax_referer( 'bd_duplicates_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'business-directory' ) ) );
		}

		$primary_id    = isset( $_POST['primary_id'] ) ? absint( $_POST['primary_id'] ) : 0;
		$duplicate_ids = isset( $_POST['duplicate_ids'] ) ? array_map( 'absint', (array) $_POST['duplicate_ids'] ) : array();

		if ( ! $primary_id || empty( $duplicate_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid selection', 'business-directory' ) ) );
		}

		$preview = DuplicateMerger::preview_merge( $primary_id, $duplicate_ids );

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX handler for getting group details.
	 */
	public function ajax_get_group_details() {
		check_ajax_referer( 'bd_duplicates_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'business-directory' ) ) );
		}

		$business_ids = isset( $_POST['business_ids'] ) ? array_map( 'absint', (array) $_POST['business_ids'] ) : array();

		if ( empty( $business_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No businesses specified', 'business-directory' ) ) );
		}

		$details = DuplicateFinder::get_group_details( $business_ids );

		wp_send_json_success( array( 'businesses' => $details ) );
	}
}
