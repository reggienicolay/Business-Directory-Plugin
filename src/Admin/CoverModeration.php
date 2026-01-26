<?php
/**
 * Cover Moderation Admin Page
 *
 * Admin interface for reviewing and moderating user-uploaded cover images.
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Lists\ListManager;
use BD\Lists\CoverManager;

class CoverModeration {

	/**
	 * Page slug
	 */
	const PAGE_SLUG = 'bd-cover-moderation';

	/**
	 * Allowed filter values (whitelist)
	 */
	const ALLOWED_FILTERS = array( 'all', 'recent', 'image', 'video' );

	/**
	 * Items per page
	 */
	const PER_PAGE = 20;

	/**
	 * Initialize admin page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bd_moderate_cover', array( __CLASS__, 'ajax_moderate_cover' ) );
	}

	/**
	 * Add submenu page under Business Directory
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			'Cover Moderation',
			'Cover Moderation',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bd-cover-moderation',
			plugins_url( 'assets/css/admin-cover-moderation.css', dirname( __DIR__ ) ),
			array(),
			BD_VERSION
		);

		wp_enqueue_script(
			'bd-cover-moderation',
			plugins_url( 'assets/js/admin-cover-moderation.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		wp_localize_script(
			'bd-cover-moderation',
			'bdCoverMod',
			array(
				'nonce'   => wp_create_nonce( 'bd_cover_moderation' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Render the admin page
	 */
	public static function render_page() {
		// Sanitize and validate filter
		$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';
		$filter = in_array( $filter, self::ALLOWED_FILTERS, true ) ? $filter : 'all';

		$paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$paged    = max( 1, $paged );
		$per_page = self::PER_PAGE;

		$covers = self::get_covers( $filter, $paged, $per_page );
		$total  = self::get_covers_count( $filter );
		$pages  = ceil( $total / $per_page );

		?>
		<div class="wrap bd-cover-moderation">
			<h1>
				<i class="dashicons dashicons-format-image"></i>
				Cover Moderation
			</h1>

			<p class="description">Review and moderate user-uploaded cover images for lists.</p>

			<!-- Filters -->
			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'filter', 'all' ) ); ?>" 
					   class="<?php echo 'all' === $filter ? 'current' : ''; ?>">
						All <span class="count">(<?php echo esc_html( self::get_covers_count( 'all' ) ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'filter', 'recent' ) ); ?>"
					   class="<?php echo 'recent' === $filter ? 'current' : ''; ?>">
						Recent (7 days) <span class="count">(<?php echo esc_html( self::get_covers_count( 'recent' ) ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'filter', 'image' ) ); ?>"
					   class="<?php echo 'image' === $filter ? 'current' : ''; ?>">
						Photos <span class="count">(<?php echo esc_html( self::get_covers_count( 'image' ) ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'filter', 'video' ) ); ?>"
					   class="<?php echo 'video' === $filter ? 'current' : ''; ?>">
						Videos <span class="count">(<?php echo esc_html( self::get_covers_count( 'video' ) ); ?>)</span>
					</a>
				</li>
			</ul>

			<div class="clear"></div>

			<!-- Covers Grid -->
			<?php if ( empty( $covers ) ) : ?>
				<div class="bd-no-covers">
					<p>No covers found matching this filter.</p>
				</div>
			<?php else : ?>
				<div class="bd-covers-grid">
					<?php foreach ( $covers as $cover ) : ?>
						<?php echo self::render_cover_card( $cover ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>

				<!-- Pagination -->
				<?php if ( $pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<?php
							echo paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
									'total'     => $pages,
									'current'   => $paged,
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render individual cover card
	 *
	 * @param array $cover Cover data from database.
	 * @return string HTML output.
	 */
	private static function render_cover_card( $cover ) {
		// Data comes from optimized JOIN query
		$list_title  = $cover['list_title'] ?? 'Untitled List';
		$author_name = $cover['author_name'] ?? 'Unknown';
		$cover_type  = $cover['cover_type'];
		$visibility  = $cover['visibility'] ?? 'private';

		// Build list URL
		$list_url = home_url( '/lists/' . ( $cover['slug'] ?? $cover['list_id'] ) . '/' );

		// Get cover image URL
		$image_url = null;
		if ( 'image' === $cover_type && ! empty( $cover['cover_image_id'] ) ) {
			$image_url = wp_get_attachment_image_url( $cover['cover_image_id'], 'medium' );
		} elseif ( ! empty( $cover['cover_video_thumb_id'] ) ) {
			$image_url = wp_get_attachment_image_url( $cover['cover_video_thumb_id'], 'medium' );
		} elseif ( ! empty( $cover['cover_video_id'] ) ) {
			$image_url = CoverManager::get_video_thumbnail_url( $cover_type, $cover['cover_video_id'] );
		}

		ob_start();
		?>
		<div class="bd-cover-card" data-list-id="<?php echo esc_attr( $cover['list_id'] ); ?>">
			<div class="bd-cover-card-image">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="">
					<?php if ( in_array( $cover_type, array( 'youtube', 'vimeo' ), true ) ) : ?>
						<span class="bd-cover-type-badge">
							<i class="dashicons dashicons-video-alt3"></i>
							<?php echo esc_html( ucfirst( $cover_type ) ); ?>
						</span>
					<?php endif; ?>
				<?php else : ?>
					<div class="bd-cover-placeholder">
						<i class="dashicons dashicons-format-image"></i>
					</div>
				<?php endif; ?>
			</div>

			<div class="bd-cover-card-info">
				<h4>
					<a href="<?php echo esc_url( $list_url ); ?>" target="_blank">
						<?php echo esc_html( $list_title ); ?>
					</a>
				</h4>

				<div class="bd-cover-meta">
					<span>
						<i class="dashicons dashicons-admin-users"></i>
						<?php echo esc_html( $author_name ); ?>
					</span>
					<span>
						<i class="dashicons dashicons-calendar-alt"></i>
						<?php echo esc_html( human_time_diff( strtotime( $cover['updated_at'] ) ) ); ?> ago
					</span>
					<span>
						<i class="dashicons dashicons-visibility"></i>
						<?php echo esc_html( ucfirst( $visibility ) ); ?>
					</span>
				</div>
			</div>

			<div class="bd-cover-card-actions">
				<?php if ( ! empty( $cover['cover_image_id'] ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $cover['cover_image_id'] . '&action=edit' ) ); ?>" 
					   class="button button-small" target="_blank" 
					   title="View in Media Library">
						<i class="dashicons dashicons-admin-media"></i>
					</a>
				<?php endif; ?>

				<button type="button" class="button button-small bd-remove-cover" 
						data-list-id="<?php echo esc_attr( $cover['list_id'] ); ?>"
						title="Remove Cover">
					<i class="dashicons dashicons-trash"></i>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build WHERE clause for cover queries
	 *
	 * Uses parameterized approach for safety and maintainability.
	 *
	 * @param string $filter Filter type.
	 * @return array Array with 'sql' (WHERE clause) and 'values' (parameters).
	 */
	private static function build_where_clause( $filter ) {
		$clauses = array();
		$values  = array();

		switch ( $filter ) {
			case 'recent':
				$clauses[] = "l.cover_type IN ('image', 'youtube', 'vimeo')";
				$clauses[] = 'l.updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)';
				break;

			case 'image':
				$clauses[] = "l.cover_type = 'image'";
				break;

			case 'video':
				$clauses[] = "l.cover_type IN ('youtube', 'vimeo')";
				break;

			case 'all':
			default:
				$clauses[] = "l.cover_type IN ('image', 'youtube', 'vimeo')";
				break;
		}

		return array(
			'sql'    => implode( ' AND ', $clauses ),
			'values' => $values,
		);
	}

	/**
	 * Get covers with filters (optimized with JOINs)
	 *
	 * @param string $filter   Filter type.
	 * @param int    $page     Current page.
	 * @param int    $per_page Items per page.
	 * @return array Cover data.
	 */
	private static function get_covers( $filter, $page, $per_page ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$where  = self::build_where_clause( $filter );
		$offset = ( $page - 1 ) * $per_page;

		// Optimized: JOIN to get list and user data in single query
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.id as list_id, l.user_id, l.title as list_title, 
				        l.slug, l.visibility, l.cover_type, l.cover_image_id, 
				        l.cover_video_id, l.cover_video_thumb_id, l.updated_at,
				        u.display_name as author_name
				 FROM {$table} l
				 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
				 WHERE {$where['sql']}
				 ORDER BY l.updated_at DESC 
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Get total covers count
	 *
	 * @param string $filter Filter type.
	 * @return int Count.
	 */
	private static function get_covers_count( $filter ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_lists';

		$where = self::build_where_clause( $filter );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} l WHERE {$where['sql']}" );
	}

	/**
	 * AJAX handler for cover moderation
	 */
	public static function ajax_moderate_cover() {
		// Verify nonce
		if ( ! check_ajax_referer( 'bd_cover_moderation', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Verify permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$list_id = absint( $_POST['list_id'] ?? 0 );
		$action  = sanitize_key( $_POST['mod_action'] ?? '' );

		if ( ! $list_id ) {
			wp_send_json_error( 'Invalid list ID' );
		}

		switch ( $action ) {
			case 'remove':
				// Use admin user ID to bypass ownership check
				$result = CoverManager::remove_cover( $list_id, get_current_user_id() );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( $result->get_error_message() );
				}
				wp_send_json_success( 'Cover removed' );
				break;

			default:
				wp_send_json_error( 'Invalid action' );
		}
	}
}

// Initialize
CoverModeration::init();
