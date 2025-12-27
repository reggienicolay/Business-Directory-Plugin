<?php

/**
 * Business Owner Tools Dashboard
 *
 * Central hub for business owners to access marketing tools.
 *
 * @package BusinessDirectory
 */

namespace BD\BusinessTools;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class ToolsDashboard
 */
class ToolsDashboard
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// Frontend shortcode.
		add_shortcode('bd_business_tools', array($this, 'render_dashboard'));
		add_shortcode('bd_owner_dashboard', array($this, 'render_dashboard'));

		// Admin menu.
		add_action('admin_menu', array($this, 'add_admin_menu'));

		// AJAX handlers.
		add_action('wp_ajax_bd_get_widget_code', array($this, 'ajax_get_widget_code'));
		add_action('wp_ajax_bd_save_widget_domains', array($this, 'ajax_save_widget_domains'));
		add_action('wp_ajax_bd_generate_qr', array($this, 'ajax_generate_qr'));
		add_action('wp_ajax_bd_get_badge_code', array($this, 'ajax_get_badge_code'));
		add_action('wp_ajax_bd_update_email_prefs', array($this, 'ajax_update_email_prefs'));
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu()
	{
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__('Business Tools', 'business-directory'),
			__('🛠️ Business Tools', 'business-directory'),
			'manage_options',
			'bd-business-tools',
			array($this, 'render_admin_page')
		);
	}

	/**
	 * Get businesses owned by current user.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of business post objects.
	 */
	public static function get_user_businesses($user_id = null)
	{
		if (! $user_id) {
			$user_id = get_current_user_id();
		}

		if (! $user_id) {
			return array();
		}

		global $wpdb;
		$claims_table = $wpdb->prefix . 'bd_claim_requests';

		// Check if claims table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $claims_table));

		if (! $table_exists) {
			return array();
		}

		// Get approved claims for this user.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claims = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT business_id FROM {$claims_table} WHERE user_id = %d AND status = 'approved'",
				$user_id
			),
			ARRAY_A
		);

		if (empty($claims)) {
			return array();
		}

		$business_ids = wp_list_pluck($claims, 'business_id');

		return get_posts(
			array(
				'post_type'      => 'bd_business',
				'post__in'       => $business_ids,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
	}

	/**
	 * Get business stats for dashboard.
	 *
	 * @param int $business_id Business ID.
	 * @return array Stats array.
	 */
	public static function get_business_stats($business_id)
	{
		global $wpdb;

		$stats = array(
			'views'         => 0,
			'reviews'       => 0,
			'rating'        => 0,
			'shares'        => 0,
			'widget_clicks' => 0,
			'qr_scans'      => 0,
		);

		// Get review count and rating.
		$reviews_table = $wpdb->prefix . 'bd_reviews';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$review_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as count, AVG(rating) as avg_rating 
				FROM {$reviews_table} 
				WHERE business_id = %d AND status = 'approved'",
				$business_id
			),
			ARRAY_A
		);

		if ($review_stats) {
			$stats['reviews'] = (int) $review_stats['count'];
			$stats['rating']  = $review_stats['avg_rating'] ? round((float) $review_stats['avg_rating'], 1) : 0;
		}

		// Get this month's reviews.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$monthly_reviews          = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$reviews_table} 
				WHERE business_id = %d 
				AND status = 'approved' 
				AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
				AND YEAR(created_at) = YEAR(CURRENT_DATE())",
				$business_id
			)
		);
		$stats['monthly_reviews'] = (int) $monthly_reviews;

		// Get share count (if share tracking table exists).
		$share_table = $wpdb->prefix . 'bd_share_tracking';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$share_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $share_table));
		if ($share_exists) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$stats['shares'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$share_table} 
					WHERE content_type = 'business' AND content_id = %d",
					$business_id
				)
			);
		}

		// Get widget clicks.
		$clicks_table = $wpdb->prefix . 'bd_widget_clicks';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$clicks_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $clicks_table));
		if ($clicks_exists) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$stats['widget_clicks'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$clicks_table} 
					WHERE business_id = %d 
					AND MONTH(created_at) = MONTH(CURRENT_DATE())",
					$business_id
				)
			);
		}

		// Get QR scans.
		$scans_table = $wpdb->prefix . 'bd_qr_scans';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$scans_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $scans_table));
		if ($scans_exists) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$stats['qr_scans'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$scans_table} 
					WHERE business_id = %d 
					AND MONTH(created_at) = MONTH(CURRENT_DATE())",
					$business_id
				)
			);
		}

		// Estimate views from post meta or analytics.
		$month_key             = 'bd_views_' . gmdate('Y_m');
		$monthly_views         = get_post_meta($business_id, $month_key, true);
		$stats['views']        = $monthly_views ? (int) $monthly_views : 0;
		$stats['total_views']  = (int) get_post_meta($business_id, 'bd_view_count', true);

		return $stats;
	}

	/**
	 * Render the frontend dashboard.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_dashboard($atts = array())
	{
		if (! is_user_logged_in()) {
			return '<div class="bd-tools-login-required">' .
				'<p>' . esc_html__('Please log in to access your business tools.', 'business-directory') . '</p>' .
				'<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="bd-btn bd-btn-primary">' .
				esc_html__('Log In', 'business-directory') . '</a>' .
				'</div>';
		}

		$businesses = self::get_user_businesses();

		if (empty($businesses)) {
			return '<div class="bd-tools-no-businesses">' .
				'<h3>' . esc_html__('No Claimed Businesses', 'business-directory') . '</h3>' .
				'<p>' . esc_html__('You haven\'t claimed any businesses yet. Claim your business to access marketing tools.', 'business-directory') . '</p>' .
				'<a href="' . esc_url(home_url('/directory/')) . '" class="bd-btn bd-btn-primary">' .
				esc_html__('Find Your Business', 'business-directory') . '</a>' .
				'</div>';
		}

		ob_start();
?>
		<div class="bd-tools-dashboard">
			<?php if (count($businesses) > 1) : ?>
				<div class="bd-tools-business-selector">
					<label for="bd-business-select"><?php esc_html_e('Select Business:', 'business-directory'); ?></label>
					<select id="bd-business-select" class="bd-tools-select">
						<?php foreach ($businesses as $business) : ?>
							<option value="<?php echo esc_attr($business->ID); ?>">
								<?php echo esc_html($business->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<?php foreach ($businesses as $index => $business) : ?>
				<?php $stats = self::get_business_stats($business->ID); ?>
				<div class="bd-tools-business-panel"
					data-business-id="<?php echo esc_attr($business->ID); ?>"
					style="<?php echo $index > 0 ? 'display: none;' : ''; ?>">

					<div class="bd-tools-header">
						<h2><?php echo esc_html($business->post_title); ?></h2>
						<a href="<?php echo esc_url(get_permalink($business->ID)); ?>"
							class="bd-tools-view-listing" target="_blank">
							<?php esc_html_e('View Listing →', 'business-directory'); ?>
						</a>
					</div>

					<!-- Stats Section -->
					<div class="bd-tools-stats">
						<h3><?php esc_html_e('This Month\'s Performance', 'business-directory'); ?></h3>
						<div class="bd-tools-stats-grid">
							<div class="bd-tools-stat">
								<span class="bd-tools-stat-value"><?php echo esc_html(number_format($stats['views'])); ?></span>
								<span class="bd-tools-stat-label"><?php esc_html_e('Views', 'business-directory'); ?></span>
							</div>
							<div class="bd-tools-stat">
								<span class="bd-tools-stat-value"><?php echo esc_html($stats['monthly_reviews']); ?></span>
								<span class="bd-tools-stat-label"><?php esc_html_e('New Reviews', 'business-directory'); ?></span>
							</div>
							<div class="bd-tools-stat">
								<span class="bd-tools-stat-value"><?php echo esc_html($stats['rating'] ?: '—'); ?></span>
								<span class="bd-tools-stat-label"><?php esc_html_e('Rating', 'business-directory'); ?></span>
							</div>
							<div class="bd-tools-stat">
								<span class="bd-tools-stat-value"><?php echo esc_html($stats['widget_clicks'] + $stats['qr_scans']); ?></span>
								<span class="bd-tools-stat-label"><?php esc_html_e('Engagements', 'business-directory'); ?></span>
							</div>
						</div>
					</div>

					<!-- Tools Grid -->
					<div class="bd-tools-grid">
						<!-- Review Widget -->
						<div class="bd-tools-card" data-tool="widget">
							<div class="bd-tools-card-icon">🔗</div>
							<h4><?php esc_html_e('Review Widget', 'business-directory'); ?></h4>
							<p><?php esc_html_e('Embed your reviews on your website to build trust with visitors.', 'business-directory'); ?></p>
							<button class="bd-btn bd-btn-primary bd-tools-open-modal"
								data-modal="widget-modal"
								data-business="<?php echo esc_attr($business->ID); ?>">
								<?php esc_html_e('Get Widget Code', 'business-directory'); ?>
							</button>
						</div>

						<!-- QR Codes -->
						<div class="bd-tools-card" data-tool="qr">
							<div class="bd-tools-card-icon">📱</div>
							<h4><?php esc_html_e('QR Codes', 'business-directory'); ?></h4>
							<p><?php esc_html_e('Generate QR codes for your counter, window, or receipts.', 'business-directory'); ?></p>
							<button class="bd-btn bd-btn-primary bd-tools-open-modal"
								data-modal="qr-modal"
								data-business="<?php echo esc_attr($business->ID); ?>">
								<?php esc_html_e('Generate QR Codes', 'business-directory'); ?>
							</button>
						</div>

						<!-- Featured Badge -->
						<div class="bd-tools-card" data-tool="badge">
							<div class="bd-tools-card-icon">🏅</div>
							<h4><?php esc_html_e('Featured Badge', 'business-directory'); ?></h4>
							<p><?php esc_html_e('Display a "Featured on" badge on your website.', 'business-directory'); ?></p>
							<button class="bd-btn bd-btn-primary bd-tools-open-modal"
								data-modal="badge-modal"
								data-business="<?php echo esc_attr($business->ID); ?>">
								<?php esc_html_e('Get Badge Code', 'business-directory'); ?>
							</button>
						</div>

						<!-- Email Reports -->
						<div class="bd-tools-card" data-tool="email">
							<div class="bd-tools-card-icon">📧</div>
							<h4><?php esc_html_e('Monthly Reports', 'business-directory'); ?></h4>
							<p><?php esc_html_e('Receive monthly performance reports via email.', 'business-directory'); ?></p>
							<button class="bd-btn bd-btn-primary bd-tools-open-modal"
								data-modal="email-modal"
								data-business="<?php echo esc_attr($business->ID); ?>">
								<?php esc_html_e('Manage Reports', 'business-directory'); ?>
							</button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>

			<?php $this->render_modals($businesses[0]->ID); ?>
		</div>
	<?php
		return ob_get_clean();
	}

	/**
	 * Render modal dialogs.
	 *
	 * @param int $business_id Default business ID.
	 */
	private function render_modals($business_id)
	{
	?>
		<!-- Widget Modal -->
		<div id="bd-widget-modal" class="bd-tools-modal">
			<div class="bd-tools-modal-content">
				<button class="bd-tools-modal-close">&times;</button>
				<h3><?php esc_html_e('Review Widget', 'business-directory'); ?></h3>

				<div class="bd-tools-widget-options">
					<label><?php esc_html_e('Widget Style:', 'business-directory'); ?></label>
					<div class="bd-tools-style-options">
						<label class="bd-tools-style-option">
							<input type="radio" name="widget_style" value="compact" checked>
							<span class="bd-tools-style-preview bd-style-compact">
								<span class="bd-preview-stars">★★★★★</span>
								<span class="bd-preview-rating">4.8 (127)</span>
								<span class="bd-preview-btn">Write a Review</span>
							</span>
							<span class="bd-style-label"><?php esc_html_e('Compact', 'business-directory'); ?></span>
						</label>
						<label class="bd-tools-style-option">
							<input type="radio" name="widget_style" value="carousel">
							<span class="bd-tools-style-preview bd-style-carousel">
								<span class="bd-preview-quote">"Great service!"</span>
								<span class="bd-preview-stars">★★★★★</span>
								<span class="bd-preview-author">— Sarah M.</span>
								<span class="bd-preview-dots">● ○ ○</span>
							</span>
							<span class="bd-style-label"><?php esc_html_e('Carousel', 'business-directory'); ?></span>
						</label>
						<label class="bd-tools-style-option">
							<input type="radio" name="widget_style" value="list">
							<span class="bd-tools-style-preview bd-style-list">
								<span class="bd-preview-list-item">
									<span class="bd-preview-stars-sm">★★★★★</span>
									<span class="bd-preview-text">Best place!</span>
								</span>
								<span class="bd-preview-list-item">
									<span class="bd-preview-stars-sm">★★★★☆</span>
									<span class="bd-preview-text">Love it here</span>
								</span>
							</span>
							<span class="bd-style-label"><?php esc_html_e('Full List', 'business-directory'); ?></span>
						</label>
					</div>

					<label><?php esc_html_e('Theme:', 'business-directory'); ?></label>
					<select id="widget-theme" class="bd-tools-select">
						<option value="light"><?php esc_html_e('Light', 'business-directory'); ?></option>
						<option value="dark"><?php esc_html_e('Dark', 'business-directory'); ?></option>
					</select>

					<label><?php esc_html_e('Number of Reviews:', 'business-directory'); ?></label>
					<select id="widget-reviews" class="bd-tools-select">
						<option value="3">3</option>
						<option value="5" selected>5</option>
						<option value="10">10</option>
					</select>

					<label><?php esc_html_e('Allowed Domains:', 'business-directory'); ?></label>
					<textarea id="widget-domains" class="bd-tools-textarea"
						placeholder="example.com&#10;www.example.com"></textarea>
					<p class="bd-tools-help"><?php esc_html_e('One domain per line. The widget will only work on these domains.', 'business-directory'); ?></p>
				</div>

				<div class="bd-tools-code-section">
					<label><?php esc_html_e('Embed Code:', 'business-directory'); ?></label>
					<textarea id="widget-code" class="bd-tools-code" readonly></textarea>
					<button class="bd-btn bd-btn-secondary bd-copy-code" data-target="widget-code">
						<?php esc_html_e('Copy Code', 'business-directory'); ?>
					</button>
				</div>

				<div class="bd-tools-preview-section">
					<label><?php esc_html_e('Preview:', 'business-directory'); ?></label>
					<div id="widget-preview" class="bd-tools-preview"></div>
				</div>
			</div>
		</div>

		<!-- QR Code Modal -->
		<div id="bd-qr-modal" class="bd-tools-modal">
			<div class="bd-tools-modal-content">
				<button class="bd-tools-modal-close">&times;</button>
				<h3><?php esc_html_e('QR Codes', 'business-directory'); ?></h3>

				<div class="bd-tools-qr-options">
					<label><?php esc_html_e('QR Code Type:', 'business-directory'); ?></label>
					<div class="bd-tools-qr-types">
						<label class="bd-tools-qr-type">
							<input type="radio" name="qr_type" value="review" checked>
							<span class="bd-tools-qr-type-info">
								<strong><?php esc_html_e('Review Page', 'business-directory'); ?></strong>
								<small><?php esc_html_e('Links directly to review form', 'business-directory'); ?></small>
							</span>
						</label>
						<label class="bd-tools-qr-type">
							<input type="radio" name="qr_type" value="listing">
							<span class="bd-tools-qr-type-info">
								<strong><?php esc_html_e('Business Listing', 'business-directory'); ?></strong>
								<small><?php esc_html_e('Links to your full listing', 'business-directory'); ?></small>
							</span>
						</label>
					</div>
				</div>

				<div class="bd-tools-qr-preview">
					<div id="qr-preview-image"></div>
					<p class="bd-tools-qr-url" id="qr-url"></p>
				</div>

				<div class="bd-tools-qr-downloads">
					<button class="bd-btn bd-btn-primary bd-download-qr" data-format="png">
						<?php esc_html_e('Download PNG', 'business-directory'); ?>
					</button>
					<button class="bd-btn bd-btn-secondary bd-download-qr" data-format="svg">
						<?php esc_html_e('Download SVG', 'business-directory'); ?>
					</button>
					<button class="bd-btn bd-btn-secondary bd-download-qr" data-format="pdf">
						<?php esc_html_e('Download Print PDF', 'business-directory'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Badge Modal -->
		<div id="bd-badge-modal" class="bd-tools-modal">
			<div class="bd-tools-modal-content">
				<button class="bd-tools-modal-close">&times;</button>
				<h3><?php esc_html_e('Featured Badge', 'business-directory'); ?></h3>

				<div class="bd-tools-badge-options">
					<label><?php esc_html_e('Badge Style:', 'business-directory'); ?></label>
					<div class="bd-tools-badge-styles">
						<label class="bd-tools-badge-style">
							<input type="radio" name="badge_style" value="simple" checked>
							<span class="bd-tools-badge-preview bd-badge-simple">
								📍 Featured on LoveTriValley
							</span>
						</label>
						<label class="bd-tools-badge-style">
							<input type="radio" name="badge_style" value="rating">
							<span class="bd-tools-badge-preview bd-badge-rating">
								⭐ 4.8 on LoveTriValley
							</span>
						</label>
						<label class="bd-tools-badge-style">
							<input type="radio" name="badge_style" value="reviews">
							<span class="bd-tools-badge-preview bd-badge-reviews">
								⭐ 4.8 · 127 reviews
							</span>
						</label>
					</div>

					<label><?php esc_html_e('Size:', 'business-directory'); ?></label>
					<select id="badge-size" class="bd-tools-select">
						<option value="small"><?php esc_html_e('Small (150px)', 'business-directory'); ?></option>
						<option value="medium" selected><?php esc_html_e('Medium (200px)', 'business-directory'); ?></option>
						<option value="large"><?php esc_html_e('Large (300px)', 'business-directory'); ?></option>
					</select>
				</div>

				<div class="bd-tools-code-section">
					<label><?php esc_html_e('Embed Code:', 'business-directory'); ?></label>
					<textarea id="badge-code" class="bd-tools-code" readonly></textarea>
					<button class="bd-btn bd-btn-secondary bd-copy-code" data-target="badge-code">
						<?php esc_html_e('Copy Code', 'business-directory'); ?>
					</button>
				</div>

				<div class="bd-tools-badge-downloads">
					<button class="bd-btn bd-btn-primary bd-download-badge" data-format="svg">
						<?php esc_html_e('Download SVG', 'business-directory'); ?>
					</button>
					<button class="bd-btn bd-btn-secondary bd-download-badge" data-format="png">
						<?php esc_html_e('Download PNG', 'business-directory'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Email Preferences Modal -->
		<div id="bd-email-modal" class="bd-tools-modal">
			<div class="bd-tools-modal-content">
				<button class="bd-tools-modal-close">&times;</button>
				<h3><?php esc_html_e('Monthly Report Settings', 'business-directory'); ?></h3>

				<div class="bd-tools-email-options">
					<label class="bd-tools-checkbox">
						<input type="checkbox" id="email-enabled" checked>
						<span><?php esc_html_e('Send me monthly performance reports', 'business-directory'); ?></span>
					</label>

					<label><?php esc_html_e('Email Address:', 'business-directory'); ?></label>
					<input type="email" id="email-address" class="bd-tools-input"
						value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">

					<label class="bd-tools-checkbox">
						<input type="checkbox" id="email-reviews" checked>
						<span><?php esc_html_e('Include recent reviews in report', 'business-directory'); ?></span>
					</label>

					<label class="bd-tools-checkbox">
						<input type="checkbox" id="email-tips" checked>
						<span><?php esc_html_e('Include tips to improve your listing', 'business-directory'); ?></span>
					</label>
				</div>

				<button class="bd-btn bd-btn-primary bd-save-email-prefs">
					<?php esc_html_e('Save Preferences', 'business-directory'); ?>
				</button>

				<div class="bd-tools-email-preview">
					<button class="bd-btn bd-btn-link bd-send-test-email">
						<?php esc_html_e('Send Test Email', 'business-directory'); ?>
					</button>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page()
	{
	?>
		<div class="wrap">
			<h1><?php esc_html_e('Business Owner Tools', 'business-directory'); ?></h1>
			<p><?php esc_html_e('Manage marketing tools available to business owners.', 'business-directory'); ?></p>

			<div class="bd-admin-tools-stats">
				<h2><?php esc_html_e('Tool Usage Statistics', 'business-directory'); ?></h2>
				<?php $this->render_admin_stats(); ?>
			</div>
		</div>
	<?php
	}

	/**
	 * Render admin statistics.
	 */
	private function render_admin_stats()
	{
		global $wpdb;

		$domains_table = $wpdb->prefix . 'bd_widget_domains';
		$clicks_table  = $wpdb->prefix . 'bd_widget_clicks';
		$scans_table   = $wpdb->prefix . 'bd_qr_scans';

		// Widget domains count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$domain_count = $wpdb->get_var("SELECT COUNT(DISTINCT business_id) FROM {$domains_table}");

		// Widget clicks this month.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$clicks_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$clicks_table} 
			WHERE MONTH(created_at) = MONTH(CURRENT_DATE())"
		);

		// QR scans this month.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$scans_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$scans_table} 
			WHERE MONTH(created_at) = MONTH(CURRENT_DATE())"
		);

	?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Metric', 'business-directory'); ?></th>
					<th><?php esc_html_e('Value', 'business-directory'); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e('Businesses with Widgets', 'business-directory'); ?></td>
					<td><?php echo esc_html($domain_count); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e('Widget Clicks (This Month)', 'business-directory'); ?></td>
					<td><?php echo esc_html($clicks_count); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e('QR Scans (This Month)', 'business-directory'); ?></td>
					<td><?php echo esc_html($scans_count); ?></td>
				</tr>
			</tbody>
		</table>
<?php
	}

	/**
	 * AJAX: Get widget embed code.
	 */
	public function ajax_get_widget_code()
	{
		check_ajax_referer('bd_tools_nonce', 'nonce');

		$business_id = isset($_POST['business_id']) ? absint($_POST['business_id']) : 0;
		$style       = isset($_POST['style']) ? sanitize_text_field(wp_unslash($_POST['style'])) : 'compact';
		$theme       = isset($_POST['theme']) ? sanitize_text_field(wp_unslash($_POST['theme'])) : 'light';
		$reviews     = isset($_POST['reviews']) ? absint($_POST['reviews']) : 5;

		if (! $business_id) {
			wp_send_json_error(array('message' => __('Invalid business ID.', 'business-directory')));
		}

		$code = WidgetGenerator::generate_embed_code($business_id, $style, $theme, $reviews);

		wp_send_json_success(array('code' => $code));
	}

	/**
	 * AJAX: Save widget domains.
	 */
	public function ajax_save_widget_domains()
	{
		check_ajax_referer('bd_tools_nonce', 'nonce');

		$business_id = isset($_POST['business_id']) ? absint($_POST['business_id']) : 0;
		$domains     = isset($_POST['domains']) ? sanitize_textarea_field(wp_unslash($_POST['domains'])) : '';

		if (! $business_id) {
			wp_send_json_error(array('message' => __('Invalid business ID.', 'business-directory')));
		}

		// Verify user owns this business.
		if (! $this->user_owns_business(get_current_user_id(), $business_id)) {
			wp_send_json_error(array('message' => __('You do not have permission to edit this business.', 'business-directory')));
		}

		$result = WidgetGenerator::save_allowed_domains($business_id, $domains);

		if ($result) {
			wp_send_json_success(array('message' => __('Domains saved successfully.', 'business-directory')));
		} else {
			wp_send_json_error(array('message' => __('Failed to save domains.', 'business-directory')));
		}
	}

	/**
	 * AJAX: Generate QR code.
	 */
	public function ajax_generate_qr()
	{
		check_ajax_referer('bd_tools_nonce', 'nonce');

		$business_id = isset($_POST['business_id']) ? absint($_POST['business_id']) : 0;
		$type        = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'review';
		$format      = isset($_POST['format']) ? sanitize_text_field(wp_unslash($_POST['format'])) : 'png';

		if (! $business_id) {
			wp_send_json_error(array('message' => __('Invalid business ID.', 'business-directory')));
		}

		$result = QRGenerator::generate($business_id, $type, $format);

		if ($result) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error(array('message' => __('Failed to generate QR code.', 'business-directory')));
		}
	}

	/**
	 * AJAX: Get badge embed code.
	 */
	public function ajax_get_badge_code()
	{
		check_ajax_referer('bd_tools_nonce', 'nonce');

		$business_id = isset($_POST['business_id']) ? absint($_POST['business_id']) : 0;
		$style       = isset($_POST['style']) ? sanitize_text_field(wp_unslash($_POST['style'])) : 'simple';
		$size        = isset($_POST['size']) ? sanitize_text_field(wp_unslash($_POST['size'])) : 'medium';

		if (! $business_id) {
			wp_send_json_error(array('message' => __('Invalid business ID.', 'business-directory')));
		}

		$code = BadgeGenerator::generate_embed_code($business_id, $style, $size);

		wp_send_json_success(array('code' => $code));
	}

	/**
	 * AJAX: Update email preferences.
	 */
	public function ajax_update_email_prefs()
	{
		check_ajax_referer('bd_tools_nonce', 'nonce');

		$business_id = isset($_POST['business_id']) ? absint($_POST['business_id']) : 0;
		$enabled     = isset($_POST['enabled']) && 'true' === $_POST['enabled'];
		$email       = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		$reviews     = isset($_POST['include_reviews']) && 'true' === $_POST['include_reviews'];
		$tips        = isset($_POST['include_tips']) && 'true' === $_POST['include_tips'];

		if (! $business_id) {
			wp_send_json_error(array('message' => __('Invalid business ID.', 'business-directory')));
		}

		$prefs = array(
			'enabled'         => $enabled,
			'email'           => $email,
			'include_reviews' => $reviews,
			'include_tips'    => $tips,
		);

		update_post_meta($business_id, 'bd_email_report_prefs', $prefs);

		wp_send_json_success(array('message' => __('Preferences saved.', 'business-directory')));
	}

	/**
	 * Check if user owns a business.
	 *
	 * @param int $user_id     User ID.
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	private function user_owns_business($user_id, $business_id)
	{
		global $wpdb;
		$claims_table = $wpdb->prefix . 'bd_claim_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claim = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$claims_table} 
				WHERE user_id = %d AND business_id = %d AND status = 'approved'",
				$user_id,
				$business_id
			)
		);

		return ! empty($claim);
	}
}
