<?php

namespace BD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

// Load ClaimRequestsTable if not already loaded
if ( ! class_exists( '\BD\DB\ClaimRequestsTable' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '../DB/ClaimRequestsTable.php';
}


/**
 * Claims Queue Admin Page
 * Manage pending business claim requests
 */
class ClaimsQueue {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_bd_approve_claim', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_bd_reject_claim', array( $this, 'handle_reject' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers
		add_action( 'wp_ajax_bd_approve_claim_ajax', array( $this, 'ajax_approve' ) );
		add_action( 'wp_ajax_bd_reject_claim_ajax', array( $this, 'ajax_reject' ) );
	}

	/**
	 * AJAX approve handler
	 */
	public function ajax_approve() {
		check_ajax_referer( 'bd_claims_admin', 'nonce' );

		if ( ! current_user_can( 'bd_manage_claims' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$claim_id = absint( $_POST['claim_id'] );
		$notes    = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

		$claim = \BD\DB\ClaimRequestsTable::get( $claim_id );

		if ( ! $claim ) {
			wp_send_json_error( 'Claim not found' );
		}

		// Approve the claim
		$admin_id = get_current_user_id();
		\BD\DB\ClaimRequestsTable::approve( $claim_id, $admin_id, $notes );

		// Create/get user and link to business
		$user_id  = $claim['user_id'];
		$password = null;

		if ( ! $user_id ) {
			$user = get_user_by( 'email', $claim['claimant_email'] );

			if ( $user ) {
				$user_id = $user->ID;
			} else {
				// Create new user
				$username = sanitize_user( $claim['claimant_email'] );
				$password = wp_generate_password( 12, true );

				$user_id = wp_create_user( $username, $password, $claim['claimant_email'] );

				if ( ! is_wp_error( $user_id ) ) {
					wp_update_user(
						array(
							'ID'           => $user_id,
							'display_name' => $claim['claimant_name'],
							'first_name'   => $claim['claimant_name'],
						)
					);

					$user = new \WP_User( $user_id );
					$user->set_role( 'business_owner' );
				}
			}
		}

		// Link business to user
		update_post_meta( $claim['business_id'], 'bd_claimed_by', $user_id );
		update_post_meta( $claim['business_id'], 'bd_claim_status', 'claimed' );
		update_post_meta( $claim['business_id'], 'bd_claimed_date', current_time( 'mysql' ) );

		// Send notification
		if ( $password ) {
			$this->send_approval_email( $claim, $password );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX reject handler
	 */
	public function ajax_reject() {
		check_ajax_referer( 'bd_claims_admin', 'nonce' );

		if ( ! current_user_can( 'bd_manage_claims' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$claim_id = absint( $_POST['claim_id'] );
		$notes    = sanitize_textarea_field( $_POST['notes'] );

		if ( empty( $notes ) ) {
			wp_send_json_error( 'Rejection reason required' );
		}

		$claim = \BD\DB\ClaimRequestsTable::get( $claim_id );

		if ( ! $claim ) {
			wp_send_json_error( 'Claim not found' );
		}

		$admin_id = get_current_user_id();
		\BD\DB\ClaimRequestsTable::reject( $claim_id, $admin_id, $notes );

		// Send rejection email
		$this->send_rejection_email( $claim, $notes );

		wp_send_json_success();
	}

	/**
	 * Add admin menu page
	 */
	public function add_menu_page() {
		$pending_count = \BD\DB\ClaimRequestsTable::count_pending();

		$menu_title = $pending_count > 0 ?
		// translators: Placeholder for dynamic value.
			sprintf( __( 'Pending Claims %s', 'business-directory' ), "<span class='awaiting-mod count-{$pending_count}'><span class='pending-count'>" . number_format_i18n( $pending_count ) . '</span></span>' ) :
			__( 'Pending Claims', 'business-directory' );

		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Business Claims', 'business-directory' ),
			$menu_title,
			'manage_options', // Changed to manage_options for now
			'bd-pending-claims',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'bd_business_page_bd-pending-claims' !== $hook ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __DIR__, 1 ) );

		wp_enqueue_style(
			'bd-admin-claims',
			$plugin_url . 'assets/css/admin-claims.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'bd-admin-claims',
			$plugin_url . 'assets/js/admin-claims.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'bd-admin-claims',
			'bdClaimsAdmin',
			array(
				'nonce'   => wp_create_nonce( 'bd_claims_admin' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Render admin page
	 */
	public function render_page() {
		// Handle tab switching
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'pending';

		$pending_claims = \BD\DB\ClaimRequestsTable::get_pending( 100 );

		?>
		<div class="wrap bd-claims-admin">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-admin-users"></span>
				<?php _e( 'Business Claims', 'business-directory' ); ?>
			</h1>
			
			<?php if ( isset( $_GET['approved'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php _e( '✓ Claim approved successfully! User has been notified.', 'business-directory' ); ?></p>
				</div>
			<?php endif; ?>
			
			<?php if ( isset( $_GET['rejected'] ) ) : ?>
				<div class="notice notice-info is-dismissible">
					<p><?php _e( 'Claim rejected. Claimant has been notified.', 'business-directory' ); ?></p>
				</div>
			<?php endif; ?>
			
			<hr class="wp-header-end">
			
			<!-- Stats Cards -->
			<div class="bd-stats-cards">
				<div class="bd-stat-card bd-stat-pending">
					<div class="bd-stat-icon">📋</div>
					<div class="bd-stat-content">
						<div class="bd-stat-value"><?php echo count( $pending_claims ); ?></div>
						<div class="bd-stat-label"><?php _e( 'Pending Review', 'business-directory' ); ?></div>
					</div>
				</div>
				
				<div class="bd-stat-card bd-stat-info">
					<div class="bd-stat-icon">ℹ️</div>
					<div class="bd-stat-content">
						<div class="bd-stat-value">24-48h</div>
						<div class="bd-stat-label"><?php _e( 'Target Response', 'business-directory' ); ?></div>
					</div>
				</div>
			</div>
			
			<?php if ( empty( $pending_claims ) ) : ?>
				<div class="bd-empty-state">
					<div class="bd-empty-icon">✅</div>
					<h2><?php _e( 'All caught up!', 'business-directory' ); ?></h2>
					<p><?php _e( 'No pending claim requests at the moment.', 'business-directory' ); ?></p>
				</div>
			<?php else : ?>
				<div class="bd-claims-list">
					<?php
					foreach ( $pending_claims as $claim ) :
						$business       = get_post( $claim['business_id'] );
						$business_title = $business ? get_the_title( $business ) : __( 'Unknown Business', 'business-directory' );
						$time_ago       = human_time_diff( strtotime( $claim['created_at'] ), current_time( 'timestamp' ) );
						?>
						<div class="bd-claim-card" data-claim-id="<?php echo $claim['id']; ?>">
							<div class="bd-claim-header">
								<div class="bd-claim-business">
									<h3>
										<span class="dashicons dashicons-store"></span>
										<?php echo esc_html( $business_title ); ?>
									</h3>
									<?php if ( $business ) : ?>
										<a href="<?php echo get_permalink( $business ); ?>" target="_blank" class="bd-view-link">
											<?php _e( 'View Listing', 'business-directory' ); ?> →
										</a>
									<?php endif; ?>
								</div>
								<div class="bd-claim-meta">
									<span class="bd-time-badge">
										<span class="dashicons dashicons-clock"></span>
										<?php echo esc_html( $time_ago ); ?> ago
									</span>
								</div>
							</div>
							
							<div class="bd-claim-body">
								<div class="bd-claim-info-grid">
									<div class="bd-info-item">
										<strong><?php _e( 'Claimant:', 'business-directory' ); ?></strong>
										<div><?php echo esc_html( $claim['claimant_name'] ); ?></div>
									</div>
									
									<div class="bd-info-item">
										<strong><?php _e( 'Email:', 'business-directory' ); ?></strong>
										<div>
											<a href="mailto:<?php echo esc_attr( $claim['claimant_email'] ); ?>">
												<?php echo esc_html( $claim['claimant_email'] ); ?>
											</a>
										</div>
									</div>
									
									<?php if ( $claim['claimant_phone'] ) : ?>
									<div class="bd-info-item">
										<strong><?php _e( 'Phone:', 'business-directory' ); ?></strong>
										<div>
											<a href="tel:<?php echo esc_attr( $claim['claimant_phone'] ); ?>">
												<?php echo esc_html( $claim['claimant_phone'] ); ?>
											</a>
										</div>
									</div>
									<?php endif; ?>
									
									<?php if ( $claim['relationship'] ) : ?>
									<div class="bd-info-item">
										<strong><?php _e( 'Relationship:', 'business-directory' ); ?></strong>
										<div><?php echo esc_html( ucfirst( $claim['relationship'] ) ); ?></div>
									</div>
									<?php endif; ?>
								</div>
								
								<?php if ( $claim['message'] ) : ?>
								<div class="bd-claim-message">
									<strong><?php _e( 'Message:', 'business-directory' ); ?></strong>
									<p><?php echo esc_html( $claim['message'] ); ?></p>
								</div>
								<?php endif; ?>
								
								<?php if ( ! empty( $claim['proof_files'] ) && is_array( $claim['proof_files'] ) ) : ?>
								<div class="bd-proof-files">
									<strong><?php _e( 'Proof of Ownership:', 'business-directory' ); ?></strong>
									<div class="bd-files-grid">
										<?php
										foreach ( $claim['proof_files'] as $file_id ) :
											$file_url  = wp_get_attachment_url( $file_id );
											$file_name = basename( get_attached_file( $file_id ) );
											$is_image  = wp_attachment_is_image( $file_id );
											?>
											<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="bd-file-item">
												<?php if ( $is_image ) : ?>
													<img src="<?php echo esc_url( wp_get_attachment_thumb_url( $file_id ) ); ?>" alt="">
												<?php else : ?>
													<span class="dashicons dashicons-media-document"></span>
												<?php endif; ?>
												<span class="bd-file-name"><?php echo esc_html( $file_name ); ?></span>
											</a>
										<?php endforeach; ?>
									</div>
								</div>
								<?php endif; ?>
							</div>
							
							<div class="bd-claim-actions">
								<button type="button" class="button button-primary bd-approve-btn" data-claim-id="<?php echo $claim['id']; ?>">
									<span class="dashicons dashicons-yes"></span>
									<?php _e( 'Approve & Create Account', 'business-directory' ); ?>
								</button>
								
								<button type="button" class="button bd-reject-btn" data-claim-id="<?php echo $claim['id']; ?>">
									<span class="dashicons dashicons-no-alt"></span>
									<?php _e( 'Reject', 'business-directory' ); ?>
								</button>
								
								<?php if ( $business ) : ?>
								<a href="<?php echo get_edit_post_link( $business ); ?>" class="button bd-edit-business-btn">
									<span class="dashicons dashicons-edit"></span>
									<?php _e( 'Edit Business', 'business-directory' ); ?>
								</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			
		</div>
		
		<!-- Approve Modal -->
		<div id="bd-approve-modal" class="bd-modal" style="display: none;">
			<div class="bd-modal-content">
				<div class="bd-modal-header">
					<h2><?php _e( 'Approve Claim', 'business-directory' ); ?></h2>
					<button type="button" class="bd-modal-close">&times;</button>
				</div>
				<div class="bd-modal-body">
					<p><?php _e( 'This will:', 'business-directory' ); ?></p>
					<ul>
						<li>✓ <?php _e( 'Create a new user account (or use existing)', 'business-directory' ); ?></li>
						<li>✓ <?php _e( 'Assign "Business Owner" role', 'business-directory' ); ?></li>
						<li>✓ <?php _e( 'Link user to this business listing', 'business-directory' ); ?></li>
						<li>✓ <?php _e( 'Send welcome email with login credentials', 'business-directory' ); ?></li>
					</ul>
					
					<label for="bd-approve-notes">
						<strong><?php _e( 'Notes (optional):', 'business-directory' ); ?></strong>
					</label>
					<textarea id="bd-approve-notes" rows="3" class="widefat" placeholder="<?php _e( 'Add any internal notes about this approval...', 'business-directory' ); ?>"></textarea>
				</div>
				<div class="bd-modal-footer">
					<button type="button" class="button button-primary bd-confirm-approve">
						<?php _e( 'Confirm Approval', 'business-directory' ); ?>
					</button>
					<button type="button" class="button bd-modal-cancel">
						<?php _e( 'Cancel', 'business-directory' ); ?>
					</button>
				</div>
			</div>
		</div>
		
		<!-- Reject Modal -->
		<div id="bd-reject-modal" class="bd-modal" style="display: none;">
			<div class="bd-modal-content">
				<div class="bd-modal-header">
					<h2><?php _e( 'Reject Claim', 'business-directory' ); ?></h2>
					<button type="button" class="bd-modal-close">&times;</button>
				</div>
				<div class="bd-modal-body">
					<p><?php _e( 'Please provide a reason for rejection. This will be sent to the claimant.', 'business-directory' ); ?></p>
					
					<label for="bd-reject-notes">
						<strong><?php _e( 'Reason for rejection:', 'business-directory' ); ?></strong>
					</label>
					<textarea id="bd-reject-notes" rows="4" class="widefat" placeholder="<?php _e( 'e.g., Unable to verify proof of ownership. Please provide additional documentation...', 'business-directory' ); ?>" required></textarea>
				</div>
				<div class="bd-modal-footer">
					<button type="button" class="button button-primary bd-confirm-reject">
						<?php _e( 'Confirm Rejection', 'business-directory' ); ?>
					</button>
					<button type="button" class="button bd-modal-cancel">
						<?php _e( 'Cancel', 'business-directory' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle approve action (non-AJAX fallback)
	 */
	public function handle_approve() {
		check_admin_referer( 'bd_approve_claim_' . $_POST['claim_id'] );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'business-directory' ) );
		}

		$claim_id = absint( $_POST['claim_id'] );
		$notes    = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

		// Use the same logic as AJAX
		$_POST['nonce'] = wp_create_nonce( 'bd_claims_admin' );
		$this->ajax_approve();

		wp_redirect( add_query_arg( 'approved', '1', admin_url( 'admin.php?page=bd-pending-claims' ) ) );
		exit;
	}

	/**
	 * Handle reject action (non-AJAX fallback)
	 */
	public function handle_reject() {
		check_admin_referer( 'bd_reject_claim_' . $_POST['claim_id'] );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'business-directory' ) );
		}

		$claim_id = absint( $_POST['claim_id'] );
		$notes    = sanitize_textarea_field( $_POST['notes'] );

		if ( empty( $notes ) ) {
			wp_die( __( 'Rejection reason is required.', 'business-directory' ) );
		}

		// Use the same logic as AJAX
		$_POST['nonce'] = wp_create_nonce( 'bd_claims_admin' );
		$this->ajax_reject();

		wp_redirect( add_query_arg( 'rejected', '1', admin_url( 'admin.php?page=bd-pending-claims' ) ) );
		exit;
	}

	/**
	 * Send approval email
	 */
	private function send_approval_email( $claim, $password ) {
		$subject = sprintf( '[%s] Claim Approved - Welcome!', get_bloginfo( 'name' ) );

		$message = sprintf(
			"Hi %s,\n\n" .
			"Great news! Your claim for %s has been approved.\n\n" .
			"Login Details:\n" .
			"Email: %s\n" .
			"Password: %s\n" .
			"Login URL: %s\n\n" .
			"Next steps:\n" .
			"1. Log in using the link above\n" .
			"2. Change your password\n" .
			"3. Complete your business profile\n\n" .
			"Welcome aboard!\n%s Team",
			$claim['claimant_name'],
			get_the_title( $claim['business_id'] ),
			$claim['claimant_email'],
			$password,
			wp_login_url(),
			get_bloginfo( 'name' )
		);

		wp_mail( $claim['claimant_email'], $subject, $message );
	}

	/**
	 * Send rejection email
	 */
	private function send_rejection_email( $claim, $notes ) {
		$subject = sprintf( '[%s] Claim Request Update', get_bloginfo( 'name' ) );

		$message = sprintf(
			"Hi %s,\n\n" .
			"Thank you for your claim request for %s.\n\n" .
			"Unfortunately, we were unable to verify your claim at this time.\n\n" .
			"%s\n\n" .
			"If you believe this is an error, please contact us at %s.\n\n" .
			"Best regards,\n%s Team",
			$claim['claimant_name'],
			get_the_title( $claim['business_id'] ),
			$notes,
			get_option( 'admin_email' ),
			get_bloginfo( 'name' )
		);

		wp_mail( $claim['claimant_email'], $subject, $message );
	}
}