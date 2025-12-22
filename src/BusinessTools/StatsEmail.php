<?php
/**
 * Stats Email
 *
 * Sends monthly performance reports to business owners.
 *
 * @package BusinessDirectory
 */

namespace BD\BusinessTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class StatsEmail
 */
class StatsEmail {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Schedule monthly email.
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( 'bd_send_monthly_stats_emails', array( $this, 'send_all_reports' ) );

		// AJAX for test email.
		add_action( 'wp_ajax_bd_send_test_stats_email', array( $this, 'ajax_send_test_email' ) );
	}

	/**
	 * Schedule cron job.
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( 'bd_send_monthly_stats_emails' ) ) {
			// Schedule for first Monday of each month at 9 AM.
			$next = strtotime( 'first monday of next month 09:00:00' );
			wp_schedule_event( $next, 'monthly', 'bd_send_monthly_stats_emails' );
		}
	}

	/**
	 * Send all monthly reports.
	 */
	public function send_all_reports() {
		global $wpdb;
		$claims_table = $wpdb->prefix . 'bd_claim_requests';

		// Get all approved claims.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claims = $wpdb->get_results(
			"SELECT DISTINCT user_id, business_id FROM {$claims_table} WHERE status = 'approved'",
			ARRAY_A
		);

		if ( empty( $claims ) ) {
			return;
		}

		foreach ( $claims as $claim ) {
			$this->send_report( $claim['user_id'], $claim['business_id'] );
		}
	}

	/**
	 * Send report for a single business.
	 *
	 * @param int $user_id     User ID.
	 * @param int $business_id Business ID.
	 * @return bool Success.
	 */
	public function send_report( $user_id, $business_id ) {
		// Check email preferences.
		$prefs = get_post_meta( $business_id, 'bd_email_report_prefs', true );

		if ( is_array( $prefs ) && isset( $prefs['enabled'] ) && ! $prefs['enabled'] ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$business = get_post( $business_id );
		if ( ! $business ) {
			return false;
		}

		// Get email address.
		$email = is_array( $prefs ) && ! empty( $prefs['email'] ) ? $prefs['email'] : $user->user_email;

		if ( ! is_email( $email ) ) {
			return false;
		}

		// Get stats.
		$stats = $this->get_monthly_stats( $business_id );

		// Get recent reviews if enabled.
		$include_reviews = ! is_array( $prefs ) || ! isset( $prefs['include_reviews'] ) || $prefs['include_reviews'];
		$reviews         = $include_reviews ? $this->get_recent_reviews( $business_id, 3 ) : array();

		// Get tips if enabled.
		$include_tips = ! is_array( $prefs ) || ! isset( $prefs['include_tips'] ) || $prefs['include_tips'];
		$tips         = $include_tips ? $this->get_tips( $stats ) : array();

		// Generate email content.
		$subject = sprintf(
			// translators: %1$s is month name, %2$s is year.
			__( 'Your %1$s %2$s Stats on %3$s', 'business-directory' ),
			gmdate( 'F' ),
			gmdate( 'Y' ),
			get_bloginfo( 'name' )
		);

		$html = $this->generate_email_html( $business, $stats, $reviews, $tips );

		// Send email.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $email, $subject, $html, $headers );
	}

	/**
	 * Get monthly stats for a business.
	 *
	 * @param int $business_id Business ID.
	 * @return array Stats.
	 */
	private function get_monthly_stats( $business_id ) {
		global $wpdb;

		$stats = array(
			'views'           => 0,
			'views_change'    => 0,
			'reviews'         => 0,
			'reviews_change'  => 0,
			'rating'          => 0,
			'widget_clicks'   => 0,
			'qr_scans'        => 0,
		);

		// Current month dates.
		$current_month_start = gmdate( 'Y-m-01' );
		$current_month_end   = gmdate( 'Y-m-t' );

		// Last month dates.
		$last_month_start = gmdate( 'Y-m-01', strtotime( '-1 month' ) );
		$last_month_end   = gmdate( 'Y-m-t', strtotime( '-1 month' ) );

		// Reviews this month.
		$reviews_table = $wpdb->prefix . 'bd_reviews';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stats['reviews'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$reviews_table} 
				WHERE business_id = %d 
				AND status = 'approved' 
				AND created_at >= %s AND created_at <= %s",
				$business_id,
				$last_month_start,
				$last_month_end
			)
		);

		// Reviews last month for comparison.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$last_month_reviews = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$reviews_table} 
				WHERE business_id = %d 
				AND status = 'approved' 
				AND created_at >= %s AND created_at <= %s",
				$business_id,
				gmdate( 'Y-m-01', strtotime( '-2 months' ) ),
				gmdate( 'Y-m-t', strtotime( '-2 months' ) )
			)
		);

		$stats['reviews_change'] = $stats['reviews'] - $last_month_reviews;

		// Current rating.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rating = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$reviews_table} 
				WHERE business_id = %d AND status = 'approved'",
				$business_id
			)
		);
		$stats['rating'] = $rating ? round( (float) $rating, 1 ) : 0;

		// Widget clicks.
		$clicks_table = $wpdb->prefix . 'bd_widget_clicks';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$clicks_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $clicks_table ) );
		if ( $clicks_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$stats['widget_clicks'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$clicks_table} 
					WHERE business_id = %d 
					AND created_at >= %s AND created_at <= %s",
					$business_id,
					$last_month_start,
					$last_month_end
				)
			);
		}

		// QR scans.
		$scans_table = $wpdb->prefix . 'bd_qr_scans';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$scans_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $scans_table ) );
		if ( $scans_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$stats['qr_scans'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$scans_table} 
					WHERE business_id = %d 
					AND created_at >= %s AND created_at <= %s",
					$business_id,
					$last_month_start,
					$last_month_end
				)
			);
		}

		// Views (from post meta or estimate).
		$stats['views'] = (int) get_post_meta( $business_id, 'bd_monthly_views', true );

		return $stats;
	}

	/**
	 * Get recent reviews.
	 *
	 * @param int $business_id Business ID.
	 * @param int $limit       Number of reviews.
	 * @return array Reviews.
	 */
	private function get_recent_reviews( $business_id, $limit = 3 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_reviews';

		$last_month_start = gmdate( 'Y-m-01', strtotime( '-1 month' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rating, content, author_name, created_at 
				FROM {$table} 
				WHERE business_id = %d 
				AND status = 'approved' 
				AND created_at >= %s
				ORDER BY created_at DESC 
				LIMIT %d",
				$business_id,
				$last_month_start,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get tips based on stats.
	 *
	 * @param array $stats Stats array.
	 * @return array Tips.
	 */
	private function get_tips( $stats ) {
		$tips = array();

		if ( $stats['reviews'] < 3 ) {
			$tips[] = __( 'Encourage customers to leave reviews by placing your QR code at the checkout counter.', 'business-directory' );
		}

		if ( $stats['widget_clicks'] < 5 ) {
			$tips[] = __( 'Add our review widget to your website to showcase your ratings to visitors.', 'business-directory' );
		}

		if ( $stats['qr_scans'] < 10 ) {
			$tips[] = __( 'Print your QR code on receipts and table tents to increase review collection.', 'business-directory' );
		}

		if ( empty( $tips ) ) {
			$tips[] = __( 'Keep up the great work! Consider sharing your positive reviews on social media.', 'business-directory' );
		}

		return $tips;
	}

	/**
	 * Generate email HTML.
	 *
	 * @param \WP_Post $business Business post.
	 * @param array    $stats    Stats array.
	 * @param array    $reviews  Reviews array.
	 * @param array    $tips     Tips array.
	 * @return string HTML.
	 */
	private function generate_email_html( $business, $stats, $reviews, $tips ) {
		$site_name    = get_bloginfo( 'name' );
		$site_url     = home_url();
		$business_url = get_permalink( $business->ID );
		$tools_url    = home_url( '/business-tools/' );
		$month_name   = gmdate( 'F', strtotime( '-1 month' ) );

		// Brand colors.
		$primary   = '#1a3a4a';
		$secondary = '#7a9eb8';
		$accent    = '#1e4258';
		$light     = '#a8c4d4';
		$star      = '#f59e0b';

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $month_name ); ?> Stats</title>
		</head>
		<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f0f5f8;">
			<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f5f8; padding: 20px 0;">
				<tr>
					<td align="center">
						<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
							<!-- Header -->
							<tr>
								<td style="background: linear-gradient(135deg, <?php echo $primary; ?> 0%, <?php echo $accent; ?> 100%); padding: 30px; text-align: center;">
									<h1 style="margin: 0; color: #ffffff; font-size: 24px;">📊 Your <?php echo esc_html( $month_name ); ?> Stats</h1>
									<p style="margin: 10px 0 0; color: <?php echo $light; ?>; font-size: 16px;">
										<?php echo esc_html( $business->post_title ); ?>
									</p>
								</td>
							</tr>

							<!-- Stats Grid -->
							<tr>
								<td style="padding: 30px;">
									<table width="100%" cellpadding="0" cellspacing="0">
										<tr>
											<td width="50%" style="padding: 10px; text-align: center; border: 1px solid <?php echo $light; ?>; border-radius: 8px 0 0 0;">
												<div style="font-size: 32px; font-weight: bold; color: <?php echo $primary; ?>;">
													<?php echo esc_html( $stats['reviews'] ); ?>
												</div>
												<div style="font-size: 14px; color: <?php echo $secondary; ?>;">
													<?php esc_html_e( 'New Reviews', 'business-directory' ); ?>
													<?php if ( $stats['reviews_change'] > 0 ) : ?>
														<span style="color: #10b981;">↑<?php echo esc_html( $stats['reviews_change'] ); ?></span>
													<?php elseif ( $stats['reviews_change'] < 0 ) : ?>
														<span style="color: #ef4444;">↓<?php echo esc_html( abs( $stats['reviews_change'] ) ); ?></span>
													<?php endif; ?>
												</div>
											</td>
											<td width="50%" style="padding: 10px; text-align: center; border: 1px solid <?php echo $light; ?>; border-left: none; border-radius: 0 8px 0 0;">
												<div style="font-size: 32px; font-weight: bold; color: <?php echo $star; ?>;">
													<?php echo esc_html( $stats['rating'] ?: '—' ); ?>
												</div>
												<div style="font-size: 14px; color: <?php echo $secondary; ?>;">
													<?php esc_html_e( 'Average Rating', 'business-directory' ); ?>
												</div>
											</td>
										</tr>
										<tr>
											<td width="50%" style="padding: 10px; text-align: center; border: 1px solid <?php echo $light; ?>; border-top: none; border-radius: 0 0 0 8px;">
												<div style="font-size: 32px; font-weight: bold; color: <?php echo $primary; ?>;">
													<?php echo esc_html( $stats['widget_clicks'] ); ?>
												</div>
												<div style="font-size: 14px; color: <?php echo $secondary; ?>;">
													<?php esc_html_e( 'Widget Clicks', 'business-directory' ); ?>
												</div>
											</td>
											<td width="50%" style="padding: 10px; text-align: center; border: 1px solid <?php echo $light; ?>; border-left: none; border-top: none; border-radius: 0 0 8px 0;">
												<div style="font-size: 32px; font-weight: bold; color: <?php echo $primary; ?>;">
													<?php echo esc_html( $stats['qr_scans'] ); ?>
												</div>
												<div style="font-size: 14px; color: <?php echo $secondary; ?>;">
													<?php esc_html_e( 'QR Scans', 'business-directory' ); ?>
												</div>
											</td>
										</tr>
									</table>
								</td>
							</tr>

							<?php if ( ! empty( $reviews ) ) : ?>
							<!-- Recent Reviews -->
							<tr>
								<td style="padding: 0 30px 30px;">
									<h2 style="margin: 0 0 15px; font-size: 18px; color: <?php echo $primary; ?>;">
										⭐ <?php esc_html_e( 'Recent Reviews', 'business-directory' ); ?>
									</h2>
									<?php foreach ( $reviews as $review ) : ?>
										<div style="background: #f8fafc; border-radius: 8px; padding: 15px; margin-bottom: 10px;">
											<div style="color: <?php echo $star; ?>; margin-bottom: 5px;">
												<?php echo esc_html( str_repeat( '★', (int) $review['rating'] ) ); ?>
											</div>
											<p style="margin: 0 0 8px; color: <?php echo $primary; ?>; font-style: italic;">
												"<?php echo esc_html( wp_trim_words( $review['content'], 20 ) ); ?>"
											</p>
											<p style="margin: 0; font-size: 13px; color: <?php echo $secondary; ?>;">
												— <?php echo esc_html( $review['author_name'] ?: __( 'Anonymous', 'business-directory' ) ); ?>, 
												<?php echo esc_html( gmdate( 'M j', strtotime( $review['created_at'] ) ) ); ?>
											</p>
										</div>
									<?php endforeach; ?>
									<a href="<?php echo esc_url( $business_url . '#reviews' ); ?>" 
									   style="display: inline-block; margin-top: 10px; color: <?php echo $secondary; ?>; text-decoration: none;">
										<?php esc_html_e( 'View All Reviews →', 'business-directory' ); ?>
									</a>
								</td>
							</tr>
							<?php endif; ?>

							<?php if ( ! empty( $tips ) ) : ?>
							<!-- Tips -->
							<tr>
								<td style="padding: 0 30px 30px;">
									<h2 style="margin: 0 0 15px; font-size: 18px; color: <?php echo $primary; ?>;">
										💡 <?php esc_html_e( 'Tips to Grow', 'business-directory' ); ?>
									</h2>
									<ul style="margin: 0; padding-left: 20px; color: <?php echo $primary; ?>;">
										<?php foreach ( $tips as $tip ) : ?>
											<li style="margin-bottom: 8px;"><?php echo esc_html( $tip ); ?></li>
										<?php endforeach; ?>
									</ul>
								</td>
							</tr>
							<?php endif; ?>

							<!-- CTA -->
							<tr>
								<td style="padding: 0 30px 30px; text-align: center;">
									<a href="<?php echo esc_url( $tools_url ); ?>" 
									   style="display: inline-block; background: <?php echo $primary; ?>; color: #ffffff; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">
										<?php esc_html_e( 'View Marketing Tools', 'business-directory' ); ?>
									</a>
								</td>
							</tr>

							<!-- Footer -->
							<tr>
								<td style="background: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid <?php echo $light; ?>;">
									<p style="margin: 0 0 10px; font-size: 14px; color: <?php echo $secondary; ?>;">
										📍 <a href="<?php echo esc_url( $site_url ); ?>" style="color: <?php echo $secondary; ?>; text-decoration: none;">
											<?php echo esc_html( $site_name ); ?>
										</a>
									</p>
									<p style="margin: 0; font-size: 12px; color: #94a3b8;">
										<a href="<?php echo esc_url( $tools_url . '?manage=email' ); ?>" style="color: #94a3b8;">
											<?php esc_html_e( 'Manage Email Preferences', 'business-directory' ); ?>
										</a>
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Send test email.
	 */
	public function ajax_send_test_email() {
		check_ajax_referer( 'bd_tools_nonce', 'nonce' );

		$business_id = isset( $_POST['business_id'] ) ? absint( $_POST['business_id'] ) : 0;
		$user_id     = get_current_user_id();

		if ( ! $business_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'business-directory' ) ) );
		}

		$result = $this->send_report( $user_id, $business_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Test email sent!', 'business-directory' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send email.', 'business-directory' ) ) );
		}
	}
}

// Register custom cron schedule.
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once Monthly', 'business-directory' ),
		);
		return $schedules;
	}
);
