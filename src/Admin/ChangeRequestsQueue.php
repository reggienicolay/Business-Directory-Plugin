<?php
/**
 * Admin Change Requests Queue
 *
 * Admin interface for reviewing and approving/rejecting business edit requests.
 *
 * @package BusinessDirectory
 */

namespace BD\Admin;

use BD\DB\ChangeRequestsTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ChangeRequestsQueue
 */
class ChangeRequestsQueue {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bd_approve_changes', array( $this, 'ajax_approve' ) );
		add_action( 'wp_ajax_bd_reject_changes', array( $this, 'ajax_reject' ) );
	}

	/**
	 * Add submenu page.
	 */
	public function add_menu_page() {
		$pending_count = ChangeRequestsTable::count_pending();

		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Pending Changes', 'business-directory' ),
			sprintf(
				// translators: %s is the pending count badge HTML.
				__( 'Pending Changes %s', 'business-directory' ),
				$pending_count > 0 ? '<span class="awaiting-mod count-' . $pending_count . '"><span class="pending-count">' . number_format_i18n( $pending_count ) . '</span></span>' : ''
			),
			'manage_options',
			'bd-pending-changes',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'bd_business_page_bd-pending-changes' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bd-admin-changes',
			plugins_url( 'assets/css/admin-changes.css', BD_PLUGIN_FILE ),
			array(),
			BD_VERSION
		);

		wp_enqueue_script(
			'bd-admin-changes',
			plugins_url( 'assets/js/admin-changes.js', BD_PLUGIN_FILE ),
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		wp_localize_script(
			'bd-admin-changes',
			'bdAdminChanges',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bd_admin_changes_nonce' ),
				'i18n'    => array(
					'approving'      => __( 'Approving...', 'business-directory' ),
					'rejecting'      => __( 'Rejecting...', 'business-directory' ),
					'approved'       => __( 'Changes approved!', 'business-directory' ),
					'rejected'       => __( 'Changes rejected.', 'business-directory' ),
					'error'          => __( 'An error occurred.', 'business-directory' ),
					'confirmReject'  => __( 'Please provide a reason for rejection:', 'business-directory' ),
					'rejectRequired' => __( 'A rejection reason is required.', 'business-directory' ),
				),
			)
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		$pending_requests = ChangeRequestsTable::get_pending();
		?>
		<div class="wrap bd-changes-wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-update" style="margin-right: 8px;"></span>
				<?php esc_html_e( 'Pending Listing Changes', 'business-directory' ); ?>
			</h1>

			<hr class="wp-header-end">

			<!-- Stats Cards -->
			<div class="bd-stats-cards">
				<div class="bd-stat-card bd-stat-pending">
					<div class="bd-stat-icon">📝</div>
					<div class="bd-stat-content">
						<div class="bd-stat-value"><?php echo count( $pending_requests ); ?></div>
						<div class="bd-stat-label"><?php esc_html_e( 'Pending Review', 'business-directory' ); ?></div>
					</div>
				</div>

				<div class="bd-stat-card bd-stat-info">
					<div class="bd-stat-icon">ℹ️</div>
					<div class="bd-stat-content">
						<div class="bd-stat-value">24h</div>
						<div class="bd-stat-label"><?php esc_html_e( 'Target Response', 'business-directory' ); ?></div>
					</div>
				</div>
			</div>

			<?php if ( empty( $pending_requests ) ) : ?>
				<div class="bd-empty-state">
					<div class="bd-empty-icon">✅</div>
					<h2><?php esc_html_e( 'All caught up!', 'business-directory' ); ?></h2>
					<p><?php esc_html_e( 'No pending change requests at the moment.', 'business-directory' ); ?></p>
				</div>
			<?php else : ?>
				<div class="bd-changes-list">
					<?php foreach ( $pending_requests as $request ) : ?>
						<?php $this->render_request_card( $request ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a single request card.
	 *
	 * @param array $request Request data.
	 */
	private function render_request_card( $request ) {
		$business       = get_post( $request['business_id'] );
		$business_title = $business ? $business->post_title : __( 'Unknown Business', 'business-directory' );
		$user           = get_user_by( 'id', $request['user_id'] );
		$time_ago       = human_time_diff( strtotime( $request['created_at'] ), current_time( 'timestamp' ) );
		$changes        = $request['changes'];
		$original       = $request['original'];
		?>
		<div class="bd-change-card" data-request-id="<?php echo esc_attr( $request['id'] ); ?>">
			<div class="bd-change-header">
				<div class="bd-change-business">
					<h3>
						<span class="dashicons dashicons-store"></span>
						<?php echo esc_html( $business_title ); ?>
					</h3>
					<?php if ( $business ) : ?>
						<a href="<?php echo esc_url( get_permalink( $business ) ); ?>" target="_blank" class="bd-view-link">
							<?php esc_html_e( 'View Listing', 'business-directory' ); ?> →
						</a>
					<?php endif; ?>
				</div>
				<div class="bd-change-meta">
					<span class="bd-time-badge">
						<span class="dashicons dashicons-clock"></span>
						<?php echo esc_html( $time_ago ); ?> ago
					</span>
				</div>
			</div>

			<div class="bd-change-body">
				<!-- Submitter Info -->
				<div class="bd-change-submitter">
					<?php if ( $user ) : ?>
						<strong><?php esc_html_e( 'Submitted by:', 'business-directory' ); ?></strong>
						<?php echo esc_html( $user->display_name ); ?>
						(<a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a>)
					<?php endif; ?>
				</div>

				<!-- Change Summary -->
				<div class="bd-change-summary">
					<strong><?php esc_html_e( 'Summary:', 'business-directory' ); ?></strong>
					<?php echo esc_html( $request['change_summary'] ); ?>
				</div>

				<!-- Detailed Changes (Diff View) -->
				<div class="bd-change-diff">
					<h4><?php esc_html_e( 'Changes:', 'business-directory' ); ?></h4>

					<?php foreach ( $changes as $field => $new_value ) : ?>
						<?php $old_value = $original[ $field ] ?? null; ?>
						<div class="bd-diff-item">
							<div class="bd-diff-field"><?php echo esc_html( $this->get_field_label( $field ) ); ?></div>
							<div class="bd-diff-content">
								<?php if ( is_array( $new_value ) ) : ?>
									<?php $this->render_array_diff( $field, $old_value, $new_value ); ?>
								<?php else : ?>
									<div class="bd-diff-old">
										<span class="bd-diff-label"><?php esc_html_e( 'Before:', 'business-directory' ); ?></span>
										<span class="bd-diff-value"><?php echo esc_html( $this->truncate( $old_value, 200 ) ); ?></span>
									</div>
									<div class="bd-diff-new">
										<span class="bd-diff-label"><?php esc_html_e( 'After:', 'business-directory' ); ?></span>
										<span class="bd-diff-value"><?php echo esc_html( $this->truncate( $new_value, 200 ) ); ?></span>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="bd-change-actions">
				<button type="button" class="button button-primary bd-approve-btn" data-request-id="<?php echo esc_attr( $request['id'] ); ?>">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Approve Changes', 'business-directory' ); ?>
				</button>
				<button type="button" class="button bd-reject-btn" data-request-id="<?php echo esc_attr( $request['id'] ); ?>">
					<span class="dashicons dashicons-no"></span>
					<?php esc_html_e( 'Reject', 'business-directory' ); ?>
				</button>
			</div>

			<!-- Reject Modal (hidden) -->
			<div class="bd-reject-modal" style="display: none;">
				<div class="bd-reject-modal-content">
					<h4><?php esc_html_e( 'Reject Changes', 'business-directory' ); ?></h4>
					<p><?php esc_html_e( 'Please provide a reason for rejecting these changes. This will be sent to the business owner.', 'business-directory' ); ?></p>
					<textarea class="bd-reject-reason" rows="4" placeholder="<?php esc_attr_e( 'Enter rejection reason...', 'business-directory' ); ?>"></textarea>
					<div class="bd-reject-modal-actions">
						<button type="button" class="button bd-reject-cancel"><?php esc_html_e( 'Cancel', 'business-directory' ); ?></button>
						<button type="button" class="button button-primary bd-reject-confirm"><?php esc_html_e( 'Reject Changes', 'business-directory' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get human-readable field label.
	 *
	 * @param string $field Field key.
	 * @return string Field label.
	 */
	private function get_field_label( $field ) {
		$labels = array(
			'title'          => __( 'Business Name', 'business-directory' ),
			'description'    => __( 'Description', 'business-directory' ),
			'contact'        => __( 'Contact Info', 'business-directory' ),
			'location'       => __( 'Location', 'business-directory' ),
			'hours'          => __( 'Business Hours', 'business-directory' ),
			'social'         => __( 'Social Media', 'business-directory' ),
			'categories'     => __( 'Categories', 'business-directory' ),
			'tags'           => __( 'Tags', 'business-directory' ),
			'photos'         => __( 'Photos', 'business-directory' ),
			'featured_image' => __( 'Featured Image', 'business-directory' ),
		);

		return $labels[ $field ] ?? ucfirst( str_replace( '_', ' ', $field ) );
	}

	/**
	 * Render array diff.
	 *
	 * @param string $field     Field name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	private function render_array_diff( $field, $old_value, $new_value ) {
		// Handle contact/location/social - key-value pairs.
		if ( in_array( $field, array( 'contact', 'location', 'social' ), true ) ) {
			$old_value = is_array( $old_value ) ? $old_value : array();
			foreach ( $new_value as $key => $val ) {
				$old_val = $old_value[ $key ] ?? '';
				if ( $val !== $old_val ) {
					echo '<div class="bd-diff-subitem">';
					echo '<span class="bd-diff-subkey">' . esc_html( ucfirst( $key ) ) . ':</span>';
					echo '<span class="bd-diff-old-inline">' . esc_html( $old_val ?: '(empty)' ) . '</span>';
					echo '<span class="bd-diff-arrow">→</span>';
					echo '<span class="bd-diff-new-inline">' . esc_html( $val ?: '(empty)' ) . '</span>';
					echo '</div>';
				}
			}
			return;
		}

		// Handle categories/tags - term IDs.
		if ( in_array( $field, array( 'categories', 'tags' ), true ) ) {
			$taxonomy  = 'categories' === $field ? 'bd_category' : 'bd_tag';
			$old_names = array();
			$new_names = array();

			if ( is_array( $old_value ) ) {
				foreach ( $old_value as $term_id ) {
					$term = get_term( $term_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$old_names[] = $term->name;
					}
				}
			}

			if ( is_array( $new_value ) ) {
				foreach ( $new_value as $term_id ) {
					$term = get_term( $term_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$new_names[] = $term->name;
					}
				}
			}

			echo '<div class="bd-diff-old">';
			echo '<span class="bd-diff-label">' . esc_html__( 'Before:', 'business-directory' ) . '</span>';
			echo '<span class="bd-diff-value">' . esc_html( implode( ', ', $old_names ) ?: '(none)' ) . '</span>';
			echo '</div>';
			echo '<div class="bd-diff-new">';
			echo '<span class="bd-diff-label">' . esc_html__( 'After:', 'business-directory' ) . '</span>';
			echo '<span class="bd-diff-value">' . esc_html( implode( ', ', $new_names ) ) . '</span>';
			echo '</div>';
			return;
		}

		// Handle photos - show thumbnails.
		if ( 'photos' === $field ) {
			echo '<div class="bd-diff-photos">';
			echo '<div class="bd-diff-old">';
			echo '<span class="bd-diff-label">' . esc_html__( 'Before:', 'business-directory' ) . '</span>';
			echo '<div class="bd-photo-thumbs">';
			if ( is_array( $old_value ) ) {
				foreach ( array_slice( $old_value, 0, 5 ) as $photo_id ) {
					$url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
					if ( $url ) {
						echo '<img src="' . esc_url( $url ) . '" alt="">';
					}
				}
				if ( count( $old_value ) > 5 ) {
					echo '<span class="bd-photo-more">+' . ( count( $old_value ) - 5 ) . '</span>';
				}
			}
			echo '</div></div>';

			echo '<div class="bd-diff-new">';
			echo '<span class="bd-diff-label">' . esc_html__( 'After:', 'business-directory' ) . '</span>';
			echo '<div class="bd-photo-thumbs">';
			if ( is_array( $new_value ) ) {
				foreach ( array_slice( $new_value, 0, 5 ) as $photo_id ) {
					$url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
					if ( $url ) {
						echo '<img src="' . esc_url( $url ) . '" alt="">';
					}
				}
				if ( count( $new_value ) > 5 ) {
					echo '<span class="bd-photo-more">+' . ( count( $new_value ) - 5 ) . '</span>';
				}
			}
			echo '</div></div>';
			return;
		}

		// Handle hours.
		if ( 'hours' === $field ) {
			$days = array(
				'monday'    => __( 'Mon', 'business-directory' ),
				'tuesday'   => __( 'Tue', 'business-directory' ),
				'wednesday' => __( 'Wed', 'business-directory' ),
				'thursday'  => __( 'Thu', 'business-directory' ),
				'friday'    => __( 'Fri', 'business-directory' ),
				'saturday'  => __( 'Sat', 'business-directory' ),
				'sunday'    => __( 'Sun', 'business-directory' ),
			);

			$old_value = is_array( $old_value ) ? $old_value : array();

			foreach ( $new_value as $day => $times ) {
				$old_times = $old_value[ $day ] ?? array();
				$old_str   = $this->format_hours( $old_times );
				$new_str   = $this->format_hours( $times );

				if ( $old_str !== $new_str ) {
					$day_label = $days[ $day ] ?? ucfirst( $day );
					echo '<div class="bd-diff-subitem">';
					echo '<span class="bd-diff-subkey">' . esc_html( $day_label ) . ':</span>';
					echo '<span class="bd-diff-old-inline">' . esc_html( $old_str ) . '</span>';
					echo '<span class="bd-diff-arrow">→</span>';
					echo '<span class="bd-diff-new-inline">' . esc_html( $new_str ) . '</span>';
					echo '</div>';
				}
			}
			return;
		}

		// Default: JSON encode.
		echo '<div class="bd-diff-old">';
		echo '<span class="bd-diff-label">' . esc_html__( 'Before:', 'business-directory' ) . '</span>';
		echo '<span class="bd-diff-value"><code>' . esc_html( wp_json_encode( $old_value ) ) . '</code></span>';
		echo '</div>';
		echo '<div class="bd-diff-new">';
		echo '<span class="bd-diff-label">' . esc_html__( 'After:', 'business-directory' ) . '</span>';
		echo '<span class="bd-diff-value"><code>' . esc_html( wp_json_encode( $new_value ) ) . '</code></span>';
		echo '</div>';
	}

	/**
	 * Format hours array to string.
	 *
	 * @param array $times Times array.
	 * @return string Formatted string.
	 */
	private function format_hours( $times ) {
		if ( empty( $times ) ) {
			return __( 'Not set', 'business-directory' );
		}

		if ( ! empty( $times['closed'] ) ) {
			return __( 'Closed', 'business-directory' );
		}

		$open  = $times['open'] ?? '';
		$close = $times['close'] ?? '';

		if ( ! $open && ! $close ) {
			return __( 'Not set', 'business-directory' );
		}

		return $open . ' - ' . $close;
	}

	/**
	 * Truncate text.
	 *
	 * @param string $text   Text to truncate.
	 * @param int    $length Max length.
	 * @return string Truncated text.
	 */
	private function truncate( $text, $length = 100 ) {
		if ( ! $text ) {
			return __( '(empty)', 'business-directory' );
		}

		$text = wp_strip_all_tags( $text );

		if ( strlen( $text ) > $length ) {
			return substr( $text, 0, $length ) . '...';
		}

		return $text;
	}

	/**
	 * AJAX: Approve changes.
	 */
	public function ajax_approve() {
		check_ajax_referer( 'bd_admin_changes_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'business-directory' ) ) );
		}

		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'business-directory' ) ) );
		}

		$request = ChangeRequestsTable::get( $request_id );
		if ( ! $request ) {
			wp_send_json_error( array( 'message' => __( 'Request not found.', 'business-directory' ) ) );
		}

		$result = ChangeRequestsTable::approve( $request_id, get_current_user_id() );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to approve changes.', 'business-directory' ) ) );
		}

		// Notify user.
		$this->notify_user_approved( $request );

		wp_send_json_success( array( 'message' => __( 'Changes approved and applied!', 'business-directory' ) ) );
	}

	/**
	 * AJAX: Reject changes.
	 */
	public function ajax_reject() {
		check_ajax_referer( 'bd_admin_changes_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'business-directory' ) ) );
		}

		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		$reason     = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'business-directory' ) ) );
		}

		if ( ! $reason ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a reason for rejection.', 'business-directory' ) ) );
		}

		$request = ChangeRequestsTable::get( $request_id );
		if ( ! $request ) {
			wp_send_json_error( array( 'message' => __( 'Request not found.', 'business-directory' ) ) );
		}

		$result = ChangeRequestsTable::reject( $request_id, get_current_user_id(), $reason );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to reject changes.', 'business-directory' ) ) );
		}

		// Notify user.
		$this->notify_user_rejected( $request, $reason );

		wp_send_json_success( array( 'message' => __( 'Changes rejected.', 'business-directory' ) ) );
	}

	/**
	 * Notify user of approval.
	 *
	 * @param array $request Request data.
	 */
	private function notify_user_approved( $request ) {
		$user = get_user_by( 'id', $request['user_id'] );
		if ( ! $user ) {
			return;
		}

		$business_name = get_the_title( $request['business_id'] );

		$subject = sprintf(
			'[%s] Your changes have been approved!',
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			"Hi %s,\n\n" .
			"Great news! Your changes to %s have been approved and are now live.\n\n" .
			"Changes made:\n%s\n\n" .
			"View your listing: %s\n\n" .
			"Thank you for keeping your listing up to date!\n\n" .
			"Best regards,\n%s Team",
			$user->display_name,
			$business_name,
			$request['change_summary'],
			get_permalink( $request['business_id'] ),
			get_bloginfo( 'name' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Notify user of rejection.
	 *
	 * @param array  $request Request data.
	 * @param string $reason  Rejection reason.
	 */
	private function notify_user_rejected( $request, $reason ) {
		$user = get_user_by( 'id', $request['user_id'] );
		if ( ! $user ) {
			return;
		}

		$business_name = get_the_title( $request['business_id'] );
		$edit_url      = \BD\Admin\Settings::get_edit_listing_url( $request['business_id'] );

		$subject = sprintf(
			'[%s] Changes to your listing need revision',
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			"Hi %s,\n\n" .
			"We've reviewed your requested changes to %s, but we weren't able to approve them at this time.\n\n" .
			"Reason:\n%s\n\n" .
			"You can make the necessary corrections and resubmit:\n%s\n\n" .
			"If you have questions, please reply to this email.\n\n" .
			"Best regards,\n%s Team",
			$user->display_name,
			$business_name,
			$reason,
			$edit_url,
			get_bloginfo( 'name' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}
}
