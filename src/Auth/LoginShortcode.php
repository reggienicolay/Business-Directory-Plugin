<?php
/**
 * Login Shortcode
 *
 * Renders [bd_login] shortcode with login, register, and reset tabs.
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
 * Class LoginShortcode
 */
class LoginShortcode {

	/**
	 * Initialize shortcode
	 */
	public static function init() {
		add_shortcode( 'bd_login', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
	}

	/**
	 * Maybe enqueue assets
	 */
	public static function maybe_enqueue_assets() {
		global $post;

		if ( ! $post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'bd_login' ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Enqueue assets
	 */
	public static function enqueue_assets() {
		wp_enqueue_style(
			'bd-auth',
			BD_PLUGIN_URL . 'assets/css/auth.css',
			array(),
			BD_VERSION
		);

		wp_enqueue_script(
			'bd-auth',
			BD_PLUGIN_URL . 'assets/js/auth.js',
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		wp_localize_script( 'bd-auth', 'bdAuth', self::get_js_data() );
	}

	/**
	 * Get JS localization data
	 *
	 * @return array
	 */
	public static function get_js_data() {
		return array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'bd_auth_nonce' ),
			'redirectTo' => self::get_redirect_to(),
			'i18n'       => array(
				'loggingIn'    => __( 'Signing in...', 'business-directory' ),
				'registering'  => __( 'Creating account...', 'business-directory' ),
				'sendingReset' => __( 'Sending reset link...', 'business-directory' ),
				'redirecting'  => __( 'Redirecting...', 'business-directory' ),
				'error'        => __( 'An error occurred. Please try again.', 'business-directory' ),
			),
		);
	}

	/**
	 * Get redirect_to value
	 *
	 * @return string
	 */
	private static function get_redirect_to() {
		if ( ! empty( $_GET['redirect_to'] ) ) {
			return esc_url( $_GET['redirect_to'] );
		}

		$referer = wp_get_referer();
		if ( $referer && strpos( $referer, '/login' ) === false ) {
			return $referer;
		}

		return home_url();
	}

	/**
	 * Render shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts( array(
			'tab'         => '',
			'redirect_to' => '',
			'show_title'  => 'yes',
		), $atts );

		// If logged in, show profile link.
		if ( is_user_logged_in() ) {
			return self::render_logged_in();
		}

		// Determine active tab.
		$active_tab = $atts['tab'];
		if ( empty( $active_tab ) && isset( $_GET['tab'] ) ) {
			$active_tab = sanitize_key( $_GET['tab'] );
		}
		if ( ! in_array( $active_tab, array( 'login', 'register', 'reset' ), true ) ) {
			$active_tab = 'login';
		}

		// Check for password reset key.
		$reset_key   = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
		$reset_login = isset( $_GET['login'] ) ? sanitize_user( $_GET['login'] ) : '';

		$redirect_to = $atts['redirect_to'] ? $atts['redirect_to'] : self::get_redirect_to();

		ob_start();
		?>
		<div class="bd-auth-page">
			<div class="bd-auth-container">

				<?php if ( 'yes' === $atts['show_title'] ) : ?>
				<div class="bd-auth-header">
					<div class="bd-auth-logo">
						<?php if ( has_custom_logo() ) : ?>
							<?php the_custom_logo(); ?>
						<?php else : ?>
							<span class="bd-auth-site-name"><?php bloginfo( 'name' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Tab Navigation -->
				<div class="bd-auth-tabs">
					<button type="button" 
						class="bd-auth-tab <?php echo 'login' === $active_tab ? 'active' : ''; ?>" 
						data-tab="login">
						<?php esc_html_e( 'Sign In', 'business-directory' ); ?>
					</button>
					<button type="button" 
						class="bd-auth-tab <?php echo 'register' === $active_tab ? 'active' : ''; ?>" 
						data-tab="register">
						<?php esc_html_e( 'Create Account', 'business-directory' ); ?>
					</button>
				</div>

				<!-- Messages -->
				<div class="bd-auth-messages"></div>

				<!-- Login Form -->
				<div class="bd-auth-panel <?php echo 'login' === $active_tab ? 'active' : ''; ?>" 
					data-panel="login">

					<?php echo self::render_social_login(); ?>

					<div class="bd-auth-divider">
						<span><?php esc_html_e( 'or', 'business-directory' ); ?></span>
					</div>

					<form class="bd-auth-form" id="bd-login-form">
						<input type="hidden" name="action" value="bd_ajax_login">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bd_auth_nonce' ) ); ?>">
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">

						<div class="bd-form-group">
							<label for="bd-login-username">
								<?php esc_html_e( 'Username or Email', 'business-directory' ); ?>
							</label>
							<input type="text" 
								id="bd-login-username" 
								name="username" 
								required 
								autocomplete="username"
								placeholder="<?php esc_attr_e( 'Enter your username or email', 'business-directory' ); ?>">
						</div>

						<div class="bd-form-group">
							<label for="bd-login-password">
								<?php esc_html_e( 'Password', 'business-directory' ); ?>
							</label>
							<input type="password" 
								id="bd-login-password" 
								name="password" 
								required 
								autocomplete="current-password"
								placeholder="<?php esc_attr_e( 'Enter your password', 'business-directory' ); ?>">
						</div>

						<div class="bd-form-row bd-form-row-flex">
							<label class="bd-checkbox-label">
								<input type="checkbox" name="remember" value="1">
								<span><?php esc_html_e( 'Remember me', 'business-directory' ); ?></span>
							</label>
							<a href="#" class="bd-forgot-link" data-show-panel="reset">
								<?php esc_html_e( 'Forgot password?', 'business-directory' ); ?>
							</a>
						</div>

						<button type="submit" class="bd-btn bd-btn-primary bd-btn-full">
							<?php esc_html_e( 'Sign In', 'business-directory' ); ?>
						</button>
					</form>
				</div>

				<!-- Register Form -->
				<div class="bd-auth-panel <?php echo 'register' === $active_tab ? 'active' : ''; ?>" 
					data-panel="register">

					<?php echo self::render_social_login( 'register' ); ?>

					<div class="bd-auth-divider">
						<span><?php esc_html_e( 'or create with email', 'business-directory' ); ?></span>
					</div>

					<form class="bd-auth-form" id="bd-register-form">
						<input type="hidden" name="action" value="bd_ajax_register">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bd_auth_nonce' ) ); ?>">
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">

						<div class="bd-form-group">
							<label for="bd-register-username">
								<?php esc_html_e( 'Username', 'business-directory' ); ?>
							</label>
							<input type="text" 
								id="bd-register-username" 
								name="username" 
								required 
								autocomplete="username"
								minlength="3"
								placeholder="<?php esc_attr_e( 'Choose a username', 'business-directory' ); ?>">
						</div>

						<div class="bd-form-group">
							<label for="bd-register-email">
								<?php esc_html_e( 'Email', 'business-directory' ); ?>
							</label>
							<input type="email" 
								id="bd-register-email" 
								name="email" 
								required 
								autocomplete="email"
								placeholder="<?php esc_attr_e( 'your@email.com', 'business-directory' ); ?>">
						</div>

						<div class="bd-form-group">
							<label for="bd-register-password">
								<?php esc_html_e( 'Password', 'business-directory' ); ?>
							</label>
							<input type="password" 
								id="bd-register-password" 
								name="password" 
								required 
								autocomplete="new-password"
								minlength="8"
								placeholder="<?php esc_attr_e( 'At least 8 characters', 'business-directory' ); ?>">
						</div>

						<div class="bd-form-group">
							<label for="bd-register-city">
								<?php esc_html_e( 'Your City', 'business-directory' ); ?>
							</label>
							<?php echo self::render_city_select(); ?>
						</div>

						<div class="bd-form-group">
							<label class="bd-checkbox-label">
								<input type="checkbox" name="terms" value="1" required>
								<span>
									<?php
									printf(
										/* translators: %s: link to terms page */
										esc_html__( 'I agree to the %s', 'business-directory' ),
										'<a href="' . esc_url( home_url( '/terms/' ) ) . '" target="_blank">' .
										esc_html__( 'Terms of Service', 'business-directory' ) . '</a>'
									);
									?>
								</span>
							</label>
						</div>

						<button type="submit" class="bd-btn bd-btn-primary bd-btn-full">
							<?php esc_html_e( 'Create Account', 'business-directory' ); ?>
						</button>
					</form>
				</div>

				<!-- Reset Password Form -->
				<div class="bd-auth-panel <?php echo 'reset' === $active_tab ? 'active' : ''; ?>" 
					data-panel="reset">

					<?php if ( $reset_key && $reset_login ) : ?>
						<?php echo self::render_new_password_form( $reset_key, $reset_login ); ?>
					<?php else : ?>
						<div class="bd-auth-reset-intro">
							<p>
								<?php
								esc_html_e(
									'Enter your username or email to receive a password reset link.',
									'business-directory'
								);
								?>
							</p>
						</div>

						<form class="bd-auth-form" id="bd-reset-form">
							<input type="hidden" name="action" value="bd_ajax_reset_password">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bd_auth_nonce' ) ); ?>">

							<div class="bd-form-group">
								<label for="bd-reset-login">
									<?php esc_html_e( 'Username or Email', 'business-directory' ); ?>
								</label>
								<input type="text" 
									id="bd-reset-login" 
									name="user_login" 
									required 
									placeholder="<?php esc_attr_e( 'Enter your username or email', 'business-directory' ); ?>">
							</div>

							<button type="submit" class="bd-btn bd-btn-primary bd-btn-full">
								<?php esc_html_e( 'Send Reset Link', 'business-directory' ); ?>
							</button>
						</form>

						<p class="bd-auth-back-link">
							<a href="#" data-show-panel="login">
								&larr; <?php esc_html_e( 'Back to Sign In', 'business-directory' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render logged in state
	 *
	 * @return string
	 */
	private static function render_logged_in() {
		$user = wp_get_current_user();

		ob_start();
		?>
		<div class="bd-auth-logged-in">
			<div class="bd-auth-user-info">
				<?php echo get_avatar( $user->ID, 64 ); ?>
				<h3><?php echo esc_html( $user->display_name ); ?></h3>
				<p><?php esc_html_e( 'You are already signed in.', 'business-directory' ); ?></p>
			</div>
			<div class="bd-auth-actions">
				<a href="<?php echo esc_url( home_url( '/my-profile/' ) ); ?>" class="bd-btn bd-btn-primary">
					<?php esc_html_e( 'Go to Profile', 'business-directory' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="bd-btn bd-btn-secondary">
					<?php esc_html_e( 'Sign Out', 'business-directory' ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render social login buttons
	 *
	 * @param string $context Context: login or register.
	 * @return string
	 */
	private static function render_social_login( $context = 'login' ) {
		ob_start();
		?>
		<div class="bd-social-login">
			<?php
			// Nextend Social Login.
			if ( function_exists( 'nsl_render_buttons' ) ) {
				echo do_shortcode( '[nextend_social_login]' );
			} elseif ( shortcode_exists( 'nextend_social_login' ) ) {
				echo do_shortcode( '[nextend_social_login]' );
			} else {
				// Fallback buttons (non-functional, for design preview).
				?>
				<p class="bd-social-login-note">
					<?php esc_html_e( 'Social login will appear here once configured.', 'business-directory' ); ?>
				</p>
				<?php
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render city select dropdown
	 *
	 * @return string
	 */
	private static function render_city_select() {
		// Get areas from taxonomy.
		$areas = get_terms( array(
			'taxonomy'   => 'bd_area',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		ob_start();
		?>
		<select id="bd-register-city" name="city" class="bd-form-select">
			<option value=""><?php esc_html_e( 'Select your city (optional)', 'business-directory' ); ?></option>
			<?php if ( ! is_wp_error( $areas ) && ! empty( $areas ) ) : ?>
				<?php foreach ( $areas as $area ) : ?>
					<option value="<?php echo esc_attr( $area->slug ); ?>">
						<?php echo esc_html( $area->name ); ?>
					</option>
				<?php endforeach; ?>
			<?php endif; ?>
			<option value="other"><?php esc_html_e( 'Other', 'business-directory' ); ?></option>
		</select>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render new password form (after clicking reset link)
	 *
	 * @param string $key   Reset key.
	 * @param string $login Username.
	 * @return string
	 */
	private static function render_new_password_form( $key, $login ) {
		// Verify the key is valid.
		$user = check_password_reset_key( $key, $login );

		if ( is_wp_error( $user ) ) {
			ob_start();
			?>
			<div class="bd-auth-error">
				<p>
					<?php esc_html_e( 'This password reset link is invalid or has expired.', 'business-directory' ); ?>
				</p>
				<a href="#" class="bd-btn bd-btn-primary" data-show-panel="reset">
					<?php esc_html_e( 'Request New Link', 'business-directory' ); ?>
				</a>
			</div>
			<?php
			return ob_get_clean();
		}

		ob_start();
		?>
		<div class="bd-auth-reset-intro">
			<p><?php esc_html_e( 'Enter your new password below.', 'business-directory' ); ?></p>
		</div>

		<form class="bd-auth-form" id="bd-new-password-form" method="post" 
			action="<?php echo esc_url( site_url( 'wp-login.php?action=resetpass' ) ); ?>">
			<input type="hidden" name="rp_key" value="<?php echo esc_attr( $key ); ?>">
			<input type="hidden" name="rp_login" value="<?php echo esc_attr( $login ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( home_url( '/login/?reset=success' ) ); ?>">

			<div class="bd-form-group">
				<label for="bd-new-password">
					<?php esc_html_e( 'New Password', 'business-directory' ); ?>
				</label>
				<input type="password" 
					id="bd-new-password" 
					name="pass1" 
					required 
					autocomplete="new-password"
					minlength="8"
					placeholder="<?php esc_attr_e( 'At least 8 characters', 'business-directory' ); ?>">
			</div>

			<div class="bd-form-group">
				<label for="bd-confirm-password">
					<?php esc_html_e( 'Confirm Password', 'business-directory' ); ?>
				</label>
				<input type="password" 
					id="bd-confirm-password" 
					name="pass2" 
					required 
					autocomplete="new-password"
					placeholder="<?php esc_attr_e( 'Confirm your new password', 'business-directory' ); ?>">
			</div>

			<button type="submit" class="bd-btn bd-btn-primary bd-btn-full">
				<?php esc_html_e( 'Reset Password', 'business-directory' ); ?>
			</button>
		</form>
		<?php
		return ob_get_clean();
	}
}
