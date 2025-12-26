<?php
/**
 * Header Auth Buttons
 *
 * Renders [bd_auth_buttons] shortcode for navigation.
 * Shows login/register links or user dropdown when logged in.
 *
 * @package BusinessDirectory
 * @subpackage Auth
 * @version 1.0.0
 */

namespace BD\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HeaderButtons
 */
class HeaderButtons {

	/**
	 * Initialize shortcode
	 */
	public static function init() {
		add_shortcode( 'bd_auth_buttons', array( __CLASS__, 'render' ) );
		add_shortcode( 'bd_auth_nav', array( __CLASS__, 'render' ) ); // Alias.
	}

	/**
	 * Render auth buttons
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts( array(
			'style'         => 'default', // default, compact, icon-only.
			'show_avatar'   => 'yes',
			'show_dropdown' => 'yes',
			'login_text'    => __( 'Login', 'business-directory' ),
			'register_text' => __( 'Register', 'business-directory' ),
		), $atts );

		if ( is_user_logged_in() ) {
			return self::render_logged_in( $atts );
		}

		return self::render_logged_out( $atts );
	}

	/**
	 * Render logged out state
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	private static function render_logged_out( $atts ) {
		$login_url    = AuthFilters::get_login_page_url();
		$register_url = $login_url ? add_query_arg( 'tab', 'register', $login_url ) : wp_registration_url();

		if ( ! $login_url ) {
			$login_url = wp_login_url();
		}

		// Add current page as redirect.
		if ( ! is_page( 'login' ) ) {
			$current_url  = home_url( add_query_arg( array() ) );
			$login_url    = add_query_arg( 'redirect_to', rawurlencode( $current_url ), $login_url );
			$register_url = add_query_arg( 'redirect_to', rawurlencode( $current_url ), $register_url );
		}

		ob_start();
		?>
		<div class="bd-auth-buttons bd-auth-logged-out bd-auth-style-<?php echo esc_attr( $atts['style'] ); ?>">
			<a href="<?php echo esc_url( $login_url ); ?>" 
				class="bd-auth-btn bd-auth-login-btn"
				data-bd-login="true"
				data-tab="login">
				<svg class="bd-auth-icon" width="18" height="18" viewBox="0 0 24 24" 
					fill="none" stroke="currentColor" stroke-width="2">
					<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
					<circle cx="12" cy="7" r="4"></circle>
				</svg>
				<?php if ( 'icon-only' !== $atts['style'] ) : ?>
					<span class="bd-auth-text"><?php echo esc_html( $atts['login_text'] ); ?></span>
				<?php endif; ?>
			</a>

			<?php if ( 'compact' !== $atts['style'] ) : ?>
				<a href="<?php echo esc_url( $register_url ); ?>" 
					class="bd-auth-btn bd-auth-register-btn"
					data-bd-login="true"
					data-tab="register">
					<?php if ( 'icon-only' !== $atts['style'] ) : ?>
						<span class="bd-auth-text"><?php echo esc_html( $atts['register_text'] ); ?></span>
					<?php endif; ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render logged in state
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	private static function render_logged_in( $atts ) {
		$user        = wp_get_current_user();
		$profile_url = home_url( '/my-profile/' );
		$logout_url  = wp_logout_url( home_url() );

		// Get user display name (first name or display name).
		$display_name = $user->first_name ? $user->first_name : $user->display_name;

		// Truncate if too long.
		if ( strlen( $display_name ) > 15 ) {
			$display_name = substr( $display_name, 0, 12 ) . '...';
		}

		ob_start();
		?>
		<div class="bd-auth-buttons bd-auth-logged-in bd-auth-style-<?php echo esc_attr( $atts['style'] ); ?>">
			<div class="bd-auth-user-wrapper">
				<button type="button" class="bd-auth-user-toggle" aria-expanded="false">
					<?php if ( 'yes' === $atts['show_avatar'] ) : ?>
						<span class="bd-auth-avatar">
							<?php echo get_avatar( $user->ID, 32 ); ?>
						</span>
					<?php else : ?>
						<svg class="bd-auth-icon" width="18" height="18" viewBox="0 0 24 24" 
							fill="none" stroke="currentColor" stroke-width="2">
							<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
							<circle cx="12" cy="7" r="4"></circle>
						</svg>
					<?php endif; ?>

					<?php if ( 'icon-only' !== $atts['style'] ) : ?>
						<span class="bd-auth-name"><?php echo esc_html( $display_name ); ?></span>
					<?php endif; ?>

					<?php if ( 'yes' === $atts['show_dropdown'] ) : ?>
						<svg class="bd-auth-chevron" width="16" height="16" viewBox="0 0 24 24" 
							fill="none" stroke="currentColor" stroke-width="2">
							<polyline points="6 9 12 15 18 9"></polyline>
						</svg>
					<?php endif; ?>
				</button>

				<?php if ( 'yes' === $atts['show_dropdown'] ) : ?>
					<div class="bd-auth-dropdown">
						<div class="bd-auth-dropdown-header">
							<span class="bd-auth-dropdown-name"><?php echo esc_html( $user->display_name ); ?></span>
							<span class="bd-auth-dropdown-email"><?php echo esc_html( $user->user_email ); ?></span>
						</div>

						<ul class="bd-auth-dropdown-menu">
							<li>
								<a href="<?php echo esc_url( $profile_url ); ?>">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
										<circle cx="12" cy="7" r="4"></circle>
									</svg>
									<?php esc_html_e( 'My Profile', 'business-directory' ); ?>
								</a>
							</li>
							<li>
								<a href="<?php echo esc_url( home_url( '/my-lists/' ) ); ?>">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
									</svg>
									<?php esc_html_e( 'My Lists', 'business-directory' ); ?>
								</a>
							</li>

							<?php if ( self::user_has_businesses() ) : ?>
								<li>
									<a href="<?php echo esc_url( home_url( '/edit-listing/' ) ); ?>">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<path d="M12 20h9"></path>
											<path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
										</svg>
										<?php esc_html_e( 'My Business', 'business-directory' ); ?>
									</a>
								</li>
							<?php endif; ?>

							<?php if ( current_user_can( 'manage_options' ) ) : ?>
								<li class="bd-auth-dropdown-divider"></li>
								<li>
									<a href="<?php echo esc_url( admin_url() ); ?>">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" 
											stroke="currentColor" stroke-width="2">
											<circle cx="12" cy="12" r="3"></circle>
											<path d="M12 1v4M12 19v4M4.2 4.2l2.8 2.8M17 17l2.8 2.8"></path>
											<path d="M1 12h4M19 12h4M4.2 19.8l2.8-2.8M17 7l2.8-2.8"></path>
										</svg>
										<?php esc_html_e( 'Admin', 'business-directory' ); ?>
									</a>
								</li>
							<?php endif; ?>

							<li class="bd-auth-dropdown-divider"></li>
							<li>
								<a href="<?php echo esc_url( $logout_url ); ?>" class="bd-auth-logout-link">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
										<polyline points="16 17 21 12 16 7"></polyline>
										<line x1="21" y1="12" x2="9" y2="12"></line>
									</svg>
									<?php esc_html_e( 'Sign Out', 'business-directory' ); ?>
								</a>
							</li>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if current user has claimed businesses
	 *
	 * @return bool
	 */
	private static function user_has_businesses() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		global $wpdb;
		$claims_table = $wpdb->prefix . 'bd_claim_requests';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $claims_table )
		);

		if ( ! $table_exists ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$claims_table} WHERE user_id = %d AND status = 'approved'",
				$user_id
			)
		);

		return $count > 0;
	}
}
