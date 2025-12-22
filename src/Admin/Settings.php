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
				'<a href="%s" style="color: #00a32a; font-weight: 600;">%s</a>',
				$approve_url,
				__( '✓ Approve', 'business-directory' )
			);
		}
		return $actions;
	}

	/**
	 * Inject approve buttons via JavaScript for pending posts
	 */
	public function inject_approve_buttons() {
		$screen = get_current_screen();

		if ( $screen && $screen->id === 'edit-bd_business' && isset( $_GET['post_status'] ) && $_GET['post_status'] === 'pending' ) {
			?>
			<script>
				jQuery(document).ready(function ($) {
					$('.row-actions .approve a').css({
						'background': '#00a32a',
						'color': '#fff',
						'padding': '2px 8px',
						'border-radius': '3px',
						'text-decoration': 'none'
					});
				});
			</script>
			<?php
		}
	}

	/**
	 * Handle approve action
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
		// Security settings.
		register_setting( 'bd_settings', 'bd_turnstile_site_key' );
		register_setting( 'bd_settings', 'bd_turnstile_secret_key' );
		register_setting( 'bd_settings', 'bd_notification_emails' );

		// Page settings.
		register_setting( 'bd_settings', 'bd_business_tools_page' );
		register_setting( 'bd_settings', 'bd_edit_listing_page' );
	}

	public function render_page() {
		// Handle cache clear action
		if ( isset( $_POST['clear_cache'] ) && check_admin_referer( 'bd_clear_cache' ) ) {
			$this->clear_all_caches();
			echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Cache cleared successfully!</strong></p></div>';
		}

		// Handle page creation
		if ( isset( $_POST['create_tools_page'] ) && check_admin_referer( 'bd_create_page' ) ) {
			$page_id = $this->create_business_tools_page();
			if ( $page_id ) {
				update_option( 'bd_business_tools_page', $page_id );
				echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Business Tools page created!</strong></p></div>';
			}
		}

		if ( isset( $_POST['create_edit_page'] ) && check_admin_referer( 'bd_create_page' ) ) {
			$page_id = $this->create_edit_listing_page();
			if ( $page_id ) {
				update_option( 'bd_edit_listing_page', $page_id );
				echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Edit Listing page created!</strong></p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1><?php _e( 'Business Directory Settings', 'business-directory' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'bd_settings' ); ?>

				<!-- Page Settings Section -->
				<h2><?php _e( 'Page Settings', 'business-directory' ); ?></h2>
				<p class="description"><?php _e( 'Select pages for business owner features. Create pages automatically or select existing ones.', 'business-directory' ); ?></p>

				<table class="form-table">
					<tr>
						<th><label for="bd_business_tools_page"><?php _e( 'Business Tools Page', 'business-directory' ); ?></label></th>
						<td>
							<?php
							$tools_page = get_option( 'bd_business_tools_page' );
							wp_dropdown_pages(
								array(
									'name'              => 'bd_business_tools_page',
									'id'                => 'bd_business_tools_page',
									'selected'          => $tools_page,
									'show_option_none'  => __( '— Select a page —', 'business-directory' ),
									'option_none_value' => '',
								)
							);
							?>
							<p class="description">
								<?php _e( 'Page with [bd_business_tools] shortcode. Business owners access their dashboard here.', 'business-directory' ); ?>
								<?php if ( $tools_page ) : ?>
									<br><a href="<?php echo get_permalink( $tools_page ); ?>" target="_blank"><?php _e( 'View page →', 'business-directory' ); ?></a>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="bd_edit_listing_page"><?php _e( 'Edit Listing Page', 'business-directory' ); ?></label></th>
						<td>
							<?php
							$edit_page = get_option( 'bd_edit_listing_page' );
							wp_dropdown_pages(
								array(
									'name'              => 'bd_edit_listing_page',
									'id'                => 'bd_edit_listing_page',
									'selected'          => $edit_page,
									'show_option_none'  => __( '— Select a page —', 'business-directory' ),
									'option_none_value' => '',
								)
							);
							?>
							<p class="description">
								<?php _e( 'Page with [bd_edit_listing] shortcode. Business owners edit their listing here.', 'business-directory' ); ?>
								<?php if ( $edit_page ) : ?>
									<br><a href="<?php echo get_permalink( $edit_page ); ?>" target="_blank"><?php _e( 'View page →', 'business-directory' ); ?></a>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<!-- Quick Page Creation -->
			<hr style="margin: 40px 0;">

			<h2><?php _e( 'Quick Page Setup', 'business-directory' ); ?></h2>
			<p><?php _e( 'Automatically create pages with the correct shortcodes.', 'business-directory' ); ?></p>

			<form method="post" action="" style="display: inline-block; margin-right: 10px;">
				<?php wp_nonce_field( 'bd_create_page' ); ?>
				<input type="hidden" name="create_tools_page" value="1">
				<?php
				$tools_exists = get_option( 'bd_business_tools_page' );
				submit_button(
					$tools_exists ? __( '📄 Recreate Business Tools Page', 'business-directory' ) : __( '📄 Create Business Tools Page', 'business-directory' ),
					'secondary',
					'submit',
					false
				);
				?>
			</form>

			<form method="post" action="" style="display: inline-block;">
				<?php wp_nonce_field( 'bd_create_page' ); ?>
				<input type="hidden" name="create_edit_page" value="1">
				<?php
				$edit_exists = get_option( 'bd_edit_listing_page' );
				submit_button(
					$edit_exists ? __( '📄 Recreate Edit Listing Page', 'business-directory' ) : __( '📄 Create Edit Listing Page', 'business-directory' ),
					'secondary',
					'submit',
					false
				);
				?>
			</form>

			<hr style="margin: 40px 0;">

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
	 * Create Business Tools page automatically
	 */
	private function create_business_tools_page() {
		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Business Tools', 'business-directory' ),
				'post_content' => '[bd_business_tools]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_name'    => 'business-tools',
			)
		);

		return $page_id;
	}

	/**
	 * Create Edit Listing page automatically
	 */
	private function create_edit_listing_page() {
		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Edit Your Listing', 'business-directory' ),
				'post_content' => '[bd_edit_listing]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_name'    => 'edit-listing',
			)
		);

		return $page_id;
	}

	/**
	 * Get Business Tools page URL
	 */
	public static function get_business_tools_url() {
		$page_id = get_option( 'bd_business_tools_page' );
		if ( $page_id ) {
			return get_permalink( $page_id );
		}
		return '';
	}

	/**
	 * Get Edit Listing page URL
	 */
	public static function get_edit_listing_url( $business_id = null ) {
		$page_id = get_option( 'bd_edit_listing_page' );
		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $business_id ) {
				$url = add_query_arg( 'business_id', $business_id, $url );
			}
			return $url;
		}
		return '';
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
