<?php

namespace BD\Admin;

class Settings {


	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_menu', array( $this, 'add_pending_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'views_edit-bd_business', array( $this, 'add_pending_view' ) );
		add_filter( 'post_row_actions', array( $this, 'add_approve_link' ), 10, 2 );
		add_action( 'admin_post_bd_approve_business', array( $this, 'handle_approve' ) );
		add_action( 'admin_footer', array( $this, 'inject_approve_buttons' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Settings', 'business-directory' ),
			__( 'Settings', 'business-directory' ),
			'manage_options',
			'bd-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add Pending Submissions submenu with count badge
	 */
	public function add_pending_menu() {
		$pending_count = wp_count_posts( 'bd_business' )->pending;

		add_submenu_page(
			'edit.php?post_type=bd_business',
			__( 'Pending Submissions', 'business-directory' ),
			sprintf(
			// translators: Placeholder for dynamic value.
				__( 'Pending Submissions %s', 'business-directory' ),
				$pending_count > 0 ? '<span class="awaiting-mod count-' . $pending_count . '"><span class="pending-count">' . number_format_i18n( $pending_count ) . '</span></span>' : ''
			),
			'edit_posts',
			'edit.php?post_type=bd_business&post_status=pending'
		);
	}

	/**
	 * Add pending filter to posts list table
	 */
	public function add_pending_view( $views ) {
		$pending_count = wp_count_posts( 'bd_business' )->pending;

		if ( $pending_count > 0 ) {
			$class            = ( isset( $_GET['post_status'] ) && $_GET['post_status'] === 'pending' ) ? 'current' : '';
			$views['pending'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				admin_url( 'edit.php?post_type=bd_business&post_status=pending' ),
				$class,
				__( 'Pending', 'business-directory' ),
				$pending_count
			);
		}

		return $views;
	}

	/**
	 * Add "Approve" link to pending posts via PHP filter
	 */
	public function add_approve_link( $actions, $post ) {
		if ( $post->post_type === 'bd_business' && $post->post_status === 'pending' ) {
			$approve_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=bd_approve_business&business_id=' . $post->ID ),
				'bd_approve_' . $post->ID
			);

			$actions['approve'] = sprintf(
				'<a href="%s" style="color: #00a32a; font-weight: 600;">✓ Approve & Publish</a>',
				$approve_url
			);
		}

		return $actions;
	}

	/**
	 * Inject approve buttons via JavaScript with proper nonces
	 */
	public function inject_approve_buttons() {
		global $pagenow, $wpdb;

		if ( 'edit.php' === $pagenow && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'bd_business' && isset( $_GET['post_status'] ) && $_GET['post_status'] === 'pending' ) {
			// Get all pending business IDs and generate nonces for each
			$pending_ids = $wpdb->get_col(
				"
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'bd_business' 
                AND post_status = 'pending'
            "
			);

			$nonces = array();
			foreach ( $pending_ids as $id ) {
				$nonces[ $id ] = wp_create_nonce( 'bd_approve_' . $id );
			}
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					var nonces = <?php echo json_encode( $nonces ); ?>;

					setTimeout(function() {
						$('table.wp-list-table tbody tr').each(function() {
							var $row = $(this);
							var $title = $row.find('.row-title');
							var $checkbox = $row.find('input[name="post[]"]');

							if ($title.length > 0 && $checkbox.length > 0) {
								var postId = $checkbox.val();

								// Create approve URL with the proper nonce
								var approveUrl = '<?php echo admin_url( 'admin-post.php' ); ?>?action=bd_approve_business&business_id=' + postId + '&_wpnonce=' + nonces[postId];

								// Create approve button
								var $approveBtn = $('<a>', {
									href: approveUrl,
									text: '✓ APPROVE',
									css: {
										'color': '#00a32a',
										'font-weight': '700',
										'text-decoration': 'none',
										'margin-left': '10px',
										'padding': '4px 12px',
										'background': '#e8f5e9',
										'border-radius': '4px',
										'display': 'inline-block'
									}
								});

								$title.after($approveBtn);
							}
						});
					}, 500);
				});
			</script>
			<?php
		}
	}

	/**
	 * Handle the approval
	 */
	public function handle_approve() {
		if ( ! isset( $_GET['business_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( 'Invalid request' );
		}

		$business_id = intval( $_GET['business_id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'bd_approve_' . $business_id ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'edit_post', $business_id ) ) {
			wp_die( 'You do not have permission to approve this business' );
		}

		// Update post status to publish
		wp_update_post(
			array(
				'ID'          => $business_id,
				'post_status' => 'publish',
			)
		);

		// Redirect back to pending list
		wp_redirect( admin_url( 'edit.php?post_type=bd_business&post_status=pending&approved=1' ) );
		exit;
	}

	public function register_settings() {
		register_setting( 'bd_settings', 'bd_turnstile_site_key' );
		register_setting( 'bd_settings', 'bd_turnstile_secret_key' );
		register_setting( 'bd_settings', 'bd_notification_emails' );
	}

	public function render_page() {
		// Handle cache clear action
		if ( isset( $_POST['clear_cache'] ) && check_admin_referer( 'bd_clear_cache' ) ) {
			$this->clear_all_caches();
			echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Cache cleared successfully!</strong></p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php _e( 'Business Directory Settings', 'business-directory' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'bd_settings' ); ?>

				<h2><?php _e( 'Cloudflare Turnstile', 'business-directory' ); ?></h2>
				<p><?php _e( 'Get free keys at: <a href="https://dash.cloudflare.com/sign-up/turnstile" target="_blank">Cloudflare Turnstile</a>', 'business-directory' ); ?></p>

				<table class="form-table">
					<tr>
						<th><label for="bd_turnstile_site_key"><?php _e( 'Site Key', 'business-directory' ); ?></label></th>
						<td>
							<input type="text" id="bd_turnstile_site_key" name="bd_turnstile_site_key" value="<?php echo esc_attr( get_option( 'bd_turnstile_site_key' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th><label for="bd_turnstile_secret_key"><?php _e( 'Secret Key', 'business-directory' ); ?></label></th>
						<td>
							<input type="text" id="bd_turnstile_secret_key" name="bd_turnstile_secret_key" value="<?php echo esc_attr( get_option( 'bd_turnstile_secret_key' ) ); ?>" class="regular-text" />
						</td>
					</tr>
				</table>

				<h2><?php _e( 'Notifications', 'business-directory' ); ?></h2>

				<table class="form-table">
					<tr>
						<th><label for="bd_notification_emails"><?php _e( 'Email Addresses', 'business-directory' ); ?></label></th>
						<td>
							<input type="text" id="bd_notification_emails" name="bd_notification_emails" value="<?php echo esc_attr( get_option( 'bd_notification_emails', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
							<p class="description"><?php _e( 'Comma-separated list of emails to receive notifications', 'business-directory' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<!-- Cache Management Section -->
			<hr style="margin: 40px 0;">

			<h2><?php _e( 'Cache Management', 'business-directory' ); ?></h2>
			<p><?php _e( 'Clear cached filter data, search results, and metadata. Use this after importing businesses or updating categories.', 'business-directory' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'bd_clear_cache' ); ?>
				<input type="hidden" name="clear_cache" value="1">
				<?php submit_button( __( '🔄 Clear All Directory Caches', 'business-directory' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Clear all directory caches
	 */
	private function clear_all_caches() {
		global $wpdb;

		// Clear all bd_ transients
		$wpdb->query(
			"
        DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_bd_%' 
        OR option_name LIKE '_transient_timeout_bd_%'
    "
		);

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}
